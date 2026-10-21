(function () {
  const STORAGE_PREFIX = "pending_tweets_";
  const MAX_AGE_MS = 15 * 60 * 1000; // 15 minutes
  const BACKOFF_INTERVALS = [3000, 5000, 10000, 20000, 30000];

  let _pollTimer = null;
  let _backoffIndex = 0;
  let _isPolling = false;

  function getCurrentUserId() {
    return (
      localStorage.getItem("userId") ||
      (window.Auth && window.Api
        ? window.Auth.getUserId(window.Api.getToken())
        : null)
    );
  }

  function getStorageKey(userId) {
    const uid = userId || getCurrentUserId();
    return uid ? `${STORAGE_PREFIX}${uid}` : null;
  }

  function getAll(userId) {
    const key = getStorageKey(userId);
    if (!key) return [];

    try {
      const stored = localStorage.getItem(key);
      if (!stored) return [];

      const list = JSON.parse(stored);
      if (!Array.isArray(list)) return [];

      const now = Date.now();
      const valid = [];
      let hadExpired = false;

      for (const item of list) {
        const createdTime = item?.createdAt
          ? new Date(item.createdAt).getTime()
          : now;
        if (now - createdTime <= MAX_AGE_MS) {
          valid.push(item);
        } else {
          hadExpired = true;
        }
      }

      if (hadExpired) {
        if (valid.length > 0) {
          localStorage.setItem(key, JSON.stringify(valid));
        } else {
          localStorage.removeItem(key);
        }
      }

      return valid;
    } catch {
      return [];
    }
  }

  function getById(tweetId, userId) {
    const list = getAll(userId);
    return list.find((item) => item.id === tweetId) || null;
  }

  function add(item) {
    if (!item?.id) return;

    const uid = item.authorId || getCurrentUserId();
    const key = getStorageKey(uid);
    if (!key) return;

    const list = getAll(uid);
    const existingIndex = list.findIndex((x) => x.id === item.id);

    const pendingItem = {
      id: item.id,
      content: item.content || "",
      createdAt: item.createdAt || new Date().toISOString(),
      authorId: uid,
      authorName: item.authorName || window.profileData?.name || "You",
      kind: item.kind || "create",
    };

    if (existingIndex >= 0) {
      list[existingIndex] = pendingItem;
    } else {
      list.unshift(pendingItem);
    }

    localStorage.setItem(key, JSON.stringify(list));
    _backoffIndex = 0;
    startPolling();
  }

  function remove(tweetId, userId) {
    const uid = userId || getCurrentUserId();
    const key = getStorageKey(uid);
    if (!key) return;

    const list = getAll(uid).filter((x) => x.id !== tweetId);
    if (list.length > 0) {
      localStorage.setItem(key, JSON.stringify(list));
    } else {
      localStorage.removeItem(key);
      stopPolling();
    }
  }

  function clear(userId) {
    const key = getStorageKey(userId);
    if (key) {
      localStorage.removeItem(key);
    }
    stopPolling();
  }

  function clearCurrent() {
    clear(getCurrentUserId());
  }

  function stopPolling() {
    if (_pollTimer) {
      clearTimeout(_pollTimer);
      _pollTimer = null;
    }
    _backoffIndex = 0;
  }

  function showRejectionAlert(message) {
    const container =
      document.querySelector(".tweets-section") ||
      document.querySelector("main .container-fluid") ||
      document.body;

    if (container) {
      const alertEl = document.createElement("div");
      alertEl.className = "alert alert-danger alert-dismissible fade show my-2";
      alertEl.role = "alert";
      alertEl.innerHTML = `<span>${message}</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
      container.prepend(alertEl);
    } else if (typeof window.alert === "function") {
      window.alert(message);
    }
  }

  async function _pollTick() {
    _pollTimer = null;

    if (document.visibilityState === "hidden") {
      return;
    }

    const items = getAll();
    if (items.length === 0) {
      stopPolling();
      return;
    }

    if (!window.Api || !window.Api.get) {
      return;
    }

    _isPolling = true;

    try {
      for (const item of items) {
        try {
          const res = await window.Api.get(
            `/api/tweets/${item.id}/moderation-status`
          );
          const status = res?.status;

          if (status === "approved") {
            remove(item.id, item.authorId);

            if (window.TweetsList?.reload) {
              document
                .querySelectorAll(".tweets-section[data-tweets-url]")
                .forEach((el) => window.TweetsList.reload(el));
            }

            const currentPathId = window.location.pathname
              .replace("/t/", "")
              .replace(/\/$/, "");
            if (currentPathId === item.id) {
              window.location.reload();
            }
          } else if (status === "rejected") {
            remove(item.id, item.authorId);

            document
              .querySelectorAll(`.tweet[data-tweet-id="${item.id}"]`)
              .forEach((el) => el.remove());

            showRejectionAlert(
              "Your tweet was not published: it did not pass moderation"
            );

            const currentPathId = window.location.pathname
              .replace("/t/", "")
              .replace(/\/$/, "");
            if (currentPathId === item.id) {
              const errorBlock = document.getElementById("tweet-error");
              if (errorBlock) {
                const details = document.getElementById("tweet-error-details");
                if (details) {
                  details.textContent =
                    "Your tweet was not published: it did not pass moderation.";
                }
                errorBlock.style.display = "block";
              }
              const tweetEl = document.querySelector(".tweet");
              if (tweetEl) tweetEl.style.display = "none";
            }
          }
        } catch (err) {
          if (err?.status === 404) {
            remove(item.id, item.authorId);
            const currentPathId = window.location.pathname
              .replace("/t/", "")
              .replace(/\/$/, "");
            if (currentPathId === item.id) {
              const errorBlock = document.getElementById("tweet-error");
              if (errorBlock) errorBlock.style.display = "block";
              const tweetEl = document.querySelector(".tweet");
              if (tweetEl) tweetEl.style.display = "none";
            }
          } else if (err?.status === 403) {
            remove(item.id, item.authorId);
          }
        }
      }
    } finally {
      _isPolling = false;
      const remaining = getAll();
      if (remaining.length > 0) {
        _backoffIndex = Math.min(_backoffIndex + 1, BACKOFF_INTERVALS.length - 1);
        const delay = BACKOFF_INTERVALS[_backoffIndex];
        _pollTimer = setTimeout(_pollTick, delay);
      } else {
        stopPolling();
      }
    }
  }

  function startPolling() {
    if (_pollTimer || _isPolling) return;
    if (document.visibilityState === "hidden") return;

    const items = getAll();
    if (items.length === 0) return;

    const delay = BACKOFF_INTERVALS[Math.min(_backoffIndex, BACKOFF_INTERVALS.length - 1)];
    _pollTimer = setTimeout(_pollTick, delay);
  }

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      const items = getAll();
      if (items.length > 0) {
        _backoffIndex = 0;
        startPolling();
      }
    } else {
      stopPolling();
    }
  });

  document.addEventListener("DOMContentLoaded", () => {
    const items = getAll();
    if (items.length > 0) {
      startPolling();
    }
  });

  window.PendingTweets = {
    getAll,
    getById,
    add,
    remove,
    clear,
    clearCurrent,
    startPolling,
    stopPolling,
  };
})();
