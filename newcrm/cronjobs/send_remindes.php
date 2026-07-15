<?php
/**
 * کرون یادآوری‌ها — هر دقیقه اجرا شود:
 *   * * * * * php /path/to/crm/cronjobs/send_remindes.php
 *
 * رفتار: ۵ دقیقه قبل از زمان تنظیم‌شدهٔ هر یادآوری، یک پیامک و یک نوتیف تلگرام
 * به «کارشناس» (صاحب یادآوری) ارسال می‌شود.
 */
require_once __DIR__ . '/../config.php';

// جلوگیری از اجرای همزمان
$lockFile = __DIR__ . '/reminder_cron.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 110) {
    echo "Cron already running...\n";
    exit;
}
touch($lockFile);

$sms = new Sms($db);
$telegram = new Telegram($db);
$persian = new PersianDate();

try {
    $now = date('Y-m-d H:i:s');
    $window = date('Y-m-d H:i:s', strtotime('+5 minutes +59 seconds'));

    // یادآوری‌هایی که زمانشان در ۵ دقیقهٔ آینده است و هنوز اطلاع‌رسانی نشده‌اند
    $sql = "SELECT r.*, u.name as user_name, u.phone as user_phone, u.telegram_info, u.telegram_chat_id
            FROM reminders r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.pre_notified = 0
              AND r.reminder_datetime > ?
              AND r.reminder_datetime <= ?
            LIMIT 100";
    $reminders = $db->fetchAll($sql, [$now, $window]);

    foreach ($reminders as $reminder) {
        $text = trim($reminder['reminder_text']);
        $lead_phone = $reminder['phone'];
        $when = $persian->jdate('H:i', strtotime($reminder['reminder_datetime']));

        $msg = "یادآوری: تا ۵ دقیقهٔ دیگر زمان «{$text}»"
            . ($lead_phone ? " برای شمارهٔ {$lead_phone}" : '')
            . " (ساعت {$when}) است.";

        // پیامک به کارشناس (عادی یا پترن بر اساس تنظیمات)
        if (!empty($reminder['user_phone'])) {
            $sms->notify(
                $reminder['user_phone'],
                'reminder',
                ['text' => $text, 'time' => $when],
                $msg
            );
        }

        // نوتیف تلگرام به کارشناس
        $chat_id = Telegram::resolveChatId($reminder);
        if ($chat_id) {
            $tg_text = "⏰ <b>یادآوری</b>\n\n{$text}\n";
            if ($lead_phone) $tg_text .= "📞 لید: <code>{$lead_phone}</code>\n";
            $tg_text .= "🕒 ساعت: {$when}";
            $telegram->sendMessage($chat_id, $tg_text);
        }

        $db->execute("UPDATE reminders SET pre_notified = 1 WHERE id = ?", [$reminder['id']]);
    }

    // علامت‌گذاری یادآوری‌های گذشته به‌عنوان انجام‌شده (برای شمارش داشبورد)
    $db->execute("UPDATE reminders SET is_done = 1 WHERE is_done = 0 AND reminder_datetime < ?", [$now]);

} catch (Exception $e) {
    error_log("Reminder Cron Error: " . $e->getMessage());
}

@unlink($lockFile);
echo "Reminder cron completed at " . date('Y-m-d H:i:s') . "\n";
