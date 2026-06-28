<?php

class Roles {
    private $db;
    private $table = 'roles';

    public function __construct($db) {
        $this->db = $db;
    }

    public function add($data) {
        $sql = "INSERT INTO {$this->table} (name, permissions) VALUES (?, ?)";
        $params = [$data['name'], json_encode($data['permissions'])];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET name = ?, permissions = ? WHERE id = ?";
        $params = [$data['name'], json_encode($data['permissions']), $id];
        $this->db->execute($sql, $params);
    }

    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $this->db->execute($sql, [$id]);
    }

    public function get_by_id($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    public function get_all() {
        $sql = "SELECT * FROM roles";
        return $this->db->fetchAll($sql);
    }

        public function name_exists($name, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE (`name` = ?)";
        $user = $this->db->fetch($sql, [$name]);
        if ($user['id']) {
            return false;
        }
        return true;
    }
}

?>