let enquiriesTable;
const enquiryModal = bootstrap.Modal.getOrCreateInstance('#enquiryModal');

function loadEnquiries() {
    RamkiAdmin.request('api/enquiries.php', {
        action: 'list',
        status: $('#enquiryStatusFilter').val()
    })
        .done(response => {
            if (!response.success) {
                RamkiAdmin.toast('error', response.message);
                return;
            }

            if (enquiriesTable) {
                enquiriesTable.destroy();
            }

            $('#enquiriesTable tbody').html(
                response.data.map((row, index) => `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${RamkiAdmin.escape(row.enquiry_number)}</strong></td>
                        <td>
                            <strong>${RamkiAdmin.escape(row.customer_name)}</strong><br>
                            <small>${RamkiAdmin.escape(row.customer_phone)}</small><br>
                            <small>${RamkiAdmin.escape(row.customer_email || '')}</small>
                        </td>
                        <td>${RamkiAdmin.escape(row.event_type || row.source || '-')}</td>
                        <td>${RamkiAdmin.escape(row.event_date || '-')}</td>
                        <td>${RamkiAdmin.escape(row.event_location || '-')}</td>
                        <td>${RamkiAdmin.statusBadge(row.status)}</td>
                        <td>${RamkiAdmin.escape(row.created_at)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary view-enquiry" data-id="${row.id}">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('')
            );

            enquiriesTable = new DataTable('#enquiriesTable', {
                pageLength: 10,
                order: [[0, 'desc']]
            });
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

$(document).on('click', '.view-enquiry', function () {
    RamkiAdmin.request('api/enquiries.php', {
        action: 'get',
        id: $(this).data('id')
    })
        .done(response => {
            if (!response.success) {
                RamkiAdmin.toast('error', response.message);
                return;
            }

            const row = response.data;

            $('#enquiryForm [name="id"]').val(row.id);
            $('#enquiryForm [name="status"]').val(row.status);
            $('#enquiryForm [name="admin_notes"]').val(row.admin_notes || '');

            const phone = String(row.customer_phone || '').replace(/\D/g, '');
            $('#enquiryWhatsApp').attr('href', `https://wa.me/91${phone}`);

            $('#enquiryDetails').html(`
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Enquiry Number</small>
                            <div class="fw-bold">${RamkiAdmin.escape(row.enquiry_number)}</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted">Submitted</small>
                            <div class="fw-bold">${RamkiAdmin.escape(row.created_at)}</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <strong>Customer</strong>
                        <p class="mb-0">
                            ${RamkiAdmin.escape(row.customer_name)}<br>
                            ${RamkiAdmin.escape(row.customer_phone)}<br>
                            ${RamkiAdmin.escape(row.customer_email || 'No email')}
                        </p>
                    </div>

                    <div class="col-md-6">
                        <strong>Event</strong>
                        <p class="mb-0">
                            ${RamkiAdmin.escape(row.event_type || row.source || '-')}<br>
                            ${RamkiAdmin.escape(row.event_date || '-')}<br>
                            ${RamkiAdmin.escape(row.event_location || '-')}
                        </p>
                    </div>

                    <div class="col-12">
                        <strong>Message</strong>
                        <p class="mb-0">${RamkiAdmin.escape(row.message || 'No message')}</p>
                    </div>
                </div>
            `);

            enquiryModal.show();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

$('#enquiryForm').on('submit', function (event) {
    event.preventDefault();

    RamkiAdmin.request('api/enquiries.php', $(this).serialize())
        .done(response => {
            if (!response.success) {
                RamkiAdmin.toast('error', response.message);
                return;
            }

            enquiryModal.hide();
            RamkiAdmin.toast('success', response.message);
            loadEnquiries();
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

$('#enquiryStatusFilter').on('change', loadEnquiries);

loadEnquiries();
