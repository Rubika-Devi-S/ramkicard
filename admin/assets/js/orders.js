(() => {
    'use strict';

    const module = document.getElementById('ordersModule');

    if (!module) {
        return;
    }

    const tableElement = document.getElementById('ordersTable');
    const tbody = tableElement?.querySelector('tbody');
    const filter = document.getElementById('orderStatusFilter');
    const search = document.getElementById('orderSearch');
    const refreshButtons = [
        document.getElementById('refreshOrders'),
        document.getElementById('refreshOrdersSmall')
    ].filter(Boolean);

    let ordersTable = null;
    let requestController = null;

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

    const statusLabel = status => String(status || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, character => character.toUpperCase());

    const badgeClass = status => {
        const classes = {
            new: 'orders-badge-new',
            processed: 'orders-badge-processed',
            delivered: 'orders-badge-success',
            paid: 'orders-badge-success',
            cancelled: 'orders-badge-danger',
            failed: 'orders-badge-danger',
            pending: 'orders-badge-pending'
        };

        return classes[status] || 'orders-badge-muted';
    };

    const badge = status => `
        <span class="orders-badge ${badgeClass(status)}">
            ${escapeHtml(statusLabel(status))}
        </span>
    `;

    const parseResponse = async response => {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch {
            throw new Error(
                text.trim()
                || 'The order service returned an invalid response.'
            );
        }
    };

    const updateCounts = counts => {
        Object.entries(counts || {}).forEach(([key, value]) => {
            const element = document.querySelector(
                `[data-order-count="${CSS.escape(key)}"]`
            );

            if (element) {
                element.textContent = String(Number(value || 0));
            }
        });
    };

    const renderRows = rows => {
        if (!tbody) {
            return;
        }

        if (ordersTable) {
            ordersTable.destroy();
            ordersTable = null;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="orders-empty">
                        No orders found.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = rows.map((row, index) => `
            <tr>
                <td>${index + 1}</td>

                <td>
                    <span class="orders-order-number">
                        ${escapeHtml(row.order_number)}
                    </span>
                </td>

                <td>
                    <strong>${escapeHtml(row.customer_name)}</strong>
                    <span class="orders-customer-phone">
                        ${escapeHtml(row.customer_phone)}
                    </span>
                </td>

                <td>
                    ${Number(row.item_count || 0)}
                    <span class="orders-small">product lines</span>
                </td>

                <td><strong>${money(row.grand_total)}</strong></td>
                <td>${badge(row.payment_status)}</td>
                <td>${badge(row.status)}</td>
                <td>${escapeHtml(row.created_at)}</td>

                <td>
                    <div class="orders-action-group">
                        <a
                            class="orders-action-btn"
                            href="order-view.php?id=${Number(row.id)}"
                            title="View order"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </a>

                        <a
                            class="orders-action-btn"
                            href="order-products.php?order_id=${Number(row.id)}"
                            title="View ordered products"
                        >
                            <i class="fa-solid fa-box-open"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');

        if (typeof DataTable !== 'undefined') {
            ordersTable = new DataTable(tableElement, {
                pageLength: 10,
                lengthChange: true,
                order: [],
                autoWidth: false,
                scrollX: true,
                layout: {
                    topStart: null,
                    topEnd: null
                }
            });

            if (search?.value) {
                ordersTable.search(search.value).draw();
            }
        }
    };

    const setLoading = isLoading => {
        refreshButtons.forEach(button => {
            button.disabled = isLoading;

            const icon = button.querySelector('i');

            if (icon) {
                icon.classList.toggle('fa-spin', isLoading);
            }
        });
    };

    const loadOrders = async () => {
        requestController?.abort();
        requestController = new AbortController();

        setLoading(true);

        const params = new URLSearchParams({
            action: 'list'
        });

        if (filter?.value) {
            params.set('status', filter.value);
        }

        try {
            const response = await fetch(
                `api/orders.php?${params.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: requestController.signal
                }
            );

            const result = await parseResponse(response);

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to load orders.'
                );
            }

            const data = result.data || {};

            updateCounts(data.counts || {});
            renderRows(data.rows || []);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Order loading failed:', error);

            if (tbody && !tbody.querySelector('tr')) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="orders-empty">
                            ${escapeHtml(error.message)}
                        </td>
                    </tr>
                `;
            }

            window.RamkiAdmin?.toast?.(
                'error',
                error.message || 'Unable to load orders.'
            );
        } finally {
            setLoading(false);
        }
    };

    filter?.addEventListener('change', () => {
        document
            .querySelectorAll('[data-order-filter]')
            .forEach(card => {
                card.classList.toggle(
                    'active',
                    card.dataset.orderFilter === filter.value
                );
            });

        loadOrders();
    });

    search?.addEventListener('input', () => {
        ordersTable?.search(search.value).draw();
    });

    refreshButtons.forEach(button => {
        button.addEventListener('click', loadOrders);
    });

    document
        .querySelectorAll('[data-order-filter]')
        .forEach(card => {
            card.addEventListener('click', () => {
                if (filter) {
                    filter.value = card.dataset.orderFilter || '';
                }

                document
                    .querySelectorAll('[data-order-filter]')
                    .forEach(item => {
                        item.classList.toggle(
                            'active',
                            item === card
                        );
                    });

                loadOrders();
            });
        });

    if (typeof DataTable !== 'undefined' && tableElement) {
        ordersTable = new DataTable(tableElement, {
            pageLength: 10,
            lengthChange: true,
            order: [],
            autoWidth: false,
            scrollX: true,
            layout: {
                topStart: null,
                topEnd: null
            }
        });
    }

    loadOrders();
})();
