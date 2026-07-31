<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'enquiries')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Enquiries';

/*
|--------------------------------------------------------------------------
| Do not load the old AJAX enquiries.js
|--------------------------------------------------------------------------
| This page renders directly from the enquiries table so a missing or failing
| JavaScript/API file can no longer leave the table blank.
*/

$pageScript = null;

$allowedStatuses = [
    'new',
    'contacted',
    'quotation_sent',
    'converted',
    'closed',
    'rejected',
];

function enquiry_page_extract(
    string $message,
    string $label
): string {
    $pattern = '/^'
        . preg_quote($label, '/')
        . '\s*:\s*(.+)$/mi';

    if (preg_match($pattern, $message, $matches) === 1) {
        return trim((string)$matches[1]);
    }

    return '';
}

function enquiry_page_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function enquiry_page_status_class(string $status): string
{
    return match ($status) {
        'new' => 'bg-danger',
        'contacted' => 'bg-info text-dark',
        'quotation_sent' => 'bg-warning text-dark',
        'converted' => 'bg-success',
        'closed' => 'bg-secondary',
        'rejected' => 'bg-dark',
        default => 'bg-secondary',
    };
}

function enquiry_page_redirect(
    string $type,
    string $message,
    string $statusFilter = ''
): never {
    $query = http_build_query([
        'flash_type' => $type,
        'flash_message' => $message,
        'status' => $statusFilter,
    ]);

    header('Location: enquiries.php?' . $query);
    exit;
}

$currentStatusFilter = trim((string)($_GET['status'] ?? ''));

if (
    $currentStatusFilter !== ''
    && !in_array($currentStatusFilter, $allowedStatuses, true)
) {
    $currentStatusFilter = '';
}

/*
|--------------------------------------------------------------------------
| Update enquiry status and notes
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');

    if (
        $postedToken === ''
        || !hash_equals((string)csrf_token(), $postedToken)
    ) {
        enquiry_page_redirect(
            'danger',
            'The page session expired. Refresh and try again.',
            $currentStatusFilter
        );
    }

    $enquiryId = max(0, (int)($_POST['id'] ?? 0));
    $newStatus = trim((string)($_POST['status'] ?? ''));
    $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));

    if ($enquiryId <= 0) {
        enquiry_page_redirect(
            'danger',
            'Invalid enquiry.',
            $currentStatusFilter
        );
    }

    if (!in_array($newStatus, $allowedStatuses, true)) {
        enquiry_page_redirect(
            'danger',
            'Invalid enquiry status.',
            $currentStatusFilter
        );
    }

    if (mb_strlen($adminNotes) > 5000) {
        enquiry_page_redirect(
            'danger',
            'Admin notes are too long.',
            $currentStatusFilter
        );
    }

    try {
        $pdo->beginTransaction();

        $select = $pdo->prepare(
            "SELECT status
             FROM enquiries
             WHERE id = :id
             LIMIT 1
             FOR UPDATE"
        );

        $select->execute(['id' => $enquiryId]);
        $oldStatus = (string)$select->fetchColumn();

        if ($oldStatus === '') {
            throw new RuntimeException('Enquiry not found.');
        }

        $update = $pdo->prepare(
            "UPDATE enquiries
             SET status = :status,
                 admin_notes = :admin_notes,
                 assigned_admin_id = COALESCE(
                     assigned_admin_id,
                     :assigned_admin_id
                 ),
                 last_contacted_at = CASE
                     WHEN :status_value = 'contacted'
                     THEN NOW()
                     ELSE last_contacted_at
                 END
             WHERE id = :id"
        );

        $adminId = function_exists('current_admin_id')
            ? (int)(current_admin_id() ?? 0)
            : (int)($_SESSION['ramki_admin']['id'] ?? 0);

        $update->execute([
            'status' => $newStatus,
            'admin_notes' =>
                $adminNotes !== '' ? $adminNotes : null,
            'assigned_admin_id' =>
                $adminId > 0 ? $adminId : null,
            'status_value' => $newStatus,
            'id' => $enquiryId,
        ]);

        if (function_exists('activity_log')) {
            activity_log(
                $pdo,
                'update',
                'Enquiries',
                'enquiry',
                $enquiryId,
                sprintf(
                    'Enquiry status changed from %s to %s.',
                    $oldStatus,
                    $newStatus
                )
            );
        }

        $pdo->commit();

        enquiry_page_redirect(
            'success',
            'Enquiry updated successfully.',
            $currentStatusFilter
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Admin enquiry update failed: '
            . $exception->getMessage()
        );

        enquiry_page_redirect(
            'danger',
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Unable to update the enquiry.',
            $currentStatusFilter
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load enquiry rows directly from the same shared database
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT
        e.id,
        e.enquiry_number,
        e.customer_id,
        e.customer_name,
        e.customer_email,
        e.customer_phone,
        e.source,
        e.status,
        e.subject,
        e.message,
        e.admin_notes,
        e.assigned_admin_id,
        e.created_at,
        e.updated_at,
        u.name AS assigned_admin_name
     FROM enquiries e
     LEFT JOIN admin_users u
        ON u.id = e.assigned_admin_id";

$params = [];

if ($currentStatusFilter !== '') {
    $sql .= " WHERE e.status = :status";
    $params['status'] = $currentStatusFilter;
}

$sql .= " ORDER BY e.created_at DESC, e.id DESC LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flashType = trim((string)($_GET['flash_type'] ?? ''));
$flashMessage = trim((string)($_GET['flash_message'] ?? ''));

require __DIR__ . '/includes/header.php';
?>

<?php if ($flashMessage !== ''): ?>
    <div
        class="alert alert-<?= e(
            in_array(
                $flashType,
                ['success', 'danger', 'warning', 'info'],
                true
            )
                ? $flashType
                : 'info'
        ); ?> alert-dismissible fade show"
        role="alert"
    >
        <?= e($flashMessage); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
<?php endif; ?>

<div class="ramki-card p-3">

    <div
        class="d-flex flex-column flex-md-row
               justify-content-between align-items-md-center
               gap-3 mb-3"
    >
        <div>
            <h2 class="h5 mb-1">
                Website Enquiries
            </h2>

            <p class="text-muted small mb-0">
                <?= count($enquiries); ?>
                enquiry<?= count($enquiries) === 1 ? '' : 'ies'; ?>
                loaded directly from the shared enquiries table.
            </p>
        </div>

        <form method="GET" action="enquiries.php">
            <select
                class="form-select"
                id="enquiryStatusFilter"
                name="status"
                onchange="this.form.submit()"
            >
                <option value="">
                    All Statuses
                </option>

                <?php foreach ($allowedStatuses as $status): ?>
                    <option
                        value="<?= e($status); ?>"
                        <?= $currentStatusFilter === $status
                            ? 'selected'
                            : ''; ?>
                    >
                        <?= e(enquiry_page_status_label($status)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="table-responsive">

        <table
            class="table table-hover align-middle w-100"
            id="enquiriesTable"
        >
            <thead>
                <tr>
                    <th>#</th>
                    <th>Enquiry</th>
                    <th>Customer</th>
                    <th>Event</th>
                    <th>Event Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$enquiries): ?>

                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i
                                class="fa-regular fa-folder-open
                                       fs-2 text-muted d-block mb-2"
                            ></i>

                            <strong>No enquiries found.</strong>

                            <div class="small text-muted mt-1">
                                Current database:
                                <?= e((string)$pdo->query(
                                    'SELECT DATABASE()'
                                )->fetchColumn()); ?>
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($enquiries as $index => $enquiry): ?>

                        <?php
                        $message = (string)($enquiry['message'] ?? '');

                        $event = enquiry_page_extract(
                            $message,
                            'Event'
                        );

                        if ($event === '') {
                            $event = preg_replace(
                                '/\s+enquiry$/i',
                                '',
                                (string)($enquiry['subject'] ?? '')
                            ) ?: '-';
                        }

                        $eventDate = enquiry_page_extract(
                            $message,
                            'Event date'
                        );

                        $location = enquiry_page_extract(
                            $message,
                            'Location'
                        );

                        $phoneDigits = preg_replace(
                            '/\D+/',
                            '',
                            (string)$enquiry['customer_phone']
                        ) ?: '';

                        if (strlen($phoneDigits) === 10) {
                            $phoneDigits = '91' . $phoneDigits;
                        }

                        $whatsappText = rawurlencode(
                            'Hello '
                            . (string)$enquiry['customer_name']
                            . ', regarding your Ramki Cards enquiry '
                            . (string)$enquiry['enquiry_number']
                            . '.'
                        );

                        $whatsappUrl =
                            'https://wa.me/'
                            . $phoneDigits
                            . '?text='
                            . $whatsappText;

                        $modalPayload = [
                            'id' => (int)$enquiry['id'],
                            'enquiry_number' =>
                                (string)$enquiry['enquiry_number'],
                            'customer_name' =>
                                (string)$enquiry['customer_name'],
                            'customer_email' =>
                                (string)($enquiry['customer_email'] ?? ''),
                            'customer_phone' =>
                                (string)$enquiry['customer_phone'],
                            'source' =>
                                (string)$enquiry['source'],
                            'status' =>
                                (string)$enquiry['status'],
                            'subject' =>
                                (string)($enquiry['subject'] ?? ''),
                            'message' =>
                                $message,
                            'admin_notes' =>
                                (string)($enquiry['admin_notes'] ?? ''),
                            'event' => $event,
                            'event_date' =>
                                $eventDate !== '' ? $eventDate : '-',
                            'location' =>
                                $location !== '' ? $location : '-',
                            'submitted' => date(
                                'd-m-Y h:i A',
                                strtotime(
                                    (string)$enquiry['created_at']
                                )
                            ),
                            'assigned_admin' =>
                                (string)(
                                    $enquiry['assigned_admin_name']
                                    ?? ''
                                ),
                            'whatsapp_url' => $whatsappUrl,
                        ];
                        ?>

                        <tr>
                            <td><?= $index + 1; ?></td>

                            <td>
                                <strong>
                                    <?= e($enquiry['enquiry_number']); ?>
                                </strong>

                                <div class="small text-muted">
                                    <?= e(ucfirst(
                                        (string)$enquiry['source']
                                    )); ?>
                                </div>
                            </td>

                            <td>
                                <strong>
                                    <?= e($enquiry['customer_name']); ?>
                                </strong>

                                <div class="small">
                                    <a
                                        href="tel:<?= e($phoneDigits); ?>"
                                    >
                                        <?= e(
                                            $enquiry['customer_phone']
                                        ); ?>
                                    </a>
                                </div>

                                <?php if (
                                    !empty($enquiry['customer_email'])
                                ): ?>
                                    <div class="small text-muted">
                                        <?= e(
                                            $enquiry['customer_email']
                                        ); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td><?= e($event); ?></td>

                            <td>
                                <?= e(
                                    $eventDate !== ''
                                        ? $eventDate
                                        : '-'
                                ); ?>
                            </td>

                            <td>
                                <?= e(
                                    $location !== ''
                                        ? $location
                                        : '-'
                                ); ?>
                            </td>

                            <td>
                                <span
                                    class="badge <?= e(
                                        enquiry_page_status_class(
                                            (string)$enquiry['status']
                                        )
                                    ); ?>"
                                >
                                    <?= e(enquiry_page_status_label(
                                        (string)$enquiry['status']
                                    )); ?>
                                </span>
                            </td>

                            <td>
                                <?= e(date(
                                    'd-m-Y',
                                    strtotime(
                                        (string)$enquiry['created_at']
                                    )
                                )); ?>

                                <div class="small text-muted">
                                    <?= e(date(
                                        'h:i A',
                                        strtotime(
                                            (string)$enquiry['created_at']
                                        )
                                    )); ?>
                                </div>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-ramki
                                           js-view-enquiry"
                                    data-enquiry="<?= e(json_encode(
                                        $modalPayload,
                                        JSON_UNESCAPED_SLASHES
                                        | JSON_UNESCAPED_UNICODE
                                    )); ?>"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                    View
                                </button>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

<div
    class="modal fade"
    id="enquiryModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">

        <form
            class="modal-content"
            id="enquiryForm"
            method="POST"
            action="enquiries.php?status=<?= e(
                $currentStatusFilter
            ); ?>"
        >
            <div class="modal-header">

                <h5 class="modal-title">
                    Enquiry Details
                </h5>

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

                <input
                    type="hidden"
                    name="id"
                    id="modalEnquiryId"
                >

                <div
                    id="enquiryDetails"
                    class="mb-4"
                ></div>

                <div class="row g-3">

                    <div class="col-md-5">

                        <label
                            class="form-label"
                            for="modalEnquiryStatus"
                        >
                            Status
                        </label>

                        <select
                            class="form-select"
                            name="status"
                            id="modalEnquiryStatus"
                            required
                        >
                            <?php foreach (
                                $allowedStatuses as $status
                            ): ?>
                                <option value="<?= e($status); ?>">
                                    <?= e(
                                        enquiry_page_status_label(
                                            $status
                                        )
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-7">

                        <label
                            class="form-label"
                            for="modalAdminNotes"
                        >
                            Admin Notes
                        </label>

                        <textarea
                            class="form-control"
                            rows="4"
                            name="admin_notes"
                            id="modalAdminNotes"
                            maxlength="5000"
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">

                <a
                    href="#"
                    class="btn btn-success"
                    id="enquiryWhatsApp"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="fa-brands fa-whatsapp me-2"></i>
                    WhatsApp
                </a>

                <button
                    class="btn btn-ramki"
                    type="submit"
                >
                    Update Enquiry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    'use strict';

    const modalElement =
        document.getElementById('enquiryModal');

    const details =
        document.getElementById('enquiryDetails');

    const idInput =
        document.getElementById('modalEnquiryId');

    const statusInput =
        document.getElementById('modalEnquiryStatus');

    const notesInput =
        document.getElementById('modalAdminNotes');

    const whatsappLink =
        document.getElementById('enquiryWhatsApp');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    document
        .querySelectorAll('.js-view-enquiry')
        .forEach(button => {
            button.addEventListener('click', () => {
                let enquiry;

                try {
                    enquiry = JSON.parse(
                        button.dataset.enquiry || '{}'
                    );
                } catch (error) {
                    console.error(
                        'Unable to parse enquiry details.',
                        error
                    );
                    return;
                }

                idInput.value = enquiry.id || '';
                statusInput.value = enquiry.status || 'new';
                notesInput.value = enquiry.admin_notes || '';

                whatsappLink.href =
                    enquiry.whatsapp_url || '#';

                details.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Enquiry Number
                            </small>
                            <strong>
                                ${escapeHtml(enquiry.enquiry_number)}
                            </strong>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Submitted
                            </small>
                            <strong>
                                ${escapeHtml(enquiry.submitted)}
                            </strong>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Customer
                            </small>
                            <strong>
                                ${escapeHtml(enquiry.customer_name)}
                            </strong>
                            <div>
                                ${escapeHtml(enquiry.customer_phone)}
                            </div>
                            <div class="text-muted">
                                ${escapeHtml(enquiry.customer_email)}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-muted d-block">
                                Event
                            </small>
                            <strong>
                                ${escapeHtml(enquiry.event)}
                            </strong>
                            <div>
                                ${escapeHtml(enquiry.event_date)}
                            </div>
                            <div>
                                ${escapeHtml(enquiry.location)}
                            </div>
                        </div>

                        <div class="col-12">
                            <small class="text-muted d-block">
                                Customer Message
                            </small>
                            <pre class="mb-0 p-3 rounded border bg-body-tertiary"
                                 style="white-space:pre-wrap;font-family:inherit;">${escapeHtml(enquiry.message || '-')}</pre>
                        </div>
                    </div>
                `;

                const modal =
                    bootstrap.Modal.getOrCreateInstance(
                        modalElement
                    );

                modal.show();
            });
        });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
