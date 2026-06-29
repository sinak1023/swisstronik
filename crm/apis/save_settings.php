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
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
if (!in_array('apis/save_settings.php', $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ذخیره تنظیمات را ندارید"]);
    exit();
}

$settings = new Settings($db);

// فقط کلیدهای مجاز قابل ذخیره‌اند
$allowed = [
    'active_sms_panel',
    'ippanel_username', 'ippanel_password', 'ippanel_number', 'ippanel_apikey',
    'melipayamak_username', 'melipayamak_password', 'melipayamak_number',
    'bot_token',
    'manager_phone', 'manager_telegram_id',
    'sms_mode',
    'pattern_reminder', 'pattern_sale_expert', 'pattern_sale_manager',
    'zarinpal_token', 'zarinpal_terminal_id',
];

$saved = [];
foreach ($allowed as $key) {
    if (array_key_exists($key, $_POST)) {
        $val = trim($_POST[$key]);
        if ($key === 'active_sms_panel' && !in_array($val, ['ippanel', 'melipayamak'])) {
            $val = 'ippanel';
        }
        if ($key === 'sms_mode' && !in_array($val, ['normal', 'pattern'])) {
            $val = 'normal';
        }
        $settings->set($key, $val);
        $saved[] = $key;
    }
}

(new ActivityLog($db))->log($_SESSION['id'], 'save_settings', 'settings', null, $saved);

echo json_encode(["ok" => true, "saved" => $saved]);
