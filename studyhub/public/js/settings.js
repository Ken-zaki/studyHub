/**
 * settings.js
 * Handles all interactivity for the Settings page:
 *   - Panel switching (left menu nav)
 *   - FAQ accordion
 *   - Theme selection (light / dark / auto)
 *   - Accent color picker
 *   - Font size adjuster
 *   - Toggle persistence (localStorage)
 */

/* ════════════════════════════════════════════
   PANEL SWITCHING
════════════════════════════════════════════ */

/**
 * Switch the visible settings panel and update the active menu item.
 * @param {string} id   - Panel id suffix, e.g. 'about', 'theme'
 * @param {Element} btn - The menu button that was clicked
 */
function switchPanel(id, btn) {
    document
        .querySelectorAll(".settings-panel")
        .forEach((p) => p.classList.remove("active"));
    document
        .querySelectorAll(".settings-menu-item")
        .forEach((b) => b.classList.remove("active"));
    document.getElementById("panel-" + id).classList.add("active");
    btn.classList.add("active");
}

/* ════════════════════════════════════════════
   FAQ ACCORDION
════════════════════════════════════════════ */

/**
 * Toggle a FAQ item open/closed. Only one item can be open at a time.
 * @param {number} index - The FAQ item index
 */
function toggleFaq(index) {
    const item = document.getElementById("faq-" + index);
    const wasOpen = item.classList.contains("open");
    document
        .querySelectorAll(".faq-item")
        .forEach((i) => i.classList.remove("open"));
    if (!wasOpen) item.classList.add("open");
}

/* ════════════════════════════════════════════
   THEME
════════════════════════════════════════════ */

const prefersDark = window.matchMedia("(prefers-color-scheme: dark)");

/**
 * Apply a theme to the document root and save it.
 * @param {'light'|'dark'|'auto'} theme
 */
function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === "dark") {
        root.setAttribute("data-theme", "dark");
    } else if (theme === "light") {
        root.setAttribute("data-theme", "light");
    } else {
        // auto — follow the OS
        root.setAttribute("data-theme", prefersDark.matches ? "dark" : "light");
    }
    localStorage.setItem("sh_theme", theme);
}

/**
 * Called when a theme card is clicked.
 * @param {'light'|'dark'|'auto'} theme
 * @param {Element} el - The clicked .theme-option element
 */
function selectTheme(theme, el) {
    document
        .querySelectorAll(".theme-option")
        .forEach((o) => o.classList.remove("selected"));
    el.classList.add("selected");
    applyTheme(theme);
}

// Re-apply auto theme if OS preference changes while the page is open
prefersDark.addEventListener("change", () => {
    const saved = localStorage.getItem("sh_theme");
    if (saved === "auto") applyTheme("auto");
});

/* ════════════════════════════════════════════
   ACCENT COLOR
════════════════════════════════════════════ */

/**
 * Apply an accent color to the CSS --primary variable and save it.
 * @param {Element} el - The clicked .accent-color element
 */
function selectAccent(el) {
    document
        .querySelectorAll(".accent-color")
        .forEach((a) => a.classList.remove("selected"));
    el.classList.add("selected");
    const color = el.dataset.accent;
    document.documentElement.style.setProperty("--primary", color);
    localStorage.setItem("sh_accent", color);
}

/* ════════════════════════════════════════════
   FONT SIZE
════════════════════════════════════════════ */

let fontSize = parseInt(localStorage.getItem("sh_font_size") || "16");

function renderFontSize() {
    const display = document.getElementById("font-size-display");
    if (display) display.textContent = fontSize + "px";
    document.body.style.fontSize = fontSize + "px";
}

/**
 * Increase or decrease the interface font size (clamped 13–20px).
 * @param {number} delta - +1 or -1
 */
function changeFontSize(delta) {
    fontSize = Math.min(20, Math.max(13, fontSize + delta));
    localStorage.setItem("sh_font_size", fontSize);
    renderFontSize();
}

/* ════════════════════════════════════════════
   TOGGLE PERSISTENCE
════════════════════════════════════════════ */

/**
 * Save a toggle preference to localStorage.
 * Swap this for an AJAX call if you want server-side persistence.
 * @param {string}  key   - The checkbox element id
 * @param {boolean} value - Checked state
 */
function saveToggle(key, value) {
    localStorage.setItem("sh_pref_" + key, value ? "1" : "0");
}

/* ════════════════════════════════════════════
   INIT — restore all saved preferences on load
════════════════════════════════════════════ */

(function init() {
    // ── Theme
    const savedTheme = localStorage.getItem("sh_theme") || "light";
    applyTheme(savedTheme);
    const themeEl = document.getElementById("theme-" + savedTheme);
    if (themeEl) {
        document
            .querySelectorAll(".theme-option")
            .forEach((o) => o.classList.remove("selected"));
        themeEl.classList.add("selected");
    }

    // ── Accent color
    const savedAccent = localStorage.getItem("sh_accent");
    if (savedAccent) {
        document.documentElement.style.setProperty("--primary", savedAccent);
        document.querySelectorAll(".accent-color").forEach((a) => {
            a.classList.toggle("selected", a.dataset.accent === savedAccent);
        });
    }

    // ── Font size
    renderFontSize();

    // ── Toggle states
    document.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
        const saved = localStorage.getItem("sh_pref_" + cb.id);
        if (saved !== null) cb.checked = saved === "1";
    });
})();
