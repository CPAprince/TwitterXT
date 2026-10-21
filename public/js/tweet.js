async function getTweet(tweetId) {
  try {
    const data = await Api.get(`/api/tweets/${tweetId}`);
    console.log('Tweet JSON data:', data);

    return data;
  } catch (error) {
    console.error('Error fetching tweet:', error);
    return null;
  }
}

function formatPublishDate(isoString) {
  const date = new Date(isoString);
  if (isNaN(date)) return '';

  const now = Date.now();
  let diff = now - date.getTime();
  if (diff < 0) diff = 0; // future dates => treat as “just now”

  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;

  if (diff < minute) {
    const seconds = Math.max(1, Math.floor(diff / 1000));
    return `${seconds}s ago`;
  }

  if (diff < hour) {
    const minutes = Math.max(1, Math.floor(diff / minute));
    return `${minutes}m ago`;
  }

  if (diff < day) {
    const hours = Math.max(1, Math.floor(diff / hour));
    return `${hours}h ago`;
  }

  if (diff < 3 * day) {
    const days = Math.floor(diff / day);
    return `${days}d ago`;
  }

  return date.toLocaleString('en-US', {
    day: 'numeric',    // 1–31
    month: 'short',    // Jan, Feb, …
    year: 'numeric'    // 4 digits
  });
}



function showTweet(tweet) {
  console.log('Tweet JSON data in show:', tweet);
  const authorId = tweet?.author?.id ?? '';
  const authorName = tweet?.author?.name ?? 'You';
  const isPending = !!tweet?.isPending;

  const authorEl = document.querySelector('[tweet_author]');
  if (authorEl) {
    authorEl.innerHTML = '<a href="/p/' + authorId + '" style="color: var(--bs-body-color);">' + authorName + '</a>';
  }

  const createdAtEl = document.querySelector('[tweet_createdAt]');
  if (createdAtEl) {
    createdAtEl.textContent = ' · ' + formatPublishDate(tweet.createdAt);
  }

  const contentEl = document.querySelector('[data-tweet-content]');
  if (contentEl) {
    contentEl.textContent = tweet.content;
  }

  const likesEl = document.querySelector('[tweet_likes]');
  if (likesEl) {
    likesEl.textContent = tweet.likesCount || 0;
  }

  const likeBtn = document.querySelector('.tweet-like-btn');
  if (likeBtn) {
    likeBtn.setAttribute('data-tweet-id', tweet.id);
    if (isPending) {
      likeBtn.disabled = true;
      likeBtn.style.pointerEvents = 'none';
      likeBtn.style.opacity = '0.6';
    } else {
      likeBtn.disabled = false;
      likeBtn.style.pointerEvents = '';
      likeBtn.style.opacity = '';
    }
  }

  const formContainer = document.querySelector('.tweet-form-container');
  if (formContainer) {
    formContainer.setAttribute('data-tweet-id', tweet.id);
  }

  const editBtn = document.querySelector('.tweet-edit-btn');
  if (editBtn) {
    editBtn.setAttribute('data-tweet-id', tweet.id);
    editBtn.setAttribute('data-author-id', authorId);
    if (isPending) {
      editBtn.style.display = 'none';
    }
  }

  const pendingBadge = document.querySelector('[data-tweet-pending-badge]');
  if (pendingBadge) {
    if (isPending) {
      pendingBadge.classList.remove('d-none');
    } else {
      pendingBadge.classList.add('d-none');
    }
  }

  const tweetContainer = document.querySelector('.tweet');
  if (tweetContainer) {
    tweetContainer.style.visibility = 'visible';
    tweetContainer.style.display = 'block';
  }

  if (!isPending) {
    window.Auth.getCurrentUserId().then(function (userId) {
      if (userId && userId === authorId && editBtn) {
        editBtn.style.display = 'inline-block';
      }
    });

    window.Tweets?.applyLikedState?.();
  }
}
