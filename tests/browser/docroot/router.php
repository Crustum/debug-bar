<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in server (`php -S host:port router.php`).
 *
 * Static files inside the docroot pass through untouched; every other path
 * boots the test application front controller so middleware, routes, and
 * plugin assets resolve exactly like a deployed host.
 */

$path = (string)parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
