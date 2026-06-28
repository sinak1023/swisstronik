<?php
header('Content-Type: application/json; charset=utf-8');
session_start();


if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';


$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];


if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = array_merge($permissions, json_decode($role['permissions'], true) ?? []);
    }
}


$root = 'apis/delete_leads.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی حذف لیدها را ندارید"]);
    exit();
}


$leads_func = new Leads($db);


$lead_ids = json_decode($_POST['lead_ids'] ?? '[]', true);

if (empty($lead_ids) || !is_array($lead_ids)) {
    echo json_encode(["ok" => false, "error" => "لیدهای معتبر انتخاب نشده"]);
    exit();
}


$lead_ids = array_map('intval', $lead_ids);
$lead_ids = array_filter($lead_ids, fn($id) => $id > 0);

if (empty($lead_ids)) {
    echo json_encode(["ok" => false, "error" => "هیچ لید معتبری انتخاب نشده"]);
    exit();
}


$deleted_count = 0;
$errors = [];

foreach ($lead_ids as $lead_id) {
    
    $lead = $leads_func->get_by_id($lead_id);
    if (!$lead) {
        $errors[] = "لید با ID $lead_id یافت نشد";
        continue;
    }


    
    if ($leads_func->delete($lead_id)) {
        $deleted_count++;
    } else {
        $errors[] = "خطا در حذف لید $lead_id";
    }
}


if ($deleted_count > 0) {
    echo json_encode([
        "ok" => true,
        "deleted_count" => $deleted_count,
        "message" => "$deleted_count لید با موفقیت حذف شد"
    ]);
} else {
    echo json_encode([
        "ok" => false,
        "error" => "هیچ لیدی حذف نشد",
        "details" => $errors
    ]);
}
?>