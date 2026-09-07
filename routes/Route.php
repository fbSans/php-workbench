<?php

namespace Routes;

final class Route
{
    private static array $routes = [];

    public static function get(
        string $uri,
        mixed $action
    ): void {
        self::add('GET', $uri, $action);
    }

    public static function post(
        string $uri,
        mixed $action
    ): void {
        self::add('POST', $uri, $action);
    }

    private static function add(
        string $method,
        string $uri,
        mixed $action
    ): void {
        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
        ];
    }

    public static function dispatch(
        string $method,
        string $uri
    ): mixed {
        foreach (self::$routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $pattern = self::compile($route['uri']);

            if (preg_match($pattern, $uri, $matches) !== 1) {
                continue;
            }

            // Remove the complete match.
            array_shift($matches);

            // Keep only named captures.
            $params = array_filter(
                $matches,
                fn ($key) => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            return self::invoke($route['action'], $params);
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
            fn ($match) => '(?P<' . $match[1] . '>[^/]+)',
            $uri
        );

        return '~^' . $pattern . '$~';
    }

    private static function invoke(mixed $action, array $params): mixed 
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
                $type->getName() === \Base\Request::class
            ) {
                $arguments[] = new \Base\Request();
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
}