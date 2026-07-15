<?php

/**
 * مدیریت جلسات کارشناس با لید.
 * وضعیت‌ها:
 *   scheduled   → هنوز نرسیده یا رسیده ولی تعیین‌تکلیف نشده (خاکستری)
 *   success     → جلسه موفق (سبز)
 *   failed      → جلسه ناموفق (قرمز)
 *   rescheduled → نیاز به جلسه مجدد (زرد) — یک جلسهٔ جدید به آن زنجیر می‌شود
 *
 * یادآوری ۱۵ دقیقه قبل: به کارشناس (پیامک + تلگرام) و به لید/مشتری (پیامک).
 */
class Meetings
{
    private $db;
    private $table = 'meetings';

    const STATUSES = ['scheduled', 'success', 'failed', 'rescheduled'];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /** ثبت جلسهٔ جدید. برمی‌گرداند: id جلسه */
    public function add($data)
    {
        $sql = "INSERT INTO {$this->table}
                (lead_id, phone, phone_norm, user_id, project_id, meeting_datetime, title, status, parent_meeting_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW(), NOW())";
        $params = [
            $data['lead_id'] ?? null,
            $data['phone'] ?? null,
            Phone::normalize($data['phone'] ?? ''),
            $data['user_id'],
            $data['project_id'] ?? null,
            $data['meeting_datetime'],
            $data['title'] ?? null,
            $data['parent_meeting_id'] ?? null,
        ];
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    public function get_by_id($id)
    {
        $sql = "SELECT m.*, l.name AS lead_name, l.assigned_to, p.name AS project_name, u.name AS user_name
                FROM {$this->table} m
                LEFT JOIN leads l ON m.lead_id = l.id
                LEFT JOIN projects p ON m.project_id = p.id
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /** به‌روزرسانی عمومی (فیلدهای دلخواه) */
    public function update($id, $data)
    {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            if ($key === 'id') continue;
            $fields[] = "`$key` = ?";
            $params[] = $value;
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . ", `updated_at` = NOW() WHERE id = ?";
        return $this->db->execute($sql, $params);
    }

    /** تعیین‌تکلیف جلسه: وضعیت + توضیحات */
    public function set_result($id, $status, $result_note = null)
    {
        if (!in_array($status, self::STATUSES)) $status = 'scheduled';
        return $this->update($id, [
            'status'      => $status,
            'result_note' => $result_note,
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete($id)
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }

    /** جلسات یک لید (برای تب مدیریت لید) — جدیدترین اول */
    public function get_by_lead($lead_id)
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.name AS user_name
             FROM {$this->table} m
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.lead_id = ?
             ORDER BY m.meeting_datetime DESC",
            [$lead_id]
        );
    }

    /**
     * جلسات یک کارشناس در یک بازهٔ زمانی (برای نمای هفتگی).
     * $from و $to به‌صورت 'Y-m-d H:i:s'.
     */
    public function get_for_user_between($user_id, $from, $to)
    {
        return $this->db->fetchAll(
            "SELECT m.*, l.name AS lead_name, l.phone AS lead_phone, p.name AS project_name
             FROM {$this->table} m
             LEFT JOIN leads l ON m.lead_id = l.id
             LEFT JOIN projects p ON m.project_id = p.id
             WHERE m.user_id = ? AND m.meeting_datetime BETWEEN ? AND ?
             ORDER BY m.meeting_datetime ASC",
            [$user_id, $from, $to]
        );
    }

    /** همهٔ جلسات در بازه (برای مدیر بدون فیلتر کارشناس) */
    public function get_all_between($from, $to)
    {
        return $this->db->fetchAll(
            "SELECT m.*, l.name AS lead_name, l.phone AS lead_phone, p.name AS project_name, u.name AS user_name
             FROM {$this->table} m
             LEFT JOIN leads l ON m.lead_id = l.id
             LEFT JOIN projects p ON m.project_id = p.id
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.meeting_datetime BETWEEN ? AND ?
             ORDER BY m.meeting_datetime ASC",
            [$from, $to]
        );
    }

    /**
     * جلسه‌هایی که تا ۱۵ دقیقهٔ آینده شروع می‌شوند و هنوز اعلان نشده‌اند (برای کرون).
     * $flag: 'expert' یا 'lead' — تعیین می‌کند کدام ستون pre_notified بررسی شود.
     */
    public function due_for_prenotify($flag, $now, $window)
    {
        $col = $flag === 'lead' ? 'lead_pre_notified' : 'expert_pre_notified';
        return $this->db->fetchAll(
            "SELECT m.*, l.name AS lead_name, l.phone AS lead_phone,
                    u.name AS user_name, u.phone AS user_phone, u.telegram_info, u.telegram_chat_id
             FROM {$this->table} m
             LEFT JOIN leads l ON m.lead_id = l.id
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.status = 'scheduled'
               AND m.`$col` = 0
               AND m.meeting_datetime > ?
               AND m.meeting_datetime <= ?
             LIMIT 200",
            [$now, $window]
        );
    }

    public function mark_prenotified($id, $flag)
    {
        $col = $flag === 'lead' ? 'lead_pre_notified' : 'expert_pre_notified';
        return $this->db->execute("UPDATE {$this->table} SET `$col` = 1 WHERE id = ?", [$id]);
    }

    /** آیا لید متعلق به کارشناس است؟ (برای چک دسترسی) */
    public function user_owns_lead($lead_id, $user_id)
    {
        $row = $this->db->fetch("SELECT id FROM leads WHERE id = ? AND assigned_to = ?", [$lead_id, $user_id]);
        return $row ? true : false;
    }
}
