<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

function product_enquiry_fail(
    array $product,
    string $message,
    int $status = 422
): never {
    $slug = rawurlencode((string)($product['slug'] ?? ''));

    if (sf_wants_json()) {
        sf_json(
            false,
            $message,
            [
                'product_url' =>
                    'product.php?slug='
                    . $slug
                    . '#enquiry',
            ],
            $status
        );
    }

    header(
        'Location: product.php?slug='
        . $slug
        . '&error='
        . rawurlencode($message)
        . '#enquiry'
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    if (sf_wants_json()) {
        sf_json(
            false,
            'Your page session expired. Refresh and try again.',
            [],
            419
        );
    }

    header(
        'Location: products.php?error='
        . rawurlencode('Page session expired.')
    );
    exit;
}

$productId = max(0, (int)($_POST['product_id'] ?? 0));
$product = sf_get_product($pdo, $productId);

if (!$product) {
    if (sf_wants_json()) {
        sf_json(false, 'Product not found.', [], 404);
    }

    header('Location: products.php');
    exit;
}

$mode = sf_purchase_mode($pdo, $product);

if (!in_array($mode, ['enquiry', 'both'], true)) {
    product_enquiry_fail(
        $product,
        'Product enquiry is not currently available.'
    );
}

$name = trim((string)($_POST['name'] ?? ''));
$mobile = sf_phone_digits((string)($_POST['mobile'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$quantity = max(0, (int)($_POST['quantity'] ?? 0));
$notes = trim((string)($_POST['notes'] ?? ''));
$colorId = max(0, (int)($_POST['color_variant_id'] ?? 0));
$designId = max(0, (int)($_POST['design_variant_id'] ?? 0));

if ($name === '' || mb_strlen($name) > 150) {
    product_enquiry_fail($product, 'Enter a valid name.');
}

if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    product_enquiry_fail(
        $product,
        'Enter a valid 10-digit mobile number.'
    );
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    product_enquiry_fail($product, 'Enter a valid email address.');
}

if (!sf_valid_quantity($product, $quantity)) {
    product_enquiry_fail(
        $product,
        'Quantity must follow the minimum order and quantity-step rules.'
    );
}

if ((int)$product['manage_stock'] === 1) {
    $stock = (int)($product['stock_quantity'] ?? 0);

    if ($quantity > $stock) {
        product_enquiry_fail(
            $product,
            'Requested quantity is currently unavailable.'
        );
    }
}

$color = sf_variant(
    $pdo,
    'product_color_variants',
    $colorId,
    $productId
);

$design = sf_variant(
    $pdo,
    'product_design_variants',
    $designId,
    $productId
);

if ((int)$product['has_color_variants'] === 1 && !$color) {
    product_enquiry_fail($product, 'Select a valid colour.');
}

if ((int)$product['has_design_variants'] === 1 && !$design) {
    product_enquiry_fail($product, 'Select a valid design.');
}

$unitPrice = sf_effective_price($product)
    + (float)($color['price_adjustment'] ?? 0)
    + (float)($design['price_adjustment'] ?? 0);

$lineTotal = $unitPrice * $quantity;

try {
    $pdo->beginTransaction();

    $customerId = sf_find_or_create_customer(
        $pdo,
        $name,
        $mobile,
        $email
    );

    $enquiryNumber = sf_next_number($pdo, 'enquiry', 'ENQ');

    $stmt = $pdo->prepare(
        "INSERT INTO enquiries
        (
            enquiry_number,
            customer_id,
            customer_name,
            customer_email,
            customer_phone,
            source,
            status,
            subject,
            message
        )
        VALUES
        (
            :enquiry_number,
            :customer_id,
            :customer_name,
            :customer_email,
            :customer_phone,
            'product',
            'new',
            :subject,
            :message
        )"
    );

    $stmt->execute([
        'enquiry_number' => $enquiryNumber,
        'customer_id' => $customerId,
        'customer_name' => $name,
        'customer_email' => $email !== '' ? $email : null,
        'customer_phone' => $mobile,
        'subject' => 'Product enquiry: ' . $product['product_name'],
        'message' => $notes !== '' ? $notes : null,
    ]);

    $enquiryId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO enquiry_items
        (
            enquiry_id,
            product_id,
            color_variant_id,
            design_variant_id,
            product_name_snapshot,
            sku_snapshot,
            thumbnail_snapshot,
            selected_color_name,
            selected_design_name,
            requested_quantity,
            minimum_qty_snapshot,
            quantity_step_snapshot,
            unit_price_snapshot,
            line_total_estimate,
            customer_item_notes
        )
        VALUES
        (
            :enquiry_id,
            :product_id,
            :color_variant_id,
            :design_variant_id,
            :product_name_snapshot,
            :sku_snapshot,
            :thumbnail_snapshot,
            :selected_color_name,
            :selected_design_name,
            :requested_quantity,
            :minimum_qty_snapshot,
            :quantity_step_snapshot,
            :unit_price_snapshot,
            :line_total_estimate,
            :customer_item_notes
        )"
    );

    $stmt->execute([
        'enquiry_id' => $enquiryId,
        'product_id' => $productId,
        'color_variant_id' => $color ? (int)$color['id'] : null,
        'design_variant_id' => $design ? (int)$design['id'] : null,
        'product_name_snapshot' => $product['product_name'],
        'sku_snapshot' => $product['sku'] ?: null,
        'thumbnail_snapshot' => $product['thumbnail_path'],
        'selected_color_name' => $color['color_name'] ?? null,
        'selected_design_name' => $design['design_name'] ?? null,
        'requested_quantity' => $quantity,
        'minimum_qty_snapshot' => (int)$product['minimum_order_qty'],
        'quantity_step_snapshot' => (int)$product['quantity_step'],
        'unit_price_snapshot' => $unitPrice,
        'line_total_estimate' => $lineTotal,
        'customer_item_notes' => $notes !== '' ? $notes : null,
    ]);

    $pdo->commit();

    $successMessage =
        'Your enquiry '
        . $enquiryNumber
        . ' has been received. WhatsApp will open with the details.';

    $companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');

    $whatsappNumber = sf_setting(
        $pdo,
        'whatsapp_number',
        sf_setting($pdo, 'phone_number', '96299 54411')
    );

    $scheme = (
        !empty($_SERVER['HTTPS'])
        && strtolower((string)$_SERVER['HTTPS']) !== 'off'
    )
        ? 'https'
        : 'http';

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

    $scriptDirectory = str_replace(
        '\\',
        '/',
        dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))
    );

    $scriptDirectory = $scriptDirectory === '/'
        ? ''
        : rtrim($scriptDirectory, '/');

    $productPath =
        $scriptDirectory
        . '/product.php?slug='
        . rawurlencode((string)$product['slug']);

    $productUrl = $host !== ''
        ? $scheme . '://' . $host . $productPath
        : $productPath;

    /*
     * Use plain text in the WhatsApp composer. Markdown-style asterisks are
     * intentionally avoided because some mobile/browser combinations show
     * them literally before the message is sent.
     */
    $whatsappLines = [
        $companyName . ' - Product Enquiry',
        '--------------------------------',
        '',
        'Enquiry No: ' . $enquiryNumber,
        'Customer: ' . $name,
        'Mobile: ' . $mobile,
    ];

    if ($email !== '') {
        $whatsappLines[] = 'Email: ' . $email;
    }

    $whatsappLines[] = '';
    $whatsappLines[] = 'Product Details';
    $whatsappLines[] = '---------------';
    $whatsappLines[] = 'Product: ' . $product['product_name'];

    if (!empty($color['color_name'])) {
        $whatsappLines[] =
            'Colour: ' . $color['color_name'];
    }

    if (!empty($design['design_name'])) {
        $whatsappLines[] =
            'Design: ' . $design['design_name'];
    }

    $whatsappLines[] = 'Quantity: ' . $quantity;
    $whatsappLines[] = 'Unit Price: ' . sf_money($unitPrice);
    $whatsappLines[] =
        'Estimated Total: ' . sf_money($lineTotal);

    if ($notes !== '') {
        $whatsappLines[] = '';
        $whatsappLines[] = 'Requirements:';
        $whatsappLines[] = $notes;
    }

    $whatsappLines[] = '';
    $whatsappLines[] = 'Product Link:';
    $whatsappLines[] = $productUrl;

    $whatsappUrl = sf_whatsapp_url(
        $whatsappNumber,
        implode("\n", $whatsappLines)
    );

    /*
    |--------------------------------------------------------------------------
    | AJAX response must be returned before email notification work
    |--------------------------------------------------------------------------
    | The enquiry has already been committed to the database. SMTP can be
    | slow or unavailable on local WAMP, which previously left the browser
    | waiting forever with the button text "Submitting...".
    |--------------------------------------------------------------------------
    */
    if (sf_wants_json()) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        sf_json(
            true,
            'Product enquiry submitted successfully.',
            [
                'enquiry_number' => $enquiryNumber,
                'message' => $successMessage,
                'whatsapp_url' => $whatsappUrl,
                'product_name' => $product['product_name'],
                'selected_color_name' =>
                    $color['color_name'] ?? '',
                'selected_design_name' =>
                    $design['design_name'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]
        );
    }

    $phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');

    $adminStmt = $pdo->prepare(
        "SELECT setting_value
         FROM site_settings
         WHERE setting_key = 'admin_notification_email'
         LIMIT 1"
    );
    $adminStmt->execute();

    $adminEmail = trim((string)$adminStmt->fetchColumn());

    if ($adminEmail === '') {
        $adminEmail = 'ariharasudhan1062003@gmail.com';
    }

    if ($adminEmail !== '') {
        sf_send_mail(
            $pdo,
            $adminEmail,
            'New Product Enquiry ' . $enquiryNumber,
            implode("\n", [
                'Enquiry: ' . $enquiryNumber,
                'Customer: ' . $name,
                'Mobile: ' . $mobile,
                'Product: ' . $product['product_name'],
                'Quantity: ' . $quantity,
                'Estimated total: ' . sf_money($lineTotal),
            ]),
            $email !== '' ? $email : null
        );
    }

    if ($email !== '') {
        sf_send_mail(
            $pdo,
            $email,
            'Your product enquiry ' . $enquiryNumber,
            implode("\n", [
                'Dear ' . $name . ',',
                '',
                'Thank you for contacting ' . $companyName . '.',
                'Your enquiry number is ' . $enquiryNumber . '.',
                'Product: ' . $product['product_name'],
                'Quantity: ' . $quantity,
                '',
                'Our team will contact you shortly.',
                'Phone: ' . $phoneNumber,
            ])
        );
    }

    $_SESSION['product_enquiry_toast'] = [
        'number' => $enquiryNumber,
        'message' => $successMessage,
    ];

    /*
     * JavaScript-free fallback: after saving the enquiry, continue directly
     * to WhatsApp with the same validated details.
     */
    header('Location: ' . $whatsappUrl);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Product enquiry failed: ' . $e->getMessage());

    product_enquiry_fail(
        $product,
        'Unable to submit the product enquiry. Please try again.'
    );
}