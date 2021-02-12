<?php

    namespace PierreGranger ;

    class ApidaeTimer {

        private $timelog ;
        private $pointer ;
        private $parent ;
        private $debug = false ;
        private $on = true ;
        private $pool ;

        public function __construct($on=true,$debug=false) {
            $this->timelog = Array() ;
            $this->pointer = &$this->timelog ;
            $this->on = $on ? true : false ;
            $this->pool = Array() ;
            $this->debug = $debug ? true : false ;
        }

        public function start($titre,$details=null) {

            if ( ! $this->on ) return false ;

            $titre = str_replace(PHP_EOL, '', $titre) ;

            $this->pool[] = 'start('.$titre.')' ;

            if ( $this->debug ) echo '<hr />start ' . $titre.'<br />' ;
            
            $this->pointer['sub'][$titre] = Array(
                'start' => microtime(true),
                'sub' => Array(),
                'parent' => &$this->pointer,
                'details_start' => $details
            ) ;
            
            $this->pointer = &$this->pointer['sub'][$titre] ;
            if ( $this->debug ) 
            {
                echo '<pre style="text-align:left;background:#FFAAAA;">' ;
                echo '$timelog : ' ; print_r($this->timelog) ;
                echo '$pointer : ' ; print_r($this->pointer) ;
                //echo '$parent : ' ; print_r($this->parent) ;
                echo '</pre>' ;
            }
        }

        public function stop($titre,$details=null) {

            if ( ! $this->on ) return false ;

            $titre = str_replace(PHP_EOL, '', $titre) ;

            $this->pool[] = 'stop('.$titre.')' ;

            if ( $this->debug ) echo '<hr />top' . $titre.'<br />' ;

            if ( ! isset($this->pointer['start']) )
            {
                if ( $this->debug ) echo '<hr />/!\ stop without start : ' . $titre.'<br />' ;
                return ;
            }

            $this->pointer['stop'] = microtime(true) ;
            $this->pointer['time'] = $this->pointer['stop'] - $this->pointer['start'] ;
            $this->pointer['details_stop'] = $details ;

            $this->pointer = &$this->pointer['parent'] ;
            unset($this->pointer['sub'][$titre]['parent']) ;

            if ( $this->debug ) 
            {
                echo '<pre style="text-align:left;background:#AAFFAA;">' ;
                echo '$timelog : ' ; print_r($this->timelog) ;
                echo '$pointer : ' ; print_r($this->pointer) ;
                echo '$parent : ' ; print_r($this->parent) ;
                echo '</pre>' ;
            }
        }
        public function end($titre) {
            if ( ! $this->on ) return false ;
            return $this->stop($titre) ;
        }

        public function display() {

            if ( ! $this->on ) return false ;

            if ( $this->debug )
            {
                echo '<pre style="text-align:left;display:none;">' ;
                echo json_encode($this->timelog,JSON_PRETTY_PRINT) ;
                print_r($this->timelog) ;
                echo "\n".'/* timelog'."\n".json_encode($this->timelog,JSON_PRETTY_PRINT).' */'."\n" ;
                echo "\n".'/* timelog[sub]'."\n".json_encode($this->timelog['sub'],JSON_PRETTY_PRINT).' */'."\n" ;
                echo "\n".'/* pool'."\n".json_encode($this->pool,JSON_PRETTY_PRINT).' */'."\n" ;
                echo '</pre>' ;
            }

            echo '<script>'."\n" ;
                $this->displayGroup($this->timelog['sub']) ;
            echo '</script>' ;
        }
        public function timer() {
            $this->display() ;
        }

        private function displayGroup($sub,$level=0)
        {
            if ( ! $this->on ) return false ;

            foreach ( $sub as $titre => $vals )
            {
                $css = '' ;
                if ( isset($vals['time']) && $vals['time'] > 0.2 ) $css = 'color:red;' ;

                $c = '"%c'.addslashes($titre).'","'.$css.'",'.preg_replace('#,#','.',round(@$vals['time'],3)) ;

                if ( isset($vals['details_start']) && $vals['details_start'] != null ) $c .= ','.json_encode($vals['details_start']) ;
                if ( isset($vals['details_stop']) && $vals['details_stop'] != null ) $c .= ','.json_encode($vals['details_stop']) ; 

                if ( sizeof($vals['sub']) > 0 )
                {
                    echo str_repeat("\t",$level).'console.group('.$c.') ;'."\n" ;
                    $this->displayGroup($vals['sub'],$level+1) ;
                    echo str_repeat("\t",$level).'console.groupEnd() ; // '.$titre."\n" ;
                }
                else
                {
                    echo str_repeat("\t",$level) ;
                    if ( $css != '' ) echo 'console.warn('.$c.') ;'."\n" ;
                    else echo 'console.log('.$c.') ;'."\n" ;
                }
            }
        }

        public function pause($s)
        {
            if ( ! $this->on ) return false ;

            usleep($s * 1000000) ;
        }

    }
