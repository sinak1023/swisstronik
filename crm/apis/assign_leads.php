<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();
if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}
require_once '../config.php';
$leads_func = new Leads($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) $permissions = json_decode($role['permissions'], true) ?? [];
}
$root = 'apis/assign_leads.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی تخصیص لیدها را ندارید"]);
    exit();
}
$leads_func = new Leads($db);
$lead_ids = json_decode($_POST['lead_ids'] ?? '[]', true);
$user_ids = array();
if(strpos($_POST['user_ids'],",") == false){
$user_ids = array(str_replace('"','',$_POST['user_ids'])); 
}else{
$user_ids = json_decode($_POST['user_ids'] ?? '[]', true);
}

$round_robin = !empty($_POST['round_robin']);
$unassign = !empty($_POST['unassign']); 
if (empty($lead_ids) || (empty($user_ids) && !$unassign)) {
    echo json_encode(["ok" => false, "error" => "داده نامعتبر"]);
    exit();
}
if ($unassign) {
    
    $unassigned = 0;
    foreach ($lead_ids as $lead_id) {
        $result = $leads_func->unassign($lead_id);
        if (!$result) continue;
        $unassigned++;
    }
    echo json_encode(["ok" => true, "unassigned_count" => $unassigned]);
} else {
    
    if ($round_robin && count($user_ids) > 1) {
        $assigned = 0;
        foreach ($lead_ids as $index => $lead_id) {
            $user_id = $user_ids[$index % count($user_ids)];
            $result = $leads_func->assign([$lead_id], $user_id);
            if (is_array($result) && isset($result['errors'])) break;
            $assigned++;
        }
        echo json_encode(["ok" => true, "assigned_count" => $assigned]);
    } else {
        $result = $leads_func->assign($lead_ids, $user_ids[0]);
        if ($result === true) {
            echo json_encode(["ok" => true, "assigned_count" => count($lead_ids)]);
        } else {
            echo json_encode(["ok" => false, "error" => $result['error'] ?? "خطا", "details" => $result['errors'] ?? []]);
        }
    }
}
