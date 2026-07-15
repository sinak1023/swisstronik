<?php
require_once __DIR__ . '/../boot_session.php';
session_start();
require_once '../config.php';

if (!isset($_SESSION['id']) || $_SESSION['id'] != $_POST['user_id']) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

$users = new Users($db);
$users->update($_POST['user_id'], ['telegram_info' => null]);

echo json_encode(['success' => true]);