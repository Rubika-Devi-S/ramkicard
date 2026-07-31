<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'admin_users')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Admin Users';
$pageScript = 'admin-users.js';

require __DIR__ . '/includes/header.php';
?>

<div class="ramki-card p-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Administrator Accounts</h2>
            <p class="text-muted small mb-0">Create staff accounts and assign roles.</p>
        </div>
        <button class="btn btn-ramki" id="addAdminBtn">
            <i class="fa-solid fa-plus me-2"></i>Add Admin
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover w-100" id="adminsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="adminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="adminForm">
            <div class="modal-header">
                <h5 class="modal-title">Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="save">

                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input class="form-control" name="name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" name="email" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone">
                </div>

                <div class="mb-3">
                    <label class="form-label">Role *</label>
                    <select class="form-select" name="role_id" id="adminRole" required></select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Password
                        <small class="text-muted">(leave blank while editing)</small>
                    </label>
                    <input type="password" class="form-control" name="password">
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ramki" type="submit">Save Admin</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
