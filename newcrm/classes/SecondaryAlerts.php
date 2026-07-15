<?php

/**
 * مدیریت هشدارِ «شمارهٔ ثانویه».
 *
 * سناریو: کارشناس A برای لیدِ خود یک شمارهٔ ثانویه (تلگرام/واتساپ/بله) وارد می‌کند
 * که همان شماره، شمارهٔ «اصلیِ» یک لید دیگر است که دستِ کارشناس B قرار دارد.
 * در این حالت:
 *   - یک رکورد هشدار برای «لید کارشناس B» ثبت می‌شود تا هنگام باز کردن آن، پاپ‌آپ ببیند.
 *   - یک‌بار به مدیر نوتیف تلگرام ارسال می‌شود (ضد اسپم).
 */
class SecondaryAlerts
{
    private $db;
    private $table = 'secondary_number_alerts';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * پس از ثبت/ویرایش شماره‌های ثانویهٔ یک لید، تداخل‌ها را بررسی و ثبت می‌کند.
     *
     * @param array  $sourceLead  لید مبدأ (که شماره‌های ثانویه رویش ثبت شده) — باید شامل id, assigned_to باشد
     * @param array  $secondaries ['telegram' => '0912...', 'whatsapp' => '...', 'bale' => '...']
     */
    public function checkAndRegister($sourceLead, $secondaries)
    {
        $leads = new Leads($this->db);
        $newAlerts = [];

        foreach ($secondaries as $channel => $phone) {
            $phone = trim((string)$phone);
            if ($phone === '') continue;

            // لیدهای اصلی متعلق به کارشناسان دیگر با همین شماره
            $matches = $leads->find_primary_owned_by_others(
                $phone,
                $sourceLead['id'],
                $sourceLead['assigned_to'] ?? 0
            );

            foreach ($matches as $target) {
                $norm = Phone::normalize($phone);
                // ثبت رکورد هشدار (یکتا بر اساس phone_norm + source_lead + target_lead)
                try {
                    $this->db->execute(
                        "INSERT INTO {$this->table}
                         (phone_norm, secondary_channel, source_lead_id, source_user_id, target_lead_id, target_user_id, manager_notified, resolved, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE secondary_channel = VALUES(secondary_channel), resolved = 0, updated_at = NOW()",
                        [
                            $norm,
                            $channel,
                            $sourceLead['id'],
                            $sourceLead['assigned_to'] ?? null,
                            $target['id'],
                            $target['assigned_to'],
                        ]
                    );

                    // آیا این جفت قبلاً به مدیر اطلاع داده شده؟
                    $existing = $this->db->fetch(
                        "SELECT id, manager_notified FROM {$this->table}
                         WHERE phone_norm = ? AND source_lead_id = ? AND target_lead_id = ?",
                        [$norm, $sourceLead['id'], $target['id']]
                    );
                    if ($existing && (int)$existing['manager_notified'] === 0) {
                        $newAlerts[] = [
                            'alert_id' => $existing['id'],
                            'channel'  => $channel,
                            'source'   => $sourceLead,
                            'target'   => $target,
                            'phone'    => $phone,
                        ];
                    }
                } catch (Exception $e) {
                    error_log('secondary alert insert error: ' . $e->getMessage());
                }
            }
        }

        // ارسال نوتیف مدیر (یک‌بار برای هر جفت)
        foreach ($newAlerts as $a) {
            $this->notifyManager($a);
            $this->db->execute(
                "UPDATE {$this->table} SET manager_notified = 1 WHERE id = ?",
                [$a['alert_id']]
            );
        }

        return count($newAlerts);
    }

    /** نوتیف تلگرام به مدیر دربارهٔ تداخل شمارهٔ ثانویه */
    private function notifyManager($alert)
    {
        $settings = new Settings($this->db);
        $managerChat = $settings->get('manager_telegram_id', '');
        if (empty($managerChat)) return;

        $channelLabel = [
            'telegram' => 'تلگرام',
            'whatsapp' => 'واتساپ',
            'bale'     => 'بله',
        ][$alert['channel']] ?? $alert['channel'];

        $srcUser = '';
        if (!empty($alert['source']['assigned_to'])) {
            $u = $this->db->fetch("SELECT name FROM users WHERE id = ?", [$alert['source']['assigned_to']]);
            $srcUser = $u['name'] ?? '';
        }

        $text = "⚠️ <b>تداخل شمارهٔ ثانویه</b>\n\n";
        $text .= "کارشناس «" . ($srcUser ?: '—') . "» شمارهٔ {$channelLabel} زیر را برای لید خود ثبت کرد:\n";
        $text .= "📞 <code>{$alert['phone']}</code>\n\n";
        $text .= "این شماره، شمارهٔ <b>اصلی</b> یک لید دیگر است که دستِ کارشناس «"
              . ($alert['target']['assignee_name'] ?? '—') . "» قرار دارد";
        if (!empty($alert['target']['project_name'])) {
            $text .= " (پروژهٔ «{$alert['target']['project_name']}»)";
        }
        $text .= ".\nلطفاً بررسی کنید.";

        (new Telegram($this->db))->sendMessage($managerChat, $text);
    }

    /**
     * هشدارهای فعالِ یک لید (برای پاپ‌آپ کارشناس هنگام باز کردن لید).
     * یعنی: این لید (به‌عنوان لید اصلی) شماره‌اش به‌عنوان ثانویه توسط کارشناس دیگری ثبت شده.
     */
    public function get_active_for_lead($lead_id)
    {
        return $this->db->fetchAll(
            "SELECT a.*, su.name AS source_user_name, sl.project_id AS source_project_id,
                    sp.name AS source_project_name
             FROM {$this->table} a
             LEFT JOIN users su ON a.source_user_id = su.id
             LEFT JOIN leads sl ON a.source_lead_id = sl.id
             LEFT JOIN projects sp ON sl.project_id = sp.id
             WHERE a.target_lead_id = ? AND a.resolved = 0
             ORDER BY a.created_at DESC",
            [$lead_id]
        );
    }
}
