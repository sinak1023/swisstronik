<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$templates_func = new MessageTemplates($db);

$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}

$root = 'apis/delete_template.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف قالب پیام را ندارید"]);
    exit();
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(["ok" => false, "error" => "شناسه قالب نامعتبر است"]);
    exit();
}

// دریافت قالب
$template = $templates_func->get_by_id($id);
if (!$template) {
    echo json_encode(["ok" => false, "error" => "قالب یافت نشد"]);
    exit();
}

// چک کردن مالکیت یا دسترسی ادمین
if ($template['user_id'] != $_SESSION["id"]) {
    if (!in_array('pages/message_templates.php', $permissions)) {
        echo json_encode(["ok" => false, "error" => "شما اجازه حذف این قالب را ندارید"]);
        exit();
    }
}

try {
    $templates_func->delete($id);
    echo json_encode([
        "ok" => true,
        "message" => "قالب با موفقیت حذف شد"
    ]);
} catch (Exception $e) {
    error_log("Delete template error: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "خطا در حذف قالب"]);
}