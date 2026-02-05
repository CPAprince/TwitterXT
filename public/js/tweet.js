async function getTweet(tweetId) {
  try {
    const response = await fetch(`/api/tweets/${tweetId}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      return null;
    }

    const data = await response.json();
    console.log('Tweet JSON data:', data);

    return data;
  } catch (error) {
    console.error('Error:', error);
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
  document.querySelector('[tweet_author]').innerHTML = '<a href=\"\/p\/' + tweet.author.id + '\"  style=\"color: var(--bs-body-color);\">' + tweet.author.name + '</a>';
  document.querySelector('[tweet_createdAt]').textContent = ' · ' + formatPublishDate(tweet.createdAt);
  document.querySelector('[data-tweet-content]').textContent = tweet.content;
  document.querySelector('[tweet_likes]').textContent = tweet.likesCount;
  document.querySelector(".tweet-like-btn").setAttribute('data-tweet-id', tweet.id);
  document.querySelector(".tweet-form-container").setAttribute('data-tweet-id', tweet.id);
  document.querySelector(".tweet-edit-btn").setAttribute('data-tweet-id', tweet.id);
  document.querySelector(".tweet-edit-btn").setAttribute('data-author-id', tweet.author.id);

  document.querySelector('.tweet').style.visibility = 'visible';

  window.Auth.getCurrentUserId().then(function(userId) {
    //console.log(userId); //
    if(userId == tweet.author.id) {
      document.querySelector("button.tweet-edit-btn").style.display = 'inline-block';
    }
  });


}
