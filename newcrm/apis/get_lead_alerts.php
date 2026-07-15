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
$alerts_function = new SecondaryAlerts($db);
$users_function = new Users($db);

$lead_id = (int)($_GET['lead_id'] ?? 0);

$user = $users_function->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;

// فقط کارشناس صاحب لید (یا مدیر) هشدارهای آن لید را می‌بیند
if (!$is_admin && !$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(['ok' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

$rows = $alerts_function->get_active_for_lead($lead_id);
$out = [];
foreach ($rows as $r) {
    $channelLabel = [
        'telegram' => 'تلگرام',
        'whatsapp' => 'واتساپ',
        'bale'     => 'بله',
    ][$r['secondary_channel']] ?? $r['secondary_channel'];

    $out[] = [
        'id'               => (int)$r['id'],
        'channel'          => $r['secondary_channel'],
        'channel_label'    => $channelLabel,
        'source_user_name' => $r['source_user_name'] ?? '—',
        'source_project'   => $r['source_project_name'] ?? '',
    ];
}

echo json_encode(['ok' => true, 'alerts' => $out]);
