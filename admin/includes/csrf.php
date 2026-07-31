<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (
        empty($_SESSION['ramki_csrf'])
        || !is_string($_SESSION['ramki_csrf'])
    ) {
        $_SESSION['ramki_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['ramki_csrf'];
}

function csrf_validate(): void
{
    /*
    |--------------------------------------------------------------------------
    | Accept every token format currently used in the Ramki Cards project
    |--------------------------------------------------------------------------
    | 1. X-CSRF-Token request header
    | 2. X-XSRF-Token request header
    | 3. _token POST field
    | 4. csrf_token POST field
    | 5. JSON request body
    |--------------------------------------------------------------------------
    */

    $token = trim((string)(
        $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_SERVER['HTTP_X_XSRF_TOKEN']
        ?? $_POST['_token']
        ?? $_POST['csrf_token']
        ?? ''
    ));

    if (
        $token === ''
        && str_contains(
            strtolower(
                (string)(
                    $_SERVER['CONTENT_TYPE']
                    ?? ''
                )
            ),
            'application/json'
        )
    ) {
        $rawBody =
            file_get_contents('php://input');

        $jsonBody = json_decode(
            $rawBody !== false
                ? $rawBody
                : '',
            true
        );

        if (is_array($jsonBody)) {
            $token = trim((string)(
                $jsonBody['_token']
                ?? $jsonBody['csrf_token']
                ?? ''
            ));
        }
    }

    $sessionToken = csrf_token();

    if (
        $token === ''
        || !hash_equals(
            $sessionToken,
            $token
        )
    ) {
        json_response(
            false,
            'Your session token is invalid. Refresh the page and try again.',
            null,
            419
        );
    }
}
