# Redis

This document outlines the architectural decisions regarding the integration of Redis for caching,
rate limiting, and concurrency control within this application.

## Caching

We utilize the **Proxy Pattern** to wrap query handlers. The proxy acts as a gateway: it checks
whether the requested data exists in Redis and returns it immediately; otherwise, it delegates
the call to the actual query handler and stores the result in the cache. This approach ensures
that domain logic remains isolated from infrastructure concerns.

### Strategy

- **Storage:** Redis (`cache.adapter.redis`) via Symfony Cache Component.
- **Tagging:** We use `TagAwareCacheInterface` to group related items (e.g., all tweets by a
  specific user) for precise invalidation.
- **Serialization:** Symfony's default serializer (IGBinary/PHP) is sufficient for current
  performance needs.

### Cache Keys & Structures

| Use Case        | Key Pattern                               | Tags                                      |
|:----------------|:------------------------------------------|:------------------------------------------|
| **Main Feed**   | `tweets_main_limit_{N}_page_{M}`          | `['tweets_main']`                         |
| **User Feed**   | `user_tweets_{userId}_limit_{N}_page_{M}` | `['user_tweets', 'user_tweets_{userId}']` |
| **Profile**     | `profile_{userId}`                        | `['profile', 'profile_{userId}']`         |

## Invalidation

We adopt a hybrid strategy of **Time-based Expiration (TTL)** and
**Event-driven Invalidation (Tags)**.

### Triggers

1. **Profile Update:**
    * **Trigger:** `UpdateProfileCommandHandler`.
    * **Action:** Invalidate tag `profile_{userId}`.
    * **Reasoning:** Users expect immediate feedback when editing their own profile.

2. **New Tweet Creation:**
    * **Trigger:** `CreateTweetCommandHandler`.
    * **Action:** Invalidate tag `user_tweets_{userId}`.
    * **Trade-off:** We **do not** invalidate the Main Feed (`tweets_main`). Doing so would
      cause a "Thundering Herd" effect on the database during high traffic. The Main Feed relies
      on short TTL (eventual consistency).

3. **Tweet Update:**
    * **Trigger:** `UpdateTweetCommandHandler`.
    * **Action:** Invalidate tag `user_tweets_{userId}`.
    * **Reasoning:** Ensures the user's profile feed reflects content changes (edits) immediately.

## Time to Live

All TTL values must be configurable via environment variables (`.env`) to allow tuning without
deployment.

### Policies

1. **Main Feed (`GetTweets`): 60 seconds**
    * **Justification:** High-traffic endpoint. A 60s window significantly reduces DB load while
      keeping content relatively fresh.
    * **Env Var:** `CACHE_TTL_FEED_MAIN`

2. **User Tweets (`GetUserTweets`): 600 seconds (10 minutes)**
    * **Justification:** Lower traffic than the main feed. Users visit specific profiles less
      frequently. 10 minutes serves as a fail-safe if explicit invalidation fails.
    * **Env Var:** `CACHE_TTL_FEED_USER`

3. **Profile (`GetProfile`): 3600 seconds (1 hour)**
    * **Justification:** Profile data (bio, name) changes very rarely (read-heavy, write-rare). Long
      TTL maximizes cache hit ratio.
    * **Env Var:** `CACHE_TTL_PROFILE`

## Rate Limiting

Implemented using **Symfony Rate Limiter** with Redis storage. This protects the system from abuse,
brute-force attacks, and ensures fair usage.

### Policies

1. **Tweet Creation (Spam Protection)**
    * **Algorithm:** Sliding Window.
    * **Limit:** 10 requests / 1 minute.
    * **Justification:** Prevents bot spam. Exceeds the physical capability of a human to generate
      meaningful content (P99 user behavior).
    * **Env Var:** `RATE_LIMIT_TWEET_CREATE`

2. **Likes (Abuse Protection)**
    * **Algorithm:** Token Bucket.
    * **Limit:** 20 burst, refill 1 per 2 seconds.
    * **Justification:** Allows natural bursts (liking a thread) but prevents mechanical "like
      farming" scripts.
    * **Env Var:** `RATE_LIMIT_LIKE`

3. **Login (Brute-force Protection)**
    * **Algorithm:** Token Bucket (per user+IP) and Sliding Window (global IP).
    * **Limit (Local):** 5 attempts / 15 minutes per IP + email.
    * **Limit (Global):** 50 attempts / 15 minutes per IP.
    * **Justification:**
      [Industry standard](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/04-Authentication_Testing/03-Testing_for_Weak_Lock_Out_Mechanism)
      strict limit to effectively mitigate credential stuffing and brute-force attacks.
    * **Env Vars:** `RATE_LIMIT_LOGIN_LOCAL` and `RATE_LIMIT_LOGIN_GLOBAL`.

## Lock

We distinguish between **User Action Locks** (UX/idempotency) and **Resource Locks** (data
integrity).

### 1. User Action Lock (Redis)

* **Purpose:** Prevent "double-click" issues (e.g., liking the same tweet twice instantly).
* **Mechanism:** Non-blocking Redis lock with short TTL (5s).
* **Behavior:** If locked, fail fast (HTTP 429 or 200 OK idempotent).
* **Why Redis:** Requires low latency to check lock status before processing the HTTP request.

### 2. Resource Contention (RabbitMQ vs Redis)

* **Scenario:** Updating `likes_count` on a viral tweet.
* **Decision:** We utilize **RabbitMQ** for serialization instead of Redis locks.

#### Architecture Decision: Redis vs RabbitMQ

While Redis locks *can* ensure data integrity by serializing access to a database row, they
introduce **blocking processes** (FrankenPHP workers hanging while waiting for a lock release). This
is not scalable under high load.

| Feature                 | Redis (Cache/Lock)                        | RabbitMQ (Queue)                               | Decision     |
|:------------------------|:------------------------------------------|:-----------------------------------------------|:-------------|
| **Rate Limiting**       | **Best Fit.** Fast, atomic counters.      | Not suitable (too late in lifecycle).          | **Redis**    |
| **Idempotency Lock**    | **Best Fit.** Immediate feedback to user. | Overhead is too high.                          | **Redis**    |
| **Write Serialization** | Blocking (performance bottleneck).        | **Best Fit.** Async processing, load leveling. | **RabbitMQ** |

**Conclusion:**

- Use **Redis** for "guard" logic (rate limits, idempotency locks) at the entry point of the
  application.
- Use **RabbitMQ** for "write" logic (counter updates) to offload the database and serialize
  operations asynchronously.
