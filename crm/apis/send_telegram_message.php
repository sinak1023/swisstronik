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

$clean_phone = ltrim(preg_replace('/\D/', '', $phone), '0');
if (strlen($clean_phone) === 10) {
    $clean_phone = '98' . $clean_phone; 
}

$encoded_message = urlencode($message);
$telegram_url = "https://t.me/+$clean_phone?text=$encoded_message";

echo json_encode([
    'ok' => true,
    'redirect_url' => $telegram_url
]);
?>