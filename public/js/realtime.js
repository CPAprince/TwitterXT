(function () {
  let eventSource = null;
  let reconnectAttempts = 0;
  const MAX_RECONNECT_DELAY = 30000;
  const recentActions = new Map();

  function connect() {
    const mercureUrl = new URL('/.well-known/mercure', window.location.origin);
    const topic = new URL('/tweets/likes', window.location.origin).toString();
    mercureUrl.searchParams.append('topic', topic);

    eventSource = new EventSource(mercureUrl);

    eventSource.onopen = () => {
      reconnectAttempts = 0;
    };

    eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        const currentUserId = localStorage.getItem('userId');
        
        // Only skip if this was OUR recent action
        const actionTime = recentActions.get(data.tweetId);
        const isOwnRecentAction = actionTime && 
                                  Date.now() - actionTime < 2000 && 
                                  data.triggeredBy === currentUserId;
        
        if (!isOwnRecentAction) {
          updateLikeCount(data.tweetId, data.likesCount);
        }
      } catch (e) {
        console.warn('Failed to parse SSE message:', e);
      }
    };

    eventSource.onerror = () => {
      eventSource.close();
      scheduleReconnect();
    };
  }

  function updateLikeCount(tweetId, count) {
    const selector = `.tweet-like-btn[data-tweet-id="${tweetId}"] .tweet-like-count`;

    document.querySelectorAll(selector).forEach((el) => {
      if (el.textContent !== String(count)) {
        el.textContent = count;
      }
    });
  }

  function scheduleReconnect() {
    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), MAX_RECONNECT_DELAY);
    reconnectAttempts++;
    setTimeout(connect, delay);
  }

  function disconnect() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
  }

  function markRecentAction(tweetId) {
    recentActions.set(tweetId, Date.now());
  }

  document.addEventListener('DOMContentLoaded', connect);
  window.addEventListener('beforeunload', disconnect);

  window.Realtime = { connect, disconnect, markRecentAction };
})();
