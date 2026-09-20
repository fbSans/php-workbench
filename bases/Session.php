<?php

namespace Base;

class Session {
    public static function start()
    {
        session_start();
    }

    public static function destroy()
    {
        session_destroy();
    }

    public static function get(string $key, mixed $default = null)
    {
        return $_SESSION[$key] ?? $default;   
    }

    public static function set(string $key, mixed $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function unset(string $key) {
        unset($_SESSION[$key]);
    }

    public static function has(string $key)
    {
        return isset($_SESSION[$key]);
    }
}