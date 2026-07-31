<?php
declare(strict_types=1);

$userName = (string)($admin['name'] ?? 'Administrator');
$userEmail = (string)($admin['email'] ?? '');

$userInitial = strtoupper(
    substr(trim($userName), 0, 1)
) ?: 'A';

$currentThemeMode = in_array(
    $adminThemeMode ?? 'light',
    ['light', 'dark'],
    true
)
    ? $adminThemeMode
    : 'light';

$isDarkTheme = $currentThemeMode === 'dark';

$themeIcon = $isDarkTheme
    ? 'fa-sun'
    : 'fa-moon';

$themeButtonText = $isDarkTheme
    ? 'Switch to light mode'
    : 'Switch to dark mode';
?>

<header
    class="admin-topbar"
    id="adminTopbar"
>
    <div class="topbar-left">

        <button
            type="button"
            class="topbar-icon-button"
            id="sidebarToggle"
            aria-label="Toggle sidebar"
            aria-expanded="<?= $adminSidebarCollapsed ? 'false' : 'true'; ?>"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="topbar-page-copy">

            <h1>
                <?= e($pageTitle); ?>
            </h1>

            <div class="topbar-breadcrumb d-none d-sm-flex">

                <a href="<?= e(admin_url('dashboard.php')); ?>">
                    Dashboard
                </a>

                <?php if ($currentPage !== 'dashboard.php'): ?>

                    <i class="fa-solid fa-chevron-right"></i>

                    <span>
                        <?= e($pageTitle); ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <div class="topbar-actions">

        <a
            href="../index.php"
            target="_blank"
            class="topbar-icon-button d-none d-md-inline-grid"
            title="View website"
            aria-label="View website"
        >
            <i class="fa-solid fa-globe"></i>
        </a>

        <!-- Light / dark mode button -->

        <button
            type="button"
            class="topbar-icon-button"
            id="themeToggle"
            data-theme-mode="<?= e($currentThemeMode); ?>"
            title="<?= e($themeButtonText); ?>"
            aria-label="<?= e($themeButtonText); ?>"
            aria-pressed="<?= $isDarkTheme ? 'true' : 'false'; ?>"
        >
            <i class="fa-solid <?= e($themeIcon); ?>"></i>
        </button>

        <div class="dropdown">

            <button
                type="button"
                class="profile-button dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="profile-avatar">
                    <?= e($userInitial); ?>
                </span>

                <span class="profile-copy d-none d-sm-block">

                    <strong>
                        <?= e($userName); ?>
                    </strong>

                    <small>
                        <?= e($adminRoleName); ?>
                    </small>

                </span>

            </button>

            <ul class="dropdown-menu dropdown-menu-end profile-menu">

                <li class="px-3 py-2">

                    <strong class="d-block">
                        <?= e($userName); ?>
                    </strong>

                    <small class="text-muted text-break">
                        <?= e($userEmail); ?>
                    </small>

                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <?php if (
                    can_menu($pdo, 'sidebar_settings') ||
                    is_super_admin($pdo)
                ): ?>

                    <li>
                        <a
                            class="dropdown-item"
                            href="<?= e(admin_url('sidebar-settings.php')); ?>"
                        >
                            <i class="fa-solid fa-table-columns me-2"></i>
                            Sidebar Settings
                        </a>
                    </li>

                <?php endif; ?>

                <?php if (
                    can_menu($pdo, 'theme_settings') ||
                    is_super_admin($pdo)
                ): ?>

                    <li>
                        <a
                            class="dropdown-item"
                            href="<?= e(admin_url('theme-settings.php')); ?>"
                        >
                            <i class="fa-solid fa-palette me-2"></i>
                            Theme Settings
                        </a>
                    </li>

                <?php endif; ?>

                <?php if (
                    can_menu($pdo, 'settings') ||
                    is_super_admin($pdo)
                ): ?>

                    <li>
                        <a
                            class="dropdown-item"
                            href="<?= e(admin_url('site-settings.php')); ?>"
                        >
                            <i class="fa-solid fa-gears me-2"></i>
                            Site Settings
                        </a>
                    </li>

                <?php endif; ?>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>
                    <a
                        class="dropdown-item text-danger"
                        href="<?= e(admin_url('logout.php')); ?>"
                    >
                        <i class="fa-solid fa-right-from-bracket me-2"></i>
                        Logout
                    </a>
                </li>

            </ul>

        </div>

    </div>
</header>