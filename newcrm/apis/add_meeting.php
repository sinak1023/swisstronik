<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function   = new Users($db);
$leads_function   = new Leads($db);
$meetings_function = new Meetings($db);

$lead_id       = (int)($_POST['lead_id'] ?? 0);
$meeting_date  = trim($_POST['meeting_date'] ?? '');   // شمسی: 1403/08/20
$meeting_time  = trim($_POST['meeting_time'] ?? '');   // 14:30
$title         = trim($_POST['title'] ?? '');
$parent_id     = (int)($_POST['parent_meeting_id'] ?? 0) ?: null;

if (empty($meeting_date) || empty($meeting_time)) {
    echo json_encode(['ok' => false, 'error' => 'تاریخ و ساعت جلسه الزامی است']);
    exit;
}

// فقط کارشناس صاحب لید (یا مدیر) می‌تواند جلسه ثبت کند
$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;
if (!$is_admin && !$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(['ok' => false, 'error' => 'این لید متعلق به شما نیست']);
    exit;
}

$lead = $leads_function->get_by_id($lead_id);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'لید یافت نشد']);
    exit;
}

// اعتبارسنجی و تبدیل تاریخ شمسی به میلادی
$persian = new PersianDate();
$parts = preg_split('/[\/\-]/', $persian->tr_num($meeting_date));
if (count($parts) !== 3 || (int)$parts[0] < 1300) {
    echo json_encode(['ok' => false, 'error' => 'فرمت تاریخ نامعتبر است']);
    exit;
}
if (!preg_match('/^\d{1,2}:\d{2}$/', $persian->tr_num($meeting_time))) {
    echo json_encode(['ok' => false, 'error' => 'فرمت ساعت نامعتبر است']);
    exit;
}

$g = $persian->jalali_to_gregorian((int)$parts[0], (int)$parts[1], (int)$parts[2]);
$meeting_datetime = sprintf('%04d-%02d-%02d %s:00', $g[0], $g[1], $g[2], $persian->tr_num($meeting_time));

// جلسه در گذشته پذیرفته نمی‌شود (مگر مدیر)
if (!$is_admin && strtotime($meeting_datetime) < time() - 60) {
    echo json_encode(['ok' => false, 'error' => 'زمان جلسه نمی‌تواند در گذشته باشد']);
    exit;
}

// اگر این جلسه ادامهٔ یک جلسهٔ قبلی است، صحت مالکیت جلسهٔ والد را بررسی کن
if ($parent_id) {
    $parent = $meetings_function->get_by_id($parent_id);
    if (!$parent || ($parent['lead_id'] != $lead_id)) {
        $parent_id = null;
    }
}

$meeting_id = $meetings_function->add([
    'lead_id'           => $lead_id,
    'phone'             => $lead['phone'],
    'user_id'           => $lead['assigned_to'] ?: $_SESSION['id'],
    'project_id'        => $lead['project_id'] ?? null,
    'meeting_datetime'  => $meeting_datetime,
    'title'             => $title ?: null,
    'parent_meeting_id' => $parent_id,
]);

if (!$meeting_id) {
    echo json_encode(['ok' => false, 'error' => 'ثبت جلسه ناموفق بود']);
    exit;
}

(new ActivityLog($db))->log($_SESSION['id'], 'add_meeting', 'lead', $lead_id, [
    'meeting_id' => $meeting_id,
    'datetime'   => $meeting_datetime,
]);

echo json_encode([
    'ok' => true,
    'message' => 'جلسه با موفقیت ثبت شد',
    'meeting_id' => $meeting_id,
]);
