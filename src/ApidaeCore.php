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

    /**
     * Tokens OAuth par clientId : ['token' => string, 'expires_at' => ?int]
     * @var array<string, array{token: string, expires_at: ?int}>
     */
    private array $token_cache = [];

    /**
     * Marge (secondes) avant l'expiration annoncée par l'API à partir de laquelle on redemande un token.
     */
    protected const TOKEN_EXPIRY_MARGIN = 60;

    /**
     * Clés de POSTFIELDS dont la valeur ne doit jamais apparaître dans les détails d'une exception ou d'un log.
     */
    protected const SENSITIVE_KEYS = ['tokenSSO', 'token', 'access_token', 'refresh_token', 'secret', 'client_secret', 'password', 'code'];

    protected $lastPostfields;
    protected $lastResult;

    protected $custom_url_api = null;
    protected $custom_url_base = null;

    public string $projet_ecriture_clientId;
    protected string $projet_ecriture_secret;

    public function __construct(?array $params = null)
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

    public function gimme_token($clientId = null, $secret = null)
    {
        $this->start(__METHOD__);
        
        $clientId = $clientId ?? $this->projet_ecriture_clientId ;
        $secret = $secret ?? $this->projet_ecriture_secret ;

        if ($clientId == null || $secret == null) {
            $this->stop(__METHOD__);
            throw new ApidaeException('no clientId', ApidaeException::MISSING_PARAMETER);
        }

        if (isset($this->token_cache[$clientId])) {
            $cached = $this->token_cache[$clientId];
            if ($cached['expires_at'] === null || $cached['expires_at'] > time()) {
                $this->stop(__METHOD__, 'token on token_cache');
                return $cached['token'];
            }
            unset($this->token_cache[$clientId]);
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
        $expiresAt = null;
        if (isset($result['expires_in']) && is_numeric($result['expires_in'])) {
            $expiresAt = time() + max(0, (int) $result['expires_in'] - static::TOKEN_EXPIRY_MARGIN);
        }
        $this->token_cache[$clientId] = [
            'token' => $result['access_token'],
            'expires_at' => $expiresAt,
        ];
        return $result['access_token'];
    }

    /**
     * Oublie le token en cache (tous les clientId si null), par exemple après une réponse 401.
     */
    public function forgetToken(?string $clientId = null): void
    {
        if ($clientId === null) {
            $this->token_cache = [];
            return;
        }
        unset($this->token_cache[$clientId]);
    }

    /**
     * Retire les secrets (identifiants, tokens) d'un tableau d'options curl avant de le placer dans une exception.
     *
     * @param array<int, mixed> $curl_opts
     * @return array<int|string, mixed>
     */
    public static function sanitizeCurlOpts(array $curl_opts): array
    {
        if (isset($curl_opts[CURLOPT_USERPWD])) {
            $curl_opts[CURLOPT_USERPWD] = '***';
        }
        if (isset($curl_opts[CURLOPT_HTTPHEADER]) && is_array($curl_opts[CURLOPT_HTTPHEADER])) {
            $curl_opts[CURLOPT_HTTPHEADER] = array_map(
                static fn ($h) => is_string($h) && preg_match('#^\s*(authorization|cookie)\s*:#i', $h)
                    ? preg_replace('#:.*$#s', ': ***', $h)
                    : $h,
                $curl_opts[CURLOPT_HTTPHEADER]
            );
        }
        if (isset($curl_opts[CURLOPT_POSTFIELDS])) {
            $curl_opts[CURLOPT_POSTFIELDS] = self::sanitizePostfields($curl_opts[CURLOPT_POSTFIELDS]);
        }
        return $curl_opts;
    }

    /**
     * @param mixed $postfields tableau ou query string
     * @return mixed
     */
    public static function sanitizePostfields(mixed $postfields): mixed
    {
        if (is_array($postfields)) {
            foreach ($postfields as $k => $v) {
                if (is_string($k) && in_array(strtolower($k), array_map('strtolower', static::SENSITIVE_KEYS), true)) {
                    $postfields[$k] = '***';
                } elseif ($v instanceof \CURLFile) {
                    $postfields[$k] = 'CURLFile(' . $v->getPostFilename() . ')';
                }
            }
            return $postfields;
        }
        if (is_string($postfields) && str_contains($postfields, '=') && !str_starts_with(ltrim($postfields), '{')) {
            parse_str($postfields, $parsed);
            if (is_array($parsed) && count($parsed) > 0) {
                return http_build_query(self::sanitizePostfields($parsed));
            }
        }
        return $postfields;
    }

    public function debug($var, $titre = null)
    {
        if (!$this->debug) {
            return;
        }
        if (php_sapi_name() !== 'cli') {
            echo '<p style="font-size:16px;font-weight:bold ;">[debug] ' . htmlspecialchars((string) ($titre ?? ''), ENT_QUOTES) . ' / ' . gettype($var) . '</p>' . PHP_EOL;
            echo '<pre style="color:white;background:black;font-family:monospace;font-size:8px;width:100%;max-height:500px;overflow:auto;">' . PHP_EOL;
            if (is_object($var) || gettype($var) == 'boolean') {
                echo htmlspecialchars(print_r($var, true), ENT_QUOTES);
            } elseif (is_array($var) || $this->isJson($var)) {
                echo htmlspecialchars((string) json_encode($var, JSON_PRETTY_PRINT), ENT_QUOTES);
            } else {
                echo htmlspecialchars((string) $var, ENT_QUOTES);
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
                $tble .= '<th><strong>' . htmlspecialchars(ucfirst((string) $key), ENT_QUOTES) . '</strong></th>';
                $tble .= '<td>';
                if (!is_array($value)) {
                    $tble .= nl2br(htmlspecialchars(stripslashes((string) $value), ENT_QUOTES));
                } else {
                    $tble .= '<pre>' . htmlspecialchars((string) json_encode($value, JSON_PRETTY_PRINT), ENT_QUOTES) . '</pre>';
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
     * @param	array<string, mixed>|null	$params	paramètres :
     *   - format (string) : si json, déclenchera une exception en cas de retour non json
     *   - POST (bool)
     *   - CUSTOMREQUEST (string) : PUT, DELETE…
     *   - POSTFIELDS (string|array)
     *   - USERPWD (string) : couple clientId:secret
     *   - header (array)
     *   - token (string) : token, récupéré le plus souvent avec gimme_token
     *   - url_type (string) : api|base (default : api)
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
        // Vérification TLS obligatoire : le secret du projet et les tokens transitent sur cette connexion.
        $curl_opts[CURLOPT_SSL_VERIFYPEER] = true;
        $curl_opts[CURLOPT_SSL_VERIFYHOST] = 2;
        if (defined('CURLOPT_PROTOCOLS_STR')) { // libcurl >= 7.85
            $curl_opts[CURLOPT_PROTOCOLS_STR] = 'http,https';
        }

        /** Le verbose affiche surtout des infos d'identification, en général peu utiles */
        //$curl_opts[CURLOPT_VERBOSE] = $this->debug;

        $curl_opts[CURLOPT_ENCODING] = 'UTF-8';
        $curl_opts[CURLOPT_RETURNTRANSFER] = true;
        $curl_opts[CURLOPT_FOLLOWLOCATION] = true;
        $curl_opts[CURLOPT_MAXREDIRS] = 5;
        // Jamais de redirection vers du http en clair (sauf environnement local)
        if (defined('CURLOPT_REDIR_PROTOCOLS_STR')) { // libcurl >= 7.85
            $curl_opts[CURLOPT_REDIR_PROTOCOLS_STR] = $this->env === 'local' ? 'http,https' : 'https';
        }
        $curl_opts[CURLOPT_CONNECTTIMEOUT] = $this->timeout;
        $curl_opts[CURLOPT_TIMEOUT] = $this->timeout;

        curl_setopt_array($ch, $curl_opts);
        
        $response = curl_exec($ch);

        if ($response === false) {
            $details = [
                'curl_error' => curl_error($ch),
                'curl_opts' => self::sanitizeCurlOpts($curl_opts)
            ];
            throw new ApidaeException('curl_response false', ApidaeException::NO_RESPONSE, $details);
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $return = [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'header' => substr($response, 0, $header_size),
            'request_size' => curl_getinfo($ch, CURLINFO_REQUEST_SIZE),
            'size_download' => curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
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
                'curl_opts' => self::sanitizeCurlOpts($curl_opts),
                'return' => $return
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
