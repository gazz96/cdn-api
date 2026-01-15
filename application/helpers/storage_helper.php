<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('ensure_dir')) {
    function ensure_dir($path, $permission = 0755)
    {
        if (!is_dir($path)) {
            mkdir($path, $permission, true);
        }
    }
}

if (!function_exists('cdn_storage_path')) {

    function cdn_storage_path($isPublic = true, $customFolder = null)
    {
        $CI =& get_instance();
        $cdn = $CI->config->item('cdn');

        $path = rtrim($cdn['base_path'], '/') . '/';
        $path .= $isPublic ? $cdn['public_folder'] : $cdn['private_folder'];

        $segments = [];

        if ($cdn['use_year'])  $segments[] = date('Y');
        if ($cdn['use_month']) $segments[] = date('m');
        if ($cdn['use_day'])   $segments[] = date('d');

        if (!empty($segments)) {
            $path .= '/' . implode('/', $segments);
        }

        if ($cdn['allow_custom_folder'] && $customFolder) {
            $safeFolder = cdn_safe_folder($customFolder);
            if ($safeFolder) {
                $path .= '/' . $safeFolder;
            }
        }

        return FCPATH . trim($path, '/') . '/';
    }
}

if (!function_exists('cdn_safe_folder')) {

    function cdn_safe_folder($folder)
    {
        if (!$folder) {
            return null;
        }

        $folder = trim($folder, '/');

        // block path traversal
        if (strpos($folder, '..') !== false) {
            return null;
        }

        $CI =& get_instance();
        $cdn = $CI->config->item('cdn');

        if (!preg_match($cdn['folder_pattern'], $folder)) {
            return null;
        }

        return $folder;
    }
}

function cdn_profile($name)
{
    $CI =& get_instance();
    $profiles = $CI->config->item('cdn')['profiles'];
    return $profiles[$name] ?? null;
}

function cdn_profile_path($profile)
{
    $path = 'storage/';
    $path .= $profile['public'] ? 'public/' : 'private/';
    $path .= trim($profile['base_folder'], '/');

    if (!empty($profile['use_year']))  $path .= '/' . date('Y');
    if (!empty($profile['use_month'])) $path .= '/' . date('m');
    if (!empty($profile['use_day']))   $path .= '/' . date('d');

    return FCPATH . trim($path, '/') . '/';
}