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
$sales = new Sales($db);
$persian = new PersianDate();

$lead_id = (int)($_GET['lead_id'] ?? 0);

$user = (new Users($db))->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;
if (!$is_admin && !$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(["ok" => false, "error" => "دسترسی غیرمجاز"]);
    exit();
}

$rows = $sales->manual_for_lead($lead_id);
$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id'            => $r['id'],
        'amount'        => number_format((int)$r['amount']),
        'payment_type'  => $r['payment_type'],
        'period'        => $r['period'],
        'note'          => $r['note'],
        'receipt_image' => $r['receipt_image'],
        'date_jalali'   => $r['transaction_date'] ? $persian->jdate('Y/m/d', strtotime($r['transaction_date'])) : '',
    ];
}

echo json_encode(["ok" => true, "receipts" => $out]);
