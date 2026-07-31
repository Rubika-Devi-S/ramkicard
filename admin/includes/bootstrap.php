<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('RAMKI_SESSION');
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout-functions.php';

$currentPage = basename(parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH));
$pageTitle = $pageTitle ?? 'Ramki Cards';
