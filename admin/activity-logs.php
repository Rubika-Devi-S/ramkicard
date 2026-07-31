<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'activity_logs')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Activity Logs';
$pageScript = 'activity-logs.js';

require __DIR__ . '/includes/header.php';
?>

<div class="ramki-card p-3">
    <div class="mb-3">
        <h2 class="h5 mb-1">Business Activity Logs</h2>
        <p class="text-muted small mb-0">
            Track login, create, update, delete and status actions.
        </p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover w-100" id="logsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Admin</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
