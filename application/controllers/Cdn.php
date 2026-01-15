<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cdn extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('File_model');
    }

    public function index($file_key = null)
    {
        if (!$file_key) {
            show_404();
        }

        $file = $this->File_model->getByKey($file_key);

        if (!$file) {
            show_404();
        }

        if ($file->expired_at && strtotime($file->expired_at) < time()) {
            show_error('File expired', 410);
        }

        if (!$file->is_public) {
            show_error('Access denied', 403);
        }

        $path = FCPATH . "../storage/{$file->path}/{$file->stored_name}";

        if (!file_exists($path)) {
            show_404();
        }

        // header caching
        header('Content-Type: ' . $file->mime_type);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        readfile($path);
        exit;
    }
}
