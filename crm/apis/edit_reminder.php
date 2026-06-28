<?php

header('Content-Type: application/json; charset=utf-8');
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
$root = 'apis/edit_reminder.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ویرایش یادآوری را ندارید"]);
    exit();
}
$leads_function = new Leads($db);
$reminder_function = new Reminders($db);
$persian = new PersianDate();


$remind_id = (int)($_POST['id'] ?? 0);
$reminder_date = trim($_POST['reminder_date'] ?? '');
$reminder_time = trim($_POST['reminder_time'] ?? '');
$reminder_text = trim($_POST['reminder_text'] ?? '');

$remind_info = $reminder_function->get_by_id($remind_id);

if (!$remind_info) {
    echo json_encode(['ok' => false, 'error' => 'پیدا نشد']);
    exit;
}
if ($remind_info['user_id'] !== $_SESSION['id']) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

if (!$remind_id || !$reminder_text || !$reminder_date || !$reminder_time) {
    echo json_encode(["ok" => false, "error" => "داده نامعتبر"]);
    exit();
}

$lead = $leads_function->get_by_id($remind_info['lead_id']);
if (!$lead || $lead['assigned_to'] != $_SESSION["id"]) {
    echo json_encode(["ok" => false, "error" => "لید متعلق به شما نیست"]);
    exit();
}

list($year, $month, $day) = explode('/', $reminder_date);
$gregorian_date = $persian->jalali_to_gregorian($year, $month, $day);
$final_datetime = implode('-', $gregorian_date) . ' ' . $reminder_time . ':00';

$result = $reminder_function->update($remind_id, [
    'reminder_datetime' => $final_datetime,
    'reminder_text' => $reminder_text
]);

echo json_encode(["ok" => true]);
