<?php
namespace Base;

class MiddlewarePipeline {
    public function __construct(
        private array $middlewares
    )
    {}

    public function handle(
        Request $request,
        callable $destination,
    ): Response {
        $next = $destination;
        foreach(array_reverse($this->middlewares) as $middleware){
            $next = function(Request $request) use ($middleware, $next): Response {
                return $middleware->handle($request, $next);
            };
        }

        return $next($request);
    }
}