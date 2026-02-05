window.Auth = {
  /**
   * Parse JWT token and return payload
   * @param {string} token - JWT token
   * @returns {object|null} - Decoded payload or null if invalid
   */
  parseJWT(token) {
    if (!token) {
      return null;
    }

    try {
      const parts = token.split('.');
      if (parts.length !== 3) {
        return null;
      }

      // Decode the payload (middle part)
      const payload = parts[1];
      const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
      return JSON.parse(decoded);
    } catch (e) {
      console.error('Failed to parse JWT:', e);
      return null;
    }
  },

  /**
   * Extract userId from JWT token
   * @param {string} token - JWT token
   * @returns {string|null} - User ID or null if not found
   */
  getUserId(token) {
    const payload = this.parseJWT(token);
    return payload?.id || null;
  },

  /**
   * Check if token is expired
   * @param {string} token - JWT token
   * @returns {boolean} - True if expired or invalid
   */
  isTokenExpired(token) {
    const payload = this.parseJWT(token);
    if (!payload || !payload.exp) {
      return true;
    }

    const currentTime = Math.floor(Date.now() / 1000);
    return payload.exp < currentTime;
  },

  /**
   * Get current userId from stored token
   * Automatically refreshes token if expired using the new middleware
   * @returns {Promise<string|null>} - User ID or null if not found
   */
  async getCurrentUserId() {
    // Use ensureValidToken to get a valid token (will auto-refresh if needed)
    const token = await Api.ensureValidToken();
    if (!token) {
      return null;
    }

    return this.getUserId(token);
  },

  /**
   * Update navigation visibility based on auth state
   */
  updateNavVisibility() {
    if (!window.Api || !document?.body) {
      return;
    }

    const token = Api.getToken();
    const isAuthenticated = !!token && !this.isTokenExpired(token);
    document.body.classList.toggle('is-authenticated', isAuthenticated);
    document.body.classList.toggle('is-guest', !isAuthenticated);
  },

  /**
   * Logout current user (revoke refresh token + clear local tokens)
   */
  async logout() {
    if (!window.Api) {
      console.warn('[Auth.logout] Api helper not available. Redirecting to homepage.');
      window.location.href = window.routes?.successRedirect || '/';
      return;
    }

    const refreshToken = Api.getRefreshToken();
    const logoutUrl = window.routes?.logout || '/api/tokens';

    try {
      // Best-effort revoke. Even if it fails, we still clear tokens locally.
      if (refreshToken) {
        // IMPORTANT: do NOT run auto-refresh/redirect logic while user explicitly logs out.
        // We send the current access token as-is (if present) and skip auth middleware.
        const accessToken = Api.getToken();
        const headers = {
          'Content-Type': 'application/json',
          ...(accessToken ? { 'Authorization': `Bearer ${accessToken}` } : {}),
        };

        await Api._request(logoutUrl, {
          method: 'DELETE',
          headers,
          body: JSON.stringify({ refreshToken }),
        }, true); // skipAuth=true
      }
    } catch (error) {
      console.error('[Auth.logout] Logout request failed (will still clear tokens):', error);
    } finally {
      Api.clearToken();
      this.updateNavVisibility();
      window.location.href = window.routes?.successRedirect || '/';
    }
  },
};

// Initialize nav visibility and keep it updated on auth changes
document.addEventListener('DOMContentLoaded', () => {
  if (window.Auth) {
    window.Auth.updateNavVisibility();
  }
});

if (window.Api && window.Auth) {
  const originalSetToken = Api.setToken.bind(Api);
  Api.setToken = (token) => {
    originalSetToken(token);
    window.Auth.updateNavVisibility();
    window.Tweets?.applyLikeButtonState?.();
  };

  const originalClearToken = Api.clearToken.bind(Api);
  Api.clearToken = () => {
    originalClearToken();
    window.Auth.updateNavVisibility();
    window.Tweets?.applyLikeButtonState?.();
  };
}

document.addEventListener('DOMContentLoaded', () => {
  const logoutLinks = document.querySelectorAll('#nav-logout-link');
  if (!logoutLinks.length) return;

  logoutLinks.forEach((el) => {
    el.addEventListener('click', async (e) => {
      e.preventDefault();
      await window.Auth.logout();
    });
  });
});

