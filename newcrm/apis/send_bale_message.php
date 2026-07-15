<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
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

// پیام‌رسان بله لینک «گفت‌وگو با شماره» عمومی مانند واتساپ ندارد؛
// بنابراین متن آماده برمی‌گردد تا در سمت کلاینت کپی شود و وب بله باز شود.
$clean_phone = Phone::intlFormat($phone);
$bale_url = "https://web.bale.ai/";

// ثبت در لاگ پیام‌ها برای گزارش‌گیری
(new ActivityLog($db))->logMessage($_SESSION['id'], $lead_id, $phone, 'bale', $message, 'sent');

echo json_encode([
    'ok' => true,
    'redirect_url' => $bale_url,
    'copy_text' => $message,
    'phone' => $clean_phone
]);
