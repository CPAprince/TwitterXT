document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const alerts = document.getElementById('alert-placeholder');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const data = Object.fromEntries(new FormData(form));

    try {
      Loading.clearAndShow(alerts, 'Logging in...');
      Loading.disableForm(form);

      const auth = await Api.post(window.routes.login, {
        email: data.email,
        password: data.password,
      }, { skipAuth: true });

      const accessToken = auth.token || auth.accessToken;
      const refreshToken = auth.refresh_token || auth.refreshToken;

      if (!accessToken) {
        throw new Error('Access token is missing.');
      }

      Api.setToken(accessToken);
      if (refreshToken) {
        Api.setRefreshToken(refreshToken);
      }

      Loading.hide(alerts);
      Alert.append(alerts, 'You have been successfully logged in!', 'success');
      window.location.href = window.routes.successRedirect;
    } catch (e) {
      Loading.hide(alerts);
      if (e?.isValidationError?.()) {
        Alert.appendValidationErrors(alerts, e.errors);
      } else if (e?.status === 401) {
        Alert.append(alerts, 'Invalid email or password. Please try again.', 'danger');
      } else {
        Alert.append(alerts, e?.message || 'Login failed. Please try again.', 'danger');
      }
    } finally {
      Loading.enableForm(form);
    }
  });
});
