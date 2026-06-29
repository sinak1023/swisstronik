<?php
header(header: 'Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$leads_function = new Leads($db);
$reminder_function = new Reminders($db);

$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/add_reminder.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی افزودن یادداشت را ندارید"]);
    exit();
}
$lead_id = (int)($_POST['lead_id'] ?? 0);
$reminder_date = trim($_POST['reminder_date'] ?? '');
$reminder_time = trim($_POST['reminder_time'] ?? '');
$reminder_text = trim($_POST['reminder_text'] ?? '');

if (empty($reminder_date) || empty($reminder_time) || empty($reminder_text)) {
    echo json_encode(['ok' => false, 'error' => 'همه فیلدها الزامی هستند']);
    exit;
}
// اتصال تلگرام دیگر الزامی نیست؛ یادآوری با پیامک ارسال می‌شود و نوتیف تلگرام
// فقط در صورت داشتن آیدی عددی (تنظیم‌شده هنگام ساخت کاربر) ارسال خواهد شد.
try {


    if (!$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
        echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
        exit;
    }
    $lead = $leads_function->get_by_id($lead_id);
    if (!$lead) {
        echo json_encode(['ok' => false, 'error' => 'لید پیدا نشد']);
        exit;
    }

    $persian = new PersianDate();
    list($year, $month, $day) = explode('/', $reminder_date);
    $gregorian_date = $persian->jalali_to_gregorian($year, $month, $day);
    $final_datetime = implode('-', $gregorian_date) . ' ' . $reminder_time . ':00';
    $params = ["lead_id" => $lead_id, "phone" => $lead['phone'], "user_id" => $_SESSION['id'], "reminder_datetime" => $final_datetime, "reminder_text" => $reminder_text];
    $reminder_function->add($params);
    echo json_encode([
        'ok' => true,
        'message' => 'یادآوری با موفقیت ثبت شد'
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ثبت یادآوری: ' . $e->getMessage()]);
}
