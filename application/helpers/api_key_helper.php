<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API Key Helper
 *
 * Helper functions for API key operations
 */

if (!function_exists('generate_api_key'))
{
    /**
     * Generate new API key
     */
    function generate_api_key()
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('validate_api_key_format'))
{
    /**
     * Validate API key format
     */
    function validate_api_key_format($api_key)
    {
        return preg_match('/^[a-f0-9]{64}$/', $api_key);
    }
}

if (!function_exists('mask_api_key'))
{
    /**
     * Mask API key for display
     */
    function mask_api_key($api_key, $show_chars = 8)
    {
        if (strlen($api_key) <= $show_chars)
        {
            return str_repeat('*', strlen($api_key));
        }
        
        return substr($api_key, 0, $show_chars) . str_repeat('*', strlen($api_key) - $show_chars);
    }
}

if (!function_exists('get_api_key_from_header'))
{
    /**
     * Extract API key from request header
     */
    function get_api_key_from_header()
    {
        $CI =& get_instance();
        return $CI->input->get_request_header('X-API-KEY');
    }
}

if (!function_exists('is_valid_api_key_length'))
{
    /**
     * Check if API key has valid length
     */
    function is_valid_api_key_length($api_key)
    {
        return strlen($api_key) === 64;
    }
}

if (!function_exists('hash_api_key'))
{
    /**
     * Hash API key for storage (optional security enhancement)
     */
    function hash_api_key($api_key)
    {
        return hash('sha256', $api_key);
    }
}

if (!function_exists('verify_api_key_hash'))
{
    /**
     * Verify API key against hash
     */
    function verify_api_key_hash($api_key, $hash)
    {
        return hash('sha256', $api_key) === $hash;
    }
}