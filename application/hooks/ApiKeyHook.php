<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ApiKeyHook
{
    protected $CI;


    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Api_key_model');


        $key = $this->CI->input->get_request_header('X-API-KEY');

        $apiKey = $this->CI->Api_key_model->getActiveKey($key);

        if (!$apiKey) {
            show_error('Invalid API Key', 401);
        }

        // SET GLOBAL PROPERTY
        $this->CI->api_key_id = $apiKey->id;
        $this->CI->api_key    = $apiKey->key;
    }

    public function check()
    {
        // hanya protect API upload
        $uri = $this->CI->uri->uri_string();

        if (strpos($uri, 'api/') !== 0) {
            return;
        }

        $apiKey = $this->CI->input->get_request_header('X-API-KEY', true);

        if (!$apiKey) {
            return $this->_reject(401, 'API Key required');
        }

        $key = $this->CI->Api_key_model->getActiveKey($apiKey);

        if (!$key) {
            return $this->_reject(401, 'Invalid API Key');
        }

        // simple rate limit
        if ($key->usage_count >= $key->rate_limit) {
            return $this->_reject(429, 'Rate limit exceeded');
        }

        // increase usage
        $this->CI->Api_key_model->incrementUsage($apiKey);

        // attach key info to CI instance
        $this->CI->api_key_data = $key;
    }

    private function _reject(int $code, string $message)
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
