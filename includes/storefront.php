<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('RAMKI_SESSION');
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/db.php';

function sf_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sf_public_settings(PDO $pdo): array
{
    static $settings = null;

    if (is_array($settings)) {
        return $settings;
    }

    $settings = [];

    $stmt = $pdo->query(
        "SELECT setting_key, setting_value
         FROM site_settings
         WHERE is_public = 1"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[(string)$row['setting_key']] =
            (string)($row['setting_value'] ?? '');
    }

    return $settings;
}

function sf_setting(
    PDO $pdo,
    string $key,
    string $default = ''
): string {
    $settings = sf_public_settings($pdo);
    $value = trim((string)($settings[$key] ?? ''));

    return $value !== '' ? $value : $default;
}

function sf_section(
    PDO $pdo,
    string $sectionKey,
    string $pageSlug = 'home'
): array {
    static $cache = [];

    $cacheKey = $pageSlug . ':' . $sectionKey;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM site_sections
         WHERE page_slug = :page_slug
           AND section_key = :section_key
           AND status = 'active'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );

    $stmt->execute([
        'page_slug' => $pageSlug,
        'section_key' => $sectionKey,
    ]);

    return $cache[$cacheKey] =
        ($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
}

function sf_section_items(
    PDO $pdo,
    string $sectionKey,
    string $pageSlug = 'home'
): array {
    $stmt = $pdo->prepare(
        "SELECT i.*
         FROM site_section_items i
         WHERE i.section_id = (
             SELECT s.id
             FROM site_sections s
             WHERE s.page_slug = :page_slug
               AND s.section_key = :section_key
               AND s.status = 'active'
             ORDER BY s.updated_at DESC, s.id DESC
             LIMIT 1
         )
           AND i.status = 'active'
         ORDER BY i.sort_order, i.id"
    );

    $stmt->execute([
        'page_slug' => $pageSlug,
        'section_key' => $sectionKey,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sf_active_categories(PDO $pdo, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));

    return $pdo->query(
        "SELECT id, category_name, slug, description, image_path
         FROM categories
         WHERE status = 'active'
           AND deleted_at IS NULL
         ORDER BY sort_order, category_name
         LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function sf_featured_products(PDO $pdo, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));

    return $pdo->query(
        "SELECT
            p.*,
            c.category_name,
            c.slug AS category_slug
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'active'
           AND p.deleted_at IS NULL
           AND c.status = 'active'
           AND c.deleted_at IS NULL
           AND p.is_featured = 1
         ORDER BY p.updated_at DESC, p.id DESC
         LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function sf_effective_price(array $product): float
{
    $base = max(0, (float)($product['base_price'] ?? 0));
    $offer = $product['offer_price'] ?? null;

    if ($offer === null || $offer === '') {
        return $base;
    }

    $offerPrice = (float)$offer;

    if ($offerPrice < 0 || $offerPrice > $base) {
        return $base;
    }

    $now = time();
    $start = !empty($product['offer_start_at'])
        ? strtotime((string)$product['offer_start_at'])
        : false;
    $end = !empty($product['offer_end_at'])
        ? strtotime((string)$product['offer_end_at'])
        : false;

    if ($start !== false && $now < $start) {
        return $base;
    }

    if ($end !== false && $now > $end) {
        return $base;
    }

    return $offerPrice;
}

function sf_purchase_mode(PDO $pdo, array $product = []): string
{
    $productMode = (string)($product['purchase_action'] ?? 'inherit');

    if (in_array($productMode, ['checkout', 'enquiry', 'both'], true)) {
        return $productMode;
    }

    $global = sf_setting($pdo, 'purchase_mode', 'both');

    return in_array($global, ['checkout', 'enquiry', 'both'], true)
        ? $global
        : 'both';
}

function sf_money(float|int|string $amount): string
{
    return '₹' . number_format((float)$amount, 2);
}

function sf_phone_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?: '';
}

function sf_whatsapp_url(string $phone, string $message = ''): string
{
    $digits = sf_phone_digits($phone);

    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }

    $url = 'https://wa.me/' . $digits;

    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}

function sf_media_path(?string $path, string $fallback): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_contains($path, '..')) {
        return $fallback;
    }

    return ltrim($path, '/');
}

function sf_csrf_token(): string
{
    if (empty($_SESSION['sf_csrf_token'])) {
        $_SESSION['sf_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['sf_csrf_token'];
}

function sf_verify_csrf(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals(sf_csrf_token(), $token);
}

function sf_wants_json(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith =
        strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest';
}

function sf_json(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}

function sf_next_number(
    PDO $pdo,
    string $sequenceKey,
    string $prefix
): string {
    $year = (int)date('Y');
    $month = (int)date('n');

    $stmt = $pdo->prepare(
        "INSERT INTO document_sequences
            (sequence_key, sequence_year, sequence_month, current_value)
         VALUES
            (:sequence_key, :sequence_year, :sequence_month, 1)
         ON DUPLICATE KEY UPDATE
            current_value = current_value + 1"
    );

    $stmt->execute([
        'sequence_key' => $sequenceKey,
        'sequence_year' => $year,
        'sequence_month' => $month,
    ]);

    $stmt = $pdo->prepare(
        "SELECT current_value
         FROM document_sequences
         WHERE sequence_key = :sequence_key
           AND sequence_year = :sequence_year
           AND sequence_month = :sequence_month
         FOR UPDATE"
    );

    $stmt->execute([
        'sequence_key' => $sequenceKey,
        'sequence_year' => $year,
        'sequence_month' => $month,
    ]);

    $value = (int)$stmt->fetchColumn();

    return sprintf(
        '%s-%s-%04d',
        $prefix,
        date('Ymd'),
        $value
    );
}

function sf_find_or_create_customer(
    PDO $pdo,
    string $name,
    string $phone,
    ?string $email
): int {
    $phone = sf_phone_digits($phone);
    $email = trim((string)$email);

    $stmt = $pdo->prepare(
        "SELECT id
         FROM customers
         WHERE phone = :phone
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute(['phone' => $phone]);
    $customerId = (int)$stmt->fetchColumn();

    if ($customerId > 0) {
        $update = $pdo->prepare(
            "UPDATE customers
             SET first_name = :first_name,
                 email = COALESCE(NULLIF(:email_value, ''), email),
                 status = CASE
                    WHEN status = 'inactive' THEN 'active'
                    ELSE status
                 END
             WHERE id = :id"
        );

        $update->execute([
            'first_name' => $name,
            'email_value' => $email,
            'id' => $customerId,
        ]);

        return $customerId;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO customers
            (first_name, last_name, email, phone, status)
         VALUES
            (:first_name, NULL, :email, :phone, 'active')"
    );

    $stmt->execute([
        'first_name' => $name,
        'email' => $email !== '' ? $email : null,
        'phone' => $phone,
    ]);

    return (int)$pdo->lastInsertId();
}

function sf_cart_token(): string
{
    if (empty($_SESSION['sf_cart_token'])) {
        $_SESSION['sf_cart_token'] = bin2hex(random_bytes(24));
    }

    return (string)$_SESSION['sf_cart_token'];
}

function sf_active_cart_id(PDO $pdo, bool $create = false): int
{
    /*
    |--------------------------------------------------------------------------
    | Logged-in customer cart
    |--------------------------------------------------------------------------
    | Cart ownership is based on customer_id after login. Guests may browse
    | the website, but they cannot create or modify a shopping cart.
    |--------------------------------------------------------------------------
    */
    if (sf_customer_logged_in()) {
        $customerId = sf_customer_id();

        $stmt = $pdo->prepare(
            "SELECT id
             FROM carts
             WHERE customer_id = :customer_id
               AND status = 'active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        $cartId = (int)$stmt->fetchColumn();

        if ($cartId > 0 || !$create) {
            return $cartId;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO carts
                (
                    customer_id,
                    session_token,
                    status,
                    expires_at
                )
             VALUES
                (
                    :customer_id,
                    NULL,
                    'active',
                    DATE_ADD(NOW(), INTERVAL 30 DAY)
                )"
        );

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | Administrator storefront test cart
    |--------------------------------------------------------------------------
    | Administrators do not have a customer_id. Give an authenticated admin a
    | browser-session cart so the product, cart and checkout flow can be tested
    | without creating a customer login. Public guests remain blocked.
    |--------------------------------------------------------------------------
    */
    if (sf_admin_logged_in()) {
        $token = sf_cart_token();

        $stmt = $pdo->prepare(
            "SELECT id
             FROM carts
             WHERE session_token = :session_token
               AND customer_id IS NULL
               AND status = 'active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );

        $stmt->execute([
            'session_token' => $token,
        ]);

        $cartId = (int)$stmt->fetchColumn();

        if ($cartId > 0 || !$create) {
            return $cartId;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO carts
                (
                    customer_id,
                    session_token,
                    status,
                    expires_at
                )
             VALUES
                (
                    NULL,
                    :session_token,
                    'active',
                    DATE_ADD(NOW(), INTERVAL 1 DAY)
                )"
        );

        $stmt->execute([
            'session_token' => $token,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /*
     * Public guests do not receive a cart in the login-only purchase flow.
     */
    return 0;
}

function sf_cart_count(PDO $pdo): int
{
    $cartId = sf_active_cart_id($pdo, false);

    if ($cartId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM cart_items
         WHERE cart_id = :cart_id"
    );

    $stmt->execute(['cart_id' => $cartId]);

    return (int)$stmt->fetchColumn();
}

function sf_get_product(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.*, c.category_name, c.slug AS category_slug
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id
           AND p.status = 'active'
           AND p.deleted_at IS NULL
           AND c.status = 'active'
           AND c.deleted_at IS NULL
         LIMIT 1"
    );

    $stmt->execute(['id' => $productId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function sf_variant(
    PDO $pdo,
    string $table,
    int $variantId,
    int $productId
): array {
    if (!in_array(
        $table,
        ['product_color_variants', 'product_design_variants'],
        true
    )) {
        return [];
    }

    if ($variantId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM {$table}
         WHERE id = :id
           AND product_id = :product_id
           AND status = 'active'
         LIMIT 1"
    );

    $stmt->execute([
        'id' => $variantId,
        'product_id' => $productId,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}


function sf_quick_add_configuration(
    PDO $pdo,
    array $product
): array {
    $productId = (int)($product['id'] ?? 0);

    if ($productId <= 0) {
        return [
            'available' => false,
            'color_variant_id' => 0,
            'design_variant_id' => 0,
        ];
    }

    $colorId = 0;
    $designId = 0;

    if ((int)($product['has_color_variants'] ?? 0) === 1) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM product_color_variants
             WHERE product_id = :product_id
               AND status = 'active'
             ORDER BY sort_order, id
             LIMIT 1"
        );
        $stmt->execute(['product_id' => $productId]);
        $colorId = (int)$stmt->fetchColumn();

        if ($colorId <= 0) {
            return [
                'available' => false,
                'color_variant_id' => 0,
                'design_variant_id' => 0,
            ];
        }
    }

    if ((int)($product['has_design_variants'] ?? 0) === 1) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM product_design_variants
             WHERE product_id = :product_id
               AND status = 'active'
             ORDER BY sort_order, id
             LIMIT 1"
        );
        $stmt->execute(['product_id' => $productId]);
        $designId = (int)$stmt->fetchColumn();

        if ($designId <= 0) {
            return [
                'available' => false,
                'color_variant_id' => 0,
                'design_variant_id' => 0,
            ];
        }
    }

    return [
        'available' => true,
        'color_variant_id' => $colorId,
        'design_variant_id' => $designId,
    ];
}

function sf_product_can_quick_add(array $product): bool
{
    $minimum = max(
        1,
        (int)($product['minimum_order_qty'] ?? 1)
    );

    return (int)($product['manage_stock'] ?? 0) !== 1
        || (int)($product['stock_quantity'] ?? 0) >= $minimum;
}

function sf_cart_snapshot(PDO $pdo): array
{
    $cartId = sf_active_cart_id($pdo, false);

    if ($cartId <= 0) {
        return [
            'cart_id' => 0,
            'items' => [],
            'item_count' => 0,
            'quantity_count' => 0,
            'subtotal' => 0.0,
            'subtotal_formatted' => sf_money(0),
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT
            ci.id,
            ci.product_id,
            ci.color_variant_id,
            ci.design_variant_id,
            ci.quantity,
            ci.unit_price_snapshot,
            ci.customer_item_notes,
            p.product_name,
            p.product_name_tamil,
            p.slug,
            p.thumbnail_path,
            p.minimum_order_qty,
            p.quantity_step,
            p.manage_stock,
            p.stock_quantity,
            cv.color_name,
            dv.design_name
         FROM cart_items ci
         INNER JOIN products p
            ON p.id = ci.product_id
         LEFT JOIN product_color_variants cv
            ON cv.id = ci.color_variant_id
         LEFT JOIN product_design_variants dv
            ON dv.id = ci.design_variant_id
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.updated_at DESC, ci.id DESC"
    );

    $stmt->execute(['cart_id' => $cartId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $subtotal = 0.0;
    $quantityCount = 0;

    foreach ($rows as $row) {
        $quantity = (int)$row['quantity'];
        $unitPrice = (float)$row['unit_price_snapshot'];
        $lineTotal = $quantity * $unitPrice;

        $subtotal += $lineTotal;
        $quantityCount += $quantity;

        $items[] = [
            'id' => (int)$row['id'],
            'product_id' => (int)$row['product_id'],
            'product_name' => (string)$row['product_name'],
            'product_name_tamil' =>
                (string)($row['product_name_tamil'] ?? ''),
            'slug' => (string)$row['slug'],
            'image' => sf_media_path(
                $row['thumbnail_path'] ?? '',
                'banner.png'
            ),
            'color_name' => (string)($row['color_name'] ?? ''),
            'design_name' => (string)($row['design_name'] ?? ''),
            'quantity' => $quantity,
            'minimum_order_qty' => max(
                1,
                (int)$row['minimum_order_qty']
            ),
            'quantity_step' => max(
                1,
                (int)$row['quantity_step']
            ),
            'maximum_quantity' =>
                (int)$row['manage_stock'] === 1
                    ? max(0, (int)$row['stock_quantity'])
                    : null,
            'unit_price' => $unitPrice,
            'unit_price_formatted' => sf_money($unitPrice),
            'line_total' => $lineTotal,
            'line_total_formatted' => sf_money($lineTotal),
            'notes' => (string)($row['customer_item_notes'] ?? ''),
        ];
    }

    return [
        'cart_id' => $cartId,
        'items' => $items,
        'item_count' => count($items),
        'quantity_count' => $quantityCount,
        'subtotal' => $subtotal,
        'subtotal_formatted' => sf_money($subtotal),
    ];
}

function sf_valid_quantity(array $product, int $quantity): bool
{
    $minimum = max(1, (int)($product['minimum_order_qty'] ?? 1));
    $step = max(1, (int)($product['quantity_step'] ?? 1));

    return $quantity >= $minimum
        && (($quantity - $minimum) % $step === 0);
}

function sf_send_mail(
    PDO $pdo,
    string $to,
    string $subject,
    string $message,
    ?string $replyTo = null
): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromName = sf_setting($pdo, 'from_name', 'Ramki Cards');
    $fromEmail = sf_setting(
        $pdo,
        'from_email',
        'info@ramkicards.com'
    );

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'info@ramkicards.com';
    }

    $safeName = str_replace(["\r", "\n"], '', $fromName);
    $safeSubject = str_replace(["\r", "\n"], '', $subject);

    $headers = "From: {$safeName} <{$fromEmail}>\r\n";
    $headers .= 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }

    return @mail($to, $safeSubject, $message, $headers);
}

/*
|--------------------------------------------------------------------------
| Unified storefront authentication helpers
|--------------------------------------------------------------------------
| Loading the authentication functions here makes them available to every
| public page that already includes storefront.php.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/storefront-auth.php';

