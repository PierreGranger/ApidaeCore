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
    private static $url_api = [
        'local' => 'http://localhost:8080/',
        'prod' => 'https://api.apidae-tourisme.com/',
        'dev' => 'https://api.apidae-tourisme.dev/',
        'cooking' => 'https://api.apidae-tourisme.cooking/'
    ];

    private static $url_base = [
        'local' => 'http://localhost:8080/',
        'prod' => 'https://base.apidae-tourisme.com/',
        'dev' => 'https://base.apidae-tourisme.dev/',
        'cooking' => 'https://base.apidae-tourisme.cooking/'
    ];

    /**
     *
     * @var string local|prod|dev|cooking
     */
    private $env = 'prod';

    protected $timeout = 15; // secondes

    protected $debug;
    protected $timer;

    public static $idApidae = [1, 1157]; // Identifiants des membres Auvergne - Rhône-Alpes Tourisme et Apidae Tourisme

    protected $_config;

    private $token_cache;

    protected $lastPostfields;
    protected $lastResult;

    protected $custom_url_api = null;
    protected $custom_url_base = null;

    public function __construct(array $params = null)
    {
        if (isset($params['debug'])) {
            $this->debug = $params['debug'] ? true : false;
        }
        if (isset($params['type_prod']) && !isset($params['env'])) {
            $params['env'] = $params['type_prod'];
        }

        if (isset($params['env'])) {
            if (in_array($params['env'], array_keys(self::$url_api))) {
                $this->env = $params['env'];
            } else {
                throw new ApidaeException('', ApidaeException::NO_PROD);
            }
        }

        if (isset($params['url_api'])) {
            $this->custom_url_api = $params['url_api'];
        }
        if (isset($params['url_base'])) {
            $this->custom_url_base = $params['url_base'];
        }

        if (in_array($this->env, ['preprod', 'dev'])) {
            $this->timeout = 30;
        }

        $this->_config = $params;

        if (isset($params['timer'])) {
            $this->timer = $params['timer'] ? true : false;
        }
        if ($this->timer) {
            $this->timer = new ApidaeTimer(true);
        }
    }

    public function url_base()
    {
        if ($this->custom_url_base != null) {
            return $this->custom_url_base;
        }
        return self::$url_base[$this->env];
    }

    public function url_api()
    {
        if ($this->custom_url_api != null) {
            return $this->custom_url_api;
        }
        return self::$url_api[$this->env];
    }

    public function setTimeout(int $timeout)
    {
        if ((int)$timeout > 5 && (int)$timeout < 600) {
            $this->timeout = (int)$timeout;
        }
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

        $result = $this->request('/oauth/token', [
            'USERPWD' => $clientId . ":" . $secret,
            'POSTFIELDS' => "grant_type=client_credentials",
            'format' => 'json'
        ]);

        if ($result['code'] != 200) {
            $this->stop(__METHOD__);
            throw new ApidaeException('invalid token', ApidaeException::INVALID_TOKEN, [
                'debug' => $this->debug,
                'result' => $result
            ]);
        }

        $this->stop(__METHOD__);
        $this->token_cache[$clientId] = $result['access_token'];
        return $result['access_token'];
    }

    public function debug($var, $titre = null)
    {
        if (!$this->debug) {
            return;
        }
        if (php_sapi_name() !== 'cli') {
            echo '<p style="font-size:16px;font-weight:bold ;">[debug] ' . (($titre !== null) ? $titre : '') . ' / ' . gettype($var) . '</p>' . PHP_EOL;
            echo '<pre style="color:white;background:black;font-family:monospace;font-size:8px;width:100%;max-height:500px;overflow:auto;">' . PHP_EOL;
            if (is_object($var) || gettype($var) == 'boolean') {
                print_r($var);
            } elseif (is_array($var) || $this->isJson($var)) {
                echo json_encode($var, JSON_PRETTY_PRINT);
            } else {
                echo $var;
            }
            echo PHP_EOL . '</pre>' . PHP_EOL;
        } else {
            if ($titre) {
                echo $titre . PHP_EOL;
            }
            if (is_object($var) || gettype($var) == 'boolean') {
                print_r($var);
            } elseif (is_array($var) || $this->isJson($var)) {
                echo json_encode($var, JSON_PRETTY_PRINT);
            } else {
                echo $var;
            }
            echo PHP_EOL;
        }
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
                if (!filter_var($mail_admin, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception(__LINE__ . ' mail admin incorrect : ' . $mail_admin);
                }
                if (!isset($first_mail_admin)) {
                    $first_mail_admin = $mail_admin;
                }
            }
            $mails_admin = $this->_config['mail_admin'];
        } else {
            if (!filter_var($this->_config['mail_admin'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception(__LINE__ . ' mail admin incorrect : ' . $this->_config['mail_admin']);
            }
            $first_mail_admin = $this->_config['mail_admin'];
            $mails_admin = [$this->_config['mail_admin']];
        }

        $from = (isset($this->_config['mail_expediteur']) && filter_var($this->_config['mail_expediteur'], FILTER_VALIDATE_EMAIL)) ? $this->_config['mail_expediteur'] : $first_mail_admin;

        if (is_array($mailto)) {
            foreach ($mailto as $mt) {
                if (!filter_var($mt, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception(__LINE__ . ' mail to incorrect' . print_r($mt, true));
                }
            }
        } elseif ($mailto !== null) {
            if (!filter_var($mailto, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception(__LINE__ . ' mail to incorrect' . print_r($mailto, true));
            }
            $mailto = [$mailto];
        } else {
            $mailto = $mails_admin;
        }

        $endline = "\n";
        $h1 = strip_tags($sujet);
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
                if (!is_array($value)) {
                    $tble .= stripslashes(nl2br($value));
                } else {
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

        foreach ($mailto as $t) {
            $mail->addAddress($t);
        }

        foreach ($mails_admin as $mail_admin) {
            $mail->AddBCC($mail_admin);
        }

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message_html;
        $mail->AltBody = $message_texte;
        return $mail->send();
    }

    public function start($titre, $details = null)
    {
        if ($this->timer) {
            $this->timer->start($titre, $details);
        }
    }
    public function stop($titre, $details = null)
    {
        if ($this->timer) {
            $this->timer->stop($titre, $details);
        }
    }
    public function timer()
    {
        if ($this->timer) {
            $this->timer->timer();
        }
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
        $this->lastResult = null;

        $expr = '#^(/api/v002)?/[a-zA-Z0-9-_/]+#ui';
        if (!preg_match($expr, $path)) {
            throw new ApidaeException('request : wrong path', ApidaeException::INVALID_PARAMETER, [
                'debug' => $this->debug,
                'method' => __METHOD__,
                'preg_fail' => $expr . ' failed on ' . $path
            ]);
        }

        $header = [];
        if (isset($params['header'])) {
            $header = $params['header'];
        }

        $header[] = 'Accept: application/json';
        if (isset($params['token'])) {
            $header[] = "Authorization: Bearer " . $params['token'];
        }

        if (isset($params['url_type']) && $params['url_type'] == 'base') {
            $url = $this->url_base() . $path;
        } else {
            $url = $this->url_api() . $path;
        }

        // Remplacement des // par /
        $url = preg_replace('#([^:])//#', '$1/', $url);

        $ch = curl_init();

        $curl_opts = [];

        $curl_opts[CURLOPT_URL] = $url;

        if (sizeof($header) > 0) {
            $curl_opts[CURLOPT_HTTPHEADER] = $header;
        }

        if (isset($params['USERPWD'])) {
            $curl_opts[CURLOPT_USERPWD] = $params['USERPWD'];
        }

        if (isset($params['POSTFIELDS'])) {
            $curl_opts[CURLOPT_POSTFIELDS] = $params['POSTFIELDS'];
            $this->lastPostfields = $params['POSTFIELDS'];
        }

        if (isset($params['CUSTOMREQUEST'])) {
            $curl_opts[CURLOPT_CUSTOMREQUEST] = $params['CUSTOMREQUEST'];
        }

        if (isset($params['POST'])) {
            $curl_opts[CURLOPT_POST] = $params['POST'];
        }

        $curl_opts[CURLOPT_HEADER] = true;
        $curl_opts[CURLOPT_SSL_VERIFYPEER] = false;

        /** Le verbose affiche surtout des infos d'identification, en général peu utiles */
        //$curl_opts[CURLOPT_VERBOSE] = $this->debug;

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

        $return = [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'header' => substr($response, 0, $header_size),
            'body' => substr($response, $header_size)
        ];

        $body_array = json_decode($return['body'], true);

        if (json_last_error() == JSON_ERROR_NONE) {
            if (is_array($body_array)) {
                $return = array_merge($return, $body_array);
            }
        } elseif (isset($params['format']) && $params['format'] == 'json') {
            $details = [
                'debug' => $this->debug,
                'curl_opts' => $curl_opts,
                'return' => @$return
            ];
            throw new ApidaeException('response body is not json', ApidaeException::NO_JSON, $details);
        }

        $this->lastResult = $return;
        return $return;
    }

    /**
     * Renvoie le détail de $result de ApidaeCore::request
     * En cas de retour correct, renvoie un tableau :
     * [
     * 	'code' => 200,
     * 	'header' => 'HTTP/1.1 100 Continue...',
     * 	'body' => '{"status":"MODIFICATION_VALIDATION_ASKED"}',
     * 	'object' => {
     * 		'status' => 'MODIFICATION_VALIDATION_ASKED'
     * 	}
     * 	'array' => [
     * 		'status' => 'MODIFICATION_VALIDATION_ASKED'
     * 	]
     * ]
     *
     * Exemple erreur
     * [
     * 	'body' => '{"message":"Cet objet est déjà en cours de modification","errorType":"ECRITURE_FORBIDDEN}',
     * 	'object' => {
     * 		'message' => 'Cet objet...',
     * 		'errorType' => 'ECRITURE_FORBIDDEN,
     * 	},
     * 	'array' => [
     * 		'message' => 'Cet objet...',
     * 		'errorType' => 'ECRITURE_FORBIDDEN,
     * 	]
     * ]
     */
    public function lastResult()
    {
        return $this->lastResult;
    }

    public function lastPostfields()
    {
        return $this->lastPostfields;
    }
    public function lastRequest()
    {
        return $this->lastPostfields();
    }

    public function getEnv()
    {
        return $this->env ;
    }
}
