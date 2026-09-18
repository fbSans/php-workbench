<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
// Register application routes
require_once dirname(__DIR__) . '/routes/web.php';

use Routes\Route;
use Init\Init;

Init::init();

// Dispatch the current request.
$response = Route::dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($response === null) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}



$response->send();