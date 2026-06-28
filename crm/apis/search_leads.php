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
$root = 'pages/leads.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده لیدها را ندارید"]);
    exit();
}

$leads = new Leads($db);
$filters = $_GET;

if (!empty($filters['project_id'])) {
    $filters['project_id'] = (int)$filters['project_id'];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 100;
$offset = ($page - 1) * $limit;

$results = $leads->search($filters, $limit, $offset);
$total = $leads->search_count($filters);
$pages = max(1, ceil($total / $limit));

echo json_encode([
    "ok" => true,
    "leads" => $results,
    "page" => $page,
    "pages" => $pages,
    "total" => $total
]);
?>