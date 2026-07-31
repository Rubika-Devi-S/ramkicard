function loadRoles() {
    RamkiAdmin.request('api/roles-permissions.php', { action: 'roles' })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);

            $('#roleSelect').html(
                '<option value="">Select Role</option>' +
                response.data.map(row => `
                    <option value="${row.id}">${RamkiAdmin.escape(row.role_name)}</option>
                `).join('')
            );
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

function loadPermissions(roleId) {
    if (!roleId) return;

    RamkiAdmin.request('api/roles-permissions.php', {
        action: 'get',
        role_id: roleId
    })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);

            $('#permissionRoleId').val(roleId);

            $('#permissionsBody').html(response.data.map(row => `
                <tr data-menu-id="${row.menu_id}">
                    <td><strong>${RamkiAdmin.escape(row.menu_name)}</strong></td>
                    ${['can_view','can_add','can_edit','can_delete','can_approve','can_export'].map(key => `
                        <td>
                            <input
                                type="checkbox"
                                class="form-check-input permission-box"
                                data-key="${key}"
                                ${Number(row[key]) === 1 ? 'checked' : ''}
                            >
                        </td>
                    `).join('')}
                </tr>
            `).join(''));
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

$('#roleSelect').on('change', function () {
    loadPermissions(this.value);
});

$('#permissionsForm').on('submit', function (event) {
    event.preventDefault();

    const permissions = $('#permissionsBody tr[data-menu-id]').map(function () {
        const row = { menu_id: $(this).data('menu-id') };

        $(this).find('.permission-box').each(function () {
            row[$(this).data('key')] =
                $(this).is(':checked') ? 1 : 0;
        });

        return row;
    }).get();

    RamkiAdmin.request('api/roles-permissions.php', {
        action: 'save',
        role_id: $('#permissionRoleId').val(),
        permissions_json: JSON.stringify(permissions)
    })
        .done(response => {
            if (response.success) {
                RamkiAdmin.toast('success', response.message);
            } else {
                RamkiAdmin.toast('error', response.message);
            }
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

loadRoles();
