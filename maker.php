<?php

define('MODULES_PATH', __DIR__ . DIRECTORY_SEPARATOR .'modules');

function show_modules(){
    $handle = opendir(MODULES_PATH);
    while($filename = readdir($handle)){
        if($filename == '.' || $filename === '..') continue;
        if(is_dir(MODULES_PATH . DIRECTORY_SEPARATOR . $filename)) echo "$filename\n";
    }
}

function module_exists(string $name){
    $handle = opendir(MODULES_PATH);
    while($filename = readdir($handle)){
        if($filename == '.' || $filename === '..') continue;
        if(is_dir(MODULES_PATH . DIRECTORY_SEPARATOR . $filename)) {
            if($filename === $name) return true;
        }
    }

    return false;
}

function module_make(string $name)
{
    if(module_exists($name)){ 
        echo "Module already exists.";
        return;
    }

    $module_dir = MODULES_PATH . DIRECTORY_SEPARATOR . $name;
    $models_dir = $module_dir . DIRECTORY_SEPARATOR . 'Controllers';
    $controllers_dir = $module_dir . DIRECTORY_SEPARATOR . 'Models';

    $makedir_and_log = function($dirpath){
        if(mkdir($dirpath)){
            echo "Created $dirpath.\n";
        }
    };
     
    if(!file_exists($module_dir)){
        echo "Start making modules...\n";
        $makedir_and_log($module_dir);
        $makedir_and_log($models_dir);
        $makedir_and_log($controllers_dir);
    } 
}

module_make('qrcode');


