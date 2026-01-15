<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller - Base Controller for CDN API
 *
 * Handles API authentication, rate limiting, and CORS
 */
class MY_Controller extends CI_Controller {

    protected $api_key_data = NULL;
    protected $cdn_config = [];

    public function __construct()
    {
        parent::__construct();
        
        // Load CDN config
        $this->cdn_config = $this->config->item('cdn');
        
        // Set CORS headers if enabled
        if ($this->cdn_config['cors_enabled'])
        {
            $this->_set_cors_headers();
        }
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
        {
            exit(0);
        }
        
        // For API endpoints, validate authentication
        if ($this->_is_api_endpoint())
        {
            $this->_validate_api_key();
            $this->_check_rate_limit();
        }
    }

    /**
     * Set CORS headers
     */
    private function _set_cors_headers()
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        
        if (in_array('*', $this->cdn_config['cors_origins']) || 
            in_array($origin, $this->cdn_config['cors_origins']))
        {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        header("Access-Control-Allow-Methods: " . implode(', ', $this->cdn_config['cors_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', $this->cdn_config['cors_headers']));
        header("Access-Control-Max-Age: 86400");
    }

    /**
     * Check if current request is for API endpoint
     */
    private function _is_api_endpoint()
    {
        $uri = $this->uri->uri_string();
        return strpos($uri, $this->cdn_config['api_prefix']) === 0;
    }

    /**
     * Validate API Key from header
     */
    private function _validate_api_key()
    {
        $api_key = $this->input->get_request_header('X-API-KEY');
        
        if (!$api_key)
        {
            $this->_send_error_response(401, 'API Key required');
        }
        
        // Load API Key model
        $this->load->model('Api_key_model');
        
        // Validate API key
        $key_data = $this->Api_key_model->get_by_key($api_key);
        
        if (!$key_data)
        {
            $this->_send_error_response(401, 'Invalid API Key');
        }
        
        // Check if API key is active
        if (!$key_data->is_active)
        {
            $this->_send_error_response(403, 'API Key is inactive');
        }
        
        // Check if API key is expired
        if ($key_data->expired_at && strtotime($key_data->expired_at) < time())
        {
            $this->_send_error_response(403, 'API Key has expired');
        }
        
        // Store API key data for use in controllers
        $this->api_key_data = $key_data;
    }

    /**
     * Check rate limiting
     */
    private function _check_rate_limit()
    {
        $this->load->model('Rate_limit_model');
        
        $endpoint = $this->uri->uri_string();
        $api_key_id = $this->api_key_data->id;
        $rate_limit = $this->api_key_data->rate_limit ?: $this->cdn_config['default_rate_limit'];
        
        // Check rate limit
        $limit_data = $this->Rate_limit_model->check_limit($api_key_id, $endpoint, $rate_limit);
        
        if ($limit_data->exceeded)
        {
            $this->_send_error_response(429, 'Rate limit exceeded. Try again later.', [
                'retry_after' => $limit_data->retry_after
            ]);
        }
        
        // Increment request count
        $this->Rate_limit_model->increment_request($api_key_id, $endpoint);
    }

    /**
     * Send JSON error response
     */
    protected function _send_error_response($code, $message, $data = [])
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'error',
                'code' => $code,
                'message' => $message,
                'data' => $data
            ]));
        
        exit;
    }

    /**
     * Send JSON success response
     */
    protected function _send_success_response($data = [], $message = 'Success')
    {
        $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'message' => $message,
                'data' => $data
            ]));
    }

    /**
     * Get current API key data
     */
    protected function get_api_key_data()
    {
        return $this->api_key_data;
    }

    /**
     * Log API request
     */
    protected function _log_request($action, $details = [])
    {
        if (!$this->cdn_config['log_uploads'] && $action === 'upload') return;
        if (!$this->cdn_config['log_downloads'] && $action === 'download') return;
        if (!$this->cdn_config['log_errors'] && $action === 'error') return;
        
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'api_key_id' => $this->api_key_data ? $this->api_key_data->id : NULL,
            'endpoint' => $this->uri->uri_string(),
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $this->input->ip_address(),
            'user_agent' => $this->input->user_agent(),
            'details' => $details
        ];
        
        log_message('info', 'CDN_API: ' . json_encode($log_data));
    }
}