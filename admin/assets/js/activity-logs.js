let logsTable;

RamkiAdmin.request('api/activity-logs.php', { action: 'list' })
    .done(response => {
        if (!response.success) {
            RamkiAdmin.toast('error', response.message);
            return;
        }

        $('#logsTable tbody').html(response.data.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${RamkiAdmin.escape(row.created_at)}</td>
                <td>${RamkiAdmin.escape(row.admin_name || 'System')}</td>
                <td>${RamkiAdmin.escape(row.module_name || '-')}</td>
                <td><span class="badge text-bg-secondary">${RamkiAdmin.escape(row.action)}</span></td>
                <td>${RamkiAdmin.escape(row.description || '-')}</td>
                <td>${RamkiAdmin.escape(row.ip_address || '-')}</td>
            </tr>
        `).join(''));

        logsTable = new DataTable('#logsTable', {
            pageLength: 25
        });
    })
    .fail(xhr => RamkiAdmin.error(xhr));
