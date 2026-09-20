<?php
namespace Middleware;

use Base\Middleware;
use Base\Request;
use Base\Response;
use Base\Session;
use Override;

class AuthMiddleware implements Middleware {
    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        if(!Session::has('user_id')){
            return Response::redirect('/login');
        }

        return $next($request);
    }
}