<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_key_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct(); // 🔥 WAJIB
    }

    protected $table = 'cdn_api_keys';

    /**
     * Get active API key
     */
    public function getActiveKey($key) {
        return $this->db
            ->where('api_key', $key)
            ->where('status', 'active')
            ->get('cdn_api_keys')
            ->row();
    }

    /**
     * Increase usage counter (simple rate limit)
     */
    public function incrementUsage(string $api_key)
    {
        return $this->db
            ->set('usage_count', 'usage_count+1', false)
            ->where('api_key', $api_key)
            ->update($this->table);
    }

    /**
     * Reset usage (for cron / daily reset)
     */
    public function resetUsage()
    {
        return $this->db
            ->set('usage_count', 0)
            ->update($this->table);
    }
}
