<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';
$order = json_decode(file_get_contents('php://input'), true)['order'] ?? [];
if (!is_array($order)) exit();

foreach ($order as $pos => $id) {
    $id = (int)$id;
    $pos = (int)$pos;
    $db->execute("UPDATE user_tasks SET position = ? WHERE id = ? AND user_id = ?", [$pos, $id, $_SESSION['id']]);
}