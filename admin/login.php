<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['ramki_admin']['id'])) {
    header('Location: dashboard.php');
    exit;
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
        content="<?= e(csrf_token()); ?>"
    >

    <title>Admin Login | Ramki Cards</title>

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
        href="assets/css/admin.css"
        rel="stylesheet"
    >
</head>

<body class="login-page">

    <div class="login-card">

        <div class="login-logo">
            <img
                src="../logo.png"
                alt="Ramki Cards"
                onerror="this.style.display='none'"
            >
        </div>

        <div class="text-center mb-4">
            <h1 class="h2 mb-1">Ramki Cards</h1>

            <p class="text-muted mb-0">
                Admin Panel Login
            </p>
        </div>

        <form id="loginForm" autocomplete="off">

            <div class="mb-3">

                <label
                    for="login"
                    class="form-label"
                >
                    Username or Email
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="fa-solid fa-user"></i>
                    </span>

                    <input
                        type="text"
                        class="form-control"
                        id="login"
                        name="login"
                        placeholder="Enter username or email"
                        required
                        autocomplete="username"
                    >

                </div>

            </div>

            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="fa-solid fa-lock"></i>
                    </span>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        class="btn btn-outline-secondary"
                        type="button"
                        id="togglePassword"
                        aria-label="Show password"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>

            </div>

            <button
                class="btn btn-ramki w-100 py-2"
                type="submit"
                id="loginButton"
            >
                <span class="button-text">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>
                    Login
                </span>

                <span
                    class="spinner-border spinner-border-sm d-none"
                    aria-hidden="true"
                ></span>
            </button>

        </form>

        <p class="text-center small text-muted mt-4 mb-0">
            Secure access for authorized administrators only.
        </p>

    </div>

    <div
        id="toastContainer"
        class="toast-container position-fixed top-0 end-0 p-3"
    ></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/admin-common.js"></script>

    <script src="assets/js/login.js"></script>

    <script>
        document
            .getElementById('togglePassword')
            .addEventListener('click', function () {

                const passwordInput =
                    document.getElementById('password');

                const icon =
                    this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';

                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');

                    this.setAttribute(
                        'aria-label',
                        'Hide password'
                    );
                } else {
                    passwordInput.type = 'password';

                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');

                    this.setAttribute(
                        'aria-label',
                        'Show password'
                    );
                }
            });
    </script>

</body>
</html>