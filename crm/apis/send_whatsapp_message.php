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

if (empty($phone) || empty($message)) {
    echo json_encode(['ok' => false, 'error' => 'شماره یا متن خالی است']);
    exit;
}

$clean_phone = preg_replace('/\D/', '', $phone);
if (substr($clean_phone, 0, 1) === '0') {
    $clean_phone = '98' . substr($clean_phone, 1);
}

$encoded_message = urlencode($message);
$whatsapp_url = "https://wa.me/$clean_phone?text=$encoded_message";

echo json_encode([
    'ok' => true,
    'redirect_url' => $whatsapp_url
]);
?>