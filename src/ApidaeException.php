<?php

    namespace PierreGranger ;
    
    /**
     * Cette classe étend le fonctionnement des exceptions pour mieux comprendre quelle est l'erreur rencontrée, dans le contexte d'Apidae.
     * message et code sont standard : en revanche on peut passer comme 3ème paramètre un tableau de détail.
     * Ce tableau doit impérativement contenir une première valeur 'debug' => true pour permettre l'affichage du détail.
     * Le détail peut contenir des données privées (ssoClientId, apiKey...) : il ne doit donc être affiché que lorsque le développeur le souhaite.
     */

    class ApidaeException extends \Exception {

        const UNIDENTIFIED_ERROR = 0 ;
        const NO_TOKEN = 1 ;
        const NO_SCOPE = 2 ;
        const NO_ERROR = 3 ;
        const NO_JSON = 4 ;
        const NO_RESPONSE = 5 ;
        const NO_BODY = 6 ;
        const NOT_CONNECTED = 7 ;
        const NO_PROD = 8 ;
        const MISSING_PARAMETER = 9 ;
        const INVALID_PARAMETER = 10 ;
        const INVALID_HTTPCODE = 11 ;
        const INVALID_TOKEN = 12 ;
    
        /**
         * @param   string  $message    Message public affiché en cas d'erreur
         * @param   int     $code       Code erreur si possible parmi la liste de CONST ci-dessous
         * @param   array|null   $details   Détails sous forme de tableau, pour permettre un debuguage plus facile : doit contenir une valeur debug => true pour être utilisé
         */
        public function __construct($message,$code=0,$details=null,\Exception $previous=null) {
            parent::__construct($message, $code, $previous) ;
            if ( is_array($details) && isset($details['debug']) && $details['debug'] === true )
            {
                unset($details['debug']) ;
                $this->details = $details ;
            }
        }

        private static function extractBody($body) {
            if ( preg_match_all('#<p><b>(.+)</b>(.+)</p>#Ui',$body,$match) )
            {
                $ret = Array() ;
                foreach ( $match[1] as $k => $v )
                    $ret[$v] = strip_tags($match[2][$k]) ;
                $ret['body'] = htmlentities($body) ;
                return $ret ;
            }
            else
            {
                $tmp = json_decode($body) ;
                if ( json_last_error() === JSON_ERROR_NONE )
                {
                    foreach ( $tmp as $k => $v )
                        $ret[$k] = $v ;
                }
                return $ret ;
            }
            
            $body = htmlentities($body) ;
            return $body ;
        }

        /*
        <html><head><title>Apache Tomcat/7.0.76 - Error report</title><style><!--H1 {font-family:Tahoma,Arial,sans-serif;color:white;background-color:#525D76;font-size:22px;} H2 {font-family:Tahoma,Arial,sans-serif;color:white;background-color:#525D76;font-size:16px;} H3 {font-family:Tahoma,Arial,sans-serif;color:white;background-color:#525D76;font-size:14px;} BODY {font-family:Tahoma,Arial,sans-serif;color:black;background-color:white;} B {font-family:Tahoma,Arial,sans-serif;color:white;background-color:#525D76;} P {font-family:Tahoma,Arial,sans-serif;background:white;color:black;font-size:12px;}A {color : black;}A.name {color : black;}HR {color : #525D76;}--></style> </head><body>
            <h1>HTTP Status 404 - Not Found</h1>
            <HR size="1" noshade="noshade">
                <p><b>type</b> Status report</p>
                <p><b>message</b> <u>Not Found</u></p>
                <p><b>description</b> <u>The requested resource is not available.</u></p>
                <HR size="1" noshade="noshade">
                <h3>Apache Tomcat/7.0.76</h3>
        </body></html>
        */


        public function getDetails() {
            return $this->details ;
        }

        public static function showException($e,$show=true) {

            if ( get_class($e) == __CLASS__ )
            {
                $fooClass = new \ReflectionClass (__CLASS__);
                $constants = $fooClass->getConstants() ;
                if ( ( $tmp = array_search($e->code,$constants) ) !== false )
                    $const = $tmp ;
            }

            $code = isset($const) ? $const : '#'.$e->code ;

            $ret = '<div style="background:#fcf8e3;padding:10px;">' ;
                $ret .= '<h2>Une erreur est survenue</h2>' ;
                $ret .= '<strong>ApidaeException '.$code.' : '.$e->message."\n";
                if ( isset($e->details) && is_array($e->details) )
                {
                    if (
                        isset($e->details['response'])
                        //&& preg_match('#^GuzzleHttp\\.*\\Response$#ui',get_class($e->details['response']))
                    )
                    {
                        $e->details['statusCode'] = $e->details['response']->getStatusCode() ;
                        $e->details['reasonPhrase'] = $e->details['response']->getReasonPhrase() ;
                        $e->details['body'] = self::extractBody($e->details['response']->getBody()) ;
                        unset($e->details['response']) ;
                    }

                    if ( isset($details['body']) ) $details['body'] = self::extractBody($details['body']) ;
                    elseif ( isset($details['return']['body']) ) $details['return']['body'] =self::extractBody($details['return']['body']) ;

                    $ret .= '<pre style="background:black;color:white;">'.print_r($e->details,true).'</pre>' ;
                }
            $ret .= '</div>' ;
            if ( $show ) echo $ret ;
            else return $ret ;
        }

        public function __toString() {
            return $this->showException($this,false) ;
        }

    }