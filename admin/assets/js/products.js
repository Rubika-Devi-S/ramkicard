(() => {
    'use strict';

    const module = document.getElementById('productModule');
    if (!module) return;

    const tbody = document.querySelector('#productsTable tbody');
    const form = document.getElementById('productForm');
    const modalElement = document.getElementById('productModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const messageBox = document.getElementById('productMessage');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const categorySelect = document.getElementById('productCategory');
    const rangeSelect = document.getElementById('productPriceRange');
    const filterCategory = document.getElementById('filterCategory');

    const permissions = {
        add: module.dataset.canAdd === '1',
        edit: module.dataset.canEdit === '1',
        delete: module.dataset.canDelete === '1',
    };

    let optionsLoaded = false;

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const money = value => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));

    function notify(message, type = 'success') {
        messageBox.innerHTML = `<div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
            ...options,
        });
        const payload = await response.json().catch(() => null);
        if (!payload || !response.ok || !payload.success) {
            if (response.status === 401 && payload?.data?.redirect) {
                window.location.href = payload.data.redirect;
                return;
            }
            throw new Error(payload?.message || 'Request failed.');
        }
        return payload.data;
    }

    async function loadOptions() {
        const data = await request('api/products.php?action=options');
        const categories = data.categories || [];
        const ranges = data.price_ranges || [];

        categorySelect.innerHTML = '<option value="">Select Category</option>' + categories.map(row =>
            `<option value="${row.id}">${escapeHtml(row.category_name)}${row.status !== 'active' ? ' (Inactive)' : ''}</option>`
        ).join('');

        filterCategory.innerHTML = '<option value="">All Categories</option>' + categories.map(row =>
            `<option value="${row.id}">${escapeHtml(row.category_name)}</option>`
        ).join('');

        rangeSelect.innerHTML = '<option value="">No Price Range</option>' + ranges.map(row =>
            `<option value="${row.id}">${escapeHtml(row.range_name)}${row.status !== 'active' ? ' (Inactive)' : ''}</option>`
        ).join('');

        optionsLoaded = true;
    }

    function renderProducts(rows) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No products found.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row, index) => {
            const effective = row.offer_price !== null && row.offer_price !== '' ? row.offer_price : row.base_price;
            const image = row.thumbnail_url
                ? `<img src="${escapeHtml(row.thumbnail_url)}" alt="" style="width:62px;height:52px;object-fit:cover;border-radius:8px;">`
                : '';
            const actions = [
                `<a
                    class="btn btn-sm btn-outline-secondary"
                    href="product-view.php?id=${Number(row.id)}"
                    title="View separate product page"
                ><i class="fa-solid fa-eye"></i></a>`,
                permissions.edit ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-product" data-id="${row.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : '',
                permissions.delete ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-product" data-id="${row.id}" data-name="${escapeHtml(row.product_name)}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : '',
            ].join(' ');

            return `<tr>
                <td>${index + 1}</td>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        ${image}
                        <div>
                            <strong>
                                ${escapeHtml(row.product_name)}
                            </strong>
                            ${
                                row.product_name_tamil
                                    ? `<div
                                        class="small fw-semibold"
                                        lang="ta"
                                    >${escapeHtml(
                                        row.product_name_tamil
                                    )}</div>`
                                    : ''
                            }
                            <div class="small text-muted">
                                ${escapeHtml(
                                    row.sku
                                    || row.slug
                                    || ''
                                )}${
                                    row.is_featured
                                        ? ' · Featured'
                                        : ''
                                }
                            </div>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(row.category_name)}</td>
                <td><strong>${money(effective)}</strong>${row.offer_price !== null && row.offer_price !== '' ? `<div class="small text-muted text-decoration-line-through">${money(row.base_price)}</div>` : ''}</td>
                <td>${Number(row.minimum_order_qty)} <div class="small text-muted">Step ${Number(row.quantity_step)}</div></td>
                <td>${Number(row.color_count)} colours<br>${Number(row.design_count)} designs<br><span class="small text-muted">${Number(row.image_count)} gallery</span></td>
                <td>${escapeHtml(row.purchase_action)}</td>
                <td><span class="badge ${row.status === 'active' ? 'bg-success' : row.status === 'draft' ? 'bg-warning text-dark' : 'bg-secondary'}">${escapeHtml(row.status)}</span></td>
                <td><div class="d-flex gap-1">${actions || '<span class="text-muted">View only</span>'}</div></td>
            </tr>`;
        }).join('');
    }

    async function loadProducts() {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Loading products...</td></tr>';
        const params = new URLSearchParams({ action: 'list' });
        if (filterCategory.value) params.set('category_id', filterCategory.value);
        if (document.getElementById('filterStatus').value) params.set('status', document.getElementById('filterStatus').value);

        try {
            const data = await request(`api/products.php?${params.toString()}`);
            renderProducts(data.rows || []);
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function clearProductForm() {
        form.reset();
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="minimum_order_qty"]').value = '1';
        form.querySelector('[name="quantity_step"]').value = '1';
        form.querySelector('[name="purchase_action"]').value = 'inherit';
        form.querySelector('[name="status"]').value = 'draft';
        document.getElementById('colorRows').innerHTML = '';
        document.getElementById('designRows').innerHTML = '';
        document.getElementById('existingImages').innerHTML = '';
        document.getElementById('existingImagesWrap').classList.add('d-none');
        document.getElementById('currentThumbnailWrap').classList.add('d-none');
        document.getElementById('currentThumbnail').removeAttribute('src');
        modalElement.querySelector('.modal-title').textContent = 'Add Product';
    }

    function addColorRow(data = {}) {
        const container = document.getElementById('colorRows');
        const row = document.createElement('div');
        row.className = 'variant-row border rounded p-2 mb-2';
        row.dataset.variant = 'color';
        row.innerHTML = `
            <input type="hidden" name="color_id[]" value="${escapeHtml(data.id || '')}">
            <input type="hidden" name="color_existing_image[]" value="${escapeHtml(data.image_path || '')}">
            <div class="row g-2">
                <div class="col-md-5"><input class="form-control form-control-sm" name="color_name[]" placeholder="Colour name *" maxlength="100" value="${escapeHtml(data.color_name || '')}"></div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="color_code[]" placeholder="#AABBCC" maxlength="7" value="${escapeHtml(data.color_code || '')}"></div>
                <div class="col-md-3"><input type="number" step="0.01" class="form-control form-control-sm" name="color_price_adjustment[]" placeholder="Price +/-" value="${escapeHtml(data.price_adjustment || '0.00')}"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-variant"><i class="fa-solid fa-xmark"></i></button></div>
                <div class="col-md-4"><input type="number" min="0" class="form-control form-control-sm" name="color_sort_order[]" placeholder="Sort" value="${escapeHtml(data.sort_order || 0)}"></div>
                <div class="col-md-4"><select class="form-select form-select-sm" name="color_status[]"><option value="active" ${data.status !== 'inactive' ? 'selected' : ''}>Active</option><option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option></select></div>
                <div class="col-md-4"><input type="file" class="form-control form-control-sm variant-image" accept=".jpg,.jpeg,.png,.webp"></div>
                ${data.image_url ? `<div class="col-12"><img src="${escapeHtml(data.image_url)}" alt="" style="width:64px;height:50px;object-fit:cover;border-radius:6px;"></div>` : ''}
            </div>`;
        container.appendChild(row);
        reindexVariants();
    }

    function addDesignRow(data = {}) {
        const container = document.getElementById('designRows');
        const row = document.createElement('div');
        row.className = 'variant-row border rounded p-2 mb-2';
        row.dataset.variant = 'design';
        row.innerHTML = `
            <input type="hidden" name="design_id[]" value="${escapeHtml(data.id || '')}">
            <input type="hidden" name="design_existing_image[]" value="${escapeHtml(data.image_path || '')}">
            <div class="row g-2">
                <div class="col-md-5"><input class="form-control form-control-sm" name="design_name[]" placeholder="Design name *" maxlength="150" value="${escapeHtml(data.design_name || '')}"></div>
                <div class="col-md-3"><input class="form-control form-control-sm" name="design_code[]" placeholder="Code" maxlength="100" value="${escapeHtml(data.design_code || '')}"></div>
                <div class="col-md-3"><input type="number" step="0.01" class="form-control form-control-sm" name="design_price_adjustment[]" placeholder="Price +/-" value="${escapeHtml(data.price_adjustment || '0.00')}"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-variant"><i class="fa-solid fa-xmark"></i></button></div>
                <div class="col-12"><textarea class="form-control form-control-sm" name="design_description[]" rows="2" placeholder="Description">${escapeHtml(data.description || '')}</textarea></div>
                <div class="col-md-4"><input type="number" min="0" class="form-control form-control-sm" name="design_sort_order[]" placeholder="Sort" value="${escapeHtml(data.sort_order || 0)}"></div>
                <div class="col-md-4"><select class="form-select form-select-sm" name="design_status[]"><option value="active" ${data.status !== 'inactive' ? 'selected' : ''}>Active</option><option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>Inactive</option></select></div>
                <div class="col-md-4"><input type="file" class="form-control form-control-sm variant-image" accept=".jpg,.jpeg,.png,.webp"></div>
                ${data.image_url ? `<div class="col-12"><img src="${escapeHtml(data.image_url)}" alt="" style="width:64px;height:50px;object-fit:cover;border-radius:6px;"></div>` : ''}
            </div>`;
        container.appendChild(row);
        reindexVariants();
    }

    function reindexVariants() {
        document.querySelectorAll('#colorRows .variant-row').forEach((row, index) => {
            row.querySelector('.variant-image').name = `color_image_${index}`;
        });
        document.querySelectorAll('#designRows .variant-row').forEach((row, index) => {
            row.querySelector('.variant-image').name = `design_image_${index}`;
        });
    }

    document.getElementById('addColorRow').addEventListener('click', () => addColorRow());
    document.getElementById('addDesignRow').addEventListener('click', () => addDesignRow());

    document.addEventListener('click', event => {
        const button = event.target.closest('.js-remove-variant');
        if (!button) return;
        button.closest('.variant-row')?.remove();
        reindexVariants();
    });

    function renderExistingImages(images) {
        const wrap = document.getElementById('existingImagesWrap');
        const container = document.getElementById('existingImages');
        container.innerHTML = '';

        if (!images.length) {
            wrap.classList.add('d-none');
            return;
        }

        wrap.classList.remove('d-none');
        container.innerHTML = images.map(image => `
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 h-100">
                    <img src="${escapeHtml(image.image_url)}" alt="" class="w-100 mb-2" style="height:90px;object-fit:cover;border-radius:6px;">
                    <label class="form-check small text-danger">
                        <input class="form-check-input" type="checkbox" name="remove_image_ids[]" value="${image.id}">
                        Remove on save
                    </label>
                </div>
            </div>`).join('');
    }

    document.getElementById('addProductBtn')?.addEventListener('click', async () => {
        try {
            if (!optionsLoaded) await loadOptions();
            clearProductForm();
            modal.show();
        } catch (error) {
            notify(error.message, 'danger');
        }
    });

    tbody.addEventListener('click', async event => {
        const editButton = event.target.closest('.js-edit-product');
        const deleteButton = event.target.closest('.js-delete-product');

        if (editButton) {
            try {
                if (!optionsLoaded) await loadOptions();
                const data = await request(`api/products.php?action=get&id=${encodeURIComponent(editButton.dataset.id)}`);
                const product = data.product;
                clearProductForm();

                form.querySelector('[name="id"]').value = product.id;
                categorySelect.value = product.category_id || '';
                rangeSelect.value = product.price_range_id || '';
                form.querySelector(
                    '[name="product_name"]'
                ).value = product.product_name || '';

                form.querySelector(
                    '[name="product_name_tamil"]'
                ).value = product.product_name_tamil || '';

                form.querySelector('[name="sku"]').value = product.sku || '';
                form.querySelector('[name="short_description"]').value = product.short_description || '';
                form.querySelector('[name="description"]').value = product.description || '';
                form.querySelector('[name="base_price"]').value = product.base_price || 0;
                form.querySelector('[name="offer_price"]').value = product.offer_price ?? '';
                form.querySelector('[name="minimum_order_qty"]').value = product.minimum_order_qty || 1;
                form.querySelector('[name="quantity_step"]').value = product.quantity_step || 1;
                form.querySelector('[name="purchase_action"]').value = product.purchase_action || 'inherit';
                form.querySelector('[name="status"]').value = product.status || 'draft';
                document.getElementById('isFeatured').checked = Number(product.is_featured) === 1;
                modalElement.querySelector('.modal-title').textContent = 'Edit Product';

                if (product.thumbnail_url) {
                    document.getElementById('currentThumbnail').src = product.thumbnail_url;
                    document.getElementById('currentThumbnailWrap').classList.remove('d-none');
                }

                renderExistingImages(data.images || []);
                (data.colors || []).forEach(addColorRow);
                (data.designs || []).forEach(addDesignRow);
                modal.show();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }

        if (deleteButton) {
            if (!confirm(`Delete product “${deleteButton.dataset.name}”? The record will be hidden from the website.`)) return;
            const body = new URLSearchParams({ action: 'delete', id: deleteButton.dataset.id, csrf_token: csrf });
            try {
                await request('api/products.php', { method: 'POST', body });
                notify('Product deleted successfully.');
                loadProducts();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        reindexVariants();

        const submit = document.getElementById('saveProductBtn');
        const formData = new FormData(form);
        formData.set('csrf_token', csrf);
        formData.set('action', 'save');
        submit.disabled = true;

        try {
            const response = await fetch('api/products.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to save product.');
            modal.hide();
            notify(payload.message || 'Product saved successfully.');
            loadProducts();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submit.disabled = false;
        }
    });

    filterCategory.addEventListener('change', loadProducts);
    document.getElementById('filterStatus').addEventListener('change', loadProducts);
    document.getElementById('refreshProducts').addEventListener('click', loadProducts);

    (async () => {
        try {
            await loadOptions();
            await loadProducts();

            const query = new URLSearchParams(window.location.search);

            if (
                query.get('action') === 'add'
                && permissions.add
            ) {
                clearProductForm();
                modal.show();
            }

            const editId = Number(query.get('edit') || 0);

            if (editId > 0 && permissions.edit) {
                const editButton = tbody.querySelector(
                    `.js-edit-product[data-id="${CSS.escape(
                        String(editId)
                    )}"]`
                );

                editButton?.click();
            }
        } catch (error) {
            notify(error.message, 'danger');
        }
    })();
})();
