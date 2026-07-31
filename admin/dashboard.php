<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'dashboard')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Dashboard';
$pageScript = 'dashboard.js';

function dashboard_table_exists(
    PDO $pdo,
    string $tableName
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name"
    );

    $stmt->execute([
        'table_name' => $tableName,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function dashboard_column_exists(
    PDO $pdo,
    string $tableName,
    string $columnName
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name"
    );

    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function dashboard_status_class(string $status): string
{
    return match ($status) {
        'active',
        'paid',
        'delivered',
        'converted' => 'badge-soft-success',

        'processed',
        'pending',
        'contacted',
        'quotation_sent' => 'badge-soft-warning',

        'new' => 'badge-soft-new',

        'cancelled',
        'rejected',
        'blocked',
        'failed' => 'badge-soft-danger',

        default => 'badge-soft-muted',
    };
}

function dashboard_status_label(string $status): string
{
    return ucwords(
        str_replace('_', ' ', $status)
    );
}

$adminName = trim((string)(
    $_SESSION['ramki_admin']['name']
    ?? $_SESSION['ramki_admin']['full_name']
    ?? $_SESSION['ramki_admin']['username']
    ?? $_SESSION['admin']['name']
    ?? $_SESSION['admin_name']
    ?? 'Administrator'
));

if ($adminName === '') {
    $adminName = 'Administrator';
}

$todayLabel = date('l, d F Y');

$dashboardCounts = [
    'new_enquiries' => 0,
    'new_orders' => 0,
    'active_products' => 0,
    'customers' => 0,
];

$recentEnquiries = [];
$recentOrders = [];
$dashboardLoadError = '';

try {
    if (dashboard_table_exists($pdo, 'enquiries')) {
        $dashboardCounts['new_enquiries'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM enquiries
                 WHERE status = 'new'"
            )
            ->fetchColumn();

        $enquiryTypeParts = [];

        if (
            dashboard_column_exists(
                $pdo,
                'enquiries',
                'event_type'
            )
        ) {
            $enquiryTypeParts[] = "NULLIF(e.event_type, '')";
        }

        if (
            dashboard_column_exists(
                $pdo,
                'enquiries',
                'subject'
            )
        ) {
            $enquiryTypeParts[] = "NULLIF(e.subject, '')";
        }

        $hasEnquiryItems =
            dashboard_table_exists($pdo, 'enquiry_items');

        if ($hasEnquiryItems) {
            $enquiryTypeParts[] = "NULLIF(ei.product_name, '')";
        }

        if (
            dashboard_column_exists(
                $pdo,
                'enquiries',
                'source'
            )
        ) {
            $enquiryTypeParts[] = "NULLIF(e.source, '')";
        }

        $enquiryTypeParts[] = "'Website Enquiry'";

        $enquiryTypeExpression =
            'COALESCE('
            . implode(', ', $enquiryTypeParts)
            . ')';

        $enquiryItemJoin = $hasEnquiryItems
            ? "LEFT JOIN (
                   SELECT
                       enquiry_id,
                       MAX(product_name_snapshot) AS product_name
                   FROM enquiry_items
                   GROUP BY enquiry_id
               ) ei
                   ON ei.enquiry_id = e.id"
            : '';

        $recentEnquirySql =
            "SELECT
                e.enquiry_number,
                e.customer_name,
                e.customer_phone,
                {$enquiryTypeExpression} AS enquiry_type,
                e.status,
                DATE_FORMAT(
                    e.created_at,
                    '%d-%m-%Y %h:%i %p'
                ) AS created_at
             FROM enquiries e
             {$enquiryItemJoin}
             ORDER BY e.id DESC
             LIMIT 6";

        $recentEnquiries = $pdo
            ->query($recentEnquirySql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    if (dashboard_table_exists($pdo, 'orders')) {
        $dashboardCounts['new_orders'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM orders
                 WHERE status = 'new'"
            )
            ->fetchColumn();

        $recentOrders = $pdo
            ->query(
                "SELECT
                    order_number,
                    customer_name,
                    grand_total,
                    status,
                    payment_status,
                    DATE_FORMAT(
                        created_at,
                        '%d-%m-%Y %h:%i %p'
                    ) AS created_at
                 FROM orders
                 ORDER BY id DESC
                 LIMIT 6"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    if (dashboard_table_exists($pdo, 'products')) {
        $productConditions = ["status = 'active'"];

        if (
            dashboard_column_exists(
                $pdo,
                'products',
                'deleted_at'
            )
        ) {
            $productConditions[] = 'deleted_at IS NULL';
        }

        $dashboardCounts['active_products'] = (int)$pdo
            ->query(
                "SELECT COUNT(*)
                 FROM products
                 WHERE "
                 . implode(' AND ', $productConditions)
            )
            ->fetchColumn();
    }

    if (dashboard_table_exists($pdo, 'customers')) {
        $dashboardCounts['customers'] = (int)$pdo
            ->query("SELECT COUNT(*) FROM customers")
            ->fetchColumn();
    }
} catch (Throwable $exception) {
    $dashboardLoadError =
        'Some dashboard information could not be loaded.';

    error_log(
        'Admin dashboard data load failed: '
        . $exception->getMessage()
    );
}

$quickActions = [
    [
        'permission' => 'enquiries',
        'url' => 'enquiries.php',
        'icon' => 'fa-solid fa-envelope-open-text',
        'label' => 'View Enquiries',
        'description' => 'Review new customer enquiries',
    ],
    [
        'permission' => 'orders',
        'url' => 'orders.php',
        'icon' => 'fa-solid fa-cart-shopping',
        'label' => 'Manage Orders',
        'description' => 'Track and update customer orders',
    ],
    [
        'permission' => 'products',
        'url' => 'products.php',
        'icon' => 'fa-solid fa-box-open',
        'label' => 'Manage Products',
        'description' => 'Add or update catalogue products',
    ],
    [
        'permission' => 'website_content',
        'url' => 'website-content.php',
        'icon' => 'fa-solid fa-globe',
        'label' => 'Website Content',
        'description' => 'Control public website sections',
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
/*
|--------------------------------------------------------------------------
| Dashboard theme bridge
|--------------------------------------------------------------------------
| All colours below are mapped to the database-driven Admin Theme variables.
| Switching html[data-theme] between light and dark updates this page without
| adding a second dashboard design.
|--------------------------------------------------------------------------
*/
.admin-dashboard {
    --dashboard-primary: var(--ui-brand-1, #8b1231);
    --dashboard-secondary: var(--ui-brand-2, #c9963e);
    --dashboard-primary-dark: color-mix(
        in srgb,
        var(--dashboard-primary) 72%,
        #000 28%
    );
    --dashboard-page: var(--ui-body-bg, #fff9ef);
    --dashboard-surface: var(--ui-card-bg, #ffffff);
    --dashboard-surface-soft: color-mix(
        in srgb,
        var(--dashboard-surface) 92%,
        var(--dashboard-primary) 8%
    );
    --dashboard-surface-softer: color-mix(
        in srgb,
        var(--dashboard-surface) 96%,
        var(--dashboard-secondary) 4%
    );
    --dashboard-text: var(--ui-text-main, #2f2623);
    --dashboard-muted: var(--ui-text-muted, #7c6f69);
    --dashboard-border: var(
        --ui-border-soft,
        rgba(139, 18, 49, 0.14)
    );
    --dashboard-header-bg: var(
        --ui-card-header-bg,
        var(--dashboard-surface)
    );
    --dashboard-table-head: var(
        --ui-table-header-bg,
        transparent
    );
    --dashboard-table-head-text: var(
        --ui-table-header-text,
        var(--dashboard-muted)
    );
    --dashboard-row-hover: var(
        --ui-table-row-hover,
        color-mix(
            in srgb,
            var(--dashboard-primary) 6%,
            transparent
        )
    );
    --dashboard-shadow: 0 16px 42px color-mix(
        in srgb,
        var(--dashboard-primary) 10%,
        transparent
    );
    --dashboard-shadow-hover: 0 22px 50px color-mix(
        in srgb,
        var(--dashboard-primary) 17%,
        transparent
    );
    color: var(--dashboard-text);
}

.dashboard-welcome {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 24px;
    padding: clamp(22px, 3vw, 30px);
    overflow: hidden;
    color: var(--ui-brand-text, #fff);
    background:
        radial-gradient(
            circle at 90% 15%,
            color-mix(in srgb, #fff 20%, transparent),
            transparent 25%
        ),
        var(
            --ui-brand-gradient,
            linear-gradient(
                135deg,
                var(--dashboard-primary-dark),
                var(--dashboard-primary)
            )
        );
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-secondary) 24%,
        transparent
    );
    border-radius: var(--ui-card-radius, 24px);
    box-shadow: 0 22px 54px color-mix(
        in srgb,
        var(--dashboard-primary) 26%,
        transparent
    );
}

.dashboard-welcome::after {
    content: '';
    position: absolute;
    right: -55px;
    bottom: -85px;
    width: 230px;
    height: 230px;
    border: 38px solid rgba(255, 255, 255, 0.07);
    border-radius: 50%;
    pointer-events: none;
}

.dashboard-welcome-copy,
.dashboard-welcome-actions {
    position: relative;
    z-index: 2;
}

.dashboard-welcome-copy {
    min-width: 0;
}

.dashboard-eyebrow {
    display: block;
    margin-bottom: 7px;
    color: color-mix(
        in srgb,
        var(--dashboard-secondary) 82%,
        #fff 18%
    );
    font-size: 0.74rem;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.dashboard-welcome h1 {
    margin: 0 0 7px;
    color: inherit;
    font-family: var(
        --ui-heading-font-family,
        "Playfair Display", Georgia, serif
    );
    font-size: clamp(1.65rem, 3vw, 2.45rem);
    font-weight: var(--ui-heading-font-weight, 800);
    line-height: 1.18;
}

.dashboard-welcome p {
    max-width: 640px;
    margin: 0;
    color: color-mix(in srgb, currentColor 78%, transparent);
    font-size: 0.9rem;
    line-height: 1.65;
}

.dashboard-welcome-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 0 0 auto;
}

.dashboard-welcome-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 16px;
    color: var(--dashboard-primary-dark);
    font-size: 0.79rem;
    font-weight: 800;
    background: color-mix(
        in srgb,
        var(--dashboard-secondary) 84%,
        #fff 16%
    );
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-secondary) 45%,
        transparent
    );
    border-radius: var(--ui-button-radius, 12px);
    text-decoration: none;
    white-space: nowrap;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
}

.dashboard-welcome-button.secondary {
    color: #fff;
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.2);
}

.dashboard-welcome-button:hover {
    color: var(--dashboard-primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
}

.dashboard-welcome-button.secondary:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
}

.dashboard-stat-grid {
    margin-bottom: 0;
}

.dashboard-stat-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 126px;
    height: 100%;
    padding: 21px;
    overflow: hidden;
    color: var(--dashboard-text);
    background: var(--dashboard-surface);
    border: 1px solid var(--dashboard-border);
    border-radius: var(--ui-card-radius, 20px);
    box-shadow: var(--dashboard-shadow);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.dashboard-stat-card:hover {
    border-color: color-mix(
        in srgb,
        var(--dashboard-primary) 32%,
        var(--dashboard-border)
    );
    transform: translateY(-4px);
    box-shadow: var(--dashboard-shadow-hover);
}

.dashboard-stat-card::after {
    content: '';
    position: absolute;
    right: -32px;
    bottom: -46px;
    width: 110px;
    height: 110px;
    background: color-mix(
        in srgb,
        var(--dashboard-primary) 5%,
        transparent
    );
    border-radius: 50%;
    pointer-events: none;
}

.dashboard-stat-icon {
    position: relative;
    z-index: 2;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 56px;
    height: 56px;
    color: #fff;
    font-size: 1.25rem;
    background: linear-gradient(
        145deg,
        var(--dashboard-primary),
        var(--dashboard-primary-dark)
    );
    border-radius: calc(var(--ui-card-radius, 18px) * 0.78);
    box-shadow: 0 12px 25px color-mix(
        in srgb,
        var(--dashboard-primary) 28%,
        transparent
    );
}

.dashboard-stat-card.orders .dashboard-stat-icon {
    background: linear-gradient(
        145deg,
        color-mix(in srgb, var(--dashboard-secondary) 92%, #bf6b00),
        color-mix(in srgb, var(--dashboard-secondary) 62%, #7e4300)
    );
}

.dashboard-stat-card.products .dashboard-stat-icon {
    background: linear-gradient(145deg, #27856a, #15513f);
}

.dashboard-stat-card.customers .dashboard-stat-icon {
    background: linear-gradient(145deg, #566db6, #344676);
}

.dashboard-stat-content {
    position: relative;
    z-index: 2;
    min-width: 0;
}

.dashboard-stat-label {
    margin-bottom: 5px;
    color: var(--dashboard-muted);
    font-size: 0.75rem;
    font-weight: 700;
}

.dashboard-stat-value {
    color: var(--dashboard-text);
    font-family: var(
        --ui-heading-font-family,
        "Playfair Display", Georgia, serif
    );
    font-size: 2rem;
    font-weight: var(--ui-heading-font-weight, 800);
    line-height: 1;
}

.dashboard-stat-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 9px;
    color: var(--dashboard-primary);
    font-size: 0.7rem;
    font-weight: 800;
    text-decoration: none;
}

.dashboard-stat-link:hover {
    color: color-mix(
        in srgb,
        var(--dashboard-primary) 70%,
        var(--dashboard-secondary) 30%
    );
}

/* Required separation between statistics, Quick Actions and recent data. */
.dashboard-quick-actions {
    margin-block: clamp(24px, 3vw, 32px) clamp(30px, 4vw, 42px);
}

.dashboard-quick-actions-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 14px;
}

.dashboard-quick-actions-heading h2 {
    margin: 0;
    color: var(--dashboard-text);
    font-family: var(
        --ui-heading-font-family,
        "Playfair Display", Georgia, serif
    );
    font-size: 1.2rem;
    font-weight: var(--ui-heading-font-weight, 800);
}

.dashboard-date {
    color: var(--dashboard-muted);
    font-size: 0.72rem;
}

.dashboard-action-card {
    display: flex;
    align-items: center;
    gap: 13px;
    min-height: 92px;
    height: 100%;
    padding: 17px;
    color: var(--dashboard-text);
    background: linear-gradient(
        145deg,
        var(--dashboard-surface),
        var(--dashboard-surface-softer)
    );
    border: 1px solid var(--dashboard-border);
    border-radius: var(--ui-card-radius, 17px);
    box-shadow: 0 10px 28px color-mix(
        in srgb,
        var(--dashboard-primary) 7%,
        transparent
    );
    text-decoration: none;
    transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.dashboard-action-card:hover {
    color: var(--dashboard-text);
    border-color: color-mix(
        in srgb,
        var(--dashboard-primary) 38%,
        var(--dashboard-border)
    );
    box-shadow: 0 16px 34px color-mix(
        in srgb,
        var(--dashboard-primary) 12%,
        transparent
    );
    transform: translateY(-3px);
}

.dashboard-action-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
    color: var(--dashboard-primary);
    background: color-mix(
        in srgb,
        var(--dashboard-primary) 10%,
        var(--dashboard-surface)
    );
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-primary) 12%,
        transparent
    );
    border-radius: calc(var(--ui-card-radius, 16px) * 0.72);
}

.dashboard-action-copy {
    min-width: 0;
}

.dashboard-action-copy strong {
    display: block;
    margin-bottom: 3px;
    color: var(--dashboard-text);
    font-size: 0.78rem;
}

.dashboard-action-copy span {
    display: block;
    color: var(--dashboard-muted);
    font-size: 0.66rem;
    line-height: 1.45;
}

.dashboard-data-grid {
    row-gap: 22px;
}

.dashboard-data-grid > [class*="col-"] {
    display: flex;
}

.dashboard-section-card {
    width: 100%;
    min-width: 0;
    height: 100%;
    overflow: hidden;
    color: var(--dashboard-text);
    background: var(--dashboard-surface);
    border: 1px solid var(--dashboard-border);
    border-radius: var(--ui-card-radius, 20px);
    box-shadow: var(--dashboard-shadow);
}

.dashboard-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px 22px;
    background: var(--dashboard-header-bg);
    border-bottom: 1px solid var(--dashboard-border);
}

.dashboard-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.dashboard-section-title-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 42px;
    height: 42px;
    color: var(--dashboard-primary);
    background: color-mix(
        in srgb,
        var(--dashboard-primary) 10%,
        var(--dashboard-surface)
    );
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-primary) 12%,
        transparent
    );
    border-radius: calc(var(--ui-card-radius, 16px) * 0.72);
}

.dashboard-section-title h2 {
    margin: 0;
    color: var(--dashboard-text);
    font-family: var(
        --ui-heading-font-family,
        "Playfair Display", Georgia, serif
    );
    font-size: 1.15rem;
    font-weight: var(--ui-heading-font-weight, 800);
}

.dashboard-section-title small {
    display: block;
    margin-top: 2px;
    overflow: hidden;
    color: var(--dashboard-muted);
    font-size: 0.66rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-view-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex: 0 0 auto;
    min-height: 38px;
    padding: 8px 11px;
    color: var(--dashboard-primary);
    font-size: 0.69rem;
    font-weight: 800;
    background: var(--dashboard-surface);
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-primary) 22%,
        var(--dashboard-border)
    );
    border-radius: var(--ui-button-radius, 10px);
    text-decoration: none;
    white-space: nowrap;
}

.dashboard-view-button:hover {
    color: var(--ui-brand-text, #fff);
    background: var(--dashboard-primary);
}

.dashboard-table-wrap {
    width: 100%;
    padding: 6px 18px 16px;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color:
        color-mix(in srgb, var(--dashboard-primary) 30%, transparent)
        transparent;
}

.dashboard-table {
    width: 100%;
    min-width: 680px;
    margin: 0;
    color: var(--dashboard-text);
    --bs-table-color: var(--dashboard-text);
    --bs-table-bg: transparent;
    --bs-table-hover-color: var(--dashboard-text);
    --bs-table-hover-bg: var(--dashboard-row-hover);
    --bs-table-border-color: var(--dashboard-border);
}

.dashboard-data-grid .col-xl-5 .dashboard-table {
    min-width: 500px;
}

.dashboard-table thead th {
    padding: 13px 10px;
    color: var(--dashboard-table-head-text);
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.45px;
    text-transform: uppercase;
    background: var(--dashboard-table-head);
    border-bottom: 1px solid var(--dashboard-border);
    white-space: nowrap;
}

.dashboard-table tbody td {
    padding: 14px 10px;
    color: var(--dashboard-text);
    font-size: 0.76rem;
    background: transparent;
    border-bottom-color: var(--dashboard-border);
    vertical-align: middle;
}

.dashboard-table tbody tr:last-child td {
    border-bottom: 0;
}

.dashboard-table tbody strong,
.dashboard-table tbody a {
    color: var(--dashboard-text);
}

.dashboard-table tbody small,
.dashboard-table .text-muted {
    color: var(--dashboard-muted) !important;
}

.dashboard-table tbody td:nth-child(3) {
    max-width: 310px;
    white-space: normal;
}

.dashboard-loading {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: var(--dashboard-muted);
}

.dashboard-loading::before {
    content: '';
    width: 18px;
    height: 18px;
    border: 2px solid color-mix(
        in srgb,
        var(--dashboard-border) 80%,
        transparent
    );
    border-top-color: var(--dashboard-primary);
    border-radius: 50%;
    animation: dashboard-spin 0.75s linear infinite;
}

@keyframes dashboard-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Database-driven night theme. */
html[data-theme="dark"] .admin-dashboard {
    --dashboard-shadow: 0 18px 46px rgba(0, 0, 0, 0.28);
    --dashboard-shadow-hover: 0 22px 54px rgba(0, 0, 0, 0.38);
}

html[data-theme="dark"] .dashboard-stat-card,
html[data-theme="dark"] .dashboard-action-card,
html[data-theme="dark"] .dashboard-section-card {
    background: var(--dashboard-surface);
}

html[data-theme="dark"] .dashboard-action-card {
    background: linear-gradient(
        145deg,
        var(--dashboard-surface),
        var(--dashboard-surface-soft)
    );
}

html[data-theme="dark"] .dashboard-stat-card::after {
    background: color-mix(
        in srgb,
        var(--dashboard-secondary) 7%,
        transparent
    );
}

html[data-theme="dark"] .dashboard-table tbody tr:hover > * {
    background: var(--dashboard-row-hover);
}

html[data-theme="dark"] .alert-warning {
    color: color-mix(
        in srgb,
        var(--dashboard-secondary) 75%,
        #fff 25%
    );
    background: color-mix(
        in srgb,
        var(--dashboard-secondary) 12%,
        var(--dashboard-surface)
    );
    border: 1px solid color-mix(
        in srgb,
        var(--dashboard-secondary) 25%,
        transparent
    ) !important;
}

@media (max-width: 1199.98px) {
    .dashboard-welcome {
        align-items: flex-start;
    }

    .dashboard-data-grid > [class*="col-"] {
        display: block;
    }

    .dashboard-section-card {
        height: auto;
    }
}

@media (max-width: 991.98px) {
    .dashboard-welcome {
        flex-direction: column;
    }

    .dashboard-welcome-actions {
        width: 100%;
    }

    .dashboard-stat-card {
        min-height: 116px;
    }

    .dashboard-quick-actions {
        margin-bottom: 34px;
    }
}

@media (max-width: 767.98px) {
    .dashboard-welcome-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dashboard-welcome-button {
        width: 100%;
    }

    .dashboard-quick-actions-heading {
        align-items: flex-start;
    }

    .dashboard-date {
        display: none;
    }

    .dashboard-section-header {
        align-items: flex-start;
        padding: 17px;
    }

    .dashboard-section-title small {
        max-width: 52vw;
    }

    .dashboard-table-wrap {
        padding-right: 12px;
        padding-left: 12px;
    }
}

@media (max-width: 575.98px) {
    .admin-dashboard {
        min-width: 0;
    }

    .dashboard-welcome {
        gap: 18px;
        margin-bottom: 18px;
        padding: 21px 18px;
        border-radius: var(--ui-card-radius, 19px);
    }

    .dashboard-welcome::after {
        right: -90px;
        bottom: -120px;
    }

    .dashboard-welcome-actions {
        grid-template-columns: 1fr;
    }

    .dashboard-stat-card {
        min-height: 104px;
        padding: 17px;
    }

    .dashboard-stat-icon {
        width: 50px;
        height: 50px;
    }

    .dashboard-stat-value {
        font-size: 1.72rem;
    }

    .dashboard-quick-actions {
        margin-block: 22px 30px;
    }

    .dashboard-action-card {
        min-height: 82px;
        padding: 14px;
    }

    .dashboard-section-title small {
        display: none;
    }

    .dashboard-section-title h2 {
        font-size: 1rem;
    }

    .dashboard-view-button {
        min-height: 34px;
        padding: 7px 9px;
    }

    .dashboard-table {
        min-width: 620px;
    }

    .dashboard-data-grid .col-xl-5 .dashboard-table {
        min-width: 480px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-stat-card,
    .dashboard-action-card,
    .dashboard-welcome-button {
        transition: none;
    }

    .dashboard-loading::before {
        animation-duration: 1.5s;
    }
}
</style>

<div class="admin-dashboard">
    <section class="dashboard-welcome">
        <div class="dashboard-welcome-copy">
            <span class="dashboard-eyebrow">
                <?= e($todayLabel); ?>
            </span>

            <h1>
                Welcome back, <?= e($adminName); ?>
            </h1>

            <p>
                Monitor enquiries, orders, products and customer activity
                from one place.
            </p>
        </div>

        <div class="dashboard-welcome-actions">
            <button type="button" class="dashboard-welcome-button secondary" onclick="window.location.reload()">
                <i class="fa-solid fa-rotate-right"></i>
                Refresh
            </button>

            <a href="../index.php" class="dashboard-welcome-button" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                View Website
            </a>
        </div>
    </section>

    <?php if ($dashboardLoadError !== ''): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-3">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <?= e($dashboardLoadError); ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 dashboard-stat-grid">
        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-stat-card enquiries">
                <div class="dashboard-stat-icon">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>

                <div class="dashboard-stat-content">
                    <div class="dashboard-stat-label">
                        New Enquiries
                    </div>

                    <div class="dashboard-stat-value" id="statEnquiries" aria-live="polite">
                        <?= (int)$dashboardCounts['new_enquiries']; ?></div>

                    <a href="enquiries.php" class="dashboard-stat-link">
                        Review enquiries
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-stat-card orders">
                <div class="dashboard-stat-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>

                <div class="dashboard-stat-content">
                    <div class="dashboard-stat-label">
                        New Orders
                    </div>

                    <div class="dashboard-stat-value" id="statOrders" aria-live="polite">
                        <?= (int)$dashboardCounts['new_orders']; ?></div>

                    <a href="orders.php" class="dashboard-stat-link">
                        Manage orders
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-stat-card products">
                <div class="dashboard-stat-icon">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div class="dashboard-stat-content">
                    <div class="dashboard-stat-label">
                        Active Products
                    </div>

                    <div class="dashboard-stat-value" id="statProducts" aria-live="polite">
                        <?= (int)$dashboardCounts['active_products']; ?></div>

                    <a href="products.php" class="dashboard-stat-link">
                        Open catalogue
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="dashboard-stat-card customers">
                <div class="dashboard-stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div class="dashboard-stat-content">
                    <div class="dashboard-stat-label">
                        Customers
                    </div>

                    <div class="dashboard-stat-value" id="statCustomers" aria-live="polite">
                        <?= (int)$dashboardCounts['customers']; ?></div>

                    <a href="customers.php" class="dashboard-stat-link">
                        View customers
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <section class="dashboard-quick-actions">
        <div class="dashboard-quick-actions-heading">
            <h2>Quick Actions</h2>

            <span class="dashboard-date">
                <?= e($todayLabel); ?>
            </span>
        </div>

        <div class="row g-3">
            <?php foreach ($quickActions as $action): ?>
            <?php if (can_menu($pdo, $action['permission'])): ?>
            <div class="col-sm-6 col-xl-3">
                <a href="<?= e($action['url']); ?>" class="dashboard-action-card">
                    <span class="dashboard-action-icon">
                        <i class="<?= e($action['icon']); ?>"></i>
                    </span>

                    <span class="dashboard-action-copy">
                        <strong>
                            <?= e($action['label']); ?>
                        </strong>

                        <span>
                            <?= e($action['description']); ?>
                        </span>
                    </span>
                </a>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="row g-3 dashboard-data-grid">
        <div class="col-xl-7">
            <section class="dashboard-section-card">
                <div class="dashboard-section-header">
                    <div class="dashboard-section-title">
                        <span class="dashboard-section-title-icon">
                            <i class="fa-solid fa-envelope"></i>
                        </span>

                        <div>
                            <h2>Recent Enquiries</h2>
                            <small>
                                Latest customer requests from the website
                            </small>
                        </div>
                    </div>

                    <a href="enquiries.php" class="dashboard-view-button">
                        View All
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive dashboard-table-wrap">
                    <table class="table table-hover align-middle dashboard-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Customer</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody id="recentEnquiries">
                            <?php if (!$recentEnquiries): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No enquiries found.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach (
                                    $recentEnquiries as $enquiry
                                ): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= e(
                                                    (string)$enquiry['enquiry_number']
                                                ); ?>
                                    </strong>
                                </td>

                                <td>
                                    <strong>
                                        <?= e(
                                                    (string)$enquiry['customer_name']
                                                ); ?>
                                    </strong>

                                    <small class="d-block text-muted">
                                        <?= e(
                                                    (string)$enquiry['customer_phone']
                                                ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e(
                                                (string)$enquiry['enquiry_type']
                                            ); ?>
                                </td>

                                <td>
                                    <span class="badge rounded-pill <?= e(
                                                    dashboard_status_class(
                                                        (string)$enquiry['status']
                                                    )
                                                ); ?>">
                                        <?= e(
                                                    dashboard_status_label(
                                                        (string)$enquiry['status']
                                                    )
                                                ); ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                                (string)$enquiry['created_at']
                                            ); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="dashboard-section-card">
                <div class="dashboard-section-header">
                    <div class="dashboard-section-title">
                        <span class="dashboard-section-title-icon">
                            <i class="fa-solid fa-bag-shopping"></i>
                        </span>

                        <div>
                            <h2>Recent Orders</h2>
                            <small>
                                Latest orders placed by customers
                            </small>
                        </div>
                    </div>

                    <a href="orders.php" class="dashboard-view-button">
                        View All
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive dashboard-table-wrap">
                    <table class="table table-hover align-middle dashboard-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody id="recentOrders">
                            <?php if (!$recentOrders): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No orders found.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach (
                                    $recentOrders as $order
                                ): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= e(
                                                    (string)$order['order_number']
                                                ); ?>
                                    </strong>

                                    <small class="d-block text-muted">
                                        <?= e(
                                                    (string)$order['created_at']
                                                ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= e(
                                                (string)$order['customer_name']
                                            ); ?>
                                </td>

                                <td class="fw-semibold">
                                    ₹<?= e(
                                                number_format(
                                                    (float)$order['grand_total'],
                                                    2
                                                )
                                            ); ?>
                                </td>

                                <td>
                                    <span class="badge rounded-pill <?= e(
                                                    dashboard_status_class(
                                                        (string)$order['status']
                                                    )
                                                ); ?>">
                                        <?= e(
                                                    dashboard_status_label(
                                                        (string)$order['status']
                                                    )
                                                ); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>


</div>

<?php require __DIR__ . '/includes/footer.php'; ?>