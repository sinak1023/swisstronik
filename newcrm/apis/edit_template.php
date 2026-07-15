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

$root = 'apis/edit_template.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش قالب پیام را ندارید"]);
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$category = trim($_POST['category'] ?? 'عمومی');
$project_id = $_POST['project_id'] === '' ? null : (int)$_POST['project_id'];

if ($id <= 0 || empty($title) || empty($content)) {
    echo json_encode(["ok" => false, "error" => "داده‌های ارسالی نامعتبر است"]);
    exit();
}

// چک کردن مالکیت قالب (فقط سازنده یا ادمین با دسترسی کامل)
$template = $templates_func->get_by_id($id);
if (!$template) {
    echo json_encode(["ok" => false, "error" => "قالب یافت نشد"]);
    exit();
}

// اگر کاربر ادمین نباشه، فقط اجازه ویرایش قالب‌های خودش رو داره
if ($template['user_id'] != $_SESSION["id"]) {
    // اگر نقش ادمین داره و دسترسی کامل داره، اجازه بده
    if (!in_array('pages/message_templates.php', $permissions)) {
        echo json_encode(["ok" => false, "error" => "شما اجازه ویرایش این قالب را ندارید"]);
        exit();
    }
}

try {
    $templates_func->update($id, [
        'title' => $title,
        'content' => $content,
        'category' => $category,
        'project_id' => $project_id
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "قالب با موفقیت ویرایش شد"
    ]);
} catch (Exception $e) {
    error_log("Edit template error: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "خطا در ویرایش قالب"]);
}