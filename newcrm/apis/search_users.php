<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$roles = new Roles($db);
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
$root = 'pages/users.php'; 
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده کاربران را ندارید"]);
    exit();
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = (int)($_GET['limit'] ?? 100);
$offset = ($page - 1) * $limit;


$filters = [];

if (!empty($_GET['name'])) {
    $filters['name'] = trim($_GET['name']);
}
if (!empty($_GET['phone'])) {
    $filters['phone'] = trim($_GET['phone']);
}
if (!empty($_GET['email'])) {
    $filters['email'] = trim($_GET['email']);
}
if (!empty($_GET['role_id'])) {
    $filters['role_id'] = (int)$_GET['role_id'];
}


$list  = $users_function->get_all_with_pagination($filters, $limit, $offset);
$total = $users_function->get_total_count($filters);
$pages = (int)ceil($total / $limit);


$roleMap = [];
foreach ($roles->get_all() as $r) {
    $roleMap[$r['id']] = $r['name'];
}


$result = [
    'ok'          => true,
    'users'       => array_map(function ($u) use ($roleMap) {
        return [
            'id'          => $u['id'],
            'name'        => $u['name'] ?? '',
            'family_name' => $u['family_name'] ?? '',
            'phone'       => $u['phone'] ?? '',
            'email'       => $u['email'] ?? '',
            'role_id'     => $u['role_id'] ?? '',
            'status'     => $u['status'] ?? '',
        ];
    }, $list),
    'total'       => $total,
    'total_pages' => $pages
];

echo json_encode($result);