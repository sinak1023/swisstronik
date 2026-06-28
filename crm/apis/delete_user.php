<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/delete_user.php'; 
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف کاربران را ندارید"]);
    exit();
}


$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'شناسه کاربر نامعتبر']);
    exit;
}


if ($id == $_SESSION['id']) {
    echo json_encode(['ok' => false, 'error' => 'نمی‌توانید خودتان را حذف کنید']);
    exit;
}

$users = new Users($db);
if (!$users->get_by_id($id)) {
    echo json_encode(['ok' => false, 'error' => 'کاربر یافت نشد']);
    exit;
}

$users->delete($id);

echo json_encode(['ok' => true]);