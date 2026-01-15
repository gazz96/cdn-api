<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rate Limit Model
 *
 * Handles rate limiting operations
 */
class Rate_limit_model extends CI_Model {

    protected $table = 'rate_limits';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Check rate limit for API key and endpoint
     */
    public function check_limit($api_key_id, $endpoint, $limit)
    {
        $window_start = date('Y-m-d H:i:s', time() - $this->config->item('cdn')['rate_limit_window']);
        
        // Get or create rate limit record
        $rate_limit = $this->db
            ->where('api_key_id', $api_key_id)
            ->where('endpoint', $endpoint)
            ->where('window_start >=', $window_start)
            ->get($this->table)
            ->row();

        if (!$rate_limit)
        {
            // Create new rate limit window
            $this->db->insert($this->table, [
                'api_key_id' => $api_key_id,
                'endpoint' => $endpoint,
                'request_count' => 0,
                'window_start' => date('Y-m-d H:i:s')
            ]);
            
            $rate_limit = (object) [
                'request_count' => 0,
                'window_start' => date('Y-m-d H:i:s')
            ];
        }

        $remaining = $limit - $rate_limit->request_count;
        $exceeded = $rate_limit->request_count >= $limit;
        
        // Calculate retry after (when window resets)
        $window_end = strtotime($rate_limit->window_start) + $this->config->item('cdn')['rate_limit_window'];
        $retry_after = $exceeded ? max(1, $window_end - time()) : 0;

        return (object) [
            'exceeded' => $exceeded,
            'remaining' => max(0, $remaining),
            'limit' => $limit,
            'retry_after' => $retry_after,
            'window_start' => $rate_limit->window_start,
            'window_end' => date('Y-m-d H:i:s', $window_end)
        ];
    }

    /**
     * Increment request count
     */
    public function increment_request($api_key_id, $endpoint)
    {
        $window_start = date('Y-m-d H:i:s', time() - $this->config->item('cdn')['rate_limit_window']);
        
        // Update existing record or create new one
        $this->db->query("
            INSERT INTO {$this->table} (api_key_id, endpoint, request_count, window_start)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
            request_count = CASE 
                WHEN window_start < ? THEN 1 
                ELSE request_count + 1 
            END,
            window_start = CASE 
                WHEN window_start < ? THEN ? 
                ELSE window_start 
            END
        ", [
            $api_key_id,
            $endpoint,
            date('Y-m-d H:i:s'),
            $window_start,
            $window_start,
            date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get rate limit status
     */
    public function get_status($api_key_id, $endpoint)
    {
        $rate_limit = $this->db
            ->where('api_key_id', $api_key_id)
            ->where('endpoint', $endpoint)
            ->where('window_start >=', date('Y-m-d H:i:s', time() - $this->config->item('cdn')['rate_limit_window']))
            ->get($this->table)
            ->row();

        if (!$rate_limit)
        {
            return (object) [
                'request_count' => 0,
                'limit' => $this->config->item('cdn')['default_rate_limit'],
                'remaining' => $this->config->item('cdn')['default_rate_limit'],
                'window_start' => date('Y-m-d H:i:s'),
                'window_end' => date('Y-m-d H:i:s', time() + $this->config->item('cdn')['rate_limit_window'])
            ];
        }

        $limit = $this->get_api_key_limit($api_key_id);
        $remaining = max(0, $limit - $rate_limit->request_count);
        $window_end = strtotime($rate_limit->window_start) + $this->config->item('cdn')['rate_limit_window'];

        return (object) [
            'request_count' => $rate_limit->request_count,
            'limit' => $limit,
            'remaining' => $remaining,
            'window_start' => $rate_limit->window_start,
            'window_end' => date('Y-m-d H:i:s', $window_end)
        ];
    }

    /**
     * Get API key's rate limit
     */
    public function get_api_key_limit($api_key_id)
    {
        $this->db->select('rate_limit');
        $api_key = $this->db->get_where('api_keys', ['id' => $api_key_id])->row();
        
        return $api_key ? $api_key->rate_limit : $this->config->item('cdn')['default_rate_limit'];
    }

    /**
     * Reset rate limit for API key and endpoint
     */
    public function reset_limit($api_key_id, $endpoint = NULL)
    {
        $this->db->where('api_key_id', $api_key_id);
        
        if ($endpoint)
        {
            $this->db->where('endpoint', $endpoint);
        }
        
        return $this->db->delete($this->table);
    }

    /**
     * Clean up old rate limit records
     */
    public function cleanup()
    {
        $cutoff_time = date('Y-m-d H:i:s', time() - ($this->config->item('cdn')['rate_limit_window'] * 2));
        
        return $this->db
            ->where('window_start <', $cutoff_time)
            ->delete($this->table);
    }

    /**
     * Get rate limit statistics
     */
    public function get_stats($api_key_id = NULL, $hours = 24)
    {
        $cutoff_time = date('Y-m-d H:i:s', time() - ($hours * 3600));
        
        $this->db
            ->select('endpoint, SUM(request_count) as total_requests, COUNT(*) as windows')
            ->where('window_start >=', $cutoff_time);
            
        if ($api_key_id)
        {
            $this->db->where('api_key_id', $api_key_id);
        }
        
        return $this->db
            ->group_by('endpoint')
            ->order_by('total_requests', 'DESC')
            ->get($this->table)
            ->result();
    }

    /**
     * Get top endpoints by request count
     */
    public function get_top_endpoints($limit = 10, $hours = 24)
    {
        $cutoff_time = date('Y-m-d H:i:s', time() - ($hours * 3600));
        
        return $this->db
            ->select('endpoint, SUM(request_count) as total_requests')
            ->where('window_start >=', $cutoff_time)
            ->group_by('endpoint')
            ->order_by('total_requests', 'DESC')
            ->limit($limit)
            ->get($this->table)
            ->result();
    }
}