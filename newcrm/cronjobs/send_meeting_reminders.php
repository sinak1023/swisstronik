<?php
/**
 * کرون یادآوری جلسات — هر دقیقه اجرا شود:
 *   * * * * * php /path/to/crm/cronjobs/send_meeting_reminders.php >/dev/null 2>&1
 *
 * رفتار: ۱۵ دقیقه قبل از زمان هر جلسه:
 *   - به «کارشناس»: پیامک + نوتیف تلگرام
 *   - به «لید/مشتری»: پیامک یادآور جلسه
 * هر کدام فلگ جداگانه دارند (expert_pre_notified / lead_pre_notified) تا تکراری ارسال نشود.
 */
require_once __DIR__ . '/../config.php';

// جلوگیری از اجرای همزمان
$lockFile = __DIR__ . '/meeting_reminder_cron.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 110) {
    echo "Cron already running...\n";
    exit;
}
touch($lockFile);

$sms      = new Sms($db);
$telegram = new Telegram($db);
$persian  = new PersianDate();
$meetings = new Meetings($db);

try {
    $now    = date('Y-m-d H:i:s');
    $window = date('Y-m-d H:i:s', strtotime('+15 minutes +59 seconds'));

    // ===== ۱) یادآوری به کارشناس =====
    $expertDue = $meetings->due_for_prenotify('expert', $now, $window);
    foreach ($expertDue as $m) {
        $when     = $persian->jdate('H:i', strtotime($m['meeting_datetime']));
        $dayLabel = $persian->jdate('l j F', strtotime($m['meeting_datetime']));
        $leadName = $m['lead_name'] ?: ($m['lead_phone'] ?: 'مشتری');

        $msg = "یادآوری جلسه: تا ۱۵ دقیقهٔ دیگر جلسهٔ شما با «{$leadName}»"
            . ($m['lead_phone'] ? " (شمارهٔ {$m['lead_phone']})" : '')
            . " در {$dayLabel} ساعت {$when} است.";

        // پیامک به کارشناس (عادی یا پترن)
        if (!empty($m['user_phone'])) {
            $sms->notify(
                $m['user_phone'],
                'meeting_expert',
                ['lead' => $leadName, 'time' => $when, 'date' => $dayLabel],
                $msg
            );
        }

        // نوتیف تلگرام به کارشناس
        $chat_id = Telegram::resolveChatId($m);
        if ($chat_id) {
            $tg_text = "📅 <b>یادآوری جلسه</b>\n\n";
            $tg_text .= "👤 لید: {$leadName}\n";
            if (!empty($m['lead_phone'])) $tg_text .= "📞 شماره: <code>{$m['lead_phone']}</code>\n";
            if (!empty($m['title'])) $tg_text .= "📝 موضوع: {$m['title']}\n";
            $tg_text .= "🗓 {$dayLabel} — ساعت {$when}\n";
            $tg_text .= "⏰ تا ۱۵ دقیقهٔ دیگر";
            $telegram->sendMessage($chat_id, $tg_text);
        }

        $meetings->mark_prenotified($m['id'], 'expert');
    }

    // ===== ۲) یادآوری به لید/مشتری =====
    $leadDue = $meetings->due_for_prenotify('lead', $now, $window);
    foreach ($leadDue as $m) {
        $when     = $persian->jdate('H:i', strtotime($m['meeting_datetime']));
        $dayLabel = $persian->jdate('l j F', strtotime($m['meeting_datetime']));
        $leadPhone = $m['lead_phone'] ?: $m['phone'];

        if (!empty($leadPhone)) {
            $msg = "یادآوری جلسه: جلسهٔ شما در {$dayLabel} ساعت {$when} تا ۱۵ دقیقهٔ دیگر آغاز می‌شود.";
            $sms->notify(
                $leadPhone,
                'meeting_lead',
                ['time' => $when, 'date' => $dayLabel],
                $msg
            );
        }

        $meetings->mark_prenotified($m['id'], 'lead');
    }

} catch (Exception $e) {
    error_log("Meeting Reminder Cron Error: " . $e->getMessage());
}

@unlink($lockFile);
echo "Meeting reminder cron completed at " . date('Y-m-d H:i:s') . "\n";
