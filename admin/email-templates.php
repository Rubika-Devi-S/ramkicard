<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'email_templates')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Email Templates';
$pageScript = 'email-templates.js';

require __DIR__ . '/includes/header.php';
?>

<div class="ramki-card p-3">
    <div class="mb-3">
        <h2 class="h5 mb-1">Email Templates</h2>
        <p class="text-muted small mb-0">Edit enquiry, order and payment messages.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover w-100" id="templatesTable">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Subject</th><th>Status</th><th>Action</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="templateForm">
            <div class="modal-header">
                <h5 class="modal-title">Edit Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="form-label">Subject</label>
                    <input class="form-control" name="email_subject" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">HTML Body</label>
                    <textarea class="form-control font-monospace" rows="10" name="body_html" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="small text-muted" id="templateVariables"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ramki" type="submit">Save Template</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
