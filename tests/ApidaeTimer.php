<?php

    ini_set('display_errors',1) ;
    error_reporting(E_ALL) ;

    require_once(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
    require_once(realpath(dirname(__FILE__)).'/../config.inc.php') ;

    $tl = new \PierreGranger\ApidaeTimer(true,true) ;

    echo '<h2>ça se passe dans la console :)</h2>' ;

    $tl->start('main') ;
        $tl->pause(0.2) ;
        $tl->start('head') ;
            $tl->pause(0.2) ;
        $tl->stop('head') ;
        $tl->start('body') ;
            $tl->pause(0.2) ;
            $tl->start('into',Array('start of something...','other')) ;
            $tl->stop('into',Array('end of something...','still another')) ;
        $tl->stop('body') ;
        $tl->start('foot') ;
            $tl->pause(0.2) ;
        $tl->stop('foot') ;
    $tl->stop('main') ;

    $tl->display() ;