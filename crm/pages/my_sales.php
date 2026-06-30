<?php
$user_id = (int)$_SESSION['id'];
$persian = new PersianDate();
$sales = new Sales($db);

// ماه جاری شمسی یا ماه انتخابی
$today_j = explode('/', $persian->jdate('Y/n/j', '', '', 'Asia/Tehran', 'en'));
$jy = (int)($_GET['y'] ?? $today_j[0]);
$jm = (int)($_GET['m'] ?? $today_j[1]);
if ($jm < 1) { $jm = 12; $jy--; }
if ($jm > 12) { $jm = 1; $jy++; }

// تعداد روزهای ماه شمسی
$days_in_month = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : ($persian->jcheckdate(12, 30, $jy) ? 30 : 29));

// مرز میلادی ماه برای کوئری
$g_start = $persian->jalali_to_gregorian($jy, $jm, 1);
$g_end   = $persian->jalali_to_gregorian($jy, $jm, $days_in_month);
$from = sprintf('%04d-%02d-%02d 00:00:00', $g_start[0], $g_start[1], $g_start[2]);
$to   = sprintf('%04d-%02d-%02d 23:59:59', $g_end[0], $g_end[1], $g_end[2]);

// فروش‌های ماه گروه‌بندی‌شده بر اساس روز شمسی
$rows = $sales->list_for_user($user_id, $from, $to);
$by_day = [];
$month_total_amount = 0;
foreach ($rows as $r) {
    $g = explode(' ', $r['transaction_date'])[0];
    $gp = explode('-', $g);
    $jd = $persian->gregorian_to_jalali($gp[0], $gp[1], $gp[2]);
    $d = (int)$jd[2];
    if (!isset($by_day[$d])) $by_day[$d] = ['count' => 0, 'amount' => 0];
    $by_day[$d]['count']++;
    $by_day[$d]['amount'] += (int)$r['amount'];
    $month_total_amount += (int)$r['amount'];
}

// اولین روز هفته ماه (0=شنبه ... 6=جمعه)
$w = (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $g_start[0], $g_start[1], $g_start[2]))); // 0=Sun
// تبدیل به شاخص شنبه‌محور: شنبه=0
$first_weekday = ($w + 1) % 7; // Sat=0

// آمار خلاصه: امروز / این هفته / این ماه
$today_g = date('Y-m-d');
$today_cnt = $db->fetch("SELECT COUNT(*) c, COALESCE(SUM(amount),0) a FROM sales WHERE user_id=? AND DATE(transaction_date)=?", [$user_id, $today_g]);
$week_cnt = $db->fetch("SELECT COUNT(*) c, COALESCE(SUM(amount),0) a FROM sales WHERE user_id=? AND transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$user_id]);
$month_cnt = ['c' => count($rows), 'a' => $month_total_amount];

// فیلتر بازهٔ دلخواه (مجموع)
$range_result = null;
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $pf = preg_split('/[\/\-]/', $persian->tr_num(trim($_GET['from_date'])));
    $pt = preg_split('/[\/\-]/', $persian->tr_num(trim($_GET['to_date'])));
    if (count($pf) === 3 && count($pt) === 3) {
        $gf = $persian->jalali_to_gregorian((int)$pf[0], (int)$pf[1], (int)$pf[2]);
        $gt = $persian->jalali_to_gregorian((int)$pt[0], (int)$pt[1], (int)$pt[2]);
        $rf = sprintf('%04d-%02d-%02d 00:00:00', $gf[0], $gf[1], $gf[2]);
        $rt = sprintf('%04d-%02d-%02d 23:59:59', $gt[0], $gt[1], $gt[2]);
        $range_result = $db->fetch("SELECT COUNT(*) c, COALESCE(SUM(amount),0) a, SUM(payment_type='full') f, SUM(payment_type='installment') i FROM sales WHERE user_id=? AND transaction_date BETWEEN ? AND ?", [$user_id, $rf, $rt]);
        // لیست دقیق فروش‌های بازه: نام مشتری، شماره، مبلغ، نوع
        $range_list = $db->fetchAll(
            "SELECT s.*, l.name AS lead_name FROM sales s LEFT JOIN leads l ON s.lead_id = l.id
             WHERE s.user_id = ? AND s.transaction_date BETWEEN ? AND ? ORDER BY s.transaction_date DESC",
            [$user_id, $rf, $rt]
        );
    }
}

$month_names = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
function toman($amount) { return number_format($amount / 10) . ' تومان'; }
?>

<div class="max-w-5xl mx-auto p-4 md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-text">فروش‌های من</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">آمار فروش روزانه، هفتگی و ماهانه بر اساس تقویم شمسی</p>
    </div>

    <!-- خلاصه -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-2xl p-5 shadow">
            <p class="text-blue-100 text-sm">امروز</p>
            <p class="text-2xl font-bold mt-1"><?= $today_cnt['c'] ?> فروش</p>
            <p class="text-blue-100 text-xs mt-1"><?= toman($today_cnt['a']) ?></p>
        </div>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-2xl p-5 shadow">
            <p class="text-purple-100 text-sm">۷ روز اخیر</p>
            <p class="text-2xl font-bold mt-1"><?= $week_cnt['c'] ?> فروش</p>
            <p class="text-purple-100 text-xs mt-1"><?= toman($week_cnt['a']) ?></p>
        </div>
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-2xl p-5 shadow">
            <p class="text-emerald-100 text-sm">این ماه</p>
            <p class="text-2xl font-bold mt-1"><?= $month_cnt['c'] ?> فروش</p>
            <p class="text-emerald-100 text-xs mt-1"><?= toman($month_cnt['a']) ?></p>
        </div>
    </div>

    <!-- فیلتر بازه -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-5 mb-6">
        <form method="get" class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm text-text mb-2">از تاریخ (شمسی)</label>
                <input type="text" name="from_date" data-jdp readonly value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text cursor-pointer" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div class="flex-1">
                <label class="block text-sm text-text mb-2">تا تاریخ (شمسی)</label>
                <input type="text" name="to_date" data-jdp readonly value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text cursor-pointer" placeholder="۱۴۰۳/۱۲/۲۹">
            </div>
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg">محاسبهٔ مجموع</button>
        </form>
        <?php if ($range_result): ?>
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-900">
                مجموع بازهٔ انتخابی: <b><?= $range_result['c'] ?></b> فروش — <b><?= toman($range_result['a']) ?></b>
                <span class="text-sm">(کامل: <?= (int)$range_result['f'] ?> | قسطی: <?= (int)$range_result['i'] ?>)</span>
            </div>

            <!-- لیست دقیق فروش‌های بازه -->
            <div class="mt-4 overflow-x-auto border border-border rounded-xl">
                <table class="w-full text-center text-sm">
                    <thead class="bg-muted"><tr>
                        <th class="px-3 py-3">مشتری</th><th class="px-3 py-3">شماره</th>
                        <th class="px-3 py-3">مبلغ (تومان)</th><th class="px-3 py-3">نوع فروش</th>
                        <th class="px-3 py-3">نوع ثبت</th><th class="px-3 py-3">تاریخ</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                    <?php if (!empty($range_list)): foreach ($range_list as $sale):
                        $sd = explode(' ', $sale['transaction_date'])[0];
                        $sgp = explode('-', $sd);
                        $sjd = (count($sgp) === 3) ? $persian->gregorian_to_jalali($sgp[0], $sgp[1], $sgp[2], '/') : '-';
                    ?>
                        <tr class="hover:bg-muted/30">
                            <td class="px-3 py-3"><?= htmlspecialchars($sale['lead_name'] ?? 'بی‌نام') ?></td>
                            <td class="px-3 py-3 dir-ltr"><?= htmlspecialchars($sale['phone'] ?? '-') ?></td>
                            <td class="px-3 py-3 font-bold"><?= number_format((int)$sale['amount']) ?></td>
                            <td class="px-3 py-3"><?= $sale['payment_type'] === 'installment' ? 'قسطی' : 'کامل' ?></td>
                            <td class="px-3 py-3 text-xs"><?= ($sale['source'] ?? 'zarinpal') === 'manual' ? 'دستی' : 'زرین‌پال' ?></td>
                            <td class="px-3 py-3 text-xs text-gray-500"><?= $sjd ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="py-6 text-gray-500">فروشی در این بازه نیست</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- تقویم ماهانه -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
        <div class="flex items-center justify-between mb-6">
            <a href="my_sales?y=<?= $jm == 1 ? $jy - 1 : $jy ?>&m=<?= $jm == 1 ? 12 : $jm - 1 ?>" class="p-2 hover:bg-muted rounded-lg"><i class='bx bx-chevron-right text-xl'></i></a>
            <h3 class="font-bold text-xl text-text"><?= $month_names[$jm] ?> <?= $jy ?></h3>
            <a href="my_sales?y=<?= $jm == 12 ? $jy + 1 : $jy ?>&m=<?= $jm == 12 ? 1 : $jm + 1 ?>" class="p-2 hover:bg-muted rounded-lg"><i class='bx bx-chevron-left text-xl'></i></a>
        </div>
        <div class="grid grid-cols-7 gap-2 mb-2 text-center text-sm font-semibold text-gray-500">
            <div>ش</div><div>ی</div><div>د</div><div>س</div><div>چ</div><div>پ</div><div>ج</div>
        </div>
        <div class="grid grid-cols-7 gap-2">
            <?php for ($i = 0; $i < $first_weekday; $i++): ?>
                <div></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $days_in_month; $d++):
                $has = isset($by_day[$d]);
                $is_today = ($jy == (int)$today_j[0] && $jm == (int)$today_j[1] && $d == (int)$today_j[2]);
            ?>
                <div class="aspect-square border rounded-xl flex flex-col items-center justify-center text-center <?= $has ? 'bg-emerald-50 border-emerald-300' : 'border-border' ?> <?= $is_today ? 'ring-2 ring-primary' : '' ?>">
                    <span class="text-sm font-semibold text-text"><?= $d ?></span>
                    <?php if ($has): ?>
                        <span class="text-[10px] text-emerald-700 font-bold mt-0.5"><?= $by_day[$d]['count'] ?> فروش</span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ time: false, persianDigits: true });
    }
</script>
