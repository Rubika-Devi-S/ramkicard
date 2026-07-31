<?php
declare(strict_types=1);

require_once __DIR__ . '/theme-loader.php';

$admin = current_admin();

$pageTitle = $pageTitle ?? 'Admin Panel';

$currentPage = basename(
    parse_url(
        $_SERVER['REQUEST_URI'] ?? '',
        PHP_URL_PATH
    ) ?: ''
);

$adminRoleName = current_admin_role_name($pdo);

$pageStyles = is_array($pageStyles ?? null)
    ? $pageStyles
    : [];

$adminId = (int)($admin['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Validate current theme mode
|--------------------------------------------------------------------------
*/

$themeMode = in_array(
    $adminThemeMode ?? 'light',
    ['light', 'dark'],
    true
)
    ? (string)$adminThemeMode
    : 'light';

/*
|--------------------------------------------------------------------------
| Build body classes
|--------------------------------------------------------------------------
*/

$bodyClasses = [];

if ($adminSidebarCollapsed) {
    $bodyClasses[] = 'sidebar-collapsed';
}

if ($adminLayoutDensity === 'compact') {
    $bodyClasses[] = 'layout-compact';
}

$bodyClasses[] = $themeMode === 'dark'
    ? 'theme-dark'
    : 'theme-light';
?>
<!doctype html>

<html
    lang="en"
    data-theme="<?= e($themeMode); ?>"
    data-bs-theme="<?= e($themeMode); ?>"
    data-admin-id="<?= $adminId; ?>"
>

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="<?= e(csrf_token()); ?>"
    >

    <meta
        name="color-scheme"
        content="light dark"
    >

    <title>
        <?= e($pageTitle); ?> | Ramki Cards
    </title>

    <!-- Apply saved theme before rendering the page -->

    <script>
        (() => {
            'use strict';

            try {
                const html = document.documentElement;

                const adminId =
                    String(
                        html.dataset.adminId || 'guest'
                    );

                const storageKey =
                    `ramki_theme_mode_${adminId}`;

                const savedMode =
                    localStorage.getItem(storageKey);

                const validMode =
                    savedMode === 'dark'
                        ? 'dark'
                        : savedMode === 'light'
                            ? 'light'
                            : null;

                if (!validMode) {
                    return;
                }

                /*
                 * Custom theme selector
                 */
                html.dataset.theme = validMode;

                /*
                 * Bootstrap 5.3 theme selector
                 */
                html.setAttribute(
                    'data-bs-theme',
                    validMode
                );

                html.style.colorScheme = validMode;

            } catch (error) {
                console.warn(
                    'Unable to restore the saved admin theme.',
                    error
                );
            }
        })();
    </script>

    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;500;600;700;800;900&family=Lato:wght@400;700;900&family=Libre+Baskerville:wght@400;700&family=Montserrat:wght@400;500;600;700;800;900&family=Nunito:wght@400;500;600;700;800;900&family=Open+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Roboto:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >

    <!-- DataTables -->

    <link
        href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css"
        rel="stylesheet"
    >

    <!-- Base admin styles -->

    <link
        href="assets/css/admin.css?v=20260730-2"
        rel="stylesheet"
    >

    <!-- Sidebar and layout styles -->

    <link
        href="assets/css/admin-layout-theme.css?v=20260730-2"
        rel="stylesheet"
    >

    <!-- Page-specific styles -->

    <?php foreach ($pageStyles as $style): ?>

        <?php
        $style = basename((string)$style);

        if ($style === '') {
            continue;
        }
        ?>

        <link
            href="assets/css/<?= e($style); ?>?v=20260730-2"
            rel="stylesheet"
        >

    <?php endforeach; ?>

    <!-- Database-driven light and dark CSS variables -->

    <?php render_admin_theme_css($adminThemeSettings); ?>

    <!-- Must always be the final admin stylesheet -->

    <link
        href="assets/css/admin-night-mode.css?v=20260730-2"
        rel="stylesheet"
    >

</head>

<body class="<?= e(implode(' ', $bodyClasses)); ?>">

<div class="admin-shell">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div
        class="admin-main"
        id="adminMain"
    >

        <?php require __DIR__ . '/topbar.php'; ?>

        <main class="admin-content container-fluid">