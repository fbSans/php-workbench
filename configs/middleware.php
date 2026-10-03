<?php

namespace Config;

use Middleware\AuthMiddleware;
use Middleware\CSRFMiddleware;

return [
    'auth' => AuthMiddleware::class,
    'csrf' => CSRFMiddleware::class,
];