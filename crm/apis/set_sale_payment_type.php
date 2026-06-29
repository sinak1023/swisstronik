<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$sale_id = (int)($_POST['sale_id'] ?? 0);
$type = $_POST['payment_type'] ?? 'full';
if (!in_array($type, ['full', 'installment'])) $type = 'full';

if (!$sale_id) {
    echo json_encode(["ok" => false, "error" => "شناسهٔ فروش نامعتبر"]);
    exit();
}

$sales = new Sales($db);
$sale = $sales->get_by_id($sale_id);
if (!$sale) {
    echo json_encode(["ok" => false, "error" => "فروش یافت نشد"]);
    exit();
}

// فقط کارشناس صاحب فروش یا مدیر (role_id=1) اجازهٔ تغییر دارد
$user = (new Users($db))->get_by_id($_SESSION['id']);
if ($sale['user_id'] != $_SESSION['id'] && (int)($user['role_id'] ?? 0) !== 1) {
    echo json_encode(["ok" => false, "error" => "دسترسی غیرمجاز"]);
    exit();
}

$sales->set_payment_type($sale_id, $type);
echo json_encode(["ok" => true]);
