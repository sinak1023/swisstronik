<?php

class Users
{
    private $db;
    private $table = 'users';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function add($data)
    {
        $sql = "INSERT INTO {$this->table} (`name`, `email`, `phone`, `password`, `created_at`, `updated_at`, `role_id`, `permissions`, `telegram_chat_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$data['name'] ?? '', $data['email'] ?? null, $data['phone'] ?? null, $data['password'] ?? null,  date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $data['role_id'] ?? null, $data['permissions'] ?? null, $data['telegram_chat_id'] ?? null];
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
        $this->update($id, ['status' => 0]);
    }

    public function get_by_id($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }


    public function get_user($username)
    {
        $sql = "SELECT * FROM {$this->table} WHERE (`email` = ? OR `phone` = ?)";
        return $this->db->fetch($sql, [$username, $username]);
    }

    public function get_all_with_pagination($filters, $limit, $offset)
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        foreach ($filters as $filter => $search) {
            switch ($filter) {
                case 'name':
                    $sql .= " AND (name LIKE ?)";
                    $params[] = "%$search%";
                    break;
                case 'phone':
                    $sql .= " AND phone LIKE ?";
                    $params[] = "%$search%";
                    break;
                case 'email':
                    $sql .= " AND email LIKE ?";
                    $params[] = "%$search%";
                    break;
            }
        }

        $sql .= " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        return $this->db->fetchAll($sql, $params);
    }

    public function get_total_count($filters)
    {
        $sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
        $params = [];

        foreach ($filters as $filter => $search) {
            switch ($filter) {
                case 'name':
                    $sql .= " AND (name LIKE ?)";
                    $params[] = "%$search%";
                    break;
                case 'phone':
                    $sql .= " AND phone LIKE ?";
                    $params[] = "%$search%";
                    break;
                case 'email':
                    $sql .= " AND email LIKE ?";
                    $params[] = "%$search%";
                    break;
            }
        }

        $result = $this->db->fetch($sql, $params);
        return $result['total'];
    }

    public function get_all()
    {
        $sql = "SELECT * FROM users ORDER BY id DESC";
        return $this->db->fetchAll($sql);
    }

    public function phone_exists($phone, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE (`phone` = ?)";
        $user = $this->db->fetch($sql, [$phone]);
        if ($user && !empty($user['id'])) {
            return false;
        }
        return true;
    }

    public function email_exists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE (`email` = ?)";
        $user = $this->db->fetch($sql, [$email]);
        if ($user && !empty($user['id'])) {
            return false;
        }
        return true;
    }

    public function reset_login_limits($id)
{
    $sql = "UPDATE {$this->table} SET refus_try = 0, limit_login = NULL, refus_try_reset = 0, limit_reset = NULL WHERE id = ?";
    return $this->db->execute($sql, [$id]);
}
}
