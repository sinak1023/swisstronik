<?php
header(header: 'Content-Type: application/json; charset=utf-8');
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
$root = 'apis/add_user.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی افزودن کاربران را ندارید"]);
    exit();
}


$name        = trim($_POST['name'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$email       = trim($_POST['email'] ?? '');
$password    = $_POST['password'] ?? '';
$role_id     = $_POST['role_id'] ?? '';

if (!$name || !$phone) {
    echo json_encode(['ok' => false, 'error' => 'نام و شماره تلفن الزامی است']);
    exit;
}


if (!$users_function->phone_exists($phone)) {
    echo json_encode(['ok' => false, 'error' => 'شماره تلفن قبلاً ثبت شده']);
    exit;
}
if (!$users_function->email_exists($email)) {
    echo json_encode(['ok' => false, 'error' => 'ایمیل قبلاً ثبت شده']);
    exit;
}

$data = [
    'name'        => $name,
    'phone'       => $phone,
    'email'       => $email,
    'role_id'     => $role_id ?: null
];

if ($password) {
    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
}

if ($role_id == null || $role_id == 0) {
    echo json_encode(['ok' => false, 'error' => 'نقش کاربر را انتخاب نمایید']);
    exit;
}

$newId = $users_function->add($data);

echo json_encode(['ok' => true, 'id' => $newId]);
