<?php
/**
 * Class for handling the communication to Open Badge Factory API.
 *
 * @copyright 2013-2015, Discendum Oy
 * @license MIT
 */
class ObfClient
{
    /**
     *
     * @var $client Static client
     */
    private static $client = null;
    /**
     *
     * @var curl|null Transport. Curl.
     */
    private $transport = null;

    /**
     *
     * @var int HTTP code for handling errors, such as deleted badges.
     */
    private $httpCode = null;

    /**
     *
     * @var array Response headers
     */
    private $headers = null;
    /**
     *
     * @var string Last error message.
     */
    private $error = '';
    /**
     *
     * @var array Raw response.
     */
    private $rawResponse = null;
    /**
     *
     * @var bool Store raw response?
     */
    private $enableRawResponse = false;

    /**
     * @var array Configuration
     */
    protected $config = array();

    /**
     * Constructor
     * @param array $config
     */
    protected function __construct($config)
    {
        $this->config = $config;
    }
    /**
     * Returns the id of the client stored in Moodle's config.
     *
     * @return string The client id.
     */
    public function get_client_id()
    {
        return $this->get_config('obf_client_id');
    }

    /**
     * Returns the url of the OBF API.
     *
     * @return string The url.
     */
    public function get_api_url()
    {
        return $this->get_config('obf_api_url');
    }

    /**
     * Returns the client instance.
     *
     * @param curl|null $transport
     * @return ObfClient The client.
     */
    public static function get_instance($transport = null, $config = null)
    {
        if (is_null(self::$client)) {
            self::$client = new self($config);

            if (!is_null($transport)) {
                self::$client->setTransport($transport);
            }
        }

        return self::$client;
    }

    /**
     * Set object transport.
     *
     * @param curl $transport
     */
    public function set_transport($transport)
    {
        $this->transport = $transport;
    }

    /**
     * Checks that the OBF client id is stored to plugin settings.
     *
     * @throws \Exception If the client id is missing.
     */
    public function require_client_id()
    {
        $clientid = $this->get_client_id();

        if (empty($clientid)) {
            throw new \Exception($this->get_string('apierror0'), 0);
        }
    }

    /**
     * Tests the connection to OBF API.
     *
     * @return int Returns the error code on failure and -1 on success.
     */
    public function test_connection()
    {
        try {
            $this->require_client_id();

            // TODO: does ping check certificate validity?
            $this->api_request('/ping/' . $this->get_client_id());
            return - 1;
        } catch (\Exception $exc) {
            return $exc->getCode();
        }
    }

    /**
     * Deauthenticates the plugin.
     */
    public function deauthenticate()
    {
        @unlink($this->get_cert_filename());
        @unlink($this->get_pkey_filename());

        $this->set_config('obf_client_id', null);
    }

    /**
     * Tries to authenticate the plugin against OBF API.
     *
     * @param string $signature
     *            The request token from OBF.
     * @return boolean Returns true on success.
     * @throws \Exception If something goes wrong.
     */
    public function authenticate($signature, $apiurl)
    {
        $pkidir = realpath($this->get_pki_dir());

        // Certificate directory not writable.
        if (!is_writable($pkidir)) {
            throw new \Exception($this->get_string('pkidirnotwritable', $pkidir));
        }

        $signature = trim($signature);
        $token = base64_decode($signature);
        $client = $this->get_transport();
        
        $guzzleOptions = $this->get_guzzle_options();
        unset($guzzleOptions['cert']);
        unset($guzzleOptions['ssl_key']);
        $curlopts = $this->get_curl_options();
        // We don't need these now, we haven't authenticated yet.
        unset($curlopts ['SSLCERT']);
        unset($curlopts ['SSLKEY']);

        //$apiurl = $this->url_checker($api_url);

        $url = $apiurl . '/client/OBF.rsa.pub';

        if (!$this->is_guzzle_transport()) {
            $pubkey = $client->get($url, array(), $curlopts);
        } else {
            $request = $client->get($url);
            $pubkey = $request->getBody()->getContents();
        }

        $error = '';
        if (!$this->is_guzzle_transport()) {
            $error = $curl->error;
        }
        // CURL-request failed.
        if ($pubkey === false) {
            throw new \Exception($this->get_string('pubkeyrequestfailed', 'local_obf') . ': ' . $error);
        }

        if (!($client instanceof \GuzzleHttp\Client)) {
            $httpcode = $client->get_info()['http_code'];
        } else {
            $httpcode = $request->getStatusCode();
        }
        // Server gave us an error.
        if ($httpcode !== 200) {
            throw new \Exception($this->get_string('pubkeyrequestfailed', 'local_obf') . ': ' .
                $this->get_string('apierror' . $httpcode));
        }

        $decrypted = '';

        // Get the public key...
        $key = openssl_pkey_get_public($pubkey);

        // ... That didn't go too well.
        if ($key === false) {
            throw new \Exception($this->get_string('pubkeyextractionfailed') . ': ' . openssl_error_string());
        }
        
        // Couldn't decrypt data with provided key.
        if (openssl_public_decrypt($token, $decrypted, $key, OPENSSL_PKCS1_PADDING) === false) {
            throw new \Exception($this->get_string('tokendecryptionfailed') . ': ' . openssl_error_string());
        }

        $json = json_decode($decrypted);

        // Yay, we have the client-id. Let's store it somewhere.
        $this->set_config('obf_client_id', $json->id);

        // Create a new private key.
        $config = array(
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA
        );
        $privkey = openssl_pkey_new($config);

        // Export the new private key to a file for later use.
        openssl_pkey_export_to_file($privkey, $this->get_pkey_filename());

        $csrout = '';
        $dn = array (
                'commonName' => $json->id
        );

        // Create a new CSR with the private key we just created.
        $csr = openssl_csr_new($dn, $privkey);

        // Export the CSR into string.
        if (openssl_csr_export($csr, $csrout) === false) {
            throw new \Exception($this->get_string('csrexportfailed'));
        }

        $postdata = json_encode(array(
                'signature' => $signature,
                'request' => $csrout
        ));
        if (!$this->is_guzzle_transport()) {
            $cert = $client->post($apiurl . '/client/' . $json->id . '/sign_request', $postdata, $curlopts);
        } else {
            $request = $client->post(
                $apiurl . '/client/' . $json->id . '/sign_request',
                array('body' => $postdata)
            );
            $cert = $request->getBody()->getContents();
        }

        // Fetching certificate failed.
        if ($cert === false) {
            if (!$this->is_guzzle_transport()) {
                $error = $curl->error;
            } else {
                $error = $request->getReasonPhrase();
            }
            throw new \Exception($this->get_string('certrequestfailed') . ': ' . $error);
        }

        if (!$this->is_guzzle_transport()) {
            $httpcode = $curl->get_info()['http_code'];
        } else {
            $httpcode = $request->getStatusCode();
        }

        // Server gave us an error.
        if ($httpcode !== 200) {
            $jsonresp = json_decode($cert);
            $extrainfo = is_null($jsonresp)? $this->get_string('apierror' . $httpcode, 'local_obf'): $jsonresp->error;

            throw new \Exception($this->get_string('certrequestfailed'). ': ' . $extrainfo);
        }

        // Everything's ok, store the certificate into a file for later use.
        file_put_contents($this->get_cert_filename(), $cert);

        return true;
    }

    /**
     * Returns the expiration date of the OBF certificate as a unix timestamp.
     *
     * @return mixed The expiration date or false if the certificate is missing.
     */
    public function get_certificate_expiration_date()
    {
        $certfile = $this->get_cert_filename();

        if (!file_exists($certfile)) {
            return false;
        }

        $cert = file_get_contents($certfile);
        $ssl = openssl_x509_parse($cert);

        return $ssl ['validTo_time_t'];
    }

    /**
     * Get absolute filename of certificate key-file.
     *
     * @return string
     */
    public function get_pkey_filename()
    {
        return $this->get_pki_dir() .
            $this->get_config('obf_client_id') .
            $this->get_config('obf_pki_keyfile_suffix');
    }
    /**
     * Get absolute filename of certificate pem-file.
     *
     * @return string
     */
    public function get_cert_filename()
    {
        return $this->get_pki_dir() .
            $this->get_config('obf_client_id') .
            $this->get_config('obf_pki_certfile_suffix');
    }
    /**
     * Get absolute path of certificate directory.
     *
     * @return string
     */
    public function get_pki_dir()
    {
        $pkidir = $this->get_config('certdir');
        if (empty($pkidir)) {
            $pkidir = $this->get_config('obf_cert_dir');
        }
        if (substr($pkidir, -1, 1) !== '/') {
            return $pkidir.'/';
        }
        return $pkidir;
    }

    /**
     * Get a single badge from the API.
     *
     * @param string $badgeId
     * @throws \Exception If the request fails
     * @return array The badge data.
     */
    public function get_badge($badgeId)
    {
        $this->require_client_id();
        return $this->api_request('/badge/' . $this->get_client_id() . '/' . $badgeId);
    }

    /**
     * Get issuer data from the API.
     *
     * @throws \Exception If the request fails
     * @return array The issuer data.
     */
    public function get_issuer()
    {
        $this->require_client_id();
        return $this->api_request('/client/' . $this->get_client_id());
    }

    /**
     * Get badge categories from the API.
     *
     * @return array The category data.
     */
    public function get_categories()
    {
        $this->require_client_id();
        return $this->api_request('/badge/' . $this->get_client_id() . '/_/categorylist');
    }

    /**
     * Get all the badges from the API.
     *
     * @param string[] $categories
     *            Filter badges by these categories.
     * @return array The badges data.
     */
    public function get_badges(array $categories = array())
    {
        $params = array (
                'draft' => 0
        );

        $this->require_client_id();

        if (count($categories)> 0) {
            $params ['category'] = implode('|', $categories);
        }

        return $this->api_request('/badge/' . $this->get_client_id(), 'get', $params, function ($output) {
            return '[' . implode(',', array_filter(explode("\n", $output))). ']';
        });
    }

    /**
     * Get badge assertions from the API.
     *
     * @param string $badgeId
     *            The id of the badge.
     * @param string $email
     *            The email address of the recipient.
     * @return array The event data.
     */
    public function get_assertions($badgeId = null, $email = null, $extra_params = array())
    {
        $params = array (
                'api_consumer_id' => $this->get_api_consumer_id()
        );

        $this->require_client_id();

        if (!is_null($badgeId)) {
            $params ['badge_id'] = $badgeId;
        }

        if (!is_null($email)) {
            $params ['email'] = $email;
        }
        $params = array_merge($extra_params, $params);

        // When getting assertions via OBF API the returned JSON isn't valid.
        // Let's use a closure that converts the returned string into valid JSON
        // before calling json_decode in $this->curl.
        return $this->api_request('/event/' . $this->get_client_id(), 'get', $params, function ($output) {
            return '[' . implode(',', array_filter(explode("\n", $output))). ']';
        });
    }

    /**
     * Get single assertion from the API.
     *
     * @param string $eventId
     *            The id of the event.
     * @return array The event data.
     */
    public function get_event($eventId)
    {
        $this->require_client_id();
        return $this->api_request('/event/' . $this->get_client_id() . '/' . $eventId, 'get');
    }

    /**
     * Get revoked for assertion from the API.
     *
     * @param string $eventId
     *            The id of the event.
     * @return array The revoked data.
     */
    public function get_revoked($eventId)
    {
        $this->require_client_id();
        return $this->api_request('/event/' . $this->get_client_id() . '/' . $eventId . '/revoked', 'get');
    }

    /**
     * Deletes all client badges.
     * Use with caution.
     */
    public function delete_badges()
    {
        $this->require_client_id();
        $this->api_request('/badge/' . $this->get_client_id(), 'delete');
    }

    /**
     * Exports a badge to Open Badge Factory
     *
     * @param mixed $badge The badge.
     * @param mixed $update False when creating, String badge id, when updating.
     * @return string Badge id
     */
    public function export_badge($badge, $update = false)
    {
        $this->require_client_id();

        if (is_array($badge)) {
        	$params = $badge;
        } else if (method_exists($badge, 'getName')) {
        	$params = array (
        			'name' => $badge->getName(),
        			'description' => $badge->getDescription(),
        			'image' => $badge->getImage(),
        			'css' => $badge->getCriteria()->getCss(),
        			'criteria_html' => $badge->getCriteria()->getHtml(),
        			'email_subject' => $badge->getEmail()->getSubject(),
        			'email_body' => $badge->getEmail()->getBody(),
        			'email_footer' => $badge->getEmail()->getFooter(),
        			'expires' => '',
        			'tags' => array(),
        			'draft' => $badge->isDraft()
        	);        	
        } else {
        	throw new \Exception('Error: export_badge expected array or Badge object.');
        }
        
        if (false === $update) {
            $this->api_request('/badge/' . $this->get_client_id(), 'post', $params);
        } else {
            $this->api_request('/badge/' . $this->get_client_id() . '/' . $update, 'put', $params);
        }
        return $this->get_id_from_headers('badge');
    }

    /**
     * Issues a badge.
     *
     * @param mixed $badge
     *            The badge to be issued.
     * @param string[] $recipients
     *            The recipient list, array of emails.
     * @param int $issuedOn
     *            The issuance date as a Unix timestamp
     * @param string $emailSubject
     *            The subject of the email.
     * @param string $emailBody
     *            The email body.
     * @param string $emailFooter
     *            The footer of the email.
     */
    public function issue_badge(
        $badge,
        $recipients,
        $issuedOn = null,
        $emailTemplate = null,
        $logEntry = null
    ) {
        $this->require_client_id();
        if (empty($issuedOn)) {
            $issuedOn = time();
        }

        $params = array (
                'recipient' => $recipients,
                'issued_on' => $issuedOn,
                'api_consumer_id' => $this->get_api_consumer_id(),
                'show_report' => 1
        );
        if (!empty($logEntry)) {
            $params['log_entry'] = $logEntry;
        }

        if (!empty($emailTemplate) && (isset($emailTemplate->email_subject) && !empty($emailTemplate->email_subject))) {
            $params['email_subject'] = $emailTemplate->email_subject;
            $params['email_body'] = $emailTemplate->email_body;
            $params['email_link_text'] = $emailTemplate->email_link_text;
            $params['email_footer'] = $emailTemplate->email_footer;
        }

        $badgeId = null;
        if (method_exists($badge, 'getId')) {
        	$badgeId = $badge->getId();
        }
        if (method_exists($badge, 'getExpires') && !is_null($badge->getExpires()) && $badge->getExpires() > \DateTime::createFromFormat('U', 0)) {
            $params ['expires'] = (int)$badge->getExpires()->format('U');
        } else if (is_array($badge)) {
        	$params = array_merge($params, $badge);
        	$badgeId = $badge['badge_id'];
        }


        $this->api_request('/badge/' . $this->get_client_id() . '/' . $badgeId, 'post', $params);

        return $this->get_id_from_headers('event');
    }
    
    public function get_id_from_headers($type)
    {
        $headers = $this->get_headers();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if ($key == 'Location') {
                    if ($match = preg_match('/'.$type.'\/[\w]+\/(.*)$/i', $value[0], $matches)) {
                        $eventid = trim($matches[$match]);
                        if (!empty($eventid)) {
                            return $eventid;
                        }
                    }
                }
            }
        }
    }
    /**
     * Revoke an issued event.
     *
     * @param string $eventId
     * @param string[] $emails
     *            Array of emails to revoke the event for.
     */
    public function revoke_event($eventId, $emails)
    {
        $this->require_client_id();
        $this->api_request(
            '/event/' . $this->get_client_id() . '/' . $eventId . '/?email=' . implode('|', $emails),
            'delete'
        );
    }

    /**
     * A wrapper for obf_client::request, prefixing $path with the API url.
     *
     * @param string $path
     * @param string $method
     *            Supported methods are: 'get', 'post' and 'delete'
     * @param array $params
     * @param Closure $preformatter
     * @return mixed Response from request.
     * @see self::request
     */
    protected function api_request($path, $method = 'get', array $params = array(), \Closure $preformatter = null)
    {
        return $this->request($this->get_api_url() . $path, $method, $params, $preformatter);
    }

    /**
     * Makes a CURL-request to OBF API.
     *
     * @param string $url
     *            The API path.
     * @param string $method
     *            The HTTP method.
     * @param array $params
     *            The params of the request.
     * @param Closure $preformatter
     *            In some cases the returned string isn't
     *            valid JSON. In those situations one has to manually preformat the
     *            returned data before decoding the JSON.
     * @return array The json-decoded response.
     * @throws \Exception In case something goes wrong.
     */
    public function request($url, $method = 'get', array $params = array(), \Closure $preformatter = null)
    {
        $client = $this->get_transport();
        if (!$this->is_guzzle_transport()) {
            $options = $this->get_curl_options();

            if ($method == 'get') {
                $output = $client->get($url, $params, $options);
            } elseif ($method == 'delete') {
                $output = $client->delete($url, $params, $options);
            } else {
                $output = $client->post($url, json_encode($params), $options);
            }

            $info = $client->get_info();

            if ($this->enableRawResponse) {
                $this->rawResponse = $client->get_raw_response();
            }
            $this->httpCode = $info['http_code'];
            $this->error = '';
        } else {
            $guzzle_options = $this->get_guzzle_options();
            if ($method == 'get') {
                $request = $client->get($url, array_merge($guzzle_options, array('query' => $params)));
            } elseif ($method == 'delete') {
                $request = $client->delete($url, array_merge($guzzle_options, array('query' => $params)));
            } elseif ($method == 'put') {
                $request = $client->put(
                    $url,
                    array_merge($guzzle_options, array(
                        'body' => json_encode($params)
                    ))
                );
            } else {
                $request = $client->post(
                    $url,
                    array_merge($guzzle_options, array(
                        'body' => json_encode($params)
                    ))
                );
            }
            $output = $request->getBody()->getContents();
            $this->httpCode = $request->getStatusCode();
            $this->error = $request->getReasonPhrase();
            $this->headers = $request->getHeaders();
        }

        if ($output !== false) {
            if (!is_null($preformatter)) {
                $output = $preformatter($output);
            }

            $response = json_decode($output, true);
        }


        // Codes 2xx should be ok.
        if (is_numeric($this->httpCode)&& ($this->httpCode < 200 || $this->httpCode >= 300)) {
            $this->error = isset($response ['error'])? $response ['error'] : '';
            throw new \Exception($this->get_string('apierror' . $this->httpCode, $this->error), $this->httpCode);
        }

        return $response;
    }
    /**
     * Get HTTP error code of the last request.
     *
     * @return integer HTTP code, 200-299 should be good, 404 means item was not found.
     */
    public function get_http_code()
    {
        return $this->httpCode;
    }
    /**
     * Get error message of the last request.
     *
     * @return string Last error message or an empty string if last request was a success.
     */
    public function get_error()
    {
        return $this->error;
    }

    /**
     * Get raw response.
     *
     * @return string[] Raw response.
     */
    public function get_raw_response()
    {
        return $this->rawResponse;
    }

    /**
     * Get headers
     *
     * @return array headers.
     */
    public function get_headers()
    {
        return $this->headers;
    }
    /**
     * Enable/disable storing raw response.
     *
     * @param bool $enable
     * @return ObfClient This object.
     */
    public function set_enable_raw_response($enable)
    {
        $this->enableRawResponse = $enable;
        $this->rawResponse = null;
        return $this;
    }

    /**
     * Returns the default CURL-settings for a request.
     *
     * @return array
     */
    public function get_curl_options()
    {
        return array (
                'RETURNTRANSFER' => true,
                'FOLLOWLOCATION' => false,
                'SSL_VERIFYHOST' => 2,
                'SSL_VERIFYPEER' => 1,
                'SSLCERT' => $this->get_cert_filename(),
                'SSLKEY' => $this->get_pkey_filename()
        );
    }

    /**
     * Returns the default CURL-settings for a request.
     *
     * @return array
     */
    public function get_guzzle_options()
    {
        return array (
                'allow_redirects' => false,
                'verify' => true,
                'cert' => $this->get_cert_filename(),
                'ssl_key' => $this->get_pkey_filename()
        );
    }
    /**
     * Returns a new transport.
     *
     * @return \GuzzleHttp\Client
     */
    protected function get_transport()
    {
        if (!is_null($this->transport)) {
            return $this->transport;
        }

        // Use Guzzle if no transport is defined.
        $guzzleOptions = $this->get_guzzle_options();
        if (!file_exists($this->get_pkey_filename()) || !file_exists($this->get_cert_filename())) {
            unset($guzzleOptions['cert']);
            unset($guzzleOptions['ssl_key']);
        }
        return new \GuzzleHttp\Client($guzzleOptions);
    }

    protected function is_guzzle_transport()
    {
        return ($this->get_transport() instanceof \GuzzleHttp\Client);
    }
    /**
     * Get configuration value.
     */
    protected function get_config($name)
    {
        if (array_key_exists($name, $this->config)) {
            return $this->config[$name];
        }
        return null;
    }

    public function set_config($key, $value)
    {
        $this->config[$key] = $value;
        return $this;
    }
    /**
     * Get string
     */
    protected function get_string($strName, $param = '')
    {
        return $strName . $param;
    }
    protected function get_api_consumer_id()
    {
        $consumer_id = $this->get_config('api_consumer_id');
        if (!empty($consumer_id))
            return $this->get_config('api_consumer_id');
        return 'obf-wp';
    }
}
