<?php

namespace Base;
use Config\Config;

class Database {
    private ?\PDO $pdo = null;

    public function __construct(){
        $adapter = Config::get('DB_ADAPTER');
        $host = Config::get('DB_HOST');
        $dbname = Config::get('DB_NAME');
        $port = Config::get('DB_PORT');
        $username = Config::get('DB_USER');
        $password = Config::get('DB_PASSWORD');
        $this->pdo = new \PDO("$adapter:host=$host;dbname=$dbname;port=$port;charset=utf8", $username, $password);
    }
    
    public function closeConnection(): void
    {
        $this->pdo = null;
    }

    public function connection(): \PDO
    {
        if ($this->pdo === null) {
            throw new \RuntimeException(
                'Database connection has not been initialized.'
            );
        }

        return $this->pdo;
    }
}