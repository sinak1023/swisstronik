<?php
// cron/send_reminders.php
require_once '../config.php';

// فقط یک بار در دقیقه اجرا شود — جلوگیری از اجرای همزمان
$lockFile = __DIR__ . '/reminder_cron.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 120) {
    echo "Cron already running...\n";
    exit;
}
touch($lockFile);

try {
    $now = date('Y-m-d H:i:00'); // دقیقاً دقیقه جاری

    $sql = "SELECT r.*, u.telegram_info, u.name as admin_name 
            FROM reminders r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.reminder_datetime <= ? 
              AND r.is_done = 0 
            LIMIT 50"; // برای جلوگیری از لود زیاد

    $reminders = $db->fetchAll($sql, [$now]);

    foreach ($reminders as $reminder) {
        $lead_phone = $reminder['phone'];
        $admin_id = $reminder['user_id'];
        $text = trim($reminder['reminder_text']);
        $reminder_id = $reminder['id'];

        // اطلاعات تلگرام ادمین
        $telegram_info = !empty($reminder['telegram_info']) ? json_decode($reminder['telegram_info'], true) : null;
        $telegram_id = $telegram_info['id'] ?? null;

        // ارسال پیامک با IP Panel
        $sms_result = sendSMSviaIPPanel($lead_phone, $text);

        // وضعیت ارسال پیامک
        $sms_status = $sms_result['status'] ?? 'failed';
        $sms_msg_id = $sms_result['message_id'] ?? null;
        $sms_error = $sms_result['error'] ?? 'خطا در ارسال';

        // ثبت نتیجه در دیتابیس
        $db->execute(
            "UPDATE reminders SET 
                is_done = 1, 
                sms_status = ?, 
                sms_message_id = ?, 
                sent_at = NOW() 
             WHERE id = ?",
            [$sms_status, $sms_msg_id, $reminder_id]
        );

        // ارسال گزارش به تلگرام ادمین
        if ($telegram_id) {
            $telegram_text = "یادآوری شما ارسال شد\n\n" .
                "لید: <code>$lead_phone</code>\n" .
                "متن: <code>$text</code>\n" .
                "وضعیت پیامک: " . ($sms_status === 'success' ? 'ارسال موفق' : "ناموفق ($sms_error)") .
                "\nزمان: " . date('Y/m/d H:i');

            sendTelegramMessage($config['bot_token'], $telegram_id, $telegram_text);
        }
    }
} catch (Exception $e) {
    error_log("Reminder Cron Error: " . $e->getMessage());
}

unlink($lockFile);
echo "Reminder cron completed at " . date('Y-m-d H:i:s') . "\n";


// تابع ارسال پیامک با IP Panel
function sendSMSviaIPPanel($phone, $text)
{
    global $config;

    $username = $config['ip_panel']['username'];
    $password = $config['ip_panel']['password'];
    $number   = $config['ip_panel']['number'];

    if (empty($username) || empty($password) || empty($number)) {
        return ['status' => 'failed', 'error' => 'تنظیمات پنل پیامک ناقص است'];
    }

    $url = "https://ippanel.com/services.jspd";
    $params = [
        'uname' => $username,
        'pass'  => $password,
        'from'  => $number,
        'to'    => json_encode([$phone]),
        'msg'   => $text . "\n\nارسال خودکار توسط سیستم یادآوری",
        'op'    => 'send'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // پاسخ نمونه: 0,123456789012345678
    if ($httpCode == 200 && strpos($result, '0,') === 0) {
        $parts = explode(',', $result);
        return ['status' => 'success', 'message_id' => $parts[1] ?? null];
    } else {
        return ['status' => 'failed', 'error' => $result ?: 'خطای ناشناخته'];
    }
}

// تابع ارسال پیام به تلگرام
function sendTelegramMessage($botToken, $chat_id, $text)
{

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}
