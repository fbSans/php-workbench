<?php

namespace Base;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class View
{
    protected static ?Environment $twig = null;

    public static function init(string $viewsPath): void
    {
        $loader = new FilesystemLoader($viewsPath);

        static::$twig = new Environment($loader);
    }

    public static function render(string $template, array $data = []): string
    {
        if (static::$twig === null) {
            throw new \RuntimeException('View system has not been initialized.');
        }

        return static::$twig->render($template, $data);
    }
}