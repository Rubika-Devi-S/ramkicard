let customersTable;

function loadCustomers() {
    RamkiAdmin.request('api/customers.php', { action: 'list' })
        .done(response => {
            if (!response.success) {
                RamkiAdmin.toast('error', response.message);
                return;
            }

            if (customersTable) {
                customersTable.destroy();
            }

            $('#customersTable tbody').html(
                response.data.map((row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <strong>
                                ${RamkiAdmin.escape(`${row.first_name} ${row.last_name || ''}`.trim())}
                            </strong>
                        </td>
                        <td>${RamkiAdmin.escape(row.phone)}</td>
                        <td>${RamkiAdmin.escape(row.email || '-')}</td>
                        <td>${row.order_count}</td>
                        <td>${row.enquiry_count}</td>
                        <td>${RamkiAdmin.statusBadge(row.status)}</td>
                        <td>${RamkiAdmin.escape(row.created_at)}</td>
                    </tr>
                `).join('')
            );

            customersTable = new DataTable('#customersTable', {
                pageLength: 10
            });
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

loadCustomers();
