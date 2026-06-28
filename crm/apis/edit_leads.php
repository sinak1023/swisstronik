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

$leads_func = new Leads($db);
$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = $_POST['status'] ?? 'new';
$notes = trim($_POST['notes'] ?? '');

if (!$id || !$name) {
    echo json_encode(["ok" => false, "error" => "داده نامعتبر"]);
    exit();
}

$lead = $leads_func->get_by_id($id);
if (!$lead || $lead['assigned_to'] != $_SESSION["id"]) {
    echo json_encode(["ok" => false, "error" => "لید متعلق به شما نیست"]);
    exit();
}

$result = $leads_func->update($id, [
    'name' => $name,
    'status' => $status,
    'notes' => $notes
]);

echo json_encode(["ok" => true]);
?>