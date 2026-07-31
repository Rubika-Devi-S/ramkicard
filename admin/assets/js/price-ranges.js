(() => {
    'use strict';

    const module = document.getElementById('priceRangeModule');
    if (!module) return;

    const tbody = document.querySelector('#rangesTable tbody');
    const form = document.getElementById('rangeForm');
    const modalElement = document.getElementById('rangeModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const messageBox = document.getElementById('rangeMessage');
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

    function render(rows) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No price ranges found.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row, index) => {
            const actions = [
                permissions.edit ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-range" data-id="${row.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : '',
                permissions.delete ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-range" data-id="${row.id}" data-name="${escapeHtml(row.range_name)}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : '',
            ].join(' ');

            return `<tr>
                <td>${index + 1}</td>
                <td><strong>${escapeHtml(row.range_name)}</strong></td>
                <td>${money(row.minimum_price)}</td>
                <td>${row.maximum_price === null || row.maximum_price === '' ? 'No limit' : money(row.maximum_price)}</td>
                <td>${Number(row.product_count || 0)}</td>
                <td><span class="badge ${row.status === 'active' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(row.status)}</span></td>
                <td>${Number(row.sort_order || 0)}</td>
                <td><div class="d-flex gap-1">${actions || '<span class="text-muted">View only</span>'}</div></td>
            </tr>`;
        }).join('');
    }

    async function loadRanges() {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Loading price ranges...</td></tr>';
        try {
            const data = await request('api/price-ranges.php?action=list');
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
        modalElement.querySelector('.modal-title').textContent = 'Add Price Range';
    }

    document.getElementById('addRangeBtn')?.addEventListener('click', () => {
        resetForm();
        modal.show();
    });

    tbody.addEventListener('click', async event => {
        const editButton = event.target.closest('.js-edit-range');
        const deleteButton = event.target.closest('.js-delete-range');

        if (editButton) {
            try {
                const row = await request(`api/price-ranges.php?action=get&id=${encodeURIComponent(editButton.dataset.id)}`);
                resetForm();
                form.querySelector('[name="id"]').value = row.id;
                form.querySelector('[name="range_name"]').value = row.range_name || '';
                form.querySelector('[name="minimum_price"]').value = row.minimum_price || 0;
                form.querySelector('[name="maximum_price"]').value = row.maximum_price ?? '';
                form.querySelector('[name="sort_order"]').value = row.sort_order || 0;
                form.querySelector('[name="status"]').value = row.status || 'active';
                modalElement.querySelector('.modal-title').textContent = 'Edit Price Range';
                modal.show();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }

        if (deleteButton) {
            if (!confirm(`Delete price range “${deleteButton.dataset.name}”?`)) return;

            const body = new URLSearchParams({ action: 'delete', id: deleteButton.dataset.id, csrf_token: csrf });
            try {
                await request('api/price-ranges.php', { method: 'POST', body });
                notify('Price range deleted successfully.');
                loadRanges();
            } catch (error) {
                notify(error.message, 'danger');
            }
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submit = document.getElementById('saveRangeBtn');
        const formData = new FormData(form);
        formData.set('csrf_token', csrf);
        formData.set('action', 'save');
        submit.disabled = true;

        try {
            const response = await fetch('api/price-ranges.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to save price range.');
            modal.hide();
            notify(payload.message || 'Price range saved successfully.');
            loadRanges();
        } catch (error) {
            notify(error.message, 'danger');
        } finally {
            submit.disabled = false;
        }
    });

    loadRanges();
})();
