<?php


class Projects
{
    private $db;
    private $table = 'projects';

    public function __construct($db)
    {
        $this->db = $db;
    }

 
    public function add($data)
    {
        $sql = "INSERT INTO {$this->table} (`name`, `description`, `created_by`, `created_at`, `updated_at`) 
                VALUES (?, ?, ?, NOW(), NOW())";
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $_SESSION['id']
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

 
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} SET `name` = ?, `description` = ?, `updated_at` = NOW() WHERE id = ?";
        $params = [
            $data['name'],
            $data['description'] ?? null,
            $id
        ];
        return $this->db->execute($sql, $params);
    }

 
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

 
    public function get_by_id($id)
    {
        $sql = "SELECT p.*, u.name as creator_name 
                FROM {$this->table} p 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE p.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

 
    public function get_all($filters = [])
    {
        $sql = "SELECT p.*, u.name as creator_name 
                FROM {$this->table} p 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['name'])) {
            $sql .= " AND p.name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }

        
        $current_user = (new Users($this->db))->get_by_id($_SESSION['id']);
        if ($current_user['role_id'] != 1) { 
            $sql .= " AND p.created_by = ?";
            $params[] = $_SESSION['id'];
        }

        $sql .= " ORDER BY p.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

 
    public function get_all_with_pagination($filters = [], $limit = 10, $offset = 0)
    {
        $sql = "SELECT p.*, u.name as creator_name 
                FROM {$this->table} p 
                LEFT JOIN users u ON p.created_by = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['name'])) {
            $sql .= " AND p.name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }

        
        $current_user = (new Users($this->db))->get_by_id($_SESSION['id']);
        if ($current_user['role_id'] != 1) {
            $sql .= " AND p.created_by = ?";
            $params[] = $_SESSION['id'];
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

 
    public function get_total_count($filters = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filters['name'])) {
            $sql .= " AND name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }

        
        $current_user = (new Users($this->db))->get_by_id($_SESSION['id']);
        if ($current_user['role_id'] != 1) {
            $sql .= " AND created_by = ?";
            $params[] = $_SESSION['id'];
        }

        $result = $this->db->fetch($sql, $params);
        return $result['total'] ?? 0;
    }

 
    public function name_exists($name, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE name = ?";
        $params = [$name];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result ? true : false;
    }

 
    public function get_by_user($user_id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE created_by = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$user_id]);
    }


}
?>