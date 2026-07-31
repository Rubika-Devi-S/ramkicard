(() => {
    'use strict';

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const statusBadge = status => {
        const value = String(status || '');
        const label = value
            .replaceAll('_', ' ')
            .replace(/\b\w/g, character => character.toUpperCase());

        const classes = {
            active: 'badge-soft-success',
            paid: 'badge-soft-success',
            delivered: 'badge-soft-success',
            converted: 'badge-soft-success',
            processed: 'badge-soft-warning',
            pending: 'badge-soft-warning',
            contacted: 'badge-soft-warning',
            quotation_sent: 'badge-soft-warning',
            new: 'badge-soft-new',
            cancelled: 'badge-soft-danger',
            rejected: 'badge-soft-danger',
            blocked: 'badge-soft-danger',
            failed: 'badge-soft-danger'
        };

        return `
            <span class="badge rounded-pill ${
                classes[value] || 'badge-soft-muted'
            }">
                ${escapeHtml(label)}
            </span>
        `;
    };

    const money = value => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2
    }).format(Number(value || 0));

    const setText = (selector, value) => {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = String(value ?? 0);
        }
    };

    const renderEnquiries = rows => {
        const body = document.getElementById('recentEnquiries');

        if (!body) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = `
                <tr>
                    <td
                        colspan="5"
                        class="text-center text-muted py-4"
                    >
                        No enquiries found.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = rows.map(row => `
            <tr>
                <td>
                    <strong>${escapeHtml(row.enquiry_number)}</strong>
                </td>

                <td>
                    <strong>${escapeHtml(row.customer_name)}</strong>
                    <small class="d-block text-muted">
                        ${escapeHtml(row.customer_phone)}
                    </small>
                </td>

                <td>
                    ${escapeHtml(
                        row.event_type
                        || row.subject
                        || row.source
                        || 'Website Enquiry'
                    )}
                </td>

                <td>${statusBadge(row.status)}</td>
                <td>${escapeHtml(row.created_at)}</td>
            </tr>
        `).join('');
    };

    const renderOrders = rows => {
        const body = document.getElementById('recentOrders');

        if (!body) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = `
                <tr>
                    <td
                        colspan="4"
                        class="text-center text-muted py-4"
                    >
                        No orders found.
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = rows.map(row => `
            <tr>
                <td>
                    <strong>${escapeHtml(row.order_number)}</strong>
                    ${
                        row.created_at
                            ? `<small class="d-block text-muted">
                                ${escapeHtml(row.created_at)}
                               </small>`
                            : ''
                    }
                </td>

                <td>${escapeHtml(row.customer_name)}</td>
                <td><strong>${money(row.grand_total)}</strong></td>
                <td>${statusBadge(row.status)}</td>
            </tr>
        `).join('');
    };

    const refreshDashboard = async () => {
        const controller = new AbortController();
        const timeout = window.setTimeout(
            () => controller.abort(),
            12000
        );

        try {
            const response = await fetch(
                'api/dashboard.php?action=summary',
                {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                    cache: 'no-store'
                }
            );

            const text = await response.text();
            let result;

            try {
                result = JSON.parse(text);
            } catch {
                throw new Error(
                    'Dashboard API returned invalid JSON.'
                );
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Unable to refresh dashboard.'
                );
            }

            const data = result.data || {};
            const counts = data.counts || {};

            setText(
                '#statEnquiries',
                counts.new_enquiries
            );
            setText(
                '#statOrders',
                counts.new_orders
            );
            setText(
                '#statProducts',
                counts.active_products
            );
            setText(
                '#statCustomers',
                counts.customers
            );

            renderEnquiries(data.recent_enquiries);
            renderOrders(data.recent_orders);
        } catch (error) {
            /*
             * The dashboard is already rendered from PHP.
             * Keep that valid data visible when background refresh fails.
             */
            console.error(
                'Dashboard background refresh failed:',
                error
            );
        } finally {
            window.clearTimeout(timeout);
        }
    };

    window.RamkiDashboard = {
        refresh: refreshDashboard
    };

    refreshDashboard();
})();
