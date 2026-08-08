<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'customers')) {
    http_response_code(403);
    exit('Permission denied.');
}

$canEdit = can_menu($pdo, 'customers', 'can_edit');
$canDelete = can_menu($pdo, 'customers', 'can_delete');

if (function_exists('is_super_admin') && is_super_admin($pdo)) {
    $canEdit = true;
    $canDelete = true;
}

$pageTitle = 'Customers';
$pageScript = null;

$allowedStatuses = [
    'active',
    'inactive',
    'blocked',
];

function customer_status_label(string $status): string
{
    return ucfirst($status);
}

function customer_status_class(string $status): string
{
    return match ($status) {
        'active' => 'bg-success',
        'inactive' => 'bg-secondary',
        'blocked' => 'bg-danger',
        default => 'bg-secondary',
    };
}

function customer_admin_redirect(
    string $type,
    string $message,
    string $search = '',
    string $status = ''
): never {
    $query = [
        'flash_type' => $type,
        'flash_message' => $message,
    ];

    if ($search !== '') {
        $query['q'] = $search;
    }

    if ($status !== '') {
        $query['status'] = $status;
    }

    header(
        'Location: '
        . admin_url('customers.php')
        . '?'
        . http_build_query($query)
    );
    exit;
}

function customer_page_url(array $changes = []): string
{
    $query = $_GET;

    unset(
        $query['flash_type'],
        $query['flash_message']
    );

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }

        $query[$key] = (string)$value;
    }

    $url = admin_url('customers.php');

    return $query
        ? $url . '?' . http_build_query($query)
        : $url;
}

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

if (
    $statusFilter !== ''
    && !in_array($statusFilter, $allowedStatuses, true)
) {
    $statusFilter = '';
}

/*
|--------------------------------------------------------------------------
| Customer actions
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');

    if (
        $postedToken === ''
        || !hash_equals((string)csrf_token(), $postedToken)
    ) {
        customer_admin_redirect(
            'danger',
            'The page session expired. Refresh and try again.',
            $search,
            $statusFilter
        );
    }

    $formAction = trim((string)($_POST['form_action'] ?? ''));
    $customerId = max(0, (int)($_POST['id'] ?? 0));

    if ($customerId <= 0) {
        customer_admin_redirect(
            'danger',
            'Invalid customer.',
            $search,
            $statusFilter
        );
    }

    if ($formAction === 'update') {
        if (!$canEdit) {
            customer_admin_redirect(
                'danger',
                'You do not have permission to update customers.',
                $search,
                $statusFilter
            );
        }

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $newStatus = trim((string)($_POST['status'] ?? ''));

        if ($firstName === '' || mb_strlen($firstName) > 100) {
            customer_admin_redirect(
                'danger',
                'Enter a valid customer first name.',
                $search,
                $statusFilter
            );
        }

        if (mb_strlen($lastName) > 100) {
            customer_admin_redirect(
                'danger',
                'Customer last name is too long.',
                $search,
                $statusFilter
            );
        }

        if (
            $email !== ''
            && (
                mb_strlen($email) > 190
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            )
        ) {
            customer_admin_redirect(
                'danger',
                'Enter a valid customer email address.',
                $search,
                $statusFilter
            );
        }

        if (
            $phone === ''
            || mb_strlen($phone) > 20
            || preg_match('/^[0-9+() -]{6,20}$/', $phone) !== 1
        ) {
            customer_admin_redirect(
                'danger',
                'Enter a valid customer phone number.',
                $search,
                $statusFilter
            );
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            customer_admin_redirect(
                'danger',
                'Invalid customer status.',
                $search,
                $statusFilter
            );
        }

        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare(
                "SELECT
                    first_name,
                    last_name,
                    email,
                    phone,
                    status
                 FROM customers
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $select->execute(['id' => $customerId]);
            $oldCustomer = $select->fetch(PDO::FETCH_ASSOC);

            if (!$oldCustomer) {
                throw new RuntimeException('Customer not found.');
            }

            $update = $pdo->prepare(
                "UPDATE customers
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     phone = :phone,
                     status = :status
                 WHERE id = :id"
            );
            $update->execute([
                'first_name' => $firstName,
                'last_name' => $lastName !== '' ? $lastName : null,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'status' => $newStatus,
                'id' => $customerId,
            ]);

            if (function_exists('activity_log')) {
                $oldName = trim(
                    (string)$oldCustomer['first_name']
                    . ' '
                    . (string)($oldCustomer['last_name'] ?? '')
                );

                activity_log(
                    $pdo,
                    'update',
                    'Customers',
                    'customer',
                    $customerId,
                    sprintf(
                        'Customer %s updated. Status: %s to %s.',
                        $oldName !== '' ? $oldName : '#' . $customerId,
                        (string)$oldCustomer['status'],
                        $newStatus
                    )
                );
            }

            $pdo->commit();

            customer_admin_redirect(
                'success',
                'Customer updated successfully.',
                $search,
                $statusFilter
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Admin customer update failed: '
                . $exception->getMessage()
            );

            customer_admin_redirect(
                'danger',
                $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Unable to update the customer.',
                $search,
                $statusFilter
            );
        }
    }

    if ($formAction === 'delete') {
        if (!$canDelete) {
            customer_admin_redirect(
                'danger',
                'You do not have permission to delete customers.',
                $search,
                $statusFilter
            );
        }

        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare(
                "SELECT first_name, last_name, phone
                 FROM customers
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $select->execute(['id' => $customerId]);
            $customer = $select->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                throw new RuntimeException('Customer not found.');
            }

            $customerName = trim(
                (string)$customer['first_name']
                . ' '
                . (string)($customer['last_name'] ?? '')
            );

            if (function_exists('activity_log')) {
                activity_log(
                    $pdo,
                    'delete',
                    'Customers',
                    'customer',
                    $customerId,
                    'Deleted customer '
                    . ($customerName !== '' ? $customerName : '#' . $customerId)
                    . ' (' . (string)$customer['phone'] . ').'
                );
            }

            $delete = $pdo->prepare(
                "DELETE FROM customers
                 WHERE id = :id"
            );
            $delete->execute(['id' => $customerId]);

            if ($delete->rowCount() !== 1) {
                throw new RuntimeException('Unable to delete the customer.');
            }

            $pdo->commit();

            customer_admin_redirect(
                'success',
                'Customer deleted successfully.',
                $search,
                $statusFilter
            );
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Admin customer delete failed: '
                . $exception->getMessage()
            );

            customer_admin_redirect(
                'danger',
                $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Unable to delete the customer.',
                $search,
                $statusFilter
            );
        }
    }

    customer_admin_redirect(
        'danger',
        'Invalid customer action.',
        $search,
        $statusFilter
    );
}

/*
|--------------------------------------------------------------------------
| Customer list
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($statusFilter !== '') {
    $where[] = 'c.status = :status';
    $params['status'] = $statusFilter;
}

if ($search !== '') {
    $searchValue = '%' . $search . '%';

    $where[] = "(
        c.first_name LIKE :search_first_name
        OR c.last_name LIKE :search_last_name
        OR c.email LIKE :search_email
        OR c.phone LIKE :search_phone
    )";

    $params['search_first_name'] = $searchValue;
    $params['search_last_name'] = $searchValue;
    $params['search_email'] = $searchValue;
    $params['search_phone'] = $searchValue;
}

$whereSql = $where
    ? ' WHERE ' . implode(' AND ', $where)
    : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM customers c' . $whereSql
);
$countStmt->execute($params);
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCustomers / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$sql =
    "SELECT
        c.id,
        c.first_name,
        c.last_name,
        c.email,
        c.phone,
        c.email_verified_at,
        c.phone_verified_at,
        c.status,
        c.last_login_at,
        c.created_at,
        c.updated_at,
        (
            SELECT COUNT(*)
            FROM customer_addresses ca
            WHERE ca.customer_id = c.id
        ) AS address_count,
        (
            SELECT COUNT(*)
            FROM enquiries e
            WHERE e.customer_id = c.id
        ) AS enquiry_count,
        (
            SELECT COUNT(*)
            FROM orders o
            WHERE o.customer_id = c.id
        ) AS order_count,
        (
            SELECT COALESCE(SUM(o2.grand_total), 0)
            FROM orders o2
            WHERE o2.customer_id = c.id
              AND o2.status <> 'cancelled'
        ) AS order_value
     FROM customers c"
    . $whereSql
    . " ORDER BY c.created_at DESC, c.id DESC
        LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Addresses for the currently displayed customers
|--------------------------------------------------------------------------
*/

$addressesByCustomer = [];

if ($customers) {
    $customerIds = array_map(
        static fn (array $customer): int => (int)$customer['id'],
        $customers
    );

    $placeholders = implode(
        ', ',
        array_fill(0, count($customerIds), '?')
    );

    $addressStmt = $pdo->prepare(
        "SELECT
            id,
            customer_id,
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
            country,
            is_default
         FROM customer_addresses
         WHERE customer_id IN ({$placeholders})
         ORDER BY customer_id, is_default DESC, id DESC"
    );
    $addressStmt->execute($customerIds);

    foreach ($addressStmt->fetchAll(PDO::FETCH_ASSOC) as $address) {
        $addressesByCustomer[(int)$address['customer_id']][] = $address;
    }
}

$flashType = trim((string)($_GET['flash_type'] ?? ''));
$flashMessage = trim((string)($_GET['flash_message'] ?? ''));

require __DIR__ . '/includes/header.php';
?>

<?php if ($flashMessage !== ''): ?>
    <?php
    $toastType = in_array(
        $flashType,
        ['success', 'danger', 'warning', 'info'],
        true
    ) ? $flashType : 'info';

    $toastClass = match ($toastType) {
        'success' => 'text-bg-success',
        'danger' => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default => 'text-bg-info',
    };

    $toastIcon = match ($toastType) {
        'success' => 'fa-circle-check',
        'danger' => 'fa-circle-exclamation',
        'warning' => 'fa-triangle-exclamation',
        default => 'fa-circle-info',
    };
    ?>

    <div
        class="toast-container position-fixed end-0 p-3"
        style="top:70px;z-index:2000;"
    >
        <div
            id="customerActionToast"
            class="toast align-items-center border-0 <?= e($toastClass); ?>"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            data-bs-autohide="true"
            data-bs-delay="4000"
        >
            <div class="d-flex align-items-center">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="fa-solid <?= e($toastIcon); ?>"></i>
                    <span><?= e($flashMessage); ?></span>
                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Close"
                ></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="ramki-card p-3">
    <div
        class="d-flex flex-column flex-xl-row
               justify-content-between align-items-xl-end
               gap-3 mb-3"
    >
        <div>
            <h2 class="h5 mb-1">Customer Management</h2>
            <p class="text-muted small mb-0">
                <?= number_format($totalCustomers); ?>
                customer<?= $totalCustomers === 1 ? '' : 's'; ?> found.
            </p>
        </div>

        <form
            method="get"
            action="<?= e(admin_url('customers.php')); ?>"
            class="row g-2 align-items-end"
        >
            <div class="col-sm-auto">
                <label class="form-label small" for="customerSearch">
                    Search
                </label>
                <input
                    type="search"
                    class="form-control"
                    id="customerSearch"
                    name="q"
                    value="<?= e($search); ?>"
                    placeholder="Name, email or phone"
                >
            </div>

            <div class="col-sm-auto">
                <label class="form-label small" for="customerStatusFilter">
                    Status
                </label>
                <select
                    class="form-select"
                    id="customerStatusFilter"
                    name="status"
                >
                    <option value="">All Statuses</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option
                            value="<?= e($status); ?>"
                            <?= $statusFilter === $status ? 'selected' : ''; ?>
                        >
                            <?= e(customer_status_label($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-sm-auto d-flex gap-2">
                <button type="submit" class="btn btn-ramki">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>
                    Search
                </button>

                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a
                        class="btn btn-outline-secondary"
                        href="<?= e(admin_url('customers.php')); ?>"
                        title="Clear filters"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="customersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Orders</th>
                    <th>Enquiries</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$customers): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="fa-regular fa-user fs-2 text-muted d-block mb-2"></i>
                            <strong>No customers found.</strong>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $index => $customer): ?>
                        <?php
                        $customerId = (int)$customer['id'];
                        $fullName = trim(
                            (string)$customer['first_name']
                            . ' '
                            . (string)($customer['last_name'] ?? '')
                        );

                        $phoneDigits = preg_replace(
                            '/\D+/',
                            '',
                            (string)$customer['phone']
                        ) ?: '';

                        $whatsappDigits = $phoneDigits;

                        if (strlen($whatsappDigits) === 10) {
                            $whatsappDigits = '91' . $whatsappDigits;
                        }

                        $payload = [
                            'id' => $customerId,
                            'first_name' => (string)$customer['first_name'],
                            'last_name' => (string)($customer['last_name'] ?? ''),
                            'full_name' => $fullName,
                            'email' => (string)($customer['email'] ?? ''),
                            'phone' => (string)$customer['phone'],
                            'status' => (string)$customer['status'],
                            'email_verified_at' => (string)($customer['email_verified_at'] ?? ''),
                            'phone_verified_at' => (string)($customer['phone_verified_at'] ?? ''),
                            'last_login_at' => (string)($customer['last_login_at'] ?? ''),
                            'created_at' => (string)$customer['created_at'],
                            'updated_at' => (string)$customer['updated_at'],
                            'address_count' => (int)$customer['address_count'],
                            'enquiry_count' => (int)$customer['enquiry_count'],
                            'order_count' => (int)$customer['order_count'],
                            'order_value' => (float)$customer['order_value'],
                            'addresses' => $addressesByCustomer[$customerId] ?? [],
                            'whatsapp_url' => $whatsappDigits !== ''
                                ? 'https://wa.me/' . $whatsappDigits
                                : '',
                        ];
                        ?>

                        <tr>
                            <td><?= $offset + $index + 1; ?></td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="rounded-circle d-inline-flex
                                               align-items-center justify-content-center
                                               text-white fw-semibold"
                                        style="width:38px;height:38px;background:var(--ramki-primary,#8f102d);"
                                    >
                                        <?= e(mb_strtoupper(mb_substr(
                                            (string)$customer['first_name'],
                                            0,
                                            1
                                        ))); ?>
                                    </span>

                                    <div>
                                        <strong><?= e($fullName); ?></strong>
                                        <div class="small text-muted">
                                            Customer #<?= $customerId; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <a href="tel:<?= e($phoneDigits); ?>">
                                    <?= e((string)$customer['phone']); ?>
                                </a>

                                <?php if (!empty($customer['email'])): ?>
                                    <div class="small text-muted">
                                        <?= e((string)$customer['email']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?= (int)$customer['order_count']; ?></strong>
                                <div class="small text-muted">
                                    ₹<?= number_format((float)$customer['order_value'], 2); ?>
                                </div>
                            </td>

                            <td>
                                <strong><?= (int)$customer['enquiry_count']; ?></strong>
                            </td>

                            <td>
                                <span
                                    class="badge <?= e(customer_status_class(
                                        (string)$customer['status']
                                    )); ?>"
                                >
                                    <?= e(customer_status_label(
                                        (string)$customer['status']
                                    )); ?>
                                </span>
                            </td>

                            <td class="text-nowrap">
                                <?php if (!empty($customer['last_login_at'])): ?>
                                    <?= e(date(
                                        'd-m-Y',
                                        strtotime((string)$customer['last_login_at'])
                                    )); ?>
                                    <div class="small text-muted">
                                        <?= e(date(
                                            'h:i A',
                                            strtotime((string)$customer['last_login_at'])
                                        )); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-nowrap">
                                <?= e(date(
                                    'd-m-Y',
                                    strtotime((string)$customer['created_at'])
                                )); ?>
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-ramki js-view-customer"
                                        data-customer="<?= e(json_encode(
                                            $payload,
                                            JSON_UNESCAPED_SLASHES
                                            | JSON_UNESCAPED_UNICODE
                                        )); ?>"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        View
                                    </button>

                                    <?php if ($whatsappDigits !== ''): ?>
                                        <a
                                            href="<?= e('https://wa.me/' . $whatsappDigits); ?>"
                                            class="btn btn-sm btn-outline-success"
                                            target="_blank"
                                            rel="noopener"
                                            title="WhatsApp customer"
                                            aria-label="WhatsApp customer"
                                        >
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($canDelete): ?>
                                        <form
                                            method="post"
                                            action="<?= e(customer_page_url(['page' => null])); ?>"
                                            class="d-inline"
                                            onsubmit="return confirm('Delete this customer account? Historical orders and enquiries will remain.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(csrf_token()); ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="form_action"
                                                value="delete"
                                            >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= $customerId; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete customer"
                                                aria-label="Delete customer"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Customer pages">
            <ul class="pagination pagination-sm justify-content-end mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                    <a
                        class="page-link"
                        href="<?= e(customer_page_url([
                            'page' => max(1, $page - 1),
                        ])); ?>"
                    >
                        Previous
                    </a>
                </li>

                <li class="page-item disabled">
                    <span class="page-link">
                        Page <?= $page; ?> of <?= $totalPages; ?>
                    </span>
                </li>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a
                        class="page-link"
                        href="<?= e(customer_page_url([
                            'page' => min($totalPages, $page + 1),
                        ])); ?>"
                    >
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form
            class="modal-content"
            id="customerForm"
            method="post"
            action="<?= e(customer_page_url(['page' => null])); ?>"
        >
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Customer Details</h5>
                    <small class="text-muted" id="modalCustomerNumber"></small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()); ?>"
                >
                <input type="hidden" name="form_action" value="update">
                <input type="hidden" name="id" id="modalCustomerId">

                <div class="row g-4">
                    <div class="col-lg-7">
                        <h6 class="mb-3">Profile</h6>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="modalFirstName">
                                    First Name
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="modalFirstName"
                                    name="first_name"
                                    maxlength="100"
                                    required
                                    <?= !$canEdit ? 'readonly' : ''; ?>
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="modalLastName">
                                    Last Name
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="modalLastName"
                                    name="last_name"
                                    maxlength="100"
                                    <?= !$canEdit ? 'readonly' : ''; ?>
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="modalPhone">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="modalPhone"
                                    name="phone"
                                    maxlength="20"
                                    required
                                    <?= !$canEdit ? 'readonly' : ''; ?>
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="modalEmail">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="modalEmail"
                                    name="email"
                                    maxlength="190"
                                    <?= !$canEdit ? 'readonly' : ''; ?>
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="modalCustomerStatus">
                                    Status
                                </label>
                                <select
                                    class="form-select"
                                    id="modalCustomerStatus"
                                    name="status"
                                    required
                                    <?= !$canEdit ? 'disabled' : ''; ?>
                                >
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= e($status); ?>">
                                            <?= e(customer_status_label($status)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Last Login</label>
                                <div class="form-control bg-body-tertiary" id="modalLastLogin">
                                    -
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Saved Addresses</h6>
                        <div id="modalCustomerAddresses"></div>
                    </div>

                    <div class="col-lg-5">
                        <div class="border rounded p-3 mb-3 bg-body-tertiary">
                            <h6 class="mb-3">Customer Activity</h6>

                            <div class="row g-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Orders</small>
                                    <strong class="fs-5" id="modalOrderCount">0</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Order Value</small>
                                    <strong class="fs-5" id="modalOrderValue">₹0.00</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Enquiries</small>
                                    <strong class="fs-5" id="modalEnquiryCount">0</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Addresses</small>
                                    <strong class="fs-5" id="modalAddressCount">0</strong>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <h6 class="mb-3">Account Information</h6>
                            <div class="small mb-2">
                                <span class="text-muted">Joined:</span>
                                <strong class="float-end" id="modalJoined">-</strong>
                            </div>
                            <div class="small mb-2">
                                <span class="text-muted">Email Verified:</span>
                                <strong class="float-end" id="modalEmailVerified">No</strong>
                            </div>
                            <div class="small">
                                <span class="text-muted">Phone Verified:</span>
                                <strong class="float-end" id="modalPhoneVerified">No</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <a
                    href="#"
                    class="btn btn-success"
                    id="customerWhatsApp"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="fa-brands fa-whatsapp me-2"></i>
                    WhatsApp
                </a>

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>

                <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-ramki">
                        <i class="fa-solid fa-floppy-disk me-2"></i>
                        Save Customer
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    'use strict';

    const customerModal = document.getElementById('customerModal');
    const customerToast = document.getElementById('customerActionToast');
    const idInput = document.getElementById('modalCustomerId');
    const numberText = document.getElementById('modalCustomerNumber');
    const firstNameInput = document.getElementById('modalFirstName');
    const lastNameInput = document.getElementById('modalLastName');
    const phoneInput = document.getElementById('modalPhone');
    const emailInput = document.getElementById('modalEmail');
    const statusInput = document.getElementById('modalCustomerStatus');
    const lastLoginText = document.getElementById('modalLastLogin');
    const orderCount = document.getElementById('modalOrderCount');
    const orderValue = document.getElementById('modalOrderValue');
    const enquiryCount = document.getElementById('modalEnquiryCount');
    const addressCount = document.getElementById('modalAddressCount');
    const joinedText = document.getElementById('modalJoined');
    const emailVerified = document.getElementById('modalEmailVerified');
    const phoneVerified = document.getElementById('modalPhoneVerified');
    const addresses = document.getElementById('modalCustomerAddresses');
    const whatsappLink = document.getElementById('customerWhatsApp');

    if (customerToast) {
        window.addEventListener('load', () => {
            if (window.bootstrap?.Toast) {
                bootstrap.Toast.getOrCreateInstance(customerToast).show();
                return;
            }

            customerToast.classList.add('show');
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        const text = String(value ?? '').trim();

        if (text === '') {
            return 'Never';
        }

        const date = new Date(text.replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return text;
        }

        return date.toLocaleString('en-IN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatMoney(value) {
        const amount = Number(value || 0);

        return `₹${Number.isFinite(amount)
            ? amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })
            : '0.00'}`;
    }

    function addressHtml(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return `
                <div class="text-muted small border rounded p-3">
                    No saved addresses.
                </div>
            `;
        }

        return list.map(address => {
            const addressLines = [
                address.address_line_1,
                address.address_line_2,
                address.landmark,
                address.city,
                address.district,
                address.state,
                address.postal_code,
                address.country
            ].filter(value => String(value ?? '').trim() !== '');

            return `
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <strong>${escapeHtml(address.contact_name || '-')}</strong>
                        <div class="d-flex gap-1">
                            <span class="badge text-bg-light border">
                                ${escapeHtml(address.address_type || 'shipping')}
                            </span>
                            ${Number(address.is_default) === 1
                                ? '<span class="badge bg-success">Default</span>'
                                : ''}
                        </div>
                    </div>
                    <div class="small">${escapeHtml(address.phone || '')}</div>
                    <div class="small text-muted mt-1">
                        ${addressLines.map(escapeHtml).join(', ')}
                    </div>
                </div>
            `;
        }).join('');
    }

    document
        .querySelectorAll('.js-view-customer')
        .forEach(button => {
            button.addEventListener('click', () => {
                let customer;

                try {
                    customer = JSON.parse(
                        button.dataset.customer || '{}'
                    );
                } catch (error) {
                    console.error('Unable to parse customer details.', error);
                    return;
                }

                idInput.value = customer.id || '';
                numberText.textContent = `Customer #${customer.id || ''}`;
                firstNameInput.value = customer.first_name || '';
                lastNameInput.value = customer.last_name || '';
                phoneInput.value = customer.phone || '';
                emailInput.value = customer.email || '';
                statusInput.value = customer.status || 'active';
                lastLoginText.textContent = formatDate(customer.last_login_at);
                orderCount.textContent = Number(customer.order_count || 0);
                orderValue.textContent = formatMoney(customer.order_value);
                enquiryCount.textContent = Number(customer.enquiry_count || 0);
                addressCount.textContent = Number(customer.address_count || 0);
                joinedText.textContent = formatDate(customer.created_at);
                emailVerified.textContent = customer.email_verified_at ? 'Yes' : 'No';
                phoneVerified.textContent = customer.phone_verified_at ? 'Yes' : 'No';
                addresses.innerHTML = addressHtml(customer.addresses);

                whatsappLink.href = customer.whatsapp_url || '#';
                whatsappLink.classList.toggle(
                    'disabled',
                    !customer.whatsapp_url
                );

                bootstrap.Modal
                    .getOrCreateInstance(customerModal)
                    .show();
            });
        });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
