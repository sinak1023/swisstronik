<?php

/**
 * مدیریت فروش‌ها. هر فروش به یک تراکنش موفق (زرین‌پال) و کارشناسی که آخرین‌بار
 * لید به او تخصیص داده شده، گره می‌خورد. فقط تراکنش‌های بعد از زمان تخصیص لید
 * به‌عنوان فروشِ آن کارشناس ثبت می‌شوند.
 */
class Sales
{
    private $db;
    private $table = 'sales';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /** ثبت فروش (در صورت نبودن تراکنش تکراری). برمی‌گرداند: id یا false اگر تکراری بود. */
    public function record($data)
    {
        // جلوگیری از ثبت تکراری بر اساس transaction_ref
        if (!empty($data['transaction_ref'])) {
            $exists = $this->db->fetch(
                "SELECT id FROM {$this->table} WHERE transaction_ref = ?",
                [$data['transaction_ref']]
            );
            if ($exists) return false;
        }

        try {
            $this->db->execute(
                "INSERT INTO {$this->table}
                 (lead_id, phone, phone_norm, user_id, project_id, amount, transaction_ref, transaction_date, payment_type, source, receipt_image, period, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['lead_id'] ?? null,
                    $data['phone'] ?? null,
                    Phone::normalize($data['phone'] ?? ''),
                    $data['user_id'] ?? null,
                    $data['project_id'] ?? null,
                    $data['amount'] ?? null,
                    $data['transaction_ref'] ?? null,
                    $data['transaction_date'] ?? null,
                    $data['payment_type'] ?? 'full',
                    $data['source'] ?? 'zarinpal',
                    $data['receipt_image'] ?? null,
                    $data['period'] ?? null,
                    $data['note'] ?? null,
                ]
            );
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            // در صورت رخداد همزمانی روی یکتایی transaction_ref
            return false;
        }
    }

    /** فهرست فیش‌های دستی ثبت‌شده برای یک لید */
    public function manual_for_lead($lead_id)
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE lead_id = ? AND source = 'manual' ORDER BY id DESC",
            [$lead_id]
        );
    }

    public function get_by_id($id)
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function set_payment_type($id, $type)
    {
        $type = in_array($type, ['full', 'installment']) ? $type : 'full';
        return $this->db->execute("UPDATE {$this->table} SET payment_type = ? WHERE id = ?", [$type, $id]);
    }

    /** آیا برای این تراکنش قبلاً فروش ثبت شده؟ */
    public function transaction_exists($ref)
    {
        $row = $this->db->fetch("SELECT id FROM {$this->table} WHERE transaction_ref = ?", [$ref]);
        return $row ? true : false;
    }

    /** آمار فروش روزانه یک کارشناس در یک بازه (کلید: تاریخ میلادی Y-m-d) */
    public function daily_for_user($user_id, $from, $to)
    {
        return $this->db->fetchAll(
            "SELECT DATE(transaction_date) as d, COUNT(*) as cnt, SUM(amount) as total,
                    SUM(payment_type='full') as full_cnt, SUM(payment_type='installment') as inst_cnt
             FROM {$this->table}
             WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
             GROUP BY DATE(transaction_date)",
            [$user_id, $from, $to]
        );
    }

    /** آمار فروش به تفکیک کاربر در یک بازه (برای مدیر) */
    public function summary_by_user($from, $to)
    {
        return $this->db->fetchAll(
            "SELECT s.user_id, u.name as user_name, COUNT(*) as cnt, SUM(s.amount) as total,
                    SUM(s.payment_type='full') as full_cnt, SUM(s.payment_type='installment') as inst_cnt
             FROM {$this->table} s
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.transaction_date BETWEEN ? AND ?
             GROUP BY s.user_id, u.name
             ORDER BY cnt DESC",
            [$from, $to]
        );
    }

    public function list_for_user($user_id, $from, $to)
    {
        return $this->db->fetchAll(
            "SELECT s.*, l.name as lead_name FROM {$this->table} s
             LEFT JOIN leads l ON s.lead_id = l.id
             WHERE s.user_id = ? AND s.transaction_date BETWEEN ? AND ?
             ORDER BY s.transaction_date DESC",
            [$user_id, $from, $to]
        );
    }
}
