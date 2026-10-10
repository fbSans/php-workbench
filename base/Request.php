<?php

namespace Base;

class Request
{
    public function input(
        string $key,
        mixed $default = null
    ): mixed {
        return $_REQUEST[$key] ?? $default;
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function has(string $key): bool
    {
        return isset($_REQUEST[$key]);
    }
}