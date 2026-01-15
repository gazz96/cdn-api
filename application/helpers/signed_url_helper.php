<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Signed URL Helper
 *
 * Helper functions for signed URL operations
 */

if (!function_exists('generate_signed_url'))
{
    /**
     * Generate signed URL for private file access
     */
    function generate_signed_url($file_id, $expires_in = 300)
    {
        $CI =& get_instance();
        $cdn_config = $CI->config->item('cdn');
        
        $expires = time() + $expires_in;
        $signature = generate_signature($file_id, $expires);
        
        $base_url = rtrim($cdn_config['base_url'], '/');
        $endpoint = $cdn_config['api_prefix'] . 'files/private/' . $file_id;
        
        return $base_url . '/' . $endpoint . '?expires=' . $expires . '&signature=' . $signature;
    }
}

if (!function_exists('generate_signature'))
{
    /**
     * Generate signature for signed URL
     */
    function generate_signature($file_id, $expires)
    {
        $CI =& get_instance();
        $cdn_config = $CI->config->item('cdn');
        
        $data = $file_id . $expires;
        $secret = $cdn_config['signed_url_secret'];
        
        return hash_hmac('sha256', $data, $secret);
    }
}

if (!function_exists('verify_signed_url'))
{
    /**
     * Verify signed URL signature
     */
    function verify_signed_url($file_id, $expires, $signature)
    {
        // Check if URL has expired
        if ($expires < time())
        {
            return false;
        }
        
        // Generate expected signature
        $expected_signature = generate_signature($file_id, $expires);
        
        // Compare signatures using hash_equals to prevent timing attacks
        return hash_equals($expected_signature, $signature);
    }
}

if (!function_exists('parse_signed_url'))
{
    /**
     * Parse signed URL parameters
     */
    function parse_signed_url()
    {
        $CI =& get_instance();
        
        $file_id = $CI->uri->segment(4); // Assuming /api/v1/files/private/{id}
        $expires = $CI->input->get('expires');
        $signature = $CI->input->get('signature');
        
        return [
            'file_id' => $file_id,
            'expires' => $expires,
            'signature' => $signature
        ];
    }
}

if (!function_exists('is_signed_url_valid'))
{
    /**
     * Check if signed URL is valid
     */
    function is_signed_url_valid()
    {
        $params = parse_signed_url();
        
        if (!$params['file_id'] || !$params['expires'] || !$params['signature'])
        {
            return false;
        }
        
        return verify_signed_url($params['file_id'], $params['expires'], $params['signature']);
    }
}

if (!function_exists('get_signed_url_expires'))
{
    /**
     * Get expiration time from signed URL
     */
    function get_signed_url_expires()
    {
        $params = parse_signed_url();
        return $params['expires'] ? (int) $params['expires'] : null;
    }
}

if (!function_exists('get_signed_url_time_remaining'))
{
    /**
     * Get time remaining for signed URL
     */
    function get_signed_url_time_remaining()
    {
        $expires = get_signed_url_expires();
        
        if (!$expires)
        {
            return 0;
        }
        
        $remaining = $expires - time();
        return max(0, $remaining);
    }
}

if (!function_exists('generate_temporary_token'))
{
    /**
     * Generate temporary token for file access
     */
    function generate_temporary_token($file_id, $expires_in = 300)
    {
        $token_data = [
            'file_id' => $file_id,
            'expires' => time() + $expires_in,
            'random' => bin2hex(random_bytes(16))
        ];
        
        $token = base64_encode(json_encode($token_data));
        return str_replace(['+', '/', '='], ['-', '_', ''], $token);
    }
}

if (!function_exists('verify_temporary_token'))
{
    /**
     * Verify temporary token
     */
    function verify_temporary_token($token)
    {
        try
        {
            // Replace URL-safe characters back
            $token = str_replace(['-', '_'], ['+', '/'], $token);
            
            // Add padding if needed
            $padding = strlen($token) % 4;
            if ($padding)
            {
                $token .= str_repeat('=', 4 - $padding);
            }
            
            $data = json_decode(base64_decode($token), true);
            
            if (!$data || !isset($data['file_id']) || !isset($data['expires']))
            {
                return false;
            }
            
            // Check expiration
            if ($data['expires'] < time())
            {
                return false;
            }
            
            return $data['file_id'];
        }
        catch (Exception $e)
        {
            return false;
        }
    }
}

if (!function_exists('get_signed_url_info'))
{
    /**
     * Get information about signed URL
     */
    function get_signed_url_info()
    {
        $params = parse_signed_url();
        
        if (!$params['file_id'] || !$params['expires'])
        {
            return null;
        }
        
        $remaining = get_signed_url_time_remaining();
        $is_valid = is_signed_url_valid();
        
        return [
            'file_id' => $params['file_id'],
            'expires' => (int) $params['expires'],
            'expires_formatted' => date('Y-m-d H:i:s', (int) $params['expires']),
            'time_remaining' => $remaining,
            'time_remaining_formatted' => format_time_remaining($remaining),
            'is_valid' => $is_valid,
            'is_expired' => $remaining === 0
        ];
    }
}

if (!function_exists('format_time_remaining'))
{
    /**
     * Format time remaining in human readable format
     */
    function format_time_remaining($seconds)
    {
        if ($seconds <= 0)
        {
            return 'Expired';
        }
        
        if ($seconds < 60)
        {
            return $seconds . ' second' . ($seconds != 1 ? 's' : '');
        }
        elseif ($seconds < 3600)
        {
            $minutes = round($seconds / 60);
            return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
        }
        else
        {
            $hours = round($seconds / 3600);
            return $hours . ' hour' . ($hours != 1 ? 's' : '');
        }
    }
}