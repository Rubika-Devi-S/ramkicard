<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'theme_settings', 'can_view')) {
    http_response_code(403);
    exit('Permission denied.');
}

$themeCanEdit = can_menu(
    $pdo,
    'theme_settings',
    'can_edit'
);

if (function_exists('is_super_admin') && is_super_admin($pdo)) {
    $themeCanEdit = true;
}

$pageTitle = 'Theme Settings';
$pageScript = 'theme-settings.js';
$pageStyles = ['theme-settings.css'];

$themeDefinition = admin_theme_definition();
$themePresets = function_exists('admin_theme_presets')
    ? admin_theme_presets()
    : [];

require __DIR__ . '/includes/header.php';

$colourGroups = [
    'layout',
    'sidebar',
    'brand',
    'tables',
    'forms',
    'status',
];

$renderThemeControl = static function (
    string $key,
    array $definition,
    array $saved,
    bool $canEdit
): void {
    $type = (string)($definition['type'] ?? 'string');

    ?>

    <div class="theme-control-card">
        <label class="theme-control-label">
            <?= e((string)$definition['label']); ?>
        </label>

        <?php if ($type === 'color'): ?>
            <div class="theme-mode-pair">
                <?php foreach (
                    ['light' => 'Light', 'dark' => 'Dark']
                    as $mode => $modeLabel
                ): ?>
                    <?php
                    $value = (string)($saved[$mode] ?? '');
                    $pickerValue = preg_match(
                        '/^#[0-9A-Fa-f]{6}$/',
                        $value
                    ) ? $value : '#000000';
                    ?>

                    <div>
                        <small><?= e($modeLabel); ?></small>

                        <div class="theme-colour-input">
                            <input
                                type="color"
                                class="js-theme-picker"
                                data-theme-key="<?= e($key); ?>"
                                data-mode="<?= e($mode); ?>"
                                value="<?= e($pickerValue); ?>"
                                <?= $canEdit ? '' : 'disabled'; ?>
                            >

                            <input
                                type="text"
                                class="form-control form-control-sm js-theme-colour"
                                data-theme-key="<?= e($key); ?>"
                                data-mode="<?= e($mode); ?>"
                                value="<?= e($value); ?>"
                                maxlength="40"
                                <?= $canEdit ? '' : 'readonly'; ?>
                            >
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($type === 'select'): ?>
            <select
                class="form-select js-theme-option"
                data-theme-key="<?= e($key); ?>"
                data-theme-type="select"
                <?= $canEdit ? '' : 'disabled'; ?>
            >
                <?php foreach (
                    (array)($definition['options'] ?? [])
                    as $value => $label
                ): ?>
                    <option
                        value="<?= e((string)$value); ?>"
                        <?= (
                            (string)($saved['light'] ?? '')
                            === (string)$value
                        ) ? 'selected' : ''; ?>
                    >
                        <?= e((string)$label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

        <?php elseif ($type === 'number'): ?>
            <div class="input-group">
                <input
                    type="number"
                    class="form-control js-theme-option"
                    data-theme-key="<?= e($key); ?>"
                    data-theme-type="number"
                    value="<?= e((string)($saved['light'] ?? '')); ?>"
                    min="<?= e((string)($definition['min'] ?? 0)); ?>"
                    max="<?= e((string)($definition['max'] ?? 999)); ?>"
                    step="<?= e((string)($definition['step'] ?? 1)); ?>"
                    <?= $canEdit ? '' : 'readonly'; ?>
                >

                <?php if (!empty($definition['unit'])): ?>
                    <span class="input-group-text">
                        <?= e((string)$definition['unit']); ?>
                    </span>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <input
                type="text"
                class="form-control js-theme-option"
                data-theme-key="<?= e($key); ?>"
                data-theme-type="string"
                value="<?= e((string)($saved['light'] ?? '')); ?>"
                <?= $canEdit ? '' : 'readonly'; ?>
            >
        <?php endif; ?>
    </div>
    <?php
};
?>

<div
    id="professionalThemeSettings"
    data-can-edit="<?= $themeCanEdit ? '1' : '0'; ?>"
>
    <div class="theme-pro-toolbar">
        <div>
            <span class="badge rounded-pill badge-soft-warning">
                PROFESSIONAL THEME
            </span>

            <h2 class="h4 mt-2 mb-1">
                Admin Theme Settings
            </h2>

            <p class="text-muted mb-0">
                Configure colours, typography and component styles.
                The website font pair is selected by default, and other
                professional font options are available.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="resetThemePreview"
            >
                Reset Preview
            </button>

            <?php if ($themeCanEdit): ?>
                <button
                    type="button"
                    class="btn btn-outline-danger"
                    id="restoreThemeDefaults"
                >
                    Restore Defaults
                </button>

                <button
                    type="button"
                    class="btn btn-ramki"
                    id="saveThemeSettings"
                >
                    <i class="fa-solid fa-floppy-disk me-2"></i>
                    Save All Settings
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($themePresets): ?>
        <section class="theme-preset-section">
            <div class="mb-3">
                <h3 class="theme-section-title mb-1">
                    Default Themes
                </h3>

                <p class="text-muted small mb-0">
                    Apply a preset to the preview and save it after
                    checking both light and dark modes.
                </p>
            </div>

            <div class="theme-preset-grid">
                <?php foreach (
                    $themePresets
                    as $presetKey => $preset
                ): ?>
                    <?php
                    $presetSettings =
                        (array)($preset['settings'] ?? []);

                    $swatches = [
                        $presetSettings['brand_1']['light']
                            ?? '#8B1231',
                        $presetSettings['brand_2']['light']
                            ?? '#C9963E',
                        $presetSettings['body_bg']['light']
                            ?? '#FFF9EF',
                        $presetSettings['sidebar_bg_2']['light']
                            ?? '#8B1231',
                    ];
                    ?>

                    <button
                        type="button"
                        class="theme-preset-card"
                        data-theme-preset="<?= e((string)$presetKey); ?>"
                        <?= $themeCanEdit ? '' : 'disabled'; ?>
                    >
                        <span class="theme-preset-swatches">
                            <?php foreach ($swatches as $colour): ?>
                                <span
                                    style="background:<?= e((string)$colour); ?>"
                                ></span>
                            <?php endforeach; ?>
                        </span>

                        <strong>
                            <?= e((string)($preset['label'] ?? $presetKey)); ?>
                        </strong>

                        <small>
                            <?= e((string)($preset['description'] ?? '')); ?>
                        </small>

                        <span class="preset-applied">
                            PREVIEW APPLIED
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="theme-pro-shell">
        <div class="theme-settings-workspace">
            <ul
                class="nav nav-pills theme-settings-tabs"
                id="themeSettingsTabs"
                role="tablist"
            >
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link active"
                        data-bs-toggle="pill"
                        data-bs-target="#themeColourTab"
                        type="button"
                    >
                        <i class="fa-solid fa-palette me-2"></i>
                        Colours
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        data-bs-toggle="pill"
                        data-bs-target="#themeTypographyTab"
                        type="button"
                    >
                        <i class="fa-solid fa-font me-2"></i>
                        Typography
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        data-bs-toggle="pill"
                        data-bs-target="#themeComponentsTab"
                        type="button"
                    >
                        <i class="fa-solid fa-shapes me-2"></i>
                        Components
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        data-bs-toggle="pill"
                        data-bs-target="#themeLayoutTab"
                        type="button"
                    >
                        <i class="fa-solid fa-sliders me-2"></i>
                        Layout
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div
                    class="tab-pane fade show active"
                    id="themeColourTab"
                >
                    <?php foreach (
                        $colourGroups
                        as $groupKey
                    ): ?>
                        <?php
                        if (!isset($themeDefinition[$groupKey])) {
                            continue;
                        }

                        $group = $themeDefinition[$groupKey];
                        ?>

                        <section class="theme-control-section">
                            <div class="theme-section-heading">
                                <span class="theme-section-icon">
                                    <i class="<?= e((string)(
                                        $group['icon']
                                        ?? 'fa-solid fa-palette'
                                    )); ?>"></i>
                                </span>

                                <div>
                                    <h3>
                                        <?= e((string)$group['label']); ?>
                                    </h3>

                                    <small class="text-muted">
                                        Separate values for light and dark modes.
                                    </small>
                                </div>
                            </div>

                            <div class="theme-control-grid">
                                <?php foreach (
                                    $group['settings']
                                    as $key => $definition
                                ): ?>
                                    <?php
                                    $saved = $adminThemeSettings[$key]
                                        ?? [
                                            'light' => $definition['light'],
                                            'dark' => $definition['dark'],
                                        ];

                                    $renderThemeControl(
                                        (string)$key,
                                        (array)$definition,
                                        (array)$saved,
                                        $themeCanEdit
                                    );
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div
                    class="tab-pane fade"
                    id="themeTypographyTab"
                >
                    <?php if (isset($themeDefinition['typography'])): ?>
                        <?php
                        $group = $themeDefinition['typography'];
                        ?>

                        <section class="theme-control-section">
                            <div class="theme-section-heading">
                                <span class="theme-section-icon">
                                    <i class="fa-solid fa-font"></i>
                                </span>

                                <div>
                                    <h3>Typography & Text</h3>
                                    <small class="text-muted">
                                        Website style: Poppins for normal text
                                        and Playfair Display for headings.
                                        You may select another font pair below.
                                    </small>
                                </div>
                            </div>

                            <div class="theme-control-grid">
                                <?php foreach (
                                    $group['settings']
                                    as $key => $definition
                                ): ?>
                                    <?php
                                    $saved = $adminThemeSettings[$key]
                                        ?? [
                                            'light' => $definition['light'],
                                            'dark' => $definition['dark'],
                                        ];

                                    $renderThemeControl(
                                        (string)$key,
                                        (array)$definition,
                                        (array)$saved,
                                        $themeCanEdit
                                    );
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <div
                    class="tab-pane fade"
                    id="themeComponentsTab"
                >
                    <?php if (isset($themeDefinition['components'])): ?>
                        <?php
                        $group = $themeDefinition['components'];
                        ?>

                        <section class="theme-control-section">
                            <div class="theme-section-heading">
                                <span class="theme-section-icon">
                                    <i class="fa-solid fa-shapes"></i>
                                </span>

                                <div>
                                    <h3>Component Styles</h3>
                                    <small class="text-muted">
                                        Control the sidebar, navbar, cards,
                                        buttons and tables.
                                    </small>
                                </div>
                            </div>

                            <div class="theme-control-grid">
                                <?php foreach (
                                    $group['settings']
                                    as $key => $definition
                                ): ?>
                                    <?php
                                    $saved = $adminThemeSettings[$key]
                                        ?? [
                                            'light' => $definition['light'],
                                            'dark' => $definition['dark'],
                                        ];

                                    $renderThemeControl(
                                        (string)$key,
                                        (array)$definition,
                                        (array)$saved,
                                        $themeCanEdit
                                    );
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>

                <div
                    class="tab-pane fade"
                    id="themeLayoutTab"
                >
                    <?php if (isset($themeDefinition['options'])): ?>
                        <?php
                        $group = $themeDefinition['options'];
                        ?>

                        <section class="theme-control-section">
                            <div class="theme-section-heading">
                                <span class="theme-section-icon">
                                    <i class="fa-solid fa-sliders"></i>
                                </span>

                                <div>
                                    <h3>Layout Settings</h3>
                                    <small class="text-muted">
                                        Set widths, heights, spacing and density.
                                    </small>
                                </div>
                            </div>

                            <div class="theme-control-grid">
                                <?php foreach (
                                    $group['settings']
                                    as $key => $definition
                                ): ?>
                                    <?php
                                    $saved = $adminThemeSettings[$key]
                                        ?? [
                                            'light' => $definition['light'],
                                            'dark' => $definition['dark'],
                                        ];

                                    $renderThemeControl(
                                        (string)$key,
                                        (array)$definition,
                                        (array)$saved,
                                        $themeCanEdit
                                    );
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <aside class="theme-preview-panel">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h3 class="theme-section-title mb-1">
                        Live Preview
                    </h3>

                    <p class="text-muted small mb-0">
                        Preview colours, fonts and component styles.
                    </p>
                </div>

                <select
                    class="form-select form-select-sm w-auto"
                    id="themePreviewMode"
                >
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>

            <div
                class="theme-preview-browser"
                id="themePreview"
            >
                <div class="theme-preview-top">
                    <strong>Ramki Cards</strong>
                    <span class="ms-auto">Super Admin</span>
                </div>

                <div class="theme-preview-body">
                    <div class="theme-preview-side">
                        <div class="preview-nav active">Dashboard</div>
                        <div class="preview-nav">Enquiries</div>
                        <div class="preview-nav">Products</div>
                        <div class="preview-nav">Orders</div>
                    </div>

                    <div class="theme-preview-content">
                        <div class="preview-card">
                            <h4>Invitation Orders</h4>
                            <p>
                                Manage products, enquiries and customer orders.
                            </p>
                            <button
                                type="button"
                                class="preview-button"
                            >
                                Add Product
                            </button>
                        </div>

                        <div class="preview-card preview-table-card">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RC-1001</td>
                                        <td>Pending</td>
                                    </tr>
                                    <tr>
                                        <td>RC-1002</td>
                                        <td>Confirmed</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$themeCanEdit): ?>
                <div class="alert alert-warning small mt-3 mb-0">
                    Your role has view-only access to Theme Settings.
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script>
window.RAMKI_THEME_DEFINITION = <?= json_encode(
    flatten_admin_theme_definition(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
); ?>;

window.RAMKI_THEME_PRESETS = <?= json_encode(
    $themePresets,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
); ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
