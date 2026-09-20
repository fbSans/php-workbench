<?php
namespace Init;

use Base\View;
use Base\Model;
use Base\Session;
use Config\Config;
class Init {
    public static function init(){
        Session::start();
        //Initialize the Template manager
        View::init(dirname(__DIR__) . '/views');
        //Initialize database connection
        $adapter = Config::get('DB_ADAPTER');
        $host = Config::get('DB_HOST');
        $dbname = Config::get('DB_NAME');
        $port = Config::get('DB_PORT');
        $username = Config::get('DB_USER');
        $password = Config::get('DB_PASSWORD');
        $pdo = new \PDO("$adapter:host=$host;dbname=$dbname;port=$port;charset=utf8", $username, $password);
        Model::setConnection($pdo);
    }
}