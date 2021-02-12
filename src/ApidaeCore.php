<?php

	namespace PierreGranger ;
	use PierreGranger\ApidaeTimer ;

	/**
	*
	* @author  Pierre Granger <pierre.granger@apidae-tourisme.com>
	*
	*/

	class ApidaeCore {

		private static $url_api = Array(
			'preprod' => 'https://api.apidae-tourisme-recette.accelance.net/',
			'prod' => 'https://api.apidae-tourisme.com/'
		) ;

		private static $url_base = Array(
			'preprod' => 'https://base.apidae-tourisme-recette.accelance.net/',
			'prod' => 'https://base.apidae-tourisme.com/'
		) ;
		
		/**
		 * 
		 * @var string prod|preprod
		 */
		private $type_prod = 'prod' ;

		protected $debug ;
		protected $timer ;

		public static $idApidae = Array(1,1157) ; // Identifiants des membres Auvergne - Rhône-Alpes Tourisme et Apidae Tourisme

		protected $_config ;

		private $token_cache ;

		public function __construct(array $params=null) {
			
			if ( isset($params['debug']) ) $this->debug = $params['debug'] ? true:false ;
			if ( isset($params['type_prod']) && in_array($params['type_prod'],Array('prod','preprod')) ) $this->type_prod = $params['type_prod'] ;

			$this->_config = $params ;

			if ( isset($params['timer']) ) $this->timer = $params['timer'] ? true:false ;
			if ( $this->timer )
			{
				$this->timer = new ApidaeTimer(true) ;
			}

			$this->token_store = Array() ;
		}

		protected function url_base() {
			return self::$url_base[$this->type_prod] ;
		}

		protected function url_api() {
			return self::$url_api[$this->type_prod] ;
		}

		public function gimme_token($clientId=null,$secret=null,$debugToken=false)
		{
			$this->start(__METHOD__) ;

			$clientId = ( $clientId != null ) ? $clientId : $this->projet_ecriture_clientId ;
			$secret = ( $secret != null ) ? $secret : $this->projet_ecriture_secret ;

			if ( isset($this->token_cache[$clientId]) )
			{
				$this->stop(__METHOD__,'token on token_cache') ;
				return $this->token_cache[$clientId] ;
			}

			$method = 'curl' ;

			if ( class_exists('\Sitra\ApiClient\Client') && $this->debug )
				$method = 'tractopelle' ;
			
			if ( $method == 'tractopelle' )
			{
				$client = new \Sitra\ApiClient\Client([
				    'ssoClientId'    => $clientId,
				    'ssoSecret'      => $secret
				]);

				$token = $client->getSsoTokenCredential() ;
				$this->stop(__METHOD__) ;
				$this->token_cache[$clientId] = $token['acces_token'] ;
				return $token['access_token'] ;
			}
			elseif ( $method == 'file_get_contents' )
			{
				// https://stackoverflow.com/a/2445332/2846837
				// https://stackoverflow.com/a/14253379/2846837

				$postdata = http_build_query(
					Array(
						'grant_type' => 'client_credentials'
					)
				) ;

				$opts = Array(
					'http' => Array(
						'method' => 'POST',
						'header' => 'Accept: application/json'."\r\n" .
									'Content-Type: application/x-www-form-urlencoded'."\r\n".
									'Authorization: Basic '.base64_encode($clientId.':'.$secret)."\r\n",
						'content' => $postdata
					)
				) ;

				$context = stream_context_create($opts) ;

				$retour = file_get_contents($this->url_api().'oauth/token',false,$context) ;
				if ( ! $retour )
				{
					if ( $this->debug )
					{
						$error = error_get_last() ;
						echo '<pre>'.print_r($error,true).'</pre>' ;
					}
					$this->stop(__METHOD__) ;
					return false ;
				}

				$retour_json = json_encode($retour) ;
				if ( json_last_error() !== JSON_ERROR_NONE )
				{
					$this->stop(__METHOD__) ;
					return false ;
				}

				$this->stop(__METHOD__) ;
				$this->token_cache[$clientId] = $retour_json->access_token ;
				return $retour_json->access_token ;
			}
			elseif ( $method == 'curl' )
			{
				$ch = curl_init() ;
				// http://stackoverflow.com/questions/15729167/paypal-api-with-php-and-curl
				curl_setopt($ch, CURLOPT_URL, $this->url_api().'oauth/token');
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				//curl_setopt($ch, CURLOPT_SSLVERSION, 6);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
				curl_setopt($ch, CURLOPT_USERPWD, $clientId.":".$secret);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
				curl_setopt($ch, CURLOPT_TIMEOUT, 4);
				
				$response = curl_exec($ch);

				$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$header = substr($response, 0, $header_size);
				$body = substr($response, $header_size);
				
				$token_json = json_decode($body) ;
				
				if ( $http_code != 200 )
				{
					$this->stop(__METHOD__) ;
					throw new \Exception(__CLASS__.':'.__METHOD__.':'.__LINE__.':http_code='.$http_code,$http_code) ;
				}

				if ( $debugToken )
				{
					echo '<pre>URL'."\n".$this->url_api().'oauth/token</pre>' ;
					echo '<pre>CURL_GETINFO'."\n".print_r(curl_getinfo($ch),true).'</pre>' ;
					echo '<pre>CURL_VERSION'."\n".print_r(curl_version(),true).'</pre>' ;
					echo '<pre>HEADER'."\n".print_r($header,true).'</pre>' ;
					echo '<pre>BODY'."\n".print_r($body,true).'</pre>' ;
					echo '<pre>token_json'."\n".print_r($token_json,true).'</pre>' ;
				}

				if ( curl_errno($ch) !== 0 )
				{
					$this->stop(__METHOD__) ;
					throw new \Exception(__LINE__.curl_error($ch), curl_errno($ch));
				}
				elseif ( json_last_error() !== JSON_ERROR_NONE )
				{
					$this->stop(__METHOD__) ;
					throw new \Exception(__LINE__.'gimme_token : le retour de curl n\'est pas une chaîne json valide');
				}
				else
				{
					$this->stop(__METHOD__) ;
					$this->token_cache[$clientId] = $token_json->access_token ;
					return $token_json->access_token ;
				}
			}
		}

		public function debug($var,$titre=null)
		{
			if ( ! $this->debug ) return ;
			echo '<p style="font-size:16px;font-weight:bold ;">[debug] '.(($titre!==null)?$titre:'').' / '.gettype($var).'</p>' ;
			echo '<textarea style="color:white;background:black;font-family:monospace;font-size:0.8em;width:100%;height:50px;">' ;
				if ( is_array($var) || is_object($var) || gettype($var) == 'boolean' ) echo var_dump($var) ;
				elseif ( $this->isJson($var) ) echo json_encode($var,JSON_PRETTY_PRINT) ;
				else echo $var ;
			echo '</textarea>' ;
		}

		// https://stackoverflow.com/questions/6041741/fastest-way-to-check-if-a-string-is-json-in-php
		protected function isJson($string) {
			json_decode($string);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		public function alerte($sujet,$msg,$mailto=null,$options=null)
		{
			if ( is_array($this->_config['mail_admin']) )
			{
				foreach ( $this->_config['mail_admin'] as $mail_admin )
				{
					if ( ! filter_var($mail_admin, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail admin incorrect : '.$mail_admin) ;
					if ( ! isset($first_mail_admin) ) $first_mail_admin = $mail_admin ;
				}
				$mails_admin = $this->_config['mail_admin'] ;
			}
			else
			{
				if ( ! filter_var($this->_config['mail_admin'], FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail admin incorrect : '.$this->_config['mail_admin']) ;
				$first_mail_admin = $this->_config['mail_admin'] ;
				$mails_admin = Array($this->_config['mail_admin']) ;
			}

			$from = ( isset($this->_config['mail_expediteur']) && filter_var($this->_config['mail_expediteur'], FILTER_VALIDATE_EMAIL) ) ? $this->_config['mail_expediteur'] : $first_mail_admin ;
			
			if ( is_array($mailto) )
			{
				foreach ( $mailto as $mt )
					if ( ! filter_var($mt, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail to incorrect'.print_r($mt,true)) ;
			}
			elseif ( $mailto !== null )
			{
				if ( ! filter_var($mailto, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail to incorrect'.print_r($mailto,true)) ;
				$mailto = Array($mailto) ;
			}
			else
				$mailto = $mails_admin ;

			$reflect = new \ReflectionClass($this) ;
			$className = $reflect->getShortName() ;

			$endline = "\n" ;
			$h1 = strip_tags($className.' - '.$sujet) ;
			$sujet = $h1 ;

			$method = 'mail' ;

			if ( class_exists('\PHPMailer\PHPMailer\PHPMailer') ) $method = 'phpmailer6' ;
			elseif ( class_exists('\PHPMailer') ) $method = 'phpmailer5' ;

			if ( $this->debug ) $sujet .= ' ['.$method.']' ;

			if ( is_array($msg) )
			{
				$new_msg = null ;
				if ( isset($msg['message']) )
				{
					$new_msg .= $msg['message'] ;
					unset($msg['message']) ;
				}
				unset($msg['x']) ; unset($msg['y']) ;
				$tble = '<table style="clear:both; background:#FFF ; font-size:11px ; margin-bottom:20px ;" border="1" cellspacing="0" cellpadding="6">' ;
				foreach ( $msg as $key => $value )
				{
					$tble .= '<tr>' ;
						$tble .= '<th><strong>'.ucfirst($key).'</strong></th>' ;
						$tble .= '<td>' ;
							if ( ! is_array($value) ) $tble .= stripslashes(nl2br($value)) ;
							else
							{
								$tble .= '<pre>'.print_r($value,true).'</pre>' ;
							}
						$tble .= '</td>' ;
					$tble .= '</tr>' ;
				}
				$tble .= '</table>' ;
				$new_msg .= $tble ;
				$msg = $new_msg ;
			}

			$message_html = '<html style="text-align : center; margin : 0; padding:0 ; font-family:Verdana ;font-size:10px ;">'.$endline  ;
				$message_html .= '<div style="text-align:left ;">'.$endline ;
					$message_html .= '<div>'.$msg.'</div>'.$endline ;
				$message_html .= '</div>'.$endline ;
			$message_html .= '</html>'.$endline ;
			
			$message_texte = strip_tags(nl2br($message_html)) ;
			
			if ( $method == 'phpmailer5' )
			{
				$mail = new \PHPMailer();
				$mail->setFrom($from) ;
				
				foreach ( $mailto as $t )
					$mail->addAddress($t) ;
				
				foreach ( $mails_admin as $mail_admin )
					$mail->AddBCC($mail_admin) ;

				$mail->CharSet = 'UTF-8' ;
				$mail->isHTML(true);
				$mail->Subject = $sujet ;
				$mail->Body    = $message_html ;
				$mail->AltBody = $message_texte ;

				return $mail->send();
			}
			elseif ( $method == 'phpmailer6' )
			{
				$mail = new \PHPMailer\PHPMailer\PHPMailer();
				$mail->setFrom($from) ;
				
				foreach ( $mailto as $t )
					$mail->addAddress($t) ;
				
				foreach ( $mails_admin as $mail_admin )
					$mail->AddBCC($mail_admin) ;

				$mail->CharSet = 'UTF-8' ;
				$mail->isHTML(true);
				$mail->Subject = $sujet ;
				$mail->Body    = $message_html ;
				$mail->AltBody = $message_texte ;
				return $mail->send();
			}
			else
			{
				$boundary = md5(time()) ;
				
				$entete = Array() ;
				$entete['From'] = $from ;
				$entete['Bcc'] = implode(',',$mails_admin) ;
				$entete['Date'] = @date("D, j M Y G:i:s O") ;
				$entete['X-Mailer'] = 'PHP'.phpversion() ;
				$entete['MIME-Version'] = '1.0' ;
				$entete['Content-Type'] = 'multipart/alternative; boundary="'.$boundary.'"' ;
				
				$message = $endline ;
				$message .= $endline."--".$boundary.$endline ;
				$message .= "Content-Type: text/plain; charset=\"utf-8\"".$endline ;
				$message .= "Content-Transfer-Encoding: 8bit".$endline ;
				$message .= $endline.strip_tags(nl2br($message_html)) ;
				$message .= $endline.$endline."--".$boundary.$endline ;
				$message .= "Content-Type: text/html; charset=\"utf-8\"".$endline ;
				$message .= "Content-Transfer-Encoding: 8bit;".$endline ;
				$message .= $endline.$message_html ;
				$message .= $endline.$endline."--".$boundary."--";
				
				$header = null ;
				foreach ( $entete as $key => $value )
				{
					$header .= $key . ' : ' . $value . $endline ;
				}

				$ret = @mail(implode(',',$mailto),$sujet,$message,$header) ;
				if ( ! $ret )
					throw new \Exception('Erreur : '.print_r(error_get_last(),true)) ;
				
				return $ret ;
			}
		}

		public function start($titre,$details=null) {
			if ( $this->timer )
				$this->timer->start($titre,$details) ;
		}
		public function stop($titre,$details=null) {
			if ( $this->timer )
				$this->timer->stop($titre,$details) ;
		}
		public function timer() {
			if ( $this->timer )
				$this->timer->timer() ;
		}

		public function showException($e) {
			echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">' ;
			echo '<div class="alert alert-danger">' ;
				echo '<h2>An error occured...</h2>' ;
				echo '<p>'.$e->getMessage().'</p>' ;
				echo '<p>Error code : '.$e->getCode().'</p>' ;
				if ( $this->debug )
					echo '<code style="font-size:.6em;white-space:pre;">'.print_r($e,true).'</code>' ;
			echo '</div>' ;
		}

	}
