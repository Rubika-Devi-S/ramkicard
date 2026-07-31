<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'customers')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Customers';
$pageScript = 'customers.js';

require __DIR__ . '/includes/header.php';
?>

<div class="ramki-card p-3">
    <div class="mb-3">
        <h2 class="h5 mb-1">Customer List</h2>
        <p class="text-muted small mb-0">
            View customer contact information, orders and enquiry totals.
        </p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover w-100" id="customersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Orders</th>
                    <th>Enquiries</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
