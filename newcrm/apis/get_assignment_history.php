<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$leads_func = new Leads($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) $permissions = json_decode($role['permissions'], true) ?? [];
}
$root = 'apis/get_assignment_history.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده تاریخچه لیدها را ندارید"]);
    exit();
}


$phone = trim($_GET['phone'] ?? '');
if (!$phone) { echo json_encode(["ok" => false, "error" => "شماره الزامی است"]); exit(); }

$leads = new Leads($db);
$history = $leads->get_assignment_history($phone);

echo json_encode(["ok" => true, "history" => $history]);
?>