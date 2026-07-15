<?php
class MessageTemplates
{
    private $db;
    private $table = 'message_templates';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function add($data)
    {
        $sql = "INSERT INTO {$this->table} (title, content, category, user_id, project_id) 
                VALUES (?, ?, ?, ?, ?)";
        $params = [
            $data['title'],
            $data['content'],
            $data['category'] ?? 'عمومی',
            $data['user_id'],
            $data['project_id'] ?? null
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId;
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
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

    public function get_all($user_id = null, $project_id = null, $search = '', $category = '')
    {
        $conditions = [];
        $params = [];

        if ($user_id) {
            $conditions[] = "user_id = ?";
            $params[] = $user_id;
        }
        if ($project_id) {
            $conditions[] = "(project_id = ? OR project_id IS NULL)";
            $params[] = $project_id;
        }

        if ($search !== '') {
            $conditions[] = "(title LIKE ? OR content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }


        if ($category !== '') {
            $conditions[] = "category = ?";
            $params[] = $category;
        }
        $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
        $sql = "SELECT * FROM {$this->table} $where ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    // جایگزینی شورت‌کدها
    public function parse($content, $lead)
    {
        $replacements = [
            '[name]'     => $lead['name'] ?? '',
            '[phone]'    => $lead['phone'] ?? '',
            '[project]'  => $lead['project_name'] ?? '',
            '[status]'   => $lead['status_fa'] ?? '',
            '[note]'     => $lead['last_note'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}