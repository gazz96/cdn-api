<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cdn_file_model extends CI_Model {

    protected $table = 'cdn_files';

    public function create(array $data)
    {
        $this->db->insert($this->table, $data);

        if ($this->db->affected_rows() !== 1) {
            log_message('error', json_encode($this->db->error()));
            return false;
        }

        return true;
    }

    public function findByUid(string $uid)
    {
        return $this->db
            ->where('file_uid', $uid)
            ->where('deleted_at IS NULL', null, false)
            ->get($this->table)
            ->row();
    }

    public function softDelete(string $uid)
    {
        return $this->db
            ->where('file_uid', $uid)
            ->update($this->table, [
                'deleted_at' => date('Y-m-d H:i:s')
            ]);
    }
}
