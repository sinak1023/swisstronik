<?php
header('Content-Type: application/json; charset=utf-8');
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
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش نقش را ندارید"]);
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$new_permissions = $_POST['permissions'] ?? [];

if ($id <= 0) {
    echo json_encode(["ok" => false, "error" => "شناسه نقش معتبر نیست"]);
    exit();
}

if ($name === '') {
    echo json_encode(["ok" => false, "error" => "نام نقش الزامی است"]);
    exit();
}


if (!is_array($new_permissions)) {
    $new_permissions = [];
}

$roles_function = new Roles($db);
$role = $roles_function->get_by_id($id);
if (!$role) {
    echo json_encode(["ok" => false, "error" => "نقش یافت نشد"]);
    exit();
}
if($role['name'] != $name) {
    if(!$roles_function->name_exists($name)) {
        echo json_encode(['ok' => false, 'error' => 'نام نقش قبلاً ثبت شده']);
        exit;
    }
}

$roles_function->update($id, [
    'name' => $name,
    'permissions' => $new_permissions
]);

echo json_encode(["ok" => true]);
?>