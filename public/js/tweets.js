function normalizeError(error) {
  if (error?.errors && Array.isArray(error.errors) && error.errors.length > 0) {
    return error.errors.map((e) => e?.message).filter(Boolean).join(". ");
  }

  const message = String(error?.message || "");
  return message || "Failed to post tweet";
}

const getLikesStorageKey = () => {
  const userId = localStorage.getItem('userId');
  return userId ? `tweet_likes_${userId}` : 'tweet_likes';
};

const getLikedTweets = () => {
  try {
    const stored = localStorage.getItem(getLikesStorageKey());
    return stored ? JSON.parse(stored) : {};
  } catch {
    return {};
  }
};

const setLikedTweet = (tweetId, liked) => {
  const likes = getLikedTweets();
  if (liked) {
    likes[tweetId] = true;
  } else {
    delete likes[tweetId];
  }
  localStorage.setItem(getLikesStorageKey(), JSON.stringify(likes));
};

window.Tweets = window.Tweets || {};
window.Tweets.applyEditVisibility = async (container = document) => {
  const currentUserId = await Auth.getCurrentUserId();
  if (!currentUserId) return;

  container.querySelectorAll(".tweet-edit-btn").forEach(btn => {
    const authorId = btn.dataset.authorId;
    if (authorId === currentUserId) {
      btn.style.display = "inline-block";
    }
  });
};

window.Tweets.applyLikedState = async (container = document) => {
  const currentUserId = await Auth.getCurrentUserId();
  if (!currentUserId) return;

  const likedTweets = getLikedTweets();
  container.querySelectorAll(".tweet-like-btn").forEach(btn => {
    const tweetId = btn.dataset.tweetId;
    if (tweetId && likedTweets[tweetId]) {
      const icon = btn.querySelector(".tweet-like-icon");
      const countSpan = btn.querySelector(".tweet-like-count");
      if (icon) {
        btn.dataset.liked = "true";
        icon.classList.remove("far");
        icon.classList.add("fas", "text-primary");

        if (countSpan) {
          const currentCount = parseInt(countSpan.textContent) || 0;
          if (currentCount === 0) {
            countSpan.textContent = "1";
          }
        }
      }
    }
  });
};

window.Tweets.applyLikeButtonState = async (container = document) => {
  const tooltipMessage = "Sign in to like tweets (or create an account).";
  const currentUserId = await Auth.getCurrentUserId();
  const isAuthenticated = !!currentUserId;

  container.querySelectorAll(".tweet-like-btn").forEach((btn) => {
    // Bootstrap does not show tooltips on disabled buttons.
    // Wrap the button and attach tooltip to the wrapper instead.
    let wrapper = btn.parentElement;
    if (!wrapper || !wrapper.classList.contains("tweet-like-tooltip-wrapper")) {
      wrapper = document.createElement("span");
      wrapper.className = "tweet-like-tooltip-wrapper";
      wrapper.style.display = "inline-block";
      btn.parentNode.insertBefore(wrapper, btn);
      wrapper.appendChild(btn);
    }

    const bootstrapTooltip = window.bootstrap?.Tooltip;
    const existingTooltip = bootstrapTooltip?.getInstance(wrapper) || null;

    if (isAuthenticated) {
      // Enable and remove guest tooltip.
      btn.disabled = false;
      wrapper.classList.remove("is-guest-disabled");
      wrapper.dataset.guestDisabled = "false";
      wrapper.removeAttribute("data-bs-toggle");
      wrapper.removeAttribute("data-bs-title");
      wrapper.removeAttribute("data-bs-placement");
      wrapper.removeAttribute("data-bs-trigger");
      wrapper.removeAttribute("tabindex");
      existingTooltip?.dispose();
      return;
    }

    // Guest: disable the button and add tooltip on wrapper.
    btn.disabled = true;
    wrapper.classList.add("is-guest-disabled");
    wrapper.dataset.guestDisabled = "true";
    wrapper.setAttribute("tabindex", "0");
    wrapper.setAttribute("data-bs-toggle", "tooltip");
    wrapper.setAttribute("data-bs-title", tooltipMessage);
    wrapper.setAttribute("data-bs-placement", "top");
    wrapper.setAttribute("data-bs-trigger", "hover focus");

    if (bootstrapTooltip) {
      bootstrapTooltip.getOrCreateInstance(wrapper);
    }
  });
};

document.addEventListener("DOMContentLoaded", async () => {
  await window.Tweets.applyEditVisibility();
  await window.Tweets.applyLikedState();
  await window.Tweets.applyLikeButtonState();

  document
    .querySelectorAll('.tweet-form-container[data-form-mode="create"]')
    .forEach((container) => {
      window.CharCounter?.init?.(container);
    });

  // Tweet like/unlike functionality
  document.addEventListener("click", async (e) => {
    const likeBtn = e.target.closest(".tweet-like-btn");
    if (!likeBtn) return;

    // Guard: do nothing for guests / disabled controls
    if (likeBtn.disabled) return;
    const likeWrapper = likeBtn.closest(".tweet-like-tooltip-wrapper");
    if (likeWrapper?.dataset?.guestDisabled === "true") return;
    const currentUserId = await Auth.getCurrentUserId();
    if (!currentUserId) return;

    e.preventDefault();
    e.stopPropagation();

    const tweetId = likeBtn.dataset.tweetId;
    if (!tweetId) return;

    const icon = likeBtn.querySelector(".tweet-like-icon");
    const countSpan = likeBtn.querySelector(".tweet-like-count");
    const isCurrentlyLiked = likeBtn.dataset.liked === "true";
    const currentCount = parseInt(countSpan.textContent) || 0;

    // Optimistic UI update
    const newLikedState = !isCurrentlyLiked;
    const newCount = newLikedState ? currentCount + 1 : Math.max(0, currentCount - 1);

    // Update UI immediately
    likeBtn.dataset.liked = newLikedState.toString();
    countSpan.textContent = newCount;

    // Update icon (far = outline, fas = filled)
    if (newLikedState) {
      icon.classList.remove("far");
      icon.classList.add("fas", "text-primary");
    } else {
      icon.classList.remove("fas", "text-primary");
      icon.classList.add("far");
    }

    // Disable button during request
    likeBtn.disabled = true;

    try {
      if (window.Realtime?.markRecentAction) {
        window.Realtime.markRecentAction(tweetId);
      }

      const toggleUrl = window.routes?.toggleLike && window.buildRoute
        ? window.buildRoute(window.routes.toggleLike, { tweetId: tweetId })
        : `/api/tweets/${tweetId}/likes/toggle`;

      const result = await Api.post(toggleUrl, {});

      // Update UI based on actual API response
      const actualLiked = result.liked;
      likeBtn.dataset.liked = actualLiked.toString();

      // Save to localStorage
      setLikedTweet(tweetId, actualLiked);

      // If the optimistic update was wrong, correct it
      if (actualLiked !== newLikedState) {
        const correctedCount = actualLiked ? currentCount + 1 : Math.max(0, currentCount - 1);
        countSpan.textContent = correctedCount;

        if (actualLiked) {
          icon.classList.remove("far");
          icon.classList.add("fas", "text-primary");
        } else {
          icon.classList.remove("fas", "text-primary");
          icon.classList.add("far");
        }
      }
    } catch (error) {
      // Revert optimistic update on error
      likeBtn.dataset.liked = isCurrentlyLiked.toString();
      countSpan.textContent = currentCount;

      if (isCurrentlyLiked) {
        icon.classList.remove("far");
        icon.classList.add("fas", "text-primary");
      } else {
        icon.classList.remove("fas", "text-primary");
        icon.classList.add("far");
      }

      console.error("Failed to toggle like:", error);
    } finally {
      likeBtn.disabled = false;
    }
  });

  document.addEventListener("click", (e) => {
    const editBtn = e.target.closest(".tweet-edit-btn");
    if (!editBtn) return;

    const tweetElement = editBtn.closest(".tweet");
    if (!tweetElement) return;

    const contentDisplay = tweetElement.querySelector(".tweet-content-display");
    const editFormContainer = tweetElement.querySelector(".tweet-edit-form");
    const form = editFormContainer?.querySelector(".tweet-form");

    if (!contentDisplay || !editFormContainer || !form) return;

    const currentContent = contentDisplay.querySelector("p")?.textContent.trim() || "";

    const isEditing = editFormContainer.style.display !== "none";
    contentDisplay.style.display = isEditing ? "block" : "none";
    editFormContainer.style.display = isEditing ? "none" : "block";

    if (!isEditing) {
      const textarea = form.querySelector("textarea");
      if (textarea) {
        textarea.value = currentContent;
      }

      const formContainer = editFormContainer.querySelector(".tweet-form-container");
      if (formContainer) {
        window.CharCounter?.init?.(formContainer);
      }

      const alerts = editFormContainer.querySelector(".tweet-form-alerts");
      if (alerts) {
        alerts.replaceChildren();
      }
    }
  });

  // Delegated event listener for tweet form submission (edit mode)
  document.addEventListener("submit", async (e) => {
    const form = e.target;
    if (!form.classList.contains("tweet-form")) return;

    const formContainer = form.closest(".tweet-form-container");
    if (!formContainer) return;

    const formMode = formContainer.dataset.formMode;
    if (formMode === "create") {
      e.preventDefault();

      const alerts = formContainer.querySelector(".tweet-form-alerts");
      const tweetsSection = document.querySelector(".tweets-section");

      if (alerts) {
        alerts.replaceChildren();
      }

      const formData = new FormData(form);
      const content = (formData.get("content") || "").trim();

      if (!content) {
        if (alerts) {
          Alert.append(alerts, "Tweet content cannot be empty", "danger");
        }
        return;
      }

      const createUrl = window.routes?.createTweet;

      if (!createUrl) {
        if (alerts) Alert.append(alerts, "Missing routes.", "danger");
        return;
      }

      try {
        if (alerts) {
          Loading.clearAndShow(alerts, "Posting tweet...");
        }
        Loading.disableForm(form);

        await Api.post(createUrl, { content });

        const textarea = form.querySelector("textarea");
        if (textarea) {
          textarea.value = "";
        }
        window.CharCounter?.init?.(formContainer);

        if (tweetsSection && window.TweetsList?.reload) {
          await window.TweetsList.reload(tweetsSection);
        }

        if (alerts) {
          alerts.replaceChildren();
          Alert.append(alerts, "Tweet posted successfully!", "success");
        }
      } catch (error) {
        if (alerts) {
          alerts.replaceChildren();
          Alert.append(alerts, normalizeError(error), "danger");
        }
      } finally {
        Loading.enableForm(form);
      }

      return;
    }

    if (formMode !== "edit") return; // Only handle edit forms here

    e.preventDefault();

    const tweetId = formContainer.dataset.tweetId;
    if (!tweetId) return;

    const tweetElement = formContainer.closest(".tweet");
    const contentDisplay = tweetElement.querySelector(".tweet-content-display");
    const editFormContainer = tweetElement.querySelector(".tweet-edit-form");
    const alerts = editFormContainer.querySelector(".tweet-form-alerts");
    const originalContent = contentDisplay.querySelector("p").textContent.trim();

    alerts.replaceChildren();

    const formData = new FormData(form);
    const content = formData.get("content").trim();

    if (!content) {
      Alert.append(alerts, "Tweet content cannot be empty", "danger");
      return;
    }

    if (content === originalContent) {
      Alert.append(alerts, "No changes to save", "info");
      return;
    }

    try {
      Loading.clearAndShow(alerts, "Saving tweet...");
      Loading.disableForm(form);

      const updateTweetUrl = window.routes?.updateTweet && window.buildRoute
        ? window.buildRoute(window.routes.updateTweet, { tweetId: tweetId })
        : `/api/tweets/${tweetId}`;
      const result = await Api.patch(updateTweetUrl, { content });

      // Update tweet display
      contentDisplay.querySelector("p").textContent = result.content;
      contentDisplay.style.display = "block";
      editFormContainer.style.display = "none";

      Loading.hide(alerts);
      Alert.append(alerts, "Tweet updated successfully!", "success");

      // Hide success message after a delay
      setTimeout(() => {
        alerts.replaceChildren();
      }, 3000);
    } catch (error) {
      Loading.hide(alerts);
      Alert.append(alerts, error.message || "Failed to update tweet", "danger");
    } finally {
      Loading.enableForm(form);
    }
  });

  // Delegated event listener for cancel button clicks
  document.addEventListener("click", async (e) => {
    if (!e.target.classList.contains("tweet-form-cancel")) return;

    const formContainer = e.target.closest(".tweet-form-container");
    if (!formContainer) return;

    const tweetElement = formContainer.closest(".tweet");
    const contentDisplay = tweetElement.querySelector(".tweet-content-display");
    const editFormContainer = tweetElement.querySelector(".tweet-edit-form");
    const form = editFormContainer.querySelector(".tweet-form");

    // Reset form to current displayed content
    const currentContent = contentDisplay.querySelector("p").textContent.trim();
    form.querySelector("textarea").value = currentContent;

    // Hide edit form and show content
    contentDisplay.style.display = "block";
    editFormContainer.style.display = "none";

    // Clear alerts
    const alerts = editFormContainer.querySelector(".tweet-form-alerts");
    if (alerts) {
      alerts.replaceChildren();
    }
  });
});
