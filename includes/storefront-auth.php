<?php
declare(strict_types=1);

function sf_admin_session(): array
{
    return is_array($_SESSION['ramki_admin'] ?? null)
        ? $_SESSION['ramki_admin']
        : [];
}

function sf_customer_session(): array
{
    return is_array($_SESSION['ramki_customer'] ?? null)
        ? $_SESSION['ramki_customer']
        : [];
}

function sf_admin_logged_in(): bool
{
    return (int)(sf_admin_session()['id'] ?? 0) > 0;
}

function sf_customer_logged_in(): bool
{
    return (int)(sf_customer_session()['id'] ?? 0) > 0;
}

function sf_customer_id(): int
{
    return (int)(sf_customer_session()['id'] ?? 0);
}

function sf_customer_name(): string
{
    $name = trim((string)(sf_customer_session()['name'] ?? 'Customer'));

    return $name !== '' ? $name : 'Customer';
}

function sf_safe_return_url(string $return, string $default): string
{
    $return = trim($return);

    if (
        $return === ''
        || str_contains($return, "\r")
        || str_contains($return, "\n")
        || str_contains($return, '..')
        || str_starts_with($return, '//')
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $return)
    ) {
        return $default;
    }

    return ltrim($return, '/');
}

function sf_current_return_url(
    string $default = 'index.php'
): string {
    $script = basename(
        (string)(
            $_SERVER['SCRIPT_NAME']
            ?? $default
        )
    );

    if ($script === '' || $script === '.') {
        $script = $default;
    }

    $query = trim(
        (string)(
            $_SERVER['QUERY_STRING']
            ?? ''
        )
    );

    $return = $script;

    if ($query !== '') {
        $return .= '?' . $query;
    }

    return sf_safe_return_url(
        $return,
        $default
    );
}

function sf_login_required_url(
    string $return = 'index.php',
    string $loginUrl = 'login.php'
): string {
    $safeReturn = sf_safe_return_url(
        $return,
        'index.php'
    );

    return $loginUrl
        . '?'
        . http_build_query(
            [
                'return' => $safeReturn,
                'reason' => 'login_required',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
}

function sf_customer_action_url(
    string $target,
    string $loginUrl = 'login.php'
): string {
    return sf_customer_logged_in()
        ? $target
        : sf_login_required_url(
            $target,
            $loginUrl
        );
}

function sf_require_customer_login(
    string $loginUrl = 'login.php',
    string $return = 'my-account.php'
): void {
    if (sf_customer_logged_in()) {
        return;
    }

    header(
        'Location: '
        . sf_login_required_url(
            $return,
            $loginUrl
        )
    );
    exit;
}

function sf_login_admin(
    PDO $pdo,
    string $identifier,
    string $password
): array {
    $identifier = trim($identifier);
    $phone = sf_phone_digits($identifier);

    $stmt = $pdo->prepare(
        "SELECT
            au.id,
            au.role_id,
            au.name,
            au.email,
            au.phone,
            au.password_hash,
            au.status,
            ar.role_code,
            ar.role_name,
            ar.status AS role_status
         FROM admin_users au
         INNER JOIN admin_roles ar ON ar.id = au.role_id
         WHERE (
             LOWER(au.email) = LOWER(:identifier)
             OR au.phone = :phone
         )
         LIMIT 1"
    );

    $stmt->execute([
        'identifier' => $identifier,
        'phone' => $phone,
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (
        !$admin
        || $admin['status'] !== 'active'
        || $admin['role_status'] !== 'active'
        || !password_verify($password, (string)$admin['password_hash'])
    ) {
        return [];
    }

    session_regenerate_id(true);

    $_SESSION['ramki_admin'] = [
        'id' => (int)$admin['id'],
        'role_id' => (int)$admin['role_id'],
        'role_code' => (string)$admin['role_code'],
        'role_name' => (string)$admin['role_name'],
        'name' => (string)$admin['name'],
        'email' => (string)$admin['email'],
        'phone' => (string)($admin['phone'] ?? ''),
    ];

    unset($_SESSION['ramki_customer']);

    $pdo->prepare(
        "UPDATE admin_users
         SET last_login_at = NOW(),
             last_login_ip = :ip
         WHERE id = :id"
    )->execute([
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'id' => (int)$admin['id'],
    ]);

    try {
        $pdo->prepare(
            "INSERT INTO activity_logs
            (
                admin_user_id,
                action,
                module_name,
                entity_type,
                entity_id,
                description,
                route_name,
                request_method,
                ip_address,
                user_agent
            )
            VALUES
            (
                :admin_user_id,
                'login',
                'Authentication',
                'admin_user',
                :entity_id,
                'Administrator logged in through unified login.',
                'login.php',
                :request_method,
                :ip_address,
                :user_agent
            )"
        )->execute([
            'admin_user_id' => (int)$admin['id'],
            'entity_id' => (int)$admin['id'],
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } catch (Throwable $ignored) {
        error_log('Admin login activity log failed: ' . $ignored->getMessage());
    }

    return $_SESSION['ramki_admin'];
}

function sf_login_customer(
    PDO $pdo,
    string $identifier,
    string $password
): array {
    $identifier = trim($identifier);
    $phone = sf_phone_digits($identifier);

    $stmt = $pdo->prepare(
        "SELECT
            id,
            first_name,
            last_name,
            email,
            phone,
            password_hash,
            status
         FROM customers
         WHERE (
             LOWER(COALESCE(email, '')) = LOWER(:identifier)
             OR phone = :phone
         )
           AND password_hash IS NOT NULL
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([
        'identifier' => $identifier,
        'phone' => $phone,
    ]);

    $customer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (
        !$customer
        || $customer['status'] !== 'active'
        || !password_verify($password, (string)$customer['password_hash'])
    ) {
        return [];
    }

    session_regenerate_id(true);

    $fullName = trim(
        (string)$customer['first_name']
        . ' '
        . (string)($customer['last_name'] ?? '')
    );

    $_SESSION['ramki_customer'] = [
        'id' => (int)$customer['id'],
        'name' => $fullName !== '' ? $fullName : 'Customer',
        'first_name' => (string)$customer['first_name'],
        'last_name' => (string)($customer['last_name'] ?? ''),
        'email' => (string)($customer['email'] ?? ''),
        'phone' => (string)$customer['phone'],
    ];

    unset($_SESSION['ramki_admin']);

    $pdo->prepare(
        "UPDATE customers
         SET last_login_at = NOW()
         WHERE id = :id"
    )->execute(['id' => (int)$customer['id']]);

    sf_merge_guest_cart($pdo, (int)$customer['id']);
    sf_log_customer_login($pdo, (int)$customer['id'], true, $identifier);

    return $_SESSION['ramki_customer'];
}

function sf_log_customer_login(
    PDO $pdo,
    ?int $customerId,
    bool $success,
    string $identifier
): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO customer_login_logs
            (
                customer_id,
                identifier_attempted,
                ip_address,
                user_agent,
                success,
                created_at
            )
            VALUES
            (
                :customer_id,
                :identifier_attempted,
                :ip_address,
                :user_agent,
                :success,
                NOW()
            )"
        );

        $stmt->execute([
            'customer_id' => $customerId,
            'identifier_attempted' => mb_substr($identifier, 0, 190),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'success' => $success ? 1 : 0,
        ]);
    } catch (Throwable $ignored) {
        error_log('Customer login log failed: ' . $ignored->getMessage());
    }
}

function sf_normalize_cart_quantity(array $product, int $quantity): int
{
    $minimum = max(1, (int)($product['minimum_order_qty'] ?? 1));
    $step = max(1, (int)($product['quantity_step'] ?? 1));
    $quantity = max($minimum, $quantity);

    $remainder = ($quantity - $minimum) % $step;

    if ($remainder !== 0) {
        $quantity += $step - $remainder;
    }

    if ((int)($product['manage_stock'] ?? 0) === 1) {
        $stock = max(0, (int)($product['stock_quantity'] ?? 0));

        if ($quantity > $stock) {
            $validStock = $stock < $minimum
                ? 0
                : $minimum + intdiv($stock - $minimum, $step) * $step;

            return max(0, $validStock);
        }
    }

    return $quantity;
}

function sf_merge_guest_cart(PDO $pdo, int $customerId): void
{
    $token = trim((string)($_SESSION['sf_cart_token'] ?? ''));

    try {
        $pdo->beginTransaction();

        $customerCartStmt = $pdo->prepare(
            "SELECT id
             FROM carts
             WHERE customer_id = :customer_id
               AND status = 'active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $customerCartStmt->execute(['customer_id' => $customerId]);
        $customerCartId = (int)$customerCartStmt->fetchColumn();

        $guestCartId = 0;

        if ($token !== '') {
            $guestCartStmt = $pdo->prepare(
                "SELECT id
                 FROM carts
                 WHERE session_token = :session_token
                   AND status = 'active'
                 LIMIT 1
                 FOR UPDATE"
            );
            $guestCartStmt->execute(['session_token' => $token]);
            $guestCartId = (int)$guestCartStmt->fetchColumn();
        }

        if ($customerCartId <= 0 && $guestCartId > 0) {
            $pdo->prepare(
                "UPDATE carts
                 SET customer_id = :customer_id,
                     session_token = NULL,
                     expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
                 WHERE id = :id"
            )->execute([
                'customer_id' => $customerId,
                'id' => $guestCartId,
            ]);

            unset($_SESSION['sf_cart_token']);
            $pdo->commit();
            return;
        }

        if ($customerCartId <= 0) {
            $pdo->prepare(
                "INSERT INTO carts
                    (customer_id, session_token, status, expires_at)
                 VALUES
                    (:customer_id, NULL, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY))"
            )->execute(['customer_id' => $customerId]);

            $customerCartId = (int)$pdo->lastInsertId();
        }

        if ($guestCartId <= 0 || $guestCartId === $customerCartId) {
            unset($_SESSION['sf_cart_token']);
            $pdo->commit();
            return;
        }

        $itemsStmt = $pdo->prepare(
            "SELECT *
             FROM cart_items
             WHERE cart_id = :cart_id
             ORDER BY id
             FOR UPDATE"
        );
        $itemsStmt->execute(['cart_id' => $guestCartId]);
        $guestItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $findExisting = $pdo->prepare(
            "SELECT id, quantity
             FROM cart_items
             WHERE cart_id = :cart_id
               AND product_id = :product_id
               AND color_variant_id <=> :color_variant_id
               AND design_variant_id <=> :design_variant_id
             LIMIT 1
             FOR UPDATE"
        );

        $updateExisting = $pdo->prepare(
            "UPDATE cart_items
             SET quantity = :quantity,
                 unit_price_snapshot = :unit_price_snapshot,
                 customer_item_notes = COALESCE(:customer_item_notes, customer_item_notes)
             WHERE id = :id"
        );

        $moveItem = $pdo->prepare(
            "UPDATE cart_items
             SET cart_id = :cart_id
             WHERE id = :id"
        );

        $deleteItem = $pdo->prepare(
            "DELETE FROM cart_items WHERE id = :id"
        );

        foreach ($guestItems as $item) {
            $product = sf_get_product($pdo, (int)$item['product_id']);

            if (!$product) {
                $deleteItem->execute(['id' => (int)$item['id']]);
                continue;
            }

            $findExisting->execute([
                'cart_id' => $customerCartId,
                'product_id' => (int)$item['product_id'],
                'color_variant_id' => $item['color_variant_id'],
                'design_variant_id' => $item['design_variant_id'],
            ]);

            $existing = $findExisting->fetch(PDO::FETCH_ASSOC) ?: [];

            if ($existing) {
                $combined = (int)$existing['quantity'] + (int)$item['quantity'];
                $validQuantity = sf_normalize_cart_quantity($product, $combined);

                if ($validQuantity > 0) {
                    $updateExisting->execute([
                        'quantity' => $validQuantity,
                        'unit_price_snapshot' => sf_effective_price($product),
                        'customer_item_notes' => $item['customer_item_notes'] ?: null,
                        'id' => (int)$existing['id'],
                    ]);
                }

                $deleteItem->execute(['id' => (int)$item['id']]);
            } else {
                $validQuantity = sf_normalize_cart_quantity(
                    $product,
                    (int)$item['quantity']
                );

                if ($validQuantity <= 0) {
                    $deleteItem->execute(['id' => (int)$item['id']]);
                    continue;
                }

                $pdo->prepare(
                    "UPDATE cart_items
                     SET cart_id = :cart_id,
                         quantity = :quantity,
                         unit_price_snapshot = :unit_price_snapshot
                     WHERE id = :id"
                )->execute([
                    'cart_id' => $customerCartId,
                    'quantity' => $validQuantity,
                    'unit_price_snapshot' => sf_effective_price($product),
                    'id' => (int)$item['id'],
                ]);
            }
        }

        $pdo->prepare(
            "UPDATE carts
             SET status = 'abandoned',
                 expires_at = NOW()
             WHERE id = :id"
        )->execute(['id' => $guestCartId]);

        unset($_SESSION['sf_cart_token']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Guest cart merge failed: ' . $exception->getMessage());
    }
}

function sf_is_favourite(PDO $pdo, int $productId): bool
{
    $customerId = sf_customer_id();

    if ($customerId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM customer_favourites
         WHERE customer_id = :customer_id
           AND product_id = :product_id
         LIMIT 1"
    );

    $stmt->execute([
        'customer_id' => $customerId,
        'product_id' => $productId,
    ]);

    return (bool)$stmt->fetchColumn();
}

function sf_account_media_url(?string $path, string $fallback = 'banner.png'): string
{
    $safe = sf_media_path($path, $fallback);

    if (preg_match('#^https?://#i', $safe)) {
        return $safe;
    }

    return ltrim($safe, '/');
}
