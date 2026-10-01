<?php

declare(strict_types=1);

/**
 * Router for the PHP built-in server.
 *
 * Start the app with:
 *   php -S localhost:8000 -t public public/router.php
 *
 * - /api.php*         → executed by the API front controller
 * - /assets/*, favicon → served as static files by the built-in server
 * - everything else   → executed by the web (Twig) front controller
 */

$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = $uri === '' ? '/' : $uri;

// API entry point: run it directly so PATH_INFO-style routes stay deterministic.
if ($path === '/api.php' || str_starts_with($path, '/api.php/')) {
    require __DIR__ . '/api.php';

    return true;
}

// Static files: never fall through to the front controller.
$documentRoot = realpath(__DIR__);
$candidate = $documentRoot !== false ? realpath($documentRoot . $path) : false;
if (
    $documentRoot !== false
    && $candidate !== false
    && is_file($candidate)
    && str_starts_with($candidate, $documentRoot)
) {
    return false; // let the built-in server deliver the file
}

// Page routes → Twig front controller.
require __DIR__ . '/index.php';

return true;
