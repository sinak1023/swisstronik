<?php

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$lead_id = (int)($_POST['lead_id'] ?? 0) ?: null;

if (empty($phone) || empty($message)) {
    echo json_encode(['ok' => false, 'error' => 'شماره یا متن خالی است']);
    exit;
}

$clean_phone = Phone::intlFormat($phone);

$encoded_message = urlencode($message);
$whatsapp_url = "https://wa.me/$clean_phone?text=$encoded_message";

// ثبت در لاگ پیام‌ها برای گزارش‌گیری
(new ActivityLog($db))->logMessage($_SESSION['id'], $lead_id, $phone, 'whatsapp', $message, 'sent');

echo json_encode([
    'ok' => true,
    'redirect_url' => $whatsapp_url
]);
