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
$meetings_function = new Meetings($db);
$persian = new PersianDate();

$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;

// مدیر می‌تواند دسترسی «گزارش‌های مدیریتی» داشته باشد تا کارشناس دلخواه را ببیند
$permissions = json_decode($user['permissions'] ?? '[]', true) ?? [];
if (($user['role_id'] ?? 0) > 0) {
    $role = (new Roles($db))->get_by_id($user['role_id']);
    if ($role) $permissions = json_decode($role['permissions'] ?? '[]', true) ?? [];
}
$can_view_all = $is_admin || in_array('pages/admin_reports.php', $permissions);

// انتخاب کارشناس: مدیر می‌تواند هر کارشناسی؛ کارشناس فقط خودش
$target_user_id = (int)($_GET['user_id'] ?? 0);
if (!$can_view_all || $target_user_id <= 0) {
    $target_user_id = (int)$_SESSION['id'];
}

// مبنای هفته: تاریخ شمسی ارسالی (شروع هفته) یا امروز
// week_start به‌صورت 'Y-m-d' میلادی از فرانت می‌آید (شنبهٔ آن هفته)
$week_start = trim($_GET['week_start'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
    // پیش‌فرض: شنبهٔ هفتهٔ جاری
    $today = strtotime(date('Y-m-d'));
    // شنبه = w:6 در PHP (0=یکشنبه..6=شنبه)؛ فاصله تا شنبهٔ قبل
    $w = (int)date('w', $today);          // 0..6
    $daysSinceSaturday = ($w + 1) % 7;    // شنبه→0، یکشنبه→1 ...
    $week_start = date('Y-m-d', $today - $daysSinceSaturday * 86400);
}

$from = $week_start . ' 00:00:00';
$to   = date('Y-m-d', strtotime($week_start . ' +6 days')) . ' 23:59:59';

if ($can_view_all && ($_GET['user_id'] ?? '') === 'all') {
    $rows = $meetings_function->get_all_between($from, $to);
} else {
    $rows = $meetings_function->get_for_user_between($target_user_id, $from, $to);
}

// گروه‌بندی بر اساس روز هفته (0=شنبه .. 6=جمعه)
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime($week_start . " +$i days"));
    $ts = strtotime($d);
    $days[$i] = [
        'date'        => $d,
        'jalali'      => $persian->jdate('Y/m/d', $ts),
        'weekday'     => $persian->jdate('l', $ts),
        'day_label'   => $persian->jdate('j F', $ts),
        'is_today'    => $d === date('Y-m-d'),
        'meetings'    => [],
    ];
}

foreach ($rows as $m) {
    $ts = strtotime($m['meeting_datetime']);
    $d = date('Y-m-d', $ts);
    $idx = null;
    foreach ($days as $i => $dd) {
        if ($dd['date'] === $d) { $idx = $i; break; }
    }
    if ($idx === null) continue;

    // «رسیده ولی تعیین‌تکلیف نشده» هم خاکستری (scheduled) می‌ماند
    $days[$idx]['meetings'][] = [
        'id'           => (int)$m['id'],
        'lead_id'      => $m['lead_id'] ? (int)$m['lead_id'] : null,
        'lead_name'    => $m['lead_name'] ?? '—',
        'lead_phone'   => $m['lead_phone'] ?? ($m['phone'] ?? ''),
        'project_name' => $m['project_name'] ?? '',
        'user_name'    => $m['user_name'] ?? '',
        'title'        => $m['title'],
        'status'       => $m['status'],
        'result_note'  => $m['result_note'],
        'time'         => $persian->jdate('H:i', $ts),
        'is_past'      => $ts < time(),
    ];
}

echo json_encode([
    'ok'         => true,
    'can_view_all' => $can_view_all,
    'week_start' => $week_start,
    'week_label' => $persian->jdate('j F Y', strtotime($week_start)) . ' تا ' . $persian->jdate('j F Y', strtotime($to)),
    'days'       => array_values($days),
]);
