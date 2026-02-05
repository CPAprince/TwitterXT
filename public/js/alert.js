window.Alert = {
  append(container, message, type) {
    container.replaceChildren();

    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="alert alert-${type} alert-dismissible" role="alert">
        <div>${message}</div>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"></button>
      </div>
    `;
    container.append(wrapper);
  },

  appendValidationErrors(container, errors, title = "Please fix the following:") {
    container.replaceChildren();

    const wrapper = document.createElement("div");
    const alert = document.createElement("div");
    alert.className = "alert alert-danger";
    alert.setAttribute("role", "alert");

    const titleEl = document.createElement("div");
    titleEl.textContent = title;
    alert.appendChild(titleEl);

    const list = document.createElement("ul");
    list.className = "mb-0 mt-2";

    (Array.isArray(errors) ? errors : []).forEach((e) => {
      const li = document.createElement("li");

      const strong = document.createElement("strong");
      strong.textContent = `${e?.field ?? "field"}: `;
      li.appendChild(strong);

      li.appendChild(document.createTextNode(String(e?.message ?? "")));
      list.appendChild(li);
    });

    alert.appendChild(list);
    wrapper.appendChild(alert);
    container.append(wrapper);
  },

  appendApiError(container, error, fallbackMessage = "An error occurred") {
    if (error?.errors && Array.isArray(error.errors) && error.errors.length > 0) {
      this.appendValidationErrors(container, error.errors);
      return;
    }

    this.append(container, error?.message || fallbackMessage, "danger");
  },
};
