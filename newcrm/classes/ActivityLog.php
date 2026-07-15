<?php

/**
 * ثبت فعالیت کاربران، لاگ تماس‌ها و پیام‌ها — برای گزارش‌های ادمین.
 */
class ActivityLog
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /** ثبت یک فعالیت کلی (لاگین، ویرایش لید، افزودن یادداشت و ...) */
    public function log($user_id, $action, $entity = null, $entity_id = null, $meta = null)
    {
        try {
            $this->db->execute(
                "INSERT INTO activity_logs (user_id, action, entity, entity_id, meta) VALUES (?, ?, ?, ?, ?)",
                [$user_id, $action, $entity, $entity_id, is_array($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : $meta]
            );
        } catch (Exception $e) {
            error_log("activity_log error: " . $e->getMessage());
        }
    }

    /** ثبت تماس کارشناس با لید (بر اساس کانال: normal/whatsapp/telegram/bale) */
    public function logCall($user_id, $lead_id, $phone, $channel = 'normal', $status = null)
    {
        try {
            $this->db->execute(
                "INSERT INTO call_logs (lead_id, phone, user_id, channel, status) VALUES (?, ?, ?, ?, ?)",
                [$lead_id, $phone, $user_id, $channel, $status]
            );
        } catch (Exception $e) {
            error_log("call_log error: " . $e->getMessage());
        }
    }

    /** ثبت پیام ارسالی (sms/bale/telegram/whatsapp) */
    public function logMessage($user_id, $lead_id, $phone, $channel = 'sms', $message = null, $status = 'sent')
    {
        try {
            $this->db->execute(
                "INSERT INTO message_logs (lead_id, phone, user_id, channel, message, status) VALUES (?, ?, ?, ?, ?, ?)",
                [$lead_id, $phone, $user_id, $channel, $message, $status]
            );
        } catch (Exception $e) {
            error_log("message_log error: " . $e->getMessage());
        }
    }
}
