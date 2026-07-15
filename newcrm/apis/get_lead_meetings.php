<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$leads_function    = new Leads($db);
$meetings_function = new Meetings($db);
$users_function    = new Users($db);
$persian = new PersianDate();

$lead_id = (int)($_GET['lead_id'] ?? 0);

$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;

if (!$is_admin && !$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

try {
    $rows = $meetings_function->get_by_lead($lead_id);
    $out = [];
    foreach ($rows as $m) {
        $ts = strtotime($m['meeting_datetime']);
        $out[] = [
            'id'            => (int)$m['id'],
            'title'         => $m['title'],
            'status'        => $m['status'],
            'result_note'   => $m['result_note'],
            'user_name'     => $m['user_name'],
            'meeting_datetime' => $m['meeting_datetime'],
            'date_jalali'   => $persian->jdate('Y/m/d', $ts),
            'time'          => $persian->jdate('H:i', $ts),
            'date_label'    => $persian->jdate('l j F Y', $ts),
            'is_past'       => $ts < time(),
            'parent_meeting_id' => $m['parent_meeting_id'] ? (int)$m['parent_meeting_id'] : null,
        ];
    }

    echo json_encode(['ok' => true, 'meetings' => $out]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در بارگذاری جلسات']);
}
