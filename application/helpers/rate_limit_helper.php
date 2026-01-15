<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rate Limit Helper
 *
 * Helper functions for rate limiting operations
 */

if (!function_exists('get_rate_limit_headers'))
{
    /**
     * Get rate limit headers for response
     */
    function get_rate_limit_headers($limit_data)
    {
        return [
            'X-RateLimit-Limit' => $limit_data->limit,
            'X-RateLimit-Remaining' => $limit_data->remaining,
            'X-RateLimit-Reset' => strtotime($limit_data->window_end)
        ];
    }
}

if (!function_exists('set_rate_limit_headers'))
{
    /**
     * Set rate limit headers in response
     */
    function set_rate_limit_headers($limit_data)
    {
        $CI =& get_instance();
        $headers = get_rate_limit_headers($limit_data);
        
        foreach ($headers as $name => $value)
        {
            $CI->output->set_header("{$name}: {$value}");
        }
        
        if ($limit_data->exceeded)
        {
            $CI->output->set_header("Retry-After: {$limit_data->retry_after}");
        }
    }
}

if (!function_exists('calculate_rate_limit_window'))
{
    /**
     * Calculate rate limit window start time
     */
    function calculate_rate_limit_window($window_size = 3600)
    {
        $timestamp = time();
        return $timestamp - ($timestamp % $window_size);
    }
}

if (!function_exists('is_rate_limit_exceeded'))
{
    /**
     * Check if rate limit is exceeded
     */
    function is_rate_limit_exceeded($current_count, $limit)
    {
        return $current_count >= $limit;
    }
}

if (!function_exists('get_rate_limit_status'))
{
    /**
     * Get human-readable rate limit status
     */
    function get_rate_limit_status($limit_data)
    {
        if ($limit_data->exceeded)
        {
            return [
                'status' => 'exceeded',
                'message' => 'Rate limit exceeded. Try again in ' . $limit_data->retry_after . ' seconds.',
                'retry_after' => $limit_data->retry_after
            ];
        }
        
        $percentage_used = (($limit_data->limit - $limit_data->remaining) / $limit_data->limit) * 100;
        
        return [
            'status' => 'ok',
            'message' => 'Rate limit: ' . $limit_data->remaining . '/' . $limit_data->limit . ' remaining',
            'percentage_used' => round($percentage_used, 2)
        ];
    }
}

if (!function_exists('format_retry_after'))
{
    /**
     * Format retry after time in human readable format
     */
    function format_retry_after($seconds)
    {
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

if (!function_exists('get_client_identifier'))
{
    /**
     * Get client identifier for rate limiting
     */
    function get_client_identifier()
    {
        $CI =& get_instance();
        
        // Try to get API key first
        $api_key = get_api_key_from_header();
        if ($api_key)
        {
            return 'api_key:' . $api_key;
        }
        
        // Fall back to IP address
        return 'ip:' . $CI->input->ip_address();
    }
}

if (!function_exists('should_rate_limit'))
{
    /**
     * Determine if request should be rate limited
     */
    function should_rate_limit($endpoint)
    {
        $CI =& get_instance();
        $cdn_config = $CI->config->item('cdn');
        
        // Skip rate limiting for certain endpoints if needed
        $skip_endpoints = [
            'api/v1/status',
            'api/v1/health'
        ];
        
        return !in_array($endpoint, $skip_endpoints);
    }
}