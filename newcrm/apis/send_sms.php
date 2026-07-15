<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';
$projects_function = new Projects($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/send_sms.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ارسال پیامک را ندارید"]);
    exit();
}

$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$lead_id = (int)($_POST['lead_id'] ?? 0) ?: null;

if (empty($phone) || empty($message)) {
    echo json_encode(['ok' => false, 'error' => 'شماره یا متن پیام خالی است']);
    exit;
}

// آزادسازی قفل نشست قبل از ارسال کند پیامک تا درخواست‌های همزمان معطل نشوند
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$sms = new Sms($db);
$result = $sms->send($phone, $message);

// ثبت در لاگ پیام‌ها برای گزارش‌گیری
$activity = new ActivityLog($db);
$activity->logMessage(
    $_SESSION['id'],
    $lead_id,
    Phone::localFormat($phone),
    'sms',
    $message,
    $result['status'] === 'success' ? 'sent' : 'failed'
);

if ($result['status'] === 'success') {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => 'خطا در ارسال: ' . ($result['error'] ?? 'نامشخص')]);
}
