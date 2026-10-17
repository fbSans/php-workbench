<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
// Register application routes
require_once dirname(__DIR__) . '/routes/web.php';

use Base\Route;
use Init\Init;

use function Helpers\view;

Init::init();

// Dispatch the current request.
$response = Route::dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($response === null) {
    $response = view('error404.html.twig', ['error_message' => '404 Not Found'], status: 404);
}



$response->send();
exit;