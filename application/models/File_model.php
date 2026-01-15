<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File_model extends CI_Model
{
    protected $table = 'cdn_files';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Insert file metadata
     */
    public function insert(array $data)
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * Get file by file_key
     */
    public function getByKey(string $file_key)
    {
        return $this->db
            ->where('file_key', $file_key)
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Check if file exists and valid (not expired)
     */
    public function getValidFile(string $file_key)
    {
        return $this->db
            ->where('file_key', $file_key)
            ->group_start()
                ->where('expired_at IS NULL', null, false)
                ->or_where('expired_at >', date('Y-m-d H:i:s'))
            ->group_end()
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Delete file by ID
     */
    public function deleteById(int $id)
    {
        return $this->db
            ->where('id', $id)
            ->delete($this->table);
    }

    /**
     * Get expired files (for cron cleanup)
     */
    public function getExpiredFiles(int $limit = 100)
    {
        return $this->db
            ->where('expired_at IS NOT NULL', null, false)
            ->where('expired_at <', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->get($this->table)
            ->result();
    }

    /**
     * Log file access (optional)
     */
    public function logAccess(int $file_id, ?string $api_key = null)
    {
        return $this->db->insert('cdn_access_logs', [
            'file_id'     => $file_id,
            'api_key'     => $api_key,
            'ip_address'  => $this->input->ip_address(),
            'user_agent'  => substr($this->input->user_agent(), 0, 500),
            'accessed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Increment download counter (optional future use)
     */
    public function incrementDownload(int $file_id)
    {
        return $this->db
            ->set('download_count', 'download_count+1', false)
            ->where('id', $file_id)
            ->update($this->table);
    }

    /**
     * Get file storage absolute path
     */
    public function getFilePath(object $file)
    {
        return FCPATH . "../storage/{$file->path}/{$file->stored_name}";
    }
}
