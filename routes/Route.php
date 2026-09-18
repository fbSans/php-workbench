<?php

namespace Routes;

use Base\MiddlewarePipeline;
use Base\Request;
use Base\Response;

final class Route
{
    private static array $routes = [];

    private string $method;
    private string $uri;
    private mixed $action;
    private array $middlewares = [];

    public function __construct(
        string $method,
        string $uri,
        mixed $action
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->action = $action;
    }

    public function middleware(string ...$middleware): static
    {
        $this->middlewares = [
            ...$this->middlewares,
            ...$middleware,
        ];

        return $this;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function action(): mixed
    {
        return $this->action;
    }

    public function middlewares(): array
    {
        return $this->middlewares;
    }

    public static function get(
        string $uri,
        mixed $action
    ): static {
        return self::add('GET', $uri, $action);
    }

    public static function post(
        string $uri,
        mixed $action
    ): static {
        return self::add('POST', $uri, $action);
    }

    private static function add(
        string $method,
        string $uri,
        mixed $action
    ): static {
        $route = new static($method, $uri, $action);

        self::$routes[] = $route;

        return $route;
    }

    public function run(array $params): Response
    {
        $request = new Request();
        $pipeline = new MiddlewarePipeline($this->resolveMiddlewares());

        return $pipeline->handle($request, function (Request $request) use ($params) {
            return $this->invoke($this->action, $params, $request);
        });
    }

    public static function dispatch(
        string $method,
        string $uri
    ): Response|null {
        foreach (self::$routes as $route) {
            if ($route->method() !== strtoupper($method)) {
                continue;
            }

            $pattern = self::compile($route->uri());

            if (preg_match($pattern, $uri, $matches) !== 1) {
                continue;
            }

            // Remove the complete match.
            array_shift($matches);

            // Keep only named captures.
            $params = array_filter(
                $matches,
                fn($key) => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            return $route->run($params);
        }

        return null;
    }

    private static function compile(string $uri): string
    {
        /*
         * /users/{id}
         * /users/{id}/posts/{post}
         *
         * becomes:
         *
         * ~^/users/(?P<id>[^/]+)/posts/(?P<post>[^/]+)$~
         */

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            fn($match) => '(?P<' . $match[1] . '>[^/]+)',
            $uri
        );

        return '~^' . $pattern . '$~';
    }

    private function invoke(mixed $action, array $params, Request $request): Response
    {
        /*
         * Resolve [ClassName::class, 'method'] into
         * [new ClassName(), 'method'] for non-static methods.
         */
        if (is_array($action) && is_string($action[0])) {
            $class = $action[0];
            $method = $action[1];

            $reflection = new \ReflectionMethod($class, $method);

            if (!$reflection->isStatic()) {
                $action = [new $class(), $method];
            }
        }

        $reflection = is_array($action)
            ? new \ReflectionMethod($action[0], $action[1])
            : new \ReflectionFunction($action);

        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            /*
             * Inject the Request object.
             */
            if (
                $type instanceof \ReflectionNamedType &&
                $type->getName() === Request::class
            ) {
                $arguments[] = $request;
                continue;
            }

            /*
             * Resolve route parameters.
             */
            if (array_key_exists($name, $params)) {
                $arguments[] = $params[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Missing route parameter: {$name}"
                );
            }
        }

        return $action(...$arguments);
    }

    private function resolveMiddlewares(): array
    {
        $definitions = require dirname(__DIR__) . '/configs/middleware.php';
        return array_map(
            function (string $middleware) use ($definitions) {
                $class = $definitions[$middleware] ?? $middleware;

                return new $class();
            },
            $this->middlewares
        );
    }
}
