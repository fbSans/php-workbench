<?php

namespace Config;
use Dotenv\Dotenv;
use function Helpers\join_paths;


class Config {
    protected static $config = null; 
    public static function init(){
        $__BASE_DIR = dirname(__DIR__);
        $dotenv = Dotenv::createImmutable($__BASE_DIR);
        $dotenv->safeLoad();
        
        self::$config = [
            'DB_ADAPTER' => $_ENV['DB_ADAPTER'] ?? 'mysql',
            'DB_NAME' => $_ENV['DB_NAME'] ?? '',
            'DB_USER' => $_ENV['DB_USER'] ?? 'dev',
            'DB_PASSWORD' => $_ENV['DB_PASSWORD'] ?? '',
            'DB_HOST' => $_ENV['DB_HOST'] ?? '127.0.0.1',

            'APP_ENV' => $_ENV['APP_ENV'] ?? 'development',
            'APP_KEY' => $_ENV['APP_KEY'] ?? '',

            'STORAGE_DIR' => join_paths($__BASE_DIR, 'storage'),
            'STORAGE_TEMP' => join_paths($__BASE_DIR, 'storage', 'tmp'),

            'UPLOAD_MAX_SIZE' => 40 * 1024 * 1024,
            'STORAGE_DIR_PERMISSIONS' => 0775,
        ];

        foreach($_ENV as $key => $value){
            self::$config[$key] = $value;
        }
    }
        
    public static function get(string $name, mixed $default = null): mixed
    {
        if(self::$config === null) {
            self::init();
        }
        return self::$config[$name] ?? $default;
    }
}