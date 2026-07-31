<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/storefront.php';

$storefrontAuthFile = __DIR__ . '/includes/storefront-auth.php';

if (!is_file($storefrontAuthFile)) {
    throw new RuntimeException(
        'Missing required file: includes/storefront-auth.php'
    );
}

require_once $storefrontAuthFile;

$requiredLoginFunctions = [
    'sf_admin_logged_in',
    'sf_customer_logged_in',
    'sf_login_admin',
    'sf_login_customer',
    'sf_safe_return_url',
    'sf_log_customer_login',
];

foreach ($requiredLoginFunctions as $requiredFunction) {
    if (!function_exists($requiredFunction)) {
        throw new RuntimeException(
            'Authentication helper is unavailable: '
            . $requiredFunction
        );
    }
}

if (sf_admin_logged_in()) {
    header('Location: admin/dashboard.php');
    exit;
}

if (sf_customer_logged_in()) {
    header('Location: my-account.php');
    exit;
}

$companyName = sf_setting(
    $pdo,
    'company_name',
    'Ramki Cards'
);

$logoPath = sf_media_path(
    sf_setting($pdo, 'logo_path', 'logo.png'),
    'logo.png'
);

$returnUrl = sf_safe_return_url(
    (string)($_GET['return'] ?? $_POST['return'] ?? ''),
    'my-account.php'
);

$loginReason = trim(
    (string)(
        $_GET['reason']
        ?? $_POST['reason']
        ?? ''
    )
);

$loginNotice = $loginReason === 'login_required'
    ? 'Please login to continue. After a successful login, '
        . 'you will return to the page or action you selected.'
    : '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sf_verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your page session expired. Refresh and try again.';
    } else {
        $identifier = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $lastAttempt = (int)($_SESSION['ramki_login_attempt_at'] ?? 0);

        if ($lastAttempt > 0 && time() - $lastAttempt < 2) {
            $error = 'Please wait a moment before trying again.';
        } elseif ($identifier === '' || $password === '') {
            $error = 'Enter your email or mobile number and password.';
        } else {
            $_SESSION['ramki_login_attempt_at'] = time();

            $admin = sf_login_admin(
                $pdo,
                $identifier,
                $password
            );

            if ($admin) {
                header('Location: admin/dashboard.php');
                exit;
            }

            $customer = sf_login_customer(
                $pdo,
                $identifier,
                $password
            );

            if ($customer) {
                $customerReturn = str_starts_with(
                    $returnUrl,
                    'admin/'
                )
                    ? 'my-account.php'
                    : $returnUrl;

                header('Location: ' . $customerReturn);
                exit;
            }

            sf_log_customer_login(
                $pdo,
                null,
                false,
                $identifier
            );

            $error = 'Invalid login details or inactive account.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="<?= sf_e(sf_csrf_token()); ?>">

    <title>
        Login | <?= sf_e($companyName); ?>
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">

    <link href="assets/css/auth.css?v=20260730-4" rel="stylesheet">

    <style>
    :root {
        --ramki-primary: #851c2c;
        --ramki-primary-dark: #4f0b17;
        --ramki-primary-soft: #ad4352;
        --ramki-maroon: #741525;
        --ramki-maroon-dark: #4c0915;
        --ramki-gold: #d5a84e;
        --ramki-gold-light: #f7dd9e;
        --ramki-cream: #fff8ed;
        --ramki-text: #2d1d20;
        --ramki-muted: #806d70;
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        min-height: 100%;
    }

    body {
        margin: 0;
    }

    body.login-page {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        min-height: 100dvh;
        padding: 32px 16px;
        overflow-x: hidden;
        overflow-y: auto;
        font-family: 'Poppins', sans-serif;
        color: var(--ramki-text);
        background:
            radial-gradient(circle at 12% 18%,
                rgba(255, 229, 173, 0.9) 0,
                rgba(255, 229, 173, 0) 30%),
            radial-gradient(circle at 88% 82%,
                rgba(111, 20, 35, 0.42) 0,
                rgba(111, 20, 35, 0) 35%),
            linear-gradient(135deg,
                #fff9ef 0%,
                #f8e6d6 30%,
                #e7b3aa 62%,
                #851c2c 100%);
        background-size: 160% 160%;
        animation: backgroundShift 16s ease-in-out infinite alternate;
    }

    body.login-page::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background:
            linear-gradient(rgba(255, 255, 255, 0.06) 1px,
                transparent 1px),
            linear-gradient(90deg,
                rgba(255, 255, 255, 0.06) 1px,
                transparent 1px);
        background-size: 46px 46px;
        mask-image: linear-gradient(to bottom,
                rgba(0, 0, 0, 0.75),
                transparent 90%);
        -webkit-mask-image: linear-gradient(to bottom,
                rgba(0, 0, 0, 0.75),
                transparent 90%);
        animation: gridMove 20s linear infinite;
    }

    body.login-page::after {
        content: '';
        position: fixed;
        inset: -40%;
        z-index: 0;
        pointer-events: none;
        background: conic-gradient(from 0deg,
                transparent,
                rgba(255, 255, 255, 0.13),
                transparent,
                rgba(213, 168, 78, 0.13),
                transparent);
        filter: blur(55px);
        animation: ambientRotate 28s linear infinite;
    }

    .background-effects {
        position: fixed;
        inset: 0;
        z-index: 1;
        overflow: hidden;
        pointer-events: none;
    }

    .blur-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(4px);
        opacity: 0.7;
    }

    .blur-orb.one {
        top: 8%;
        left: 6%;
        width: 210px;
        height: 210px;
        background: rgba(255, 222, 148, 0.34);
        animation: orbFloatOne 9s ease-in-out infinite alternate;
    }

    .blur-orb.two {
        right: 4%;
        bottom: 4%;
        width: 300px;
        height: 300px;
        background: rgba(88, 12, 27, 0.24);
        animation: orbFloatTwo 12s ease-in-out infinite alternate;
    }

    .blur-orb.three {
        top: 48%;
        left: 48%;
        width: 180px;
        height: 180px;
        background: rgba(255, 255, 255, 0.13);
        animation: orbFloatThree 10s ease-in-out infinite alternate;
    }

    .floating-particle {
        position: absolute;
        bottom: -120px;
        display: block;
        border-radius: 50%;
        opacity: 0;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow:
            inset 0 0 16px rgba(255, 255, 255, 0.15),
            0 15px 30px rgba(70, 10, 20, 0.1);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        animation: particleRise 18s linear infinite;
    }

    .floating-particle:nth-child(4) {
        left: 8%;
        width: 76px;
        height: 76px;
        animation-delay: 0s;
        animation-duration: 18s;
    }

    .floating-particle:nth-child(5) {
        left: 24%;
        width: 38px;
        height: 38px;
        animation-delay: 4s;
        animation-duration: 14s;
    }

    .floating-particle:nth-child(6) {
        left: 43%;
        width: 105px;
        height: 105px;
        animation-delay: 7s;
        animation-duration: 21s;
    }

    .floating-particle:nth-child(7) {
        left: 65%;
        width: 52px;
        height: 52px;
        animation-delay: 2s;
        animation-duration: 16s;
    }

    .floating-particle:nth-child(8) {
        left: 82%;
        width: 88px;
        height: 88px;
        animation-delay: 9s;
        animation-duration: 20s;
    }

    .floating-particle:nth-child(9) {
        left: 94%;
        width: 30px;
        height: 30px;
        animation-delay: 5s;
        animation-duration: 13s;
    }

    /*
     * Login card
     */

    .login-card {
        position: relative;
        z-index: 5;
        width: 100%;
        max-width: 455px;
        padding: 38px 38px 32px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.62);
        border-radius: 27px;
        background: rgba(255, 255, 255, 0.91);
        box-shadow:
            0 32px 85px rgba(66, 8, 20, 0.28),
            0 8px 24px rgba(66, 8, 20, 0.12),
            inset 0 1px 0 rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(22px);
        -webkit-backdrop-filter: blur(22px);
        opacity: 1;
        transform: none;
    }

    .login-card::before {
        content: '';
        position: absolute;
        top: -90px;
        right: -90px;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        background: radial-gradient(circle,
                rgba(213, 168, 78, 0.3),
                transparent 68%);
        pointer-events: none;
        animation: cardGlow 5s ease-in-out infinite alternate;
    }

    .login-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -130%;
        width: 58%;
        height: 100%;
        transform: skewX(-22deg);
        pointer-events: none;
        background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.55),
                transparent);
    }

    /*
         * Main login logo:
         * maroon background with white logo letters.
         */
    .login-logo {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 108px;
        height: 108px;
        margin: 0 auto 20px;
        padding: 18px;
        overflow: hidden;
        border-radius: 50%;
        background:
            linear-gradient(145deg,
                #9a3041 0%,
                var(--ramki-maroon) 48%,
                var(--ramki-maroon-dark) 100%);
        border: 2px solid rgba(255, 255, 255, 0.9);
        box-shadow:
            0 16px 38px rgba(82, 10, 23, 0.28),
            0 0 0 8px rgba(133, 28, 44, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.3);
        text-decoration: none;
        opacity: 1;
        transform: none;
        transition:
            transform 0.3s ease,
            box-shadow 0.3s ease;
    }

    .login-logo::before {
        content: '';
        position: absolute;
        top: -30%;
        left: -85%;
        width: 62%;
        height: 160%;
        transform: rotate(20deg);
        background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.24),
                transparent);
        transition: left 0.7s ease;
    }

    .login-logo:hover {
        transform: translateY(-5px) scale(1.04);
        box-shadow:
            0 22px 45px rgba(82, 10, 23, 0.34),
            0 0 0 10px rgba(133, 28, 44, 0.14),
            inset 0 1px 0 rgba(255, 255, 255, 0.35);
    }

    .login-logo:hover::before {
        left: 135%;
    }

    .login-logo img {
        position: relative;
        z-index: 2;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;

        /*
             * Makes the logo image completely white.
             * Use a transparent PNG for the cleanest result.
             */
        filter:
            brightness(0) saturate(100%) invert(100%);
    }

    .login-logo-fallback {
        position: absolute;
        z-index: 1;
        display: none;
        color: #fff;
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -2px;
    }

    .login-heading {
        position: relative;
        z-index: 2;
        opacity: 1;
        transform: none;
    }

    .login-heading h1 {
        margin: 0;
        font-family: 'Playfair Display', serif;
        font-weight: 800;
        color: var(--ramki-primary-dark);
        letter-spacing: -0.5px;
    }

    .login-heading p {
        color: var(--ramki-muted) !important;
    }

    .login-form-wrap {
        position: relative;
        z-index: 2;
        opacity: 1;
        transform: none;
    }

    .form-label {
        margin-bottom: 7px;
        font-size: 0.88rem;
        font-weight: 600;
        color: #50373b;
    }

    .input-group {
        position: relative;
        border-radius: 13px;
        transition:
            transform 0.25s ease,
            box-shadow 0.25s ease;
    }

    .input-group:focus-within {
        transform: translateY(-2px);
        box-shadow: 0 11px 28px rgba(139, 30, 45, 0.13);
    }

    .input-group-text {
        min-width: 48px;
        justify-content: center;
        color: var(--ramki-primary);
        background: #fff8f3;
        border: 1px solid #ead9d3;
        border-right: 0;
        border-radius: 13px 0 0 13px;
        transition:
            color 0.25s ease,
            background-color 0.25s ease,
            border-color 0.25s ease;
    }

    .form-control {
        min-height: 50px;
        font-size: 0.92rem;
        color: var(--ramki-text);
        background: rgba(255, 255, 255, 0.95);
        border-color: #ead9d3;
        box-shadow: none !important;
        transition:
            border-color 0.25s ease,
            background-color 0.25s ease;
    }

    .form-control::placeholder {
        color: #ad9b9e;
    }

    .form-control:focus {
        color: var(--ramki-text);
        background: #fff;
        border-color: rgba(133, 28, 44, 0.58);
    }

    .input-group:focus-within .input-group-text {
        color: #fff;
        background: var(--ramki-primary);
        border-color: var(--ramki-primary);
    }

    .password-toggle {
        min-width: 48px;
        color: var(--ramki-primary);
        background: #fff8f3;
        border-color: #ead9d3;
        border-radius: 0 13px 13px 0;
        transition:
            color 0.25s ease,
            background-color 0.25s ease,
            border-color 0.25s ease;
    }

    .password-toggle:hover,
    .password-toggle:focus {
        color: #fff;
        background: var(--ramki-primary);
        border-color: var(--ramki-primary);
    }

    .login-support {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 0.88rem;
        color: var(--ramki-muted);
    }

    .login-support a,
    .back-link {
        position: relative;
        color: var(--ramki-primary);
        font-weight: 600;
        text-decoration: none;
    }

    .login-support a::after,
    .back-link::after {
        content: '';
        position: absolute;
        right: 0;
        bottom: -2px;
        left: 0;
        height: 1px;
        transform: scaleX(0);
        transform-origin: right;
        background: var(--ramki-primary);
        transition: transform 0.25s ease;
    }

    .login-support a:hover::after,
    .back-link:hover::after {
        transform: scaleX(1);
        transform-origin: left;
    }

    .btn-ramki {
        position: relative;
        min-height: 50px;
        overflow: hidden;
        border: 0;
        border-radius: 13px;
        color: #fff;
        font-size: 0.96rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        background: linear-gradient(135deg,
                var(--ramki-primary-soft) 0%,
                var(--ramki-primary) 45%,
                var(--ramki-primary-dark) 100%);
        box-shadow:
            0 14px 30px rgba(133, 28, 44, 0.3),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
        transition:
            transform 0.25s ease,
            box-shadow 0.25s ease;
    }

    .btn-ramki::before {
        content: '';
        position: absolute;
        top: 0;
        left: -110%;
        width: 70%;
        height: 100%;
        transform: skewX(-22deg);
        background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.35),
                transparent);
        transition: left 0.65s ease;
    }

    .btn-ramki:hover {
        color: #fff;
        transform: translateY(-3px);
        box-shadow:
            0 19px 38px rgba(133, 28, 44, 0.38),
            inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }

    .btn-ramki:hover::before {
        left: 130%;
    }

    .btn-ramki:active {
        transform: translateY(0) scale(0.985);
    }

    .btn-ramki:disabled {
        color: #fff;
        opacity: 0.84;
        cursor: wait;
    }

    .alert-danger {
        position: relative;
        z-index: 2;
        border: 1px solid rgba(181, 41, 61, 0.18);
        border-radius: 12px;
        color: #851c2c;
        background: rgba(255, 235, 238, 0.94);
        box-shadow: 0 8px 20px rgba(133, 28, 44, 0.08);
        animation:
            errorEnter 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    .login-footer {
        position: relative;
        z-index: 2;
        opacity: 1;
        transform: none;
    }

    .login-footer .text-muted {
        color: var(--ramki-muted) !important;
    }

    @keyframes backgroundShift {
        0% {
            background-position: 0% 40%;
        }

        50% {
            background-position: 60% 65%;
        }

        100% {
            background-position: 100% 35%;
        }
    }

    @keyframes gridMove {
        from {
            background-position: 0 0;
        }

        to {
            background-position: 46px 46px;
        }
    }

    @keyframes ambientRotate {
        from {
            transform: rotate(0deg) scale(1);
        }

        to {
            transform: rotate(360deg) scale(1.08);
        }
    }

    @keyframes orbFloatOne {
        from {
            transform: translate3d(0, 0, 0) scale(1);
        }

        to {
            transform: translate3d(50px, 35px, 0) scale(1.15);
        }
    }

    @keyframes orbFloatTwo {
        from {
            transform: translate3d(0, 0, 0) scale(1);
        }

        to {
            transform: translate3d(-65px, -42px, 0) scale(1.12);
        }
    }

    @keyframes orbFloatThree {
        from {
            transform: translate3d(-30px, -15px, 0) scale(0.9);
        }

        to {
            transform: translate3d(35px, 30px, 0) scale(1.12);
        }
    }

    @keyframes particleRise {
        0% {
            opacity: 0;
            transform: translateY(0) rotate(0deg) scale(0.8);
        }

        12% {
            opacity: 0.5;
        }

        50% {
            opacity: 0.32;
            transform: translateY(-55vh) rotate(180deg) scale(1.05);
        }

        90% {
            opacity: 0.12;
        }

        100% {
            opacity: 0;
            transform: translateY(-120vh) rotate(360deg) scale(0.9);
        }
    }

    70% {
        opacity: 1;
        transform: scale(1.08) rotate(3deg);
    }

    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
    }

    to {
        transform: translateX(0);
    }
    }

    100% {
        opacity: 0;
        transform: scale(1.45);
    }
    }

    100% {
        opacity: 0;
        transform: scale(1.65);
    }
    }

    100% {
        opacity: 0;
        transform: scale(1.85);
    }
    }

    45%,
    100% {
        left: 150%;
    }
    }

    to {
        opacity: 0;
        transform: scale(0.82);
    }
    }

    to {
        transform: translateX(-102%);
    }
    }

    to {
        transform: translateX(102%);
    }
    }

    55% {
        opacity: 1;
        filter: blur(0);
    }

    100% {
        opacity: 1;
        transform:
            perspective(1200px) translateY(0) rotateX(0deg) scale(1);
        filter: blur(0);
    }
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
    }

    to {
        left: 165%;
    }
    }

    @keyframes cardGlow {
        from {
            opacity: 0.5;
            transform: scale(0.9);
        }

        to {
            opacity: 1;
            transform: scale(1.15);
        }
    }

    @keyframes errorEnter {
        from {
            opacity: 0;
            transform: translateY(-12px) scale(0.94);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @media (max-width: 575.98px) {
        body.login-page {
            align-items: flex-start;
            padding: 24px 14px;
        }

        .login-card {
            margin: auto 0;
            padding: 30px 22px 26px;
            border-radius: 22px;
        }

        .login-logo {
            width: 94px;
            height: 94px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .login-heading h1 {
            font-size: 1.75rem;
        }

        .floating-particle:nth-child(6),
        .floating-particle:nth-child(8) {
            display: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }

        .login-card,
        .login-logo,
        .login-heading,
        .login-form-wrap,
        .login-footer {
            opacity: 1 !important;
            transform: none !important;
            filter: none !important;
        }
    }
    </style>
</head>

<body class="login-page">

    <!-- Animated background -->
    <div class="background-effects" aria-hidden="true">

        <span class="blur-orb one"></span>
        <span class="blur-orb two"></span>
        <span class="blur-orb three"></span>

        <span class="floating-particle"></span>
        <span class="floating-particle"></span>
        <span class="floating-particle"></span>
        <span class="floating-particle"></span>
        <span class="floating-particle"></span>
        <span class="floating-particle"></span>
    </div>

    <main class="login-card">

        <a href="index.php" class="login-logo" aria-label="<?= sf_e($companyName); ?>">

            <span class="login-logo-fallback">
                RC
            </span>

            <img src="<?= sf_e($logoPath); ?>" alt="<?= sf_e($companyName); ?>" class="white-logo-image"
                onerror="handleLogoError(this)">
        </a>

        <div class="login-heading text-center mb-4">
            <h1 class="h2 mb-1">
                <?= sf_e($companyName); ?>
            </h1>

            <p class="text-muted mb-0">
                Login
            </p>
        </div>

        <div class="login-form-wrap">

            <?php if ($loginNotice !== ''): ?>
            <div class="alert alert-warning login-required-notice" role="status">
                <i class="fa-solid fa-lock me-2"></i>
                <?= sf_e($loginNotice); ?>
            </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= sf_e($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="on">

                <input type="hidden" name="csrf_token" value="<?= sf_e(sf_csrf_token()); ?>">

                <input type="hidden" name="return" value="<?= sf_e($returnUrl); ?>">
                <input type="hidden" name="reason" value="<?= sf_e($loginReason); ?>">

                <div class="mb-3">
                    <label for="login" class="form-label">

                        Email or Mobile Number
                    </label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-user"></i>
                        </span>

                        <input type="text" class="form-control" id="login" name="login"
                            value="<?= sf_e($_POST['login'] ?? ''); ?>" placeholder="Enter email or mobile number"
                            maxlength="190" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">

                        Password
                    </label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fa-solid fa-lock"></i>
                        </span>

                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter password" required autocomplete="current-password">

                        <button class="btn btn-outline-secondary password-toggle" type="button" id="togglePassword"
                            aria-label="Show password">

                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="login-support mb-4">
                    <span>New customer?</span>

                    <a href="register.php">
                        Create Account
                    </a>
                </div>

                <button class="btn btn-ramki w-100 py-2" type="submit" id="loginButton">

                    <span class="button-text">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>
                        Login
                    </span>

                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true">
                    </span>
                </button>
            </form>
        </div>

        <div class="login-footer">
            <p class="text-center small text-muted mt-4 mb-0">
                One secure login for all Ramki Cards users.
            </p>

            <p class="text-center small mt-2 mb-0">
                <a href="index.php" class="back-link">

                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Back to Website
                </a>
            </p>
        </div>
    </main>

    <script>
    function handleLogoError(image) {
        image.style.display = 'none';

        const parent = image.parentElement;
        const fallback = parent?.querySelector(
            '.login-logo-fallback'
        );

        if (fallback) {
            fallback.style.display = 'block';
        }
    }

    (() => {
        'use strict';

        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const form = document.getElementById('loginForm');
        const button = document.getElementById('loginButton');

        toggle?.addEventListener('click', () => {
            if (!password) {
                return;
            }

            const icon = toggle.querySelector('i');
            const showPassword = password.type === 'password';

            password.type = showPassword ?
                'text' :
                'password';

            icon?.classList.toggle(
                'fa-eye',
                !showPassword
            );

            icon?.classList.toggle(
                'fa-eye-slash',
                showPassword
            );

            toggle.setAttribute(
                'aria-label',
                showPassword ?
                'Hide password' :
                'Show password'
            );

            password.focus();
        });

        form?.addEventListener('submit', () => {
            if (!form.checkValidity() || !button) {
                return;
            }

            button.disabled = true;

            button
                .querySelector('.button-text')
                ?.classList.add('d-none');

            button
                .querySelector('.spinner-border')
                ?.classList.remove('d-none');
        });
    })();
    </script>
</body>

</html>