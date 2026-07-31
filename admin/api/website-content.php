<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Always return clean JSON
|--------------------------------------------------------------------------
| PHP notices/warnings printed before JSON make fetch().json() fail.
|--------------------------------------------------------------------------
*/
ob_start();
ini_set('display_errors', '0');

register_shutdown_function(
    static function (): void {
        $error = error_get_last();

        if (
            !$error
            || !in_array(
                $error['type'],
                [
                    E_ERROR,
                    E_PARSE,
                    E_CORE_ERROR,
                    E_COMPILE_ERROR,
                    E_USER_ERROR,
                ],
                true
            )
        ) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header(
                'Content-Type: application/json; charset=utf-8'
            );
        }

        echo json_encode(
            [
                'success' => false,
                'message' =>
                    'Website Content API error: '
                    . $error['message'],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );
    }
);

require_once dirname(__DIR__) . '/includes/api-bootstrap.php';

$allowedSections = [
    'hero' => 10,
    'top_strip' => 20,
    'hero_features' => 30,
    'categories' => 40,
    'featured_products' => 50,
    'services' => 60,
    'custom_design' => 70,
    'why_choose' => 80,
    'testimonials' => 90,
    'contact' => 100,
];

$allowedExtraKeys = [
    'hero' => [
        'heading_line_2',
        'secondary_button_text',
        'secondary_button_url',
    ],
    'contact' => [
        'info_title',
        'info_subtitle',
        'whatsapp_title',
        'whatsapp_text',
        'whatsapp_button_text',
        'whatsapp_message',
        'form_button_text',
        'success_title',
        'success_message',
    ],
];

function website_content_text(
    mixed $value,
    int $maximum = 255
): string {
    $value = trim((string)$value);

    if (mb_strlen($value) > $maximum) {
        throw new RuntimeException(
            'One of the submitted values is too long.'
        );
    }

    return $value;
}

function website_content_url(mixed $value): string
{
    $value = website_content_text(
        $value,
        255
    );

    if ($value === '') {
        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Allowed button paths
    |--------------------------------------------------------------------------
    | products.php
    | products.php?category=wedding-cards
    | ./products.php
    | ../products.php
    | /ramkicards/products.php
    | #contact
    | https://example.com/page
    | tel:+919999999999
    | mailto:info@example.com
    |--------------------------------------------------------------------------
    */

    if (
        preg_match(
            '#^(?:javascript|data|vbscript):#i',
            $value
        )
    ) {
        throw new RuntimeException(
            'Unsafe button URL is not allowed.'
        );
    }

    if (
        str_starts_with($value, '#')
        || str_starts_with($value, '/')
        || str_starts_with($value, './')
        || str_starts_with($value, '../')
        || preg_match(
            '#^(?:https?://|tel:|mailto:)#i',
            $value
        )
        || preg_match(
            '#^[a-zA-Z0-9][a-zA-Z0-9_./?=&%+#:@~-]*$#',
            $value
        )
    ) {
        return $value;
    }

    throw new RuntimeException(
        'Enter a valid page path such as products.php, #contact or an HTTPS URL.'
    );
}

function website_content_status(mixed $value): string
{
    return (string)$value === 'inactive'
        ? 'inactive'
        : 'active';
}

function website_content_section_id(
    PDO $pdo,
    string $sectionKey,
    int $defaultSort
): int {
    /*
     * Always use one canonical row. Older databases may not have the
     * unique index on page_slug + section_key, which allowed duplicate
     * Hero records to be created.
     */
    $stmt = $pdo->prepare(
        "SELECT id
         FROM site_sections
         WHERE page_slug = 'home'
           AND section_key = :section_key
         ORDER BY updated_at DESC, id DESC"
    );
    $stmt->execute([
        'section_key' => $sectionKey,
    ]);

    $ids = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    if ($ids) {
        $canonicalId = $ids[0];

        if (count($ids) > 1) {
            $duplicateIds = array_slice($ids, 1);
            $placeholders = implode(
                ',',
                array_fill(0, count($duplicateIds), '?')
            );

            $moveStmt = $pdo->prepare(
                "UPDATE site_section_items
                 SET section_id = ?
                 WHERE section_id IN ({$placeholders})"
            );
            $moveStmt->execute([
                $canonicalId,
                ...$duplicateIds,
            ]);

            $deleteStmt = $pdo->prepare(
                "DELETE FROM site_sections
                 WHERE id IN ({$placeholders})"
            );
            $deleteStmt->execute($duplicateIds);
        }

        return $canonicalId;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO site_sections
        (
            page_slug,
            section_key,
            sort_order,
            status,
            updated_by
        )
        VALUES
        (
            'home',
            :section_key,
            :sort_order,
            'active',
            :updated_by
        )"
    );
    $stmt->execute([
        'section_key' => $sectionKey,
        'sort_order' => $defaultSort,
        'updated_by' => current_admin_id(),
    ]);

    return (int)$pdo->lastInsertId();
}

$action = request_action();

try {
    if ($action === 'save_settings') {
        require_permission(
            $pdo,
            'website_content',
            'can_edit'
        );

        $values = [
            'company_name' => website_content_text(
                request_value('company_name'),
                150
            ),
            'phone_number' => website_content_text(
                request_value('phone_number'),
                30
            ),
            'secondary_phone_number' => website_content_text(
                request_value('secondary_phone_number'),
                30
            ),
            'whatsapp_number' => website_content_text(
                request_value('whatsapp_number'),
                30
            ),
            'email_address' => website_content_text(
                request_value('email_address'),
                190
            ),
            'address' => website_content_text(
                request_value('address'),
                1000
            ),
            'instagram_handle' => website_content_text(
                request_value('instagram_handle'),
                100
            ),
        ];

        if (
            $values['company_name'] === ''
            || $values['phone_number'] === ''
            || $values['whatsapp_number'] === ''
            || $values['email_address'] === ''
            || $values['address'] === ''
        ) {
            throw new RuntimeException(
                'Complete all required contact fields.'
            );
        }

        if (!filter_var(
            $values['email_address'],
            FILTER_VALIDATE_EMAIL
        )) {
            throw new RuntimeException(
                'Enter a valid email address.'
            );
        }

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
                'contact',
                :setting_key,
                :setting_value,
                'string',
                1,
                :updated_by
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                is_public = 1,
                updated_by = VALUES(updated_by)"
        );

        $pdo->beginTransaction();

        foreach ($values as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_by' => current_admin_id(),
            ]);
        }

        $pdo->commit();

        activity_log(
            $pdo,
            'update',
            'Website Content',
            'site_settings',
            null,
            'Public website contact information updated.',
            null,
            $values
        );

        json_response(
            true,
            'Website contact information saved successfully.'
        );
    }

    if ($action === 'save_section') {
        require_permission(
            $pdo,
            'website_content',
            'can_edit'
        );

        $sectionKey = website_content_text(
            request_value('section_key'),
            100
        );

        if (!array_key_exists(
            $sectionKey,
            $allowedSections
        )) {
            throw new RuntimeException(
                'Invalid website section.'
            );
        }

        $title = website_content_text(
            request_value('section_title'),
            255
        );

        $subtitle = website_content_text(
            request_value('section_subtitle'),
            255
        );

        $content = website_content_text(
            request_value('section_content'),
            10000
        );

        $buttonText = website_content_text(
            request_value('button_text'),
            100
        );

        $buttonUrl = website_content_url(
            request_value('button_url')
        );

        $sortOrder = max(
            0,
            min(
                999999,
                (int)request_value(
                    'sort_order',
                    $allowedSections[$sectionKey]
                )
            )
        );

        $status = website_content_status(
            request_value('status')
        );

        $extraInput =
            $_POST['extra']
            ?? [];

        $extra = [];

        if (is_array($extraInput)) {
            foreach (
                $allowedExtraKeys[$sectionKey]
                ?? []
                as $extraKey
            ) {
                if (!array_key_exists(
                    $extraKey,
                    $extraInput
                )) {
                    continue;
                }

                if (
                    str_ends_with(
                        $extraKey,
                        '_url'
                    )
                ) {
                    $extra[$extraKey] =
                        website_content_url(
                            $extraInput[$extraKey]
                        );
                } else {
                    $extra[$extraKey] =
                        website_content_text(
                            $extraInput[$extraKey],
                            in_array(
                                $extraKey,
                                [
                                    'whatsapp_message',
                                    'success_message',
                                ],
                                true
                            )
                                ? 2000
                                : 255
                        );
                }
            }
        }

        $pdo->beginTransaction();

        /*
         * Read the exact current row. This avoids relying on a duplicate-key
         * insert when only an UPDATE is required.
         */
        $currentStmt = $pdo->prepare(
            "SELECT *
             FROM site_sections
             WHERE page_slug = 'home'
               AND section_key = :section_key
             ORDER BY updated_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );

        $currentStmt->execute([
            'section_key' => $sectionKey,
        ]);

        $old = $currentStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;

        $backgroundImagePath =
            (string)(
                $old['background_image_path']
                ?? ''
            );

        if (
            isset($_FILES['background_image'])
            && (
                $_FILES['background_image']['error']
                ?? UPLOAD_ERR_NO_FILE
            ) !== UPLOAD_ERR_NO_FILE
        ) {
            $backgroundImagePath = upload_image(
                $_FILES['background_image'],
                'uploads/site-content'
            );
        }

        $existingExtra = json_decode(
            (string)(
                $old['additional_settings']
                ?? ''
            ),
            true
        );

        if (!is_array($existingExtra)) {
            $existingExtra = [];
        }

        $savedExtra = array_merge(
            $existingExtra,
            $extra
        );

        $additionalJson = $savedExtra
            ? json_encode(
                $savedExtra,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            )
            : null;

        if ($old) {
            $id = (int)$old['id'];

            $stmt = $pdo->prepare(
                "UPDATE site_sections
                 SET section_title = :section_title,
                     section_subtitle = :section_subtitle,
                     section_content = :section_content,
                     background_image_path =
                        :background_image_path,
                     button_text = :button_text,
                     button_url = :button_url,
                     additional_settings =
                        :additional_settings,
                     sort_order = :sort_order,
                     status = :status,
                     updated_by = :updated_by
                 WHERE id = :id"
            );

            $stmt->execute([
                'section_title' =>
                    $title !== ''
                        ? $title
                        : null,
                'section_subtitle' =>
                    $subtitle !== ''
                        ? $subtitle
                        : null,
                'section_content' =>
                    $content !== ''
                        ? $content
                        : null,
                'background_image_path' =>
                    $backgroundImagePath !== ''
                        ? $backgroundImagePath
                        : null,
                'button_text' =>
                    $buttonText !== ''
                        ? $buttonText
                        : null,
                'button_url' =>
                    $buttonUrl !== ''
                        ? $buttonUrl
                        : null,
                'additional_settings' =>
                    $additionalJson,
                'sort_order' => $sortOrder,
                'status' => $status,
                'updated_by' =>
                    current_admin_id(),
                'id' => $id,
            ]);

            $logAction = 'update';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO site_sections
                (
                    page_slug,
                    section_key,
                    section_title,
                    section_subtitle,
                    section_content,
                    background_image_path,
                    button_text,
                    button_url,
                    additional_settings,
                    sort_order,
                    status,
                    updated_by
                )
                VALUES
                (
                    'home',
                    :section_key,
                    :section_title,
                    :section_subtitle,
                    :section_content,
                    :background_image_path,
                    :button_text,
                    :button_url,
                    :additional_settings,
                    :sort_order,
                    :status,
                    :updated_by
                )"
            );

            $stmt->execute([
                'section_key' => $sectionKey,
                'section_title' =>
                    $title !== ''
                        ? $title
                        : null,
                'section_subtitle' =>
                    $subtitle !== ''
                        ? $subtitle
                        : null,
                'section_content' =>
                    $content !== ''
                        ? $content
                        : null,
                'background_image_path' =>
                    $backgroundImagePath !== ''
                        ? $backgroundImagePath
                        : null,
                'button_text' =>
                    $buttonText !== ''
                        ? $buttonText
                        : null,
                'button_url' =>
                    $buttonUrl !== ''
                        ? $buttonUrl
                        : null,
                'additional_settings' =>
                    $additionalJson,
                'sort_order' => $sortOrder,
                'status' => $status,
                'updated_by' =>
                    current_admin_id(),
            ]);

            $id = (int)$pdo->lastInsertId();
            $logAction = 'create';
        }

        $pdo->commit();

        activity_log(
            $pdo,
            $logAction,
            'Website Content',
            'site_section',
            $id,
            'Homepage section saved: '
                . $sectionKey,
            $old,
            [
                'section_key' => $sectionKey,
                'section_title' => $title,
                'section_subtitle' =>
                    $subtitle,
                'section_content' => $content,
                'button_text' => $buttonText,
                'button_url' => $buttonUrl,
                'additional_settings' =>
                    $savedExtra,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]
        );

        json_response(
            true,
            'Website section saved successfully.',
            [
                'section_id' => $id,
                'section_key' => $sectionKey,
                'button_url' => $buttonUrl,
            ]
        );
    }

    if ($action === 'save_item') {
        $id = (int)request_value('id', 0);

        require_permission(
            $pdo,
            'website_content',
            $id > 0 ? 'can_edit' : 'can_add'
        );

        $sectionKey = website_content_text(
            request_value('section_key'),
            100
        );

        if (!array_key_exists($sectionKey, $allowedSections)) {
            throw new RuntimeException(
                'Invalid website section.'
            );
        }

        $title = website_content_text(
            request_value('item_title'),
            255
        );
        $subtitle = website_content_text(
            request_value('item_subtitle'),
            255
        );
        $content = website_content_text(
            request_value('item_content'),
            5000
        );
        $icon = website_content_text(
            request_value('icon_class'),
            150
        );
        $linkText = website_content_text(
            request_value('link_text'),
            100
        );
        $linkUrl = website_content_url(
            request_value('link_url')
        );
        $sortOrder = max(
            0,
            min(
                999999,
                (int)request_value('sort_order', 0)
            )
        );
        $status = website_content_status(
            request_value('status')
        );

        if ($title === '') {
            throw new RuntimeException(
                'Enter an item title.'
            );
        }

        $rating = max(
            1,
            min(
                5,
                (int)request_value('rating', 5)
            )
        );

        $additionalData = $sectionKey === 'testimonials'
            ? ['rating' => $rating]
            : [];

        $sectionId = website_content_section_id(
            $pdo,
            $sectionKey,
            $allowedSections[$sectionKey]
        );

        $old = null;
        $imagePath = '';

        if ($id > 0) {
            $oldStmt = $pdo->prepare(
                "SELECT i.*
                 FROM site_section_items i
                 INNER JOIN site_sections s
                    ON s.id = i.section_id
                 WHERE i.id = :id
                   AND s.page_slug = 'home'
                 LIMIT 1"
            );
            $oldStmt->execute(['id' => $id]);
            $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new RuntimeException(
                    'Website section item not found.'
                );
            }

            $imagePath = (string)($old['image_path'] ?? '');
        }

        if (
            isset($_FILES['item_image'])
            && ($_FILES['item_image']['error'] ?? UPLOAD_ERR_NO_FILE)
                !== UPLOAD_ERR_NO_FILE
        ) {
            $imagePath = upload_image(
                $_FILES['item_image'],
                'uploads/site-content/items'
            );
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "UPDATE site_section_items
                 SET section_id = :section_id,
                     item_title = :item_title,
                     item_subtitle = :item_subtitle,
                     item_content = :item_content,
                     icon_class = :icon_class,
                     image_path = :image_path,
                     link_text = :link_text,
                     link_url = :link_url,
                     additional_data = :additional_data,
                     sort_order = :sort_order,
                     status = :status
                 WHERE id = :id"
            );

            $stmt->execute([
                'section_id' => $sectionId,
                'item_title' => $title,
                'item_subtitle' => $subtitle !== '' ? $subtitle : null,
                'item_content' => $content !== '' ? $content : null,
                'icon_class' => $icon !== '' ? $icon : null,
                'image_path' => $imagePath !== '' ? $imagePath : null,
                'link_text' => $linkText !== '' ? $linkText : null,
                'link_url' => $linkUrl !== '' ? $linkUrl : null,
                'additional_data' =>
                    $additionalData
                        ? json_encode(
                            $additionalData,
                            JSON_UNESCAPED_UNICODE
                        )
                        : null,
                'sort_order' => $sortOrder,
                'status' => $status,
                'id' => $id,
            ]);

            $message = 'Website item updated successfully.';
            $logAction = 'update';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO site_section_items
                (
                    section_id,
                    item_title,
                    item_subtitle,
                    item_content,
                    icon_class,
                    image_path,
                    link_text,
                    link_url,
                    additional_data,
                    sort_order,
                    status
                )
                VALUES
                (
                    :section_id,
                    :item_title,
                    :item_subtitle,
                    :item_content,
                    :icon_class,
                    :image_path,
                    :link_text,
                    :link_url,
                    :additional_data,
                    :sort_order,
                    :status
                )"
            );

            $stmt->execute([
                'section_id' => $sectionId,
                'item_title' => $title,
                'item_subtitle' => $subtitle !== '' ? $subtitle : null,
                'item_content' => $content !== '' ? $content : null,
                'icon_class' => $icon !== '' ? $icon : null,
                'image_path' => $imagePath !== '' ? $imagePath : null,
                'link_text' => $linkText !== '' ? $linkText : null,
                'link_url' => $linkUrl !== '' ? $linkUrl : null,
                'additional_data' =>
                    $additionalData
                        ? json_encode(
                            $additionalData,
                            JSON_UNESCAPED_UNICODE
                        )
                        : null,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);

            $id = (int)$pdo->lastInsertId();
            $message = 'Website item added successfully.';
            $logAction = 'create';
        }

        activity_log(
            $pdo,
            $logAction,
            'Website Content',
            'site_section_item',
            $id,
            $message,
            $old,
            [
                'section_key' => $sectionKey,
                'item_title' => $title,
                'item_subtitle' => $subtitle,
                'item_content' => $content,
                'icon_class' => $icon,
                'sort_order' => $sortOrder,
                'status' => $status,
                'additional_data' => $additionalData,
            ]
        );

        json_response(true, $message);
    }

    if ($action === 'delete_item') {
        require_permission(
            $pdo,
            'website_content',
            'can_delete'
        );

        $id = (int)request_value('id', 0);

        $stmt = $pdo->prepare(
            "SELECT
                i.*,
                s.section_key
             FROM site_section_items i
             INNER JOIN site_sections s
                ON s.id = i.section_id
             WHERE i.id = :id
               AND s.page_slug = 'home'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old) {
            throw new RuntimeException(
                'Website section item not found.'
            );
        }

        $pdo->prepare(
            "DELETE FROM site_section_items
             WHERE id = :id"
        )->execute(['id' => $id]);

        activity_log(
            $pdo,
            'delete',
            'Website Content',
            'site_section_item',
            $id,
            'Website section item deleted.',
            $old,
            null
        );

        json_response(
            true,
            'Website item deleted successfully.'
        );
    }

    throw new RuntimeException(
        'Invalid website-content action.'
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Website Content API failed: '
        . $exception->getMessage()
    );

    json_response(
        false,
        (
            $exception instanceof RuntimeException
            || in_array(
                $_SERVER['REMOTE_ADDR']
                    ?? '',
                ['127.0.0.1', '::1'],
                true
            )
        )
            ? $exception->getMessage()
            : 'Unable to process the website content.',
        null,
        422
    );
}
