<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$sujet = __FILE__ ;
	
	$to = Array(
		'pierre.granger@apidae-tourisme.com'
	) ;

	$admins = Array(
		'pierre.granger@apidae-tourisme.com'
	) ;

	$message = Array(
		'titre' => 'Mon titre',
		'adresse1' => 'Adresse 1',
		'from' => implode(', ',$admins),
		'to' => implode(', ',$to)
	) ;


	if (php_sapi_name() !== "cli") echo '<pre>' ;
    
	foreach ( $admins as $admin )
	{
		$ApidaeCore = new \PierreGranger\ApidaeCore(array_merge(
            $configApidaeCore,
            Array('debug'=>true)
        )) ;
		
		try {
			echo 'Envoi de '.$admin.' à '.implode(',',$to).' : ' ;
			$sujet = basename(__FILE__).' '.$admin.' => '.implode(',',$to) ;
			$ret = $ApidaeCore->alerte($sujet,$message,$to) ;
			var_dump($ret) ;
			echo "\n" ;
		}
		catch ( Exception $e ) {
            echo '<div class="alert alert-danger">' ;
                echo '<h2>Exception '.__FILE__.':'.__LINE__.'</h2>' ;
			    echo '<code>'.print_r($e,true).'</code>' ;
            echo '</div>' ;
		}

	}

?><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">