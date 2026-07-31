<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$action = request_action();
$definitions = flatten_admin_theme_definition();

function theme_database_type(string $type): string
{
    return in_array(
        $type,
        ['color', 'string', 'boolean', 'number', 'select'],
        true
    ) ? $type : 'string';
}

function theme_normalise_option_token(
    mixed $value
): string {
    return strtolower(
        preg_replace(
            '/[\s_-]+/',
            '',
            trim((string)$value)
        ) ?? ''
    );
}

function theme_weight_meaning(
    mixed $value
): string {
    $token = theme_normalise_option_token($value);

    $meanings = [
        'normal' => 'regular',
        'regular' => 'regular',
        '400' => 'regular',
        'medium' => 'medium',
        '500' => 'medium',
        'semibold' => 'semibold',
        '600' => 'semibold',
        'bold' => 'bold',
        '700' => 'bold',
        'extrabold' => 'extrabold',
        '800' => 'extrabold',
        'black' => 'black',
        '900' => 'black',
    ];

    return $meanings[$token] ?? $token;
}

function theme_definition_for_save(
    string $key,
    array $definition
): array {
    /*
     * The Theme Settings UI intentionally keeps the public
     * Ramki Cards font identity.
     */
    if ($key === 'font_family') {
        $definition['options'][
            'Poppins, Arial, sans-serif'
        ] = 'Poppins';
    }

    if ($key === 'heading_font_family') {
        $definition['options'][
            '"Playfair Display", Georgia, serif'
        ] = 'Playfair Display';
    }

    return $definition;
}

function theme_resolve_select_value(
    string $key,
    mixed $submittedValue,
    array $definition
): string {
    $options = (array)(
        $definition['options']
        ?? []
    );

    if (!$options) {
        return trim((string)$submittedValue);
    }

    $submitted = trim(
        (string)$submittedValue
    );

    if (array_key_exists($submitted, $options)) {
        return $submitted;
    }

    $submittedToken =
        theme_normalise_option_token($submitted);

    foreach ($options as $value => $label) {
        if (
            theme_normalise_option_token($value)
                === $submittedToken
            || theme_normalise_option_token($label)
                === $submittedToken
        ) {
            return (string)$value;
        }
    }

    if (
        $key === 'font_weight'
        || $key === 'heading_font_weight'
    ) {
        $submittedMeaning =
            theme_weight_meaning($submitted);

        foreach ($options as $value => $label) {
            if (
                theme_weight_meaning($value)
                    === $submittedMeaning
                || theme_weight_meaning($label)
                    === $submittedMeaning
            ) {
                return (string)$value;
            }
        }
    }

    throw new RuntimeException(
        'Select a valid option.'
    );
}

function theme_normalise_submitted_value(
    string $key,
    mixed $value,
    array $definition,
    mixed $fallback = null
): string {
    if (
        (string)($definition['type'] ?? '')
        === 'number'
        && trim((string)$value) === ''
    ) {
        $value = $fallback
            ?? $definition['light']
            ?? $definition['dark']
            ?? '0';
    }

    if (
        (string)($definition['type'] ?? '')
        === 'select'
    ) {
        return theme_resolve_select_value(
            $key,
            $value,
            $definition
        );
    }

    return normalize_admin_theme_setting_value(
        $definition,
        $value
    );
}

try {
    if ($action === 'save') {
        require_permission(
            $pdo,
            'theme_settings',
            'can_edit'
        );

        $submitted = json_decode(
            (string)request_value('settings_json', '{}'),
            true
        );

        if (!is_array($submitted)) {
            throw new RuntimeException(
                'Invalid theme settings data.'
            );
        }

        if (!$submitted) {
            json_response(
                true,
                'No changed theme settings to save.',
                ['updated' => 0]
            );
        }

        $stmt = $pdo->prepare(
            "INSERT INTO admin_theme_settings
            (
                setting_group,
                setting_key,
                setting_label,
                light_value,
                dark_value,
                data_type,
                sort_order,
                is_active,
                updated_by
            )
            VALUES
            (
                :setting_group,
                :setting_key,
                :setting_label,
                :light_value,
                :dark_value,
                :data_type,
                :sort_order,
                1,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                setting_group = VALUES(setting_group),
                setting_label = VALUES(setting_label),
                light_value = VALUES(light_value),
                dark_value = VALUES(dark_value),
                data_type = VALUES(data_type),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_by = VALUES(updated_by)"
        );

        $pdo->beginTransaction();
        $updated = 0;

        foreach ($submitted as $key => $values) {
            if (!isset($definitions[$key])) {
                continue;
            }

            $definition = theme_definition_for_save(
                $key,
                $definitions[$key]
            );
            $definition['key'] = $key;

            $lightRaw = is_array($values)
                ? ($values['light'] ?? '')
                : $values;

            $darkRaw = is_array($values)
                ? ($values['dark'] ?? $lightRaw)
                : $values;

            try {
                $light = theme_normalise_submitted_value(
                    $key,
                    $lightRaw,
                    $definition,
                    $definition['light']
                        ?? '0'
                );

                $dark = theme_normalise_submitted_value(
                    $key,
                    $darkRaw,
                    $definition,
                    $definition['dark']
                        ?? $definition['light']
                        ?? '0'
                );
            } catch (RuntimeException $exception) {
                throw new RuntimeException(
                    $definition['label'] . ': '
                    . $exception->getMessage()
                );
            }

            $stmt->execute([
                'setting_group' => $definition['group'],
                'setting_key' => $key,
                'setting_label' => $definition['label'],
                'light_value' => $light,
                'dark_value' => $dark,
                'data_type' => theme_database_type(
                    (string)$definition['type']
                ),
                'sort_order' => (int)$definition['sort_order'],
                'updated_by' => current_admin_id(),
            ]);

            $updated++;
        }

        $pdo->commit();

        activity_log(
            $pdo,
            'update',
            'Theme Settings',
            'admin_theme_settings',
            null,
            $updated . ' admin theme settings updated.'
        );

        json_response(
            true,
            $updated === 1
                ? '1 theme setting saved successfully.'
                : $updated . ' theme settings saved successfully.',
            ['updated' => $updated]
        );
    }

    if ($action === 'reset_defaults') {
        require_permission(
            $pdo,
            'theme_settings',
            'can_edit'
        );

        $stmt = $pdo->prepare(
            "INSERT INTO admin_theme_settings
            (
                setting_group,
                setting_key,
                setting_label,
                light_value,
                dark_value,
                data_type,
                sort_order,
                is_active,
                updated_by
            )
            VALUES
            (
                :setting_group,
                :setting_key,
                :setting_label,
                :light_value,
                :dark_value,
                :data_type,
                :sort_order,
                1,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                setting_group = VALUES(setting_group),
                setting_label = VALUES(setting_label),
                light_value = VALUES(light_value),
                dark_value = VALUES(dark_value),
                data_type = VALUES(data_type),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_by = VALUES(updated_by)"
        );

        $pdo->beginTransaction();

        foreach ($definitions as $key => $definition) {
            $definition = theme_definition_for_save(
                $key,
                $definition
            );
            $definition['key'] = $key;

            $lightValue =
                theme_normalise_submitted_value(
                    $key,
                    $definition['light'],
                    $definition,
                    $definition['light']
                        ?? '0'
                );

            $darkValue =
                theme_normalise_submitted_value(
                    $key,
                    $definition['dark'],
                    $definition,
                    $definition['dark']
                        ?? $definition['light']
                        ?? '0'
                );

            $stmt->execute([
                'setting_group' => $definition['group'],
                'setting_key' => $key,
                'setting_label' => $definition['label'],
                'light_value' => $lightValue,
                'dark_value' => $darkValue,
                'data_type' => theme_database_type(
                    (string)$definition['type']
                ),
                'sort_order' => (int)$definition['sort_order'],
                'updated_by' => current_admin_id(),
            ]);
        }

        $pdo->commit();

        activity_log(
            $pdo,
            'reset',
            'Theme Settings',
            'admin_theme_settings',
            null,
            'Professional Admin Theme defaults restored.'
        );

        json_response(
            true,
            'Default Ramki Cards theme restored successfully.'
        );
    }

    throw new RuntimeException('Invalid theme action.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Theme settings API failed: '
        . $exception->getMessage()
    );

    json_response(
        false,
        $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Unable to process the theme settings.',
        null,
        422
    );
}
