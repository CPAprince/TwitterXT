import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';
import sql from 'k6/x/sql';
import driver from 'k6/x/sql/driver/mysql';

/* =========================================================================
   🔧 CONFIGURATION & PARAMS
   ========================================================================= */

const BASE_URL = __ENV.BASE_URL || 'https://localhost';

// Minimum required logged-in users pool.
// Uses pre-warmed active refresh tokens from DB. If fewer, seeds missing users.
const MIN_LOGGED_IN_USERS = parseInt(__ENV.MIN_LOGGED_IN_USERS || '1000', 10);

// Probability (%) that an authenticated user creates a new tweet on this iteration
const POST_PROBABILITY = parseFloat(__ENV.POST_PROBABILITY || '17');

const MySQL_USER = __ENV.MYSQL_USER || 'app';
const MySQL_PASSWORD = __ENV.MYSQL_PASSWORD || '!ChangeMe!';
const MySQL_IP = __ENV.MYSQL_IP || '127.0.0.1';
const MySQL_PORT = __ENV.MYSQL_PORT || '3306';
const MySQL_DATABASE = __ENV.MYSQL_DATABASE || 'app';

const userLoginShare = parseInt(__ENV.USER_LOGIN_SHARE || '100', 10); // % iterations that authenticate
const maxFeedPages = parseInt(__ENV.FEED_PAGES || '15', 10);          // Max feed scroll depth
const scrollProbability = parseFloat(__ENV.SCROLL_PROB || '0.9');    // Probability of scrolling to next page
const FEED_LIMIT = parseInt(__ENV.FEED_LIMIT || '50', 10);

const THINK_TIME_MIN = parseFloat(__ENV.THINK_TIME_MIN || '0');
const THINK_TIME_MAX = parseFloat(__ENV.THINK_TIME_MAX || '0');

/* =========================================================================
   📊 K6 OPTIONS & THRESHOLDS (8 VUs default for optimal P-core saturation)
   ========================================================================= */

const endpoints = [
  'tweets_page1',
  'tweets_page2',
  'tweets_page3',
  'tweets_page4plus',
  'ProfileNameBio',
  'ProfileFeed',
  'Like',
  'CreateTweet',
  'TokenRefresh',
  'Login',
];

export const options = {
  insecureSkipTLSVerify: true,
  setupTimeout: '15m', // Generous headroom for setup & pre-loading

  stages: [
    { duration: '10s', target: 8 },
    { duration: '1m', target: 8 },
    { duration: '3m', target: 8 },
    { duration: '3m', target: 8 },
    { duration: '1m', target: 8 },
  ],

  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_waiting: ['p(95)<10000'],
    ...endpoints.reduce(
      (acc, endpoint) => ({
        ...acc,
        [`http_req_duration{endpoint:${endpoint}}`]: ['p(95)<10000'],
        [`http_reqs{endpoint:${endpoint}}`]: ['count>=0'],
      }),
      {}
    ),
  },
};

/* =========================================================================
   🛠 UTILITIES
   ========================================================================= */

function asciiToString(bytes) {
  if (!bytes) return '';
  if (typeof bytes === 'string') return bytes;
  return String.fromCharCode(...bytes);
}

// UTF-8 multi-byte decoder to preserve Cyrillic, emojis, and symbols from MySQL bytes
function utf8BytesToString(bytes) {
  if (!bytes) return '';
  if (typeof bytes === 'string') return bytes;

  let out = '';
  let i = 0;

  while (i < bytes.length) {
    const c = bytes[i++];

    if (c < 128) {
      out += String.fromCharCode(c);
    } else if (c > 191 && c < 224) {
      const c2 = bytes[i++];
      out += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
    } else if (c > 223 && c < 240) {
      const c2 = bytes[i++];
      const c3 = bytes[i++];
      out += String.fromCharCode(
        ((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63)
      );
    } else {
      const c2 = bytes[i++];
      const c3 = bytes[i++];
      const c4 = bytes[i++];

      let codepoint =
        ((c & 7) << 18) |
        ((c2 & 63) << 12) |
        ((c3 & 63) << 6) |
        (c4 & 63);

      codepoint -= 0x10000;
      out += String.fromCharCode(
        0xd800 + (codepoint >> 10),
        0xdc00 + (codepoint & 0x3ff)
      );
    }
  }

  return out;
}

function randomItem(arr) {
  if (!arr || arr.length === 0) return null;
  return arr[Math.floor(Math.random() * arr.length)];
}

function randomSleep() {
  if (THINK_TIME_MAX <= 0) return;
  const t = THINK_TIME_MIN + Math.random() * (THINK_TIME_MAX - THINK_TIME_MIN);
  sleep(t);
}

function authHeaders(jwt) {
  return jwt
    ? { headers: { Authorization: `Bearer ${jwt}` } }
    : {};
}

function shouldLogin() {
  return Math.random() * 100 < userLoginShare;
}

/* =========================================================================
   🔐 AUTH API HELPERS
   ========================================================================= */

function executeLogin(email) {
  const payload = JSON.stringify({
    email: email,
    password: 'Pa$$word1',
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    tags: {
      endpoint: 'Login',
      name: 'POST /api/token',
    },
  };

  const res = http.post(`${BASE_URL}/api/token`, payload, params);

  check(res, {
    'login status is 200': (r) => r.status === 200,
    'login JWT received': (r) => !!r.json('token'),
  });

  if (res.status === 200) {
    return {
      token: res.json('token'),
      refreshToken: res.json('refresh_token'),
    };
  }

  return null;
}

function executeTokenRefresh(refreshTokenStr) {
  const payload = JSON.stringify({
    refresh_token: refreshTokenStr,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    tags: {
      endpoint: 'TokenRefresh',
      name: 'POST /api/token/refresh',
    },
  };

  const res = http.post(`${BASE_URL}/api/token/refresh`, payload, params);

  check(res, {
    'refresh status is 200': (r) => r.status === 200,
    'refreshed JWT received': (r) => !!r.json('token'),
  });

  if (res.status === 200) {
    return res.json('token');
  }

  return null;
}

/* =========================================================================
   📦 SETUP (User Pool, Profiles, Likes, & Realistic Tweet Contents)
   ========================================================================= */

export function setup() {
  console.log(`\x1b[34m[SETUP]\x1b[0m Connecting to MySQL database at ${MySQL_IP}:${MySQL_PORT}...`);
  const db = sql.open(
    driver,
    `${MySQL_USER}:${MySQL_PASSWORD}@tcp(${MySQL_IP}:${MySQL_PORT})/${MySQL_DATABASE}`
  );

  // 1. Fetch active refresh tokens from MySQL
  console.log(`\x1b[34m[SETUP]\x1b[0m Querying active refresh tokens (valid > NOW())...`);
  const activeTokenRows = db.query(
    'SELECT MAX(refresh_token) as refresh_token, username FROM app.refresh_tokens WHERE valid > NOW() GROUP BY username LIMIT 50000;'
  );

  const pool = [];
  for (const row of activeTokenRows) {
    pool.push({
      username: asciiToString(row.username),
      refreshToken: asciiToString(row.refresh_token),
      token: null,
      tokenExpiresAt: 0,
    });
  }

  const existingCount = pool.length;
  console.log(
    `\x1b[34m[SETUP]\x1b[0m Found \x1b[32m${existingCount}\x1b[0m users with valid refresh tokens in DB (Required minimum: \x1b[33m${MIN_LOGGED_IN_USERS}\x1b[0m).`
  );

  // 2. If fewer than MIN_LOGGED_IN_USERS, seed missing users in parallel batches of 5
  if (existingCount < MIN_LOGGED_IN_USERS) {
    const needed = MIN_LOGGED_IN_USERS - existingCount;
    console.log(
      `\x1b[34m[SETUP]\x1b[0m Need to seed \x1b[33m${needed}\x1b[0m more users via /api/token to reach minimum pool size...`
    );

    const unauthRows = db.query(
      `SELECT email FROM app.users WHERE email NOT IN (
         SELECT DISTINCT username FROM app.refresh_tokens WHERE valid > NOW()
       ) LIMIT ${needed};`
    );

    const emailsToSeed = [];
    for (const row of unauthRows) {
      emailsToSeed.push(asciiToString(row.email));
    }

    let seededCount = 0;
    const BATCH_SIZE = 5; // Matches the 5 FrankenPHP workers
    for (let i = 0; i < emailsToSeed.length; i += BATCH_SIZE) {
      const chunk = emailsToSeed.slice(i, i + BATCH_SIZE);
      const batchRequests = chunk.map((email) => [
        'POST',
        `${BASE_URL}/api/token`,
        JSON.stringify({ email: email, password: 'Pa$$word1' }),
        {
          headers: { 'Content-Type': 'application/json' },
          tags: { endpoint: 'Login', name: 'POST /api/token' },
        },
      ]);

      const responses = http.batch(batchRequests);
      for (let j = 0; j < responses.length; j++) {
        const res = responses[j];
        if (res.status === 200) {
          const body = res.json();
          if (body && body.refresh_token) {
            pool.push({
              username: chunk[j],
              refreshToken: body.refresh_token,
              token: body.token,
              tokenExpiresAt: Date.now() + 840000,
            });
            seededCount++;
          }
        }
      }
      if (seededCount % 50 === 0 && seededCount > 0) {
        console.log(`\x1b[34m[SETUP]\x1b[0m Seeded ${seededCount}/${needed} users...`);
      }
    }

    console.log(
      `\x1b[32m[SETUP]\x1b[0m Successfully seeded ${seededCount} new users. Total active pool: ${pool.length}`
    );
  } else {
    console.log(
      `\x1b[32m[SETUP]\x1b[0m Pool already satisfies minimum required size. Using all \x1b[36m${pool.length}\x1b[0m active user sessions!`
    );
  }

  // 3. Pre-load profile UUIDs
  console.log(`\x1b[34m[SETUP]\x1b[0m Loading profile UUIDs...`);
  const profileRows = db.query(
    'SELECT distinct BIN_TO_UUID(user_id) as profile_uuid FROM app.tweets limit 10000;'
  );
  const profilesAvailable = [];
  for (const row of profileRows) {
    profilesAvailable.push(asciiToString(row.profile_uuid));
  }

  // 4. Pre-load tweet IDs for likes
  console.log(`\x1b[34m[SETUP]\x1b[0m Loading tweet IDs for likes...`);
  const tweetRows = db.query('SELECT BIN_TO_UUID(id) as tweet_id FROM app.tweets limit 10000;');
  const tweetsAvailable = [];
  for (const row of tweetRows) {
    tweetsAvailable.push(asciiToString(row.tweet_id));
  }

  // 5. Pre-load realistic tweet content for posting (UTF-8 decoded)
  console.log(`\x1b[34m[SETUP]\x1b[0m Loading realistic tweet content for posting...`);
  const tweetContentRows = db.query(
    "SELECT DISTINCT content FROM app.tweets WHERE created_at <= '2026-02-25' ORDER BY RAND() LIMIT 30000;"
  );
  const tweets4postingAvailable = [];
  for (const row of tweetContentRows) {
    tweets4postingAvailable.push(utf8BytesToString(row.content));
  }

  db.close();

  console.log(
    `\x1b[33m#SETUP COMPLETE#\x1b[0m Logged-in Pool: \x1b[36m${pool.length}\x1b[0m | Profiles: \x1b[36m${profilesAvailable.length}\x1b[0m | TweetsToLike: \x1b[36m${tweetsAvailable.length}\x1b[0m | TweetsToPost: \x1b[36m${tweets4postingAvailable.length}\x1b[0m`
  );

  return {
    userPool: pool,
    profilesAvailable,
    tweetsAvailable,
    tweets4postingAvailable,
  };
}

/* =========================================================================
   🔑 PER-VU ACCESS TOKEN MANAGER
   ========================================================================= */

// In-memory token cache isolated to each VU thread: username -> { token, expiresAt }
const vuTokenCache = {};

function getOrRefreshAccessToken(user) {
  const now = Date.now();
  const cached = vuTokenCache[user.username];

  // 1. If VU has an unexpired access token (safety margin 60s), use it directly (0ms HTTP cost!)
  if (cached && cached.expiresAt > now + 60000) {
    return cached.token;
  }

  // 2. If user session had a fresh token from setup(), populate cache
  if (user.token && user.tokenExpiresAt && user.tokenExpiresAt > now + 60000) {
    vuTokenCache[user.username] = {
      token: user.token,
      expiresAt: user.tokenExpiresAt,
    };
    return user.token;
  }

  // 3. Token expired or not yet issued: refresh via /api/token/refresh (5-15ms, NO bcrypt!)
  const newToken = executeTokenRefresh(user.refreshToken);
  if (newToken) {
    vuTokenCache[user.username] = {
      token: newToken,
      expiresAt: now + 840000, // 14 minutes
    };
    return newToken;
  }

  // 4. Fallback: if refresh token failed (e.g. revoked), re-login directly
  const freshAuth = executeLogin(user.username);
  if (freshAuth && freshAuth.token) {
    user.refreshToken = freshAuth.refreshToken;
    vuTokenCache[user.username] = {
      token: freshAuth.token,
      expiresAt: now + 840000,
    };
    return freshAuth.token;
  }

  return null;
}

/* =========================================================================
   📰 USER ACTIONS
   ========================================================================= */

function mainFeed(jwt) {
  let page = 1;

  while (page <= maxFeedPages) {
    const endpointTag =
      page === 1
        ? 'tweets_page1'
        : page === 2
          ? 'tweets_page2'
          : page === 3
            ? 'tweets_page3'
            : 'tweets_page4plus';

    group(`GET /api/tweets (page=${page})`, () => {
      http.get(`${BASE_URL}/api/tweets?limit=${FEED_LIMIT}&page=${page}`, {
        ...authHeaders(jwt),
        tags: {
          endpoint: endpointTag,
          name: 'GET /api/tweets',
        },
      });
    });

    // Probabilistic scrolling: 90% chance to scroll to the next page, up to maxFeedPages
    if (page === maxFeedPages || Math.random() > scrollProbability) break;
    page++;
    randomSleep();
  }
}

function getProfile(jwt, profilesAvailable) {
  const profileId = randomItem(profilesAvailable);
  if (!profileId) return;

  group('GET /api/profiles/{id}', () => {
    http.get(`${BASE_URL}/api/profiles/${profileId}`, {
      ...authHeaders(jwt),
      tags: {
        endpoint: 'ProfileNameBio',
        name: 'GET /api/profiles/{id}',
      },
    });
  });

  group('GET /api/profiles/{id}/tweets', () => {
    http.get(`${BASE_URL}/api/profiles/${profileId}/tweets?limit=${FEED_LIMIT}&page=1`, {
      ...authHeaders(jwt),
      tags: {
        endpoint: 'ProfileFeed',
        name: 'GET /api/profiles/{id}/tweets',
      },
    });
  });
}

function toggleLike(jwt, tweetsAvailable) {
  if (!jwt) return;
  const tweetId = randomItem(tweetsAvailable);
  if (!tweetId) return;

  group('POST /api/tweets/{id}/likes/toggle', () => {
    http.post(`${BASE_URL}/api/tweets/${tweetId}/likes/toggle`, null, {
      ...authHeaders(jwt),
      tags: {
        endpoint: 'Like',
        name: 'POST /api/tweets/{id}/likes/toggle',
      },
    });
  });
}

function createTweet(jwt, tweets4postingAvailable) {
  if (!jwt) return;
  const tweetContent = randomItem(tweets4postingAvailable);
  if (!tweetContent) return;

  const payload = JSON.stringify({
    content: tweetContent,
  });

  const params = {
    headers: {
      Authorization: `Bearer ${jwt}`,
      'Content-Type': 'application/json',
    },
    tags: {
      endpoint: 'CreateTweet',
      name: 'POST /api/tweets',
    },
  };

  group('POST /api/tweets', () => {
    const res = http.post(`${BASE_URL}/api/tweets`, payload, params);
    check(res, {
      'tweet created status is 201': (r) => r.status === 201,
      'tweet has tweetId': (r) => !!r.json('tweetId'),
    });
  });
}

/* =========================================================================
   🚶 USER JOURNEY (Main Scenario Execution)
   ========================================================================= */

export default function (data) {
  let jwt = null;

  // 1. Anonymous feed browsing (discovery with probabilistic scrolling)
  mainFeed(null);

  // 2. Select a user from the pre-authenticated session pool
  if (shouldLogin() && data.userPool && data.userPool.length > 0) {
    const user = randomItem(data.userPool);
    jwt = getOrRefreshAccessToken(user);
  }

  // 3. Authenticated feed browsing (reflects newly posted tweets / cache updates)
  mainFeed(jwt);

  // 4. View 1 to 3 author profiles
  const profileViews = 1 + Math.floor(Math.random() * 3);
  for (let i = 0; i < profileViews; i++) {
    getProfile(jwt, data.profilesAvailable);
    randomSleep();
  }

  // 5. Tweet creation (17% probability by default - invalidates Redis cache & dispatches to RabbitMQ)
  if (jwt && Math.random() * 100 < POST_PROBABILITY && data.tweets4postingAvailable && data.tweets4postingAvailable.length > 0) {
    createTweet(jwt, data.tweets4postingAvailable);
    randomSleep();
  }

  // 6. Toggle 0 to 2 likes
  const likes = jwt ? Math.floor(Math.random() * 3) : 0;
  for (let i = 0; i < likes; i++) {
    toggleLike(jwt, data.tweetsAvailable);
    randomSleep();
  }

  randomSleep();
}

export function handleSummary(data) {
  return {
    stdout: textSummary(data, {
      indent: ' ',
      enableColors: true,
    }),
  };
}
