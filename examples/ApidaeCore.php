<?php

    ini_set('display_errors',1) ;
    error_reporting(E_ALL) ;

    require_once(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
    require_once(realpath(dirname(__FILE__)).'/../config.inc.php') ;

    $ApidaeCore = new \PierreGranger\ApidaeCore(Array('debug'=>true)) ;
    