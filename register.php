<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

if (sf_admin_logged_in()) {
    header('Location: admin/dashboard.php');
    exit;
}

if (sf_customer_logged_in()) {
    header('Location: my-account.php');
    exit;
}

$companyName = sf_setting($pdo, 'company_name', 'Ramki Cards');
$logoPath = sf_media_path(
    sf_setting($pdo, 'logo_path', 'logo.png'),
    'logo.png'
);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $phone = sf_phone_digits((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($firstName === '' || mb_strlen($firstName) > 100) {
            $error = 'Enter a valid first name.';
        } elseif ($lastName !== '' && mb_strlen($lastName) > 100) {
            $error = 'Enter a valid last name.';
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
            $error = 'Enter a valid 10-digit mobile number.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (
            strlen($password) < 8
            || !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/[0-9]/', $password)
        ) {
            $error = 'Password must have at least 8 characters with letters and numbers.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Confirm password does not match.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        phone,
                        email,
                        password_hash,
                        status
                     FROM customers
                     WHERE phone = :phone
                        OR (
                            :email_value <> ''
                            AND LOWER(COALESCE(email, '')) = LOWER(:email_match)
                        )
                     ORDER BY id DESC
                     LIMIT 1
                     FOR UPDATE"
                );

                $stmt->execute([
                    'phone' => $phone,
                    'email_value' => $email,
                    'email_match' => $email,
                ]);

                $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                if ($existing && !empty($existing['password_hash'])) {
                    throw new RuntimeException(
                        'An account already exists. Please use the Login page.'
                    );
                }

                if ($existing && $existing['status'] === 'blocked') {
                    throw new RuntimeException(
                        'This customer account is blocked. Contact Ramki Cards.'
                    );
                }

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($existing) {
                    $customerId = (int)$existing['id'];

                    $stmt = $pdo->prepare(
                        "UPDATE customers
                         SET first_name = :first_name,
                             last_name = :last_name,
                             email = :email,
                             phone = :phone,
                             password_hash = :password_hash,
                             status = 'active',
                             last_login_at = NOW()
                         WHERE id = :id"
                    );

                    $stmt->execute([
                        'first_name' => $firstName,
                        'last_name' =>
                            $lastName !== '' ? $lastName : null,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone,
                        'password_hash' => $passwordHash,
                        'id' => $customerId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO customers
                        (
                            first_name,
                            last_name,
                            email,
                            phone,
                            password_hash,
                            status,
                            last_login_at
                        )
                        VALUES
                        (
                            :first_name,
                            :last_name,
                            :email,
                            :phone,
                            :password_hash,
                            'active',
                            NOW()
                        )"
                    );

                    $stmt->execute([
                        'first_name' => $firstName,
                        'last_name' =>
                            $lastName !== '' ? $lastName : null,
                        'email' => $email !== '' ? $email : null,
                        'phone' => $phone,
                        'password_hash' => $passwordHash,
                    ]);

                    $customerId = (int)$pdo->lastInsertId();
                }

                $pdo->commit();

                session_regenerate_id(true);

                $fullName = trim($firstName . ' ' . $lastName);

                $_SESSION['ramki_customer'] = [
                    'id' => $customerId,
                    'name' =>
                        $fullName !== '' ? $fullName : 'Customer',
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                ];

                unset($_SESSION['ramki_admin']);

                sf_merge_guest_cart($pdo, $customerId);

                sf_log_customer_login(
                    $pdo,
                    $customerId,
                    true,
                    $email !== '' ? $email : $phone
                );

                header('Location: my-account.php');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Customer registration failed: '
                    . $exception->getMessage()
                );

                $error = $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Unable to create the customer account. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="<?= sf_e(sf_csrf_token()); ?>"
    >

    <title>Create Account | <?= sf_e($companyName); ?></title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet"
    >

    <link
        href="assets/css/auth.css?v=20260728-1"
        rel="stylesheet"
    >
</head>

<body class="login-page register-page">

    <main class="login-card register-card">

        <a
            href="index.php"
            class="login-logo"
            aria-label="<?= sf_e($companyName); ?>"
        >
            <img
                src="<?= sf_e($logoPath); ?>"
                alt="<?= sf_e($companyName); ?>"
                onerror="this.style.display='none'"
            >
        </a>

        <div class="text-center mb-4">
            <h1 class="h2 mb-1">Create Customer Account</h1>

            <p class="text-muted mb-0">
                Register and create your own secure password
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <?= sf_e($error); ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            id="registerForm"
            autocomplete="on"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= sf_e(sf_csrf_token()); ?>"
            >

            <div class="row g-3">
                <div class="col-md-6">
                    <label
                        for="firstName"
                        class="form-label"
                    >First Name</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-user"></i>
                        </span>

                        <input
                            type="text"
                            class="form-control"
                            id="firstName"
                            name="first_name"
                            value="<?= sf_e($_POST['first_name'] ?? ''); ?>"
                            maxlength="100"
                            autocomplete="given-name"
                            required
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="lastName"
                        class="form-label"
                    >Last Name</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-user"></i>
                        </span>

                        <input
                            type="text"
                            class="form-control"
                            id="lastName"
                            name="last_name"
                            value="<?= sf_e($_POST['last_name'] ?? ''); ?>"
                            maxlength="100"
                            autocomplete="family-name"
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="phone"
                        class="form-label"
                    >Mobile Number</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-phone"></i>
                        </span>

                        <input
                            type="tel"
                            class="form-control"
                            id="phone"
                            name="phone"
                            value="<?= sf_e($_POST['phone'] ?? ''); ?>"
                            placeholder="10-digit mobile number"
                            inputmode="numeric"
                            pattern="[0-9]{10}"
                            maxlength="10"
                            autocomplete="tel"
                            required
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="email"
                        class="form-label"
                    >Email Address</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-envelope"></i>
                        </span>

                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?= sf_e($_POST['email'] ?? ''); ?>"
                            placeholder="Optional"
                            maxlength="190"
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="newPassword"
                        class="form-label"
                    >Create Password</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-lock"></i>
                        </span>

                        <input
                            type="password"
                            class="form-control"
                            id="newPassword"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary password-toggle"
                            data-password-toggle="newPassword"
                            aria-label="Show password"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="confirmPassword"
                        class="form-label"
                    >Confirm Password</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>

                        <input
                            type="password"
                            class="form-control"
                            id="confirmPassword"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary password-toggle"
                            data-password-toggle="confirmPassword"
                            aria-label="Show password"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <p class="password-help">
                Use at least 8 characters with letters and numbers.
            </p>

            <button
                class="btn btn-ramki w-100 py-2 mt-3"
                type="submit"
                id="registerButton"
            >
                <span class="button-text">
                    <i class="fa-solid fa-user-plus me-2"></i>
                    Create Account
                </span>

                <span
                    class="spinner-border spinner-border-sm d-none"
                    aria-hidden="true"
                ></span>
            </button>
        </form>

        <div class="login-support justify-content-center mt-4">
            <span>Already registered?</span>
            <a href="login.php">Login</a>
        </div>

        <p class="text-center small mt-3 mb-0">
            <a href="index.php" class="back-link">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Back to Website
            </a>
        </p>
    </main>

    <script>
        (() => {
            'use strict';

            document
                .querySelectorAll('[data-password-toggle]')
                .forEach(button => {
                    button.addEventListener('click', () => {
                        const input = document.getElementById(
                            button.dataset.passwordToggle
                        );

                        const icon = button.querySelector('i');
                        const show = input.type === 'password';

                        input.type = show ? 'text' : 'password';

                        icon?.classList.toggle('fa-eye', !show);
                        icon?.classList.toggle('fa-eye-slash', show);

                        button.setAttribute(
                            'aria-label',
                            show ? 'Hide password' : 'Show password'
                        );
                    });
                });

            const form = document.getElementById('registerForm');
            const button = document.getElementById('registerButton');

            form?.addEventListener('submit', event => {
                const password =
                    document.getElementById('newPassword').value;

                const confirmation =
                    document.getElementById('confirmPassword').value;

                if (password !== confirmation) {
                    event.preventDefault();
                    document
                        .getElementById('confirmPassword')
                        .setCustomValidity(
                            'Confirm password does not match.'
                        );
                    document
                        .getElementById('confirmPassword')
                        .reportValidity();
                    return;
                }

                document
                    .getElementById('confirmPassword')
                    .setCustomValidity('');

                button.disabled = true;
                button.querySelector('.button-text')?.classList.add('d-none');
                button.querySelector('.spinner-border')?.classList.remove('d-none');
            });
        })();
    </script>
</body>
</html>
