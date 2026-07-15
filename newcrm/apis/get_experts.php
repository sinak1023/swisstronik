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
$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;

// دسترسی: مدیر یا دارندهٔ «گزارش‌های مدیریتی»
$permissions = json_decode($user['permissions'] ?? '[]', true) ?? [];
if (($user['role_id'] ?? 0) > 0) {
    $role = (new Roles($db))->get_by_id($user['role_id']);
    if ($role) $permissions = json_decode($role['permissions'] ?? '[]', true) ?? [];
}
if (!$is_admin && !in_array('pages/admin_reports.php', $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ندارید"]);
    exit();
}

$rows = $db->fetchAll("SELECT id, name, phone FROM users WHERE status = 1 ORDER BY name ASC");
$experts = array_map(function ($u) {
    return ['id' => (int)$u['id'], 'name' => $u['name'] ?? '', 'phone' => $u['phone'] ?? ''];
}, $rows);

echo json_encode(["ok" => true, "experts" => $experts]);
