<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}


require_once '../config.php';

if (!isset($_SESSION['id'])) exit();

$id = (int)($_POST['id'] ?? 0);
$db->execute("DELETE FROM user_tasks WHERE id = ? AND user_id = ?", [$id, $_SESSION['id']]);

echo json_encode([
    "ok" => true,
    "message" => "تسک با موفقیت حذف شد"
]);
