<?php
/**
 * PHP Curl Library
 * @author Sujeet <sujeetkv90@gmail.com>
 * @link https://github.com/sujeet-kumar/php-curl-lib
 */

class Curl
{
	protected $url = '';
	protected $port = 80;
	protected $request = NULL;
	protected $response = '';
	
	protected $headers = array();
	protected $options = array();
	
	protected $error_code = '';
	protected $error_msg = '';
	protected $info = array();
	
	protected $timeout = 28;
	protected $strict_mode = false;
	protected $max_redirects = 10;
	
	protected $http_version = NULL;
	protected $http_versions = array(
		'1.0' => CURL_HTTP_VERSION_1_0,
		'1.1' => CURL_HTTP_VERSION_1_1
	);
	
	/**
	 * Initialize class
	 * @param array $config
	 */
	public function __construct($config = array()){
		if(! $this->_isEnabled()){
			throw new CurlException('cURL extension has to be loaded and enabled to use '.get_class($this).' class.');
		}
		
		$this->_initialize($config);
	}
	
	/**
	 * Magic call for available custom methods (get|post|put|patch|delete|head|options)
	 */
	public function __call($method, $arguments){
		empty($arguments) and $arguments = array();
		array_unshift($arguments, $method);
		return call_user_func_array(array($this, 'sendRequest'), $arguments);
	}
	
	/**
	 * Prepare and execute request
	 * @param string $method
	 * @param string $url
	 * @param mixed $data
	 * @param array $options
	 */
	public function sendRequest($method, $url, $data = array(), $options = array()){
		$this->setUrl($url);
		$post_data = false;
		
		switch(strtolower($method)){
			case 'get':
				$this->options[CURLOPT_HTTPGET] = true;
			break;
			
			case 'post':
				$this->options[CURLOPT_POST] = true;
				$post_data = true;
			break;
			
			case 'put':
			case 'patch':
			case 'delete':
				$this->httpMethod($method);
				$post_data = true;
			break;
			
			case 'head':
				$this->httpMethod('head');
				$this->options[CURLOPT_HEADER] = true;
				$this->options[CURLOPT_NOBODY] = true;
			break;
			
			case 'options':
				$this->httpMethod('options');
				$this->options[CURLOPT_HEADER] = true;
			break;
			
			default:
				throw new CurlException('Method \''.$method.'\' not supported by '.get_class($this).' class.');
		}
		
		if(!empty($data)){
			if($post_data){
				$this->options[CURLOPT_POSTFIELDS] = $data;
			}else{
				is_array($data) and $data = http_build_query($data, NULL, '&');
				$this->url .= ((parse_url($this->url, PHP_URL_QUERY)) ? '&' : '?') . $data;
			}
		}
		
		$this->setOptions($options);
		
		return $this->execute();
	}
	
	/**
	 * Get response headers for given URL
	 * @param string $url
	 * @param mixed $data
	 * @param array $options
	 */
	public function getHeaders($url, $data = array(), $options = array()){
		$headers = array();
		if($res = $this->head($url, $data = array(), $options = array())){
			$headers = $this->parseHeader($res);
		}
		return $headers;
	}
	
	/**
	 * Set curl option for request
	 * @param int|string $option
	 * @param mixed $value
	 * @param string $prefix
	 */
	public function setOption($option, $value, $prefix = 'opt'){
		if(is_string($option) && !is_numeric($option)){
			$option = constant('CURL' . strtoupper($prefix) . '_' . strtoupper($option));
		}
		$this->options[$option] = $value;
		return $this;
	}
	
	/**
	 * Set curl options for request
	 * @param array $options
	 */
	public function setOptions($options = array()){
		if(! is_array($options)){
			throw new CurlException('Invalid argument passed to \'setOptions\' method of '.get_class($this).' class.');
		}
		foreach($options as $option => $value){
			$this->setOption($option, $value);
		}
		return $this;
	}
	
	/**
	 * Set cookies for request
	 * @param array $data
	 */
	public function setCookies($data = array()){
		if(is_array($data)) $data = http_build_query($data, NULL, '; ');
		$this->options[CURLOPT_COOKIE] = $data;
		return $this;
	}
	
	/**
	 * Set http method for request
	 * @param string $method
	 */
	public function httpMethod($method){
		$this->options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
		$this->headers[] = 'X-HTTP-Method-Override: '.strtoupper($method);
		return $this;
	}
	
	/**
	 * Set headers for request
	 * @param array|string $header
	 * @param string $content
	 */
	public function httpHeaders($header, $content = NULL){
		if(!is_array($header)) $header = array($header => $content);
		foreach($header as $header_name => $content){
			$this->headers[] = ($content === NULL) ? $header_name : $header_name . ': ' . $content;
		}
		return $this;
	}
	
	/**
	 * Provide auth credentials for request
	 * @param string $username
	 * @param string $password
	 * @param bool $http_auth
	 * @param string $auth_type (eg. any, basic, digest)
	 */
	public function httpLogin($username, $password, $http_auth = false, $auth_type = 'any'){
		if($http_auth) $this->options[CURLOPT_HTTPAUTH] = constant('CURLAUTH_' . strtoupper($auth_type));
		$this->options[CURLOPT_USERPWD] = $username . ':' . $password;
		return $this;
	}
	
	/**
	 * Specify proxy tunnel for request
	 * @param string $host
	 * @param int $port
	 * @param bool $use_connect
	 */
	public function proxy($host, $port = 80, $use_connect = false){
		$use_connect and $this->options[CURLOPT_HTTPPROXYTUNNEL] = true;
		$this->options[CURLOPT_PROXY] = $host;
		$this->options[CURLOPT_PROXYPORT] = $port;
		return $this;
	}
	
	/**
	 * Provide auth credentials for proxy tunnel
	 * @param string $username
	 * @param string $password
	 */
	public function proxyLogin($username, $password){
		$this->options[CURLOPT_PROXYUSERPWD] = $username . ':' . $password;
		return $this;
	}
	
	/**
	 * Enable secure request
	 * @param bool $verify_peer
	 * @param string $path_to_cert
	 * @param int|bool $verify_host
	 */
	public function secure($verify_peer = true, $path_to_cert = '', $verify_host = 2){
		if($verify_peer){
			$this->options[CURLOPT_SSL_VERIFYPEER] = true;
			$this->options[CURLOPT_SSL_VERIFYHOST] = $verify_host;
			empty($path_to_cert) or $this->options[CURLOPT_CAINFO] = realpath($path_to_cert);
		}else{
			$this->options[CURLOPT_SSL_VERIFYPEER] = false;
			is_bool($verify_host) and $this->options[CURLOPT_SSL_VERIFYHOST] = $verify_host;
		}
		return $this;
	}
	
	/**
	 * Set url for request
	 * @param string $url
	 * @param int $port
	 */
	public function setUrl($url, $port = NULL){
		if(!empty($url)){
			if($url_parts = parse_url($url)){
				$this->url = $url_parts['scheme'] . '://' . $url_parts['host'];
				
				empty($url_parts['path']) or $this->url .= $url_parts['path'];
				empty($url_parts['query']) or $this->url .= '?' . $url_parts['query'];
				
				(!empty($port) and $this->setPort($port)) or (empty($url_parts['port']) or $this->setPort($url_parts['port']));
				
				if(!empty($url_parts['user']) and isset($url_parts['pass'])){
					$this->httpLogin($url_parts['user'],$url_parts['pass']);
				}
			}else{
				$this->error_msg = 'Invalid URL format.';
			}
		}
		return $this;
	}
	
	/**
	 * Set port for request
	 * @param int $port
	 */
	public function setPort($port){
		if($port != 80 and !empty($port)){
			$this->options[CURLOPT_PORT] = intval($port);
		}
		$this->port = $port;
		return $this;
	}
	
	/**
	 * Execute request
	 * @param string $url
	 */
	public function execute($url = ''){
		$this->setUrl($url);
		
		if(empty($this->url)){
			$this->clear();
			empty($this->error_msg) and $this->error_msg = 'URL not provided.';
			return false;
		}
		
		isset($this->options[CURLOPT_RETURNTRANSFER]) or $this->options[CURLOPT_RETURNTRANSFER] = true;
		isset($this->options[CURLOPT_TIMEOUT]) or $this->options[CURLOPT_TIMEOUT] = $this->timeout;
		isset($this->options[CURLOPT_FAILONERROR]) or $this->options[CURLOPT_FAILONERROR] = $this->strict_mode;
		
		if( !ini_get('safe_mode') && !ini_get('open_basedir') && !isset($this->options[CURLOPT_FOLLOWLOCATION])){
			$this->options[CURLOPT_FOLLOWLOCATION] = true;
		}
		
		if(!empty($this->options[CURLOPT_FOLLOWLOCATION]) && !isset($this->options[CURLOPT_MAXREDIRS])){
			$this->options[CURLOPT_MAXREDIRS] = $this->max_redirects;
		}
		
		(isset($this->options[CURLOPT_HTTPHEADER]) or empty($this->headers)) 
		or $this->options[CURLOPT_HTTPHEADER] = $this->headers;
		
		(isset($this->options[CURLOPT_HTTP_VERSION]) or empty($this->http_version)) 
		or $this->options[CURLOPT_HTTP_VERSION] = $this->http_version;
		
		if($this->request = curl_init($this->url) and is_resource($this->request)){
			$set_options = curl_setopt_array($this->request, $this->options);
			
			$this->response = curl_exec($this->request);
			$this->info = curl_getinfo($this->request);
			
			if($set_options === false or $this->response === false){
				$errno = curl_errno($this->request);
				$error = curl_error($this->request);
				curl_close($this->request);
				$this->clear();
				$this->error_code = $errno;
				$this->error_msg = $error;
				return false;
			}else{
				$response = $this->response;
				curl_close($this->request);
				$this->clear();
				return $response;
			}
		}else{
			$this->clear();
			$this->error_msg = 'Could not connect to ' . $this->url . ':' . $this->port;
			return false;
		}
	}
	
	/**
	 * Clear initialized data
	 */
	public function clear(){
		$this->request = NULL;
		$this->response = '';
		$this->headers = array();
		$this->options = array();
		$this->url = '';
		$this->port = 80;
		$this->error_code = '';
		$this->error_msg = '';
	}
	
	/**
	 * Get recent request info
	 * @param string $key
	 */
	public function getInfo($key = ''){
		return isset($this->info[$key]) ? $this->info[$key] : $this->info;
	}
	
	/**
	 * Get request error
	 */
	public function getError(){
		return ($this->error_code or $this->error_msg) ? get_class($this) . ' Class Error ' . $this->error_code . ': ' . $this->error_msg : '';
	}
	
	/**
	 * Get request error code
	 */
	public function errorCode(){
		return $this->error_code;
	}
	
	/**
	 * Get request error message
	 */
	public function errorMsg(){
		return $this->error_msg;
	}
	
	/**
	 * Parse raw header text
	 * @param string $raw_header
	 */
	public function parseHeader($raw_header){
		$headers = array();
		foreach(explode("\r\n", trim($raw_header, "\r\n")) as $line){
			if(strpos($line, ':') !== false){
				list($key, $value) = explode(':', $line, 2);
				$headers[trim($key)] = trim($value);
			}
		}
		return $headers;
	}
	
	protected function _initialize($config){
		if(is_array($config)){
			isset($config['timeout']) and $this->timeout = $config['timeout'];
			isset($config['strict_mode']) and $this->strict_mode = (bool) $config['strict_mode'];
			isset($config['max_redirects']) and $this->max_redirects = (int) $config['max_redirects'];
			
			(isset($config['http_version']) and isset($this->http_versions[$config['http_version']])) 
			and $this->http_version = $this->http_versions[$config['http_version']];
		}
	}
	
	protected function _isEnabled(){
		return (extension_loaded('curl') and function_exists('curl_init'));
	}
}

/* CurlException class */
class CurlException extends Exception{}

/* End of file Curl.php */