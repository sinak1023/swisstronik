<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$lead_notes_function = new Notes($db);
$leads_function = new Leads($db);
$users_function = new Users($db);
$projects_function = new Projects($db);

$lead_id = (int)($_GET['lead_id'] ?? 0);

try {
    if (!$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
        echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
        exit;
    }

    $lead = $leads_function->get_by_id($lead_id);
    if (!$lead) {
        echo json_encode(['ok' => false, 'error' => 'شماره یافت نشد']);
        exit;
    }
    $phone = $lead['phone'];
    
    $notes = $lead_notes_function->get_by_phone($phone);

    $i = 0;
    foreach($notes as $note){
        $notes[$i]['admin_name'] = $users_function->get_by_id($note['user_id'])['name'];
        $notes[$i]['project_name'] = $projects_function->get_by_id($lead['project_id'])['name'];
        $i++;
    }
    echo json_encode([
        'ok' => true,
        'notes' => $notes
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در بارگذاری یادداشت‌ها']);
}
