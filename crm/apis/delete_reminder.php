<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$reminders_function = new Reminders($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/delete_reminder.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف یاداوری را ندارید"]);
    exit();
}

$leads_function = new Leads($db);
$reminder_function = new Reminders($db);
$persian = new PersianDate();


$remind_id = (int)($_POST['id'] ?? 0);

try {
    $remind_info = $reminder_function->get_by_id($remind_id);

    if (!$remind_info) {
        echo json_encode(['ok' => false, 'error' => 'پیدا نشد']);
        exit;
    }
    if ($remind_info['user_id'] !== $_SESSION['id']) {
        echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
        exit;
    }
    $reminder_function->delete($remind_id);
    echo json_encode(['ok' => true, 'message' => 'یادآوری حذف شد']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در حذف یادآوری']);
}
