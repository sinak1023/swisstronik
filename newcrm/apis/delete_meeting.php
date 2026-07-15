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

$meeting_id = (int)($_POST['meeting_id'] ?? 0);
if (!$meeting_id) {
    echo json_encode(['ok' => false, 'error' => 'شناسهٔ جلسه نامعتبر']);
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

$meetings_function->delete($meeting_id);

(new ActivityLog($db))->log($_SESSION['id'], 'delete_meeting', 'meeting', $meeting_id, null);

echo json_encode(['ok' => true]);
