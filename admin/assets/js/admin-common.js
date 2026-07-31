(() => {
    'use strict';

    if (window.__RAMKI_SIDEBAR_READY__) {
        return;
    }

    window.__RAMKI_SIDEBAR_READY__ = true;

    function initialiseRamkiSidebar() {
        const body = document.body;
        const sidebar = document.getElementById('adminSidebar');
        const toggleButton = document.getElementById('sidebarToggle');
        const closeButton = document.getElementById('sidebarClose');
        const backdrop = document.getElementById('sidebarBackdrop');
        const sidebarScroll = document.querySelector('.sidebar-scroll');

        const desktopMedia =
            window.matchMedia('(min-width: 992px)');

        let flyout = null;
        let activeFlyoutButton = null;

        if (!sidebar) {
            console.error(
                'Ramki Cards: #adminSidebar is missing.'
            );

            return;
        }

        if (!toggleButton) {
            console.error(
                'Ramki Cards: #sidebarToggle is missing.'
            );

            return;
        }

        function isDesktop() {
            return desktopMedia.matches;
        }

        function isCollapsed() {
            return (
                isDesktop() &&
                body.classList.contains('sidebar-collapsed')
            );
        }

        function saveCollapsedState() {
            localStorage.setItem(
                'ramki_sidebar_collapsed',
                body.classList.contains('sidebar-collapsed')
                    ? '1'
                    : '0'
            );
        }

        function restoreCollapsedState() {
            if (!isDesktop()) {
                body.classList.remove('sidebar-collapsed');
                return;
            }

            const collapsed =
                localStorage.getItem(
                    'ramki_sidebar_collapsed'
                ) === '1';

            body.classList.toggle(
                'sidebar-collapsed',
                collapsed
            );
        }

        function openMobileSidebar() {
            body.classList.add('sidebar-mobile-open');
            sidebar.classList.add('show');

            if (backdrop) {
                backdrop.classList.add('show');
            }
        }

        function closeMobileSidebar() {
            body.classList.remove('sidebar-mobile-open');
            sidebar.classList.remove('show');

            if (backdrop) {
                backdrop.classList.remove('show');
            }
        }

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

        function closeNormalSubmenus() {
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

        /*
        |--------------------------------------------------------------------------
        | Main sidebar toggle
        |--------------------------------------------------------------------------
        */

        toggleButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (isDesktop()) {
                closeFlyout();

                body.classList.toggle(
                    'sidebar-collapsed'
                );

                saveCollapsedState();

                if (
                    body.classList.contains(
                        'sidebar-collapsed'
                    )
                ) {
                    closeNormalSubmenus();
                }

                return;
            }

            if (
                body.classList.contains(
                    'sidebar-mobile-open'
                )
            ) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });

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
        | Create collapsed flyout
        |--------------------------------------------------------------------------
        */

        function createFlyout() {
            flyout = document.createElement('div');
            flyout.className = 'sidebar-flyout';
            flyout.id = 'sidebarFlyout';

            flyout.innerHTML = `
                <div class="sidebar-flyout-title">
                    <span id="sidebarFlyoutTitle"></span>

                    <button
                        type="button"
                        id="sidebarFlyoutClose"
                        class="border-0 bg-transparent p-0"
                        aria-label="Close submenu"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div
                    id="sidebarFlyoutLinks"
                    class="sidebar-flyout-links"
                ></div>
            `;

            document.body.appendChild(flyout);

            document
                .getElementById('sidebarFlyoutClose')
                ?.addEventListener(
                    'click',
                    closeFlyout
                );

            flyout.addEventListener(
                'click',
                function (event) {
                    event.stopPropagation();
                }
            );
        }

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
                document.getElementById(
                    'sidebarFlyoutTitle'
                );

            const linksElement =
                document.getElementById(
                    'sidebarFlyoutLinks'
                );

            if (!titleElement || !linksElement) {
                return;
            }

            titleElement.textContent = title;
            linksElement.innerHTML = '';

            submenu
                .querySelectorAll('a.sidebar-link')
                .forEach((originalLink) => {
                    const clonedLink =
                        originalLink.cloneNode(true);

                    clonedLink.classList.remove(
                        'sidebar-link',
                        'sidebar-child-link'
                    );

                    clonedLink.classList.add(
                        'sidebar-flyout-link'
                    );

                    const text =
                        clonedLink.querySelector(
                            '.sidebar-menu-text'
                        );

                    if (text) {
                        text.style.display = 'block';
                        text.style.width = 'auto';
                        text.style.opacity = '1';
                        text.style.visibility = 'visible';
                    }

                    clonedLink.addEventListener(
                        'click',
                        closeFlyout
                    );

                    linksElement.appendChild(
                        clonedLink
                    );
                });

            activeFlyoutButton = parentButton;

            parentButton.classList.add('open');

            parentButton.setAttribute(
                'aria-expanded',
                'true'
            );

            const sidebarRectangle =
                sidebar.getBoundingClientRect();

            const buttonRectangle =
                parentButton.getBoundingClientRect();

            flyout.classList.add('show');

            const flyoutHeight =
                flyout.offsetHeight;

            const viewportPadding = 12;

            let top = buttonRectangle.top;

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

            flyout.style.left =
                `${sidebarRectangle.right + 12}px`;

            flyout.style.top = `${top}px`;

            const arrowPosition = Math.max(
                18,
                Math.min(
                    flyoutHeight - 30,
                    buttonRectangle.top +
                        buttonRectangle.height / 2 -
                        top -
                        8
                )
            );

            flyout.style.setProperty(
                '--flyout-arrow-top',
                `${arrowPosition}px`
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Parent submenu controls
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '[data-submenu-toggle]'
            )
            .forEach((parentButton) => {
                parentButton.addEventListener(
                    'click',
                    function (event) {
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

                        if (isCollapsed()) {
                            const alreadyOpen =
                                activeFlyoutButton ===
                                    parentButton &&
                                flyout?.classList.contains(
                                    'show'
                                );

                            if (alreadyOpen) {
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

                        closeFlyout();

                        const shouldOpen =
                            !submenu.classList.contains(
                                'open'
                            );

                        document
                            .querySelectorAll(
                                '.sidebar-submenu.open'
                            )
                            .forEach((otherSubmenu) => {
                                if (
                                    otherSubmenu !== submenu
                                ) {
                                    otherSubmenu.classList.remove(
                                        'open'
                                    );
                                }
                            });

                        document
                            .querySelectorAll(
                                '.sidebar-parent-link.open'
                            )
                            .forEach((otherButton) => {
                                if (
                                    otherButton !==
                                    parentButton
                                ) {
                                    otherButton.classList.remove(
                                        'open'
                                    );

                                    otherButton.setAttribute(
                                        'aria-expanded',
                                        'false'
                                    );
                                }
                            });

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
        | Other closing controls
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {
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

        document.addEventListener(
            'keydown',
            function (event) {
                if (event.key === 'Escape') {
                    closeFlyout();
                    closeMobileSidebar();
                }
            }
        );

        if (sidebarScroll) {
            sidebarScroll.addEventListener(
                'scroll',
                function () {
                    if (isCollapsed()) {
                        closeFlyout();
                    }
                },
                {
                    passive: true
                }
            );
        }

        window.addEventListener(
            'resize',
            function () {
                closeFlyout();

                if (isDesktop()) {
                    closeMobileSidebar();
                    restoreCollapsedState();
                } else {
                    body.classList.remove(
                        'sidebar-collapsed'
                    );
                }
            }
        );

        restoreCollapsedState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initialiseRamkiSidebar
        );
    } else {
        initialiseRamkiSidebar();
    }
})();