<?php
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    http_response_code(403);
    exit('forbidden');
}

require_once '../config.php';

$users_function = new Users($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) $permissions = json_decode($role['permissions'], true) ?? [];
}
if (!in_array('pages/admin_reports.php', $permissions)) {
    http_response_code(403);
    exit('دسترسی ندارید');
}

$persian = new PersianDate();
$tab = $_GET['tab'] ?? 'sales';
$today_g = date('Y-m-d');

function jtg($persian, $val, $fallback)
{
    $p = preg_split('/[\/\-]/', $persian->tr_num(trim((string)$val)));
    if (count($p) === 3 && (int)$p[0] > 1300) {
        $g = $persian->jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        return sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
    }
    return $fallback;
}
$from_g = !empty($_GET['from_date']) ? jtg($persian, $_GET['from_date'], $today_g) : $today_g;
$to_g   = !empty($_GET['to_date'])   ? jtg($persian, $_GET['to_date'], $today_g)   : $today_g;
$from_dt = $from_g . ' 00:00:00';
$to_dt   = $to_g . ' 23:59:59';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $tab . '_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // BOM برای نمایش درست فارسی در اکسل

if ($tab === 'calls') {
    fputcsv($out, ['کاربر', 'کل تماس', 'تعداد شماره', 'موفق', 'عدم پاسخ', 'پیگیری', 'خریداری', 'رد', 'در انتظار']);
    $rows = $db->fetchAll("SELECT u.name un, COUNT(*) tc, COUNT(DISTINCT cl.phone) dn,
            SUM(cl.status='success') a, SUM(cl.status='not_answer') b, SUM(cl.status='following') c,
            SUM(cl.status='purchased') d, SUM(cl.status='rejected') e, SUM(cl.status='pending') f
        FROM call_logs cl JOIN users u ON cl.user_id=u.id WHERE cl.created_at BETWEEN ? AND ?
        GROUP BY u.id,u.name ORDER BY tc DESC", [$from_dt, $to_dt]);
    foreach ($rows as $r) {
        fputcsv($out, [$r['un'], $r['tc'], $r['dn'], (int)$r['a'], (int)$r['b'], (int)$r['c'], (int)$r['d'], (int)$r['e'], (int)$r['f']]);
    }
} elseif ($tab === 'messages') {
    fputcsv($out, ['کاربر', 'کل پیام', 'پیامک', 'بله', 'تلگرام', 'واتساپ']);
    $rows = $db->fetchAll("SELECT u.name un, COUNT(*) t,
            SUM(ml.channel='sms') s, SUM(ml.channel='bale') b, SUM(ml.channel='telegram') tg, SUM(ml.channel='whatsapp') w
        FROM message_logs ml JOIN users u ON ml.user_id=u.id WHERE ml.created_at BETWEEN ? AND ?
        GROUP BY u.id,u.name ORDER BY t DESC", [$from_dt, $to_dt]);
    foreach ($rows as $r) {
        fputcsv($out, [$r['un'], $r['t'], (int)$r['s'], (int)$r['b'], (int)$r['tg'], (int)$r['w']]);
    }
} elseif ($tab === 'sales') {
    fputcsv($out, ['کاربر', 'تعداد فروش', 'مبلغ کل (تومان)', 'فروش کامل', 'قسطی']);
    $sales = new Sales($db);
    foreach ($sales->summary_by_user($from_dt, $to_dt) as $r) {
        fputcsv($out, [$r['user_name'], $r['cnt'], number_format($r['total'] / 10), (int)$r['full_cnt'], (int)$r['inst_cnt']]);
    }
} elseif ($tab === 'tasks') {
    fputcsv($out, ['کاربر', 'تسک', 'تاریخ', 'وضعیت']);
    $rows = $db->fetchAll("SELECT u.name un, t.title, t.task_date, t.is_completed FROM user_tasks t JOIN users u ON t.user_id=u.id WHERE t.task_date BETWEEN ? AND ? ORDER BY u.name", [$from_g, $to_g]);
    foreach ($rows as $r) {
        $gp = explode('-', $r['task_date']);
        $jd = $persian->gregorian_to_jalali($gp[0], $gp[1], $gp[2], '/');
        fputcsv($out, [$r['un'], $r['title'], $jd, $r['is_completed'] ? 'انجام شد' : 'انجام نشده']);
    }
} else { // activity
    fputcsv($out, ['کاربر', 'عملیات', 'مورد', 'زمان']);
    $rows = $db->fetchAll("SELECT u.name un, a.action, a.entity, a.entity_id, a.created_at FROM activity_logs a LEFT JOIN users u ON a.user_id=u.id WHERE a.created_at BETWEEN ? AND ? ORDER BY a.created_at DESC LIMIT 5000", [$from_dt, $to_dt]);
    foreach ($rows as $r) {
        fputcsv($out, [$r['un'], $r['action'], ($r['entity'] ?? '') . ($r['entity_id'] ? ' #' . $r['entity_id'] : ''), $persian->jdate('Y/m/d H:i', strtotime($r['created_at']))]);
    }
}

fclose($out);
