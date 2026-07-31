<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['ramki_admin']['id'])) {
    json_response(false, 'Login session expired.', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
}
