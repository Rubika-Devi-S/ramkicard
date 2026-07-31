let adminsTable;
const adminModal = bootstrap.Modal.getOrCreateInstance('#adminModal');

function loadAdmins() {
    RamkiAdmin.request('api/admin-users.php', { action: 'list' })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);

            $('#adminRole').html(
                '<option value="">Select Role</option>' +
                response.data.roles.map(row => `
                    <option value="${row.id}">${RamkiAdmin.escape(row.role_name)}</option>
                `).join('')
            );

            if (adminsTable) adminsTable.destroy();

            $('#adminsTable tbody').html(response.data.users.map((row, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${RamkiAdmin.escape(row.name)}</strong></td>
                    <td>${RamkiAdmin.escape(row.email)}</td>
                    <td>${RamkiAdmin.escape(row.phone || '-')}</td>
                    <td>${RamkiAdmin.escape(row.role_name)}</td>
                    <td>${RamkiAdmin.statusBadge(row.status)}</td>
                    <td>${RamkiAdmin.escape(row.last_login_at || '-')}</td>
                    <td><button class="btn btn-sm btn-outline-primary edit-admin" data-id="${row.id}"><i class="fa-solid fa-pen"></i></button></td>
                </tr>
            `).join(''));

            adminsTable = new DataTable('#adminsTable', { pageLength: 10 });
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

$('#addAdminBtn').on('click', () => {
    $('#adminForm')[0].reset();
    $('#adminForm [name="id"]').val('');
    adminModal.show();
});

$(document).on('click', '.edit-admin', function () {
    RamkiAdmin.request('api/admin-users.php', { action: 'get', id: $(this).data('id') })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);

            Object.entries(response.data).forEach(([key, value]) => {
                $(`#adminForm [name="${key}"]`).val(value);
            });

            $('#adminForm [name="password"]').val('');
            adminModal.show();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

$('#adminForm').on('submit', function (event) {
    event.preventDefault();

    RamkiAdmin.request('api/admin-users.php', $(this).serialize())
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);

            adminModal.hide();
            RamkiAdmin.toast('success', response.message);
            loadAdmins();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

loadAdmins();
