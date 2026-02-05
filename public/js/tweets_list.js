(function () {
  const states = new WeakMap();

  function clear(el) {
    while (el.firstChild) el.removeChild(el.firstChild);
  }

  async function getJson(url) {
    if (window.Api?.get) return await window.Api.get(url);

    const res = await fetch(url, { headers: { Accept: "application/json" } });
    if (res.status === 204) return null;
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  }

  function normalizeTweetsPayload(data) {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.tweets)) return data.tweets;
    return [];
  }

  function resolveAuthorName(t, authorId) {
    const apiName = t?.author?.name;
    if (apiName && String(apiName).trim()) return apiName;

    const pageProfileName = window.profileData?.name;
    if (pageProfileName && String(pageProfileName).trim()) return pageProfileName;

    if (authorId) return `@${String(authorId).slice(0, 8)}`;
    return "Unknown";
  }

  function getLimit(container) {
    const v = parseInt(container.getAttribute("data-tweets-limit") || "", 10);
    return Number.isFinite(v) && v > 0 ? Math.min(100, v) : 100; // default 100
  }

  function isInfiniteEnabled(container) {
    return container.getAttribute("data-tweets-infinite") !== "0";
  }

  function getOrCreateSentinel(container) {
    const next = container.nextElementSibling;
    if (next && next.classList?.contains("tweets-sentinel")) return next;

    const sentinel = document.createElement("div");
    sentinel.className = "tweets-sentinel";
    sentinel.style.height = "1px";
    sentinel.setAttribute("aria-hidden", "true");

    container.insertAdjacentElement("afterend", sentinel);
    return sentinel;
  }

  function buildPageUrl(baseUrl, limit, page) {
    const u = new URL(baseUrl, window.location.origin);
    u.searchParams.set("limit", String(limit));
    u.searchParams.set("page", String(page));
    return u.toString();
  }

  function buildTweetNode(tplItem, t) {
    const node = tplItem.content.cloneNode(true);
    const root = node.querySelector(".tweet");

    const tweetId = t?.id ?? "";
    const authorId = t?.author?.id ?? t?.userId ?? "";
    const authorName = resolveAuthorName(t, authorId);
    const createdAt = t?.createdAt ?? "";
    const content = t?.content ?? "";
    const likes = t?.likesCount ?? 0;

    if (root) root.dataset.tweetId = tweetId;

    const authorLink = node.querySelector("[data-tweet-author-link]");
    const authorNameEl = node.querySelector("[data-tweet-author-name]");
    if (authorLink) authorLink.href = `/p/${authorId}`;
    if (authorNameEl) authorNameEl.textContent = authorName;

    const createdAtEl = node.querySelector("[data-tweet-created-at]");
    if (createdAtEl) createdAtEl.textContent = ' · ' + formatPublishDate(createdAt);

    const contentEl = node.querySelector("[data-tweet-content]");
    if (contentEl) contentEl.textContent = content;

    // like button
    const likeBtn = node.querySelector(".tweet-like-btn");
    const likeCount = node.querySelector(".tweet-like-count");
    if (likeBtn) likeBtn.dataset.tweetId = tweetId;
    if (likeCount) likeCount.textContent = String(likes);

    const external = node.querySelector("[data-tweet-external-link]");
    if (external) external.href = "/t/" + tweetId;

    const editBtn = node.querySelector(".tweet-edit-btn");
    if (editBtn) {
      editBtn.dataset.tweetId = tweetId;
      editBtn.dataset.authorId = authorId;
    }

    const editFormContainer = node.querySelector(".tweet-edit-form .tweet-form-container");
    if (editFormContainer) editFormContainer.dataset.tweetId = tweetId;

    const editTextarea = node.querySelector(".tweet-edit-form textarea[name=\"content\"]");
    if (editTextarea) editTextarea.value = content;

    return node;
  }

  function renderTweets(container, tweets) {
    const tplItem = document.getElementById("tpl-tweet-item");
    const tplEmpty = document.getElementById("tpl-tweets-empty");
    if (!tplItem || !tplEmpty) return;

    clear(container);

    if (!Array.isArray(tweets) || tweets.length === 0) {
      container.appendChild(tplEmpty.content.cloneNode(true));
      return;
    }

    for (const t of tweets) {
      container.appendChild(buildTweetNode(tplItem, t));
    }
  }

  function appendTweets(container, tweets) {
    const tplItem = document.getElementById("tpl-tweet-item");
    if (!tplItem) return;

    if (!Array.isArray(tweets) || tweets.length === 0) return;

    // Remove "empty" placeholder if it exists
    const emptyAlert = container.querySelector(".alert.alert-secondary");
    if (emptyAlert) clear(container);

    for (const t of tweets) {
      container.appendChild(buildTweetNode(tplItem, t));
    }
  }

  async function applyBehaviors(container) {
    if (window.Tweets?.applyLikedState) await window.Tweets.applyLikedState(container);
    if (window.Tweets?.applyLikeButtonState) await window.Tweets.applyLikeButtonState(container);
    if (window.Tweets?.applyEditVisibility) await window.Tweets.applyEditVisibility(container);
  }

  async function loadOnce(container, url) {
    try {
      const data = await getJson(url);
      const tweets = normalizeTweetsPayload(data);
      renderTweets(container, tweets);
      await applyBehaviors(container);
    } catch (_) {
      renderTweets(container, []);
    }
  }

  async function loadInfinite(container, url) {
    const limit = getLimit(container);
    const sentinel = getOrCreateSentinel(container);

    const prev = states.get(container);
    if (prev?.observer) prev.observer.disconnect();

    const state = { page: 1, isLoading: false, isDone: false, observer: null };
    states.set(container, state);

    async function loadNextPage() {
      const s = states.get(container);
      if (!s || s.isLoading || s.isDone) return;

      s.isLoading = true;

      try {
        const pageUrl = buildPageUrl(url, limit, s.page);
        const data = await getJson(pageUrl);
        const tweets = normalizeTweetsPayload(data);

        if (s.page === 1) renderTweets(container, tweets);
        else appendTweets(container, tweets);

        await applyBehaviors(container);

        if (!tweets || tweets.length === 0 || tweets.length < limit) {
          s.isDone = true;
          s.observer?.disconnect();
          return;
        }

        s.page += 1;
      } catch (_) {
        if (s.page === 1) renderTweets(container, []);
        s.isDone = true;
        s.observer?.disconnect();
      } finally {
        s.isLoading = false;
      }
    }

    state.observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) loadNextPage();
      },
      {
        root: null,
        rootMargin: "200% 0px",
        threshold: 0,
      }
    );

    state.observer.observe(sentinel);

    await loadNextPage();
  }

  async function load(container) {
    const url = container.getAttribute("data-tweets-url");
    if (!url) return;

    if (!isInfiniteEnabled(container)) {
      await loadOnce(container, url);
      return;
    }

    await loadInfinite(container, url);
  }

  window.TweetsList = {
    reload: load,
  };

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".tweets-section[data-tweets-url]").forEach((el) => load(el));
  });
})();
