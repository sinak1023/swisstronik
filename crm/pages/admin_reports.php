<?php
$persian = new PersianDate();

$tab = $_GET['tab'] ?? 'activity';
$valid_tabs = ['activity', 'tasks', 'calls', 'messages', 'sales'];
if (!in_array($tab, $valid_tabs)) $tab = 'activity';

// بازهٔ تاریخ (پیش‌فرض: امروز)
$today_g = date('Y-m-d');
$from_g = $today_g;
$to_g = $today_g;

function jalali_to_g($persian, $val, $fallback)
{
    $p = preg_split('/[\/\-]/', $persian->tr_num(trim($val)));
    if (count($p) === 3 && (int)$p[0] > 1300) {
        $g = $persian->jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        return sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
    }
    return $fallback;
}
if (!empty($_GET['from_date'])) $from_g = jalali_to_g($persian, $_GET['from_date'], $today_g);
if (!empty($_GET['to_date']))   $to_g   = jalali_to_g($persian, $_GET['to_date'], $today_g);

$from_dt = $from_g . ' 00:00:00';
$to_dt   = $to_g . ' 23:59:59';

$status_labels = ['pending' => 'در انتظار', 'success' => 'تماس موفق', 'not_answer' => 'عدم پاسخ', 'following' => 'در حال پیگیری', 'purchased' => 'خریداری', 'rejected' => 'رد شده'];

function toman($a) { return number_format($a / 10); }
$qs_dates = 'from_date=' . urlencode($_GET['from_date'] ?? '') . '&to_date=' . urlencode($_GET['to_date'] ?? '');
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-text">گزارش‌های مدیریتی</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">فعالیت، تماس‌ها، پیام‌ها و فروش هر کاربر به تفکیک</p>
    </div>

    <!-- فیلتر تاریخ -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-5 mb-6">
        <form method="get" class="flex flex-col md:flex-row md:items-end gap-4">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="flex-1">
                <label class="block text-sm text-text mb-2">از تاریخ (شمسی)</label>
                <input type="text" name="from_date" data-jdp readonly value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text cursor-pointer" placeholder="امروز">
            </div>
            <div class="flex-1">
                <label class="block text-sm text-text mb-2">تا تاریخ (شمسی)</label>
                <input type="text" name="to_date" data-jdp readonly value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text cursor-pointer" placeholder="امروز">
            </div>
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg">اعمال فیلتر</button>
            <a href="apis/export_report.php?tab=<?= $tab ?>&<?= $qs_dates ?>" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg flex items-center gap-2">
                <i class='bx bx-download'></i> خروجی CSV
            </a>
        </form>
    </div>

    <!-- تب‌ها -->
    <div class="flex flex-wrap gap-2 mb-6 border-b border-border">
        <?php
        $tabs = ['activity' => 'فعالیت روزانه', 'tasks' => 'تسک کاربران', 'calls' => 'تماس‌ها', 'messages' => 'پیام‌ها', 'sales' => 'فروش'];
        foreach ($tabs as $tk => $tv):
            $active = $tk === $tab;
        ?>
            <a href="admin_reports?tab=<?= $tk ?>&<?= $qs_dates ?>" class="px-4 py-2.5 rounded-t-lg font-medium <?= $active ? 'bg-primary text-white' : 'text-text/70 hover:bg-muted' ?>"><?= $tv ?></a>
        <?php endforeach; ?>
    </div>

    <div class="bg-surface rounded-2xl shadow-sm border border-border overflow-x-auto">
        <?php if ($tab === 'activity'): ?>
            <?php
            $rows = $db->fetchAll("SELECT a.*, u.name as user_name FROM activity_logs a LEFT JOIN users u ON a.user_id=u.id WHERE a.created_at BETWEEN ? AND ? ORDER BY a.user_id, a.created_at DESC LIMIT 2000", [$from_dt, $to_dt]);
            $action_labels = ['edit_lead' => 'ویرایش لید', 'new_sale' => 'فروش جدید', 'save_settings' => 'تغییر تنظیمات', 'login' => 'ورود'];
            ?>
            <table class="w-full text-center">
                <thead class="bg-muted"><tr>
                    <th class="px-4 py-3 text-sm">کاربر</th><th class="px-4 py-3 text-sm">عملیات</th>
                    <th class="px-4 py-3 text-sm">مورد</th><th class="px-4 py-3 text-sm">زمان</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-muted/30">
                        <td class="px-4 py-3"><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= $action_labels[$r['action']] ?? htmlspecialchars($r['action']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars(($r['entity'] ?? '') . ($r['entity_id'] ? ' #' . $r['entity_id'] : '')) ?></td>
                        <td class="px-4 py-3 text-xs text-gray-500"><?= $persian->jdate('Y/m/d H:i', strtotime($r['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="4" class="py-8 text-gray-500">رکوردی یافت نشد</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tab === 'tasks'): ?>
            <?php
            $rows = $db->fetchAll("SELECT t.*, u.name as user_name FROM user_tasks t LEFT JOIN users u ON t.user_id=u.id WHERE t.task_date BETWEEN ? AND ? ORDER BY u.name, t.task_date DESC, t.position", [$from_g, $to_g]);
            ?>
            <table class="w-full text-center">
                <thead class="bg-muted"><tr>
                    <th class="px-4 py-3 text-sm">کاربر</th><th class="px-4 py-3 text-sm">تسک</th>
                    <th class="px-4 py-3 text-sm">تاریخ</th><th class="px-4 py-3 text-sm">وضعیت</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                <?php foreach ($rows as $r):
                    $gp = explode('-', $r['task_date']);
                    $jd = $persian->gregorian_to_jalali($gp[0], $gp[1], $gp[2], '/');
                ?>
                    <tr class="hover:bg-muted/30">
                        <td class="px-4 py-3"><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-right"><?= htmlspecialchars($r['title']) ?></td>
                        <td class="px-4 py-3 text-xs"><?= $jd ?></td>
                        <td class="px-4 py-3">
                            <?php if ($r['is_completed']): ?>
                                <span class="px-3 py-1 rounded-full text-xs bg-green-100 text-green-800">انجام شد</span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">انجام نشده</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="4" class="py-8 text-gray-500">تسکی یافت نشد</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tab === 'calls'): ?>
            <?php
            $rows = $db->fetchAll("SELECT u.id, u.name as user_name,
                    COUNT(*) total_calls, COUNT(DISTINCT cl.phone) distinct_numbers,
                    SUM(cl.status='success') s_success, SUM(cl.status='not_answer') s_noanswer,
                    SUM(cl.status='following') s_following, SUM(cl.status='purchased') s_purchased,
                    SUM(cl.status='rejected') s_rejected, SUM(cl.status='pending') s_pending
                FROM call_logs cl JOIN users u ON cl.user_id=u.id
                WHERE cl.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.name ORDER BY total_calls DESC", [$from_dt, $to_dt]);
            ?>
            <table class="w-full text-center text-sm">
                <thead class="bg-muted"><tr>
                    <th class="px-3 py-3">کاربر</th><th class="px-3 py-3">کل تماس</th><th class="px-3 py-3">تعداد شماره</th>
                    <th class="px-3 py-3">موفق</th><th class="px-3 py-3">عدم پاسخ</th><th class="px-3 py-3">پیگیری</th>
                    <th class="px-3 py-3">خریداری</th><th class="px-3 py-3">رد</th><th class="px-3 py-3">در انتظار</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-muted/30">
                        <td class="px-3 py-3 font-medium"><?= htmlspecialchars($r['user_name']) ?></td>
                        <td class="px-3 py-3 font-bold"><?= $r['total_calls'] ?></td>
                        <td class="px-3 py-3"><?= $r['distinct_numbers'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_success'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_noanswer'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_following'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_purchased'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_rejected'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['s_pending'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="9" class="py-8 text-gray-500">تماسی ثبت نشده</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tab === 'messages'): ?>
            <?php
            $rows = $db->fetchAll("SELECT u.id, u.name as user_name,
                    COUNT(*) total_msgs,
                    SUM(ml.channel='sms') c_sms, SUM(ml.channel='bale') c_bale,
                    SUM(ml.channel='telegram') c_telegram, SUM(ml.channel='whatsapp') c_whatsapp
                FROM message_logs ml JOIN users u ON ml.user_id=u.id
                WHERE ml.created_at BETWEEN ? AND ?
                GROUP BY u.id, u.name ORDER BY total_msgs DESC", [$from_dt, $to_dt]);
            ?>
            <table class="w-full text-center text-sm">
                <thead class="bg-muted"><tr>
                    <th class="px-3 py-3">کاربر</th><th class="px-3 py-3">کل پیام</th>
                    <th class="px-3 py-3">پیامک</th><th class="px-3 py-3">بله</th>
                    <th class="px-3 py-3">تلگرام</th><th class="px-3 py-3">واتساپ</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-muted/30">
                        <td class="px-3 py-3 font-medium"><?= htmlspecialchars($r['user_name']) ?></td>
                        <td class="px-3 py-3 font-bold"><?= $r['total_msgs'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['c_sms'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['c_bale'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['c_telegram'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['c_whatsapp'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="6" class="py-8 text-gray-500">پیامی ثبت نشده</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tab === 'sales'): ?>
            <?php
            $sales = new Sales($db);
            $rows = $sales->summary_by_user($from_dt, $to_dt);
            ?>
            <table class="w-full text-center text-sm">
                <thead class="bg-muted"><tr>
                    <th class="px-3 py-3">کاربر</th><th class="px-3 py-3">تعداد فروش</th>
                    <th class="px-3 py-3">مبلغ کل (تومان)</th><th class="px-3 py-3">فروش کامل</th><th class="px-3 py-3">قسطی</th>
                </tr></thead>
                <tbody class="divide-y divide-border">
                <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-muted/30">
                        <td class="px-3 py-3 font-medium"><?= htmlspecialchars($r['user_name'] ?? '—') ?></td>
                        <td class="px-3 py-3 font-bold"><?= $r['cnt'] ?></td>
                        <td class="px-3 py-3"><?= toman($r['total']) ?></td>
                        <td class="px-3 py-3"><?= (int)$r['full_cnt'] ?></td>
                        <td class="px-3 py-3"><?= (int)$r['inst_cnt'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="5" class="py-8 text-gray-500">فروشی ثبت نشده</td></tr><?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ time: false, persianDigits: true });
    }
</script>
