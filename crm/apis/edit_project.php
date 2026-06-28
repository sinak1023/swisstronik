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
$root = 'apis/edit_project.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش پروژه را ندارید"]);
    exit();
}

$id          = $_POST['id'] ?? 0;
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$id || !$name) {
    echo json_encode(['ok' => false, 'error' => 'داده‌های ضروری ناقص است']);
    exit;
}

$existing = $projects_function->get_by_id($id);
if (!$existing) {
    echo json_encode(['ok' => false, 'error' => 'پروژه یافت نشد']);
    exit;
}


$current_user = (new Users($db))->get_by_id($_SESSION["id"]);
if ($current_user['role_id'] != 1 && $existing['created_by'] != $_SESSION["id"]) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی ندارید']);
    exit;
}

if ($name !== $existing['name'] && $projects_function->name_exists($name)) {
    echo json_encode(['ok' => false, 'error' => 'نام پروژه قبلاً ثبت شده']);
    exit;
}

$update = [
    'name'        => $name,
    'description' => $description
];

$projects_function->update($id, $update);

echo json_encode(['ok' => true]);
?>