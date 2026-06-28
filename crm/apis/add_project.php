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
$root = 'apis/add_project.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی افزودن پروژه را ندارید"]);
    exit();
}

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$name) {
    echo json_encode(['ok' => false, 'error' => 'نام پروژه الزامی است']);
    exit;
}

if ($projects_function->name_exists($name)) {
    echo json_encode(['ok' => false, 'error' => 'نام پروژه قبلاً ثبت شده']);
    exit;
}

$data = [
    'name'        => $name,
    'description' => $description
];

$newId = $projects_function->add($data);

echo json_encode(['ok' => true, 'id' => $newId]);
?>