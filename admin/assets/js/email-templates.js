let templatesTable;
const templateModal = bootstrap.Modal.getOrCreateInstance('#templateModal');

function loadTemplates() {
    RamkiAdmin.request('api/email-templates.php', { action: 'list' })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);
            if (templatesTable) templatesTable.destroy();

            $('#templatesTable tbody').html(response.data.map((row, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${RamkiAdmin.escape(row.template_name)}</strong></td>
                    <td><code>${RamkiAdmin.escape(row.template_code)}</code></td>
                    <td>${RamkiAdmin.escape(row.email_subject)}</td>
                    <td>${RamkiAdmin.statusBadge(row.status)}</td>
                    <td><button class="btn btn-sm btn-outline-primary edit-template" data-id="${row.id}"><i class="fa-solid fa-pen"></i></button></td>
                </tr>
            `).join(''));

            templatesTable = new DataTable('#templatesTable', { pageLength: 10 });
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

$(document).on('click', '.edit-template', function () {
    RamkiAdmin.request('api/email-templates.php', { action: 'get', id: $(this).data('id') })
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);
            const row = response.data;
            $('#templateForm [name="id"]').val(row.id);
            $('#templateForm [name="email_subject"]').val(row.email_subject);
            $('#templateForm [name="body_html"]').val(row.body_html);
            $('#templateForm [name="status"]').val(row.status);
            $('#templateVariables').text(`Available variables: ${row.available_variables || 'Not specified'}`);
            templateModal.show();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

$('#templateForm').on('submit', function (event) {
    event.preventDefault();

    RamkiAdmin.request('api/email-templates.php', $(this).serialize())
        .done(response => {
            if (!response.success) return RamkiAdmin.toast('error', response.message);
            templateModal.hide();
            RamkiAdmin.toast('success', response.message);
            loadTemplates();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

loadTemplates();
