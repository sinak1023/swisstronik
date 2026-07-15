<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
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
$root = 'apis/edit_role.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده نقش را ندارید"]);
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["ok" => false, "error" => "شناسه نقش معتبر نیست"]);
    exit();
}

$roles_function = new Roles($db);
$role = $roles_function->get_by_id($id);
if (!$role) {
    echo json_encode(["ok" => false, "error" => "نقش یافت نشد"]);
    exit();
}

$role['permissions'] = json_decode($role['permissions'], true) ?? [];

echo json_encode(["ok" => true, "role" => $role]);
?>