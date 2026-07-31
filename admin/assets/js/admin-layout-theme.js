(() => {
    const html = document.documentElement;
    const body = document.body;
    const desktopBreakpoint = 992;
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarScroll = document.querySelector('.sidebar-scroll');
    const themeToggle = document.getElementById('themeToggle');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let flyout = null;
    let flyoutParentId = null;

    function postPreference(url, values) {
        const bodyData = new URLSearchParams(values);

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrf,
            },
            body: bodyData.toString(),
        }).catch(() => {
            // Keep the locally saved preference if the server request fails.
        });
    }

    function isDesktop() {
        return window.innerWidth >= desktopBreakpoint;
    }

    function isCollapsedDesktop() {
        return isDesktop() && body.classList.contains('sidebar-collapsed');
    }

    function closeFlyout() {
        if (!flyout) return;

        flyout.classList.remove('show');
        flyoutParentId = null;

        window.setTimeout(() => {
            if (flyout && !flyout.classList.contains('show')) {
                flyout.remove();
                flyout = null;
            }
        }, 170);
    }

    function flyoutLinksFromSubmenu(submenu) {
        return Array.from(submenu.querySelectorAll('a.sidebar-link[href]')).map(link => {
            const clone = link.cloneNode(true);
            clone.classList.remove('sidebar-link', 'sidebar-child-link');
            clone.classList.add('sidebar-flyout-link');
            clone.removeAttribute('style');
            return clone.outerHTML;
        }).join('');
    }

    function openFlyout(button, submenu) {
        const parentId = button.getAttribute('data-submenu-toggle') || '';

        if (flyoutParentId === parentId && flyout?.classList.contains('show')) {
            closeFlyout();
            return;
        }

        closeFlyout();

        flyout = document.createElement('div');
        flyout.className = 'sidebar-flyout';
        flyout.setAttribute('role', 'menu');

        const title = submenu.dataset.parentTitle || button.getAttribute('title') || 'Menu';
        const links = flyoutLinksFromSubmenu(submenu);

        flyout.innerHTML = `
            <div class="sidebar-flyout-title">
                <span>${escapeHtml(title)}</span>
            </div>
            <div class="sidebar-flyout-links">
                ${links || '<div class="px-2 py-3 text-muted small">No submenu configured.</div>'}
            </div>
        `;

        document.body.appendChild(flyout);
        flyoutParentId = parentId;

        positionFlyout(button);
        requestAnimationFrame(() => flyout?.classList.add('show'));

        flyout.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeFlyout);
        });
    }

    function positionFlyout(button) {
        if (!flyout || !button) return;

        const buttonRect = button.getBoundingClientRect();
        const sidebarRect = sidebar?.getBoundingClientRect();
        const gap = 12;
        const viewportPadding = 12;

        const left = Math.round((sidebarRect?.right || 82) + gap);
        flyout.style.left = `${left}px`;
        flyout.style.top = `${Math.max(viewportPadding, Math.round(buttonRect.top))}px`;

        const flyoutRect = flyout.getBoundingClientRect();
        let top = Math.max(viewportPadding, buttonRect.top);

        if (top + flyoutRect.height > window.innerHeight - viewportPadding) {
            top = Math.max(viewportPadding, window.innerHeight - flyoutRect.height - viewportPadding);
        }

        const arrowTop = Math.max(18, Math.min(flyoutRect.height - 24, buttonRect.top + buttonRect.height / 2 - top - 8));

        flyout.style.top = `${Math.round(top)}px`;
        flyout.style.setProperty('--flyout-arrow-top', `${Math.round(arrowTop)}px`);
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function closeMobileSidebar() {
        body.classList.remove('sidebar-mobile-open');
        sidebar?.classList.remove('show');
        sidebarBackdrop?.classList.remove('show');
        closeFlyout();
    }

    function setDesktopCollapsed(collapsed, persist = true) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem('ramki_sidebar_collapsed', collapsed ? '1' : '0');
        closeFlyout();

        if (persist) {
            postPreference('api/save-sidebar-state.php', {
                collapsed: collapsed ? '1' : '0',
            });
        }
    }

    sidebarToggle?.addEventListener('click', () => {
        if (!isDesktop()) {
            const open = !body.classList.contains('sidebar-mobile-open');
            body.classList.toggle('sidebar-mobile-open', open);
            sidebar?.classList.toggle('show', open);
            sidebarBackdrop?.classList.toggle('show', open);
            closeFlyout();
            return;
        }

        setDesktopCollapsed(!body.classList.contains('sidebar-collapsed'));
    });

    sidebarClose?.addEventListener('click', closeMobileSidebar);
    sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

    document.querySelectorAll('[data-submenu-toggle]').forEach(button => {
        button.addEventListener('click', event => {
            const id = button.getAttribute('data-submenu-toggle');
            const submenu = document.querySelector(`[data-submenu="${CSS.escape(id)}"]`);

            if (!submenu) return;

            if (isCollapsedDesktop()) {
                event.preventDefault();
                event.stopPropagation();
                openFlyout(button, submenu);
                return;
            }

            closeFlyout();

            const open = !button.classList.contains('open');
            button.classList.toggle('open', open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            submenu.classList.toggle('open', open);
        });
    });

    document.querySelectorAll('.sidebar-link[href]').forEach(link => {
        link.addEventListener('click', () => {
            closeFlyout();
            if (!isDesktop()) closeMobileSidebar();
        });
    });

    document.addEventListener('click', event => {
        if (!flyout?.classList.contains('show')) return;
        if (flyout.contains(event.target)) return;
        if (event.target.closest('[data-submenu-toggle]')) return;
        closeFlyout();
    });

    sidebarScroll?.addEventListener('scroll', () => {
        if (!flyout?.classList.contains('show')) return;
        const button = document.querySelector(`[data-submenu-toggle="${CSS.escape(flyoutParentId || '')}"]`);
        if (button) positionFlyout(button);
    }, { passive: true });

    function updateThemeButton(mode) {
        if (!themeToggle) return;
        themeToggle.dataset.themeMode = mode;
        const icon = themeToggle.querySelector('i');
        if (!icon) return;
        icon.classList.toggle('fa-moon', mode !== 'dark');
        icon.classList.toggle('fa-sun', mode === 'dark');
    }

    themeToggle?.addEventListener('click', () => {
        const current = html.dataset.theme === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        html.dataset.theme = next;
        localStorage.setItem('ramki_theme_mode', next);
        updateThemeButton(next);

        postPreference('api/save-theme.php', { mode: next });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeFlyout();
            closeMobileSidebar();
        }
    });

    window.addEventListener('resize', () => {
        closeFlyout();
        if (isDesktop()) closeMobileSidebar();
    });

    window.addEventListener('scroll', closeFlyout, { passive: true });

    updateThemeButton(html.dataset.theme === 'dark' ? 'dark' : 'light');
})();
