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
$root = 'apis/delete_role.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف نقش را ندارید"]);
    exit();
}

$id = (int)($_POST['id'] ?? 0);
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

if (in_array($id, [1])) { 
    echo json_encode(["ok" => false, "error" => "نقش سیستمی قابل حذف نیست"]);
    exit();
}

$roles_function->delete($id);

echo json_encode(["ok" => true]);
?>