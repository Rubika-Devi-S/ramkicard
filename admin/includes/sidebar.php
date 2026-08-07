<?php
declare(strict_types=1);

$sidebarTree = load_admin_sidebar_tree($pdo);

$isRouteActive = static function (?string $route) use ($currentPage): bool {
    $basename = admin_menu_route_basename($route);
    return $basename !== '' && $basename !== '#' && $basename === $currentPage;
};

$hasActiveDescendant = null;
$hasActiveDescendant = function (array $item) use (&$hasActiveDescendant, $isRouteActive): bool {
    if ($isRouteActive($item['route_name'] ?? '')) {
        return true;
    }
    foreach ($item['children'] ?? [] as $child) {
        if ($hasActiveDescendant($child)) {
            return true;
        }
    }
    return false;
};

$renderItems = null;
$renderItems = function (array $items, int $level = 0) use (&$renderItems, $hasActiveDescendant, $isRouteActive): void {
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $children = $item['children'] ?? [];
        $hasChildren = count($children) > 0;
        $active = $hasActiveDescendant($item);
        $route = trim((string)($item['route_name'] ?? '#'));
        $icon = admin_menu_icon_class($item['icon_class'] ?? '');
        $levelClass = $level > 0 ? ' sidebar-child-link' : '';

        if ($hasChildren) {
            ?>
<button type="button"
    class="sidebar-link sidebar-parent-link<?= $active ? ' active open' : ''; ?><?= e($levelClass); ?>"
    data-submenu-toggle="<?= $id; ?>" aria-expanded="<?= $active ? 'true' : 'false'; ?>"
    aria-controls="sidebarSubmenu<?= $id; ?>" title="<?= e((string)$item['menu_name']); ?>">
    <span class="sidebar-link-icon"><i class="<?= e($icon); ?>"></i></span>
    <span class="sidebar-menu-text"><?= e((string)$item['menu_name']); ?></span>
    <span class="sidebar-chevron"><i class="fa-solid fa-chevron-down"></i></span>
</button>
<div class="sidebar-submenu<?= $active ? ' open' : ''; ?>" id="sidebarSubmenu<?= $id; ?>" data-submenu="<?= $id; ?>"
    data-parent-title="<?= e((string)$item['menu_name']); ?>">
    <?php $renderItems($children, $level + 1); ?>
</div>
<?php
            continue;
        }

        $href = ($route === '' || $route === '#') ? '#' : admin_url($route);
        ?>
<?php $currentRoute = $isRouteActive($route); ?>
<a href="<?= e($href); ?>" class="sidebar-link<?= $currentRoute ? ' active' : ''; ?><?= e($levelClass); ?>"
    title="<?= e((string)$item['menu_name']); ?>"
    <?= $currentRoute ? 'aria-current="page" data-sidebar-current="true"' : ''; ?>>
    <span class="sidebar-link-icon"><i class="<?= e($icon); ?>"></i></span>
    <span class="sidebar-menu-text"><?= e((string)$item['menu_name']); ?></span>
</a>
<?php
    }
};
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <a href="<?= e(admin_url('dashboard.php')); ?>" class="sidebar-brand-link">
            <span class="sidebar-logo">
                <img src="../logo.png" alt="Ramki Cards"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                <span class="sidebar-logo-fallback">R</span>
            </span>
            <span class="sidebar-brand-copy">
                <strong>Ramki Cards</strong>
                <small>ADMIN PANEL</small>
            </span>
        </a>

        <button type="button" class="sidebar-close d-lg-none" id="sidebarClose" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-scroll" id="sidebarScroll">
        <?php if (!$sidebarTree): ?>
        <div class="sidebar-empty">
            <i class="fa-solid fa-circle-info"></i>
            <span class="sidebar-menu-text">No sidebar menu configured.</span>
        </div>
        <?php else: ?>
        <?php $renderItems($sidebarTree); ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" class="sidebar-website-link" title="View website">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <span class="sidebar-menu-text">View Website</span>
        </a>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<style>
/*
 * The menu area must be a real scroll container.  Without min-height: 0 a
 * flex child may expand past the viewport, leaving the last active menu
 * clipped behind the footer instead of scrollable.
 */
#adminSidebar {
    display: flex;
    flex-direction: column;
    height: 100vh;
    height: 100dvh;
    overflow: hidden;
}

#adminSidebar .sidebar-brand,
#adminSidebar .sidebar-footer {
    flex: 0 0 auto;
}

#adminSidebar .sidebar-scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto !important;
    overscroll-behavior: contain;
}
</style>

<script>
/*
 * Component-level active menu reveal.
 *
 * This intentionally lives with sidebar.php as a reliable fallback even when
 * a browser still has an older cached sidebar.js.  The normal sidebar.js also
 * performs the same reveal for desktop/mobile state changes.
 */
(function () {
    'use strict';

    function revealCurrentSidebarMenu() {
        var sidebarScroll = document.getElementById('sidebarScroll');

        if (!sidebarScroll) {
            return;
        }

        var currentLink = sidebarScroll.querySelector(
            '[data-sidebar-current="true"]'
        );

        if (!currentLink) {
            currentLink = sidebarScroll.querySelector(
                '.sidebar-link.active:not(.sidebar-parent-link)'
            );
        }

        if (!currentLink) {
            currentLink = sidebarScroll.querySelector(
                '.sidebar-parent-link.active'
            );
        }

        if (!currentLink) {
            return;
        }

        /* A hidden child in collapsed mode cannot be measured reliably. */
        if (currentLink.offsetParent === null) {
            var activeParent = sidebarScroll.querySelector(
                '.sidebar-parent-link.active'
            );

            if (activeParent) {
                currentLink = activeParent;
            }
        }

        var containerRect = sidebarScroll.getBoundingClientRect();
        var currentRect = currentLink.getBoundingClientRect();
        var maxScroll = Math.max(
            0,
            sidebarScroll.scrollHeight - sidebarScroll.clientHeight
        );

        var targetTop =
            sidebarScroll.scrollTop +
            (currentRect.top - containerRect.top) -
            ((sidebarScroll.clientHeight - currentRect.height) / 2);

        sidebarScroll.scrollTop = Math.max(
            0,
            Math.min(maxScroll, targetTop)
        );
    }

    function revealAfterLayout() {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(revealCurrentSidebarMenu);
        });

        /*
         * Fonts, theme CSS and saved collapsed state can change dimensions
         * shortly after DOMContentLoaded, so retry after those settle.
         */
        window.setTimeout(revealCurrentSidebarMenu, 80);
        window.setTimeout(revealCurrentSidebarMenu, 250);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', revealAfterLayout);
    } else {
        revealAfterLayout();
    }

    window.addEventListener('load', revealAfterLayout);
})();
</script>
