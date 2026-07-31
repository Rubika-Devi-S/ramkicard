<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalog-common.php';

$categoryPermissions = catalog_permissions(
    $pdo,
    'product_categories'
);

if (!$categoryPermissions['can_view']) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Categories';
$pageScript = 'categories.js';

require __DIR__ . '/includes/header.php';
?>

<div
    class="ramki-card p-3"
    id="categoryModule"
    data-can-add="<?= $categoryPermissions['can_add'] ? '1' : '0'; ?>"
    data-can-edit="<?= $categoryPermissions['can_edit'] ? '1' : '0'; ?>"
    data-can-delete="<?= $categoryPermissions['can_delete'] ? '1' : '0'; ?>"
>
    <div id="categoryMessage"></div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Invitation Card Categories</h2>
            <p class="text-muted small mb-0">
                Manage Wedding, Ear Piercing, Engagement, Birthday and other categories.
            </p>
        </div>

        <?php if ($categoryPermissions['can_add']): ?>
            <button class="btn btn-ramki" id="addCategoryBtn" type="button">
                <i class="fa-solid fa-plus me-2"></i>Add Category
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle w-100" id="categoriesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Image</th>
                    <th>Category</th>
                    <th>Slug</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Sort</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        Loading categories...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="categoryForm" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="save">

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Category Name *</label>
                        <input class="form-control" name="category_name" maxlength="150" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Sort Order</label>
                        <input type="number" min="0" class="form-control" name="sort_order" value="0">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Category Image</label>
                        <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-text">JPG, PNG or WEBP. Maximum 5 MB.</div>
                    </div>

                    <div class="col-12 d-none" id="currentCategoryImageWrap">
                        <label class="form-label">Current Image</label>
                        <div>
                            <img id="currentCategoryImage" alt="Current category" style="width:110px;height:80px;object-fit:cover;border-radius:10px;">
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="4" name="description" maxlength="5000"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-ramki" type="submit" id="saveCategoryBtn">Save Category</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
