<?php
declare(strict_types=1);

$adminThemeSettings = load_admin_theme_settings($pdo);
$adminUserPreferences = load_admin_user_preferences(
    $pdo,
    current_admin_id()
);

$adminThemeMode = $adminUserPreferences['theme_mode'];
$adminSidebarCollapsed =
    (int)$adminUserPreferences['sidebar_collapsed'] === 1;

$adminThemeOption = static function (
    string $key,
    string $default
) use ($adminThemeSettings): string {
    return trim((string)(
        $adminThemeSettings[$key]['light']
        ?? $default
    ));
};

$adminThemeOptions = [
    'font_family' => $adminThemeOption(
        'font_family',
        'Poppins, Arial, sans-serif'
    ),
    'heading_font_family' => $adminThemeOption(
        'heading_font_family',
        '"Playfair Display", Georgia, serif'
    ),
    'base_font_size' => $adminThemeOption('base_font_size', '14'),
    'heading_font_size' => $adminThemeOption('heading_font_size', '25'),
    'font_weight' => $adminThemeOption('font_weight', '400'),
    'heading_font_weight' => $adminThemeOption('heading_font_weight', '700'),
    'line_height' => $adminThemeOption('line_height', '1.5'),
    'letter_spacing' => $adminThemeOption('letter_spacing', '0'),
    'button_text_transform' => $adminThemeOption('button_text_transform', 'none'),
    'sidebar_style' => $adminThemeOption('sidebar_style', 'gradient'),
    'navbar_style' => $adminThemeOption('navbar_style', 'solid'),
    'card_style' => $adminThemeOption('card_style', 'elevated'),
    'button_style' => $adminThemeOption('button_style', 'rounded'),
    'table_style' => $adminThemeOption('table_style', 'clean'),
    'table_density' => $adminThemeOption('table_density', 'comfortable'),
    'card_radius' => $adminThemeOption('card_radius', '18'),
    'button_radius' => $adminThemeOption('button_radius', '11'),
    'sidebar_width' => $adminThemeOption('sidebar_width', '276'),
    'navbar_height' => $adminThemeOption('navbar_height', '76'),
    'page_spacing' => $adminThemeOption('page_spacing', '24'),
    'layout_width' => $adminThemeOption('layout_width', 'fluid'),
    'layout_density' => $adminThemeOption('layout_density', 'comfortable'),
    'content_density' => $adminThemeOption('content_density', 'comfortable'),
];

$adminLayoutDensity = $adminThemeOptions['layout_density'];

if (!function_exists('render_admin_theme_css')) {
    function render_admin_theme_css(array $settings): void
    {
        $option = static function (
            string $key,
            string $default
        ) use ($settings): string {
            return trim((string)(
                $settings[$key]['light']
                ?? $default
            ));
        };

        $colourKeys = [];

        foreach ($settings as $key => $values) {
            if (($values['type'] ?? '') === 'color') {
                $colourKeys[] = $key;
            }
        }

        /*
         * Apply the exact font families selected in Theme Settings.
         */
        $fontFamily = $option(
            'font_family',
            'Poppins, Arial, sans-serif'
        );
        $headingFont = $option(
            'heading_font_family',
            '"Playfair Display", Georgia, serif'
        );
        $baseSize = $option('base_font_size', '14');
        $headingSize = $option('heading_font_size', '25');
        $fontWeight = $option('font_weight', '400');
        $headingWeight = $option('heading_font_weight', '700');
        $lineHeight = $option('line_height', '1.5');
        $letterSpacing = $option('letter_spacing', '0');
        $buttonTransform = $option('button_text_transform', 'none');
        $cardRadius = $option('card_radius', '18');
        $buttonRadius = $option('button_radius', '11');
        $sidebarWidth = $option('sidebar_width', '276');
        $navbarHeight = $option('navbar_height', '76');
        $pageSpacing = $option('page_spacing', '24');

        echo '<style id="ramkiDatabaseTheme">';

        echo ':root{color-scheme:light;';
        foreach ($colourKeys as $key) {
            $cssKey = str_replace('_', '-', $key);
            echo '--ui-' . e($cssKey) . ':'
                . e((string)$settings[$key]['light']) . ';';
        }
        echo '--ui-sidebar-bg:linear-gradient(160deg,var(--ui-sidebar-bg-1),var(--ui-sidebar-bg-2),var(--ui-sidebar-bg-3));';
        echo '--ui-brand-gradient:linear-gradient(135deg,var(--ui-brand-1),var(--ui-brand-2));';
        echo '--ui-font-family:' . e($fontFamily) . ';';
        echo '--ui-heading-font-family:' . e($headingFont) . ';';
        echo '--ui-base-font-size:' . e($baseSize) . 'px;';
        echo '--ui-heading-font-size:' . e($headingSize) . 'px;';
        echo '--ui-font-weight:' . e($fontWeight) . ';';
        echo '--ui-heading-font-weight:' . e($headingWeight) . ';';
        echo '--ui-line-height:' . e($lineHeight) . ';';
        echo '--ui-letter-spacing:' . e($letterSpacing) . 'px;';
        echo '--ui-button-text-transform:' . e($buttonTransform) . ';';
        echo '--ui-card-radius:' . e($cardRadius) . 'px;';
        echo '--ui-button-radius:' . e($buttonRadius) . 'px;';
        echo '--sidebar-width:' . e($sidebarWidth) . 'px;';
        echo '--topbar-height:' . e($navbarHeight) . 'px;';
        echo '--ui-page-spacing:' . e($pageSpacing) . 'px;';
        echo '}';

        echo 'html[data-theme="dark"]{color-scheme:dark;';
        foreach ($colourKeys as $key) {
            $cssKey = str_replace('_', '-', $key);
            echo '--ui-' . e($cssKey) . ':'
                . e((string)$settings[$key]['dark']) . ';';
        }
        echo '--ui-sidebar-bg:linear-gradient(160deg,var(--ui-sidebar-bg-1),var(--ui-sidebar-bg-2),var(--ui-sidebar-bg-3));';
        echo '--ui-brand-gradient:linear-gradient(135deg,var(--ui-brand-1),var(--ui-brand-2));';
        echo '}';

        echo 'html body,html body button,html body input,html body select,html body textarea,html body .btn,html body .form-control,html body .form-select,html body .table{font-family:var(--ui-font-family)!important;}';
        echo 'html body{font-size:var(--ui-base-font-size);font-weight:var(--ui-font-weight);line-height:var(--ui-line-height);letter-spacing:var(--ui-letter-spacing);}';
        echo 'html body h1,html body h2,html body h3,html body h4,html body h5,html body h6,html body .topbar-page-copy h1,html body .sidebar-brand-copy strong,html body .modal-title,html body .card-title,html body .theme-pro-toolbar h2,html body .theme-section-title,html body .theme-section-heading h3,html body .ramki-card-title{font-family:var(--ui-heading-font-family)!important;font-weight:var(--ui-heading-font-weight)!important;}';
        echo 'html body .topbar-page-copy h1{font-size:var(--ui-heading-font-size)!important;}';
        echo '.admin-content{padding:var(--ui-page-spacing)!important;}';
        echo '.ramki-card,.card,.modal-content{border-radius:var(--ui-card-radius)!important;}';
        echo '.btn,.form-control,.form-select,.input-group-text{border-radius:var(--ui-button-radius);}';
        echo '.btn{text-transform:var(--ui-button-text-transform);}';

        echo 'html[data-ui-sidebar-style="solid"]{--ui-sidebar-bg:var(--ui-sidebar-bg-1);}';
        echo 'html[data-ui-sidebar-style="soft"]{--ui-sidebar-bg:linear-gradient(180deg,color-mix(in srgb,var(--ui-sidebar-bg-1) 86%,var(--ui-card-bg)),color-mix(in srgb,var(--ui-sidebar-bg-2) 78%,var(--ui-card-bg)));}';

        echo 'html[data-ui-navbar-style="glass"] .admin-topbar{background:color-mix(in srgb,var(--ui-topbar-bg) 82%,transparent);backdrop-filter:blur(18px);box-shadow:0 8px 28px color-mix(in srgb,var(--ui-brand-1) 8%,transparent);}';
        echo 'html[data-ui-navbar-style="bordered"] .admin-topbar{background:var(--ui-topbar-bg);box-shadow:none;border:1px solid var(--ui-border-soft);margin:10px 12px 0;border-radius:var(--ui-card-radius);}';
        echo 'html[data-ui-navbar-style="floating"] .admin-topbar{margin:12px 16px 0;border:1px solid var(--ui-border-soft);border-radius:var(--ui-card-radius);box-shadow:0 14px 35px color-mix(in srgb,var(--ui-brand-1) 13%,transparent);}';

        echo 'html[data-ui-card-style="elevated"] .ramki-card,html[data-ui-card-style="elevated"] .card{box-shadow:0 16px 40px color-mix(in srgb,var(--ui-brand-1) 10%,transparent);}';
        echo 'html[data-ui-card-style="flat"] .ramki-card,html[data-ui-card-style="flat"] .card{box-shadow:none;border-color:transparent!important;}';
        echo 'html[data-ui-card-style="bordered"] .ramki-card,html[data-ui-card-style="bordered"] .card{box-shadow:none;border-width:1px!important;}';
        echo 'html[data-ui-card-style="soft"] .ramki-card,html[data-ui-card-style="soft"] .card{box-shadow:none;background:color-mix(in srgb,var(--ui-card-bg) 92%,var(--ui-brand-1) 8%);}';

        echo 'html[data-ui-button-style="pill"] .btn{border-radius:999px;}';
        echo 'html[data-ui-button-style="square"] .btn{border-radius:0;}';
        echo 'html[data-ui-button-style="soft"] .btn-ramki,html[data-ui-button-style="soft"] .btn-brand{color:var(--ui-brand-1);background:color-mix(in srgb,var(--ui-brand-1) 13%,var(--ui-card-bg));border:1px solid color-mix(in srgb,var(--ui-brand-1) 24%,var(--ui-border-soft));}';

        echo 'html[data-ui-table-style="bordered"] .table>:not(caption)>*>*{border-width:1px;}';
        echo 'html[data-ui-table-style="striped"] .table tbody tr:nth-of-type(odd)>*{background:color-mix(in srgb,var(--ui-table-row-hover) 70%,transparent);}';
        echo 'html[data-ui-table-density="compact"] .table>:not(caption)>*>*{padding:.42rem .55rem;}';
        echo 'html[data-ui-table-density="spacious"] .table>:not(caption)>*>*{padding:1rem .9rem;}';

        echo 'html[data-ui-layout-width="boxed"] .admin-content{max-width:1500px;margin-inline:auto;}';
        echo 'html[data-ui-content-density="compact"] .ramki-card,html[data-ui-content-density="compact"] .card-body{--bs-card-spacer-y:.75rem;--bs-card-spacer-x:.8rem;}';
        echo 'html[data-ui-content-density="spacious"] .ramki-card,html[data-ui-content-density="spacious"] .card-body{--bs-card-spacer-y:1.5rem;--bs-card-spacer-x:1.6rem;}';

        echo '</style>';
    }
}
