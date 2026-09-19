<?php

namespace Helpers;

use Base\Response;
use Base\View;
use Users\Models\User;

/*
|--------------------------------------------------------------------------
| String helpers
|--------------------------------------------------------------------------
*/

function clean_input(string $data): string
{
    return trim(stripslashes($data));
}

function escape_html(string $data): string
{
    return htmlspecialchars(
        $data,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function str_empty(?string $value): bool
{
    return $value === null || trim($value) === '';
}

function str_not_empty(?string $value): bool
{
    return !str_empty($value);
}


/*
|--------------------------------------------------------------------------
| Path helpers
|--------------------------------------------------------------------------
*/

function join_paths(string ...$parts): string
{
    $parts = array_map(
        fn (string $part) => trim($part, '/'),
        $parts
    );

    return '/' . implode('/', array_filter(
        $parts,
        fn (string $part) => $part !== ''
    ));
}


/*
|--------------------------------------------------------------------------
| Validation helpers
|--------------------------------------------------------------------------
*/

function valid_name(string $name): bool
{
    return preg_match(
        "/^[\p{L}\p{M}][\p{L}\p{M}' -]*$/u",
        trim($name)
    ) === 1;
}

function valid_email(string $email): bool
{
    return filter_var(
        trim($email),
        FILTER_VALIDATE_EMAIL
    ) !== false;
}

function valid_url(string $url): bool
{
    return filter_var(
        trim($url),
        FILTER_VALIDATE_URL
    ) !== false;
}

function valid_website(string $url): bool
{
    $url = trim($url);

    if (!preg_match('~^https?://~i', $url)) {
        return false;
    }

    return filter_var(
        $url,
        FILTER_VALIDATE_URL
    ) !== false;
}

function valid_integer(mixed $value): bool
{
    return filter_var(
        $value,
        FILTER_VALIDATE_INT
    ) !== false;
}

function valid_positive_integer(mixed $value): bool
{
    return valid_integer($value) && (int) $value > 0;
}

function valid_float(mixed $value): bool
{
    return filter_var(
        $value,
        FILTER_VALIDATE_FLOAT
    ) !== false;
}

function valid_boolean(mixed $value): bool
{
    return filter_var(
        $value,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    ) !== null;
}

function valid_date(
    string $date,
    string $format = 'Y-m-d'
): bool {
    $parsed = \DateTime::createFromFormat($format, $date);

    return $parsed !== false
        && $parsed->format($format) === $date;
}


/*
|--------------------------------------------------------------------------
| Collection / array helpers
|--------------------------------------------------------------------------
*/

function array_get(
    array $array,
    string|int $key,
    mixed $default = null
): mixed {
    return $array[$key] ?? $default;
}

function array_has(
    array $array,
    string|int $key
): bool {
    return array_key_exists($key, $array);
}


/*
|--------------------------------------------------------------------------
| Request helpers
|--------------------------------------------------------------------------
*/

function request_method(): string
{
    return strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
}

function request_uri(): string
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

function request_path(): string
{
    return parse_url(
        request_uri(),
        PHP_URL_PATH
    ) ?: '/';
}

function is_get(): bool
{
    return request_method() === 'GET';
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function input(
    string $key,
    mixed $default = null
): mixed {
    return $_POST[$key]
        ?? $_GET[$key]
        ?? $default;
}

/*
|--------------------------------------------------------------------------
| Response helpers
|--------------------------------------------------------------------------
*/
function view(string $template, array $data = []): Response
{
    return new Response(
        View::render($template, $data)
    );
}

/*
|--------------------------------------------------------------------------
| Auth helpers
|--------------------------------------------------------------------------
*/
function auth_user(){
    if(!$_SESSION || $_SESSION['user_id']) return null;
    $user = User::find($_SESSION['user_id']);
    return $user;
} 

/*
|--------------------------------------------------------------------------
| General helpers
|--------------------------------------------------------------------------
*/

function value_or(
    mixed $value,
    mixed $default
): mixed {
    return $value ?? $default;
}

function is_json(string $value): bool
{
    json_decode($value);

    return json_last_error() === JSON_ERROR_NONE;
}