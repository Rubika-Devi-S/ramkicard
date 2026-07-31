<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'orders')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Orders';
$pageScript = 'orders.js';

function orders_page_badge_class(string $status): string
{
    return match ($status) {
        'new' => 'orders-badge-new',
        'processed' => 'orders-badge-processed',
        'delivered', 'paid' => 'orders-badge-success',
        'cancelled', 'failed' => 'orders-badge-danger',
        'pending' => 'orders-badge-pending',
        default => 'orders-badge-muted',
    };
}

function orders_page_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

$orderCounts = [
    'all' => 0,
    'new' => 0,
    'processed' => 0,
    'delivered' => 0,
    'cancelled' => 0,
];

$initialOrders = [];
$orderPageError = '';

try {
    $orderCounts['all'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM orders")
        ->fetchColumn();

    $statusCountStmt = $pdo->query(
        "SELECT status, COUNT(*) AS total
         FROM orders
         GROUP BY status"
    );

    foreach ($statusCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string)$row['status'];

        if (array_key_exists($status, $orderCounts)) {
            $orderCounts[$status] = (int)$row['total'];
        }
    }

    $initialOrders = $pdo->query(
        "SELECT
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.grand_total,
            o.payment_status,
            o.status,
            (
                SELECT COUNT(*)
                FROM order_items oi
                WHERE oi.order_id = o.id
            ) AS item_count,
            DATE_FORMAT(
                o.created_at,
                '%d-%m-%Y %h:%i %p'
            ) AS created_at
         FROM orders o
         ORDER BY o.id DESC
         LIMIT 100"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    $orderPageError = 'Unable to load order information.';

    error_log(
        'Admin orders page failed: '
        . $exception->getMessage()
    );
}

require __DIR__ . '/includes/header.php';
?>

<style>
.orders-admin-page {
    --orders-surface:
        var(--ui-card-bg, var(--bs-body-bg, #ffffff));
    --orders-surface-soft:
        var(--ui-card-header-bg, #fff8f3);
    --orders-text:
        var(--ui-text-main, var(--bs-body-color, #2d2421));
    --orders-muted:
        var(--ui-text-muted, #776c67);
    --orders-border:
        var(--ui-border-soft, rgba(139, 18, 49, 0.13));
    --orders-primary:
        var(--ui-brand-1, #8b1231);
    --orders-primary-dark:
        var(--ui-brand-2, #5d071d);
    --orders-gold:
        var(--ui-accent, #c9963e);
    --orders-hover:
        var(--ui-table-row-hover, rgba(139, 18, 49, 0.045));
    color: var(--orders-text);
}

.orders-page-banner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
    padding: 27px 29px;
    overflow: hidden;
    color: #fff;
    background:
        radial-gradient(
            circle at 88% 12%,
            rgba(255, 255, 255, 0.19),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            var(--orders-primary-dark),
            var(--orders-primary)
        );
    border-radius: 22px;
    box-shadow: 0 20px 48px rgba(84, 12, 34, 0.2);
}

.orders-page-banner::after {
    content: '';
    position: absolute;
    right: -48px;
    bottom: -82px;
    width: 210px;
    height: 210px;
    border: 34px solid rgba(255, 255, 255, 0.07);
    border-radius: 50%;
}

.orders-page-banner-copy,
.orders-page-banner-actions {
    position: relative;
    z-index: 2;
}

.orders-page-banner small {
    display: block;
    margin-bottom: 5px;
    color: #f1cf78;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.orders-page-banner h1 {
    margin: 0 0 5px;
    color: #fff;
    font-family: "Playfair Display", Georgia, serif;
    font-size: clamp(1.7rem, 3vw, 2.35rem);
    font-weight: 800;
}

.orders-page-banner p {
    max-width: 690px;
    margin: 0;
    color: rgba(255, 255, 255, 0.76);
    font-size: 0.83rem;
    line-height: 1.65;
}

.orders-page-banner-actions {
    display: flex;
    gap: 9px;
    flex: 0 0 auto;
}

.orders-banner-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 43px;
    padding: 9px 15px;
    color: var(--orders-primary-dark);
    font-size: 0.74rem;
    font-weight: 800;
    background: #f3d16f;
    border: 0;
    border-radius: 11px;
    text-decoration: none;
    cursor: pointer;
}

.orders-banner-btn.secondary {
    color: #fff;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.orders-summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 13px;
    margin-bottom: 22px;
}

.orders-summary-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 17px;
    color: var(--orders-text);
    background: var(--orders-surface);
    border: 1px solid var(--orders-border);
    border-radius: 17px;
    box-shadow: 0 11px 30px rgba(65, 27, 38, 0.07);
    cursor: pointer;
    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.orders-summary-card:hover,
.orders-summary-card.active {
    color: var(--orders-text);
    border-color: color-mix(
        in srgb,
        var(--orders-primary) 45%,
        transparent
    );
    transform: translateY(-2px);
    box-shadow: 0 15px 34px rgba(65, 27, 38, 0.11);
}

.orders-summary-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 43px;
    height: 43px;
    color: var(--orders-primary);
    background: color-mix(
        in srgb,
        var(--orders-primary) 10%,
        var(--orders-surface)
    );
    border-radius: 12px;
}

.orders-summary-copy {
    min-width: 0;
}

.orders-summary-copy span {
    display: block;
    overflow: hidden;
    margin-bottom: 3px;
    color: var(--orders-muted);
    font-size: 0.65rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.orders-summary-copy strong {
    color: var(--orders-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.45rem;
    line-height: 1;
}

.orders-list-card {
    overflow: hidden;
    background: var(--orders-surface);
    border: 1px solid var(--orders-border);
    border-radius: 20px;
    box-shadow: 0 16px 42px rgba(65, 27, 38, 0.08);
}

.orders-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    padding: 20px 21px;
    background: var(--orders-surface-soft);
    border-bottom: 1px solid var(--orders-border);
}

.orders-toolbar-title h2 {
    margin: 0 0 3px;
    color: var(--orders-text);
    font-family: "Playfair Display", Georgia, serif;
    font-size: 1.18rem;
    font-weight: 800;
}

.orders-toolbar-title p {
    margin: 0;
    color: var(--orders-muted);
    font-size: 0.7rem;
}

.orders-toolbar-controls {
    display: flex;
    align-items: center;
    gap: 9px;
}

.orders-search {
    position: relative;
    min-width: 230px;
}

.orders-search i {
    position: absolute;
    top: 50%;
    left: 12px;
    color: var(--orders-muted);
    transform: translateY(-50%);
}

.orders-search input,
.orders-status-select {
    min-height: 42px;
    color: var(--orders-text);
    background: var(--orders-surface);
    border: 1px solid var(--orders-border);
    border-radius: 11px;
}

.orders-search input {
    width: 100%;
    padding: 9px 12px 9px 35px;
    outline: none;
}

.orders-status-select {
    width: auto;
    min-width: 155px;
}

.orders-refresh-btn {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    color: var(--orders-primary);
    background: var(--orders-surface);
    border: 1px solid var(--orders-border);
    border-radius: 11px;
    cursor: pointer;
}

.orders-table-wrap {
    padding: 4px 17px 17px;
}

.orders-table {
    width: 100%;
    margin: 0;
    color: var(--orders-text);
}

.orders-table thead th {
    padding: 13px 10px;
    color: var(--orders-muted);
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.45px;
    text-transform: uppercase;
    background: transparent;
    border-bottom: 1px solid var(--orders-border);
    white-space: nowrap;
}

.orders-table tbody td {
    padding: 14px 10px;
    color: var(--orders-text);
    font-size: 0.75rem;
    background: transparent;
    border-bottom-color: var(--orders-border);
    vertical-align: middle;
}

.orders-table tbody tr:hover td {
    background: var(--orders-hover);
}

.orders-order-number {
    display: block;
    margin-bottom: 3px;
    color: var(--orders-primary);
    font-weight: 800;
}

.orders-customer-phone,
.orders-small {
    display: block;
    margin-top: 3px;
    color: var(--orders-muted);
    font-size: 0.65rem;
}

.orders-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 9px;
    font-size: 0.61rem;
    font-weight: 800;
    letter-spacing: 0.25px;
    text-transform: uppercase;
    border-radius: 999px;
    white-space: nowrap;
}

.orders-badge-new {
    color: #405ab8;
    background: rgba(64, 90, 184, 0.12);
}

.orders-badge-processed,
.orders-badge-pending {
    color: #a4690c;
    background: rgba(196, 133, 27, 0.14);
}

.orders-badge-success {
    color: #197044;
    background: rgba(25, 112, 68, 0.13);
}

.orders-badge-danger {
    color: #b1263f;
    background: rgba(177, 38, 63, 0.12);
}

.orders-badge-muted {
    color: var(--orders-muted);
    background: color-mix(
        in srgb,
        var(--orders-muted) 12%,
        transparent
    );
}

.orders-action-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.orders-action-btn {
    display: grid;
    place-items: center;
    width: 35px;
    height: 35px;
    color: var(--orders-primary);
    background: var(--orders-surface);
    border: 1px solid var(--orders-border);
    border-radius: 9px;
    text-decoration: none;
}

.orders-action-btn:hover {
    color: #fff;
    background: var(--orders-primary);
}

.orders-empty {
    padding: 38px !important;
    color: var(--orders-muted) !important;
    text-align: center;
}

@media (max-width: 1199.98px) {
    .orders-summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 991.98px) {
    .orders-page-banner {
        align-items: flex-start;
        flex-direction: column;
    }

    .orders-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .orders-toolbar-controls {
        width: 100%;
    }

    .orders-search {
        min-width: 0;
        flex: 1;
    }
}

@media (max-width: 767.98px) {
    .orders-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .orders-toolbar-controls {
        align-items: stretch;
        flex-wrap: wrap;
    }

    .orders-search {
        flex: 1 1 100%;
    }

    .orders-status-select {
        flex: 1;
        min-width: 0;
    }
}

@media (max-width: 575.98px) {
    .orders-page-banner {
        padding: 22px 19px;
        border-radius: 18px;
    }

    .orders-page-banner-actions {
        align-items: stretch;
        flex-direction: column;
        width: 100%;
    }

    .orders-banner-btn {
        width: 100%;
    }

    .orders-summary-grid {
        grid-template-columns: 1fr;
    }

    .orders-summary-card {
        padding: 14px 16px;
    }

    .orders-table-wrap {
        padding-right: 8px;
        padding-left: 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .orders-summary-card {
        transition: none;
    }
}
</style>

<div class="orders-admin-page" id="ordersModule">
    <section class="orders-page-banner">
        <div class="orders-page-banner-copy">
            <small>Order administration</small>
            <h1>Order Management</h1>
            <p>
                Review customer orders, view ordered products separately,
                and update each order from its dedicated details page.
            </p>
        </div>

        <div class="orders-page-banner-actions">
            <button
                type="button"
                class="orders-banner-btn secondary"
                id="refreshOrders"
            >
                <i class="fa-solid fa-rotate-right"></i>
                Refresh
            </button>

            <a
                href="../index.php"
                class="orders-banner-btn"
                target="_blank"
                rel="noopener"
            >
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                View Website
            </a>
        </div>
    </section>

    <?php if ($orderPageError !== ''): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            <?= e($orderPageError); ?>
        </div>
    <?php endif; ?>

    <section class="orders-summary-grid">
        <?php
        $summaryCards = [
            [
                'status' => '',
                'label' => 'All Orders',
                'count' => $orderCounts['all'],
                'icon' => 'fa-solid fa-layer-group',
            ],
            [
                'status' => 'new',
                'label' => 'New Orders',
                'count' => $orderCounts['new'],
                'icon' => 'fa-solid fa-sparkles',
            ],
            [
                'status' => 'processed',
                'label' => 'Processed',
                'count' => $orderCounts['processed'],
                'icon' => 'fa-solid fa-gears',
            ],
            [
                'status' => 'delivered',
                'label' => 'Delivered',
                'count' => $orderCounts['delivered'],
                'icon' => 'fa-solid fa-circle-check',
            ],
            [
                'status' => 'cancelled',
                'label' => 'Cancelled',
                'count' => $orderCounts['cancelled'],
                'icon' => 'fa-solid fa-ban',
            ],
        ];
        ?>

        <?php foreach ($summaryCards as $index => $card): ?>
            <button
                type="button"
                class="orders-summary-card <?= $index === 0
                    ? 'active'
                    : ''; ?>"
                data-order-filter="<?= e($card['status']); ?>"
            >
                <span class="orders-summary-icon">
                    <i class="<?= e($card['icon']); ?>"></i>
                </span>

                <span class="orders-summary-copy">
                    <span><?= e($card['label']); ?></span>
                    <strong
                        data-order-count="<?= e(
                            $card['status'] !== ''
                                ? $card['status']
                                : 'all'
                        ); ?>"
                    >
                        <?= (int)$card['count']; ?>
                    </strong>
                </span>
            </button>
        <?php endforeach; ?>
    </section>

    <section class="orders-list-card">
        <div class="orders-toolbar">
            <div class="orders-toolbar-title">
                <h2>Customer Orders</h2>
                <p>
                    Use the eye button for complete order details and the
                    box button for the separate ordered-products page.
                </p>
            </div>

            <div class="orders-toolbar-controls">
                <label class="orders-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        type="search"
                        id="orderSearch"
                        placeholder="Search order or customer"
                        autocomplete="off"
                    >
                </label>

                <select
                    class="form-select orders-status-select"
                    id="orderStatusFilter"
                >
                    <option value="">All Orders</option>
                    <option value="new">New Orders</option>
                    <option value="processed">Processed</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>

                <button
                    type="button"
                    class="orders-refresh-btn"
                    id="refreshOrdersSmall"
                    aria-label="Refresh orders"
                >
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>

        <div class="table-responsive orders-table-wrap">
            <table
                class="table table-hover align-middle orders-table"
                id="ordersTable"
            >
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Products</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Placed</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$initialOrders): ?>
                        <tr>
                            <td colspan="9" class="orders-empty">
                                No orders found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (
                            $initialOrders as $index => $order
                        ): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>

                                <td>
                                    <span class="orders-order-number">
                                        <?= e($order['order_number']); ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($order['customer_name']); ?>
                                    </strong>

                                    <span class="orders-customer-phone">
                                        <?= e($order['customer_phone']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= (int)$order['item_count']; ?>
                                    <span class="orders-small">
                                        product lines
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        ₹<?= e(number_format(
                                            (float)$order['grand_total'],
                                            2
                                        )); ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="orders-badge <?= e(
                                        orders_page_badge_class(
                                            (string)$order['payment_status']
                                        )
                                    ); ?>">
                                        <?= e(orders_page_status_label(
                                            (string)$order['payment_status']
                                        )); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="orders-badge <?= e(
                                        orders_page_badge_class(
                                            (string)$order['status']
                                        )
                                    ); ?>">
                                        <?= e(orders_page_status_label(
                                            (string)$order['status']
                                        )); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($order['created_at']); ?>
                                </td>

                                <td>
                                    <div class="orders-action-group">
                                        <a
                                            class="orders-action-btn"
                                            href="order-view.php?id=<?= (int)$order['id']; ?>"
                                            title="View order"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </a>

                                        <a
                                            class="orders-action-btn"
                                            href="order-products.php?order_id=<?= (int)$order['id']; ?>"
                                            title="View ordered products"
                                        >
                                            <i class="fa-solid fa-box-open"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
