<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

/**
 * Settings managed by the Site Settings screen.
 *
 * Keeping this whitelist on the server prevents an administrator request from
 * overwriting unrelated/private settings simply by posting another key.
 */
function site_settings_definition(): array
{
    return [
        'company_name' => [
            'group' => 'contact',
            'type' => 'string',
            'public' => 1,
            'max' => 150,
        ],
        'admin_notification_email' => [
            'group' => 'mail',
            'type' => 'string',
            'public' => 0,
            'max' => 255,
            'email' => true,
        ],
        'phone_number' => [
            'group' => 'contact',
            'type' => 'string',
            'public' => 1,
            'max' => 40,
        ],
        'secondary_phone_number' => [
            'group' => 'contact',
            'type' => 'string',
            'public' => 1,
            'max' => 40,
        ],
        'whatsapp_number' => [
            'group' => 'contact',
            'type' => 'string',
            'public' => 1,
            'max' => 40,
        ],
        'email_address' => [
            'group' => 'contact',
            'type' => 'string',
            'public' => 1,
            'max' => 255,
            'email' => true,
        ],
        'purchase_mode' => [
            'group' => 'commerce',
            'type' => 'string',
            'public' => 1,
            'max' => 20,
            'choices' => ['checkout', 'enquiry', 'both'],
        ],
        'address' => [
            'group' => 'contact',
            'type' => 'text',
            'public' => 1,
            'max' => 2000,
        ],
        'instagram_url' => [
            'group' => 'social',
            'type' => 'string',
            'public' => 1,
            'max' => 500,
            'url' => true,
        ],
        'facebook_url' => [
            'group' => 'social',
            'type' => 'string',
            'public' => 1,
            'max' => 500,
            'url' => true,
        ],
        'youtube_url' => [
            'group' => 'social',
            'type' => 'string',
            'public' => 1,
            'max' => 500,
            'url' => true,
        ],
    ];
}

function site_settings_admin_id(): ?int
{
    $id = (int)(
        $_SESSION['ramki_admin']['id']
        ?? $_SESSION['ramki_admin']['user_id']
        ?? 0
    );

    return $id > 0 ? $id : null;
}

function site_settings_length(string $value): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($value)
        : strlen($value);
}

function site_settings_validate(
    string $key,
    string $value,
    array $definition
): string {
    $value = trim($value);

    if (site_settings_length($value) > (int)$definition['max']) {
        throw new RuntimeException(
            str_replace('_', ' ', ucfirst($key)) . ' is too long.'
        );
    }

    if (
        $value !== ''
        && !empty($definition['email'])
        && filter_var($value, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new RuntimeException(
            'Please enter a valid ' . str_replace('_', ' ', $key) . '.'
        );
    }

    if ($value !== '' && !empty($definition['url'])) {
        $url = filter_var($value, FILTER_VALIDATE_URL);
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));

        if ($url === false || !in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(
                'Please enter a complete http:// or https:// URL for '
                . str_replace('_', ' ', $key) . '.'
            );
        }
    }

    if (
        isset($definition['choices'])
        && !in_array($value, $definition['choices'], true)
    ) {
        throw new RuntimeException('Invalid purchase mode selected.');
    }

    return $value;
}

try {
    $definitions = site_settings_definition();

    if ($action === 'get') {
        require_permission($pdo, 'settings', 'can_view');

        $keys = array_keys($definitions);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value
             FROM site_settings
             WHERE setting_key IN ({$placeholders})"
        );
        $stmt->execute($keys);

        $settings = array_fill_keys($keys, '');

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string)$row['setting_key']] =
                (string)($row['setting_value'] ?? '');
        }

        // A safe default keeps the select usable on a fresh database.
        if ($settings['purchase_mode'] === '') {
            $settings['purchase_mode'] = 'enquiry';
        }

        json_response(true, '', $settings);
    }

    if ($action === 'save') {
        require_permission($pdo, 'settings', 'can_edit');

        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = site_settings_validate(
                $key,
                (string)request_value($key, ''),
                $definition
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

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
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

        $adminId = site_settings_admin_id();

        foreach ($definitions as $key => $definition) {
            $stmt->execute([
                'setting_group' => $definition['group'],
                'setting_key' => $key,
                'setting_value' => $values[$key],
                'data_type' => $definition['type'],
                'is_public' => (int)$definition['public'],
                'updated_by' => $adminId,
            ]);
        }

        activity_log(
            $pdo,
            'update',
            'Site Settings',
            'site_settings',
            0,
            'Global website and commerce settings updated.'
        );

        $pdo->commit();

        json_response(
            true,
            'Site settings saved successfully.',
            $values
        );
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(false, $e->getMessage(), null, 422);
}
