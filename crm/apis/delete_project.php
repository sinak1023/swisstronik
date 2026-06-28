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
$root = 'apis/delete_project.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف پروژه را ندارید"]);
    exit();
}

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'شناسه پروژه نامعتبر']);
    exit;
}

$project = $projects_function->get_by_id($id);
if (!$project) {
    echo json_encode(['ok' => false, 'error' => 'پروژه یافت نشد']);
    exit;
}


$current_user = (new Users($db))->get_by_id($_SESSION["id"]);
if ($current_user['role_id'] != 1 && $project['created_by'] != $_SESSION["id"]) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی ندارید']);
    exit;
}


$projects_function->delete($id);

echo json_encode(['ok' => true]);
?>