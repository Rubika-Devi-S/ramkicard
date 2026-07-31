<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalog-common.php';

$rangePermissions = catalog_permissions($pdo, 'price_ranges');

if (!$rangePermissions['can_view']) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Price Ranges';
$pageScript = 'price-ranges.js';

require __DIR__ . '/includes/header.php';
?>

<div
    class="ramki-card p-3"
    id="priceRangeModule"
    data-can-add="<?= $rangePermissions['can_add'] ? '1' : '0'; ?>"
    data-can-edit="<?= $rangePermissions['can_edit'] ? '1' : '0'; ?>"
    data-can-delete="<?= $rangePermissions['can_delete'] ? '1' : '0'; ?>"
>
    <div id="rangeMessage"></div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Product Price Ranges</h2>
            <p class="text-muted small mb-0">Used for website product filtering.</p>
        </div>

        <?php if ($rangePermissions['can_add']): ?>
            <button class="btn btn-ramki" id="addRangeBtn" type="button">
                <i class="fa-solid fa-plus me-2"></i>Add Price Range
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="rangesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Range</th>
                    <th>Minimum</th>
                    <th>Maximum</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">Loading price ranges...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="rangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="rangeForm">
            <div class="modal-header">
                <h5 class="modal-title">Add Price Range</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="save">

                <div class="mb-3">
                    <label class="form-label">Range Name *</label>
                    <input class="form-control" name="range_name" placeholder="₹20 - ₹40" maxlength="100" required>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Minimum Price *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="minimum_price" required>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Maximum Price</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="maximum_price">
                    </div>

                    <div class="col-6">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" class="form-control" name="sort_order" value="0">
                    </div>

                    <div class="col-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-ramki" type="submit" id="saveRangeBtn">Save Range</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
