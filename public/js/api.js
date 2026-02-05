class ApiError extends Error {
  constructor(message, { code = null, errors = null, status = 0 } = {}) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.errors = errors;
    this.status = status;
  }

  isValidationError() {
    return Array.isArray(this.errors) && this.errors.length > 0;
  }
}

window.Api = {
  /**
   * In-flight refresh promise to prevent concurrent refresh attempts
   * @private
   */
  _refreshPromise: null,

  /**
   * Get token from localStorage
   * @returns {string|null} - Token or null if not found
   */
  getToken() {
    return localStorage.getItem('accessToken');
  },

  /**
   * Get refresh token from localStorage
   * @returns {string|null} - Refresh token or null if not found
   */
  getRefreshToken() {
    return localStorage.getItem('refreshToken');
  },

  /**
   * Store token in localStorage
   * Also extracts and caches userId for quick access
   * @param {string} token - JWT token
   */
  setToken(token) {
    localStorage.setItem('accessToken', token);
    // Cache userId for performance
    if (window.Auth) {
      const userId = window.Auth.getUserId(token);
      if (userId) {
        localStorage.setItem('userId', userId);
      }
    }
  },

  /**
   * Store refresh token in localStorage
   * @param {string} refreshToken - Refresh token
   */
  setRefreshToken(refreshToken) {
    localStorage.setItem('refreshToken', refreshToken);
  },

  /**
   * Store both access and refresh tokens
   * @param {string} accessToken - Access token
   * @param {string} refreshToken - Refresh token
   */
  setTokens(accessToken, refreshToken) {
    this.setToken(accessToken);
    if (refreshToken) {
      this.setRefreshToken(refreshToken);
    }
  },

  /**
   * Remove token and userId from localStorage
   */
  clearToken() {
    localStorage.removeItem('accessToken');
    localStorage.removeItem('refreshToken');
    localStorage.removeItem('userId');
  },

  /**
   * Check if token is expired or will expire soon (within 60 seconds)
   * @param {string} token - JWT token to check
   * @returns {boolean} - True if expired or expiring soon
   */
  isTokenExpiredOrExpiringSoon(token) {
    if (!token || !window.Auth) {
      console.debug('[Token Check] No token or Auth helper available');
      return true;
    }

    const payload = window.Auth.parseJWT(token);
    if (!payload || !payload.exp) {
      console.debug('[Token Check] Token missing or invalid payload');
      return true;
    }

    const currentTime = Math.floor(Date.now() / 1000);
    const bufferTime = 60; // Refresh if expiring within 60 seconds
    const timeUntilExpiry = payload.exp - currentTime;
    const isExpiringSoon = payload.exp < (currentTime + bufferTime);

    if (isExpiringSoon) {
      console.debug(`[Token Check] Token expiring soon. Expires in ${timeUntilExpiry}s (exp: ${new Date(payload.exp * 1000).toISOString()}, now: ${new Date(currentTime * 1000).toISOString()})`);
    } else {
      console.debug(`[Token Check] Token valid. Expires in ${timeUntilExpiry}s`);
    }

    return isExpiringSoon;
  },

  /**
   * Ensure access token is valid, refresh if needed
   * Uses a queue to prevent concurrent refresh attempts
   * @returns {Promise<string|null>} - Valid access token or null
   */
  async ensureValidToken() {
    const currentToken = this.getToken();
    const refreshToken = this.getRefreshToken();

    console.debug('[ensureValidToken] Starting token validation check', {
      hasAccessToken: !!currentToken,
      hasRefreshToken: !!refreshToken
    });

    // If we have a token, check if it's still valid
    if (currentToken) {
      // If token is still valid, return it
      if (!this.isTokenExpiredOrExpiringSoon(currentToken)) {
        console.debug('[ensureValidToken] Access token is still valid, returning it');
        return currentToken;
      }
      console.debug('[ensureValidToken] Access token expired or expiring soon, will attempt refresh');
    } else {
      console.debug('[ensureValidToken] No access token found, will attempt refresh');
    }

    // Token is missing or expired - try to refresh if we have a refresh token
    if (!refreshToken) {
      console.warn('[ensureValidToken] No refresh token available, cannot refresh. Clearing tokens.');
      // No refresh token available, clear everything
      if (currentToken) {
        this.clearToken();
      }
      return null;
    }

    // If refresh is already in progress, wait for it
    if (this._refreshPromise) {
      console.debug('[ensureValidToken] Refresh already in progress, waiting for existing refresh attempt');
      return await this._refreshPromise;
    }

    // Start refresh process
    console.debug('[ensureValidToken] Starting new token refresh process');
    this._refreshPromise = this.refreshAccessToken()
      .finally(() => {
        // Clear the promise when done (success or failure)
        this._refreshPromise = null;
        console.debug('[ensureValidToken] Refresh promise cleared');
      });

    const result = await this._refreshPromise;
    console.debug('[ensureValidToken] Refresh completed', { success: !!result });
    return result;
  },

  /**
   * Refresh the access token using the refresh token
   * Updated to skip auth check for the refresh endpoint itself
   * @returns {Promise<string|null>} - New access token or null if refresh failed
   */
  async refreshAccessToken() {
    const refreshToken = this.getRefreshToken();
    if (!refreshToken) {
      console.warn('[refreshAccessToken] No refresh token available');
      return null;
    }

    console.log('[refreshAccessToken] Starting token refresh attempt');

    try {
      // Use _request directly to inspect response before it's converted to error
      // Note: gesdinet_jwt_refresh_token expects 'refresh_token' parameter name
      const refreshUrl = window.routes?.tokenRefresh || '/api/token/refresh';
      console.debug('[refreshAccessToken] Sending refresh request to:', refreshUrl);

      const response = await this._request(refreshUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          refresh_token: refreshToken
        }),
      }, true); // skipAuth = true

      const data = await response.json().catch(() => null);

      console.log('[refreshAccessToken] Refresh response received', {
        status: response.status,
        statusText: response.statusText,
        ok: response.ok,
        hasData: !!data,
        errorCode: data?.error?.code,
        errorMessage: data?.error?.message
      });

      // Debug logging - remove in production
      if (response.ok) {
        console.log('[refreshAccessToken] Token refresh successful:', data);
      }

      if (response.ok && data) {
        const newAccessToken = data.token || data.accessToken;
        const newRefreshToken = data.refresh_token || data.refreshToken;

        if (newAccessToken) {
          console.log('[refreshAccessToken] New access token received, storing it');
          this.setToken(newAccessToken);

          // CRITICAL FIX: Properly handle refresh token response
          // If newRefreshToken is undefined, server didn't include it - keep existing token (normal for non-rotating tokens)
          // If newRefreshToken is null or empty, server explicitly returned empty - clear tokens (indicates error)
          // If newRefreshToken has a value, save the new token (normal rotation)
          if (newRefreshToken !== undefined) {
            if (newRefreshToken) {
              // New refresh token provided - save it
              console.log('[refreshAccessToken] New refresh token received, storing it');
              this.setRefreshToken(newRefreshToken);
            } else {
              // Server returned null/empty refresh token - this shouldn't happen
              // but if it does, clear tokens to force re-login
              console.warn('[refreshAccessToken] Server returned empty refresh token, clearing tokens');
              this.clearToken();
              return null;
            }
          } else {
            console.debug('[refreshAccessToken] No new refresh token in response, keeping existing one');
          }
          // If newRefreshToken is undefined, server didn't include it - keep existing token

          console.log('[refreshAccessToken] Token refresh completed successfully');
          return newAccessToken;
        } else {
          // Access token missing in successful response - this shouldn't happen
          console.error('[refreshAccessToken] Token refresh succeeded but no access token in response:', data);
          return null;
        }
      } else {
        // Check if error indicates invalid refresh token
        const errorCode = data?.error?.code;
        const isTokenInvalid = errorCode === 'AUTH_TOKEN_INVALID' ||
                               errorCode === 'AUTH_UNAUTHORIZED' ||
                               response.status === 422 ||
                               (response.status === 401 && (errorCode === 'AUTH_TOKEN_INVALID' || errorCode === 'AUTH_UNAUTHORIZED' || !errorCode));

        console.warn('[refreshAccessToken] Refresh failed', {
          status: response.status,
          errorCode: errorCode,
          isTokenInvalid: isTokenInvalid,
          responseData: data
        });

        if (isTokenInvalid) {
          // Refresh token is invalid/expired, clear all tokens
          console.warn('[refreshAccessToken] Refresh token is invalid/expired, clearing all tokens');
          this.clearToken();
        } else {
          console.debug('[refreshAccessToken] Refresh failed but token may still be valid (network/server error), keeping tokens for retry');
        }
        // For other errors (network, server errors), keep tokens and let user retry
      }
    } catch (e) {
      // Network error or other exception - don't clear tokens, might be temporary
      console.error('[refreshAccessToken] Exception during token refresh:', e);
    }

    console.debug('[refreshAccessToken] Token refresh failed, returning null');
    return null;
  },

  /**
   * Core request method that handles all HTTP requests
   * This is the middleware that intercepts all requests
   * @private
   * @param {string} url - Request URL
   * @param {object} options - Fetch options (method, headers, body, etc.)
   * @param {boolean} skipAuth - Skip authentication for this request
   * @returns {Promise<Response>}
   */
  async _request(url, options = {}, skipAuth = false) {
    console.debug('[API Request] Starting request', {
      url: url,
      method: options.method || 'GET',
      skipAuth: skipAuth
    });

    // Ensure valid token before request (unless skipping auth)
    if (!skipAuth) {
      const authToken = await this.ensureValidToken();
      if (authToken) {
        options.headers = options.headers || {};
        options.headers['Authorization'] = `Bearer ${authToken}`;
        console.debug('[API Request] Authorization header added to request');
      } else {
        console.warn('[API Request] No valid auth token available for request');
      }
    } else {
      console.debug('[API Request] Skipping auth for this request');
    }

    // Make the request
    let response = await fetch(url, options);

    console.debug('[API Request] Response received', {
      url: url,
      status: response.status,
      statusText: response.statusText,
      ok: response.ok
    });

    // If we get 401, try refreshing (handles both expired tokens and missing tokens with refresh token available)
    // This is a fallback in case the token expired between the check and the request
    if (response.status === 401 && !skipAuth &&
        !url.includes('/token/refresh') && this.getRefreshToken()) {

      console.warn('[API Request] Received 401, attempting token refresh', {
        url: url,
        hasRefreshToken: !!this.getRefreshToken()
      });

      // Clear the cached refresh promise to force a new refresh attempt
      this._refreshPromise = null;

      // Try to refresh and get a new token
      const newToken = await this.refreshAccessToken();
      if (newToken) {
        console.log('[API Request] Token refresh successful, retrying original request');
        // Retry the request with the new token
        // Clone options to avoid mutating the original
        const retryOptions = {
          ...options,
          headers: {
            ...options.headers,
            'Authorization': `Bearer ${newToken}`
          }
        };
        response = await fetch(url, retryOptions);
        console.debug('[API Request] Retry response received', {
          url: url,
          status: response.status,
          statusText: response.statusText,
          ok: response.ok
        });
      } else {
        console.warn('[API Request] Token refresh failed after 401');
        // Refresh failed - check if refresh token is expired
        // If refresh failed and we still have a refresh token, it's likely expired
        if (this.getRefreshToken()) {
          // Refresh token exists but refresh failed - it's expired or invalid
          // Clear all tokens and redirect to registration page
          console.error('[API Request] Refresh token exists but refresh failed - token expired. Clearing tokens and redirecting.');
          this.clearToken();

          // Redirect to registration page if not already there
          const currentPath = window.location.pathname;
          console.log('[API Request] Redirecting to registration page', {
            currentPath: currentPath,
            willRedirect: currentPath !== '/registration' && currentPath !== '/'
          });
          if (currentPath !== '/registration' && currentPath !== '/') {
            window.location.href = '/registration';
          }
        } else {
          console.debug('[API Request] No refresh token available, cannot redirect');
        }
      }
      // If refresh failed, don't clear tokens here - refreshAccessToken() handles that
      // Only clear if the refresh token itself is invalid
    } else if (response.status === 401) {
      console.warn('[API Request] Received 401 but not attempting refresh', {
        url: url,
        skipAuth: skipAuth,
        isRefreshEndpoint: url.includes('/token/refresh'),
        hasRefreshToken: !!this.getRefreshToken()
      });
    }

    return response;
  },

  /**
   * Make a POST request
   * @param {string} url - Request URL
   * @param {object} payload - Request body
   * @param {object} options - Additional options (skipAuth, etc.)
   * @returns {Promise<any>}
   */
  async post(url, payload, options = {}) {
    const response = await this._request(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
      body: JSON.stringify(payload),
    }, options.skipAuth);

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(
        data?.error?.message ?? data?.message ?? response.statusText,
        {
          code: data?.error?.code ?? null,
          errors: data?.errors ?? null,
          status: response.status
        }
      );
    }

    return data;
  },

  /**
   * Make a GET request
   * @param {string} url - Request URL
   * @param {object} options - Additional options (skipAuth, etc.)
   * @returns {Promise<any>}
   */
  async get(url, options = {}) {
    const response = await this._request(url, {
      method: 'GET',
      headers: options.headers || {},
    }, options.skipAuth);

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(
        data?.error?.message ?? data?.message ?? response.statusText,
        {
          code: data?.error?.code ?? null,
          errors: data?.errors ?? null,
          status: response.status
        }
      );
    }

    return data;
  },

  /**
   * Make a PATCH request
   * @param {string} url - Request URL
   * @param {object} payload - Request body
   * @param {object} options - Additional options (skipAuth, etc.)
   * @returns {Promise<any>}
   */
  async patch(url, payload, options = {}) {
    const response = await this._request(url, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
      body: JSON.stringify(payload),
    }, options.skipAuth);

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw new ApiError(
        data?.error?.message ?? data?.message ?? response.statusText,
        {
          code: data?.error?.code ?? null,
          errors: data?.errors ?? null,
          status: response.status
        }
      );
    }

    return data;
  },
};
