<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$title = trim($_POST['title'] ?? '');
if (!$title) exit(json_encode(['success' => false, 'message' => 'عنوان خالی است']));

// تسک‌ها روزانه‌اند؛ ترتیب فقط بین تسک‌های امروز محاسبه می‌شود
$position = $db->fetch("SELECT MAX(position) as max_pos FROM user_tasks WHERE user_id = ? AND task_date = CURDATE()", [$_SESSION['id']])['max_pos'] ?? 0;
$position += 1;

$db->execute("INSERT INTO user_tasks (user_id, title, position, task_date) VALUES (?, ?, ?, CURDATE())", [$_SESSION['id'], $title, $position]);

echo json_encode(['success' => true,'task_id'=>$db->lastInsertId()]);