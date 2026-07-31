<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'website_content', 'can_view')) {
    http_response_code(403);
    exit('Permission denied.');
}

$websitePermissions = [
    'can_view' => can_menu(
        $pdo,
        'website_content',
        'can_view'
    ),
    'can_add' => can_menu(
        $pdo,
        'website_content',
        'can_add'
    ),
    'can_edit' => can_menu(
        $pdo,
        'website_content',
        'can_edit'
    ),
    'can_delete' => can_menu(
        $pdo,
        'website_content',
        'can_delete'
    ),
];

$pageTitle = 'Website Content';
$pageScript = 'website-content.js';
$pageStyles = ['website-content.css'];

$settingDefaults = [
    'company_name' => 'Ramki Cards',
    'phone_number' => '96299 54411',
    'secondary_phone_number' => '96299 54421',
    'whatsapp_number' => '96299 54411',
    'email_address' => 'info@ramkicards.com',
    'address' => 'Chennai, Tamil Nadu, India',
    'instagram_handle' => '@ramkicards',
];

$settingRows = $pdo->query(
    "SELECT setting_key, setting_value
     FROM site_settings
     WHERE setting_key IN (
        'company_name',
        'phone_number',
        'secondary_phone_number',
        'whatsapp_number',
        'email_address',
        'address',
        'instagram_handle'
     )"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$siteSettings = array_merge(
    $settingDefaults,
    array_map(
        static fn(mixed $value): string =>
            (string)$value,
        $settingRows
    )
);

$sectionDefinitions = [
    'hero' => [
        'label' => 'Hero Banner',
        'description' => 'Main website banner, heading, description, buttons and background image.',
        'icon' => 'fa-solid fa-panorama',
        'default_title' => 'Ramki Cards – Suba Nigalichi',
        'default_subtitle' => '25 Years of Trusted Excellence',
        'default_content' => 'Premium wedding and function invitations with custom printing and fast delivery.',
        'default_button_text' => 'View Collections',
        'default_button_url' => 'products.php',
        'sort_order' => 10,
        'fields' => [
            'title', 'subtitle', 'content',
            'button', 'background_image',
        ],
        'extras' => [
            'heading_line_2' => [
                'label' => 'Second Heading Line',
                'default' => 'Suba Nigalichi....',
            ],
            'secondary_button_text' => [
                'label' => 'Secondary Button Text',
                'default' => 'Get a Quote',
            ],
            'secondary_button_url' => [
                'label' => 'Secondary Button URL',
                'default' => '#contact',
            ],
        ],
    ],
    'top_strip' => [
        'label' => 'Top Announcement Strip',
        'description' => 'Scrolling announcements displayed above the public website navigation.',
        'icon' => 'fa-solid fa-bullhorn',
        'sort_order' => 20,
        'fields' => [],
        'items' => true,
        'item_labels' => [
            'title' => 'Announcement Text',
            'subtitle' => 'Optional Subtitle',
            'content' => 'Optional Details',
            'icon' => 'Emoji / Icon',
        ],
    ],
    'hero_features' => [
        'label' => 'Hero Highlights',
        'description' => 'The three small benefit cards displayed inside the hero banner.',
        'icon' => 'fa-solid fa-gem',
        'sort_order' => 30,
        'fields' => [],
        'items' => true,
        'item_labels' => [
            'title' => 'Highlight Title',
            'subtitle' => 'Short Subtitle',
            'content' => 'Optional Description',
            'icon' => 'Emoji / Icon',
        ],
    ],
    'categories' => [
        'label' => 'Featured Categories Section',
        'description' => 'Controls the category-section heading and visibility. Category cards are managed in Categories.',
        'icon' => 'fa-solid fa-layer-group',
        'default_title' => 'Featured',
        'default_subtitle' => 'Categories',
        'default_content' => 'Browse by type',
        'sort_order' => 40,
        'fields' => ['title', 'subtitle', 'content'],
        'quick_link' => 'categories.php',
        'quick_text' => 'Manage Categories',
    ],
    'featured_products' => [
        'label' => 'Featured Products Section',
        'description' => 'Controls the product-section heading, visibility and View All button. Products are managed in Products.',
        'icon' => 'fa-solid fa-box-open',
        'default_title' => 'Featured',
        'default_subtitle' => 'Products',
        'default_content' => 'Selected for you',
        'default_button_text' => 'View All Products',
        'default_button_url' => 'products.php',
        'sort_order' => 50,
        'fields' => ['title', 'subtitle', 'content', 'button'],
        'quick_link' => 'products.php',
        'quick_text' => 'Manage Products',
    ],
    'services' => [
        'label' => 'Services',
        'description' => 'Website services heading and service cards.',
        'icon' => 'fa-solid fa-handshake',
        'default_title' => 'Our',
        'default_subtitle' => 'Services',
        'sort_order' => 60,
        'fields' => ['title', 'subtitle'],
        'items' => true,
        'item_labels' => [
            'title' => 'Service Name',
            'subtitle' => 'Optional Subtitle',
            'content' => 'Service Description',
            'icon' => 'Emoji / Icon',
        ],
    ],
    'custom_design' => [
        'label' => 'Custom Design Process',
        'description' => 'Custom-design section heading, introduction and process steps.',
        'icon' => 'fa-solid fa-wand-magic-sparkles',
        'default_title' => 'Customize Every Detail of Your Wedding Card',
        'default_subtitle' => 'Make it truly yours',
        'default_content' => 'From names and dates to colors, fonts, language and design, we create invitations that are uniquely yours.',
        'sort_order' => 70,
        'fields' => ['title', 'subtitle', 'content'],
        'items' => true,
        'item_labels' => [
            'title' => 'Step Title',
            'subtitle' => 'Optional Subtitle',
            'content' => 'Step Description',
            'icon' => 'Emoji / Icon',
        ],
    ],
    'why_choose' => [
        'label' => 'Why Choose Us',
        'description' => 'Trust and benefit cards displayed on the homepage.',
        'icon' => 'fa-solid fa-award',
        'default_title' => 'Why Choose',
        'default_subtitle' => 'Ramki Cards?',
        'sort_order' => 80,
        'fields' => ['title', 'subtitle'],
        'items' => true,
        'item_labels' => [
            'title' => 'Benefit Title',
            'subtitle' => 'Optional Subtitle',
            'content' => 'Benefit Description',
            'icon' => 'Emoji / Icon',
        ],
    ],
    'testimonials' => [
        'label' => 'Customer Testimonials',
        'description' => 'Customer reviews displayed above the contact form.',
        'icon' => 'fa-solid fa-comments',
        'default_title' => 'What Our Customers Say',
        'sort_order' => 90,
        'fields' => ['title'],
        'items' => true,
        'item_labels' => [
            'title' => 'Customer Name',
            'subtitle' => 'Customer Location',
            'content' => 'Customer Review',
            'icon' => 'Optional Avatar Text',
        ],
        'rating' => true,
    ],
    'contact' => [
        'label' => 'Contact & WhatsApp',
        'description' => 'Enquiry form heading, contact card, WhatsApp card and success message.',
        'icon' => 'fa-solid fa-address-card',
        'default_title' => "Let's Create Something Beautiful Together",
        'default_subtitle' => 'Share your requirements and we will get back to you shortly.',
        'sort_order' => 100,
        'fields' => ['title', 'subtitle'],
        'extras' => [
            'info_title' => [
                'label' => 'Contact Card Title',
                'default' => 'Get in Touch',
            ],
            'info_subtitle' => [
                'label' => 'Contact Card Subtitle',
                'default' => 'We are here to help you!',
            ],
            'whatsapp_title' => [
                'label' => 'WhatsApp Card Title',
                'default' => 'Quick Enquiry on WhatsApp',
            ],
            'whatsapp_text' => [
                'label' => 'WhatsApp Card Text',
                'default' => 'Chat with us directly for a fast response.',
            ],
            'whatsapp_button_text' => [
                'label' => 'WhatsApp Button Text',
                'default' => 'Chat on WhatsApp',
            ],
            'whatsapp_message' => [
                'label' => 'Default WhatsApp Message',
                'default' => 'Hello Ramki Cards, I would like to enquire about your invitation cards.',
            ],
            'form_button_text' => [
                'label' => 'Enquiry Submit Button',
                'default' => 'Submit Enquiry ✈',
            ],
            'success_title' => [
                'label' => 'Success Popup Title',
                'default' => 'Thank You!',
            ],
            'success_message' => [
                'label' => 'Success Popup Message',
                'default' => 'Your enquiry has been submitted successfully. Our team will get back to you shortly.',
            ],
        ],
    ],
];

$sectionRows = $pdo->query(
    "SELECT *
     FROM site_sections
     WHERE page_slug = 'home'
     ORDER BY sort_order, id"
)->fetchAll(PDO::FETCH_ASSOC);

$sections = [];

foreach ($sectionRows as $row) {
    $row['additional_settings_array'] = json_decode(
        (string)($row['additional_settings'] ?? ''),
        true
    );

    if (!is_array($row['additional_settings_array'])) {
        $row['additional_settings_array'] = [];
    }

    $sections[(string)$row['section_key']] = $row;
}

$itemRows = $pdo->query(
    "SELECT
        i.*,
        s.section_key
     FROM site_section_items i
     INNER JOIN site_sections s
        ON s.id = i.section_id
     WHERE s.page_slug = 'home'
     ORDER BY s.sort_order, i.sort_order, i.id"
)->fetchAll(PDO::FETCH_ASSOC);

$sectionItems = [];

foreach ($itemRows as $row) {
    $row['additional_data_array'] = json_decode(
        (string)($row['additional_data'] ?? ''),
        true
    );

    if (!is_array($row['additional_data_array'])) {
        $row['additional_data_array'] = [];
    }

    $sectionItems[(string)$row['section_key']][] = $row;
}

$adminMediaUrl = static function (?string $path): string {
    $path = trim((string)$path);

    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return '../' . ltrim($path, '/');
};

$getSection = static function (
    string $key,
    array $definition,
    array $rows
): array {
    $row = $rows[$key] ?? [];

    return array_merge([
        'id' => 0,
        'page_slug' => 'home',
        'section_key' => $key,
        'section_title' => $definition['default_title'] ?? '',
        'section_subtitle' => $definition['default_subtitle'] ?? '',
        'section_content' => $definition['default_content'] ?? '',
        'image_path' => '',
        'background_image_path' => '',
        'button_text' => $definition['default_button_text'] ?? '',
        'button_url' => $definition['default_button_url'] ?? '',
        'additional_settings_array' => [],
        'sort_order' => $definition['sort_order'] ?? 0,
        'status' => 'active',
    ], $row);
};

$renderSectionEditor = static function (
    string $key,
    array $definition,
    array $section,
    array $items,
    array $permissions,
    callable $mediaUrl
): void {
    $fields = $definition['fields'] ?? [];
    $extras = $definition['extras'] ?? [];
    $additional = $section['additional_settings_array'] ?? [];

    $titleLabel = $key === 'hero'
        ? 'Main Heading'
        : 'Section Title';
    $subtitleLabel = $key === 'hero'
        ? 'Eyebrow / Trust Line'
        : 'Section Subtitle';
    $contentLabel = $key === 'hero'
        ? 'Hero Description'
        : 'Section Content';
    ?>
    <section
        class="website-section-card"
        id="section-<?= e($key); ?>"
    >
        <div class="website-section-card-head">
            <div class="d-flex align-items-start gap-3">
                <span class="website-section-icon">
                    <i class="<?= e((string)$definition['icon']); ?>"></i>
                </span>

                <div>
                    <h3><?= e((string)$definition['label']); ?></h3>
                    <p><?= e((string)$definition['description']); ?></p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if (!empty($definition['quick_link'])): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary"
                        href="<?= e((string)$definition['quick_link']); ?>"
                    >
                        <?= e((string)$definition['quick_text']); ?>
                    </a>
                <?php endif; ?>

                <span class="badge <?= $section['status'] === 'active'
                    ? 'bg-success'
                    : 'bg-secondary'; ?>"
                >
                    <?= e(ucfirst((string)$section['status'])); ?>
                </span>
            </div>
        </div>

        <form
            class="website-section-form"
            method="post"
            action="api/website-content.php"
            enctype="multipart/form-data"
        >
            <input type="hidden" name="action" value="save_section">
            <input type="hidden" name="section_key" value="<?= e($key); ?>">

            <?php if ($fields || $extras): ?>
                <div class="row g-3">
                    <?php if (in_array('title', $fields, true)): ?>
                        <div class="col-md-6">
                            <label class="form-label"><?= e($titleLabel); ?></label>
                            <input
                                class="form-control"
                                name="section_title"
                                maxlength="255"
                                value="<?= e((string)$section['section_title']); ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('subtitle', $fields, true)): ?>
                        <div class="col-md-6">
                            <label class="form-label"><?= e($subtitleLabel); ?></label>
                            <input
                                class="form-control"
                                name="section_subtitle"
                                maxlength="255"
                                value="<?= e((string)$section['section_subtitle']); ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('content', $fields, true)): ?>
                        <div class="col-12">
                            <label class="form-label"><?= e($contentLabel); ?></label>
                            <textarea
                                class="form-control"
                                rows="3"
                                name="section_content"
                            ><?= e((string)$section['section_content']); ?></textarea>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('button', $fields, true)): ?>
                        <div class="col-md-6">
                            <label class="form-label">Button Text</label>
                            <input
                                class="form-control"
                                name="button_text"
                                maxlength="100"
                                value="<?= e((string)$section['button_text']); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Button URL</label>
                            <input
                                class="form-control"
                                name="button_url"
                                maxlength="255"
                                value="<?= e((string)$section['button_url']); ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php foreach ($extras as $extraKey => $extra): ?>
                        <?php
                        $extraValue = (string)(
                            $additional[$extraKey]
                            ?? $extra['default']
                            ?? ''
                        );
                        $isLong = in_array(
                            $extraKey,
                            ['whatsapp_message', 'success_message'],
                            true
                        );
                        ?>

                        <div class="<?= $isLong ? 'col-12' : 'col-md-6'; ?>">
                            <label class="form-label">
                                <?= e((string)$extra['label']); ?>
                            </label>

                            <?php if ($isLong): ?>
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    name="extra[<?= e($extraKey); ?>]"
                                ><?= e($extraValue); ?></textarea>
                            <?php else: ?>
                                <input
                                    class="form-control"
                                    name="extra[<?= e($extraKey); ?>]"
                                    maxlength="255"
                                    value="<?= e($extraValue); ?>"
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (in_array('background_image', $fields, true)): ?>
                        <div class="col-md-8">
                            <label class="form-label">Hero Background Image</label>
                            <input
                                type="file"
                                class="form-control js-content-image"
                                name="background_image"
                                accept=".jpg,.jpeg,.png,.webp"
                            >
                            <div class="form-text">JPG, PNG or WebP. Maximum 5 MB.</div>
                        </div>

                        <div class="col-md-4">
                            <?php if (!empty($section['background_image_path'])): ?>
                                <label class="form-label d-block">Current Image</label>
                                <img
                                    class="website-current-image"
                                    src="<?= e($mediaUrl((string)$section['background_image_path'])); ?>"
                                    alt="Current hero background"
                                >
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="website-section-controls">
                <div>
                    <label class="form-label">Sort Order</label>
                    <input
                        type="number"
                        min="0"
                        max="999999"
                        class="form-control"
                        name="sort_order"
                        value="<?= (int)$section['sort_order']; ?>"
                    >
                </div>

                <div>
                    <label class="form-label">Visibility</label>
                    <select class="form-select" name="status">
                        <option
                            value="active"
                            <?= $section['status'] === 'active'
                                ? 'selected'
                                : ''; ?>
                        >Active</option>
                        <option
                            value="inactive"
                            <?= $section['status'] === 'inactive'
                                ? 'selected'
                                : ''; ?>
                        >Inactive</option>
                    </select>
                </div>

                <?php if ($permissions['can_edit']): ?>
                    <button
                        type="submit"
                        class="btn btn-ramki align-self-end"
                    >
                        <i class="fa-solid fa-floppy-disk me-2"></i>
                        Save Section
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($definition['items'])): ?>
            <div class="website-items-block">
                <div class="website-items-head">
                    <div>
                        <h4>Section Items</h4>
                        <p>Manage the cards, steps or messages displayed in this section.</p>
                    </div>

                    <?php if ($permissions['can_add']): ?>
                        <button
                            type="button"
                            class="btn btn-sm btn-ramki add-content-item"
                            data-section-key="<?= e($key); ?>"
                            data-section-label="<?= e((string)$definition['label']); ?>"
                            data-labels='<?= e(json_encode(
                                $definition['item_labels'] ?? [],
                                JSON_UNESCAPED_UNICODE
                            )); ?>'
                            data-has-rating="<?= !empty($definition['rating']) ? '1' : '0'; ?>"
                        >
                            <i class="fa-solid fa-plus me-2"></i>
                            Add Item
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Icon</th>
                                <th>Title</th>
                                <th>Subtitle</th>
                                <th>Status</th>
                                <th>Sort</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$items): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No items added for this section.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                    <?php
                                    $payload = [
                                        'id' => (int)$item['id'],
                                        'section_key' => $key,
                                        'item_title' => (string)($item['item_title'] ?? ''),
                                        'item_subtitle' => (string)($item['item_subtitle'] ?? ''),
                                        'item_content' => (string)($item['item_content'] ?? ''),
                                        'icon_class' => (string)($item['icon_class'] ?? ''),
                                        'link_text' => (string)($item['link_text'] ?? ''),
                                        'link_url' => (string)($item['link_url'] ?? ''),
                                        'sort_order' => (int)$item['sort_order'],
                                        'status' => (string)$item['status'],
                                        'rating' => (int)(
                                            $item['additional_data_array']['rating']
                                            ?? 5
                                        ),
                                    ];
                                    ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td class="website-item-icon">
                                            <?= e((string)($item['icon_class'] ?: '✦')); ?>
                                        </td>
                                        <td>
                                            <strong><?= e((string)$item['item_title']); ?></strong>
                                            <?php if (!empty($item['item_content'])): ?>
                                                <small class="d-block text-muted website-item-summary">
                                                    <?= e(mb_strimwidth(
                                                        (string)$item['item_content'],
                                                        0,
                                                        90,
                                                        '…'
                                                    )); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string)($item['item_subtitle'] ?: '-')); ?></td>
                                        <td>
                                            <span class="badge <?= $item['status'] === 'active'
                                                ? 'bg-success'
                                                : 'bg-secondary'; ?>"
                                            >
                                                <?= e(ucfirst((string)$item['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?= (int)$item['sort_order']; ?></td>
                                        <td class="text-nowrap">
                                            <?php if ($permissions['can_edit']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary edit-content-item"
                                                    data-item='<?= e(json_encode(
                                                        $payload,
                                                        JSON_UNESCAPED_UNICODE
                                                    )); ?>'
                                                    data-section-label="<?= e((string)$definition['label']); ?>"
                                                    data-labels='<?= e(json_encode(
                                                        $definition['item_labels'] ?? [],
                                                        JSON_UNESCAPED_UNICODE
                                                    )); ?>'
                                                    data-has-rating="<?= !empty($definition['rating']) ? '1' : '0'; ?>"
                                                    title="Edit"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($permissions['can_delete']): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger delete-content-item"
                                                    data-id="<?= (int)$item['id']; ?>"
                                                    title="Delete"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
};

require __DIR__ . '/includes/header.php';
?>

<div
    id="websiteContentModule"
    data-can-add="<?= $websitePermissions['can_add'] ? '1' : '0'; ?>"
    data-can-edit="<?= $websitePermissions['can_edit'] ? '1' : '0'; ?>"
    data-can-delete="<?= $websitePermissions['can_delete'] ? '1' : '0'; ?>"
>
    <div class="website-content-toolbar">
        <div>
            <span class="badge rounded-pill badge-soft-warning">
                WEBSITE CONTROL
            </span>
            <h2 class="h4 mt-2 mb-1">Homepage Content Manager</h2>
            <p class="text-muted mb-0">
                Control the public homepage content, visibility, order, contact information and section items.
            </p>
        </div>

        <a
            class="btn btn-outline-secondary"
            href="../index.php"
            target="_blank"
        >
            <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>
            Preview Website
        </a>
    </div>

    <ul class="nav nav-pills website-content-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#generalContentTab" type="button">
                <i class="fa-solid fa-building me-2"></i>General
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#heroContentTab" type="button">
                <i class="fa-solid fa-panorama me-2"></i>Hero & Header
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#catalogueContentTab" type="button">
                <i class="fa-solid fa-boxes-stacked me-2"></i>Catalogue
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#mainContentTab" type="button">
                <i class="fa-solid fa-table-cells-large me-2"></i>Content Sections
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#contactContentTab" type="button">
                <i class="fa-solid fa-comments me-2"></i>Reviews & Contact
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="generalContentTab">
            <section class="website-section-card">
                <div class="website-section-card-head">
                    <div class="d-flex align-items-start gap-3">
                        <span class="website-section-icon">
                            <i class="fa-solid fa-building"></i>
                        </span>
                        <div>
                            <h3>Business & Contact Information</h3>
                            <p>Used in the contact card, WhatsApp link and other public website locations.</p>
                        </div>
                    </div>
                </div>

                <form
                    id="websiteGeneralSettingsForm"
                    method="post"
                    action="api/website-content.php"
                >
                    <input type="hidden" name="action" value="save_settings">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name *</label>
                            <input class="form-control" name="company_name" maxlength="150" value="<?= e($siteSettings['company_name']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Primary Phone *</label>
                            <input class="form-control" name="phone_number" maxlength="30" value="<?= e($siteSettings['phone_number']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Secondary Phone</label>
                            <input class="form-control" name="secondary_phone_number" maxlength="30" value="<?= e($siteSettings['secondary_phone_number']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">WhatsApp Number *</label>
                            <input class="form-control" name="whatsapp_number" maxlength="30" value="<?= e($siteSettings['whatsapp_number']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email_address" maxlength="190" value="<?= e($siteSettings['email_address']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Instagram Handle</label>
                            <input class="form-control" name="instagram_handle" maxlength="100" value="<?= e($siteSettings['instagram_handle']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Business Address *</label>
                            <textarea class="form-control" name="address" rows="3" required><?= e($siteSettings['address']); ?></textarea>
                        </div>
                    </div>

                    <?php if ($websitePermissions['can_edit']): ?>
                        <div class="mt-3">
                            <button class="btn btn-ramki" type="submit">
                                <i class="fa-solid fa-floppy-disk me-2"></i>
                                Save Contact Information
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </section>
        </div>

        <div class="tab-pane fade" id="heroContentTab">
            <?php foreach (['hero', 'top_strip', 'hero_features'] as $key): ?>
                <?php
                $definition = $sectionDefinitions[$key];
                $renderSectionEditor(
                    $key,
                    $definition,
                    $getSection($key, $definition, $sections),
                    $sectionItems[$key] ?? [],
                    $websitePermissions,
                    $adminMediaUrl
                );
                ?>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="catalogueContentTab">
            <?php foreach (['categories', 'featured_products'] as $key): ?>
                <?php
                $definition = $sectionDefinitions[$key];
                $renderSectionEditor(
                    $key,
                    $definition,
                    $getSection($key, $definition, $sections),
                    [],
                    $websitePermissions,
                    $adminMediaUrl
                );
                ?>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="mainContentTab">
            <?php foreach (['services', 'custom_design', 'why_choose'] as $key): ?>
                <?php
                $definition = $sectionDefinitions[$key];
                $renderSectionEditor(
                    $key,
                    $definition,
                    $getSection($key, $definition, $sections),
                    $sectionItems[$key] ?? [],
                    $websitePermissions,
                    $adminMediaUrl
                );
                ?>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane fade" id="contactContentTab">
            <?php foreach (['testimonials', 'contact'] as $key): ?>
                <?php
                $definition = $sectionDefinitions[$key];
                $renderSectionEditor(
                    $key,
                    $definition,
                    $getSection($key, $definition, $sections),
                    $sectionItems[$key] ?? [],
                    $websitePermissions,
                    $adminMediaUrl
                );
                ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="contentItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form
            class="modal-content"
            id="contentItemForm"
            method="post"
            action="api/website-content.php"
            enctype="multipart/form-data"
        >
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Website Section Item</h5>
                    <small class="text-muted" id="contentItemSectionLabel"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="save_item">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="section_key">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" id="contentItemTitleLabel">Item Title *</label>
                        <input class="form-control" name="item_title" maxlength="255" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" id="contentItemIconLabel">Emoji / Icon</label>
                        <input class="form-control" name="icon_class" maxlength="150" placeholder="💎 or fa-solid fa-star">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" id="contentItemSubtitleLabel">Subtitle</label>
                        <input class="form-control" name="item_subtitle" maxlength="255">
                    </div>
                    <div class="col-md-3 content-item-rating-field" hidden>
                        <label class="form-label">Rating</label>
                        <select class="form-select" name="rating">
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" max="999999" class="form-control" name="sort_order" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label" id="contentItemContentLabel">Description</label>
                        <textarea class="form-control" rows="4" name="item_content"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link Text</label>
                        <input class="form-control" name="link_text" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link URL</label>
                        <input class="form-control" name="link_url" maxlength="255">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Item Image</label>
                        <input type="file" class="form-control" name="item_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-ramki">Save Item</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
