<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

if (!isset($_SESSION['id'])) exit();

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');

if ($id && $title) {
    $db->execute("UPDATE user_tasks SET title = ? WHERE id = ? AND user_id = ?", [$title, $id, $_SESSION['id']]);
}
