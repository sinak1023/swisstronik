<?php

class Notes
{
    private $db;
    private $table = 'lead_notes';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function add($data)
    {
        $sql = "INSERT INTO {$this->table} (lead_id, phone, user_id, note, created_at) VALUES (?, ?, ?, ?, NOW())";
        $params = [$data['lead_id'],$data['phone'], $data['user_id'], $data['note']];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = ?";
        return $this->db->execute($sql, $params);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }


    public function get_by_id($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    public function get_by_lead($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lead_id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    public function get_by_user($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        return $this->db->fetch($sql, [$id]);
    }
    public function get_by_phone($phone)
    {
        $sql = "SELECT * FROM {$this->table} WHERE phone = ? ORDER BY created_at DESC, id DESC";
        return $this->db->fetchAll($sql, [$phone]);
    }
    public function get_by_lead_user($lead_id, $user_id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lead_id = ? and user_id = ? ORDER BY created_at DESC, id DESC";
        return $this->db->fetchAll($sql, [$lead_id, $user_id]);
    }

    public function get_all()
    {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->fetchAll($sql);
    }
}
