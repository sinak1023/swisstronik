<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function    = new Users($db);
$leads_function    = new Leads($db);
$notes_function    = new Notes($db);
$sales             = new Sales($db);
$meetings_function = new Meetings($db);
$persian = new PersianDate();

$lead_id = (int)($_GET['lead_id'] ?? 0);

$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;

// دسترسی: مدیر / دارندهٔ گزارش‌های مدیریتی → همهٔ لیدها؛ در غیر این صورت فقط لید خودش
$permissions = json_decode($user['permissions'] ?? '[]', true) ?? [];
if (($user['role_id'] ?? 0) > 0) {
    $role = (new Roles($db))->get_by_id($user['role_id']);
    if ($role) $permissions = json_decode($role['permissions'] ?? '[]', true) ?? [];
}
// می‌تواند همهٔ لیدها را ببیند اگر: مدیر باشد، یا دسترسی گزارش‌های مدیریتی،
// یا دسترسی مدیریت لیدها (pages/leads.php) را داشته باشد (فقط‌خواندنی)
$can_view_all = $is_admin
    || in_array('pages/admin_reports.php', $permissions)
    || in_array('pages/leads.php', $permissions);

$lead = $leads_function->get_by_id($lead_id);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'لید یافت نشد']);
    exit;
}

if (!$can_view_all && $lead['assigned_to'] != $_SESSION['id']) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

// یادداشت‌ها (بر اساس شماره، مثل get_lead_notes)
$notes = $notes_function->get_by_phone($lead['phone']);
$notes_out = [];
foreach ($notes as $n) {
    $notes_out[] = [
        'note'       => $n['note'],
        'admin_name' => $users_function->get_by_id($n['user_id'])['name'] ?? 'نامشخص',
        'created_at' => $n['created_at'],
        'date_label' => $n['created_at'] ? $persian->jdate('Y/m/d H:i', strtotime($n['created_at'])) : '',
    ];
}

// پرداخت‌ها/فروش‌های ثبت‌شده برای این لید
$sale_rows = $db->fetchAll(
    "SELECT * FROM sales WHERE lead_id = ? ORDER BY transaction_date DESC, id DESC",
    [$lead_id]
);
$sales_out = [];
foreach ($sale_rows as $s) {
    $sales_out[] = [
        'amount'       => number_format((int)$s['amount']),
        'payment_type' => $s['payment_type'],
        'source'       => $s['source'] ?? 'zarinpal',
        'period'       => $s['period'] ?? null,
        'note'         => $s['note'] ?? null,
        'date_label'   => $s['transaction_date'] ? $persian->jdate('Y/m/d H:i', strtotime($s['transaction_date'])) : '',
    ];
}

// جلسات این لید
$meeting_rows = $meetings_function->get_by_lead($lead_id);
$meetings_out = [];
foreach ($meeting_rows as $m) {
    $ts = strtotime($m['meeting_datetime']);
    $meetings_out[] = [
        'status'      => $m['status'],
        'result_note' => $m['result_note'],
        'title'       => $m['title'],
        'date_label'  => $persian->jdate('l j F Y', $ts) . ' — ' . $persian->jdate('H:i', $ts),
    ];
}

echo json_encode([
    'ok'   => true,
    'lead' => [
        'id'           => (int)$lead['id'],
        'name'         => $lead['name'],
        'phone'        => $lead['phone'],
        'status'       => $lead['status'],
        'project_name' => $lead['project_name'] ?? '',
        'assignee_name' => $lead['assignee_name'] ?? '',
        'created_label' => $lead['created_at'] ? $persian->jdate('Y/m/d', strtotime($lead['created_at'])) : '',
    ],
    'notes'    => $notes_out,
    'sales'    => $sales_out,
    'meetings' => $meetings_out,
]);
