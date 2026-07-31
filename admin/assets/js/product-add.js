(() => {
    'use strict';

    const module = document.getElementById('productAddModule');

    if (!module) {
        return;
    }

    const form = document.getElementById('productAddForm');
    const submitButton = document.getElementById(
        'saveNewProductButton'
    );
    const messageBox = document.getElementById(
        'productAddMessage'
    );
    const colorRows = document.getElementById(
        'productColorRows'
    );
    const designRows = document.getElementById(
        'productDesignRows'
    );
    const csrf =
        document.querySelector('meta[name="csrf-token"]')
            ?.content
        || form.querySelector('[name="csrf_token"]')?.value
        || '';

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const money = value => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    }).format(Number(value || 0));

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');

        toast.className =
            `product-add-toast${type === 'error' ? ' error' : ''}`;
        toast.textContent = message;
        messageBox.replaceChildren(toast);

        window.setTimeout(() => {
            toast.remove();
        }, 4500);
    }

    function removeEmptyState(container) {
        container.querySelector('[data-empty-state]')?.remove();
    }

    function restoreEmptyState(container, message) {
        if (container.querySelector('.product-add-variant-row')) {
            return;
        }

        container.innerHTML = `
            <div
                class="product-add-variant-empty"
                data-empty-state
            >
                ${escapeHtml(message)}
            </div>
        `;
    }

    function addColorRow(data = {}) {
        removeEmptyState(colorRows);

        const row = document.createElement('div');

        row.className = 'product-add-variant-row';
        row.dataset.variant = 'color';
        row.innerHTML = `
            <input
                type="hidden"
                name="color_id[]"
                value="${escapeHtml(data.id || '')}"
            >
            <input
                type="hidden"
                name="color_existing_image[]"
                value="${escapeHtml(data.image_path || '')}"
            >

            <div class="product-add-variant-grid">
                <div class="product-add-variant-field wide">
                    <label>Colour Name *</label>
                    <input
                        class="form-control"
                        name="color_name[]"
                        maxlength="100"
                        value="${escapeHtml(data.color_name || '')}"
                        required
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Colour Code</label>
                    <input
                        class="form-control"
                        name="color_code[]"
                        maxlength="7"
                        placeholder="#AABBCC"
                        value="${escapeHtml(data.color_code || '')}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Price +/-</label>
                    <input
                        type="number"
                        class="form-control"
                        name="color_price_adjustment[]"
                        step="0.01"
                        value="${escapeHtml(
                            data.price_adjustment ?? '0.00'
                        )}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Sort Order</label>
                    <input
                        type="number"
                        class="form-control"
                        name="color_sort_order[]"
                        min="0"
                        value="${escapeHtml(data.sort_order ?? 0)}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Status</label>
                    <select
                        class="form-select"
                        name="color_status[]"
                    >
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="product-add-variant-field wide">
                    <label>Colour Image</label>
                    <input
                        type="file"
                        class="form-control variant-image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Remove</label>
                    <button
                        type="button"
                        class="product-add-variant-remove"
                        data-remove-variant
                        aria-label="Remove colour"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;

        colorRows.appendChild(row);
        reindexVariants();
        updateSummary();
    }

    function addDesignRow(data = {}) {
        removeEmptyState(designRows);

        const row = document.createElement('div');

        row.className = 'product-add-variant-row';
        row.dataset.variant = 'design';
        row.innerHTML = `
            <input
                type="hidden"
                name="design_id[]"
                value="${escapeHtml(data.id || '')}"
            >
            <input
                type="hidden"
                name="design_existing_image[]"
                value="${escapeHtml(data.image_path || '')}"
            >

            <div class="product-add-variant-grid">
                <div class="product-add-variant-field wide">
                    <label>Design Name *</label>
                    <input
                        class="form-control"
                        name="design_name[]"
                        maxlength="150"
                        value="${escapeHtml(data.design_name || '')}"
                        required
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Design Code</label>
                    <input
                        class="form-control"
                        name="design_code[]"
                        maxlength="100"
                        value="${escapeHtml(data.design_code || '')}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Price +/-</label>
                    <input
                        type="number"
                        class="form-control"
                        name="design_price_adjustment[]"
                        step="0.01"
                        value="${escapeHtml(
                            data.price_adjustment ?? '0.00'
                        )}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Sort Order</label>
                    <input
                        type="number"
                        class="form-control"
                        name="design_sort_order[]"
                        min="0"
                        value="${escapeHtml(data.sort_order ?? 0)}"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Status</label>
                    <select
                        class="form-select"
                        name="design_status[]"
                    >
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="product-add-variant-field wide">
                    <label>Design Image</label>
                    <input
                        type="file"
                        class="form-control variant-image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >
                </div>

                <div class="product-add-variant-field">
                    <label>Remove</label>
                    <button
                        type="button"
                        class="product-add-variant-remove"
                        data-remove-variant
                        aria-label="Remove design"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>

                <div class="product-add-variant-field full">
                    <label>Description</label>
                    <textarea
                        class="form-control"
                        name="design_description[]"
                        rows="2"
                    >${escapeHtml(data.description || '')}</textarea>
                </div>
            </div>
        `;

        designRows.appendChild(row);
        reindexVariants();
        updateSummary();
    }

    function reindexVariants() {
        colorRows
            .querySelectorAll('.product-add-variant-row')
            .forEach((row, index) => {
                const image = row.querySelector('.variant-image');

                if (image) {
                    image.name = `color_image_${index}`;
                }
            });

        designRows
            .querySelectorAll('.product-add-variant-row')
            .forEach((row, index) => {
                const image = row.querySelector('.variant-image');

                if (image) {
                    image.name = `design_image_${index}`;
                }
            });
    }

    function updateSummary() {
        const name =
            form.querySelector('[name="product_name"]')?.value.trim()
            || 'Not entered';
        const basePrice =
            form.querySelector('[name="base_price"]')?.value
            || 0;
        const offerPrice =
            form.querySelector('[name="offer_price"]')?.value;
        const minimumQty =
            form.querySelector('[name="minimum_order_qty"]')?.value
            || 1;
        const status =
            form.querySelector('[name="status"]')?.value
            || 'draft';

        document.getElementById(
            'summaryProductName'
        ).textContent = name;

        document.getElementById(
            'summaryProductPrice'
        ).textContent = money(
            offerPrice !== '' && offerPrice !== undefined
                ? offerPrice
                : basePrice
        );

        document.getElementById(
            'summaryProductMoq'
        ).textContent = String(minimumQty);

        document.getElementById(
            'summaryProductColors'
        ).textContent = String(
            colorRows.querySelectorAll(
                '.product-add-variant-row'
            ).length
        );

        document.getElementById(
            'summaryProductDesigns'
        ).textContent = String(
            designRows.querySelectorAll(
                '.product-add-variant-row'
            ).length
        );

        document.getElementById(
            'summaryProductStatus'
        ).textContent =
            status.charAt(0).toUpperCase() + status.slice(1);
    }

    function previewSingleImage(input, image, wrap) {
        const file = input.files?.[0];

        if (!file) {
            image.removeAttribute('src');
            wrap.classList.remove('show');
            return;
        }

        const url = URL.createObjectURL(file);

        image.onload = () => URL.revokeObjectURL(url);
        image.src = url;
        wrap.classList.add('show');
    }

    function previewGallery(input, container) {
        container.innerHTML = '';

        Array.from(input.files || [])
            .slice(0, 10)
            .forEach(file => {
                const image = document.createElement('img');
                const url = URL.createObjectURL(file);

                image.onload = () => URL.revokeObjectURL(url);
                image.src = url;
                image.alt = file.name;
                container.appendChild(image);
            });
    }

    function validateForm() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return false;
        }

        const basePrice = Number(
            form.querySelector('[name="base_price"]').value || 0
        );
        const offerValue =
            form.querySelector('[name="offer_price"]').value.trim();

        if (
            offerValue !== ''
            && Number(offerValue) > basePrice
        ) {
            showToast(
                'Offer price cannot be greater than the base price.',
                'error'
            );
            return false;
        }

        const galleryCount =
            document.getElementById('addProductGallery')
                ?.files?.length
            || 0;

        if (galleryCount > 10) {
            showToast(
                'Upload a maximum of 10 secondary images.',
                'error'
            );
            return false;
        }

        return true;
    }

    async function submitProduct() {
        if (!validateForm()) {
            return;
        }

        reindexVariants();

        const originalHtml = submitButton.innerHTML;
        const formData = new FormData(form);

        formData.set('action', 'save');
        formData.set('csrf_token', csrf);

        submitButton.disabled = true;
        submitButton.innerHTML = `
            <i class="fa-solid fa-spinner fa-spin"></i>
            Saving Product...
        `;

        try {
            const response = await fetch('api/products.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const text = await response.text();
            let payload = null;

            try {
                payload = JSON.parse(text);
            } catch {
                throw new Error(
                    text.trim()
                    || 'The product service returned an invalid response.'
                );
            }

            if (!response.ok || !payload?.success) {
                throw new Error(
                    payload?.message || 'Unable to save product.'
                );
            }

            showToast(
                payload.message || 'Product created successfully.'
            );

            const productId = Number(payload?.data?.id || 0);

            window.setTimeout(() => {
                window.location.href = productId > 0
                    ? `${module.dataset.productViewUrl}?id=${productId}`
                    : module.dataset.productsUrl;
            }, 750);
        } catch (error) {
            console.error('Product creation failed:', error);

            showToast(
                error.message || 'Unable to save product.',
                'error'
            );
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = originalHtml;
        }
    }

    document.getElementById(
        'addProductColor'
    )?.addEventListener('click', () => addColorRow());

    document.getElementById(
        'addProductDesign'
    )?.addEventListener('click', () => addDesignRow());

    document.addEventListener('click', event => {
        const button = event.target.closest(
            '[data-remove-variant]'
        );

        if (!button) {
            return;
        }

        const row = button.closest('.product-add-variant-row');
        const type = row?.dataset.variant;

        row?.remove();
        reindexVariants();

        if (type === 'color') {
            restoreEmptyState(
                colorRows,
                'No colour variants added.'
            );
        }

        if (type === 'design') {
            restoreEmptyState(
                designRows,
                'No design variants added.'
            );
        }

        updateSummary();
    });

    document.getElementById(
        'addProductThumbnail'
    )?.addEventListener('change', event => {
        previewSingleImage(
            event.currentTarget,
            document.getElementById('thumbnailPreview'),
            document.getElementById('thumbnailPreviewWrap')
        );
    });

    document.getElementById(
        'addProductGallery'
    )?.addEventListener('change', event => {
        previewGallery(
            event.currentTarget,
            document.getElementById('galleryPreview')
        );
    });

    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);

    form.addEventListener('submit', event => {
        event.preventDefault();
        submitProduct();
    });

    updateSummary();
})();
