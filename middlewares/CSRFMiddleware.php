<?php

namespace Middleware;

use Base\Middleware;
use Base\Request;
use Base\Response;
use Base\Session;
use Override;

class CSRFMiddleware implements Middleware
{
    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        

        if ( !$request->has('csrf_token') ||
             !Session::has('csrf_token') || 
             Session::get('csrf_token') !== $request->input('csrf_token'))
        {
            return Response::redirect('/login');
        }

        $response = $next($request);

        return $response;
    }
}
