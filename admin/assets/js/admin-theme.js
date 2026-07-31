(() => {
    'use strict';

    if (window.__RAMKI_ADMIN_THEME_READY__) {
        return;
    }

    window.__RAMKI_ADMIN_THEME_READY__ = true;

    const html = document.documentElement;
    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const adminId = String(html.dataset.adminId || 'guest');
    const storageKey = `ramki_theme_mode_${adminId}`;

    function normaliseMode(value) {
        return value === 'dark' ? 'dark' : 'light';
    }

    function currentMode() {
        return normaliseMode(html.dataset.theme);
    }

    function themeButton() {
        return document.getElementById('themeToggle');
    }

    function updateThemeButton(mode) {
        const button = themeButton();

        if (!button) {
            return;
        }

        const dark = mode === 'dark';
        const icon = button.querySelector('i');

        button.dataset.themeMode = mode;
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        button.setAttribute(
            'aria-label',
            dark ? 'Switch to light mode' : 'Switch to dark mode'
        );
        button.setAttribute(
            'title',
            dark ? 'Switch to light mode' : 'Switch to dark mode'
        );

        if (icon) {
            icon.classList.toggle('fa-moon', !dark);
            icon.classList.toggle('fa-sun', dark);
        }
    }

    function applyTheme(mode, persistLocal = true) {
        const selected = normaliseMode(mode);

        html.dataset.theme = selected;
        html.style.colorScheme = selected;

        if (document.body) {
            document.body.classList.toggle(
                'theme-dark',
                selected === 'dark'
            );
            document.body.classList.toggle(
                'theme-light',
                selected === 'light'
            );
        }

        updateThemeButton(selected);

        if (persistLocal) {
            localStorage.setItem(storageKey, selected);
        }

        window.dispatchEvent(
            new CustomEvent('ramki:theme-changed', {
                detail: { mode: selected },
            })
        );

        return selected;
    }

    async function saveThemePreference(mode) {
        const body = new URLSearchParams();
        body.set('mode', mode);
        body.set('_token', csrf);

        try {
            const response = await fetch('api/save-theme.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrf,
                    'Accept': 'application/json',
                },
                body: body.toString(),
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result?.success) {
                throw new Error(
                    result?.message || 'Unable to save theme preference.'
                );
            }
        } catch (error) {
            console.warn('Theme preference was saved locally only.', error);

            if (window.RamkiAdmin?.toast) {
                RamkiAdmin.toast(
                    'warning',
                    'Theme changed, but the server preference could not be saved.'
                );
            }
        }
    }

    function initialise() {
        /*
         * Local storage is admin-specific. The database value rendered on the
         * html element is used when this browser has no local preference.
         */
        const localMode = localStorage.getItem(storageKey);
        const initialMode = normaliseMode(
            localMode || html.dataset.theme || 'light'
        );

        applyTheme(initialMode, Boolean(localMode));

        const button = themeButton();

        if (!button) {
            console.error(
                'Ramki Cards theme: #themeToggle is missing from the topbar.'
            );
            return;
        }

        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const next = currentMode() === 'dark' ? 'light' : 'dark';

            applyTheme(next, true);
            await saveThemePreference(next);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, {
            once: true,
        });
    } else {
        initialise();
    }
})();
