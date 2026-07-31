$('#loginForm').on('submit', function (event) {
    event.preventDefault();

    const form = $(this);
    const button = form.find('button[type="submit"]');

    button.prop('disabled', true);
    button.find('.button-text').addClass('d-none');
    button.find('.spinner-border').removeClass('d-none');

    RamkiAdmin.request(
        'api/login.php',
        form.serialize()
    )
    .done(function (response) {
        if (response.success) {
            RamkiAdmin.toast('success', response.message);

            setTimeout(function () {
                window.location.href =
                    response.data?.redirect || 'dashboard.php';
            }, 500);
        } else {
            RamkiAdmin.toast('error', response.message);
        }
    })
    .fail(function (xhr) {
        RamkiAdmin.error(
            xhr,
            'Unable to log in. Please try again.'
        );
    })
    .always(function () {
        button.prop('disabled', false);
        button.find('.button-text').removeClass('d-none');
        button.find('.spinner-border').addClass('d-none');
    });
});