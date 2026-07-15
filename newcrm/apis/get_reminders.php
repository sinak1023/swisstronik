<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$leads_function = new Leads($db);
$projects_function = new Projects($db);
$reminder_function = new Reminders($db);
$users_function = new Users($db);
$persian = new PersianDate();


$lead_id = (int)($_GET['lead_id'] ?? 0);

try {

    if (!$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
        echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
        exit;
    }

    
    $reminders = $reminder_function->get_by_lead_user($lead_id, $_SESSION['id']);


    foreach ($reminders as &$reminder) {
        $reminder_date = explode(" ", $reminder['reminder_datetime'])[0];
        $reminder_time = explode(" ", $reminder['reminder_datetime'])[1];
        list($year, $month, $day) = explode('-', $reminder_date);
        $jalali = $persian->gregorian_to_jalali($year, $month, $day, "/");
        
        $reminder['reminder_datetime'] = $jalali;
        $reminder['reminder_date'] = $jalali;
        $reminder['reminder_time'] = $reminder_time;
        $project_name = $projects_function->get_by_id($leads_function->get_by_id($reminder['lead_id'])['project_id'])['name'];
        $reminder['lead_name'] = $project_name;
        $reminder['user'] = $users_function->get_by_id($reminder['user_id']);
    }

    echo json_encode([
        'ok' => true,
        'reminders' => $reminders
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در بارگذاری یادآوری‌ها']);
}
