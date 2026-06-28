<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$id = (int)($_POST['id'] ?? 0);
$completed = (boolean)($_POST['completed'] ?? 0);
error_log(json_encode($_POST));
$db->execute("UPDATE user_tasks SET is_completed = ? WHERE id = ? AND user_id = ?", [$completed, $id, $_SESSION['id']]);
    echo json_encode(['ok' => true, 'message' => "تسک کامل شد"]);
