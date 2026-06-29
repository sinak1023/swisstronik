<?php
header(header: 'Content-Type: application/json; charset=utf-8');
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
$root = 'apis/add_role.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی افزودن نقش را ندارید"]);
    exit();
}

$name = trim($_POST['name'] ?? '');
$new_permissions = $_POST['permissions'] ?? [];

if ($name === '') {
    echo json_encode(["ok" => false, "error" => "نام نقش الزامی است"]);
    exit();
}

if(!$roles_function->name_exists($name)) {
    echo json_encode(['ok' => false, 'error' => 'نام نقش قبلاً ثبت شده']);
    exit;
}

if (!is_array($new_permissions)) {
    $new_permissions = [];
}

$roles_function = new Roles($db);
$role_id = $roles_function->add([
    'name' => $name,
    'permissions' => $new_permissions
]);


echo json_encode([
    "ok" => true, 
    "role_id" => $role_id,
    "name" => $name,
    "permissions" => $new_permissions
]);
