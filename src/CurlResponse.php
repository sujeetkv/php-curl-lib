<?php
namespace SujeetKumar\CurlLib;

/**
 * CurlResponse class
 * 
 * @author Sujeet <sujeetkv90@gmail.com>
 * @link https://github.com/sujeet-kumar/php-curl-lib
 */
class CurlResponse
{
    public $response;
    public $headers;
    public $body;
    public $code;
    
    public function __construct($response, $headers, $body, $code) {
        $this->response = $response;
        $this->headers = self::parseHeaders($headers);
        $this->body = $body;
        $this->code = $code;
    }
    
    /**
     * Get response
     */
    public function getResponse() {
        return $this->response;
    }
    
    /**
     * Get response headers
     */
    public function getHeaders() {
        return $this->headers;
    }
    
    /**
     * Get response body
     */
    public function getBody() {
        return $this->body;
    }
    
    /**
     * Get response code
     */
    public function getCode() {
        return $this->code;
    }
    
    /**
     * Parse raw header text
     * @param string $raw_headers
     */
    public static function parseHeaders($raw_headers) {
        is_array($raw_headers) or $raw_headers = explode("\r\n", trim($raw_headers, "\r\n"));
        $headers = array();
        foreach ($raw_headers as $raw_header) {
            if (strpos($raw_header, ':') !== false) {
                list($key, $value) = array_map('trim', explode(':', $raw_header, 2));
                // as per HTTP RFC Sec 4.2 combine same type of headers
                $headers[$key] = isset($headers[$key]) ? $headers[$key] . ',' . $value : $value;
            }
        }
        return $headers;
    }
    
    public function __toString() {
        return $this->body;
    }
}
