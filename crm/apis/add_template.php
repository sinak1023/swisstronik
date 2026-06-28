<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$category = $_POST['category'] ?? 'عمومی';
$project_id = (int)($_POST['project_id'] ?? 0);

if (empty($title) || empty($content)) {
    echo json_encode(["ok"=>false,"error"=>"عنوان و محتوا الزامی است"]);
    exit;
}

$templates = new MessageTemplates($db);
$id = $templates->add([
    'title' => $title,
    'content' => $content,
    'category' => $category,
    'user_id' => $_SESSION["id"],
    'project_id' => $project_id ?: null
]);

echo json_encode(["ok"=>true, "id"=>$id]);