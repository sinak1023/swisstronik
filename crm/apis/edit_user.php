<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/edit_user.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش نقش را ندارید"]);
    exit();
}

$id          = $_POST['id'] ?? 0;
$name        = trim($_POST['name'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$email       = trim($_POST['email'] ?? '');
$role_id     = $_POST['role_id'] ?? '';

if (!$id || !$name || !$phone) {
    echo json_encode(['ok' => false, 'error' => 'داده‌های ضروری ناقص است']);
    exit;
}

$existing = $users_function->get_by_id($id);
if (!$existing) {
    echo json_encode(['ok' => false, 'error' => 'کاربر یافت نشد']);
    exit;
}

if ($phone !== $existing['phone'] && !$users_function->phone_exists($phone)) {
    echo json_encode(['ok' => false, 'error' => 'شماره تلفن قبلاً ثبت شده']);
    exit;
}
if ( $email !== $existing['email'] && !$users_function->email_exists($email)) {
    echo json_encode(['ok' => false, 'error' => 'ایمیل قبلاً ثبت شده']);
    exit;
}

$update = [
    'name'        => $name,
    'phone'       => $phone,
    'email'       => $email,
    'role_id'     => $role_id ?: null
];

$users_function->update($id, $update);

echo json_encode(['ok' => true]);