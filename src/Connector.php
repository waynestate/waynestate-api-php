<?php namespace Waynestate\Api;

use Waynestate\Api\ConnectorException;

/**
 * Class Connector
 * @package Waynestate\Api
 */
class Connector
{
    public $apiKey;  // To obtain an API key: http://api.wayne.edu/
    public $parser = 'json'; // Use the included XML parser? Default: true.
    public $debug = false; // Switch for debug mode
    public $sessionid;
    public $cache_dir;

    private $endpoints = array(
        'production' => 'https://api.wayne.edu/v1/',
        'development' => 'https://www-dev.api.wayne.edu/v1/',
        'user' => '',
    );
    private $active_endpoint = 'production';
    private $force_production = false;

    public function __construct($apiKey = false, $mode = 'production')
    {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        }

        if ($mode == 'dev' || $mode == 'development') {
            $this->active_endpoint = 'development';
        }

        if (defined('API_ENDPOINT') && API_ENDPOINT != '') {
            $this->addEndpoint('user', API_ENDPOINT);
        }

        $envVariables = $this->getEnvVariables();

        if(!empty($envVariables['WSUAPI_ENDPOINT'])){
            $this->addEndPoint('user', $envVariables['WSUAPI_ENDPOINT']);
        }

        if (defined('API_CACHE_DIR') && API_CACHE_DIR != '') {
            $this->cache_dir = API_CACHE_DIR;
        }
    }

    /**
     * Get the Environment Variables
     *
     * If the Laravel Helper function env is available then use it, otherwise getenv
     *
     * @return array
     */
    private function getEnvVariables()
    {
        if(function_exists('env')){
            return [
                'WSUAPI_ENDPOINT' => env('WSUAPI_ENDPOINT'),
            ];
        }

        return [
            'WSUAPI_ENDPOINT' => getenv('WSUAPI_ENDPOINT'),
        ];
    }

    /**
     * setSession
     *
     * @param session => string
     * @return bool
     */
    public function setSession($sessionid)
    {
        return ($this->sessionid = $sessionid);
    }

    /**
     * buildArguments
     *
     * @param $p (array)
     * @return string
     */
    protected function buildArguments($p)
    {
        $args = '';
        foreach ($p as $key => $value) {
            // Don't include these
            if ($key == 'method' || $key == 'submit' || $key == 'MAX_FILE_SIZE') {
                continue;
            }

            $args .= $key . '=' . urlencode($value) . '&';
        }

        // Chop off last ampersand
        return substr($args, 0, -1);
    }

    /**
     * Ensure the endpoint is SSL
     *
     * @param $url
     * @return string
     */
    protected function ensureSslEndpoint($url)
    {
        // If the endpoint isn't on SSL
        if (substr($url, 0, 5) != 'https') {
            // Force an SSL endpoint
            $endpoint = parse_url($url);
            $url = 'https://' . $endpoint['host'] . $endpoint['path'];
        }

        // SSL already enabled
        return $url;
    }

    public function sendRequest($method=null, $args=null, $postmethod='get', $tryagain=true, $buildquery=true)
    {
        try {
            $result = $this->Request($method, $args, $postmethod, '', $buildquery);

            if ($tryagain && is_null($result)) {
                $result = $this->Request($method, $args, $postmethod, false, $buildquery);
            } elseif (is_null($result)) {
                throw new ConnectorException("No response", $method, 8888, 'n/a');
            }

            if (is_array($result) && isset($result['error']) && $result['error']) {
                throw new ConnectorException($result['error']['message'], $method, $result['error']['code'], $result['error']['field']);
            }
        } catch (ConnectorException $e) {
        }

        if (isset($result['response'])) {
            return $result['response'];
        }

        return $result;
    }

    /**
     * @param null $method
     * @param null $args
     * @param string $postmethod
     * @param bool $tryagain
     * @param bool $buildquery
     * @return mixed|string
     */
    private function Request($method = null, $args = null, $postmethod = 'get', $tryagain = false, $buildquery = true)
    {
        // Get the endpoint for this call
        $endpoint_url = $this->getEndpoint();

        // Check for a cached version of the call results
        if (strtolower($postmethod) == 'get' && array_key_exists('ttl', (array)$args)) {
            // Create a standard filename
            $filename = str_replace('/', '.', strtolower($method)) . '-' . md5($endpoint_url . $this->apiKey . $this->sessionid . serialize($args));

            // Check to see if there is a cache
            $cache_serialized = $this->Cache('get', $filename, '', $args['ttl']);

            // If a cached version exists
            if ($cache_serialized != '') {
                // Use the cached results
                $response = unserialize($cache_serialized);

                // Debug?
                if ($this->debug) {
                    echo '<pre>';
                    print_r('From Cache: ' . $filename . "\n");
                    print_r($response);
                    echo '</pre>';
                }

                return $response;
            }
        }

        // Convert array to string
        $reqURL = $endpoint_url . '?api_key=' . $this->apiKey . '&return=json&method=' . $method;

        // If there is a session, pass the info along
        if ($this->sessionid != '') {
            $args['sessionid'] = (string)urlencode($this->sessionid);
        }

        if ($postmethod == 'get') {
            if (is_array($args)) {
                $getArgs = http_build_query($args);
            } else {
                $getArgs = $args;
            }

            $reqURL .= '&' . $getArgs;
        }

        if ($postmethod == 'post' && !empty($args['sessionid'])) {
            $reqURL .= '&sessionid=' . $args['sessionid'];
        }

        $curl_handle = curl_init();

        curl_setopt($curl_handle, CURLOPT_URL, $reqURL);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_HEADER, 0);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl_handle, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curl_handle, CURLOPT_REFERER, 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);

        // Set the custom HTTP Headers
        $http_header = array();
        $http_header[] = 'X-Api-Key: ' . $this->apiKey;
        $http_header[] = 'X-Return: json';
        if (isset($args['sessionid'])) {
            $http_header[] = 'X-Sessionid: ' . $args['sessionid'];
        }

        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $http_header);

        if ($postmethod == 'post') {
            curl_setopt($curl_handle, CURLOPT_POST, 1);
            if ($method == 'cms.file.upload' || $buildquery == false) {
                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $args);
            } else {
                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, http_build_query($args));
            }
        }

        $response = curl_exec($curl_handle);

        if (!$response) {
            $response = curl_error($curl_handle);
        }

        curl_close($curl_handle);

        // Debug?
        if ($this->debug) {
            echo '<pre>';
            print_r($response);
            echo '</pre>';
        }

        // Return array
        if ($this->parser == 'json') {
            $response = json_decode($response, true);
        }

        // If successful return and TTL is set, cache it
        if (array_key_exists('ttl', (array)$args) // First check if trying to cache
            && strtolower($postmethod) == 'get' // Only cache GET requests
            && is_array($response) // Ensure the response is a structure
            && array_key_exists('response', $response) // Ensure there is a response in the response
            && !array_key_exists('error', $response['response']) // Ensure there wasn't an error (params, etc)
        ) {
            // Debug?
            if ($this->debug) {
                echo '<pre>';
                print_r('Saving Cache: ' . $filename);
                echo '</pre>';
            }

            // Save the results
            $this->Cache('set', $filename, $response);
        }

        return $response;
    }

    /**
     * Get or Set Cache
     *
     * @param string $action (ex. "get" or "set")
     * @param string $filename
     * @param mixed $data
     * @param string $max_age
     * @return string
     */
    protected function Cache($action, $filename, $data = '', $max_age = '')
    {
        if (!is_dir($this->cache_dir)) {
            return '';
        }

        // Set the full path
        $cache_file = $this->cache_dir . $filename;

        $cache = '';

        if ($action == 'get') {
            // Clear the file stats
            clearstatcache();

            if (is_file($cache_file) && $max_age != '') {
                // Make sure $max_age is negitive
                if (is_string($max_age) && substr($max_age, 0, 1) != '-') {
                    $max_age = '-' . $max_age;
                }

                // Make sure $max_age is an INT
                if (!is_int($max_age)) {
                    $max_age = strtotime($max_age);
                }

                // Test to see if the file is still fresh enough
                if (filemtime($cache_file) >= date($max_age)) {
                    $cache = file_get_contents($cache_file);
                }
            }
        } else {
            if (is_writable($this->cache_dir)) {
                // Serialize the Fields
                $store = serialize($data);

                //Open and Write the File
                $fp = fopen($cache_file, "w");
                fwrite($fp, $store);
                fclose($fp);
                chmod($cache_file, 0770);

                $cache = strlen($store);
            }
        }

        return $cache;
    }

    /**
     * Use the production endpoint for the next few calls
     *
     */
    public function nextRequestProduction()
    {
        $this->force_production = true;
    }

    /**
     * Get the current active endpoint
     *
     * @return mixed
     */
    protected function getEndpoint()
    {
        // If force production is in effect
        if ($this->force_production) {
            $this->force_production = false;
            return $this->endpoints['production'];
        }

        // Return the active endpoint
        return $this->endpoints[$this->active_endpoint];
    }

    /**
     * Add a user defined endpoint
     *
     * @param $name
     * @param $url
     * @return bool
     */
    protected function addEndpoint($name, $url)
    {
        // Add or modify the endpoint URL
        $this->endpoints[$name] = $this->ensureSslEndpoint($url);
        $this->active_endpoint = $name;

        // Return boolean if endpoint has been added successfully
        return array_key_exists($name, $this->endpoints);
    }

    /**
     * Backwards compatibility for public access to the $cmsREST property
     * Example: `$api->cmsREST = 'http...`
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if ($name == 'cmsREST') {
            $this->addEndpoint('user', $value);
        }
    }

    /**
     * Backwards compatibility to access the $cmsREST property
     * Example: `$api->cmsREST;`
     *
     * @param $name
     * @return mixed
     */
    public function __get($name) {
        if ($name == 'cmsREST') {
            return $this->endpoints['user'];
        }
    }
}
