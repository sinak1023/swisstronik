<?php


class Leads
{
    private $db;
    private $table = 'leads';

    public function __construct($db)
    {
        $this->db = $db;
    }

 
    public function add($data)
    {
        $sql = "INSERT INTO {$this->table}
                (`project_id`, `name`, `phone`, `phone_norm`, `notes`, `assigned_to`, `status`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $params = [
            $data['project_id'],
            $data['name'] ?? 'بینام',
            $data['phone'],
            Phone::normalize($data['phone']),
            $data['notes'] ?? null,
            $data['assigned_to'] ?? null,
            $data['status'] ?? 'new'
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

 
    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "`$key` = ?";
                $params[] = $value;
            }
        }
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(", ", $fields) . ", `updated_at` = NOW() WHERE id = ?";
        return $this->db->execute($sql, $params);
    }

 
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

 
    public function get_by_id($id)
    {
        $sql = "SELECT l.*, p.name as project_name, u.name as assignee_name 
                FROM {$this->table} l 
                LEFT JOIN projects p ON l.project_id = p.id 
                LEFT JOIN users u ON l.assigned_to = u.id 
                WHERE l.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

 
    public function search($filters = [], $limit = 100, $offset = 0)
    {
        $sql = "SELECT l.*, p.name as project_name, u.name as assignee_name 
                FROM {$this->table} l 
                LEFT JOIN projects p ON l.project_id = p.id 
                LEFT JOIN users u ON l.assigned_to = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND l.project_id = ?";
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['name'])) {
            $sql .= " AND l.name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }
        if (!empty($filters['phone'])) {
            $sql .= " AND l.phone LIKE ?";
            $params[] = "%{$filters['phone']}%";
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND l.created_at <= ?";
            $params[] = $filters['to_date'] . ' 23:59:59';
        }
        if (!empty($filters['assigned_status'])) {
            if ($filters['assigned_status'] === 'assigned') {
                $sql .= " AND l.assigned_to IS NOT NULL";
            } elseif ($filters['assigned_status'] === 'unassigned') {
                $sql .= " AND l.assigned_to IS NULL";
            }
        }

        $sql .= " ORDER BY l.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

 
    public function search_count($filters = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} l 
                LEFT JOIN projects p ON l.project_id = p.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND l.project_id = ?";
            $params[] = $filters['project_id'];
        }
        if (!empty($filters['name'])) {
            $sql .= " AND l.name LIKE ?";
            $params[] = "%{$filters['name']}%";
        }
        if (!empty($filters['phone'])) {
            $sql .= " AND l.phone LIKE ?";
            $params[] = "%{$filters['phone']}%";
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['from_date'])) {
            $sql .= " AND l.created_at >= ?";
            $params[] = $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $sql .= " AND l.created_at <= ?";
            $params[] = $filters['to_date'] . ' 23:59:59';
        }
        if (!empty($filters['assigned_status'])) {
            if ($filters['assigned_status'] === 'assigned') {
                $sql .= " AND l.assigned_to IS NOT NULL";
            } elseif ($filters['assigned_status'] === 'unassigned') {
                $sql .= " AND l.assigned_to IS NULL";
            }
        }

        $result = $this->db->fetch($sql, $params);
        return $result['total'] ?? 0;
    }


    public function get_count_by_project($project_id)
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE project_id = ?";
        $result = $this->db->fetch($sql, [$project_id]);
        return $result['total'] ?? 0;
    }


    public function assign($lead_ids, $user_id)
    {
        if (empty($lead_ids)) return false;

        $errors = [];

        try {
            foreach ($lead_ids as $lead_id) {
                $lead = $this->get_by_id($lead_id);
                if (!$lead) {
                    $errors[] = "لید $lead_id یافت نشد";
                    continue;
                }

                
                $this->update($lead_id, ['assigned_to' => $user_id]);

                
                $this->db->execute(
                    "INSERT INTO lead_numbers (lead_id, phone, phone_norm, assigned_to , assigned_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
                    [$lead_id, $lead['phone'], Phone::normalize($lead['phone']), $user_id, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")]
                );
            }

            if (!empty($errors)) {
                return ['errors' => $errors];
            }

            return true;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function unassign($lead_id)
    {
        try {
            $lead = $this->get_by_id($lead_id);
            if (!$lead) {
                return false;
            }

            
            $this->update($lead_id, ['assigned_to' => null]);

            
            $this->db->execute(
                "INSERT INTO lead_numbers (lead_id, phone, phone_norm, assigned_to , assigned_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
                [$lead_id, $lead['phone'], Phone::normalize($lead['phone']), null, date("Y-m-d H:i:s"), date("Y-m-d H:i:s")]
            );

            return true;
        } catch (Exception $e) {
            return false;
        }
    }


    public function assign_round_robin($project_id, $lead_ids, $user_ids)
    {
        if (empty($user_ids)) return false;

        $user_count = count($user_ids);
        foreach ($lead_ids as $index => $lead_id) {
            $user_id = $user_ids[$index % $user_count];  
            $this->assign([$lead_id], $user_id);
        }
    }

    /**
     * تاریخچه تخصیص بر اساس شماره نرمال‌شده — تا شماره‌ای که در یک کمپین با صفر و
     * در کمپین دیگر بدون صفر ثبت شده، یکسان تشخیص داده شود.
     */
    public function get_assignment_history($phone)
    {
        $norm = Phone::normalize($phone);
        $sql = "SELECT ln.*, u.name as user_name
            FROM lead_numbers ln
            JOIN users u ON ln.assigned_to = u.id
            WHERE ln.phone_norm = ? OR ln.phone = ?
            ORDER BY ln.assigned_at DESC";
        return $this->db->fetchAll($sql, [$norm, $phone]);
    }

    /**
     * یافتن سابقهٔ این شماره در پروژه‌ها/کمپین‌های دیگر (با مقایسهٔ نرمال‌شده).
     * شامل نام پروژه، آخرین کارشناسی که شماره دستش بوده و تاریخ‌ها.
     */
    public function get_cross_campaign_matches($phone, $exclude_project_id = null)
    {
        $norm = Phone::normalize($phone);
        if ($norm === '') return [];
        $sql = "SELECT l.id, l.phone, l.project_id, l.assigned_to, l.status, l.created_at,
                       p.name as project_name, u.name as assignee_name
                FROM {$this->table} l
                LEFT JOIN projects p ON l.project_id = p.id
                LEFT JOIN users u ON l.assigned_to = u.id
                WHERE l.phone_norm = ?";
        $params = [$norm];
        if ($exclude_project_id !== null) {
            $sql .= " AND l.project_id != ?";
            $params[] = $exclude_project_id;
        }
        $sql .= " ORDER BY l.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /** آخرین کارشناسی که این شماره (نرمال‌شده) دستش بوده */
    public function get_last_handler($phone)
    {
        $norm = Phone::normalize($phone);
        if ($norm === '') return null;
        $sql = "SELECT ln.*, u.name as user_name
                FROM lead_numbers ln
                JOIN users u ON ln.assigned_to = u.id
                WHERE ln.phone_norm = ? AND ln.assigned_to IS NOT NULL
                ORDER BY ln.assigned_at DESC LIMIT 1";
        return $this->db->fetch($sql, [$norm]);
    }


    public function import($project_id, $leads)
    {
        $inserted = 0;
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (project_id, name, phone, phone_norm, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()
        ");

        foreach ($leads as $lead) {
            $name = !empty($lead['name']) ? trim($lead['name']) : 'بینام';
            $phone = trim($lead['phone']);
            if (!$phone || strlen($phone) < 10 || strlen($phone) > 15) continue;

            $stmt->execute([$project_id, $name, $phone, Phone::normalize($phone)]);
            if ($stmt->rowCount()) $inserted++;
        }
        return $inserted;
    }


    public function phone_exists_in_project($project_id, $phone, $excludeId = null)
    {
        // مقایسه بر اساس شماره نرمال‌شده تا 0912... و 912... یکسان شمرده شوند
        $norm = Phone::normalize($phone);
        $sql = "SELECT id FROM {$this->table} WHERE project_id = ? AND (phone_norm = ? OR phone = ?)";
        $params = [$project_id, $norm, $phone];
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $result = $this->db->fetch($sql, $params);
        return $result ? true : false;
    }

 
    public function get_purchase_history($phone)
    {
        
        $api_url = "https://your-purchase-api.com/history?phone=" . urlencode($phone);
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $response = @file_get_contents($api_url, false, $context);
        return $response ? json_decode($response, true) : [];
    }

 
    public function get_assigned_to_user($user_id, $limit = 50)
    {
        $sql = "SELECT l.*, p.name as project_name 
                FROM {$this->table} l 
                JOIN projects p ON l.project_id = p.id 
                WHERE l.assigned_to = ? 
                ORDER BY l.updated_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$user_id, $limit]);
    }

 
    public function update_status($id, $status)
    {
        return $this->update($id, ['status' => $status]);
    }

 
    public function export_csv($filters = [])
    {
        $leads = $this->search($filters, 100000, 0); 
        $csv = "ID,نام,شماره,پروژه,کارشناس,وضعیت,یادداشت,تاریخ ایجاد\n";
        foreach ($leads as $lead) {
            $csv .= implode(',', [
                $lead['id'],
                '"' . str_replace('"', '""', $lead['name']) . '"',
                $lead['phone'],
                '"' . str_replace('"', '""', $lead['project_name'] ?? '') . '"',
                '"' . str_replace('"', '""', $lead['assignee_name'] ?? 'اختصاص نیافته') . '"',
                $lead['status'],
                '"' . str_replace('"', '""', $lead['notes'] ?? '') . '"',
                $lead['created_at']
            ]) . "\n";
        }
        return $csv;
    }

    function check_lead_permission($lead_id, $user_id)
    {
        $lead = $this->db->fetch("
        SELECT id FROM leads 
        WHERE id = ? AND assigned_to = ?
    ", [$lead_id, $user_id]);

        if (!$lead) {
            return false;
        }
        return true;
    }
}
