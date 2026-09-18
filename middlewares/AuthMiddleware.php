<?php
namespace Middleware;

use Base\Middleware;
use Base\Request;
use Base\Response;
use Override;

class AuthMiddleware implements Middleware {
    #[Override]
    public function handle(Request $request, callable $next): Response
    {
        if(!isset($_SESSION) || !isset($_SESSION['user_id'])){
            return Response::redirect('/login');
        }

        return $next($request);
    }
}