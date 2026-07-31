<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['ramki_admin']['id'])) {
    $return = ltrim(
        (string)($_SERVER['REQUEST_URI'] ?? 'admin/dashboard.php'),
        '/'
    );

    header(
        'Location: ../login.php?return='
        . rawurlencode($return)
    );
    exit;
}
