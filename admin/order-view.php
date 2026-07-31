<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'orders')) {
    http_response_code(403);
    exit('Permission denied.');
}

function order_view_media_url(?string $path): string
{
    $path = trim((string)$path);

    if ($path === '') {
        return '../assets/images/banner.png';
    }

    if (
        preg_match('#^(?:https?:)?//#i', $path)
        || str_starts_with($path, 'data:')
        || str_starts_with($path, '/')
    ) {
        return $path;
    }

    return '../' . ltrim($path, './');
}

$orderId = max(0, (int)($_GET['id'] ?? 0));

$orderStmt = $pdo->prepare(
    "SELECT *
     FROM orders
     WHERE id = :id
     LIMIT 1"
);
$orderStmt->execute(['id' => $orderId]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemStmt = $pdo->prepare(
    "SELECT
        id,
        product_id,
        product_name_snapshot,
        sku_snapshot,
        thumbnail_snapshot,
        selected_color_name,
        selected_design_name,
        quantity,
        final_unit_price,
        line_total,
        customer_item_notes
     FROM order_items
     WHERE order_id = :id
     ORDER BY id"
);
$itemStmt->execute(['id' => $orderId]);
$orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$addressStmt = $pdo->prepare(
    "SELECT *
     FROM order_addresses
     WHERE order_id = :id
     ORDER BY
        CASE
            WHEN address_type = 'shipping' THEN 0
            ELSE 1
        END,
        id
     LIMIT 1"
);
$addressStmt->execute(['id' => $orderId]);
$address = $addressStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$paymentStmt = $pdo->prepare(
    "SELECT *
     FROM payments
     WHERE order_id = :id
     ORDER BY id DESC
     LIMIT 1"
);
$paymentStmt->execute(['id' => $orderId]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$history = [];

try {
    $historyStmt = $pdo->prepare(
        "SELECT
            previous_status,
            new_status,
            notes,
            DATE_FORMAT(
                created_at,
                '%d-%m-%Y %h:%i %p'
            ) AS created_at
         FROM order_status_history
         WHERE order_id = :id
         ORDER BY id DESC"
    );
    $historyStmt->execute(['id' => $orderId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log(
        'Order history loading failed: '
        . $exception->getMessage()
    );
}

$pageTitle = 'Order ' . $order['order_number'];

require __DIR__ . '/includes/header.php';
?>

<style>
.admin-detail-page {
    --detail-surface:
        var(--ui-card-bg, var(--bs-body-bg, #ffffff));
    --detail-soft:
        var(--ui-card-header-bg, #fff8f3);
    --detail-text:
        var(--ui-text-main, var(--bs-body-color, #2d2421));
    --detail-muted:
        var(--ui-text-muted, #786d68);
    --detail-border:
        var(--ui-border-soft, rgba(139, 18, 49, 0.13));
    --detail-primary:
        var(--ui-brand-1, #8b1231);
    --detail-primary-dark:
        var(--ui-brand-2, #5d071d);
    --detail-gold:
        var(--ui-accent, #c9963e);
    color: var(--detail-text);
}

.detail-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 20px;
}

.detail-back-link,
.detail-action-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 41px;
    padding: 9px 14px;
    color: var(--detail-primary);
    font-size: 0.72rem;
    font-weight: 800;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
    text-decoration: none;
}

.detail-action-link.primary {
    color: #fff;
    background: linear-gradient(
        135deg,
        var(--detail-primary),
        var(--detail-primary-dark)
    );
    border-color: transparent;
}

.detail-page-banner {
    position: relative;
    margin-bottom: 21px;
    padding: 25px 27px;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(
            circle at 88% 15%,
            rgba(255, 255, 255, 0.17),
            transparent 24%
        ),
        linear-gradient(
            135deg,
            var(--detail-primary-dark),
            var(--detail-primary)
        );
    border-radius: 21px;
    box-shadow: 0 18px 45px rgba(82, 14, 34, 0.2);
}

.detail-page-banner small {
    display: block;
    margin-bottom: 5px;
    color: #f0cd73;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 1.3px;
    text-transform: uppercase;
}

.detail-page-banner h1 {
    margin: 0 0 5px;
    color: #fff;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(1.65rem, 3vw, 2.3rem);
    font-weight: 800;
}

.detail-page-banner p {
    margin: 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 0.8rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(290px, 0.8fr);
    gap: 19px;
    align-items: start;
}

.detail-card {
    overflow: hidden;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 18px;
    box-shadow: 0 13px 36px rgba(65, 27, 38, 0.075);
}

.detail-card + .detail-card {
    margin-top: 18px;
}

.detail-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 17px 19px;
    background: var(--detail-soft);
    border-bottom: 1px solid var(--detail-border);
}

.detail-card-header h2 {
    margin: 0;
    color: var(--detail-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.08rem;
    font-weight: 800;
}

.detail-card-body {
    padding: 19px;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.detail-info-item {
    min-width: 0;
    padding: 13px;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 12px;
}

.detail-info-item.full {
    grid-column: 1 / -1;
}

.detail-info-item span {
    display: block;
    margin-bottom: 5px;
    color: var(--detail-muted);
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.detail-info-item strong,
.detail-info-item p {
    margin: 0;
    color: var(--detail-text);
    font-size: 0.77rem;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.detail-product-list {
    display: grid;
    gap: 12px;
}

.detail-product-row {
    display: grid;
    grid-template-columns: 76px minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 13px;
    background: var(--detail-soft);
    border: 1px solid var(--detail-border);
    border-radius: 14px;
}

.detail-product-image {
    width: 76px;
    height: 76px;
    overflow: hidden;
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
}

.detail-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.detail-product-copy {
    min-width: 0;
}

.detail-product-copy h3 {
    margin: 0 0 4px;
    color: var(--detail-primary);
    font-size: 0.82rem;
    font-weight: 800;
}

.detail-product-copy p {
    margin: 2px 0;
    color: var(--detail-muted);
    font-size: 0.67rem;
}

.detail-product-total {
    color: var(--detail-text);
    font-size: 0.82rem;
    font-weight: 800;
    text-align: right;
}

.detail-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 10px 0;
    color: var(--detail-muted);
    font-size: 0.74rem;
    border-bottom: 1px solid var(--detail-border);
}

.detail-summary-row:last-child {
    border-bottom: 0;
}

.detail-summary-row.total {
    margin-top: 5px;
    color: var(--detail-primary);
    font-size: 0.94rem;
    font-weight: 800;
}

.detail-status-form label {
    display: block;
    margin-bottom: 6px;
    color: var(--detail-muted);
    font-size: 0.68rem;
    font-weight: 800;
}

.detail-status-form select,
.detail-status-form textarea {
    width: 100%;
    color: var(--detail-text);
    background: var(--detail-surface);
    border: 1px solid var(--detail-border);
    border-radius: 11px;
}

.detail-status-form textarea {
    min-height: 105px;
    resize: vertical;
}

.detail-submit-btn {
    width: 100%;
    min-height: 45px;
    margin-top: 13px;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    background: linear-gradient(
        135deg,
        var(--detail-primary),
        var(--detail-primary-dark)
    );
    border: 0;
    border-radius: 11px;
}

.detail-history {
    display: grid;
    gap: 12px;
}

.detail-history-item {
    position: relative;
    padding-left: 21px;
}

.detail-history-item::before {
    content: '';
    position: absolute;
    top: 5px;
    left: 1px;
    width: 10px;
    height: 10px;
    background: var(--detail-gold);
    border-radius: 50%;
}

.detail-history-item::after {
    content: '';
    position: absolute;
    top: 16px;
    bottom: -14px;
    left: 5px;
    width: 1px;
    background: var(--detail-border);
}

.detail-history-item:last-child::after {
    display: none;
}

.detail-history-item strong {
    display: block;
    color: var(--detail-text);
    font-size: 0.72rem;
}

.detail-history-item span,
.detail-history-item p {
    display: block;
    margin: 3px 0 0;
    color: var(--detail-muted);
    font-size: 0.65rem;
}

@media (max-width: 991.98px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575.98px) {
    .detail-topbar {
        align-items: stretch;
        flex-direction: column;
    }

    .detail-page-banner {
        padding: 21px 19px;
        border-radius: 17px;
    }

    .detail-info-grid {
        grid-template-columns: 1fr;
    }

    .detail-info-item.full {
        grid-column: auto;
    }

    .detail-product-row {
        grid-template-columns: 64px minmax(0, 1fr);
    }

    .detail-product-image {
        width: 64px;
        height: 64px;
    }

    .detail-product-total {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>


<div class="admin-detail-page">
    <div class="detail-topbar">
        <a href="orders.php" class="detail-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Orders
        </a>

        <div class="d-flex gap-2 flex-wrap">
            <a
                href="order-products.php?order_id=<?= $orderId; ?>"
                class="detail-action-link"
            >
                <i class="fa-solid fa-box-open"></i>
                Separate Products Page
            </a>

            <a
                href="../my-order.php?order=<?= rawurlencode(
                    (string)$order['order_number']
                ); ?>"
                class="detail-action-link primary"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                Customer View
            </a>
        </div>
    </div>

    <section class="detail-page-banner">
        <small>Order details</small>
        <h1><?= e($order['order_number']); ?></h1>
        <p>
            Placed on
            <?= e(date(
                'd F Y, h:i A',
                strtotime((string)$order['created_at'])
            )); ?>
            by <?= e($order['customer_name']); ?>
        </p>
    </section>

    <div class="detail-grid">
        <div>
            <section class="detail-card">
                <div class="detail-card-header">
                    <h2>Ordered Products</h2>

                    <a
                        href="order-products.php?order_id=<?= $orderId; ?>"
                        class="detail-action-link"
                    >
                        View Separately
                    </a>
                </div>

                <div class="detail-card-body">
                    <div class="detail-product-list">
                        <?php foreach ($orderItems as $item): ?>
                            <article class="detail-product-row">
                                <a
                                    class="detail-product-image"
                                    href="<?= !empty($item['product_id'])
                                        ? 'product-view.php?id='
                                            . (int)$item['product_id']
                                        : '#'; ?>"
                                >
                                    <img
                                        src="<?= e(order_view_media_url(
                                            $item['thumbnail_snapshot']
                                            ?? ''
                                        )); ?>"
                                        alt="<?= e(
                                            $item['product_name_snapshot']
                                        ); ?>"
                                    >
                                </a>

                                <div class="detail-product-copy">
                                    <h3>
                                        <?= e(
                                            $item['product_name_snapshot']
                                        ); ?>
                                    </h3>

                                    <?php if (!empty(
                                        $item['sku_snapshot']
                                    )): ?>
                                        <p>
                                            SKU:
                                            <?= e($item['sku_snapshot']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (
                                        !empty($item['selected_color_name'])
                                        || !empty($item['selected_design_name'])
                                    ): ?>
                                        <p>
                                            <?= e(implode(
                                                ' · ',
                                                array_filter([
                                                    $item['selected_color_name'],
                                                    $item['selected_design_name'],
                                                ])
                                            )); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p>
                                        <?= (int)$item['quantity']; ?>
                                        × ₹<?= e(number_format(
                                            (float)$item['final_unit_price'],
                                            2
                                        )); ?>
                                    </p>
                                </div>

                                <div class="detail-product-total">
                                    ₹<?= e(number_format(
                                        (float)$item['line_total'],
                                        2
                                    )); ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="detail-card">
                <div class="detail-card-header">
                    <h2>Customer & Delivery</h2>
                </div>

                <div class="detail-card-body">
                    <div class="detail-info-grid">
                        <div class="detail-info-item">
                            <span>Customer</span>
                            <strong><?= e($order['customer_name']); ?></strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Mobile</span>
                            <strong><?= e($order['customer_phone']); ?></strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Email</span>
                            <strong>
                                <?= e($order['customer_email'] ?: '—'); ?>
                            </strong>
                        </div>

                        <div class="detail-info-item">
                            <span>Order Source</span>
                            <strong>
                                <?= e(ucwords(str_replace(
                                    '_',
                                    ' ',
                                    (string)$order['order_source']
                                ))); ?>
                            </strong>
                        </div>

                        <div class="detail-info-item full">
                            <span>Delivery Address</span>
                            <p>
                                <?= e(implode(', ', array_filter([
                                    $address['contact_name'] ?? '',
                                    $address['address_line_1'] ?? '',
                                    $address['address_line_2'] ?? '',
                                    $address['landmark'] ?? '',
                                    $address['city'] ?? '',
                                    $address['district'] ?? '',
                                    $address['state'] ?? '',
                                    $address['postal_code'] ?? '',
                                    $address['country'] ?? '',
                                ]))); ?>
                            </p>
                        </div>

                        <?php if (!empty($order['customer_notes'])): ?>
                            <div class="detail-info-item full">
                                <span>Customer Notes</span>
                                <p><?= nl2br(e($order['customer_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <aside>
            <section class="detail-card">
                <div class="detail-card-header">
                    <h2>Order Summary</h2>
                </div>

                <div class="detail-card-body">
                    <div class="detail-summary-row">
                        <span>Subtotal</span>
                        <strong>
                            ₹<?= e(number_format(
                                (float)$order['subtotal'],
                                2
                            )); ?>
                        </strong>
                    </div>

                    <div class="detail-summary-row">
                        <span>Discount</span>
                        <strong>
                            ₹<?= e(number_format(
                                (float)$order['discount_amount'],
                                2
                            )); ?>
                        </strong>
                    </div>

                    <div class="detail-summary-row">
                        <span>Shipping</span>
                        <strong>
                            ₹<?= e(number_format(
                                (float)$order['shipping_amount'],
                                2
                            )); ?>
                        </strong>
                    </div>

                    <div class="detail-summary-row">
                        <span>Tax</span>
                        <strong>
                            ₹<?= e(number_format(
                                (float)$order['tax_amount'],
                                2
                            )); ?>
                        </strong>
                    </div>

                    <div class="detail-summary-row total">
                        <span>Grand Total</span>
                        <strong>
                            ₹<?= e(number_format(
                                (float)$order['grand_total'],
                                2
                            )); ?>
                        </strong>
                    </div>

                    <div class="detail-summary-row">
                        <span>Payment</span>
                        <strong>
                            <?= e(ucwords(str_replace(
                                '_',
                                ' ',
                                (string)$order['payment_status']
                            ))); ?>
                        </strong>
                    </div>

                    <?php if ($payment): ?>
                        <div class="detail-summary-row">
                            <span>Method</span>
                            <strong>
                                <?= e(ucwords(str_replace(
                                    '_',
                                    ' ',
                                    (string)(
                                        $payment['payment_method']
                                        ?? $payment['payment_gateway']
                                        ?? 'Pending'
                                    )
                                ))); ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="detail-card">
                <div class="detail-card-header">
                    <h2>Update Status</h2>
                </div>

                <div class="detail-card-body">
                    <form
                        class="detail-status-form"
                        id="orderStatusForm"
                    >
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $orderId; ?>">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()); ?>"
                        >

                        <label for="orderStatus">Order Status</label>
                        <select
                            class="form-select mb-3"
                            id="orderStatus"
                            name="status"
                            required
                        >
                            <?php foreach (
                                ['new', 'processed', 'delivered', 'cancelled']
                                as $status
                            ): ?>
                                <option
                                    value="<?= e($status); ?>"
                                    <?= $order['status'] === $status
                                        ? 'selected'
                                        : ''; ?>
                                >
                                    <?= e(ucwords($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="orderAdminNotes">
                            Admin Notes / Cancellation Reason
                        </label>

                        <textarea
                            class="form-control"
                            id="orderAdminNotes"
                            name="notes"
                            maxlength="1500"
                        ><?= e($order['admin_notes'] ?? ''); ?></textarea>

                        <button
                            type="submit"
                            class="detail-submit-btn"
                            id="updateOrderButton"
                        >
                            Update Order
                        </button>
                    </form>
                </div>
            </section>

            <?php if ($history): ?>
                <section class="detail-card">
                    <div class="detail-card-header">
                        <h2>Status History</h2>
                    </div>

                    <div class="detail-card-body">
                        <div class="detail-history">
                            <?php foreach ($history as $row): ?>
                                <div class="detail-history-item">
                                    <strong>
                                        <?= e(ucwords(
                                            (string)$row['previous_status']
                                        )); ?>
                                        →
                                        <?= e(ucwords(
                                            (string)$row['new_status']
                                        )); ?>
                                    </strong>

                                    <span><?= e($row['created_at']); ?></span>

                                    <?php if (!empty($row['notes'])): ?>
                                        <p><?= e($row['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script>
(() => {
    'use strict';

    const form = document.getElementById('orderStatusForm');
    const button = document.getElementById('updateOrderButton');

    form?.addEventListener('submit', event => {
        event.preventDefault();

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Updating...';

        RamkiAdmin.request(
            'api/orders.php',
            new URLSearchParams(new FormData(form)).toString()
        )
            .done(response => {
                if (!response.success) {
                    RamkiAdmin.toast('error', response.message);
                    return;
                }

                RamkiAdmin.toast('success', response.message);

                window.setTimeout(() => {
                    window.location.reload();
                }, 650);
            })
            .fail(xhr => RamkiAdmin.error(xhr))
            .always(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
