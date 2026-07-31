<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'role_permissions')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Role Permissions';
$pageScript = 'roles-permissions.js';

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ramki-card p-3">
            <h2 class="h5">Select Role</h2>
            <select class="form-select" id="roleSelect"></select>
            <p class="small text-muted mt-3 mb-0">
                Super Admin automatically receives complete access.
            </p>
        </div>
    </div>

    <div class="col-lg-8">
        <form class="ramki-card p-3" id="permissionsForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="role_id" id="permissionRoleId">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Menu Permissions</h2>
                <button class="btn btn-ramki" type="submit">Save Permissions</button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>View</th>
                            <th>Add</th>
                            <th>Edit</th>
                            <th>Delete</th>
                            <th>Approve</th>
                            <th>Export</th>
                        </tr>
                    </thead>
                    <tbody id="permissionsBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Select a role.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
