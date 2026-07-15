<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function    = new Users($db);
$leads_function    = new Leads($db);
$meetings_function = new Meetings($db);

$meeting_id  = (int)($_POST['meeting_id'] ?? 0);
$status      = $_POST['status'] ?? '';
$result_note = trim($_POST['result_note'] ?? '');

// در صورت انتخاب «ناموفق» یا «جلسه مجدد»، امکان ثبت زمان جلسهٔ جدید (اختیاری برای failed، معمول برای rescheduled)
$new_date = trim($_POST['new_meeting_date'] ?? '');
$new_time = trim($_POST['new_meeting_time'] ?? '');

if (!in_array($status, ['success', 'failed', 'rescheduled'])) {
    echo json_encode(['ok' => false, 'error' => 'وضعیت جلسه نامعتبر است']);
    exit;
}

$meeting = $meetings_function->get_by_id($meeting_id);
if (!$meeting) {
    echo json_encode(['ok' => false, 'error' => 'جلسه یافت نشد']);
    exit;
}

// فقط کارشناس صاحب جلسه یا مدیر
$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;
if (!$is_admin && $meeting['user_id'] != $_SESSION['id']) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

// اگر وضعیت نیازمند جلسهٔ جدید است، تاریخ/ساعت را بررسی و تبدیل کن
$new_meeting_id = null;
$new_meeting_datetime = null;
if ($new_date !== '' && $new_time !== '') {
    $persian = new PersianDate();
    $parts = preg_split('/[\/\-]/', $persian->tr_num($new_date));
    if (count($parts) !== 3 || (int)$parts[0] < 1300) {
        echo json_encode(['ok' => false, 'error' => 'فرمت تاریخ جلسهٔ جدید نامعتبر است']);
        exit;
    }
    if (!preg_match('/^\d{1,2}:\d{2}$/', $persian->tr_num($new_time))) {
        echo json_encode(['ok' => false, 'error' => 'فرمت ساعت جلسهٔ جدید نامعتبر است']);
        exit;
    }
    $g = $persian->jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    $new_meeting_datetime = sprintf('%04d-%02d-%02d %s:00', $g[0], $g[1], $g[2], $persian->tr_num($new_time));

    if (strtotime($new_meeting_datetime) < time() - 60) {
        echo json_encode(['ok' => false, 'error' => 'زمان جلسهٔ جدید نمی‌تواند در گذشته باشد']);
        exit;
    }
}

// وضعیت «جلسه مجدد» بدون تعیین زمان جدید منطقی نیست
if ($status === 'rescheduled' && !$new_meeting_datetime) {
    echo json_encode(['ok' => false, 'error' => 'برای «نیاز به جلسه مجدد» باید زمان جلسهٔ جدید را تعیین کنید']);
    exit;
}

// ثبت نتیجهٔ جلسهٔ فعلی
$meetings_function->set_result($meeting_id, $status, $result_note ?: null);

// در صورت نیاز، جلسهٔ جدید (زنجیر شده به جلسهٔ فعلی)
if ($new_meeting_datetime) {
    $new_meeting_id = $meetings_function->add([
        'lead_id'           => $meeting['lead_id'],
        'phone'             => $meeting['phone'],
        'user_id'           => $meeting['user_id'],
        'project_id'        => $meeting['project_id'],
        'meeting_datetime'  => $new_meeting_datetime,
        'title'             => $meeting['title'],
        'parent_meeting_id' => $meeting_id,
    ]);
}

(new ActivityLog($db))->log($_SESSION['id'], 'meeting_result', 'meeting', $meeting_id, [
    'status'         => $status,
    'new_meeting_id' => $new_meeting_id,
]);

echo json_encode([
    'ok' => true,
    'message' => 'نتیجهٔ جلسه ثبت شد',
    'new_meeting_id' => $new_meeting_id,
]);
