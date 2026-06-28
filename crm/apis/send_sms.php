<?php

header('Content-Type: application/json; charset=utf-8');
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

if (empty($phone) || empty($message)) {
    echo json_encode(['ok' => false, 'error' => 'شماره یا متن پیام خالی است']);
    exit;
}

$ip_panel_username = $config['ippanel']['username'];
$ip_panel_password = $config['ippanel']['password'];
$ip_panel_number   = $config['ippanel']['number'];

if (substr($phone, 0, 1) !== '0') {
    $phone = '0' . ltrim($phone, '+98');
}

$url = "https://ippanel.com/services.jspd";

$param = [
    "uname"     => $ip_panel_username,
    "pass"      => $ip_panel_password,
    "from"      => $ip_panel_number,
    "message"   => $message,
    "to"        => json_encode([$phone]),
    "op"        => "send"
];

$handler = curl_init($url);
curl_setopt($handler, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($handler, CURLOPT_POSTFIELDS, $param);
curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handler, CURLOPT_TIMEOUT, 30);
curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($handler);

if (curl_errno($handler)) {
    echo json_encode(['ok' => false, 'error' => 'خطا در اتصال به سرور پیامک']);
    curl_close($handler);
    exit;
}

curl_close($handler);

$parts = explode(',', $response);
$status_code = $parts[0] ?? '';

if ($status_code === '0') {
    echo json_encode(['ok' => true]);
} else {
    $error_msg = $parts[1] ?? 'خطای ناشناخته در ارسال پیامک';
    echo json_encode(['ok' => false, 'error' => 'خطا در ارسال: ' . $error_msg]);
}
