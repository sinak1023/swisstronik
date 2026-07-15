<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
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
$root = 'pages/projects.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده پروژه‌ها را ندارید"]);
    exit();
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = (int)($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;


$filters = [];

if (!empty($_GET['name'])) {
    $filters['name'] = trim($_GET['name']);
}


$list  = $projects_function->get_all_with_pagination($filters, $limit, $offset);
$total = $projects_function->get_total_count($filters);
$pages = (int)ceil($total / $limit);


$users_function = new Users($db);
$creatorMap = [];
foreach ($list as $p) {
    if (!isset($creatorMap[$p['created_by']])) {
        $user = $users_function->get_by_id($p['created_by']);
        $creatorMap[$p['created_by']] = $user['name'] ?? 'نامشخص';
    }
}


$result = [
    'ok'          => true,
    'projects'    => array_map(function ($p) use ($creatorMap) {
        return [
            'id'          => $p['id'],
            'name'        => $p['name'] ?? '',
            'description' => $p['description'] ?? '',
            'creator_name'=> $creatorMap[$p['created_by']],
            'created_at'  => $p['created_at'],
            'lead_count'  => (int)($p['lead_count'] ?? 0)
        ];
    }, $list),
    'total'       => $total,
    'total_pages' => $pages
];

echo json_encode($result);
?>