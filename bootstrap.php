<?php

use CoreDB\Kernel\Database\DatabaseInstallationException;
use Src\Controller\InstallController;

include __DIR__.'/vendor/autoload.php';
include __DIR__.'/Kernel/CoreDB.php';
define("IS_CLI", php_sapi_name() === 'cli');

try{
    if(is_file( __DIR__.'/config/config.php')){
        include __DIR__.'/config/config.php';
        define("CONFIGURATON_LOADED", true);
    }else{
        define("CONFIGURATON_LOADED", false);
    }
    if(!IS_CLI){
        $host = \CoreDB::baseHost();
        if(defined("TIMEZONE")){
            date_default_timezone_set(TIMEZONE);
        }
        define("BASE_URL", $_SERVER["REQUEST_SCHEME"]."://".$host.SITE_ROOT);
        session_start();

        // Enable for configuration import
        // \CoreDB::config()->clearCache();
        // \CoreDB::config()->importTableConfiguration();
        // \Src\Entity\Translation::importTranslations();
        if( defined("ENVIROMENT") && ENVIROMENT != "development"){
            Sentry\init(['dsn' => 'https://987cf3c1c7c94f0e9b8e7916b4dd004b@o487593.ingest.sentry.io/5792119' ]);
        }

        CoreDB\Kernel\Router::getInstance()->route();
    }
}catch(DatabaseInstallationException $ex){
    if(!$configurationLoaded){
        CoreDB::goTo(InstallController::getUrl());
    }else{
        echo $ex->getMessage();
    }
}

