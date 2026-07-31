<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

function enquiry_fail(string $message, int $status = 422): never
{
    if (sf_wants_json()) {
        sf_json(false, $message, [], $status);
    }

    header(
        'Location: index.php?error='
        . rawurlencode($message)
        . '#contact'
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
    enquiry_fail(
        'Your page session expired. Refresh the page and try again.',
        419
    );
}

$lastSubmission = (int)($_SESSION['last_contact_enquiry_at'] ?? 0);

if ($lastSubmission > 0 && time() - $lastSubmission < 10) {
    enquiry_fail(
        'Please wait a few seconds before submitting another enquiry.',
        429
    );
}

$name = trim((string)($_POST['name'] ?? ''));
$mobile = sf_phone_digits((string)($_POST['mobile'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$event = trim((string)($_POST['event'] ?? ''));
$otherEvent = trim((string)($_POST['other_event'] ?? ''));
$eventDate = trim((string)($_POST['date'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));

if ($name === '' || mb_strlen($name) > 150) {
    enquiry_fail('Enter a valid name.');
}

if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    enquiry_fail('Enter a valid 10-digit mobile number.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    enquiry_fail('Enter a valid email address.');
}

$allowedEvents = [
    'Wedding',
    'Engagement',
    'Reception',
    'House Warming',
    'Birthday',
    'Corporate Event',
    'Other',
];

if (!in_array($event, $allowedEvents, true)) {
    enquiry_fail('Select a valid function or event.');
}

if ($event === 'Other') {
    if ($otherEvent === '' || mb_strlen($otherEvent) > 150) {
        enquiry_fail('Specify your event.');
    }

    $eventDisplay = $otherEvent;
} else {
    $eventDisplay = $event;
}

$dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $eventDate);
$dateErrors = DateTimeImmutable::getLastErrors();

if (
    !$dateObject
    || (
        is_array($dateErrors)
        && (
            ($dateErrors['warning_count'] ?? 0) > 0
            || ($dateErrors['error_count'] ?? 0) > 0
        )
    )
) {
    enquiry_fail('Select a valid event date.');
}

if ($dateObject < new DateTimeImmutable('today')) {
    enquiry_fail('Event date cannot be in the past.');
}

if ($location === '' || mb_strlen($location) > 255) {
    enquiry_fail('Enter a valid location.');
}

$subject = $eventDisplay . ' enquiry';

$message = implode("\n", [
    'Event: ' . $eventDisplay,
    'Event date: ' . $dateObject->format('d-m-Y'),
    'Location: ' . $location,
    'Submitted from: Website contact form',
]);

try {
    $pdo->beginTransaction();

    $customerId = sf_find_or_create_customer(
        $pdo,
        $name,
        $mobile,
        $email
    );

    $enquiryNumber = sf_next_number(
        $pdo,
        'enquiry',
        'ENQ'
    );

    $stmt = $pdo->prepare(
        "INSERT INTO enquiries
        (
            enquiry_number,
            customer_id,
            customer_name,
            customer_email,
            customer_phone,
            source,
            status,
            subject,
            message
        )
        VALUES
        (
            :enquiry_number,
            :customer_id,
            :customer_name,
            :customer_email,
            :customer_phone,
            'contact',
            'new',
            :subject,
            :message
        )"
    );

    $stmt->execute([
        'enquiry_number' => $enquiryNumber,
        'customer_id' => $customerId,
        'customer_name' => $name,
        'customer_email' => $email !== '' ? $email : null,
        'customer_phone' => $mobile,
        'subject' => $subject,
        'message' => $message,
    ]);

    $enquiryId = (int)$pdo->lastInsertId();

    $pdo->commit();

    $_SESSION['last_contact_enquiry_at'] = time();

    /*
    |--------------------------------------------------------------------------
    | Email notifications are best effort.
    |--------------------------------------------------------------------------
    | The enquiry remains saved in the admin panel even when PHP mail fails.
    */

    $companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
    $phoneNumber = sf_setting($pdo, 'phone_number', '96299 54411');

    $adminStmt = $pdo->prepare(
        "SELECT setting_value
         FROM site_settings
         WHERE setting_key = 'admin_notification_email'
         LIMIT 1"
    );
    $adminStmt->execute();

    $adminEmail = trim((string)$adminStmt->fetchColumn());

    if ($adminEmail === '') {
        $adminEmail = 'ariharasudhan1062003@gmail.com';
    }

    $adminMessage = implode("\n", [
        'NEW WEBSITE ENQUIRY',
        '------------------------------',
        'Enquiry number: ' . $enquiryNumber,
        'Name: ' . $name,
        'Mobile: ' . $mobile,
        'Email: ' . ($email !== '' ? $email : 'Not provided'),
        'Event: ' . $eventDisplay,
        'Event date: ' . $dateObject->format('d-m-Y'),
        'Location: ' . $location,
        'Submitted on: ' . date('d-m-Y h:i:s A'),
    ]);

    sf_send_mail(
        $pdo,
        $adminEmail,
        'New Website Enquiry ' . $enquiryNumber,
        $adminMessage,
        $email !== '' ? $email : null
    );

    if ($email !== '') {
        $customerMessage = implode("\n", [
            'Dear ' . $name . ',',
            '',
            'Thank you for contacting ' . $companyName . '.',
            'Your enquiry number is ' . $enquiryNumber . '.',
            '',
            'Event: ' . $eventDisplay,
            'Event date: ' . $dateObject->format('d-m-Y'),
            'Location: ' . $location,
            '',
            'Our team will contact you shortly.',
            'Phone: ' . $phoneNumber,
            '',
            'Regards,',
            $companyName,
        ]);

        sf_send_mail(
            $pdo,
            $email,
            'Your enquiry ' . $enquiryNumber . ' has been received',
            $customerMessage
        );
    }

    if (sf_wants_json()) {
        sf_json(true, 'Enquiry submitted successfully.', [
            'enquiry_id' => $enquiryId,
            'enquiry_number' => $enquiryNumber,
        ]);
    }

    header(
        'Location: index.php?status=success&enquiry='
        . rawurlencode($enquiryNumber)
        . '#contact'
    );
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Website contact enquiry failed: ' . $e->getMessage());

    enquiry_fail(
        'We could not submit your enquiry. Please try again.',
        500
    );
}
