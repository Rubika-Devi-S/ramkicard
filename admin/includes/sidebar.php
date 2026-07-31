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
<a href="<?= e($href); ?>" class="sidebar-link<?= $isRouteActive($route) ? ' active' : ''; ?><?= e($levelClass); ?>"
    title="<?= e((string)$item['menu_name']); ?>">
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

    <nav class="sidebar-scroll">
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