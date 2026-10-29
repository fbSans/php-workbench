<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
use Base\Database;
use Users\Models\User;
use Config\Config;

function fresh_migrate()
{
    $sql = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .'bootstrap.sql');
    $db = new Database();
    $pdo = $db->connection();
    User::setConnection($pdo);

    try {
        if($pdo->exec($sql) !== false){
            echo "[INFO] Database migration ran successfully!\n";
        }
        $user = User::first();

        echo $user->name;
        if($user) {
            /** @var User $user */
            $password = password_hash(Config::get('system_initial_password', ''), PASSWORD_DEFAULT); 
            $user->update(['password' => $password]);
            echo "[INFO] System password was set!\n";
        }
    } catch(PDOException $e) {
        echo $e->getMessage();
    }

}


fresh_migrate();
exit;