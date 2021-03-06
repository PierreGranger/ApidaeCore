<?php

namespace PierreGranger;

use PierreGranger\ApidaeTimer;
use PierreGranger\ApidaeException;

/**
 *
 * @author  Pierre Granger <pierre.granger@apidae-tourisme.com>
 *
 */

class ApidaeCore
{

	private static $url_api = array(
		'preprod' => 'https://api.apidae-tourisme-recette.accelance.net/',
		'prod' => 'https://api.apidae-tourisme.com/'
	);

	private static $url_base = array(
		'preprod' => 'https://base.apidae-tourisme-recette.accelance.net/',
		'prod' => 'https://base.apidae-tourisme.com/'
	);

	/**
	 * 
	 * @var string prod|preprod
	 */
	private $type_prod = 'prod';

	protected $timeout = 15; // secondes

	protected $debug;
	protected $timer;

	public static $idApidae = array(1, 1157); // Identifiants des membres Auvergne - Rhône-Alpes Tourisme et Apidae Tourisme

	protected $_config;

	private $token_cache;

	public function __construct(array $params = null)
	{

		if (isset($params['debug'])) $this->debug = $params['debug'] ? true : false;
		if (isset($params['type_prod'])) {
			if (in_array($params['type_prod'], array_keys(self::$url_api))) $this->type_prod = $params['type_prod'];
			else throw new ApidaeException('', ApidaeException::NO_PROD);
		}

		if ($this->type_prod == 'preprod')
			$this->timeout = 30;

		$this->_config = $params;

		if (isset($params['timer'])) $this->timer = $params['timer'] ? true : false;
		if ($this->timer) {
			$this->timer = new ApidaeTimer(true);
		}

		$this->token_store = array();
	}

	public function url_base()
	{
		return self::$url_base[$this->type_prod];
	}

	public function url_api()
	{
		return self::$url_api[$this->type_prod];
	}

	public function gimme_token($clientId = null, $secret = null, $debugToken = false)
	{
		$this->start(__METHOD__);

		$clientId = ($clientId != null) ? $clientId : (isset($this->projet_ecriture_clientId) ? $this->projet_ecriture_clientId : null);
		$secret = ($secret != null) ? $secret : (isset($this->projet_ecriture_secret) ? $this->projet_ecriture_secret : null);

		if ($clientId == null || $secret == null) {
			$this->stop(__METHOD__);
			throw new ApidaeException('no clientId', ApidaeException::MISSING_PARAMETER);
		}

		if (isset($this->token_cache[$clientId])) {
			$this->stop(__METHOD__, 'token on token_cache');
			return $this->token_cache[$clientId];
		}

		$result = $this->request('/oauth/token', array(
			'USERPWD' => $clientId . ":" . $secret,
			'POSTFIELDS' => "grant_type=client_credentials",
			'format' => 'json'
		));

		$token_json = $result['object'];

		if ($result['code'] != 200) {
			$this->stop(__METHOD__);
			throw new ApidaeException('invalid token', ApidaeException::INVALID_TOKEN, array(
				'debug' => $this->debug,
				'result' => $result
			));
		}

		$this->stop(__METHOD__);
		$this->token_cache[$clientId] = $token_json->access_token;
		return $token_json->access_token;
	}

	public function debug($var, $titre = null)
	{
		if (!$this->debug) return;
		echo '<p style="font-size:16px;font-weight:bold ;">[debug] ' . (($titre !== null) ? $titre : '') . ' / ' . gettype($var) . '</p>';
		echo '<pre style="color:white;background:black;font-family:monospace;font-size:8px;width:100%;max-height:500px;overflow:auto;">';
		if (is_array($var) || is_object($var) || gettype($var) == 'boolean') print_r($var);
		elseif ($this->isJson($var)) echo json_encode($var, JSON_PRETTY_PRINT);
		else echo $var;
		echo '</pre>';
	}

	// https://stackoverflow.com/questions/6041741/fastest-way-to-check-if-a-string-is-json-in-php
	protected function isJson($string)
	{
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	public function alerte($sujet, $msg, $mailto = null, $options = null)
	{
		if (is_array($this->_config['mail_admin'])) {
			foreach ($this->_config['mail_admin'] as $mail_admin) {
				if (!filter_var($mail_admin, FILTER_VALIDATE_EMAIL)) throw new \Exception(__LINE__ . ' mail admin incorrect : ' . $mail_admin);
				if (!isset($first_mail_admin)) $first_mail_admin = $mail_admin;
			}
			$mails_admin = $this->_config['mail_admin'];
		} else {
			if (!filter_var($this->_config['mail_admin'], FILTER_VALIDATE_EMAIL)) throw new \Exception(__LINE__ . ' mail admin incorrect : ' . $this->_config['mail_admin']);
			$first_mail_admin = $this->_config['mail_admin'];
			$mails_admin = array($this->_config['mail_admin']);
		}

		$from = (isset($this->_config['mail_expediteur']) && filter_var($this->_config['mail_expediteur'], FILTER_VALIDATE_EMAIL)) ? $this->_config['mail_expediteur'] : $first_mail_admin;

		if (is_array($mailto)) {
			foreach ($mailto as $mt)
				if (!filter_var($mt, FILTER_VALIDATE_EMAIL)) throw new \Exception(__LINE__ . ' mail to incorrect' . print_r($mt, true));
		} elseif ($mailto !== null) {
			if (!filter_var($mailto, FILTER_VALIDATE_EMAIL)) throw new \Exception(__LINE__ . ' mail to incorrect' . print_r($mailto, true));
			$mailto = array($mailto);
		} else
			$mailto = $mails_admin;

		$reflect = new \ReflectionClass($this);
		$className = $reflect->getShortName();

		$endline = "\n";
		$h1 = strip_tags($className . ' - ' . $sujet);
		$sujet = $h1;

		if (is_array($msg)) {
			$new_msg = null;
			if (isset($msg['message'])) {
				$new_msg .= $msg['message'];
				unset($msg['message']);
			}
			unset($msg['x']);
			unset($msg['y']);
			$tble = '<table style="clear:both; background:#FFF ; font-size:11px ; margin-bottom:20px ;" border="1" cellspacing="0" cellpadding="6">';
			foreach ($msg as $key => $value) {
				$tble .= '<tr>';
				$tble .= '<th><strong>' . ucfirst($key) . '</strong></th>';
				$tble .= '<td>';
				if (!is_array($value)) $tble .= stripslashes(nl2br($value));
				else {
					$tble .= '<pre>' . json_encode($value, JSON_PRETTY_PRINT) . '</pre>';
				}
				$tble .= '</td>';
				$tble .= '</tr>';
			}
			$tble .= '</table>';
			$new_msg .= $tble;
			$msg = $new_msg;
		}

		$message_html = '<html style="text-align : center; margin : 0; padding:0 ; font-family:Verdana ;font-size:10px ;">' . $endline;
		$message_html .= '<div style="text-align:left ;">' . $endline;
		$message_html .= '<div>' . $msg . '</div>' . $endline;
		$message_html .= '</div>' . $endline;
		$message_html .= '</html>' . $endline;

		$message_texte = strip_tags(nl2br($message_html));

		$mail = new \PHPMailer\PHPMailer\PHPMailer();
		$mail->setFrom($from);

		foreach ($mailto as $t)
			$mail->addAddress($t);

		foreach ($mails_admin as $mail_admin)
			$mail->AddBCC($mail_admin);

		$mail->CharSet = 'UTF-8';
		$mail->isHTML(true);
		$mail->Subject = $sujet;
		$mail->Body    = $message_html;
		$mail->AltBody = $message_texte;
		return $mail->send();
	}

	public function start($titre, $details = null)
	{
		if ($this->timer)
			$this->timer->start($titre, $details);
	}
	public function stop($titre, $details = null)
	{
		if ($this->timer)
			$this->timer->stop($titre, $details);
	}
	public function timer()
	{
		if ($this->timer)
			$this->timer->timer();
	}

	public function showException($e)
	{
		ApidaeException::showException($e);
	}

	/**
	 * Cette fonction a pour but de gérer tous les appels aux API Apidae.
	 * Elle ne gère pas les erreurs elle-même, parce que selon les cas les erreurs n'ont pas la même signifiation :
	 * un retour 404 sur un objet est acceptable, mais il ne l'est pas sur un "getUserProfile" par exemple (qui suppose que l'utilisateur soit identifié, et donc qu'il existe)
	 * 
	 * @param	string	$path	chemin relatif vers l'API (/api/v002/...)
	 * @param	array|null	$params	paramètres
	 * @param	string	$params['format']	Si json : déclenchera une exception en cas de retour non json
	 * @param	string	$params['POST']
	 * @param	string	$params['CUSTOMREQUEST']	PUT
	 * @param	string	$params['POSTFIELDS']
	 * @param	string	$params['USERPWD']	couple clientId:secret
	 * @param	array	$params['header']
	 * @param	string	$params['token']	token, récupéré le plus souvent avec gimme_token
	 * @param	string	$params['url_type']	api|base (default : api)
	 * @see		ApidaeSso::getSsoToken
	 * @see		ApidaeSso::refreshSsoToken
	 * @see		ApidaeSso::getUserProfile
	 * @see		ApidaeSso::getUserPermissionOnObject
	 * 
	 */
	protected function request(string $path, $params = null)
	{

		$expr = '#^(/api/v002)?/[a-zA-Z0-9-_/]+#ui';
		if (!preg_match($expr, $path))
			throw new ApidaeException('request : wrong path', ApidaeException::INVALID_PARAMETER, array(
				'debug' => $this->debug,
				'method' => __METHOD__,
				'preg_fail' => $expr . ' failed on ' . $path
			));

		// Juste une aide pour les cas où on passe /oauth/token au lieu de /api/v002/oauth/token
		//if ( ! preg_match('#^/api/v002/#ui',$path) ) $path = '/api/v002'.$path ;

		$header = array();
		if (isset($params['header'])) $header = $params['header'];
		else {
			$header[] = 'Accept: application/json';
			if (isset($params['token'])) $header[] = "Authorization: Bearer " . $params['token'];
		}

		if (isset($params['url_type']) && $params['url_type'] == 'base')
			$url = $this->url_base() . $path;
		else
			$url = $this->url_api() . $path;

		// Remplacement des // par /
		$url = preg_replace('#([^:])//#', '$1/', $url);

		$ch = curl_init();

		$curl_opts = array();

		$curl_opts[CURLOPT_URL] = $url;

		if (sizeof($header) > 0)
			$curl_opts[CURLOPT_HTTPHEADER] = $header;

		if (isset($params['USERPWD']))
			$curl_opts[CURLOPT_USERPWD] = $params['USERPWD'];

		if (isset($params['POSTFIELDS']))
			$curl_opts[CURLOPT_POSTFIELDS] = $params['POSTFIELDS'];

		if (isset($params['CUSTOMREQUEST']))
			$curl_opts[CURLOPT_CUSTOMREQUEST] = $params['CUSTOMREQUEST'];

		if (isset($params['POST']))
			$curl_opts[CURLOPT_POST] = $params['POST'];

		$curl_opts[CURLOPT_HEADER] = true;
		$curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
		$curl_opts[CURLOPT_VERBOSE] = $this->debug;

		$curl_opts[CURLOPT_ENCODING] = 'UTF-8';
		$curl_opts[CURLOPT_RETURNTRANSFER] = true;
		$curl_opts[CURLOPT_FOLLOWLOCATION] = true;
		$curl_opts[CURLOPT_CONNECTTIMEOUT] = $this->timeout;
		$curl_opts[CURLOPT_TIMEOUT] = $this->timeout;

		curl_setopt_array($ch, $curl_opts);

		$response = curl_exec($ch);

		if ($response === false) {
			$details = [
				'curl_error' => curl_error($ch),
				'curl_opts' => $curl_opts
			];
			throw new ApidaeException('curl_response false', ApidaeException::NO_RESPONSE, $details);
		}

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		$return = array(
			'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
			'header' => substr($response, 0, $header_size),
			'body' => substr($response, $header_size)
		);

		$ret = json_decode($return['body']);
		if (json_last_error() == JSON_ERROR_NONE) {
			$return['object'] = $ret;
			$return['array'] = json_decode($return['body'], true);
		} elseif (isset($params['format']) && $params['format'] == 'json') {
			$details = [
				'debug' => $this->debug,
				'curl_opts' => $curl_opts,
				'return' => @$return
			];
			throw new ApidaeException('response body is not json', ApidaeException::NO_JSON, $details);
		}

		return $return;
	}
}
