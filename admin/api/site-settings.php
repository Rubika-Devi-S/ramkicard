<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();

$settingsMap = [
    'company_name' => ['contact', 'string', 1],
    'phone_number' => ['contact', 'string', 1],
    'secondary_phone_number' => ['contact', 'string', 1],
    'whatsapp_number' => ['contact', 'string', 1],
    'email_address' => ['contact', 'string', 1],
    'address' => ['contact', 'text', 1],
    'purchase_mode' => ['commerce', 'string', 1],
    'instagram_url' => ['social', 'string', 1],
    'facebook_url' => ['social', 'string', 1],
    'youtube_url' => ['social', 'string', 1],
    'admin_notification_email' => ['mail', 'string', 0],
];

try {
    if ($action === 'get') {
        require_permission($pdo, 'settings', 'can_view');

        $keys = array_keys($settingsMap);
        $placeholders = implode(
            ',',
            array_fill(0, count($keys), '?')
        );

        $stmt = $pdo->prepare(
            "SELECT setting_key, setting_value
             FROM site_settings
             WHERE setting_key IN ({$placeholders})"
        );

        $stmt->execute($keys);

        $result = array_fill_keys($keys, '');

        foreach ($stmt->fetchAll() as $row) {
            $result[$row['setting_key']] =
                $row['setting_value'];
        }

        json_response(true, '', $result);
    }

    if ($action === 'save') {
        require_permission($pdo, 'settings', 'can_edit');

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

        foreach (
            $settingsMap
            as $key => [$group, $type, $isPublic]
        ) {
            $value = trim(
                (string)request_value($key, '')
            );

            if (
                $key === 'purchase_mode' &&
                !in_array(
                    $value,
                    ['checkout', 'enquiry', 'both'],
                    true
                )
            ) {
                $value = 'both';
            }

            $stmt->execute([
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => $value,
                'data_type' => $type,
                'is_public' => $isPublic,
                'updated_by' => current_admin_id(),
            ]);
        }

        activity_log(
            $pdo,
            'update',
            'Site Settings',
            'site_settings',
            null,
            'Website settings updated.'
        );

        json_response(true, 'Settings saved successfully.');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    json_response(false, $e->getMessage(), null, 422);
}
