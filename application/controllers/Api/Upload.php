<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Upload extends CI_Controller {

    public $basePath;
    public $profile;
    public $profileName;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Cdn_file_model');
    }

    public function index()
    {
        $this->profileName = $this->input->post('profile');
        $this->profile     = cdn_profile($this->profileName);

        if (!$this->profile) {
            return $this->error('Invalid upload profile');
        }

        $this->basePath = cdn_profile_path($this->profile);

        // =========================
        // 1. UPLOAD VIA FILE
        // =========================
        if (!empty($_FILES['file']['name'])) {
            return $this->uploadFromFile();
        }

        // =========================
        // 2. UPLOAD VIA IMAGE URL
        // =========================
        if ($this->input->post('image_url')) {
            return $this->uploadFromUrl($this->input->post('image_url'));
        }

        return $this->error('file or image_url is required');
    }

    // =============================
    // FILE UPLOAD
    // =============================
    private function uploadFromFile()
    {
        $tmp  = $_FILES['file']['tmp_name'];
        $size = $_FILES['file']['size'];

        if ($size > $this->profile['max_size']) {
            return $this->error('File too large');
        }

        $mime = mime_content_type($tmp);
        if (!in_array($mime, $this->profile['allowed_mime'])) {
            return $this->error('Invalid file type');
        }

        $ext      = explode('/', $mime)[1];
        $fileUid  = 'f_' . sha1(uniqid('', true));
        $filename = $fileUid . '.' . $ext;

        ensure_dir($this->basePath);

        $fullPath     = $this->basePath . $filename;
        //$relativePath = str_replace(FCPATH, '', $fullPath);
        $relativePath = rtrim($this->profile['base_folder'], '/') . '/' . $filename;


        if (!move_uploaded_file($tmp, $fullPath)) {
            return $this->error('Failed to save file');
        }

        return $this->persistAndRespond(
            $fileUid,
            $filename,
            $relativePath,
            $mime,
            $size,
            $fullPath
        );
    }

    // =============================
    // URL UPLOAD
    // =============================
    private function uploadFromUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('Invalid URL');
        }

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
            'http' => ['timeout' => 5, 'follow_location' => 1]
        ]);

        $data = @file_get_contents($url, false, $ctx);
        if (!$data) {
            return $this->error('Failed to download image');
        }

        if (strlen($data) > $this->profile['max_size']) {
            return $this->error('Image too large');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($data);

        if (!in_array($mime, $this->profile['allowed_mime'])) {
            return $this->error('Invalid image type');
        }

        $ext      = explode('/', $mime)[1];
        $fileUid  = 'f_' . sha1(uniqid('', true));
        $filename = $fileUid . '.' . $ext;

        ensure_dir($this->basePath);

        $fullPath     = $this->basePath . $filename;
        $relativePath = str_replace(FCPATH, '', $fullPath);

        if (!file_put_contents($fullPath, $data)) {
            return $this->error('Failed to save image');
        }

        return $this->persistAndRespond(
            $fileUid,
            $filename,
            $relativePath,
            $mime,
            strlen($data),
            $fullPath
        );
    }

    // =============================
    // SAVE TO DB + RESPONSE
    // =============================
    private function persistAndRespond(
        $fileUid,
        $filename,
        $relativePath, // akan kita override
        $mime,
        $size,
        $fullPath
    ) {
        $relativePath = rtrim($this->profile['base_folder'], '/') . '/' . $filename;
        $publicUrl    = base_url($relativePath);

        $originalName = $this->input->post('original_name');
        if (!$originalName) {
            $originalName = $filename;
        }

        $saved = $this->Cdn_file_model->create([
            'file_key'      => $fileUid,
            'original_name' => $originalName,
            'stored_name'   => $filename,
            'mime_type'     => $mime,
            'size'          => (int) $size,
            'path'          => $relativePath,
            'is_public'     => $this->profile['public'] ? 1 : 0,
            'expired_at'    => null,
        ]);

        var_dump($saved);
        die();

        if (!$saved) {
            @unlink($fullPath);
            return $this->error('Failed to save metadata');
        }

        return $this->success($fileUid, $publicUrl);
    }

    // =============================
    // RESPONSE
    // =============================
    private function success($fileUid, $url)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'file' => [
                    'id'  => $fileUid,
                    'url' => $url,
                    'profile' => $this->profileName
                ]
            ]));
    }

    private function error($msg)
    {
        $this->output
            ->set_status_header(400)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => false,
                'error' => $msg
            ]));
    }
}
