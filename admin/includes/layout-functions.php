<?php
declare(strict_types=1);

/**
 * Helpers for the database-driven sidebar and admin theme.
 */

function db_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name"
    );
    $stmt->execute(['table_name' => $table]);

    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function admin_theme_definition(): array
{
    return [
        'layout' => [
            'label' => 'Theme Colours',
            'icon' => 'fa-solid fa-palette',
            'settings' => [
                'body_bg' => ['label' => 'Page Background', 'type' => 'color', 'light' => '#FFF9EF', 'dark' => '#151014'],
                'topbar_bg' => ['label' => 'Navbar Background', 'type' => 'color', 'light' => '#FFFFFF', 'dark' => '#21191D'],
                'topbar_text' => ['label' => 'Navbar Text', 'type' => 'color', 'light' => '#29201D', 'dark' => '#F7EBE6'],
                'card_bg' => ['label' => 'Card Background', 'type' => 'color', 'light' => '#FFFFFF', 'dark' => '#261C21'],
                'card_header_bg' => ['label' => 'Card Header', 'type' => 'color', 'light' => '#FFF4DF', 'dark' => '#302229'],
                'border_soft' => ['label' => 'Border Colour', 'type' => 'color', 'light' => '#EAD8C0', 'dark' => '#4E3B44'],
                'text_main' => ['label' => 'Main Text', 'type' => 'color', 'light' => '#29201D', 'dark' => '#F5E9E3'],
                'text_muted' => ['label' => 'Muted Text', 'type' => 'color', 'light' => '#756B62', 'dark' => '#BBAEA7'],
            ],
        ],
        'sidebar' => [
            'label' => 'Sidebar Style',
            'icon' => 'fa-solid fa-table-columns',
            'settings' => [
                'sidebar_bg_1' => ['label' => 'Gradient Start', 'type' => 'color', 'light' => '#5D071D', 'dark' => '#22030D'],
                'sidebar_bg_2' => ['label' => 'Gradient Middle', 'type' => 'color', 'light' => '#8B1231', 'dark' => '#4B081C'],
                'sidebar_bg_3' => ['label' => 'Gradient End', 'type' => 'color', 'light' => '#A51C42', 'dark' => '#68102A'],
                'sidebar_text' => ['label' => 'Sidebar Text', 'type' => 'color', 'light' => '#FFF7EC', 'dark' => '#FFF7EC'],
                'sidebar_active_bg_1' => ['label' => 'Active Start', 'type' => 'color', 'light' => '#F3D9A0', 'dark' => '#C9963E'],
                'sidebar_active_bg_2' => ['label' => 'Active End', 'type' => 'color', 'light' => '#C9963E', 'dark' => '#A06F22'],
                'sidebar_active_text' => ['label' => 'Active Text', 'type' => 'color', 'light' => '#5D071D', 'dark' => '#1D0B11'],
                'sidebar_hover_bg' => ['label' => 'Hover Background', 'type' => 'color', 'light' => 'rgba(255,255,255,.10)', 'dark' => 'rgba(255,255,255,.10)'],
                'sidebar_hover_text' => ['label' => 'Hover Text', 'type' => 'color', 'light' => '#FFFFFF', 'dark' => '#FFFFFF'],
                'sidebar_submenu_bg' => ['label' => 'Submenu Background', 'type' => 'color', 'light' => 'rgba(255,255,255,.06)', 'dark' => 'rgba(255,255,255,.06)'],
            ],
        ],
        'brand' => [
            'label' => 'Brand Colours',
            'icon' => 'fa-solid fa-gem',
            'settings' => [
                'brand_1' => ['label' => 'Primary Brand', 'type' => 'color', 'light' => '#8B1231', 'dark' => '#C9963E'],
                'brand_2' => ['label' => 'Secondary Brand', 'type' => 'color', 'light' => '#C9963E', 'dark' => '#F3D9A0'],
                'brand_text' => ['label' => 'Button Text', 'type' => 'color', 'light' => '#FFFFFF', 'dark' => '#1D0B11'],
            ],
        ],
        'tables' => [
            'label' => 'Table Colours',
            'icon' => 'fa-solid fa-table',
            'settings' => [
                'table_header_bg' => ['label' => 'Header Background', 'type' => 'color', 'light' => '#FFF4DF', 'dark' => '#33232A'],
                'table_header_text' => ['label' => 'Header Text', 'type' => 'color', 'light' => '#5D071D', 'dark' => '#F3D9A0'],
                'table_row_hover' => ['label' => 'Row Hover', 'type' => 'color', 'light' => '#FFF9EF', 'dark' => '#2D2026'],
            ],
        ],
        'forms' => [
            'label' => 'Form & Input Colours',
            'icon' => 'fa-solid fa-pen-to-square',
            'settings' => [
                'input_bg' => ['label' => 'Input Background', 'type' => 'color', 'light' => '#FFFFFF', 'dark' => '#22191E'],
                'input_border' => ['label' => 'Input Border', 'type' => 'color', 'light' => '#DCC7AA', 'dark' => '#58414C'],
                'input_text' => ['label' => 'Input Text', 'type' => 'color', 'light' => '#29201D', 'dark' => '#F5E9E3'],
            ],
        ],
        'status' => [
            'label' => 'Status Colours',
            'icon' => 'fa-solid fa-circle-check',
            'settings' => [
                'success_color' => ['label' => 'Success', 'type' => 'color', 'light' => '#198754', 'dark' => '#3BCF8E'],
                'warning_color' => ['label' => 'Warning', 'type' => 'color', 'light' => '#D89A16', 'dark' => '#F0B94D'],
                'danger_color' => ['label' => 'Danger', 'type' => 'color', 'light' => '#DC3545', 'dark' => '#FF6875'],
                'info_color' => ['label' => 'Information', 'type' => 'color', 'light' => '#0D6EFD', 'dark' => '#6AA5FF'],
            ],
        ],
        'typography' => [
            'label' => 'Typography & Text',
            'icon' => 'fa-solid fa-font',
            'settings' => [
                'font_family' => [
                    'label' => 'Application Font', 'type' => 'select',
                    'light' => 'Poppins, Arial, sans-serif', 'dark' => 'Poppins, Arial, sans-serif',
                    'options' => [
                        'Poppins, Arial, sans-serif' => 'Poppins — Website Style',
                        'Inter, Arial, sans-serif' => 'Inter',
                        'Roboto, Arial, sans-serif' => 'Roboto',
                        '"Open Sans", Arial, sans-serif' => 'Open Sans',
                        'Nunito, Arial, sans-serif' => 'Nunito',
                        'Montserrat, Arial, sans-serif' => 'Montserrat',
                        'Lato, Arial, sans-serif' => 'Lato',
                    ],
                ],
                'heading_font_family' => [
                    'label' => 'Heading Font', 'type' => 'select',
                    'light' => '"Playfair Display", Georgia, serif', 'dark' => '"Playfair Display", Georgia, serif',
                    'options' => [
                        '"Playfair Display", Georgia, serif' => 'Playfair Display — Website Style',
                        '"DM Serif Display", Georgia, serif' => 'DM Serif Display',
                        '"Libre Baskerville", Georgia, serif' => 'Libre Baskerville',
                        'Poppins, Arial, sans-serif' => 'Poppins',
                        'Montserrat, Arial, sans-serif' => 'Montserrat',
                        'Inter, Arial, sans-serif' => 'Inter',
                        'Roboto, Arial, sans-serif' => 'Roboto',
                        'Nunito, Arial, sans-serif' => 'Nunito',
                    ],
                ],
                'base_font_size' => ['label' => 'Base Font Size', 'type' => 'number', 'light' => '14', 'dark' => '14', 'min' => 10, 'max' => 24, 'step' => 1, 'unit' => 'px'],
                'heading_font_size' => ['label' => 'Page Heading Size', 'type' => 'number', 'light' => '25', 'dark' => '25', 'min' => 18, 'max' => 42, 'step' => 1, 'unit' => 'px'],
                'font_weight' => ['label' => 'Default Font Weight', 'type' => 'select', 'light' => '400', 'dark' => '400', 'options' => ['400' => 'Regular', '500' => 'Medium', '600' => 'Semi Bold']],
                'heading_font_weight' => ['label' => 'Heading Weight', 'type' => 'select', 'light' => '700', 'dark' => '700', 'options' => ['600' => 'Semi Bold', '700' => 'Bold', '800' => 'Extra Bold', '900' => 'Black']],
                'line_height' => ['label' => 'Line Height', 'type' => 'number', 'light' => '1.5', 'dark' => '1.5', 'min' => 1, 'max' => 2.5, 'step' => 0.05],
                'letter_spacing' => ['label' => 'Letter Spacing', 'type' => 'number', 'light' => '0', 'dark' => '0', 'min' => -2, 'max' => 5, 'step' => 0.1, 'unit' => 'px'],
                'button_text_transform' => ['label' => 'Button Text', 'type' => 'select', 'light' => 'none', 'dark' => 'none', 'options' => ['none' => 'Normal', 'uppercase' => 'UPPERCASE', 'capitalize' => 'Capitalize']],
            ],
        ],
        'components' => [
            'label' => 'Component Styles',
            'icon' => 'fa-solid fa-shapes',
            'settings' => [
                'sidebar_style' => ['label' => 'Sidebar Style', 'type' => 'select', 'light' => 'gradient', 'dark' => 'gradient', 'options' => ['gradient' => 'Gradient', 'solid' => 'Solid', 'soft' => 'Soft']],
                'navbar_style' => ['label' => 'Navbar Style', 'type' => 'select', 'light' => 'solid', 'dark' => 'solid', 'options' => ['solid' => 'Solid', 'glass' => 'Glass', 'bordered' => 'Bordered', 'floating' => 'Floating']],
                'card_style' => ['label' => 'Card Style', 'type' => 'select', 'light' => 'elevated', 'dark' => 'elevated', 'options' => ['elevated' => 'Elevated', 'flat' => 'Flat', 'bordered' => 'Bordered', 'soft' => 'Soft']],
                'button_style' => ['label' => 'Button Style', 'type' => 'select', 'light' => 'rounded', 'dark' => 'rounded', 'options' => ['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square', 'soft' => 'Soft']],
                'table_style' => ['label' => 'Table Style', 'type' => 'select', 'light' => 'clean', 'dark' => 'clean', 'options' => ['clean' => 'Clean', 'striped' => 'Striped', 'bordered' => 'Bordered']],
                'table_density' => ['label' => 'Table Density', 'type' => 'select', 'light' => 'comfortable', 'dark' => 'comfortable', 'options' => ['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious']],
                'card_radius' => ['label' => 'Card Radius', 'type' => 'number', 'light' => '18', 'dark' => '18', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px'],
                'button_radius' => ['label' => 'Button Radius', 'type' => 'number', 'light' => '11', 'dark' => '11', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px'],
            ],
        ],
        'options' => [
            'label' => 'Layout Settings',
            'icon' => 'fa-solid fa-sliders',
            'settings' => [
                'sidebar_width' => ['label' => 'Sidebar Width', 'type' => 'number', 'light' => '276', 'dark' => '276', 'min' => 220, 'max' => 340, 'step' => 1, 'unit' => 'px'],
                'navbar_height' => ['label' => 'Navbar Height', 'type' => 'number', 'light' => '76', 'dark' => '76', 'min' => 56, 'max' => 92, 'step' => 1, 'unit' => 'px'],
                'page_spacing' => ['label' => 'Page Spacing', 'type' => 'number', 'light' => '24', 'dark' => '24', 'min' => 8, 'max' => 40, 'step' => 1, 'unit' => 'px'],
                'layout_width' => ['label' => 'Layout Width', 'type' => 'select', 'light' => 'fluid', 'dark' => 'fluid', 'options' => ['fluid' => 'Fluid', 'boxed' => 'Boxed']],
                'layout_density' => ['label' => 'Layout Density', 'type' => 'select', 'light' => 'comfortable', 'dark' => 'comfortable', 'options' => ['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious']],
                'content_density' => ['label' => 'Content Density', 'type' => 'select', 'light' => 'comfortable', 'dark' => 'comfortable', 'options' => ['compact' => 'Compact', 'comfortable' => 'Comfortable', 'spacious' => 'Spacious']],
            ],
        ],
    ];
}

function admin_theme_build_preset(
    string $label,
    string $description,
    array $light,
    array $dark,
    array $options = []
): array {
    $settings = [];

    foreach ($light as $key => $value) {
        $settings[$key] = [
            'light' => (string)$value,
            'dark' => (string)($dark[$key] ?? $value),
        ];
    }

    foreach ($options as $key => $value) {
        $settings[$key] = [
            'light' => (string)$value,
            'dark' => (string)$value,
        ];
    }

    return [
        'label' => $label,
        'description' => $description,
        'settings' => $settings,
    ];
}

function admin_theme_presets(): array
{
    $commonStatusLight = [
        'success_color' => '#16A34A',
        'warning_color' => '#D97706',
        'danger_color' => '#DC2626',
        'info_color' => '#0284C7',
    ];

    $commonStatusDark = [
        'success_color' => '#3BCF8E',
        'warning_color' => '#F0B94D',
        'danger_color' => '#FF6875',
        'info_color' => '#6AA5FF',
    ];

    return [
        'ramki_heritage' => admin_theme_build_preset(
            'Ramki Heritage',
            'Maroon, gold and cream invitation-card identity.',
            array_merge([
                'body_bg' => '#FFF9EF', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#29201D',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#FFF4DF', 'border_soft' => '#EAD8C0',
                'text_main' => '#29201D', 'text_muted' => '#756B62',
                'sidebar_bg_1' => '#5D071D', 'sidebar_bg_2' => '#8B1231', 'sidebar_bg_3' => '#A51C42',
                'sidebar_text' => '#FFF7EC', 'sidebar_active_bg_1' => '#F3D9A0', 'sidebar_active_bg_2' => '#C9963E',
                'sidebar_active_text' => '#5D071D', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#8B1231', 'brand_2' => '#C9963E', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#FFF4DF', 'table_header_text' => '#5D071D', 'table_row_hover' => '#FFF9EF',
                'input_bg' => '#FFFFFF', 'input_border' => '#DCC7AA', 'input_text' => '#29201D',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#151014', 'topbar_bg' => '#21191D', 'topbar_text' => '#F7EBE6',
                'card_bg' => '#261C21', 'card_header_bg' => '#302229', 'border_soft' => '#4E3B44',
                'text_main' => '#F5E9E3', 'text_muted' => '#BBAEA7',
                'sidebar_bg_1' => '#22030D', 'sidebar_bg_2' => '#4B081C', 'sidebar_bg_3' => '#68102A',
                'sidebar_text' => '#FFF7EC', 'sidebar_active_bg_1' => '#C9963E', 'sidebar_active_bg_2' => '#A06F22',
                'sidebar_active_text' => '#1D0B11', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#C9963E', 'brand_2' => '#F3D9A0', 'brand_text' => '#1D0B11',
                'table_header_bg' => '#33232A', 'table_header_text' => '#F3D9A0', 'table_row_hover' => '#2D2026',
                'input_bg' => '#22191E', 'input_border' => '#58414C', 'input_text' => '#F5E9E3',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif',
                'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '25', 'font_weight' => '400',
                'heading_font_weight' => '700', 'line_height' => '1.5', 'letter_spacing' => '0',
                'button_text_transform' => 'none', 'sidebar_style' => 'gradient', 'navbar_style' => 'solid',
                'card_style' => 'elevated', 'button_style' => 'rounded', 'table_style' => 'clean',
                'table_density' => 'comfortable', 'card_radius' => '18', 'button_radius' => '11',
                'sidebar_width' => '276', 'navbar_height' => '76', 'page_spacing' => '24',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
        'classic_blue' => admin_theme_build_preset(
            'Classic Blue',
            'Clean professional administration workspace.',
            array_merge([
                'body_bg' => '#F4F7FC', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#0F172A',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#F8FAFC', 'border_soft' => '#DCE5F1',
                'text_main' => '#0F172A', 'text_muted' => '#64748B',
                'sidebar_bg_1' => '#0F172A', 'sidebar_bg_2' => '#1E3A8A', 'sidebar_bg_3' => '#312E81',
                'sidebar_text' => '#E2E8F0', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#7C3AED',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#2563EB', 'brand_2' => '#7C3AED', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#EFF6FF', 'table_header_text' => '#1E3A8A', 'table_row_hover' => '#F8FAFF',
                'input_bg' => '#FFFFFF', 'input_border' => '#CBD5E1', 'input_text' => '#0F172A',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#020617', 'topbar_bg' => '#0F172A', 'topbar_text' => '#F8FAFC',
                'card_bg' => '#111827', 'card_header_bg' => '#1E293B', 'border_soft' => '#334155',
                'text_main' => '#F8FAFC', 'text_muted' => '#94A3B8',
                'sidebar_bg_1' => '#020617', 'sidebar_bg_2' => '#0F172A', 'sidebar_bg_3' => '#111827',
                'sidebar_text' => '#CBD5E1', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#06B6D4',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#60A5FA', 'brand_2' => '#22D3EE', 'brand_text' => '#07111F',
                'table_header_bg' => '#1E293B', 'table_header_text' => '#F8FAFC', 'table_row_hover' => '#1E293B',
                'input_bg' => '#0F172A', 'input_border' => '#334155', 'input_text' => '#F8FAFC',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif', 'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500',
                'heading_font_weight' => '800', 'line_height' => '1.5', 'letter_spacing' => '0',
                'button_text_transform' => 'none', 'sidebar_style' => 'gradient', 'navbar_style' => 'solid',
                'card_style' => 'elevated', 'button_style' => 'rounded', 'table_style' => 'clean',
                'table_density' => 'comfortable', 'card_radius' => '18', 'button_radius' => '12',
                'sidebar_width' => '268', 'navbar_height' => '64', 'page_spacing' => '16',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
        'emerald_business' => admin_theme_build_preset(
            'Emerald Business',
            'Fresh green retail and finance appearance.',
            array_merge([
                'body_bg' => '#F0FDF4', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#052E16',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#ECFDF5', 'border_soft' => '#BBF7D0',
                'text_main' => '#14532D', 'text_muted' => '#4B7A5D',
                'sidebar_bg_1' => '#052E16', 'sidebar_bg_2' => '#14532D', 'sidebar_bg_3' => '#166534',
                'sidebar_text' => '#DCFCE7', 'sidebar_active_bg_1' => '#16A34A', 'sidebar_active_bg_2' => '#059669',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#16A34A', 'brand_2' => '#059669', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#DCFCE7', 'table_header_text' => '#14532D', 'table_row_hover' => '#F0FDF4',
                'input_bg' => '#FFFFFF', 'input_border' => '#86EFAC', 'input_text' => '#14532D',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#031A10', 'topbar_bg' => '#052E16', 'topbar_text' => '#ECFDF5',
                'card_bg' => '#0B3B24', 'card_header_bg' => '#14532D', 'border_soft' => '#166534',
                'text_main' => '#ECFDF5', 'text_muted' => '#A7F3D0',
                'sidebar_bg_1' => '#02150C', 'sidebar_bg_2' => '#052E16', 'sidebar_bg_3' => '#14532D',
                'sidebar_text' => '#DCFCE7', 'sidebar_active_bg_1' => '#22C55E', 'sidebar_active_bg_2' => '#10B981',
                'sidebar_active_text' => '#052E16', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#4ADE80', 'brand_2' => '#34D399', 'brand_text' => '#052E16',
                'table_header_bg' => '#14532D', 'table_header_text' => '#DCFCE7', 'table_row_hover' => '#12472B',
                'input_bg' => '#052E16', 'input_border' => '#166534', 'input_text' => '#ECFDF5',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif', 'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500',
                'heading_font_weight' => '800', 'line_height' => '1.55', 'letter_spacing' => '0',
                'button_text_transform' => 'none', 'sidebar_style' => 'gradient', 'navbar_style' => 'glass',
                'card_style' => 'soft', 'button_style' => 'rounded', 'table_style' => 'clean',
                'table_density' => 'comfortable', 'card_radius' => '20', 'button_radius' => '12',
                'sidebar_width' => '268', 'navbar_height' => '64', 'page_spacing' => '16',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
        'royal_purple' => admin_theme_build_preset(
            'Royal Purple',
            'Premium modern invitation and POS appearance.',
            array_merge([
                'body_bg' => '#F5F3FF', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#2E1065',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#F5F3FF', 'border_soft' => '#DDD6FE',
                'text_main' => '#2E1065', 'text_muted' => '#6D5A8A',
                'sidebar_bg_1' => '#2E1065', 'sidebar_bg_2' => '#4C1D95', 'sidebar_bg_3' => '#581C87',
                'sidebar_text' => '#EDE9FE', 'sidebar_active_bg_1' => '#7C3AED', 'sidebar_active_bg_2' => '#C026D3',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#7C3AED', 'brand_2' => '#C026D3', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#EDE9FE', 'table_header_text' => '#3B0764', 'table_row_hover' => '#FAF5FF',
                'input_bg' => '#FFFFFF', 'input_border' => '#C4B5FD', 'input_text' => '#2E1065',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#120523', 'topbar_bg' => '#1E0A3D', 'topbar_text' => '#FAF5FF',
                'card_bg' => '#261047', 'card_header_bg' => '#35125B', 'border_soft' => '#5B2184',
                'text_main' => '#FAF5FF', 'text_muted' => '#D8B4FE',
                'sidebar_bg_1' => '#17062F', 'sidebar_bg_2' => '#2E1065', 'sidebar_bg_3' => '#4C1D95',
                'sidebar_text' => '#EDE9FE', 'sidebar_active_bg_1' => '#8B5CF6', 'sidebar_active_bg_2' => '#D946EF',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#A78BFA', 'brand_2' => '#E879F9', 'brand_text' => '#23083B',
                'table_header_bg' => '#35125B', 'table_header_text' => '#F5D0FE', 'table_row_hover' => '#321353',
                'input_bg' => '#1E0A3D', 'input_border' => '#6B21A8', 'input_text' => '#FAF5FF',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif', 'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '25', 'font_weight' => '600',
                'heading_font_weight' => '900', 'line_height' => '1.5', 'letter_spacing' => '0.1',
                'button_text_transform' => 'none', 'sidebar_style' => 'gradient', 'navbar_style' => 'floating',
                'card_style' => 'elevated', 'button_style' => 'pill', 'table_style' => 'striped',
                'table_density' => 'comfortable', 'card_radius' => '22', 'button_radius' => '24',
                'sidebar_width' => '272', 'navbar_height' => '68', 'page_spacing' => '18',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
        'sunset_orange' => admin_theme_build_preset(
            'Sunset Orange',
            'Warm retail and order-management theme.',
            array_merge([
                'body_bg' => '#FFF7ED', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#431407',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#FFF7ED', 'border_soft' => '#FED7AA',
                'text_main' => '#431407', 'text_muted' => '#8A5A44',
                'sidebar_bg_1' => '#431407', 'sidebar_bg_2' => '#7C2D12', 'sidebar_bg_3' => '#9A3412',
                'sidebar_text' => '#FFEDD5', 'sidebar_active_bg_1' => '#EA580C', 'sidebar_active_bg_2' => '#F59E0B',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#EA580C', 'brand_2' => '#F59E0B', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#FFEDD5', 'table_header_text' => '#7C2D12', 'table_row_hover' => '#FFF7ED',
                'input_bg' => '#FFFFFF', 'input_border' => '#FDBA74', 'input_text' => '#431407',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#1C0B05', 'topbar_bg' => '#2A1007', 'topbar_text' => '#FFF7ED',
                'card_bg' => '#38160A', 'card_header_bg' => '#4A1D0C', 'border_soft' => '#7C2D12',
                'text_main' => '#FFF7ED', 'text_muted' => '#FDBA74',
                'sidebar_bg_1' => '#1A0904', 'sidebar_bg_2' => '#431407', 'sidebar_bg_3' => '#7C2D12',
                'sidebar_text' => '#FFEDD5', 'sidebar_active_bg_1' => '#F97316', 'sidebar_active_bg_2' => '#FBBF24',
                'sidebar_active_text' => '#431407', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#FB923C', 'brand_2' => '#FBBF24', 'brand_text' => '#431407',
                'table_header_bg' => '#4A1D0C', 'table_header_text' => '#FFEDD5', 'table_row_hover' => '#4A1D0C',
                'input_bg' => '#2A1007', 'input_border' => '#9A3412', 'input_text' => '#FFF7ED',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif', 'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500',
                'heading_font_weight' => '800', 'line_height' => '1.5', 'letter_spacing' => '0',
                'button_text_transform' => 'capitalize', 'sidebar_style' => 'solid', 'navbar_style' => 'bordered',
                'card_style' => 'bordered', 'button_style' => 'rounded', 'table_style' => 'bordered',
                'table_density' => 'comfortable', 'card_radius' => '16', 'button_radius' => '10',
                'sidebar_width' => '264', 'navbar_height' => '64', 'page_spacing' => '16',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
        'midnight_dark' => admin_theme_build_preset(
            'Midnight Dark',
            'Elegant dark workspace with blue and cyan accents.',
            array_merge([
                'body_bg' => '#EAF2FF', 'topbar_bg' => '#F8FBFF', 'topbar_text' => '#102A43',
                'card_bg' => '#FFFFFF', 'card_header_bg' => '#EEF5FF', 'border_soft' => '#C9DAF2',
                'text_main' => '#102A43', 'text_muted' => '#627D98',
                'sidebar_bg_1' => '#102A43', 'sidebar_bg_2' => '#243B53', 'sidebar_bg_3' => '#334E68',
                'sidebar_text' => '#D9EAF7', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#06B6D4',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#2563EB', 'brand_2' => '#06B6D4', 'brand_text' => '#FFFFFF',
                'table_header_bg' => '#E6F0FF', 'table_header_text' => '#102A43', 'table_row_hover' => '#F4F8FF',
                'input_bg' => '#FFFFFF', 'input_border' => '#B8CCE5', 'input_text' => '#102A43',
            ], $commonStatusLight),
            array_merge([
                'body_bg' => '#020617', 'topbar_bg' => '#0F172A', 'topbar_text' => '#F8FAFC',
                'card_bg' => '#111827', 'card_header_bg' => '#1E293B', 'border_soft' => '#334155',
                'text_main' => '#F8FAFC', 'text_muted' => '#94A3B8',
                'sidebar_bg_1' => '#020617', 'sidebar_bg_2' => '#0F172A', 'sidebar_bg_3' => '#111827',
                'sidebar_text' => '#CBD5E1', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#06B6D4',
                'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
                'sidebar_hover_text' => '#FFFFFF', 'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
                'brand_1' => '#60A5FA', 'brand_2' => '#22D3EE', 'brand_text' => '#07111F',
                'table_header_bg' => '#1E293B', 'table_header_text' => '#F8FAFC', 'table_row_hover' => '#1E293B',
                'input_bg' => '#0F172A', 'input_border' => '#334155', 'input_text' => '#F8FAFC',
            ], $commonStatusDark),
            [
                'font_family' => 'Poppins, Arial, sans-serif', 'heading_font_family' => '"Playfair Display", Georgia, serif',
                'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '400',
                'heading_font_weight' => '700', 'line_height' => '1.55', 'letter_spacing' => '0',
                'button_text_transform' => 'none', 'sidebar_style' => 'gradient', 'navbar_style' => 'glass',
                'card_style' => 'flat', 'button_style' => 'rounded', 'table_style' => 'clean',
                'table_density' => 'comfortable', 'card_radius' => '18', 'button_radius' => '12',
                'sidebar_width' => '268', 'navbar_height' => '64', 'page_spacing' => '16',
                'layout_width' => 'fluid', 'layout_density' => 'comfortable', 'content_density' => 'comfortable',
            ]
        ),
    ];
}

function flatten_admin_theme_definition(): array
{
    $flat = [];
    $sortOrder = 0;

    foreach (admin_theme_definition() as $groupKey => $group) {
        foreach ($group['settings'] as $key => $setting) {
            $sortOrder++;
            $setting['group'] = $groupKey;
            $setting['sort_order'] = $sortOrder;
            $flat[$key] = $setting;
        }
    }

    return $flat;
}

function is_valid_theme_colour(string $value): bool
{
    $value = trim($value);

    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
        return true;
    }

    return (bool)preg_match(
        '/^rgba?\(\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1?\d?\d)(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i',
        $value
    );
}

function is_valid_admin_theme_font(string $value): bool
{
    return $value !== ''
        && mb_strlen($value) <= 200
        && !preg_match('/[;{}<>]|url\s*\(|expression\s*\(|@import/i', $value);
}

function normalize_admin_theme_setting_value(array $definition, mixed $value): string
{
    $type = (string)($definition['type'] ?? 'string');
    $value = trim((string)$value);

    if ($type === 'color') {
        if (!is_valid_theme_colour($value)) {
            throw new RuntimeException('Invalid colour value.');
        }

        return str_starts_with($value, '#')
            ? strtoupper($value)
            : $value;
    }

    if ($type === 'number') {
        /*
         * Accept clean numeric values and legacy values such as:
         * 0px, 1.5 px, 14rem.
         */
        $numberValue = str_replace(
            ',',
            '.',
            trim($value)
        );

        $numberValue = preg_replace(
            '/\\s*(?:px|rem|em|%)\\s*$/i',
            '',
            $numberValue
        ) ?? '';

        if ($numberValue === '') {
            $numberValue = (string)(
                $definition['light']
                ?? $definition['dark']
                ?? '0'
            );

            $numberValue = preg_replace(
                '/\\s*(?:px|rem|em|%)\\s*$/i',
                '',
                str_replace(
                    ',',
                    '.',
                    trim($numberValue)
                )
            ) ?? '0';
        }

        if (!is_numeric($numberValue)) {
            throw new RuntimeException(
                'Enter a valid numeric value.'
            );
        }

        $number = (float)$numberValue;

        $minimum = isset($definition['min'])
            ? (float)$definition['min']
            : null;

        $maximum = isset($definition['max'])
            ? (float)$definition['max']
            : null;

        if (
            $minimum !== null
            && $number < $minimum
        ) {
            throw new RuntimeException(
                'The value is below the permitted minimum.'
            );
        }

        if (
            $maximum !== null
            && $number > $maximum
        ) {
            throw new RuntimeException(
                'The value exceeds the permitted maximum.'
            );
        }

        return rtrim(
            rtrim(
                number_format(
                    $number,
                    4,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }

    if ($type === 'select') {
        $options = array_keys((array)($definition['options'] ?? []));

        if (!in_array($value, $options, true)) {
            throw new RuntimeException('Select a valid option.');
        }

        return $value;
    }

    if (($definition['key'] ?? '') === 'font_family'
        || ($definition['key'] ?? '') === 'heading_font_family'
        || str_contains((string)($definition['label'] ?? ''), 'Font')) {
        if (!is_valid_admin_theme_font($value)) {
            throw new RuntimeException('Invalid font value.');
        }
    }

    if ($value === '' || mb_strlen($value) > 255) {
        throw new RuntimeException('Enter a valid setting value.');
    }

    return $value;
}

function load_admin_theme_settings(PDO $pdo): array
{
    $definitions = flatten_admin_theme_definition();
    $settings = [];

    foreach ($definitions as $key => $definition) {
        $definition['key'] = $key;
        $settings[$key] = [
            'light' => (string)$definition['light'],
            'dark' => (string)$definition['dark'],
            'type' => (string)$definition['type'],
            'label' => (string)$definition['label'],
            'group' => (string)$definition['group'],
        ];
    }

    if (!db_table_exists($pdo, 'admin_theme_settings')) {
        return $settings;
    }

    $rows = $pdo->query(
        "SELECT setting_key, light_value, dark_value
         FROM admin_theme_settings
         WHERE is_active = 1"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $key = (string)$row['setting_key'];

        if (!isset($definitions[$key], $settings[$key])) {
            continue;
        }

        $definition = $definitions[$key];
        $definition['key'] = $key;

        try {
            $settings[$key]['light'] = normalize_admin_theme_setting_value(
                $definition,
                $row['light_value']
            );
            $settings[$key]['dark'] = normalize_admin_theme_setting_value(
                $definition,
                $row['dark_value']
            );
        } catch (Throwable $ignored) {
            // Invalid legacy values fall back to the safe definition defaults.
        }
    }

    return $settings;
}

function load_admin_user_preferences(PDO $pdo, ?int $adminId): array
{
    $preferences = [
        'theme_mode' => 'light',
        'sidebar_collapsed' => 0,
    ];

    if (!$adminId || !db_table_exists($pdo, 'admin_user_preferences')) {
        return $preferences;
    }

    $stmt = $pdo->prepare(
        "SELECT theme_mode, sidebar_collapsed
         FROM admin_user_preferences
         WHERE admin_user_id = :admin_user_id
         LIMIT 1"
    );
    $stmt->execute(['admin_user_id' => $adminId]);
    $row = $stmt->fetch();

    if (!$row) {
        return $preferences;
    }

    if (in_array($row['theme_mode'], ['light', 'dark'], true)) {
        $preferences['theme_mode'] = $row['theme_mode'];
    }
    $preferences['sidebar_collapsed'] = (int)$row['sidebar_collapsed'] === 1 ? 1 : 0;

    return $preferences;
}

function current_admin_role_name(PDO $pdo): string
{
    $admin = current_admin();
    if (!$admin) {
        return 'Administrator';
    }

    if (!empty($admin['role_name'])) {
        return (string)$admin['role_name'];
    }

    $stmt = $pdo->prepare(
        "SELECT ar.role_name
         FROM admin_users au
         INNER JOIN admin_roles ar ON ar.id = au.role_id
         WHERE au.id = :admin_user_id
         LIMIT 1"
    );
    $stmt->execute(['admin_user_id' => (int)$admin['id']]);

    return (string)($stmt->fetchColumn() ?: 'Administrator');
}

function admin_menu_route_basename(?string $route): string
{
    $path = parse_url((string)$route, PHP_URL_PATH);
    return basename((string)$path);
}

function admin_menu_route_is_safe(string $route): bool
{
    $route = trim($route);

    if ($route === '' || $route === '#') {
        return true;
    }

    if (str_contains($route, '..')) {
        return false;
    }

    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/)#i', $route)) {
        return false;
    }

    return (bool)preg_match('/^[a-zA-Z0-9_\-\.\/\?=&%#]+$/', $route);
}

function load_admin_sidebar_tree(PDO $pdo): array
{
    if (!db_table_exists($pdo, 'admin_menus')) {
        return [];
    }

    $admin = current_admin();
    if (!$admin) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            m.id,
            m.parent_id,
            m.menu_name,
            m.menu_key,
            m.route_name,
            m.icon_class,
            m.sort_order,
            COALESCE(rmp.can_view, 0) AS can_view
         FROM admin_menus m
         LEFT JOIN role_menu_permissions rmp
            ON rmp.menu_id = m.id
           AND rmp.role_id = :role_id
         WHERE m.status = 'active'
           AND m.is_visible = 1
         ORDER BY
            CASE WHEN m.parent_id IS NULL THEN m.sort_order ELSE 999999 END,
            COALESCE(m.parent_id, m.id),
            m.parent_id IS NOT NULL,
            m.sort_order,
            m.id"
    );
    $stmt->execute(['role_id' => (int)$admin['role_id']]);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return [];
    }

    $rowsById = [];
    foreach ($rows as $row) {
        $rowsById[(int)$row['id']] = $row;
    }

    $allowed = [];
    if (is_super_admin($pdo)) {
        $allowed = array_fill_keys(array_keys($rowsById), true);
    } else {
        foreach ($rowsById as $id => $row) {
            if ((int)$row['can_view'] === 1) {
                $allowed[$id] = true;
            }
        }

        foreach (array_keys($allowed) as $id) {
            $cursor = $id;
            $guard = 0;
            while ($cursor > 0 && isset($rowsById[$cursor]) && $guard++ < 50) {
                $parentId = (int)($rowsById[$cursor]['parent_id'] ?? 0);
                if ($parentId <= 0 || !isset($rowsById[$parentId])) {
                    break;
                }
                $allowed[$parentId] = true;
                $cursor = $parentId;
            }
        }
    }

    $children = [];
    foreach ($rowsById as $id => $row) {
        if (!isset($allowed[$id])) {
            continue;
        }

        $parentId = (int)($row['parent_id'] ?? 0);
        if ($parentId > 0 && !isset($allowed[$parentId])) {
            continue;
        }

        $children[$parentId][] = $row;
    }

    $build = function (int $parentId, array $trail = []) use (&$build, $children): array {
        $items = [];
        foreach ($children[$parentId] ?? [] as $row) {
            $id = (int)$row['id'];
            if (isset($trail[$id])) {
                continue;
            }
            $nextTrail = $trail;
            $nextTrail[$id] = true;
            $row['children'] = $build($id, $nextTrail);
            $items[] = $row;
        }
        return $items;
    };

    return $build(0);
}

function admin_menu_icon_class(?string $iconClass): string
{
    $iconClass = trim((string)$iconClass);

    if ($iconClass === '' || !preg_match('/^[a-zA-Z0-9 _-]+$/', $iconClass)) {
        return 'fa-solid fa-circle';
    }

    if (!str_contains($iconClass, 'fa-')) {
        return 'fa-solid fa-circle';
    }

    if (!str_contains($iconClass, 'fa-solid') &&
        !str_contains($iconClass, 'fa-regular') &&
        !str_contains($iconClass, 'fa-brands')) {
        $iconClass = 'fa-solid ' . $iconClass;
    }

    return $iconClass;
}
