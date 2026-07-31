(() => {
    'use strict';

    const module = document.getElementById('categoryModule');
    if (!module) return;

    const tbody = document.querySelector('#categoriesTable tbody');
    const form = document.getElementById('categoryForm');
    const modalElement = document.getElementById('categoryModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const messageBox = document.getElementById('categoryMessage');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const permissions = {
        add: module.dataset.canAdd === '1',
        edit: module.dataset.canEdit === '1',
        delete: module.dataset.canDelete === '1',
    };

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function notify(message, type = 'success') {
        messageBox.innerHTML = `
            <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
            ...options,
        });

        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error('The server returned an invalid response.');
        }

        if (!response.ok || !payload.success) {
            if (response.status === 401 && payload.data?.redirect) {
                window.location.href = payload.data.redirect;
                return;
            }
            throw new Error(payload.message || 'Request failed.');
        }

        return payload.data;
    }

    function render(rows) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No categories found.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row, index) => {
            const image = row.image_url
                ? `<img src="${escapeHtml(row.image_url)}" alt="" style="width:58px;height:45px;object-fit:cover;border-radius:8px;">`
                : '<span class="text-muted">No image</span>';

            const actions = [
                permissions.edit
                    ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-category" data-id="${row.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>`
                    : '',
                permissions.delete
                    ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-category" data-id="${row.id}" data-name="${escapeHtml(row.category_name)}" title="Delete"><i class="fa-solid fa-trash"></i></button>`
                    : '',
            ].join(' ');

            return `
                <tr>
                    <td>${index + 1}</td>
                    <td>${image}</td>
                    <td><strong>${escapeHtml(row.category_name)}</strong><div class="small text-muted">${escapeHtml(row.description || '')}</div></td>
                    <td><code>${escapeHtml(row.slug)}</code></td>
                    <td>${Number(row.product_count || 0)}</td>
                    <td><span class="badge ${row.status === 'active' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(row.status)}</span></td>
                    <td>${Number(row.sort_order || 0)}</td>
                    <td><div class="d-flex gap-1">${actions || '<span class="text-muted">View only</span>'}</div></td>
                </tr>`;
        }).join('');
    }

    async function loadCategories() {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Loading categories...</td></tr>';
        try {
            const data = await request('api/categories.php?action=list');
            render(data.rows || []);
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function resetForm() {
        form.reset();
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="sort_order"]').value = '0';
        form.querySelector('[name="status"]').value = 'active';
        modalElement.querySelector('.modal-title').textContent = 'Add Category';
        document.getElementById('currentCategoryImageWrap').classList.add('d-none');
        document.getElementById('currentCategoryImage').removeAttribute('src');
    }

    document.getElementById('addCategoryBtn')?.addEventListener('click', () => {
        if (!permissions.add) return;
        resetForm();
        modal.show();
    });

    tbody.addEventListener('click', async event => {
        const editButton = event.target.closest('.js-edit-category');
        const deleteButton = event.target.closest('.js-delete-category');

        if (editButton) {
            try {
                const row = await request(`api/categories.php?action=get&id=${encodeURIComponent(editButton.dataset.id)}`);
                resetForm();
                form.querySelector('[name="id"]').value = row.id;
                form.querySelector('[name="category_name"]').value = row.category_name || '';
                form.querySelector('[name="sort_order"]').value = row.sort_order || 0;
                form.querySelector('[name="status"]').value = row.status || 'active';
                form.querySelector('[name="description"]').value = row.description || '';
                modalElement.querySelector('.modal-title').textContent = 'Edit Category';

                if (row.image_url) {
                    document.getElementById('currentCategoryImage').src = row.image_url;
                    document.getElementById('currentCategoryImageWrap').classList.remove('d-none');
                }

                modal.show();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }

        if (deleteButton) {
            if (!confirm(`Delete category “${deleteButton.dataset.name}”?`)) return;

            const body = new URLSearchParams({
                action: 'delete',
                id: deleteButton.dataset.id,
                csrf_token: csrf,
            });

            try {
                await request('api/categories.php', { method: 'POST', body });
                notify('Category deleted successfully.');
                loadCategories();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const submit = document.getElementById('saveCategoryBtn');
        const formData = new FormData(form);
        formData.set('csrf_token', csrf);
        formData.set('action', 'save');

        submit.disabled = true;

        try {
            const response = await fetch('api/categories.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to save category.');
            }

            modal.hide();
            notify(payload.message || 'Category saved successfully.');
            loadCategories();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submit.disabled = false;
        }
    });

    loadCategories();
})();
