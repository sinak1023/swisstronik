<?php

header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$leads_function = new Leads($db);
$lead_notes_function = new Notes($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}
$root = 'apis/add_lead_note.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی افزودن یادداشت را ندارید"]);
    exit();
}

$lead_id = (int)($_POST['lead_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$lead = $leads_function->get_by_id($lead_id);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'شماره یافت نشد']);
    exit;
}
$phone = $lead['phone'];
if (empty($note)) {
    echo json_encode(['ok' => false, 'error' => 'متن یادداشت الزامی است']);
    exit;
}

try {
    if (!$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
        echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
        exit;
    }

    $params = ["lead_id" => $lead_id,"phone"=>$phone, "user_id" => $_SESSION['id'], "note" => $note];
    $lead_notes_function->add($params);

    echo json_encode([
        'ok' => true,
        'message' => 'یادداشت با موفقیت ثبت شد'
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در ثبت یادداشت']);
}
