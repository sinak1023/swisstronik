<?php
// صفحهٔ لیست جلسات — نمای هفتگی
// مدیر (role_id=1 یا دارندهٔ «گزارش‌های مدیریتی») می‌تواند کارشناس دلخواه را ببیند؛
// کارشناس فقط جلسات خودش را می‌بیند.
$current_user = (new Users($db))->get_by_id($_SESSION['id']);
$is_admin = (int)($current_user['role_id'] ?? 0) === 1;

$perms = json_decode($current_user['permissions'] ?? '[]', true) ?? [];
if (($current_user['role_id'] ?? 0) > 0) {
    $role = (new Roles($db))->get_by_id($current_user['role_id']);
    if ($role) $perms = json_decode($role['permissions'] ?? '[]', true) ?? [];
}
$can_view_all = $is_admin || in_array('pages/admin_reports.php', $perms);
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">
                <i class='bx bx-calendar-event text-primary'></i> لیست جلسات
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?= $can_view_all ? 'نمای هفتگی جلسات کارشناسان' : 'نمای هفتگی جلسات شما' ?>
            </p>
        </div>
    </div>

    <!-- نوار کنترل: انتخاب کارشناس (مدیر) + ناوبری هفته -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <?php if ($can_view_all): ?>
            <div class="flex-1">
                <label class="block text-sm text-text mb-1">کارشناس</label>
                <select id="expertSelect" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text">
                    <option value="all">همهٔ کارشناسان</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex items-end gap-2">
                <button onclick="meetingsWeekShift(-1)" class="px-3 py-2 border border-border rounded-lg hover:bg-muted text-text">
                    <i class='bx bx-chevron-right'></i> هفتهٔ قبل
                </button>
                <button onclick="meetingsWeekToday()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">امروز</button>
                <button onclick="meetingsWeekShift(1)" class="px-3 py-2 border border-border rounded-lg hover:bg-muted text-text">
                    هفتهٔ بعد <i class='bx bx-chevron-left'></i>
                </button>
            </div>
        </div>
        <p id="weekLabel" class="text-center text-sm text-gray-500 mt-3">در حال بارگذاری...</p>
    </div>

    <!-- راهنمای رنگ‌ها -->
    <div class="flex flex-wrap items-center justify-center gap-4 text-xs mb-6">
        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-gray-300"></span> نرسیده / بدون تعیین‌تکلیف</span>
        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-green-400"></span> موفق</span>
        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-red-400"></span> ناموفق</span>
        <span class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-yellow-300"></span> نیاز به جلسهٔ مجدد</span>
    </div>

    <!-- جدول هفتگی -->
    <div class="overflow-x-auto no-scrollbar">
        <div id="weekGrid" class="grid grid-cols-7 gap-3 min-w-[900px]">
            <!-- ستون‌های روزهای هفته اینجا رندر می‌شوند -->
        </div>
    </div>
</div>

<!-- مودال جزئیات لید (مشخصات + یادداشت‌ها + پرداخت‌ها) -->
<div id="leadDetailModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 1000;">
    <div class="bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-3xl max-h-[88vh] flex flex-col overflow-hidden">
        <div class="sticky top-0 bg-surface border-b border-border px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-text">مشخصات لید</h3>
            <button onclick="closeLeadDetail()" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-6 no-scrollbar" id="leadDetailBody">
            <div class="text-center py-12 text-gray-500">
                <i class='bx bx-loader-alt bx-spin text-4xl'></i>
                <p class="mt-3">در حال بارگذاری...</p>
            </div>
        </div>
    </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index: 9999;"></div>

<script>
    const CAN_VIEW_ALL = <?= $can_view_all ? 'true' : 'false' ?>;
    const MEETINGS_WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    let mWeekStart = null; // persianDate → شنبهٔ هفتهٔ جاری نمایش

    function showSnackbar(message, type = 'success') {
        const sb = $('#snackbar');
        sb.text(message).removeClass('hidden bg-green-600 bg-red-600')
            .addClass(type === 'success' ? 'bg-green-600' : 'bg-red-600');
        setTimeout(() => sb.addClass('hidden'), 3000);
    }

    function toEnglishDigits(str) {
        const fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩', en = '0123456789';
        return (str || '').toString()
            .replace(/[۰-۹]/g, w => en[fa.indexOf(w)])
            .replace(/[٠-٩]/g, w => en[ar.indexOf(w)]);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : text;
        return div.innerHTML;
    }

    function statusMeta(status, isPast) {
        const map = {
            'success':     { bar: 'bg-green-400', chip: 'bg-green-100 text-green-800', label: 'موفق' },
            'failed':      { bar: 'bg-red-400', chip: 'bg-red-100 text-red-800', label: 'ناموفق' },
            'rescheduled': { bar: 'bg-yellow-300', chip: 'bg-yellow-100 text-yellow-800', label: 'جلسه مجدد' },
            'scheduled':   { bar: 'bg-gray-300', chip: 'bg-gray-100 text-gray-700', label: isPast ? 'بدون تعیین‌تکلیف' : 'زمان‌بندی‌شده' },
        };
        return map[status] || map['scheduled'];
    }

    // شنبهٔ هفتهٔ حاوی pd (day(): 1=شنبه .. 7=جمعه)
    function startOfWeek(pd) {
        const dow = pd.day();
        return pd.clone().subtract('days', dow - 1);
    }

    function meetingsWeekToday() {
        mWeekStart = startOfWeek(new persianDate());
        loadWeek();
    }

    function meetingsWeekShift(delta) {
        if (!mWeekStart) mWeekStart = startOfWeek(new persianDate());
        mWeekStart = mWeekStart.clone().add('days', delta * 7);
        loadWeek();
    }

    // تبدیل persianDate شنبه به تاریخ میلادی Y-m-d برای ارسال به سرور
    function weekStartGregorian() {
        const g = mWeekStart.toDate(); // Date میلادی
        const y = g.getFullYear();
        const m = String(g.getMonth() + 1).padStart(2, '0');
        const d = String(g.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function loadWeek() {
        if (!mWeekStart) mWeekStart = startOfWeek(new persianDate());
        const params = { week_start: weekStartGregorian() };
        if (CAN_VIEW_ALL) {
            params.user_id = $('#expertSelect').val() || 'all';
        }

        $('#weekGrid').html('<div class="col-span-7 text-center py-12 text-gray-500"><i class="bx bx-loader-alt bx-spin text-3xl"></i></div>');

        $.get('apis/get_meetings.php', params, function(res) {
            if (!res.ok) { showSnackbar(res.error || 'خطا', 'error'); return; }
            $('#weekLabel').text(res.week_label);
            renderGrid(res.days);
        }).fail(() => showSnackbar('خطا در بارگذاری جلسات', 'error'));
    }

    function renderGrid(days) {
        let html = '';
        days.forEach((day, idx) => {
            const isFriday = idx === 6;
            const headClass = day.is_today ? 'bg-primary text-white' : (isFriday ? 'bg-red-50 text-red-600' : 'bg-muted text-text');

            let cards = '';
            if (day.meetings.length === 0) {
                cards = '<p class="text-center text-xs text-gray-400 py-4">—</p>';
            } else {
                day.meetings.forEach(m => {
                    const meta = statusMeta(m.status, m.is_past);
                    cards += `
                    <div onclick='openLeadDetail(${m.lead_id})'
                         class="cursor-pointer rounded-lg border border-border bg-background hover:shadow-md transition-all overflow-hidden">
                        <div class="h-1.5 ${meta.bar}"></div>
                        <div class="p-2">
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="text-xs font-bold text-text">${escapeHtml(m.time)}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded ${meta.chip}">${meta.label}</span>
                            </div>
                            <p class="text-xs font-medium text-text truncate">${escapeHtml(m.lead_name)}</p>
                            <p class="text-[11px] text-gray-500 truncate dir-ltr text-right">${escapeHtml(m.lead_phone)}</p>
                            ${CAN_VIEW_ALL && m.user_name ? `<p class="text-[10px] text-gray-400 mt-1 truncate"><i class='bx bx-user'></i> ${escapeHtml(m.user_name)}</p>` : ''}
                        </div>
                    </div>`;
                });
            }

            html += `
            <div class="flex flex-col">
                <div class="rounded-t-lg px-2 py-2 text-center ${headClass}">
                    <div class="text-xs font-semibold">${MEETINGS_WEEKDAYS[idx]}</div>
                    <div class="text-[11px] opacity-80">${toEnglishDigits(day.day_label)}</div>
                </div>
                <div class="flex-1 space-y-2 p-2 bg-surface border border-t-0 border-border rounded-b-lg min-h-[120px]">
                    ${cards}
                </div>
            </div>`;
        });
        $('#weekGrid').html(html);
    }

    /* --- جزئیات لید --- */
    function openLeadDetail(leadId) {
        if (!leadId) { showSnackbar('این جلسه به لیدی متصل نیست', 'error'); return; }
        $('#leadDetailModal').removeClass('hidden');
        $('body').addClass('modal-open');
        $('#leadDetailBody').html('<div class="text-center py-12 text-gray-500"><i class="bx bx-loader-alt bx-spin text-4xl"></i><p class="mt-3">در حال بارگذاری...</p></div>');

        $.get('apis/get_lead_full.php', { lead_id: leadId }, function(res) {
            if (!res.ok) {
                $('#leadDetailBody').html(`<p class="text-center text-red-500 py-8">${escapeHtml(res.error || 'خطا')}</p>`);
                return;
            }
            renderLeadDetail(res);
        }).fail(() => {
            $('#leadDetailBody').html('<p class="text-center text-red-500 py-8">خطا در ارتباط با سرور</p>');
        });
    }

    function renderLeadDetail(res) {
        const l = res.lead;
        const statusLabels = { 'pending': 'در انتظار', 'success': 'تماس موفق', 'not_answer': 'عدم پاسخ', 'following': 'در حال پیگیری', 'purchased': 'قبلا خریداری شده', 'rejected': 'رد شده' };

        let html = `
        <div class="bg-muted/30 border border-border rounded-xl p-4">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                <div><span class="text-gray-500">نام:</span> <span class="font-medium text-text">${escapeHtml(l.name)}</span></div>
                <div><span class="text-gray-500">شماره:</span> <span class="font-mono text-text dir-ltr">${escapeHtml(l.phone)}</span></div>
                <div><span class="text-gray-500">پروژه:</span> <span class="text-text">${escapeHtml(l.project_name || '—')}</span></div>
                <div><span class="text-gray-500">کارشناس:</span> <span class="text-text">${escapeHtml(l.assignee_name || '—')}</span></div>
                <div><span class="text-gray-500">وضعیت:</span> <span class="text-text">${escapeHtml(statusLabels[l.status] || l.status)}</span></div>
                <div><span class="text-gray-500">تاریخ ثبت:</span> <span class="text-text">${escapeHtml(l.created_label)}</span></div>
            </div>
        </div>`;

        // جلسات
        html += `<div><h4 class="font-semibold text-text mb-3 flex items-center gap-2"><i class='bx bx-calendar-event text-primary'></i> جلسات</h4>`;
        if (res.meetings.length) {
            html += '<div class="space-y-2">';
            res.meetings.forEach(m => {
                const meta = statusMeta(m.status, false);
                html += `
                <div class="border border-border rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-text">${escapeHtml(m.date_label)}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded ${meta.chip}">${meta.label}</span>
                    </div>
                    ${m.title ? `<p class="text-xs text-gray-500 mt-1">${escapeHtml(m.title)}</p>` : ''}
                    ${m.result_note ? `<p class="text-xs mt-2 bg-muted/40 rounded p-2 whitespace-pre-wrap">${escapeHtml(m.result_note)}</p>` : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-sm text-gray-400">جلسه‌ای ثبت نشده</p>';
        }
        html += `</div>`;

        // پرداخت‌ها
        html += `<div><h4 class="font-semibold text-text mb-3 flex items-center gap-2"><i class='bx bx-credit-card text-primary'></i> پرداخت‌ها</h4>`;
        if (res.sales.length) {
            html += '<div class="space-y-2">';
            res.sales.forEach(s => {
                const typeLabel = s.payment_type === 'installment' ? 'قسطی' : 'کامل';
                const srcLabel = s.source === 'manual' ? 'فیش دستی' : 'زرین‌پال';
                html += `
                <div class="border border-green-200 bg-green-50 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-green-800">${escapeHtml(s.amount)} تومان</span>
                        <span class="text-[11px] text-green-700">${typeLabel} • ${srcLabel}</span>
                    </div>
                    <div class="flex items-center justify-between mt-1 text-xs text-gray-500">
                        <span>${escapeHtml(s.date_label)}</span>
                        ${s.period ? `<span>${escapeHtml(s.period)}</span>` : ''}
                    </div>
                    ${s.note ? `<p class="text-xs mt-1 text-gray-600">${escapeHtml(s.note)}</p>` : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-sm text-gray-400">پرداختی ثبت نشده</p>';
        }
        html += `</div>`;

        // یادداشت‌ها
        html += `<div><h4 class="font-semibold text-text mb-3 flex items-center gap-2"><i class='bx bx-note text-primary'></i> یادداشت‌ها</h4>`;
        if (res.notes.length) {
            html += '<div class="space-y-2">';
            res.notes.forEach(n => {
                html += `
                <div class="border border-border rounded-lg p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-text">${escapeHtml(n.admin_name)}</span>
                        <span class="text-[11px] text-gray-400">${escapeHtml(n.date_label)}</span>
                    </div>
                    <p class="text-sm text-text whitespace-pre-wrap">${escapeHtml(n.note)}</p>
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-sm text-gray-400">یادداشتی ثبت نشده</p>';
        }
        html += `</div>`;

        $('#leadDetailBody').html(html);
    }

    function closeLeadDetail() {
        $('#leadDetailModal').addClass('hidden');
        $('body').removeClass('modal-open');
    }

    $(document).ready(function() {
        if (CAN_VIEW_ALL) {
            $.get('apis/get_experts.php', function(res) {
                if (res.ok) {
                    let opts = '<option value="all">همهٔ کارشناسان</option>';
                    res.experts.forEach(e => {
                        opts += `<option value="${e.id}">${escapeHtml(e.name)}</option>`;
                    });
                    $('#expertSelect').html(opts);
                }
            });
            $('#expertSelect').on('change', loadWeek);
        }
        meetingsWeekToday();
    });
</script>
