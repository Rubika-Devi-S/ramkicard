<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

/**
 * RAMKI CARDS
 *
 * Database connection for:
 *
 * 1. Hostinger live server  → localhost
 * 2. Local XAMPP / WAMP     → Hostinger Remote MySQL
 */

$serverName = strtolower(
    (string)($_SERVER['SERVER_NAME'] ?? 'localhost')
);

$httpHost = strtolower(
    (string)($_SERVER['HTTP_HOST'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Environment Detection
|--------------------------------------------------------------------------
*/

$isLocalhost =
    in_array(
        $serverName,
        [
            'localhost',
            '127.0.0.1',
            '::1',
        ],
        true
    )
    || str_starts_with($httpHost, 'localhost')
    || str_starts_with($httpHost, '127.0.0.1')
    || str_contains($serverName, '.test')
    || str_contains($serverName, '.local')
    || PHP_SAPI === 'cli-server';

/*
|--------------------------------------------------------------------------
| Database Host
|--------------------------------------------------------------------------
|
| Local development:
| Connects to the Hostinger Remote MySQL server.
|
| Live Hostinger server:
| Connects using localhost.
|
*/

if ($isLocalhost) {

    /*
     * Remote MySQL hostname shown in Hostinger.
     *
     * Hostinger:
     * Databases → Remote MySQL
     */

    $dbHost = 'auth-db1740.hstgr.io';
    $dbPort = 3306;

} else {

    /*
     * Live Hostinger connection.
     */

    $dbHost = 'localhost';
    $dbPort = 3306;
}

/*
|--------------------------------------------------------------------------
| Database Credentials
|--------------------------------------------------------------------------
*/

$dbName = 'u966043993_ramkicards';

$dbUser = 'u966043993_ramkicards';

/*
 * Enter the Ramki Cards database password here.
 */
$dbPass = 'Ramkicards@2026';

$dbCharset = 'utf8mb4';

/*
|--------------------------------------------------------------------------
| PDO Connection
|--------------------------------------------------------------------------
*/

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbHost,
    $dbPort,
    $dbName,
    $dbCharset
);

$pdoOptions = [

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES =>
        false,

    PDO::ATTR_PERSISTENT =>
        false,

    PDO::ATTR_TIMEOUT =>
        20,
];

try {

    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        $pdoOptions
    );

    /*
     * Set the database character set.
     */

    $pdo->exec(
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    /*
     * Set India timezone.
     */

    $pdo->exec(
        "SET time_zone = '+05:30'"
    );

} catch (PDOException $exception) {

    $safeHost = htmlspecialchars(
        $dbHost,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeDatabase = htmlspecialchars(
        $dbName,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeError = htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

    error_log(
        sprintf(
            '[RAMKI CARDS PDO ERROR] Host: %s | Database: %s | Message: %s',
            $dbHost,
            $dbName,
            $exception->getMessage()
        )
    );

    http_response_code(500);

    /*
     * Show complete error details only on localhost.
     */

    if ($isLocalhost) {

        die(
            '<div style="
                max-width:850px;
                margin:40px auto;
                padding:25px;
                font-family:Arial,sans-serif;
                border:1px solid #dc3545;
                border-radius:10px;
                background:#fff5f5;
                color:#212529;
            ">' .

            '<h3 style="color:#dc3545;">
                Ramki Cards remote database connection failed
            </h3>' .

            '<p><strong>Host:</strong> '
            . $safeHost
            . '</p>' .

            '<p><strong>Database:</strong> '
            . $safeDatabase
            . '</p>' .

            '<p><strong>Error:</strong> '
            . $safeError
            . '</p>' .

            '<p>Please confirm the following:</p>' .

            '<ul>' .

            '<li>
                Your current public IP address is added in
                Hostinger Remote MySQL access.
            </li>' .

            '<li>
                Remote MySQL access is enabled for database
                <strong>'
                . $safeDatabase
                . '</strong>.
            </li>' .

            '<li>
                Port <strong>3306</strong> is not blocked by
                Windows Firewall or your internet provider.
            </li>' .

            '<li>
                The remote host is exactly
                <strong>'
                . $safeHost
                . '</strong>.
            </li>' .

            '<li>
                The database username and password are correct.
            </li>' .

            '<li>
                The PHP extension
                <strong>pdo_mysql</strong> is enabled.
            </li>' .

            '</ul>' .

            '</div>'
        );
    }

    die(
        'Unable to connect to the database. '
        . 'Please try again later.'
    );
}

/*
|--------------------------------------------------------------------------
| PDO Compatibility Variables
|--------------------------------------------------------------------------
|
| Some pages may use $db instead of $pdo.
|
*/

$db = $pdo;

/*
|--------------------------------------------------------------------------
| MySQLi Connection
|--------------------------------------------------------------------------
|
| Kept for compatibility with older pages that use MySQLi.
|
*/

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect(
    $dbHost,
    $dbUser,
    $dbPass,
    $dbName,
    $dbPort
);

if (!$conn) {

    $mysqliError = mysqli_connect_error();

    error_log(
        sprintf(
            '[RAMKI CARDS MYSQLI ERROR] Host: %s | Database: %s | Message: %s',
            $dbHost,
            $dbName,
            $mysqliError
        )
    );

    http_response_code(500);

    if ($isLocalhost) {

        die(
            '<div style="
                max-width:850px;
                margin:40px auto;
                padding:25px;
                font-family:Arial,sans-serif;
                border:1px solid #dc3545;
                border-radius:10px;
                background:#fff5f5;
                color:#212529;
            ">' .

            '<h3 style="color:#dc3545;">
                Ramki Cards MySQLi connection failed
            </h3>' .

            '<p><strong>Host:</strong> '
            . htmlspecialchars(
                $dbHost,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</p>' .

            '<p><strong>Database:</strong> '
            . htmlspecialchars(
                $dbName,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</p>' .

            '<p><strong>Error:</strong> '
            . htmlspecialchars(
                $mysqliError,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</p>' .

            '</div>'
        );
    }

    die(
        'Unable to connect to the database. '
        . 'Please try again later.'
    );
}

/*
|--------------------------------------------------------------------------
| MySQLi Character Set and Timezone
|--------------------------------------------------------------------------
*/

mysqli_set_charset(
    $conn,
    'utf8mb4'
);

mysqli_query(
    $conn,
    "SET time_zone = '+05:30'"
);