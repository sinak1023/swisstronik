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

    // یادداشت‌ها بر اساس شمارهٔ نرمال‌شده — شامل کمپین‌های قبلی و کارشناسان دیگر
    $notes = $lead_notes_function->get_by_phone_norm($phone);

    // کش نام کاربر/پروژه برای جلوگیری از کوئری تکراری
    $userCache = [];
    $projCache = [];
    $out = [];
    foreach ($notes as $note) {
        $uid = $note['user_id'];
        if (!array_key_exists($uid, $userCache)) {
            $u = $users_function->get_by_id($uid);
            $userCache[$uid] = $u['name'] ?? 'نامشخص';
        }

        // پروژهٔ خودِ لیدی که یادداشت رویش ثبت شده (نه لزوماً لید فعلی)
        $note_project = '';
        $note_lead = $note['lead_id'] ? $leads_function->get_by_id($note['lead_id']) : null;
        if ($note_lead) {
            $pid = $note_lead['project_id'] ?? 0;
            if ($pid && !array_key_exists($pid, $projCache)) {
                $p = $projects_function->get_by_id($pid);
                $projCache[$pid] = $p['name'] ?? '';
            }
            $note_project = $projCache[$pid] ?? '';
        }

        // آیا این یادداشت مربوط به همین لید فعلی است یا از کمپین/کارشناس دیگر
        $is_other = ($note['lead_id'] != $lead_id);

        $out[] = [
            'id'           => (int)$note['id'],
            'note'         => $note['note'],
            'user_id'      => (int)$note['user_id'],
            'admin_name'   => $userCache[$uid],
            'project_name' => $note_project,
            'is_other_campaign' => $is_other,
            'created_at'   => $note['created_at'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'notes' => $out
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'خطا در بارگذاری یادداشت‌ها']);
}
