<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$projects_function = new Projects($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/edit_project.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش پروژه را ندارید"]);
    exit();
}

$leads_func = new Leads($db);
$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = $_POST['status'] ?? 'new';

// روش‌های تماس (اختیاری) — در صورت خالی بودن، شمارهٔ اصلی لید مبناست
$whatsapp_phone = trim($_POST['whatsapp_phone'] ?? '');
$telegram_phone = trim($_POST['telegram_phone'] ?? '');
$telegram_id    = trim($_POST['telegram_id'] ?? '');
$bale_phone     = trim($_POST['bale_phone'] ?? '');

$called = !empty($_POST['called']) ? 1 : 0;

if (!$id || !$name) {
    echo json_encode(["ok" => false, "error" => "داده نامعتبر"]);
    exit();
}

$lead = $leads_func->get_by_id($id);
if (!$lead || $lead['assigned_to'] != $_SESSION["id"]) {
    echo json_encode(["ok" => false, "error" => "لید متعلق به شما نیست"]);
    exit();
}

$update = [
    'name' => $name,
    'status' => $status,
    'whatsapp_phone' => $whatsapp_phone ?: null,
    'telegram_phone' => $telegram_phone ?: null,
    'telegram_id'    => $telegram_id ?: null,
    'bale_phone'     => $bale_phone ?: null,
];

// فقط در صورت ارسال صریح، یادداشت ستونی لید را به‌روزرسانی کن (جلوگیری از پاک‌شدن)
if (isset($_POST['notes'])) {
    $update['notes'] = trim($_POST['notes']);
}

if ($called) {
    $update['called'] = 1;
    $update['called_at'] = date('Y-m-d H:i:s');
}

$leads_func->update($id, $update);

// ثبت لاگ تماس بر اساس چک‌باکس «تماس گرفتم» — وضعیت انتخابی به‌عنوان نتیجهٔ تماس
if ($called) {
    (new ActivityLog($db))->logCall($_SESSION['id'], $id, $lead['phone'], 'normal', $status);
}

(new ActivityLog($db))->log($_SESSION['id'], 'edit_lead', 'lead', $id, ['status' => $status, 'called' => $called]);

echo json_encode(["ok" => true]);
