<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'settings')) {
    http_response_code(403);
    exit('Permission denied.');
}

$pageTitle = 'Site Settings';
$pageScript = 'site-settings.js';

require __DIR__ . '/includes/header.php';
?>

<form class="ramki-card p-3" id="settingsForm">
    <input type="hidden" name="action" value="save">

    <div class="mb-4">
        <h2 class="h5 mb-1">Website and Commerce Settings</h2>
        <p class="text-muted small mb-0">
            These values are shared by the public website and admin panel.
        </p>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input class="form-control" name="company_name">
        </div>

        <div class="col-md-6">
            <label class="form-label">Admin Notification Email</label>
            <input type="email" class="form-control" name="admin_notification_email">
        </div>

        <div class="col-md-4">
            <label class="form-label">Phone Number</label>
            <input class="form-control" name="phone_number">
        </div>

        <div class="col-md-4">
            <label class="form-label">Second Phone</label>
            <input class="form-control" name="secondary_phone_number">
        </div>

        <div class="col-md-4">
            <label class="form-label">WhatsApp Number</label>
            <input class="form-control" name="whatsapp_number">
        </div>

        <div class="col-md-6">
            <label class="form-label">Website Email</label>
            <input type="email" class="form-control" name="email_address">
        </div>

        <div class="col-md-6">
            <label class="form-label">Purchase Mode</label>
            <select class="form-select" name="purchase_mode">
                <option value="checkout">Checkout</option>
                <option value="enquiry">Enquiry Now</option>
                <option value="both">Both</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Address</label>
            <textarea class="form-control" rows="3" name="address"></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label">Instagram URL</label>
            <input class="form-control" name="instagram_url">
        </div>

        <div class="col-md-4">
            <label class="form-label">Facebook URL</label>
            <input class="form-control" name="facebook_url">
        </div>

        <div class="col-md-4">
            <label class="form-label">YouTube URL</label>
            <input class="form-control" name="youtube_url">
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-ramki px-4" type="submit">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save Settings
        </button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
