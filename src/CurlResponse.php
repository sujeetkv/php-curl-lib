<?php
namespace SujeetKumar\CurlLib;

/**
 * CurlResponse class
 * - PSR 7 compliant
 * 
 * @author Sujeet <sujeetkv90@gmail.com>
 * @link https://github.com/sujeet-kumar/php-curl-lib
 */
class CurlResponse
{
    private $raw_response;
    private $raw_headers;
    
    private $body;
    private $headers = [];
    private $header_names = [];
    
    private $status_code = 200;
    private $reason_phrase = '';
    private $protocol_version = '1.1';
    
    private static $reason_phrases = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];
    
    /**
     * Constructor
     * 
     * @param string $raw_response
     * @param string $raw_headers
     * @param string $body
     * @param int $status_code
     * @param string $protocol_version
     * @param string $reason_phrase
     */
    public function __construct($raw_response, $raw_headers, $body, $status_code = 200, $protocol_version = '1.1', $reason_phrase = '') {
        $this->raw_response = $raw_response;
        $this->raw_headers = $raw_headers;
        $this->protocol_version = $protocol_version;
        $this->status_code = $status_code;
        $this->reason_phrase = ($reason_phrase == '' and isset(self::$reason_phrases[$this->status_code])) 
                                ? self::$reason_phrases[$this->status_code] : (string) $reason_phrase;
        $this->body = $body;
        $this->setHeaders();
    }
    
    /**
     * Get the HTTP protocol version
     * 
     * @return string
     */
    public function getProtocolVersion() {
        return $this->protocol_version;
    }
    
    /**
     * Return an instance with the specified HTTP protocol version
     * 
     * @param string $version
     * @return CurlResponse
     */
    public function withProtocolVersion($version) {
        if ($version === $this->protocol_version) {
            return $this;
        }
        
        $new = clone $this;
        $this->protocol_version = $version;
        return $new;
    }
    
    /**
     * Get all headers
     * 
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }
    
    /**
     * Checks if a header exists by the given case-insensitive name
     * 
     * @param string $name
     * @return bool
     */
    public function hasHeader($name) {
        return isset($this->header_names[strtolower($name)]);
    }
    
    /**
     * Get a message header value by the given case-insensitive name
     * 
     * @param string $name
     * @return array
     */
    public function getHeader($name) {
        $name = strtolower($name);
        return isset($this->header_names[$name]) ? $this->headers[$this->header_names[$name]] : [];
    }
    
    /**
     * Get comma-separated string of the values for a single header
     * 
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name) {
        return implode(', ', $this->getHeader($name));
    }
    
    /**
     * Return an instance with the provided value replacing the specified header
     * 
     * @param string $name
     * @param string|array $value
     * @return CurlResponse
     */
    public function withHeader($name, $value) {
        $normalized_name = strtolower($name);
        is_array($value) or $value = [$value];
        
        $value = array_map([$this, 'trimHeaderValue'], $value);
        
        $new = clone $this;
        if (isset($new->header_names[$normalized_name])) {
            unset($new->headers[$new->header_names[$normalized_name]]);
        }
        $new->header_names[$normalized_name] = $name;
        $new->headers[$name] = $value;
        return $new;
    }
    
    /**
     * Return an instance with the specified header appended with the given value
     * 
     * @param string $name
     * @param string|string[] $value
     * @return CurlResponse
     */
    public function withAddedHeader($name, $value) {
        $normalized_name = strtolower($name);
        is_array($value) or $value = [$value];
        
        $value = array_map([$this, 'trimHeaderValue'], $value);
        
        $new = clone $this;
        if (isset($new->header_names[$normalized_name])) {
            $new->headers[$new->header_names[$normalized_name]] = array_merge($new->headers[$new->header_names[$normalized_name]], $value);
        }
        $new->header_names[$normalized_name] = $name;
        $new->headers[$name] = $value;
        return $new;
    }
    
    /**
     * Return an instance without the specified header
     * 
     * @param string $name
     * @return CurlResponse
     */
    public function withoutHeader($name) {
        $normalized_name = strtolower($name);
        
        if (!isset($this->header_names[$normalized_name])) {
            return $this;
        }
        
        $new = clone $this;
        unset($new->headers[$new->header_names[$normalized_name]]);
        return $new;
    }
    
    /**
     * Get response body
     * 
     * @return string
     */
    public function getBody() {
        return $this->body;
    }
    
    /**
     * Return an instance with specified response body
     * 
     * @param string $body
     * @return CurlResponse
     */
    public function withBody($body) {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }
    
    /**
     * Get response status code
     * 
     * @return int
     */
    public function getStatusCode() {
        return $this->status_code;
    }
    
    /**
     * Get response status reason phrase
     * 
     * @return string
     */
    public function getReasonPhrase() {
        return $this->reason_phrase;
    }
    
    /**
     * Get response with given status
     * 
     * @param int $status_code
     * @param string $reason_phrase
     * @return CurlResponse
     */
    public function withStatus($status_code, $reason_phrase = '') {
        $new = clone $this;
        $new->status_code = $status_code;
        $new->reason_phrase = $reason_phrase;
        return $new;
    }
    
    /**
     * Get raw response
     * 
     * @return string
     */
    public function getRawResponse() {
        return $this->raw_response;
    }
    
    /**
     * Parse raw header text
     */
    private function setHeaders() {
        $raw_headers = explode("\r\n", trim($this->raw_headers, "\r\n"));
        
        foreach ($raw_headers as $raw_header) {
            if (strpos($raw_header, ':') !== false) {
                
                list($key, $value) = array_map([$this, 'trimHeaderValue'], explode(':', $raw_header, 2));
                
                $key_name = strtolower($key);
                if (isset($this->header_names[$key_name])) {
                    $this->headers[$key] = array_merge($this->headers[$key], [$value]);
                } else {
                    $this->headers[$key] = [$value];
                    $this->header_names[$key_name] = $key;
                }
            }
        }
    }
    
    /**
     * Trim header value
     * 
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     * 
     * @param string $value
     * @return string
     */
    private function trimHeaderValue($value) {
        return trim($value, " \t");
    }
    
    /**
     * Stringify instance
     */
    public function __toString() {
        return $this->body;
    }
}
