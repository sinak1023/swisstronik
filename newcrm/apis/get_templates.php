<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();
require_once '../config.php';

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "Unauthorized"]);
    exit();
}

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
$root = 'pages/message_templates.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده کاربران را ندارید"]);
    exit();
}

$templates_func = new MessageTemplates($db);
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$project_id = (int)($_GET['project_id'] ?? 0);

$templates = $templates_func->get_all($_SESSION["id"], $project_id > 0 ? $project_id : null, $search,$category);

echo json_encode([
    "ok" => true,
    "templates" => $templates
]);