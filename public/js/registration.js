document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registration-form");
  const alerts = document.getElementById("alert-placeholder");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const data = Object.fromEntries(new FormData(form));

    try {
      Loading.clearAndShow(alerts, "Registering...");
      Loading.disableForm(form);

      await Api.post(window.routes.registerUser, {
        email: data.email,
        password: data.password,
        name: data.name,
        bio: data.bio,
      }, { skipAuth: true });

      const auth = await Api.post(window.routes.login, {
        email: data.email,
        password: data.password,
      }, { skipAuth: true });

      // Handle both token and accessToken response formats
      const accessToken = auth.token || auth.accessToken;
      const refreshToken = auth.refresh_token || auth.refreshToken;

      if (!accessToken) {
        throw new Error("Access token is missing.");
      }

      // Store access token
      Api.setToken(accessToken);

      // Store refresh token if present
      if (refreshToken) {
        Api.setRefreshToken(refreshToken);
      }

      Loading.hide(alerts);
      Alert.append(alerts, "You have been successfully registered!", "success");
      window.location.href = window.routes.successRedirect;
    } catch (e) {
      Loading.hide(alerts);
      if (e?.code === "USER_ALREADY_EXISTS") {
        Alert.append(
          alerts,
          "An account with this email already exists. Try logging in instead.",
          "warning"
        );
      } else if (e?.isValidationError?.()) {
        Alert.appendValidationErrors(alerts, e.errors, "Please fix the following errors:");
      } else {
        Alert.append(
          alerts,
          e?.message || "Registration failed. Please try again.",
          "danger"
        );
      }
    } finally {
      Loading.enableForm(form);
    }
  });
});
