<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Upload extends CI_Controller {

    public $basePath;

    public function index()
    {
        $profileName = $this->input->post('profile');
        $profile = cdn_profile($profileName);
        
        if (!$profile) {
            return $this->error('Invalid upload profile');
        }

        $this->basePath = cdn_profile_path($profile);
        $isPublic = $this->input->post('is_public') ?? 1;

        // =========================
        // 1. UPLOAD VIA FILE
        // =========================
        if (!empty($_FILES['file']['name'])) {
            return $this->uploadFromFile($isPublic);
        }

        // =========================
        // 2. UPLOAD VIA IMAGE URL
        // =========================
        if ($this->input->post('image_url')) {
            return $this->uploadFromUrl(
                $this->input->post('image_url'),
                $isPublic
            );
        }

        return $this->error('file or image_url is required');
    }

    // =============================
    // FILE UPLOAD
    // =============================
    private function uploadFromFile($isPublic)
    {
        $tmp  = $_FILES['file']['tmp_name'];
        $size = $_FILES['file']['size'];

        if ($size > 5 * 1024 * 1024) {
            return $this->error('File too large');
        }

        $mime = mime_content_type($tmp);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed)) {
            return $this->error('Invalid file type');
        }

        $ext = explode('/', $mime)[1];
        $fileKey = bin2hex(random_bytes(16));
        $filename = $fileKey . '.' . $ext;

        ensure_dir($this->basePath);
        $path = $this->basePath . $filename;
        move_uploaded_file($tmp, $path);

        return $this->success($fileKey);
    }

    // =============================
    // URL UPLOAD
    // =============================
    private function uploadFromUrl($url, $isPublic)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('Invalid URL');
        }

        if (!preg_match('#^https?://#', $url)) {
            return $this->error('Only http/https allowed');
        }

        // SSRF protection
        $host = parse_url($url, PHP_URL_HOST);
        $ip   = gethostbyname($host);

        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
            return $this->error('Private IP not allowed');
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'follow_location' => 1
            ]
        ]);

        $data = @file_get_contents($url, false, $ctx);

        if (!$data) {
            return $this->error('Failed to download image');
        }

        if (strlen($data) > 5 * 1024 * 1024) {
            return $this->error('Image too large');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($data);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed)) {
            return $this->error('Invalid image type');
        }

        $ext = explode('/', $mime)[1];
        $fileKey = bin2hex(random_bytes(16));
        $filename = $fileKey . '.' . $ext;

        
        ensure_dir($this->basePath);
        $path = $this->basePath . $filename;
        file_put_contents($path, $data);

        return $this->success($fileKey);
    }

    // =============================
    // RESPONSE
    // =============================
    private function success($fileKey)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'data' => [
                    'file_key' => $fileKey,
                    'url' => base_url('cdn/' . $fileKey)
                ]
            ]));
    }

    private function error($msg)
    {
        $this->output
            ->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'error',
                'message' => $msg
            ]));
    }
}
