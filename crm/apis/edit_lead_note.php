<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$notes_function = new Notes($db);
$leads_function = new Leads($db);

$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}

$root = 'apis/edit_lead_note.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش یادداشت را ندارید"]);
    exit();
}

$note_id = (int)($_POST['note_id'] ?? 0);
$new_note = trim($_POST['note'] ?? '');

if (!$note_id || empty($new_note)) {
    echo json_encode(["ok" => false, "error" => "داده نامعتبر"]);
    exit();
}

$note = $notes_function->get_by_id($note_id);
if (!$note) {
    echo json_encode(["ok" => false, "error" => "یادداشت یافت نشد"]);
    exit();
}

if (!$leads_function->check_lead_permission($note['lead_id'], $_SESSION['id'])) {
    echo json_encode(["ok" => false, "error" => "دسترسی غیرمجاز"]);
    exit();
}

try {
    $notes_function->update($note_id, ['note' => $new_note]);
    echo json_encode(["ok" => true, "message" => "یادداشت با موفقیت ویرایش شد"]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => "خطا در ویرایش یادداشت"]);
}