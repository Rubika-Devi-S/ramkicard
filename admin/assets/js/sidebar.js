(() => {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate sidebar initialization
    |--------------------------------------------------------------------------
    */

    if (window.__RAMKI_SIDEBAR_INITIALIZED__) {
        return;
    }

    window.__RAMKI_SIDEBAR_INITIALIZED__ = true;

    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;

        const sidebar = document.getElementById('adminSidebar');
        const toggleButton = document.getElementById('sidebarToggle');
        const closeButton = document.getElementById('sidebarClose');
        const backdrop = document.getElementById('sidebarBackdrop');
        const sidebarScroll = document.querySelector('.sidebar-scroll');

        const desktopMedia = window.matchMedia('(min-width: 992px)');

        let flyout = null;
        let activeFlyoutButton = null;

        if (!sidebar || !toggleButton) {
            console.error(
                'Ramki sidebar error: #adminSidebar or #sidebarToggle is missing.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Device detection
        |--------------------------------------------------------------------------
        */

        function isDesktop() {
            return desktopMedia.matches;
        }

        function isSidebarCollapsed() {
            return (
                isDesktop() &&
                body.classList.contains('sidebar-collapsed')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Save and restore desktop sidebar state
        |--------------------------------------------------------------------------
        */

        function saveSidebarState(collapsed) {
            localStorage.setItem(
                'ramki_sidebar_collapsed',
                collapsed ? '1' : '0'
            );
        }

        function restoreSidebarState() {
            if (!isDesktop()) {
                body.classList.remove('sidebar-collapsed');
                return;
            }

            const collapsed =
                localStorage.getItem('ramki_sidebar_collapsed') === '1';

            body.classList.toggle(
                'sidebar-collapsed',
                collapsed
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Keep the current page visible in the sidebar
        |--------------------------------------------------------------------------
        |
        | Menu order remains controlled by admin_menus.sort_order.  We only move
        | the sidebar scroll position so a menu near the bottom (for example
        | Activity Logs) is immediately visible after navigation.
        |
        */

        function revealActiveSidebarItem() {
            if (!sidebarScroll) {
                return;
            }

            let activeLink = sidebarScroll.querySelector(
                '.sidebar-link.active:not(.sidebar-parent-link)'
            );

            /*
             * In collapsed mode a child route may be hidden inside its submenu.
             * Reveal the active parent icon instead.
             */
            if (
                !activeLink ||
                (isSidebarCollapsed() &&
                    activeLink.classList.contains('sidebar-child-link'))
            ) {
                activeLink = sidebarScroll.querySelector(
                    '.sidebar-parent-link.active'
                );
            }

            if (!activeLink) {
                return;
            }

            const containerRect =
                sidebarScroll.getBoundingClientRect();
            const activeRect = activeLink.getBoundingClientRect();
            const safeGap = 16;

            const isFullyVisible =
                activeRect.top >= containerRect.top + safeGap &&
                activeRect.bottom <= containerRect.bottom - safeGap;

            if (isFullyVisible) {
                return;
            }

            const centredOffset =
                activeRect.top -
                containerRect.top -
                (sidebarScroll.clientHeight - activeRect.height) / 2;

            sidebarScroll.scrollTo({
                top: Math.max(
                    0,
                    sidebarScroll.scrollTop + centredOffset
                ),
                behavior: 'auto'
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile sidebar
        |--------------------------------------------------------------------------
        */

        function openMobileSidebar() {
            body.classList.add('sidebar-mobile-open');
            sidebar.classList.add('show');

            if (backdrop) {
                backdrop.classList.add('show');
            }

            window.requestAnimationFrame(
                revealActiveSidebarItem
            );
        }

        function closeMobileSidebar() {
            body.classList.remove('sidebar-mobile-open');
            sidebar.classList.remove('show');

            if (backdrop) {
                backdrop.classList.remove('show');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Desktop sidebar toggle
        |--------------------------------------------------------------------------
        */

        function toggleDesktopSidebar() {
            closeFlyout();

            const collapsed =
                !body.classList.contains('sidebar-collapsed');

            body.classList.toggle(
                'sidebar-collapsed',
                collapsed
            );

            saveSidebarState(collapsed);

            /*
             * Close normal accordion menus when collapsing.
             */
            if (collapsed) {
                document
                    .querySelectorAll('.sidebar-submenu.open')
                    .forEach((submenu) => {
                        submenu.classList.remove('open');
                    });

                document
                    .querySelectorAll('.sidebar-parent-link.open')
                    .forEach((button) => {
                        button.classList.remove('open');
                        button.setAttribute(
                            'aria-expanded',
                            'false'
                        );
                    });
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Main toggle button
        |--------------------------------------------------------------------------
        */

        toggleButton.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (isDesktop()) {
                toggleDesktopSidebar();
                return;
            }

            if (body.classList.contains('sidebar-mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Mobile close controls
        |--------------------------------------------------------------------------
        */

        if (closeButton) {
            closeButton.addEventListener(
                'click',
                closeMobileSidebar
            );
        }

        if (backdrop) {
            backdrop.addEventListener(
                'click',
                closeMobileSidebar
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create collapsed floating submenu
        |--------------------------------------------------------------------------
        */

        function createFlyout() {
            flyout = document.createElement('div');

            flyout.id = 'sidebarFlyout';
            flyout.className = 'sidebar-flyout';

            flyout.innerHTML = `
                <div class="sidebar-flyout-title">
                    <span id="sidebarFlyoutTitle"></span>

                    <button
                        type="button"
                        class="btn btn-sm p-0 border-0 bg-transparent"
                        id="sidebarFlyoutClose"
                        aria-label="Close submenu"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div
                    class="sidebar-flyout-links"
                    id="sidebarFlyoutLinks"
                ></div>
            `;

            document.body.appendChild(flyout);

            const flyoutClose =
                document.getElementById('sidebarFlyoutClose');

            flyoutClose?.addEventListener(
                'click',
                closeFlyout
            );

            flyout.addEventListener(
                'click',
                (event) => {
                    event.stopPropagation();
                }
            );

            return flyout;
        }

        /*
        |--------------------------------------------------------------------------
        | Open collapsed submenu flyout
        |--------------------------------------------------------------------------
        */

        function openFlyout(parentButton, submenu) {
            if (!flyout) {
                createFlyout();
            }

            const title =
                parentButton
                    .querySelector('.sidebar-menu-text')
                    ?.textContent
                    ?.trim() ||
                parentButton.getAttribute('title') ||
                'Menu';

            const titleElement =
                document.getElementById('sidebarFlyoutTitle');

            const linksContainer =
                document.getElementById('sidebarFlyoutLinks');

            if (!titleElement || !linksContainer) {
                return;
            }

            titleElement.textContent = title;
            linksContainer.innerHTML = '';

            /*
             * Clone all submenu links into the floating box.
             */
            const submenuLinks =
                submenu.querySelectorAll('a.sidebar-link');

            submenuLinks.forEach((originalLink) => {
    const clonedLink = originalLink.cloneNode(true);

    clonedLink.classList.remove(
        'sidebar-link',
        'sidebar-child-link',
        'active'
    );

    clonedLink.classList.add('sidebar-flyout-link');

    if (originalLink.classList.contains('active')) {
        clonedLink.classList.add('active');
    }

    const textElement =
        clonedLink.querySelector('.sidebar-menu-text');

    if (textElement) {
        textElement.style.removeProperty('display');
        textElement.style.removeProperty('width');
        textElement.style.removeProperty('opacity');
        textElement.style.removeProperty('visibility');
    }

    clonedLink.addEventListener('click', closeFlyout);

    linksContainer.appendChild(clonedLink);
});
            if (!submenuLinks.length) {
                linksContainer.innerHTML = `
                    <div class="text-muted small p-2">
                        No submenu configured.
                    </div>
                `;
            }

            activeFlyoutButton = parentButton;

            parentButton.classList.add('open');
            parentButton.setAttribute(
                'aria-expanded',
                'true'
            );

            const sidebarRect =
                sidebar.getBoundingClientRect();

            const buttonRect =
                parentButton.getBoundingClientRect();

            /*
             * Show temporarily so its height can be measured.
             */
            flyout.classList.add('show');

            const viewportPadding = 12;
            const flyoutHeight = flyout.offsetHeight;

            let top = buttonRect.top;

            if (
                top + flyoutHeight >
                window.innerHeight - viewportPadding
            ) {
                top = Math.max(
                    viewportPadding,
                    window.innerHeight -
                        flyoutHeight -
                        viewportPadding
                );
            }

            const left =
                sidebarRect.right + 12;

            flyout.style.top = `${top}px`;
            flyout.style.left = `${left}px`;

            const buttonCentre =
                buttonRect.top +
                buttonRect.height / 2;

            const arrowTop = Math.max(
                18,
                Math.min(
                    flyoutHeight - 32,
                    buttonCentre - top - 8
                )
            );

            flyout.style.setProperty(
                '--flyout-arrow-top',
                `${arrowTop}px`
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Close collapsed flyout
        |--------------------------------------------------------------------------
        */

        function closeFlyout() {
            if (flyout) {
                flyout.classList.remove('show');
            }

            if (activeFlyoutButton) {
                activeFlyoutButton.classList.remove('open');
                activeFlyoutButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }

            activeFlyoutButton = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Close other accordion submenus
        |--------------------------------------------------------------------------
        */

        function closeOtherSubmenus(
            currentButton,
            currentSubmenu
        ) {
            document
                .querySelectorAll('.sidebar-parent-link')
                .forEach((button) => {
                    if (button === currentButton) {
                        return;
                    }

                    button.classList.remove('open');
                    button.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                });

            document
                .querySelectorAll('.sidebar-submenu')
                .forEach((submenu) => {
                    if (submenu === currentSubmenu) {
                        return;
                    }

                    submenu.classList.remove('open');
                });
        }

        /*
        |--------------------------------------------------------------------------
        | Parent menu click
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('[data-submenu-toggle]')
            .forEach((parentButton) => {
                parentButton.addEventListener(
                    'click',
                    (event) => {
                        event.preventDefault();
                        event.stopPropagation();

                        const submenuId =
                            parentButton.dataset
                                .submenuToggle;

                        const submenu =
                            document.querySelector(
                                `[data-submenu="${submenuId}"]`
                            );

                        if (!submenu) {
                            return;
                        }

                        /*
                         * Collapsed desktop:
                         * display a floating submenu.
                         */
                        if (isSidebarCollapsed()) {
                            const sameButtonOpen =
                                activeFlyoutButton ===
                                    parentButton &&
                                flyout?.classList.contains(
                                    'show'
                                );

                            if (sameButtonOpen) {
                                closeFlyout();
                            } else {
                                closeFlyout();

                                openFlyout(
                                    parentButton,
                                    submenu
                                );
                            }

                            return;
                        }

                        /*
                         * Expanded desktop/mobile:
                         * use normal accordion submenu.
                         */
                        closeFlyout();

                        const shouldOpen =
                            !submenu.classList.contains(
                                'open'
                            );

                        closeOtherSubmenus(
                            parentButton,
                            submenu
                        );

                        submenu.classList.toggle(
                            'open',
                            shouldOpen
                        );

                        parentButton.classList.toggle(
                            'open',
                            shouldOpen
                        );

                        parentButton.setAttribute(
                            'aria-expanded',
                            shouldOpen
                                ? 'true'
                                : 'false'
                        );
                    }
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Close mobile sidebar after selecting a route
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.sidebar-link[href]:not([href="#"])'
            )
            .forEach((link) => {
                link.addEventListener(
                    'click',
                    () => {
                        if (!isDesktop()) {
                            closeMobileSidebar();
                        }
                    }
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Outside click
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            (event) => {
                if (
                    event.target.closest(
                        '.sidebar-flyout'
                    ) ||
                    event.target.closest(
                        '[data-submenu-toggle]'
                    )
                ) {
                    return;
                }

                closeFlyout();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Escape key
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                closeFlyout();
                closeMobileSidebar();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Sidebar scrolling
        |--------------------------------------------------------------------------
        */

        if (sidebarScroll) {
            sidebarScroll.addEventListener(
                'scroll',
                () => {
                    if (isSidebarCollapsed()) {
                        closeFlyout();
                    }
                },
                {
                    passive: true
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Window resize
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'resize',
            () => {
                closeFlyout();

                if (isDesktop()) {
                    closeMobileSidebar();
                    restoreSidebarState();
                } else {
                    body.classList.remove(
                        'sidebar-collapsed'
                    );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Initial state
        |--------------------------------------------------------------------------
        */

        restoreSidebarState();

        /*
         * Wait until the active submenu/open state and sidebar dimensions have
         * been applied before calculating the scroll position.
         */
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(
                revealActiveSidebarItem
            );
        });
    });
})();
