/**
 * Generic character counter initializer.
 *
 * Usage:
 * - Add `data-char-counter` to any <input> or <textarea> that has `maxlength`.
 * - Place a counter element near the field with class `.char-counter-display` and:
 *     <span class="char-counter-current">0</span>/MAX
 *
 * Optional per-field configuration via data attributes:
 * - data-char-counter-warn="20"   -> warning when remaining <= 20 (default 20)
 *
 * The initializer is idempotent and safe to call multiple times, including on subtrees.
 */
(function () {
  const DEFAULT_WARN_THRESHOLD = 20;

  function toInt(value, fallback) {
    const n = parseInt(String(value ?? ""), 10);
    return Number.isFinite(n) ? n : fallback;
  }

  function getMaxLength(field) {
    const maxAttr = field.getAttribute("maxlength");
    const max = toInt(maxAttr, null);
    return max && max > 0 ? max : null;
  }

  function findCounterDisplay(field) {
    // 1) Explicit selector
    const selector = field.dataset?.charCounterTarget;
    if (selector) {
      try {
        const el = document.querySelector(selector);
        if (el) return el;
      } catch {
        // ignore invalid selectors
      }
    }

    // 2) Same parent
    const parent = field.parentElement;
    if (parent) {
      const withinParent = parent.querySelector(".char-counter-display");
      if (withinParent) return withinParent;
    }

    // 3) Nearest "field group" container (Bootstrap-ish)
    const group = field.closest(".mb-3") || field.closest(".form-group") || field.closest("div");
    if (group) {
      const withinGroup = group.querySelector(".char-counter-display");
      if (withinGroup) return withinGroup;
    }

    return null;
  }

  function ensureCounterDisplay(field, max) {
    const existing = findCounterDisplay(field);
    if (existing) return existing;

    // Fallback: create a minimal display right after the field.
    const wrapper = document.createElement("div");
    wrapper.className = "d-flex justify-content-end mt-1";

    const display = document.createElement("div");
    display.className = "char-counter-display text-muted small";

    const current = document.createElement("span");
    current.className = "char-counter-current";
    current.textContent = "0";

    display.appendChild(current);
    display.appendChild(document.createTextNode(`/${max}`));
    wrapper.appendChild(display);

    field.insertAdjacentElement("afterend", wrapper);
    return display;
  }

  function updateDisplay(field, display, max) {
    const currentEl = display.querySelector(".char-counter-current");
    const length = String(field.value ?? "").length;
    const remaining = max - length;

    if (currentEl) currentEl.textContent = String(length);

    const warnThreshold = toInt(field.dataset?.charCounterWarn, DEFAULT_WARN_THRESHOLD);

    // Keep "small" and base class, swap only the state color classes.
    display.classList.remove("text-muted", "text-warning", "text-danger");
    if (remaining < 0) {
      display.classList.add("text-danger");
    } else if (remaining <= warnThreshold) {
      display.classList.add("text-warning");
    } else {
      display.classList.add("text-muted");
    }
    display.classList.add("small");
  }

  function init(container = document) {
    if (!container || !container.querySelectorAll) return;

    container.querySelectorAll("[data-char-counter]").forEach((field) => {
      if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
      if (field.dataset.charCounterInitialized === "true") {
        // Still update once in case value changed programmatically.
        const max = getMaxLength(field);
        if (!max) return;
        const display = findCounterDisplay(field);
        if (display) updateDisplay(field, display, max);
        return;
      }

      const max = getMaxLength(field);
      if (!max) return;

      const display = ensureCounterDisplay(field, max);

      // Mark initialized before attaching listener to avoid double binds in re-entrant calls.
      field.dataset.charCounterInitialized = "true";

      field.addEventListener("input", () => updateDisplay(field, display, max));

      // Initial update (handles prefilled values).
      updateDisplay(field, display, max);
    });
  }

  window.CharCounter = window.CharCounter || {};
  window.CharCounter.init = init;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => init(document));
  } else {
    init(document);
  }
})();

