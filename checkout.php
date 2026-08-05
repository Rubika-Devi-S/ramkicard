<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$checkoutCustomerLoggedIn = sf_customer_logged_in();
$checkoutAdminLoggedIn = sf_admin_logged_in();

if (!$checkoutCustomerLoggedIn && !$checkoutAdminLoggedIn) {
    header('Location: ' . sf_login_required_url('checkout.php'));
    exit;
}

$checkoutCustomer = $checkoutCustomerLoggedIn
    ? sf_customer_session()
    : [];

$checkoutAdmin = $checkoutAdminLoggedIn
    ? sf_admin_session()
    : [];

$checkoutDefaultName = $checkoutCustomerLoggedIn
    ? sf_customer_name()
    : trim((string)($checkoutAdmin['name'] ?? ''));

$checkoutAddress = [];

if ($checkoutCustomerLoggedIn) {
    $checkoutAddressStmt = $pdo->prepare(
        "SELECT *
         FROM customer_addresses
         WHERE customer_id = :customer_id
         ORDER BY is_default DESC, updated_at DESC, id DESC
         LIMIT 1"
    );

    $checkoutAddressStmt->execute([
        'customer_id' => sf_customer_id(),
    ]);

    $checkoutAddress =
        $checkoutAddressStmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$cartId = sf_active_cart_id($pdo, false);

if ($cartId <= 0) {
    header('Location: cart.php');
    exit;
}

function checkout_items(PDO $pdo, int $cartId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            ci.*,
            p.*,
            p.id AS product_id_value,
            cv.color_name,
            cv.price_adjustment AS color_adjustment,
            dv.design_name,
            dv.price_adjustment AS design_adjustment
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_color_variants cv
            ON cv.id = ci.color_variant_id
         LEFT JOIN product_design_variants dv
            ON dv.id = ci.design_variant_id
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.id
         FOR UPDATE"
    );

    $stmt->execute(['cart_id' => $cartId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$displayItems = [];
$stmt = $pdo->prepare(
    "SELECT
        ci.*,
        p.product_name,
        p.product_name_tamil,
        p.slug,
        p.thumbnail_path,
        p.minimum_order_qty,
        p.quantity_step,
        cv.color_name,
        dv.design_name
     FROM cart_items ci
     INNER JOIN products p ON p.id = ci.product_id
     LEFT JOIN product_color_variants cv
        ON cv.id = ci.color_variant_id
     LEFT JOIN product_design_variants dv
        ON dv.id = ci.design_variant_id
     WHERE ci.cart_id = :cart_id
     ORDER BY ci.id"
);
$stmt->execute(['cart_id' => $cartId]);
$displayItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$displayItems) {
    header('Location: cart.php');
    exit;
}

$displaySubtotal = 0.0;
$displayQuantityCount = 0;

foreach ($displayItems as $item) {
    $displayQuantity = (int)$item['quantity'];

    $displaySubtotal +=
        (float)$item['unit_price_snapshot']
        * $displayQuantity;

    $displayQuantityCount += $displayQuantity;
}

$displayShippingEnabled =
    sf_setting($pdo, 'shipping_enabled', '1') === '1';

$displayShippingAmount = $displayShippingEnabled
    ? max(
        0,
        (float)sf_setting(
            $pdo,
            'flat_shipping_amount',
            '0'
        )
    )
    : 0.0;

$displayTaxEnabled =
    sf_setting($pdo, 'tax_enabled', '0') === '1';

$displayTaxPercentage = $displayTaxEnabled
    ? max(
        0,
        (float)sf_setting(
            $pdo,
            'tax_percentage',
            '0'
        )
    )
    : 0.0;

$displayTaxAmount = round(
    $displaySubtotal * $displayTaxPercentage / 100,
    2
);

$displayGrandTotal =
    $displaySubtotal
    + $displayShippingAmount
    + $displayTaxAmount;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = sf_phone_digits((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address1 = trim((string)($_POST['address_line_1'] ?? ''));
        $address2 = trim((string)($_POST['address_line_2'] ?? ''));
        $landmark = trim((string)($_POST['landmark'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $state = trim((string)($_POST['state'] ?? 'Tamil Nadu'));
        $postalCode = trim((string)($_POST['postal_code'] ?? ''));
        $country = trim((string)($_POST['country'] ?? 'India'));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($name === '' || mb_strlen($name) > 150) {
            $error = 'Enter a valid customer name.';
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $error = 'Enter a valid 10-digit mobile number.';
        } elseif (
            $email !== ''
            && !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $error = 'Enter a valid email address.';
        } elseif (
            $address1 === ''
            || $city === ''
            || $state === ''
            || $postalCode === ''
            || $country === ''
        ) {
            $error = 'Complete the required delivery address fields.';
        } elseif (!preg_match('/^[0-9]{6}$/', $postalCode)) {
            $error = 'Enter a valid 6-digit postal code.';
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $items = checkout_items($pdo, $cartId);

                if (!$items) {
                    throw new RuntimeException('Your cart is empty.');
                }

                $subtotal = 0.0;
                $validatedItems = [];

                foreach ($items as $item) {
                    if (
                        $item['status'] !== 'active'
                        || $item['deleted_at'] !== null
                    ) {
                        throw new RuntimeException(
                            $item['product_name']
                            . ' is no longer available.'
                        );
                    }

                    $mode = sf_purchase_mode($pdo, $item);

                    if (!in_array($mode, ['checkout', 'both'], true)) {
                        throw new RuntimeException(
                            $item['product_name']
                            . ' is not available for checkout.'
                        );
                    }

                    $quantity = (int)$item['quantity'];

                    if (!sf_valid_quantity($item, $quantity)) {
                        throw new RuntimeException(
                            'Invalid quantity for '
                            . $item['product_name']
                            . '.'
                        );
                    }

                    if (
                        (int)$item['manage_stock'] === 1
                        && $quantity > (int)$item['stock_quantity']
                    ) {
                        throw new RuntimeException(
                            'Insufficient stock for '
                            . $item['product_name']
                            . '.'
                        );
                    }

                    $baseEffective = sf_effective_price($item);
                    $variantAdjustment =
                        (float)($item['color_adjustment'] ?? 0)
                        + (float)($item['design_adjustment'] ?? 0);

                    $finalUnitPrice =
                        max(0, $baseEffective + $variantAdjustment);

                    $lineTotal = $finalUnitPrice * $quantity;
                    $subtotal += $lineTotal;

                    $item['base_effective_price'] = $baseEffective;
                    $item['variant_adjustment_total'] =
                        $variantAdjustment;
                    $item['final_unit_price_value'] = $finalUnitPrice;
                    $item['line_total_value'] = $lineTotal;

                    $validatedItems[] = $item;
                }

                $shippingEnabled =
                    sf_setting($pdo, 'shipping_enabled', '1') === '1';

                $shippingAmount = $shippingEnabled
                    ? max(
                        0,
                        (float)sf_setting(
                            $pdo,
                            'flat_shipping_amount',
                            '0'
                        )
                    )
                    : 0.0;

                $taxEnabled =
                    sf_setting($pdo, 'tax_enabled', '0') === '1';

                $taxPercentage = $taxEnabled
                    ? max(
                        0,
                        (float)sf_setting(
                            $pdo,
                            'tax_percentage',
                            '0'
                        )
                    )
                    : 0.0;

                $taxAmount = round(
                    $subtotal * $taxPercentage / 100,
                    2
                );

                $grandTotal =
                    $subtotal + $shippingAmount + $taxAmount;

                $customerId = sf_find_or_create_customer(
                    $pdo,
                    $name,
                    $phone,
                    $email
                );

                $orderNumber = sf_next_number(
                    $pdo,
                    'order',
                    'RKC'
                );

                $orderStmt = $pdo->prepare(
                    "INSERT INTO orders
                    (
                        order_number,
                        customer_id,
                        enquiry_id,
                        order_source,
                        status,
                        payment_status,
                        customer_name,
                        customer_email,
                        customer_phone,
                        currency,
                        subtotal,
                        discount_amount,
                        shipping_amount,
                        tax_amount,
                        grand_total,
                        customer_notes
                    )
                    VALUES
                    (
                        :order_number,
                        :customer_id,
                        NULL,
                        'website_checkout',
                        'new',
                        'pending',
                        :customer_name,
                        :customer_email,
                        :customer_phone,
                        :currency,
                        :subtotal,
                        0,
                        :shipping_amount,
                        :tax_amount,
                        :grand_total,
                        :customer_notes
                    )"
                );

                $orderStmt->execute([
                    'order_number' => $orderNumber,
                    'customer_id' => $customerId,
                    'customer_name' => $name,
                    'customer_email' => $email !== '' ? $email : null,
                    'customer_phone' => $phone,
                    'currency' => sf_setting($pdo, 'currency', 'INR'),
                    'subtotal' => $subtotal,
                    'shipping_amount' => $shippingAmount,
                    'tax_amount' => $taxAmount,
                    'grand_total' => $grandTotal,
                    'customer_notes' => $notes !== '' ? $notes : null,
                ]);

                $orderId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare(
                    "INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        color_variant_id,
                        design_variant_id,
                        product_name_snapshot,
                        sku_snapshot,
                        thumbnail_snapshot,
                        selected_color_name,
                        selected_design_name,
                        minimum_qty_snapshot,
                        quantity_step_snapshot,
                        quantity,
                        base_unit_price,
                        variant_adjustment,
                        discount_amount,
                        final_unit_price,
                        line_total,
                        customer_item_notes
                    )
                    VALUES
                    (
                        :order_id,
                        :product_id,
                        :color_variant_id,
                        :design_variant_id,
                        :product_name_snapshot,
                        :sku_snapshot,
                        :thumbnail_snapshot,
                        :selected_color_name,
                        :selected_design_name,
                        :minimum_qty_snapshot,
                        :quantity_step_snapshot,
                        :quantity,
                        :base_unit_price,
                        :variant_adjustment,
                        0,
                        :final_unit_price,
                        :line_total,
                        :customer_item_notes
                    )"
                );

                $stockStmt = $pdo->prepare(
                    "UPDATE products
                     SET stock_quantity = stock_quantity - :quantity
                     WHERE id = :product_id
                       AND manage_stock = 1
                       AND stock_quantity >= :quantity_check"
                );

                foreach ($validatedItems as $item) {
                    $itemStmt->execute([
                        'order_id' => $orderId,
                        'product_id' => (int)$item['product_id_value'],
                        'color_variant_id' =>
                            $item['color_variant_id'] ?: null,
                        'design_variant_id' =>
                            $item['design_variant_id'] ?: null,
                        'product_name_snapshot' => $item['product_name'],
                        'sku_snapshot' => $item['sku'] ?: null,
                        'thumbnail_snapshot' => $item['thumbnail_path'],
                        'selected_color_name' =>
                            $item['color_name'] ?: null,
                        'selected_design_name' =>
                            $item['design_name'] ?: null,
                        'minimum_qty_snapshot' =>
                            (int)$item['minimum_order_qty'],
                        'quantity_step_snapshot' =>
                            (int)$item['quantity_step'],
                        'quantity' => (int)$item['quantity'],
                        'base_unit_price' =>
                            $item['base_effective_price'],
                        'variant_adjustment' =>
                            $item['variant_adjustment_total'],
                        'final_unit_price' =>
                            $item['final_unit_price_value'],
                        'line_total' => $item['line_total_value'],
                        'customer_item_notes' =>
                            $item['customer_item_notes'] ?: null,
                    ]);

                    if ((int)$item['manage_stock'] === 1) {
                        $stockStmt->execute([
                            'quantity' => (int)$item['quantity'],
                            'product_id' => (int)$item['product_id_value'],
                            'quantity_check' => (int)$item['quantity'],
                        ]);

                        if ($stockStmt->rowCount() !== 1) {
                            throw new RuntimeException(
                                'Stock changed while placing the order.'
                            );
                        }
                    }
                }

                $addressStmt = $pdo->prepare(
                    "INSERT INTO order_addresses
                    (
                        order_id,
                        address_type,
                        contact_name,
                        phone,
                        address_line_1,
                        address_line_2,
                        landmark,
                        city,
                        district,
                        state,
                        postal_code,
                        country
                    )
                    VALUES
                    (
                        :order_id,
                        :address_type,
                        :contact_name,
                        :phone,
                        :address_line_1,
                        :address_line_2,
                        :landmark,
                        :city,
                        :district,
                        :state,
                        :postal_code,
                        :country
                    )"
                );

                foreach (['shipping', 'billing'] as $addressType) {
                    $addressStmt->execute([
                        'order_id' => $orderId,
                        'address_type' => $addressType,
                        'contact_name' => $name,
                        'phone' => $phone,
                        'address_line_1' => $address1,
                        'address_line_2' =>
                            $address2 !== '' ? $address2 : null,
                        'landmark' =>
                            $landmark !== '' ? $landmark : null,
                        'city' => $city,
                        'district' =>
                            $district !== '' ? $district : null,
                        'state' => $state,
                        'postal_code' => $postalCode,
                        'country' => $country,
                    ]);
                }

                $paymentStmt = $pdo->prepare(
                    "INSERT INTO payments
                    (
                        order_id,
                        payment_gateway,
                        payment_method,
                        amount,
                        currency,
                        status
                    )
                    VALUES
                    (
                        :order_id,
                        :payment_gateway,
                        'pending_selection',
                        :amount,
                        :currency,
                        'pending'
                    )"
                );

                $paymentStmt->execute([
                    'order_id' => $orderId,
                    'payment_gateway' =>
                        sf_setting($pdo, 'gateway_name', 'manual'),
                    'amount' => $grandTotal,
                    'currency' => sf_setting($pdo, 'currency', 'INR'),
                ]);

                $cartStmt = $pdo->prepare(
                    "UPDATE carts
                     SET customer_id = :customer_id,
                         status = 'converted'
                     WHERE id = :cart_id
                       AND status = 'active'"
                );

                $cartStmt->execute([
                    'customer_id' => $customerId,
                    'cart_id' => $cartId,
                ]);

                $pdo->commit();

                $_SESSION['last_order_number'] = $orderNumber;
                unset($_SESSION['sf_cart_token']);

                $companyName = sf_setting(
                    $pdo,
                    'company_name',
                    'Ramki Cards'
                );

                if ($email !== '') {
                    sf_send_mail(
                        $pdo,
                        $email,
                        'Order received ' . $orderNumber,
                        implode("\n", [
                            'Dear ' . $name . ',',
                            '',
                            'Your order has been received.',
                            'Order number: ' . $orderNumber,
                            'Grand total: ' . sf_money($grandTotal),
                            'Payment status: Pending',
                            '',
                            'Our team will contact you with payment and delivery details.',
                            '',
                            'Regards,',
                            $companyName,
                        ])
                    );
                }

                header(
                    'Location: order-success.php?order='
                    . rawurlencode($orderNumber)
                );
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Checkout failed: ' . $e->getMessage());

                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Unable to place the order. Please try again.';
            }
        }
    }
}

$pageTitle = 'Checkout | '
    . sf_setting($pdo, 'company_name', 'Ramki Cards');

$topStripItems = [];
require __DIR__ . '/includes/storefront-header.php';
?>

<style>
  .checkout-page-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.12fr) minmax(360px, .88fr);
    gap: 28px;
    align-items: start;
  }

  .checkout-customer-panel,
  .checkout-review-panel {
    min-width: 0;
  }

  .checkout-customer-panel {
    padding: 28px;
  }

  .checkout-customer-panel > h3 {
    margin: 0;
  }

  .checkout-customer-panel .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 18px !important;
  }

  .checkout-customer-panel .form-group {
    min-width: 0;
  }

  .checkout-customer-panel .form-group.full {
    grid-column: 1 / -1;
  }

  .checkout-customer-panel input,
  .checkout-customer-panel textarea {
    width: 100%;
    max-width: 100%;
  }

  .checkout-admin-notice {
    margin: 0 0 18px;
    padding: 13px 15px;
    color: #6b3f08;
    font-size: 13px;
    line-height: 1.6;
    background: #fff7df;
    border: 1px solid #efd79c;
    border-radius: 12px;
  }

  .checkout-review-panel {
    position: sticky;
    top: 104px;
    padding: 24px;
    overflow: hidden;
  }

  .checkout-review-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  .checkout-review-heading small {
    display: block;
    margin-bottom: 3px;
    color: #8b7378;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .checkout-review-heading h3 {
    margin: 0;
  }

  .checkout-item-badge {
    flex: 0 0 auto;
    padding: 7px 11px;
    color: #7e1028;
    font-size: 12px;
    font-weight: 700;
    background: #fff2f4;
    border: 1px solid #f0d6dc;
    border-radius: 999px;
  }

  .checkout-product-list {
    display: grid;
    gap: 14px;
  }

  .checkout-product-card {
    display: grid;
    grid-template-columns: 86px minmax(0, 1fr);
    gap: 15px;
    padding: 15px;
    background: #fffdf9;
    border: 1px solid #eee1d8;
    border-radius: 16px;
  }

  .checkout-product-image {
    display: block;
    width: 86px;
    height: 86px;
    overflow: hidden;
    background: #fff5df;
    border: 1px solid #ebddcf;
    border-radius: 13px;
  }

  .checkout-product-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .checkout-product-content {
    min-width: 0;
  }

  .checkout-product-title {
    display: block;
    margin: 0;
    overflow-wrap: anywhere;
    color: #74152a;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
  }

  .checkout-product-title:hover {
    color: #a11937;
  }

  .checkout-name-tamil {
    display: block;
    margin-top: 2px;
    color: #7b6a6e;
    font-size: 12px;
    font-weight: 500;
  }

  .checkout-product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }

  .checkout-product-meta span {
    padding: 4px 7px;
    color: #68595d;
    font-size: 10px;
    line-height: 1.3;
    background: #f8f1ed;
    border-radius: 7px;
  }

  .checkout-product-notes {
    margin: 8px 0 0;
    color: #77686b;
    font-size: 11px;
    line-height: 1.5;
    overflow-wrap: anywhere;
  }

  .checkout-product-price-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
  }

  .checkout-product-price-row span {
    color: #76666a;
    font-size: 12px;
  }

  .checkout-product-price-row strong {
    color: #5e071d;
    font-size: 15px;
    white-space: nowrap;
  }

  .checkout-total-box {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #ebdfe1;
  }

  .checkout-total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 7px 0;
    color: #68595d;
    font-size: 13px;
  }

  .checkout-total-row strong {
    color: #33272a;
  }

  .checkout-total-row.grand {
    margin-top: 7px;
    padding-top: 15px;
    color: #651127;
    font-size: 17px;
    font-weight: 700;
    border-top: 1px dashed #dbc8cd;
  }

  .checkout-total-row.grand strong {
    color: #8b0f2f;
    font-size: 21px;
  }

  .checkout-review-note {
    margin: 15px 0 0;
    color: #7a696d;
    font-size: 11px;
    line-height: 1.6;
    text-align: center;
  }

  @media (max-width: 980px) {
    .checkout-page-layout {
      grid-template-columns: 1fr;
    }

    .checkout-review-panel {
      position: static;
      order: -1;
    }
  }

  @media (max-width: 680px) {
    .commerce-page .container {
      padding-right: 14px;
      padding-left: 14px;
    }

    .checkout-customer-panel,
    .checkout-review-panel {
      padding: 18px;
      border-radius: 16px;
    }

    .checkout-customer-panel .form-grid {
      grid-template-columns: 1fr;
      gap: 13px;
    }

    .checkout-customer-panel .form-group.full {
      grid-column: auto;
    }

    .checkout-product-card {
      grid-template-columns: 70px minmax(0, 1fr);
      gap: 12px;
      padding: 12px;
    }

    .checkout-product-image {
      width: 70px;
      height: 70px;
    }

    .checkout-product-price-row {
      align-items: flex-start;
      flex-direction: column;
      gap: 5px;
    }

    .checkout-product-price-row strong {
      white-space: normal;
    }

    .checkout-review-heading {
      align-items: center;
    }
  }

  @media (max-width: 420px) {
    .checkout-product-card {
      grid-template-columns: 62px minmax(0, 1fr);
      gap: 10px;
      padding: 10px;
    }

    .checkout-product-image {
      width: 62px;
      height: 62px;
    }

    .checkout-product-title {
      font-size: 14px;
    }

    .checkout-item-badge {
      padding: 6px 9px;
      font-size: 11px;
    }
  }

  /* ================================================================
     Mobile-first checkout refinement
     ================================================================ */

  .checkout-mobile-action {
    display: none;
  }

  @media (max-width: 680px) {
    .commerce-page {
      padding-bottom: 104px;
    }

    .commerce-page .store-page-title {
      margin-bottom: 18px;
      padding-top: 4px;
    }

    .commerce-page .store-page-title .decor-line {
      margin-bottom: 8px;
      transform: scale(.8);
    }

    .commerce-page .store-page-title > span {
      font-size: 10px;
      letter-spacing: .15em;
    }

    .commerce-page .store-page-title h2 {
      margin-top: 5px;
      font-size: clamp(27px, 8vw, 34px);
      line-height: 1.08;
    }

    .checkout-page-layout {
      gap: 16px;
    }

    .checkout-review-panel,
    .checkout-customer-panel {
      width: 100%;
      padding: 16px;
      background: rgba(255, 255, 255, .96);
      border: 1px solid #eadfe1;
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(74, 20, 36, .08);
    }

    .checkout-review-panel {
      border-top: 3px solid #c89438;
    }

    .checkout-review-heading {
      gap: 10px;
      margin-bottom: 8px;
      padding-bottom: 12px;
      border-bottom: 1px solid #eee4e6;
    }

    .checkout-review-heading small {
      margin-bottom: 2px;
      font-size: 9px;
      letter-spacing: .12em;
    }

    .checkout-review-heading h3,
    .checkout-customer-panel > h3 {
      font-size: 23px;
      line-height: 1.15;
    }

    .checkout-item-badge {
      padding: 5px 9px;
      font-size: 10px;
      background: #fff7f8;
    }

    .checkout-product-list {
      gap: 0;
    }

    .checkout-product-card {
      grid-template-columns: 74px minmax(0, 1fr);
      gap: 12px;
      padding: 14px 0;
      background: transparent;
      border: 0;
      border-bottom: 1px solid #eee5e6;
      border-radius: 0;
    }

    .checkout-product-card:last-child {
      border-bottom: 0;
    }

    .checkout-product-image {
      width: 74px;
      height: 88px;
      padding: 3px;
      background: #fff9ee;
      border-radius: 11px;
    }

    .checkout-product-image img {
      object-fit: contain;
      border-radius: 8px;
    }

    .checkout-product-title {
      font-size: 15px;
      line-height: 1.3;
    }

    .checkout-name-tamil {
      margin-top: 3px;
      font-size: 11px;
      line-height: 1.5;
    }

    .checkout-product-meta {
      display: grid;
      gap: 3px;
      margin-top: 7px;
    }

    .checkout-product-meta span {
      position: relative;
      padding: 0 0 0 11px;
      color: #756469;
      font-size: 10px;
      line-height: 1.5;
      background: transparent;
      border-radius: 0;
    }

    .checkout-product-meta span::before {
      content: "";
      position: absolute;
      top: .58em;
      left: 0;
      width: 4px;
      height: 4px;
      background: #c89438;
      border-radius: 50%;
    }

    .checkout-product-notes {
      margin-top: 7px;
      padding: 7px 8px;
      font-size: 10px;
      background: #fbf5f1;
      border-radius: 8px;
    }

    .checkout-product-price-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-direction: row;
      gap: 8px;
      margin-top: 9px;
    }

    .checkout-product-price-row span {
      font-size: 11px;
    }

    .checkout-product-price-row strong {
      font-size: 14px;
      white-space: nowrap;
    }

    .checkout-total-box {
      margin-top: 8px;
      padding-top: 10px;
    }

    .checkout-total-row {
      padding: 6px 0;
      font-size: 12px;
    }

    .checkout-total-row.grand {
      margin-top: 4px;
      padding-top: 12px;
      font-size: 16px;
    }

    .checkout-total-row.grand strong {
      font-size: 21px;
    }

    .checkout-review-note {
      display: none;
    }

    .checkout-customer-panel {
      padding-bottom: 18px;
    }

    .checkout-admin-notice {
      margin-bottom: 14px;
      padding: 10px 12px;
      font-size: 11px;
      border-radius: 10px;
    }

    .checkout-customer-panel .form-grid {
      gap: 11px;
      margin-top: 14px !important;
    }

    .checkout-customer-panel input,
    .checkout-customer-panel textarea {
      min-height: 50px;
      padding: 12px 13px;
      font-size: 16px;
      background: #fff;
      border: 1px solid #dfd4d6;
      border-radius: 11px;
      box-shadow: none;
    }

    .checkout-customer-panel textarea {
      min-height: 104px;
      resize: vertical;
    }

    .checkout-customer-panel input:focus,
    .checkout-customer-panel textarea:focus {
      border-color: #a51b3b;
      box-shadow: 0 0 0 3px rgba(165, 27, 59, .09);
      outline: 0;
    }

    .checkout-customer-panel .notice {
      margin-top: 14px;
      padding: 11px 12px;
      font-size: 10px;
      line-height: 1.55;
      border-radius: 10px;
    }

    .checkout-main-submit {
      display: none !important;
    }

    .checkout-mobile-action {
      position: fixed;
      right: 0;
      bottom: 0;
      left: 0;
      z-index: 999;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      padding:
        11px
        max(14px, env(safe-area-inset-right))
        max(11px, env(safe-area-inset-bottom))
        max(14px, env(safe-area-inset-left));
      background: rgba(255, 255, 255, .98);
      border-top: 1px solid #e5d9dc;
      box-shadow: 0 -12px 30px rgba(56, 15, 28, .14);
      backdrop-filter: blur(12px);
    }

    .checkout-mobile-total {
      min-width: 0;
    }

    .checkout-mobile-total small {
      display: block;
      color: #78676b;
      font-size: 10px;
      line-height: 1.2;
    }

    .checkout-mobile-total strong {
      display: block;
      margin-top: 2px;
      color: #8c1231;
      font-size: 19px;
      line-height: 1.2;
      white-space: nowrap;
    }

    .checkout-mobile-action button {
      min-width: 145px;
      min-height: 48px;
      padding: 11px 18px;
      color: #fff;
      font-size: 14px;
      font-weight: 700;
      background: linear-gradient(135deg, #9d1736, #6f0d25);
      border: 0;
      border-radius: 12px;
      box-shadow: 0 10px 22px rgba(113, 13, 38, .24);
      cursor: pointer;
    }

    .whatsapp-float {
      bottom: 92px !important;
    }
  }

  @media (max-width: 380px) {
    .commerce-page .container {
      padding-right: 10px;
      padding-left: 10px;
    }

    .checkout-review-panel,
    .checkout-customer-panel {
      padding: 13px;
      border-radius: 15px;
    }

    .checkout-product-card {
      grid-template-columns: 66px minmax(0, 1fr);
      gap: 10px;
    }

    .checkout-product-image {
      width: 66px;
      height: 80px;
    }

    .checkout-mobile-action {
      gap: 10px;
      padding-right: 10px;
      padding-left: 10px;
    }

    .checkout-mobile-action button {
      min-width: 128px;
      padding-right: 13px;
      padding-left: 13px;
    }

    .checkout-mobile-total strong {
      font-size: 17px;
    }
  }

</style>

<main class="store-page commerce-page">
  <div class="container">
    <div class="section-title store-page-title">
      <div class="decor-line"><i></i></div>
      <span>Delivery and order details</span>
      <h2>Secure <em>Checkout</em></h2>
    </div>

    <?php if ($error !== ''): ?>
      <div class="purchase-message error">
        <?= sf_e($error); ?>
      </div>
    <?php endif; ?>

    <div class="checkout-grid checkout-page-layout">
      <form
        id="checkoutForm"
        method="POST"
        action="checkout.php"
        class="store-panel glass-card checkout-customer-panel"
      >
        <input
          type="hidden"
          name="csrf_token"
          value="<?= sf_e(sf_csrf_token()); ?>"
        >

        <?php if ($checkoutAdminLoggedIn && !$checkoutCustomerLoggedIn): ?>
          <div class="checkout-admin-notice">
            <strong>Administrator checkout test mode:</strong>
            enter the actual customer and delivery details before placing
            this test order.
          </div>
        <?php endif; ?>

        <h3>Customer Details</h3>

        <div class="form-grid" style="margin-top:16px">
          <div class="form-group">
            <input
              type="text"
              name="name"
              value="<?= sf_e($_POST['name'] ?? $checkoutDefaultName); ?>"
              placeholder="Full Name"
              maxlength="150"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="tel"
              name="phone"
              value="<?= sf_e($_POST['phone'] ?? ($checkoutCustomer['phone'] ?? '')); ?>"
              placeholder="10-digit Mobile Number"
              pattern="[0-9]{10}"
              maxlength="10"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="email"
              name="email"
              value="<?= sf_e($_POST['email'] ?? ($checkoutCustomer['email'] ?? '')); ?>"
              placeholder="Email Address (Optional)"
              maxlength="190"
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="address_line_1"
              value="<?= sf_e($_POST['address_line_1'] ?? ($checkoutAddress['address_line_1'] ?? '')); ?>"
              placeholder="Address Line 1"
              maxlength="255"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="address_line_2"
              value="<?= sf_e($_POST['address_line_2'] ?? ($checkoutAddress['address_line_2'] ?? '')); ?>"
              placeholder="Address Line 2 (Optional)"
              maxlength="255"
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="landmark"
              value="<?= sf_e($_POST['landmark'] ?? ($checkoutAddress['landmark'] ?? '')); ?>"
              placeholder="Landmark (Optional)"
              maxlength="255"
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="city"
              value="<?= sf_e($_POST['city'] ?? ($checkoutAddress['city'] ?? '')); ?>"
              placeholder="City"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="district"
              value="<?= sf_e($_POST['district'] ?? ($checkoutAddress['district'] ?? '')); ?>"
              placeholder="District"
              maxlength="100"
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="state"
              value="<?= sf_e($_POST['state'] ?? ($checkoutAddress['state'] ?? 'Tamil Nadu')); ?>"
              placeholder="State"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group">
            <input
              type="text"
              name="postal_code"
              value="<?= sf_e($_POST['postal_code'] ?? ($checkoutAddress['postal_code'] ?? '')); ?>"
              placeholder="6-digit Postal Code"
              pattern="[0-9]{6}"
              maxlength="6"
              required
            >
          </div>

          <div class="form-group full">
            <input
              type="text"
              name="country"
              value="<?= sf_e($_POST['country'] ?? ($checkoutAddress['country'] ?? 'India')); ?>"
              placeholder="Country"
              maxlength="100"
              required
            >
          </div>

          <div class="form-group full">
            <textarea
              name="notes"
              placeholder="Order and customization notes"
              maxlength="1500"
            ><?= sf_e($_POST['notes'] ?? ''); ?></textarea>
          </div>
        </div>

        <div class="notice">
          This version creates the order with payment status
          <strong>Pending</strong>. Connect the approved payment gateway
          after adding its credentials and callback verification.
        </div>

        <button
          class="submit-btn checkout-main-submit"
          type="submit"
        >
          Place Order
        </button>
      </form>

      <aside
        class="store-panel glass-card checkout-summary checkout-review-panel"
      >
        <div class="checkout-review-heading">
          <div>
            <small>Products in your cart</small>
            <h3>Order Summary</h3>
          </div>

          <span class="checkout-item-badge">
            <?= count($displayItems); ?>
            <?= count($displayItems) === 1 ? 'item' : 'items'; ?>
          </span>
        </div>

        <div class="checkout-product-list">
          <?php foreach ($displayItems as $item): ?>
            <?php
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price_snapshot'];
            $lineTotal = $unitPrice * $quantity;
            $productUrl = 'product.php?slug='
                . rawurlencode((string)$item['slug']);
            ?>
            <article class="checkout-product-card">
              <a
                class="checkout-product-image"
                href="<?= sf_e($productUrl); ?>"
                aria-label="View <?= sf_e($item['product_name']); ?>"
              >
                <img
                  src="<?= sf_e(sf_media_path(
                      $item['thumbnail_path'],
                      'banner.png'
                  )); ?>"
                  alt="<?= sf_e($item['product_name']); ?>"
                  loading="lazy"
                >
              </a>

              <div class="checkout-product-content">
                <a
                  class="checkout-product-title"
                  href="<?= sf_e($productUrl); ?>"
                >
                  <?= sf_e($item['product_name']); ?>
                </a>

                <?php if (!empty($item['product_name_tamil'])): ?>
                  <small
                    class="product-name-tamil checkout-name-tamil"
                    lang="ta"
                  >
                    <?= sf_e($item['product_name_tamil']); ?>
                  </small>
                <?php endif; ?>

                <div class="checkout-product-meta">
                  <?php if (!empty($item['color_name'])): ?>
                    <span>
                      Colour: <?= sf_e($item['color_name']); ?>
                    </span>
                  <?php endif; ?>

                  <?php if (!empty($item['design_name'])): ?>
                    <span>
                      Design: <?= sf_e($item['design_name']); ?>
                    </span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($item['customer_item_notes'])): ?>
                  <p class="checkout-product-notes">
                    <strong>Customization:</strong>
                    <?= sf_e($item['customer_item_notes']); ?>
                  </p>
                <?php endif; ?>

                <div class="checkout-product-price-row">
                  <span>
                    <?= $quantity; ?>
                    × <?= sf_e(sf_money($unitPrice)); ?>
                  </span>

                  <strong><?= sf_e(sf_money($lineTotal)); ?></strong>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="checkout-total-box">
          <div class="checkout-total-row">
            <span>
              Subtotal (<?= $displayQuantityCount; ?> units)
            </span>
            <strong><?= sf_e(sf_money($displaySubtotal)); ?></strong>
          </div>

          <div class="checkout-total-row">
            <span>Shipping</span>
            <strong>
              <?= $displayShippingAmount > 0
                  ? sf_e(sf_money($displayShippingAmount))
                  : 'Free'; ?>
            </strong>
          </div>

          <?php if ($displayTaxEnabled): ?>
            <div class="checkout-total-row">
              <span>
                Tax (<?= sf_e(
                    rtrim(
                        rtrim(number_format($displayTaxPercentage, 2), '0'),
                        '.'
                    )
                ); ?>%)
              </span>
              <strong><?= sf_e(sf_money($displayTaxAmount)); ?></strong>
            </div>
          <?php endif; ?>

          <div class="checkout-total-row grand">
            <span>Total</span>
            <strong><?= sf_e(sf_money($displayGrandTotal)); ?></strong>
          </div>
        </div>

        <p class="checkout-review-note">
          Product price, quantity, selected options and stock are validated
          again when the order is placed.
        </p>
      </aside>
    </div>
  </div>

  <div class="checkout-mobile-action" aria-label="Checkout total and action">
    <div class="checkout-mobile-total">
      <small>Total payable</small>
      <strong><?= sf_e(sf_money($displayGrandTotal)); ?></strong>
    </div>

    <button type="submit" form="checkoutForm">
      Place Order
    </button>
  </div>
</main>

<?php require __DIR__ . '/includes/storefront-footer.php'; ?>
