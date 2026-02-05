window.Loading = {
  /**
   * Show loading spinner in container
   * @param {HTMLElement} container - Container to show loading in
   * @param {string} message - Optional message to display
   */
  show(container, message = '') {
    if (!container) return;

    // Create loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'd-flex align-items-center';
    loadingDiv.setAttribute('data-loading-indicator', 'true');
    loadingDiv.innerHTML = `
      <div class="spinner-border spinner-border-sm me-2" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      ${message ? `<span>${message}</span>` : ''}
    `;
    container.appendChild(loadingDiv);
  },

  /**
   * Hide loading indicator from container
   * @param {HTMLElement} container - Container to hide loading from
   */
  hide(container) {
    if (!container) return;

    const loadingIndicator = container.querySelector('[data-loading-indicator="true"]');
    if (loadingIndicator) {
      loadingIndicator.remove();
    }
  },

  /**
   * Clear container and show loading
   * @param {HTMLElement} container - Container to clear and show loading in
   * @param {string} message - Optional message to display
   */
  clearAndShow(container, message = '') {
    if (!container) return;
    container.replaceChildren();
    this.show(container, message);
  },

  /**
   * Disable all form inputs, textareas, and submit button
   * Stores original disabled states for restoration
   * @param {HTMLFormElement} form - Form to disable
   */
  disableForm(form) {
    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, button[type="submit"], button:not([type])');
    inputs.forEach(input => {
      // Store original disabled state if not already stored
      if (!input.hasAttribute('data-original-disabled')) {
        input.setAttribute('data-original-disabled', input.disabled ? 'true' : 'false');
      }
      input.disabled = true;
    });
  },

  /**
   * Re-enable form inputs based on stored original states
   * @param {HTMLFormElement} form - Form to enable
   */
  enableForm(form) {
    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, button[type="submit"], button:not([type])');
    inputs.forEach(input => {
      const originalDisabled = input.getAttribute('data-original-disabled');
      if (originalDisabled !== null) {
        input.disabled = originalDisabled === 'true';
        input.removeAttribute('data-original-disabled');
      } else {
        // If no original state stored, just enable it
        input.disabled = false;
      }
    });
  },
};
