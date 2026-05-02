(function() {
    const storageKey = "chatsmart-theme";
    const root = document.documentElement;

    function resolvedTheme() {
        const saved = window.localStorage.getItem(storageKey);
        if (saved === "light-theme" || saved === "dark-theme") {
            return saved;
        }

        return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark-theme"
            : "light-theme";
    }

    function applyTheme(theme, persist) {
        root.classList.remove("light-theme", "dark-theme", "semi-dark", "minimal-theme");
        root.classList.add(theme);

        if (persist !== false) {
            window.localStorage.setItem(storageKey, theme);
        }

        updateThemeToggle();
    }

    function updateThemeToggle() {
        const trigger = document.querySelector("[data-theme-toggle]");
        if (!trigger) {
            return;
        }

        const icon = trigger.querySelector("[data-theme-icon]");
        const label = trigger.querySelector("[data-theme-label]");
        const isDark = root.classList.contains("dark-theme");

        if (icon) {
            icon.className = isDark ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
        }

        if (label) {
            label.textContent = isDark ? "Light" : "Dark";
        }

        trigger.setAttribute("title", isDark ? "Switch to light mode" : "Switch to dark mode");
        trigger.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
    }

    document.addEventListener("DOMContentLoaded", function() {
        applyTheme(resolvedTheme(), false);

        const trigger = document.querySelector("[data-theme-toggle]");
        if (trigger) {
            trigger.addEventListener("click", function() {
                applyTheme(root.classList.contains("dark-theme") ? "light-theme" : "dark-theme", true);
            });
        }

        const dismiss = document.querySelector("[data-dismiss-welcome]");
        if (dismiss) {
            dismiss.addEventListener("click", function() {
                const card = document.querySelector(".dashboard-welcome-card");
                if (card) {
                    card.classList.add("welcome-hidden");
                }
            });
        }
    });
})();
