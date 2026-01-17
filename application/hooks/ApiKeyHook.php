<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ApiKeyHook
{
    protected $CI;

    /**
     * URI yang DIBEBASKAN dari API KEY
     * (file statis / hotlink CDN)
     */
    protected $excludedPrefixes = [
        'cdn/',
        'uploads/',
        'assets/',
        'storage/',
    ];

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Hook entry point
     */
    public function check()
    {
        $uri = ltrim($this->CI->uri->uri_string(), '/');

        /**
         * 1. Skip jika request file statis / CDN
         */
        foreach ($this->excludedPrefixes as $prefix) {
            if (strpos($uri, $prefix) === 0) {
                return; // LEWAT TANPA API KEY
            }
        }

        /**
         * 2. Hanya protect API
         */
        if (strpos($uri, 'api/') !== 0) {
            return;
        }

        /**
         * 3. Ambil API Key
         */
        $apiKey = $this->CI->input->get_request_header('X-API-KEY', true);

        if (!$apiKey) {
            return $this->reject(401, 'API Key required');
        }

        /**
         * 4. Validasi API Key
         */
        $this->CI->load->model('Api_key_model');
        $key = $this->CI->Api_key_model->getActiveKey($apiKey);

        if (!$key) {
            return $this->reject(401, 'Invalid API Key');
        }

        /**
         * 5. Rate limiting (simple)
         */
        if ($key->rate_limit !== null && $key->usage_count >= $key->rate_limit) {
            return $this->reject(429, 'Rate limit exceeded');
        }

        /**
         * 6. Increment usage
         */
        $this->CI->Api_key_model->incrementUsage($apiKey);

        /**
         * 7. Attach data ke CI instance (GLOBAL)
         */
        $this->CI->api_key_id   = $key->id;
        $this->CI->api_key_data = $key;
    }

    /**
     * JSON reject response
     */
    protected function reject(int $code, string $message)
    {
        http_response_code($code);
        header('Content-Type: application/json');

        echo json_encode([
            'status'  => 'error',
            'message' => $message
        ]);

        exit;
    }
}
