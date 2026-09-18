<?php

namespace  Middleware;

use Base\Middleware;
use Base\Request;
use Base\Response;

class TestMiddleware implements Middleware {
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response;
    }
}