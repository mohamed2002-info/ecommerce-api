<?php
// Router script for PHP's built-in server (php -S) so EVERY request is
// handled by Laravel's front controller (public/index.php).
//
// Without this, `php -S` serves the URI directly and routes like /api/health
// return 404 because there is no matching file on disk.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public';

// Serve real static files (assets, images) directly.
if ($uri !== '/' && file_exists($publicPath . $uri) && ! is_dir($publicPath . $uri)) {
    return false;
}

// Everything else -> Laravel.
require $publicPath . '/index.php';
