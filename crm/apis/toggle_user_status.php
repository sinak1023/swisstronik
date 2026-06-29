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
$projects_function = new Projects($db);

$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/toggle_user_status.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی تغییر وضعیت کاربر را ندارید"]);
    exit();
}
$user = $users_function->get_by_id((int)$_POST['id']);

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'کاربر یافت نشد']);
    exit;
}
$new_status = $user['status'] == 1 ? 0 : 1;
$message = $new_status == 1 ? 'کاربر با موفقیت فعال شد' : 'کاربر با موفقیت غیرفعال شد';

if ($users_function->update($user['id'], ['status' => $new_status])) {
    echo json_encode(['ok' => true, 'message' => $message]);
} else {
    echo json_encode(['ok' => false, 'error' => 'خطا در تغییر وضعیت']);
}