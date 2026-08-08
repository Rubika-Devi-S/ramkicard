<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$canViewSettings = can_menu(
    $pdo,
    'settings',
    'can_view'
);

$canEditSettings = can_menu(
    $pdo,
    'settings',
    'can_edit'
);

if (function_exists('is_super_admin') && is_super_admin($pdo)) {
    $canViewSettings = true;
    $canEditSettings = true;
}

if (!$canViewSettings) {
    http_response_code(403);
    exit('Permission denied.');
}

/*
|--------------------------------------------------------------------------
| Settings controlled by this page
|--------------------------------------------------------------------------
*/

$siteSettingDefinitions = [
    'company_name' => [
        'group' => 'contact',
        'type' => 'string',
        'public' => 1,
        'default' => 'Ramki Cards',
    ],
    'admin_notification_email' => [
        'group' => 'mail',
        'type' => 'string',
        'public' => 0,
        'default' => '',
    ],
    'phone_number' => [
        'group' => 'contact',
        'type' => 'string',
        'public' => 1,
        'default' => '96299 54411',
    ],
    'secondary_phone_number' => [
        'group' => 'contact',
        'type' => 'string',
        'public' => 1,
        'default' => '',
    ],
    'whatsapp_number' => [
        'group' => 'contact',
        'type' => 'string',
        'public' => 1,
        'default' => '96299 54411',
    ],
    'email_address' => [
        'group' => 'contact',
        'type' => 'string',
        'public' => 1,
        'default' => '',
    ],
    'purchase_mode' => [
        'group' => 'commerce',
        'type' => 'string',
        'public' => 1,
        'default' => 'enquiry',
    ],
    'address' => [
        'group' => 'contact',
        'type' => 'text',
        'public' => 1,
        'default' => '',
    ],
    'instagram_url' => [
        'group' => 'social',
        'type' => 'string',
        'public' => 1,
        'default' => '',
    ],
    'facebook_url' => [
        'group' => 'social',
        'type' => 'string',
        'public' => 1,
        'default' => '',
    ],
    'youtube_url' => [
        'group' => 'social',
        'type' => 'string',
        'public' => 1,
        'default' => '',
    ],
];

function site_settings_page_redirect(
    string $type,
    string $message
): void {
    $_SESSION['site_settings_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: site-settings.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Save directly in this PHP page
|--------------------------------------------------------------------------
| This intentionally does not depend on site-settings.js or
| api/site-settings.php. It follows the same reliable POST pattern used by
| the working server-rendered admin modules.
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        csrf_validate();

        if (!$canEditSettings) {
            throw new RuntimeException(
                'You do not have permission to update Site Settings.'
            );
        }

        $action = trim((string)($_POST['action'] ?? ''));

        if ($action !== 'save') {
            throw new RuntimeException('Invalid Site Settings action.');
        }

        $values = [];

        foreach ($siteSettingDefinitions as $key => $definition) {
            $values[$key] = trim(
                (string)($_POST[$key] ?? '')
            );
        }

        if ($values['company_name'] === '') {
            throw new RuntimeException('Company name is required.');
        }

        if ($values['phone_number'] === '') {
            throw new RuntimeException('Phone number is required.');
        }

        if ($values['whatsapp_number'] === '') {
            throw new RuntimeException('WhatsApp number is required.');
        }

        foreach (
            ['admin_notification_email', 'email_address']
            as $emailKey
        ) {
            if (
                $values[$emailKey] !== ''
                && filter_var(
                    $values[$emailKey],
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    'Please enter a valid email address.'
                );
            }
        }

        if (!in_array(
            $values['purchase_mode'],
            ['checkout', 'enquiry', 'both'],
            true
        )) {
            throw new RuntimeException('Invalid purchase mode selected.');
        }

        foreach ($values as $key => $value) {
            $maxLength = $key === 'address' ? 2000 : 500;

            if (strlen($value) > $maxLength) {
                throw new RuntimeException(
                    'One of the Site Settings values is too long.'
                );
            }
        }

        $adminId = (int)(
            $_SESSION['ramki_admin']['id']
            ?? $_SESSION['ramki_admin']['user_id']
            ?? 0
        );

        $pdo->beginTransaction();

        $saveStatement = $pdo->prepare(
            "INSERT INTO site_settings
            (
                setting_group,
                setting_key,
                setting_value,
                data_type,
                is_public,
                updated_by
            )
            VALUES
            (
                :setting_group,
                :setting_key,
                :setting_value,
                :data_type,
                :is_public,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                setting_group = VALUES(setting_group),
                setting_value = VALUES(setting_value),
                data_type = VALUES(data_type),
                is_public = VALUES(is_public),
                updated_by = VALUES(updated_by)"
        );

        foreach ($siteSettingDefinitions as $key => $definition) {
            $saveStatement->execute([
                'setting_group' => $definition['group'],
                'setting_key' => $key,
                'setting_value' => $values[$key],
                'data_type' => $definition['type'],
                'is_public' => (int)$definition['public'],
                'updated_by' => $adminId > 0
                    ? $adminId
                    : null,
            ]);
        }

        if (function_exists('activity_log')) {
            activity_log(
                $pdo,
                'update',
                'Site Settings',
                'site_settings',
                0,
                'Global website and commerce settings updated.'
            );
        }

        $pdo->commit();

        site_settings_page_redirect(
            'success',
            'Site settings saved successfully.'
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        site_settings_page_redirect(
            'error',
            $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load current database values
|--------------------------------------------------------------------------
*/

$siteSettings = [];

foreach ($siteSettingDefinitions as $key => $definition) {
    $siteSettings[$key] = (string)$definition['default'];
}

$settingKeys = array_keys($siteSettingDefinitions);
$settingPlaceholders = implode(
    ', ',
    array_fill(0, count($settingKeys), '?')
);

$loadStatement = $pdo->prepare(
    "SELECT setting_key, setting_value
     FROM site_settings
     WHERE setting_key IN ({$settingPlaceholders})"
);

$loadStatement->execute($settingKeys);

foreach ($loadStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $siteSettings[(string)$row['setting_key']] =
        (string)($row['setting_value'] ?? '');
}

$flash = $_SESSION['site_settings_flash'] ?? null;
unset($_SESSION['site_settings_flash']);

$pageTitle = 'Site Settings';

// Saving and loading are handled in this PHP file.
$pageScript = null;

require __DIR__ . '/includes/header.php';
?>

<form class="ramki-card p-3" id="settingsForm" method="post">
    <input type="hidden" name="_token" value="<?= e(csrf_token()); ?>">
    <input type="hidden" name="action" value="save">

    <div class="mb-4">
        <h2 class="h5 mb-1">Website and Commerce Settings</h2>
        <p class="text-muted small mb-0">
            These values are shared by the public website and admin panel.
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input class="form-control" name="company_name" value="<?= e($siteSettings['company_name']); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Admin Notification Email</label>
            <input type="email" class="form-control" name="admin_notification_email"
                value="<?= e($siteSettings['admin_notification_email']); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Phone Number</label>
            <input class="form-control" name="phone_number" value="<?= e($siteSettings['phone_number']); ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Second Phone</label>
            <input class="form-control" name="secondary_phone_number"
                value="<?= e($siteSettings['secondary_phone_number']); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">WhatsApp Number</label>
            <input class="form-control" name="whatsapp_number" value="<?= e($siteSettings['whatsapp_number']); ?>"
                required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Website Email</label>
            <input type="email" class="form-control" name="email_address"
                value="<?= e($siteSettings['email_address']); ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label">Purchase Mode</label>
            <select class="form-select" name="purchase_mode">
                <option value="checkout" <?= $siteSettings['purchase_mode'] === 'checkout'
                        ? 'selected'
                        : ''; ?>>Checkout</option>
                <option value="enquiry" <?= $siteSettings['purchase_mode'] === 'enquiry'
                        ? 'selected'
                        : ''; ?>>Enquiry Now</option>
                <option value="both" <?= $siteSettings['purchase_mode'] === 'both'
                        ? 'selected'
                        : ''; ?>>Both</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Address</label>
            <textarea class="form-control" rows="3" name="address"><?= e($siteSettings['address']); ?></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label">Instagram URL</label>
            <input class="form-control" name="instagram_url" value="<?= e($siteSettings['instagram_url']); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">Facebook URL</label>
            <input class="form-control" name="facebook_url" value="<?= e($siteSettings['facebook_url']); ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label">YouTube URL</label>
            <input class="form-control" name="youtube_url" value="<?= e($siteSettings['youtube_url']); ?>">
        </div>
    </div>

    <?php if ($canEditSettings): ?>
    <div class="mt-4">
        <button class="btn btn-ramki px-4" type="submit">
            <i class="fa-solid fa-floppy-disk me-2"></i>
            Save Settings
        </button>
    </div>
    <?php endif; ?>
</form>

<?php if (is_array($flash)): ?>
<script>
window.addEventListener('load', function() {
    const type = <?= json_encode(
        (string)($flash['type'] ?? 'info'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;
    const message = <?= json_encode(
        (string)($flash['message'] ?? ''),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;

    if (
        window.RamkiAdmin &&
        typeof window.RamkiAdmin.toast === 'function'
    ) {
        window.RamkiAdmin.toast(type, message);
    } else if (message) {
        window.alert(message);
    }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>