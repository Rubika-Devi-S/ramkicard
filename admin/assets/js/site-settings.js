const settingKeys = [
    'company_name',
    'admin_notification_email',
    'phone_number',
    'secondary_phone_number',
    'whatsapp_number',
    'email_address',
    'purchase_mode',
    'address',
    'instagram_url',
    'facebook_url',
    'youtube_url'
];

function loadSettings() {
    RamkiAdmin.request('api/site-settings.php', { action: 'get' })
        .done(response => {
            if (!response.success) {
                RamkiAdmin.toast('error', response.message);
                return;
            }

            settingKeys.forEach(key => {
                $(`#settingsForm [name="${key}"]`).val(
                    response.data[key] ?? ''
                );
            });
        })
        .fail(xhr => RamkiAdmin.error(xhr));
}

$('#settingsForm').on('submit', function (event) {
    event.preventDefault();

    RamkiAdmin.request(
        'api/site-settings.php',
        $(this).serialize()
    )
        .done(response => {
            if (response.success) {
                RamkiAdmin.toast('success', response.message);
            } else {
                RamkiAdmin.toast('error', response.message);
            }
        })
        .fail(xhr => RamkiAdmin.error(xhr));
});

loadSettings();
