<?php

$leads_func = new Leads($db);
$projects_func = new Projects($db);
$users_func = new Users($db);

$project_id = (int)($_GET['project_id'] ?? 0);
$project = $project_id ? $projects_func->get_by_id($project_id) : null;

$statuses = [
    'pending' => 'در انتظار',
    'success' => 'تماس موفق',
    'not_answer' => 'عدم پاسخ',
    'following' => 'در حال پیگیری',
    'purchased' => 'قبلا خریداری شده',
    'rejected' => 'رد شده'
];
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">
                <?= $project ? 'لیدهای من در: ' . htmlspecialchars($project['name']) : 'لیدهای من' ?>
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?= $project ? 'فقط لیدهای اختصاصی شما در این پروژه' : 'لیدهای اختصاصی شما در همه پروژه‌ها' ?>
            </p>
        </div>
    </div>


    <div class="bg-surface rounded-2xl shadow-sm border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm mb-2 text-text">نام</label>
                <input id="search_name" type="text" class="w-full px-4 py-2 border rounded-lg bg-background text-text" placeholder="نام...">
            </div>
            <div>
                <label class="block text-sm mb-2 text-text">شماره</label>
                <input id="search_phone" type="text" class="w-full px-4 py-2 border rounded-lg bg-background text-text" placeholder="شماره...">
            </div>
            <div>
                <label class="block text-sm mb-2 text-text">وضعیت</label>
                <select id="search_status" class="w-full px-4 py-2 border rounded-lg bg-background text-text">
                    <option value="">همه</option>
                    <?php foreach ($statuses as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm mb-2 text-text">از تاریخ (شمسی)</label>
                <input id="search_from_date" type="text" data-jdp readonly class="w-full px-4 py-2 border rounded-lg bg-background text-text cursor-pointer" placeholder="۱۴۰۳/۰۱/۰۱">
            </div>
            <div>
                <label class="block text-sm mb-2 text-text">تا تاریخ (شمسی)</label>
                <input id="search_to_date" type="text" data-jdp readonly class="w-full px-4 py-2 border rounded-lg bg-background text-text cursor-pointer" placeholder="۱۴۰۳/۱۲/۲۹">
            </div>
            <div class="flex items-end">
                <button type="button" onclick="clearDateFilters()" class="text-sm text-gray-500 hover:text-red-500 px-2 py-2">
                    <i class='bx bx-x-circle'></i> پاک کردن تاریخ
                </button>
            </div>
        </div>
        <div class="flex justify-end mt-4">
            <button id="searchBtn" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg flex items-center gap-2">
                <i class='bx bx-search'></i> جستجو
            </button>
        </div>
    </div>


    <div class="bg-surface rounded-2xl shadow-sm border overflow-x-auto no-scrollbar">
        <table class="w-full text-center">
            <thead class="bg-muted border-b">
                <tr>
                    <th class="px-6 py-4 text-sm font-semibold text-text">نام</th>
                    <th class="px-6 py-4 text-sm font-semibold text-text">شماره</th>
                    <?php if (!$project_id): ?>
                        <th class="px-6 py-4 text-sm font-semibold text-text">پروژه</th>
                    <?php endif; ?>
                    <th class="px-6 py-4 text-sm font-semibold text-text">وضعیت</th>
                    <th class="px-6 py-4 text-sm font-semibold text-text">تاریخ ایجاد</th>
                    <th class="px-6 py-4 text-sm font-semibold text-text">عملیات</th>
                </tr>
            </thead>
            <tbody id="leadsTableBody" class="divide-y divide-border"></tbody>
        </table>
        <div class="flex items-center justify-between px-6 py-4 border-t border-border">
            <p id="paginationInfo" class="text-text">در حال بارگذاری...</p>
            <div id="paginationLinks" class="flex gap-1"></div>
        </div>
    </div>
</div>

<div id="leadModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center" style="z-index: 1000;">
    <div class="absolute inset-0 bg-surface rounded-2xl shadow-xl border my-10 pb-4 mx-4 md:w-full md:max-w-4xl md:mx-auto  overflow-y-auto no-scrollbar">


        <div class="sticky top-0 bg-surface border-b px-6 py-4 flex justify-between items-center rounded-t-2xl z-10">
            <h3 class="text-xl font-bold text-text">مدیریت لید</h3>
            <button onclick="closeModal('leadModal')" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>


        <div class="border-b border-border bg-muted px-6 w-full overflow-x-auto">
            <div class="flex items-center justify-center -mb-px" id="modalTabs">
                <button class="modal-tab-btn flex items-center gap-2 px-4 py-3  border-primary text-primary font-medium" data-tab="info">
                    <i class='bx bx-edit text-lg'></i> اطلاعات
                </button>
                <button class="modal-tab-btn flex items-center gap-2 px-4 py-3  border-transparent text-text/70 hover:text-text" data-tab="messaging">
                    <i class='bx bx-message-dots text-lg'></i> ارسال پیام
                </button>
                <button class="modal-tab-btn flex items-center gap-2 px-4 py-3  border-transparent text-text/70 hover:text-text" data-tab="notes">
                    <i class='bx bx-note text-lg'></i> یادداشت‌ها
                    <span id="notesBadge" class="bg-red-500 text-white text-xs rounded-full h-5 min-w-5 flex items-center justify-center px-1 hidden"></span>
                </button>
                <button class="modal-tab-btn flex items-center gap-2 px-4 py-3  border-transparent text-text/70 hover:text-text" data-tab="reminders">
                    <i class='bx bx-calendar text-lg'></i> یادآوری‌ها
                    <span id="remindersBadge" class="bg-green-600 text-white text-xs rounded-full h-5 min-w-5 flex items-center justify-center px-1 hidden"></span>
                </button>

                <button class="modal-tab-btn flex items-center gap-2 px-4 py-3 border-transparent text-text/70 hover:text-text" data-tab="transactions">
                    <i class='bx bx-credit-card text-lg'></i> بررسی تراکنش‌ها
                    <span id="transactionsBadge" class="bg-red-500 text-white text-xs rounded-full h-5 min-w-5 flex items-center justify-center px-1 hidden"></span>
                </button>
            </div>
        </div>


        <div class="p-6 space-y-6 " id="modalContent">

            <div class="tab-content active" id="tab-info">
                <form id="editLeadForm" class="space-y-6">
                    <input type="hidden" id="modal_lead_id">
                    <div class="grid grid-cols-1 gap-6">
                        <div class="space-y-4">

                            <div>
                                <label class="block text-sm font-medium text-text mb-2">شماره تماس</label>
                                <input type="text" id="modal_phone" class="w-full px-4 py-3 border border-border rounded-lg bg-muted text-text/70 cursor-not-allowed dir-ltr" disabled>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-text mb-2">نام لید</label>
                                <input type="text" id="modal_name" class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:ring-2 focus:ring-primary focus:border-primary" required>
                            </div>
                        </div>

                        <!-- روش‌های تماس (اختیاری) — پیش‌فرض همان شمارهٔ اصلی است -->
                        <div class="border border-border rounded-xl p-4 bg-muted/30">
                            <h4 class="text-sm font-semibold text-text mb-3 flex items-center gap-2">
                                <i class='bx bx-id-card text-primary'></i> روش‌های تماس (اختیاری)
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">شماره واتساپ</label>
                                    <input type="text" id="modal_whatsapp_phone" class="w-full px-3 py-2 border border-border rounded-lg bg-background dir-ltr text-sm" placeholder="پیش‌فرض: شماره اصلی">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">شماره تلگرام</label>
                                    <input type="text" id="modal_telegram_phone" class="w-full px-3 py-2 border border-border rounded-lg bg-background dir-ltr text-sm" placeholder="پیش‌فرض: شماره اصلی">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">آیدی تلگرام</label>
                                    <input type="text" id="modal_telegram_id" class="w-full px-3 py-2 border border-border rounded-lg bg-background dir-ltr text-sm" placeholder="@username">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">شماره بله</label>
                                    <input type="text" id="modal_bale_phone" class="w-full px-3 py-2 border border-border rounded-lg bg-background dir-ltr text-sm" placeholder="پیش‌فرض: شماره اصلی">
                                </div>
                            </div>
                        </div>

                        <!-- چک‌باکس «تماس گرفتم» — با انتخاب آن، باکس وضعیت نمایش داده می‌شود -->
                        <div class="flex items-center gap-2 bg-primary/5 border border-primary/20 rounded-lg px-4 py-3">
                            <input type="checkbox" id="modal_called" onchange="toggleStatusBox()" class="w-5 h-5 text-primary rounded focus:ring-primary">
                            <label for="modal_called" class="text-sm font-medium text-text cursor-pointer">تماس گرفتم</label>
                        </div>

                        <div id="statusBoxWrap" class="hidden">
                            <label class="block text-sm font-medium text-text mb-2">وضعیت تماس</label>
                            <select id="modal_status" class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:ring-2 focus:ring-primary focus:border-primary">
                                <?php foreach ($statuses as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                                <i class='bx bx-save'></i> ذخیره تغییرات
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <div class="tab-content" id="tab-messaging">
            <div class="p-6 space-y-6">

                <div class="bg-muted border border-border rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i class='bx bx-info-circle text-primary text-xl mt-0.5'></i>
                        <div>
                            <h4 class="font-medium text-text">راهنمای ارسال پیام</h4>
                            <p class="text-text/70 text-sm mt-1">پیام خود را در کادر زیر نوشته و سپس از طریق پلتفرم مورد نظر ارسال کنید.</p>
                        </div>
                    </div>
                </div>


                <div class=" rounded-xl p-6 border border-border">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
                        <h4 class="font-semibold flex items-center gap-2">
                            <i class='bx bx-message text-primary'></i> متن پیام
                        </h4>
                        <button type="button" onclick="openTemplatesModal('message_text', 'message')" class="text-primary hover:text-blue-800 text-sm flex items-center gap-2 transition-colors">
                            <i class='bx bx-collection'></i>
                            انتخاب از قالب‌ها
                        </button>
                    </div>
                    <textarea id="message_text" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="متن پیام خود را اینجا بنویسید..."></textarea>
                </div>


                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button onclick="sendViaTelegram()" class="bg-white border border-border rounded-xl p-6 text-center hover:shadow-lg transition-all hover:border-blue-300 group">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-200 transition-colors">
                            <i class='bx bxl-telegram text-2xl text-primary'></i>
                        </div>
                        <h4 class="font-semibold mb-1">ارسال در تلگرام</h4>
                        <p class="text-sm text-gray-600">ارسال خودکار پیام</p>
                    </button>

                    <button onclick="sendViaWhatsapp()" class="bg-white border border-border rounded-xl p-6 text-center hover:shadow-lg transition-all hover:border-green-300 group">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-green-200 transition-colors">
                            <i class='bx bxl-whatsapp text-2xl text-green-600'></i>
                        </div>
                        <h4 class="font-semibold mb-1">ارسال در واتساپ</h4>
                        <p class="text-sm text-gray-600">ارسال خودکار پیام</p>
                    </button>

                    <button onclick="sendViaBale()" class="bg-white border border-border rounded-xl p-6 text-center hover:shadow-lg transition-all hover:border-cyan-300 group">
                        <div class="w-12 h-12 bg-cyan-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-cyan-200 transition-colors">
                            <i class='bx bx-paper-plane text-2xl text-cyan-600'></i>
                        </div>
                        <h4 class="font-semibold mb-1">ارسال در بله</h4>
                        <p class="text-sm text-gray-600">کپی متن و باز کردن بله</p>
                    </button>

                    <button onclick="sendViaSMS()" class="bg-white border border-border rounded-xl p-6 text-center hover:shadow-lg transition-all hover:border-purple-300 group">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-200 transition-colors">
                            <i class='bx bx-message-dots text-2xl text-purple-600'></i>
                        </div>
                        <h4 class="font-semibold mb-1">ارسال پیامک</h4>
                        <p class="text-sm text-gray-600">ارسال خودکار پیامک</p>
                    </button>
                </div>
            </div>
        </div>


        <div class="tab-content" id="tab-notes">
            <div class="p-6 space-y-6">

                <div class=" rounded-xl p-6 border border-border">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
                        <h4 class="font-semibold flex items-center gap-2">
                            <i class='bx bx-plus-circle text-primary'></i> افزودن یادداشت جدید
                        </h4>
                        <button type="button" onclick="openTemplatesModal('note_text','note')" class="text-primary hover:text-blue-800 text-sm flex items-center gap-2 transition-colors">
                            <i class='bx bx-collection'></i>
                            انتخاب از قالب‌ها
                        </button>
                    </div>
                    <form id="addNoteForm" class="space-y-4">
                        <input type="hidden" id="note_lead_id">
                        <div>
                            <textarea id="note_text" rows="3" placeholder="متن یادداشت خود را اینجا بنویسید..." class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none" required></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-primary hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg flex items-center gap-2 transition-all shadow-md hover:shadow-lg">
                                <i class='bx bx-plus'></i> افزودن یادداشت
                            </button>
                        </div>
                    </form>
                </div>


                <div>
                    <h4 class="font-semibold mb-4 flex items-center gap-2">
                        <i class='bx bx-history text-primary'></i> تاریخچه یادداشت‌ها
                    </h4>
                    <div id="notesHistory" class="space-y-4 max-h-96 overflow-y-auto pr-2 no-scrollbar">
                        <div class="text-center py-8 text-gray-500">
                            <i class='bx bx-note text-4xl mb-2'></i>
                            <p>هیچ یادداشتی ثبت نشده است</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="tab-content " id="tab-reminders">
            <div class="p-6 space-y-6">

                <div class="bg-white rounded-2xl border border-border shadow-sm overflow-hidden">
                    <div class="p-6">



                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="changeYear(-1)"
                                    class="p-2 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                                    <i class='bx bx-chevrons-right text-xl text-gray-600 group-hover:text-primary'></i>
                                </button>
                                <button type="button" onclick="changeMonth(-1)"
                                    class="p-2 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                                    <i class='bx bx-chevron-right text-xl text-gray-600 group-hover:text-primary'></i>
                                </button>
                            </div>

                            <h3 class="font-bold text-xl  from-primary text-text"
                                id="calendarTitle">
                                در حال بارگذاری...
                            </h3>

                            <div class="flex items-center gap-3">
                                <button type="button" onclick="changeMonth(1)"
                                    class="p-2 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                                    <i class='bx bx-chevron-left text-xl text-gray-600 group-hover:text-primary'></i>
                                </button>
                                <button type="button" onclick="changeYear(1)"
                                    class="p-2 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                                    <i class='bx bx-chevrons-left text-xl text-gray-600 group-hover:text-primary'></i>
                                </button>
                            </div>
                        </div>


                        <div class="grid grid-cols-7 gap-2 mb-4">
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">ش</div>
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">ی</div>
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">د</div>
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">س</div>
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">چ</div>
                            <div class="text-center py-3 text-sm font-semibold text-gray-600">پ</div>
                            <div class="text-center py-3 text-sm font-semibold text-text bg-holiday rounded-lg">ج</div>
                        </div>


                        <div class="grid grid-cols-7 gap-2" id="calendarDays">

                        </div>
                    </div>


                    <div class=" border-t border-border px-6 py-4">
                        <div class="flex items-center justify-center gap-6 text-xs">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-100 border-2 border-blue-300 rounded"></div>
                                <span class="text-gray-600">امروز</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-red-100 border-2 border-red-300 rounded"></div>
                                <span class="text-gray-600">یادآوری دارد</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="tab-content" id="tab-transactions">
            <div class="p-6 space-y-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i class='bx bx-info-circle text-yellow-600 text-xl mt-0.5'></i>
                        <div>
                            <h4 class="font-medium text-yellow-900">توضیحات</h4>
                            <p class="text-yellow-700 text-sm mt-1">
                                این بخش تراکنش‌های موفق زرین‌پال را بر اساس شماره موبایل لید بررسی می‌کند.
                                فقط پرداخت‌های تأییدشده نمایش داده می‌شوند.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold flex items-center gap-2">
                        <i class='bx bx-history text-primary'></i> تاریخچه تراکنش‌ها
                    </h4>
                    <button onclick="checkTransactions(true)" class="text-primary hover:text-blue-800 text-sm flex items-center gap-2">
                        <i class='bx bx-refresh'></i> بروزرسانی
                    </button>
                </div>

                <div id="transactionsLoading" class="text-center py-12 text-gray-500">
                    <i class='bx bx-loader-alt bx-spin text-4xl'></i>
                    <p class="mt-4">در حال بررسی تراکنش‌ها...</p>
                </div>

                <div id="transactionsEmpty" class="text-center py-12 text-gray-500 hidden">
                    <i class='bx bx-credit-card-front text-6xl text-gray-300 mb-4'></i>
                    <p>هیچ تراکنش موفقی برای این شماره یافت نشد</p>
                </div>

                <div id="transactionsList" class="space-y-4 hidden">
                    <!-- تراکنش‌ها اینجا تزریق میشن -->
                </div>

                <div id="transactionsSummary" class="mt-6 p-4 bg-green-50 border border-green-200 rounded-xl hidden">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-green-800">تعداد تراکنش‌های موفق:</span>
                        <span id="transactionsCount" class="font-bold text-green-900">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>


<div id="dayRemindersModal" class="hidden fixed inset-0 backdrop-blur-sm flex items-center justify-center p-4 z-[1100]" style="z-index: 1100;">
    <div class="bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">

        <div class="bg-surface px-6 py-4 flex justify-between items-center">
            <div>
                <h3 class="text-xl font-bold text-text" id="dayRemindersTitle">یادآوری‌های ۱۴۰۴/۰۸/۲۰</h3>
            </div>
            <button onclick="closeModal('dayRemindersModal')" class="text-text hover:text-blue-200 transition-colors">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>


        <div class=" flex-1 overflow-y-auto p-6 no-scrollbar">

            <div id="emptyRemindersState" class="hidden text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class='bx bx-bell-off text-3xl text-gray-400'></i>
                </div>
                <h4 class="text-lg font-semibold text-gray-600 mb-2">هیچ یادآوری ثبت نشده</h4>
                <p class="text-gray-500 text-sm mb-6">هنوز هیچ یادآوری برای این تاریخ ثبت نشده است.</p>
            </div>


            <div id="dayRemindersList" class="space-y-4">

            </div>
        </div>


        <div class="border-t border-border px-6 py-4  flex justify-between items-center">
            <button onclick="closeModal('dayRemindersModal')"
                class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100 transition-colors font-medium">
                بستن
            </button>
            <button onclick="openAddReminderModal()"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-xl flex items-center gap-2 transition-all font-medium shadow-md hover:shadow-lg">
                <i class='bx bx-plus'></i>
                افزودن یادآوری جدید
            </button>
        </div>
    </div>
</div>


<div id="addReminderModal" class="hidden fixed inset-0 backdrop-blur-sm flex items-center justify-center p-4 z-[1200]" style="z-index: 1200;">
    <div class="bg-surface rounded-2xl shadow-2xl border border-border w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden">
        <div class=" border-b border-border px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-800">ثبت یادآوری جدید</h3>
            <button onclick="closeModal('addReminderModal')" class="text-gray-500 hover:text-gray-700 transition-colors">
                <i class='bx bx-x text-2xl'></i>
            </button>

        </div>

        <div class="flex-1 overflow-y-auto p-6 no-scrollbar">
            <form id="reminderForm" class="space-y-4">
                <input type="hidden" id="reminder_lead_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">تاریخ یادآوری</label>
                    <input type="text" id="reminder_date"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl  text-gray-600 cursor-not-allowed"
                        required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ساعت</label>
                    <input type="time" id="reminder_time"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                        required>
                </div>

                <div>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">

                        <label class="block text-sm font-medium text-gray-700 mb-2">متن یادآوری</label>
                        <button type="button" onclick="openTemplatesModal('reminder_text', 'reminder')" class="text-primary hover:text-blue-800 text-sm flex items-center gap-2 transition-colors">
                            <i class='bx bx-collection'></i>
                            انتخاب از قالب‌ها
                        </button>
                    </div>
                    <textarea id="reminder_text" rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white resize-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                        placeholder="یادآوری برای تماس با مشتری، پیگیری پروژه و..."
                        required></textarea>
                </div>

                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-xl font-medium transition-all flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                    <i class='bx bx-bell'></i>
                    ثبت یادآوری
                </button>
            </form>
        </div>
    </div>
</div>


<div id="templatesModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" style="z-index: 1300;">
    <div class="absolute inset-0 bg-surface rounded-2xl shadow-xl border my-10 pb-4 mx-4 md:w-full md:max-w-4xl md:mx-auto  overflow-y-auto no-scrollbar">
        <div class="bg-surface border-b px-6 py-4 flex justify-between items-center rounded-t-2xl">
            <h3 class="text-xl font-bold text-text">انتخاب از قالب‌های متن</h3>
            <button onclick="closeModal('templatesModal')" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-2xl'></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto no-scrollbar">
            <div class="mb-4">
                <input type="text" id="templateSearch" placeholder="جستجو در قالب‌ها..." class="w-full px-4 py-2 border rounded-lg bg-background">
            </div>
            <div id="templatesList" class="space-y-3">

            </div>
        </div>

        <div class="border-t px-6 py-4 bg-muted/30">
            <button onclick="closeModal('templatesModal')" class="w-full 0 hover:bg-gray-600 text-white px-4 py-2.5 rounded-lg">
                بستن
            </button>
        </div>
    </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index: 9999;"></div>

<script>
    let currentPage = 1;
    let currentFilters = {};
    let currentLeadData = null;
    let currentJalaliDate = new persianDate();
    let currentTextareaId = null;
    let todayJalali = new persianDate();
    let selectedDate = null;
    let remindersByDate = {};


    function showSnackbar(message, type = 'success') {
        const snackbar = $('#snackbar');
        snackbar.text(message).removeClass('hidden')
            .removeClass('bg-green-600 bg-red-600 bg-primary')
            .addClass(type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-primary');
        setTimeout(() => snackbar.addClass('hidden'), 3000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(date) {
        return new Date(date).toLocaleString('fa-IR', {
            hour12: false
        });
    }

    function getStatusClass(status) {
        const map = {
            'pending': 'bg-blue-100 text-blue-800',
            'success': 'bg-green-100 text-green-800',
            'not_answer': 'bg-red-100 text-red-800',
            'following': 'bg-yellow-100 text-yellow-800',
            'purchased': 'bg-purple-100 text-purple-800',
            'rejected': 'bg-red-200 text-red-900'
        };
        return map[status] || 'bg-gray-100 text-gray-800';
    }

    function getStatusText(status) {
        const map = {
            'pending': 'در انتظار',
            'success': 'تماس موفق',
            'not_answer': 'عدم پاسخ',
            'following': 'در حال پیگیری',
            'purchased': 'قبلا خریداری شده',
            'rejected': 'رد شده'
        };
        return map[status] || status;
    }


    function loadPage(page = 1, filters = {}) {
        currentPage = page;
        currentFilters = {
            ...filters,
            page
        };

        const params = new URLSearchParams({
            ...filters,
            page,
            project_id: <?= $project_id ?>,
            my_leads: 1
        }).toString();

        $.get('apis/search_my_leads.php?' + params, function(data) {
            if (data.ok) {
                renderTable(data.leads);
                renderPagination(data.page, data.pages, data.total);
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در بارگذاری داده‌ها', 'error');
        });
    }

    function renderTable(leads) {
        const tbody = $('#leadsTableBody').empty();
        if (leads.length === 0) {
            const cols = <?= $project_id ? 5 : 6 ?>;
            tbody.append(`<tr><td colspan="${cols}" class="py-8 text-gray-500">هیچ لیدی یافت نشد.</td></tr>`);
            return;
        }

        leads.forEach(lead => {
            const row = `
                <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-6 py-4">${escapeHtml(lead.name)}</td>
                    <td class="px-6 py-4">${lead.phone}</td>
                    <?php if (!$project_id): ?>
                        <td class="px-6 py-4 text-xs">${escapeHtml(lead.project_name || '-')}</td>
                    <?php endif; ?>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs ${getStatusClass(lead.status)}">
                            ${getStatusText(lead.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4">${formatDate(lead.created_at)}</td>
                    <td class="px-6 py-4">
                        <button onclick='openLeadModal(${JSON.stringify(lead)})'
                            class="text-primary hover:underline text-sm">
                            <i class='bx bx-edit'></i> مدیریت
                        </button>
                    </td>
                </tr>`;
            tbody.append(row);
        });
    }

    function renderPagination(page, pages, total) {
        $('#paginationInfo').text(`صفحه ${page} از ${pages} (کل: ${total})`);
        const links = $('#paginationLinks').empty();

        for (let i = 1; i <= pages; i++) {
            const active = i === page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text';
            links.append(
                `<a onclick="loadPage(${i}, currentFilters)" class="px-3 py-1.5 rounded-lg text-sm font-medium ${active} transition-colors cursor-pointer">${i}</a>`
            );
        }
    }


    $('#searchBtn').on('click', function() {
        const filters = {
            name: $('#search_name').val().trim(),
            phone: $('#search_phone').val().trim(),
            status: $('#search_status').val(),
            from_date: toEnglishDigits($('#search_from_date').val().trim()),
            to_date: toEnglishDigits($('#search_to_date').val().trim()),
            project_id: <?= $project_id ?>,
            my_leads: 1
        };
        loadPage(1, filters);
    });

    function clearDateFilters() {
        $('#search_from_date').val('');
        $('#search_to_date').val('');
    }

    // فعال‌سازی تقویم شمسی روی فیلترهای تاریخ
    if (window.jalaliDatepicker) {
        jalaliDatepicker.startWatch({ time: false, persianDigits: true });
    }


    $('#search_name, #search_phone').on('keypress', e => {
        if (e.which === 13) $('#searchBtn').click();
    });


    function closeModal(id) {
        $('#' + id).addClass('hidden');
        $('body').removeClass('modal-open');

        if (id === 'leadModal') {

            setTimeout(() => {
                const tabBtns = document.querySelectorAll('.tab-btn');
                const tabContents = document.querySelectorAll('.tab-content');

                tabBtns.forEach((btn, index) => {
                    if (index === 0) {
                        btn.classList.remove('border-transparent', 'text-text/70');
                        btn.classList.add('border-primary', 'text-primary');
                    } else {
                        btn.classList.remove('border-primary', 'text-primary');
                        btn.classList.add('border-transparent', 'text-text/70');
                    }
                });

                tabContents.forEach((content, index) => {
                    if (index === 0) {
                        content.classList.add('active');
                    } else {
                        content.classList.remove('active');
                    }
                });
            }, 300);
        }

        if (id === 'addReminderModal') {
            $('#reminderForm')[0].reset();
        }
    }


    function initModalTabs() {
        const tabBtns = document.querySelectorAll('.modal-tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');

                tabBtns.forEach(b => {
                    b.classList.remove('text-primary');
                    b.classList.add('text-gray-500', 'hover:text-gray-700');
                });



                tabContents.forEach(content => {
                    content.classList.remove('active');
                });


                btn.classList.remove('text-gray-500', 'hover:text-gray-700');
                btn.classList.add('text-primary');



                const targetContent = document.getElementById(`tab-${tabId}`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });


        if (tabBtns.length > 0) {
            tabBtns[0].click();
        }
    }


    function updateBadges(notesCount = 0, remindersCount = 0) {
        const notesBadge = $('#notesBadge');
        const remindersBadge = $('#remindersBadge');

        if (notesCount > 0) {
            notesBadge.text(notesCount).removeClass('hidden');
        } else {
            notesBadge.addClass('hidden');
        }

        if (remindersCount > 0) {
            remindersBadge.text(remindersCount).removeClass('hidden');
        } else {
            remindersBadge.addClass('hidden');
        }

        $('#summary_notes').text(notesCount);
    }

    function updateLeadSummary(lead) {
        $('#summary_created').text(formatDate(lead.created_at));
        $('#summary_updated').text(formatDate(lead.updated_at || lead.created_at));
        $('#summary_project').text(lead.project_name || 'نامشخص');
    }


    // وقتی مودال باز شد، بج‌ها رو بروزرسانی کن
    function updateBadges(notesCount = null, remindersCount = null, transactionsCount = null) {
        if (notesCount !== null && notesCount > 0) {
            $('#notesBadge').removeClass('hidden').text(notesCount > 99 ? '99+' : notesCount);
        } else {
            $('#notesBadge').addClass('hidden');
        }

        if (remindersCount !== null && remindersCount > 0) {
            $('#remindersBadge').removeClass('hidden').text(remindersCount > 99 ? '99+' : remindersCount);
        } else {
            $('#remindersBadge').addClass('hidden');
        }

        if (transactionsCount !== null && transactionsCount > 0) {
            $('#transactionsBadge').removeClass('hidden').text(transactionsCount > 99 ? '99+' : transactionsCount);
            newTransactionsCount = transactionsCount;
        } else {
            $('#transactionsBadge').addClass('hidden');
        }
    }

    function openLeadModal(lead) {
        currentLeadData = lead;

        window.currentLead = lead;

        $('#modal_lead_id').val(lead.id);
        $('#modal_name').val(lead.name);
        $('#modal_phone').val(lead.phone);
        $('#modal_status').val(lead.status);
        $('#note_lead_id').val(lead.id);
        $('#reminder_lead_id').val(lead.id);

        // روش‌های تماس — اگر خالی باشند، شمارهٔ اصلی پیش‌فرض است
        $('#modal_whatsapp_phone').val(lead.whatsapp_phone || '');
        $('#modal_telegram_phone').val(lead.telegram_phone || '');
        $('#modal_telegram_id').val(lead.telegram_id || '');
        $('#modal_bale_phone').val(lead.bale_phone || '');

        // ریست چک‌باکس «تماس گرفتم» و مخفی‌سازی باکس وضعیت در هر بار باز شدن
        $('#modal_called').prop('checked', false);
        $('#statusBoxWrap').addClass('hidden');


        updateLeadSummary(lead);


        loadNotesHistory(lead.id);
        loadReminders(lead.id);
        renderCalendar();
        checkTransactions(true);

        initModalTabs();

        $('#leadModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }


    function toggleStatusBox() {
        if ($('#modal_called').is(':checked')) {
            $('#statusBoxWrap').removeClass('hidden');
        } else {
            $('#statusBoxWrap').addClass('hidden');
        }
    }

    $('#editLeadForm').on('submit', function(e) {
        e.preventDefault();
        const called = $('#modal_called').is(':checked') ? 1 : 0;
        const data = {
            id: $('#modal_lead_id').val(),
            name: $('#modal_name').val(),
            status: $('#modal_status').val(),
            whatsapp_phone: $('#modal_whatsapp_phone').val().trim(),
            telegram_phone: $('#modal_telegram_phone').val().trim(),
            telegram_id: $('#modal_telegram_id').val().trim(),
            bale_phone: $('#modal_bale_phone').val().trim(),
            called: called
        };

        $.post('apis/edit_leads.php', data, function(res) {
            if (res.ok) {
                showSnackbar('لید با موفقیت بروزرسانی شد');
                // ریست چک‌باکس تا تماس بعدی دوباره ثبت شود
                $('#modal_called').prop('checked', false);
                $('#statusBoxWrap').addClass('hidden');
                setTimeout(() => loadPage(currentPage, currentFilters), 1000);
            } else {
                showSnackbar(res.error || 'خطا در ذخیره', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    });

    // شمارهٔ هر کانال: اگر کارشناس شمارهٔ اختصاصی وارد کرده باشد همان، وگرنه شمارهٔ اصلی
    function channelPhone(field) {
        const v = ($('#' + field).val() || '').trim();
        return v !== '' ? v : $('#modal_phone').val();
    }

    function sendViaTelegram() {
        const message = $('#message_text').val().trim();
        const tgId = ($('#modal_telegram_id').val() || '').trim();
        const phone = channelPhone('modal_telegram_phone');

        if (!message) {
            showSnackbar('لطفا متن پیام را وارد کنید', 'error');
            return;
        }

        // اگر آیدی تلگرام وارد شده باشد، مستقیم به آن می‌رویم
        if (tgId) {
            const uname = tgId.replace(/^@/, '');
            window.open('https://t.me/' + encodeURIComponent(uname), '_blank');
            showSnackbar('در حال باز کردن تلگرام...');
            return;
        }

        $.post('apis/send_telegram_message.php', {
            phone,
            message,
            lead_id: $('#modal_lead_id').val()
        }, function(res) {
            if (res.ok) {
                window.open(res.redirect_url, '_blank');
                showSnackbar('در حال باز کردن تلگرام...');
            } else {
                showSnackbar(res.error || 'خطا در آماده‌سازی لینک تلگرام', 'error');
            }
        });
    }

    function sendViaWhatsapp() {
        const message = $('#message_text').val().trim();
        const phone = channelPhone('modal_whatsapp_phone');

        if (!message) {
            showSnackbar('لطفا متن پیام را وارد کنید', 'error');
            return;
        }

        $.post('apis/send_whatsapp_message.php', {
            phone,
            message,
            lead_id: $('#modal_lead_id').val()
        }, function(res) {
            if (res.ok) {
                window.open(res.redirect_url, '_blank');
                showSnackbar('در حال باز کردن واتساپ...');
            } else {
                showSnackbar(res.error || 'خطا در آماده‌سازی لینک واتساپ', 'error');
            }
        });
    }

    function sendViaBale() {
        const message = $('#message_text').val().trim();
        const phone = channelPhone('modal_bale_phone');

        if (!message) {
            showSnackbar('لطفا متن پیام را وارد کنید', 'error');
            return;
        }

        $.post('apis/send_bale_message.php', {
            phone,
            message,
            lead_id: $('#modal_lead_id').val()
        }, function(res) {
            if (res.ok) {
                // متن در کلیپ‌بورد کپی می‌شود (بله لینک گفت‌وگو با شماره ندارد)
                if (navigator.clipboard && res.copy_text) {
                    navigator.clipboard.writeText(res.copy_text).catch(() => {});
                }
                window.open(res.redirect_url, '_blank');
                showSnackbar('متن پیام کپی شد؛ در حال باز کردن بله...');
            } else {
                showSnackbar(res.error || 'خطا در آماده‌سازی بله', 'error');
            }
        });
    }

    function sendViaSMS() {
        const message = $('#message_text').val().trim();
        const phone = $('#modal_phone').val();

        if (!message) {
            showSnackbar('لطفا متن پیام را وارد کنید', 'error');
            return;
        }

        $.post('apis/send_sms.php', {
            phone: phone,
            message: message,
            lead_id: $('#modal_lead_id').val()
        }, function(res) {
            if (res.ok) {
                showSnackbar('پیامک با موفقیت ارسال شد');
            } else {
                showSnackbar(res.error || 'خطا در ارسال پیامک', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    }

    function loadNotesHistory(lead_id) {
        $('#notesHistory').html('<div class="text-center py-4"><div class="loading mx-auto"></div><p class="text-gray-500 mt-2">در حال بارگذاری...</p></div>');

        $.get('apis/get_lead_notes.php', {
            lead_id
        }, function(res) {
            if (res.ok && res.notes && res.notes.length > 0) {
                let html = '';
                res.notes.forEach(note => {
                    const isOwn = note.user_id == <?= $_SESSION['id'] ?>;
                    const canEditDelete = isOwn; // فقط صاحب یادداشت بتونه ویرایش/حذف کنه

                    html += `
                <div class="bg-muted/50 border border-border rounded-lg p-4 relative">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-medium text-text">${escapeHtml(note.admin_name || 'نامشخص')}</p>
                            <p class="text-xs text-gray-500">${formatDate(note.created_at)}</p>
                        </div>
                        ${canEditDelete ? `
                        <div class="flex gap-2">
                            <button onclick="editNote(${note.id}, \`${escapeHtmlJs(note.note)}\`)" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class='bx bx-edit text-base'></i>
                            ویرایش
                            </button>
                            <button onclick="deleteNote(${note.id})" class="text-red-600 hover:text-red-800 text-sm">
                              <i class='bx bx-trash text-base'></i>
                            حذف
                            </button>
                        </div>
                        ` : ''}
                    </div>
                    <p class="text-text whitespace-pre-wrap" id="note-text-${note.id}">${escapeHtml(note.note)}</p>
                    ${canEditDelete ? `
                    <div id="edit-form-${note.id}" class="mt-3 hidden">
                        <textarea class="w-full px-3 py-2 border rounded-lg" rows="3" id="edit-text-${note.id}">${escapeHtml(note.note)}</textarea>
                        <div class="mt-2 flex gap-2 justify-end">
                            <button onclick="saveEditNote(${note.id})" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1.5 rounded text-sm">ذخیره</button>
                            <button onclick="cancelEditNote(${note.id})" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-1.5 rounded text-sm">لغو</button>
                        </div>
                    </div>
                    ` : ''}
                </div>`;
                });
                $('#notesHistory').html(html);
                updateBadges(res.notes.length, $('#remindersBadge').text() || 0);
            } else {
                $('#notesHistory').html('<div class="text-center py-8"><i class="bx bx-note text-4xl text-gray-300 mb-2"></i><p class="text-gray-500">هیچ یادداشتی ثبت نشده است</p></div>');
                updateBadges(0, $('#remindersBadge').text() || 0);
            }
        }).fail(() => {
            $('#notesHistory').html('<div class="text-center py-8 text-red-500">خطا در بارگذاری یادداشت‌ها</div>');
        });
    }

    $('#addNoteForm').on('submit', function(e) {
        e.preventDefault();

        const data = {
            lead_id: $('#note_lead_id').val(),
            note: $('#note_text').val()
        };

        $.post('apis/add_lead_note.php', data, function(res) {
            if (res.ok) {
                showSnackbar('یادداشت با موفقیت ثبت شد');
                $('#note_text').val('');
                loadNotesHistory(data.lead_id);
            } else {
                showSnackbar(res.error || 'خطا در ثبت یادداشت', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeHtmlJs(text) {
        return text.replace(/`/g, '\\`').replace(/\${/g, '\\${');
    }

    function toEnglishDigits(str) {
        const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
        const englishDigits = '0123456789';

        return str
            .replace(/[۰-۹]/g, function(w) {
                return englishDigits[persianDigits.indexOf(w)];
            })
            .replace(/[٠-٩]/g, function(w) {
                return englishDigits[arabicDigits.indexOf(w)];
            });
    }


    function renderCalendar() {
        const year = currentJalaliDate.year();
        const monthIndex = currentJalaliDate.month();
        const monthForDate = monthIndex;
        const firstDay = new persianDate([year, monthForDate, 1]).day();
        const daysInMonth = new persianDate([year, monthForDate, 1]).daysInMonth();



        const todayJalali = new persianDate();
        const todayStr = toEnglishDigits(
            todayJalali.format('YYYY/M/D')
        );

        const currentMonthStr = toEnglishDigits(
            todayJalali.clone().date(1).format('YYYY/MM')
        );

        $('#calendarTitle').text(currentJalaliDate.format('MMMM YYYY'));



        const daysContainer = $('#calendarDays').empty();
        let html = '';


        for (let i = 1; i < firstDay; i++) {
            html += `<div class="aspect-square p-1">
                    <div class="w-full h-full  rounded-lg opacity-50"></div>
                 </div>`;
        }


        for (let day = 1; day <= daysInMonth; day++) {
            const jalaliDate = `${year}/${String(monthForDate)}/${String(day)}`;
            const reminders = remindersByDate[jalaliDate] || [];
            const reminderCount = reminders.length;
            const isToday = jalaliDate === todayStr;
            const isSelected = selectedDate === jalaliDate;
            const hasReminders = reminderCount > 0;

            let dayStyle = 'text-gray-700';
            let indicatorStyle = '';
            let indicatorText = '';

            if (hasReminders) {
                indicatorStyle = 'bg-red-300';

            }

            if (isToday) {
                dayStyle = 'text-primary font-bold';
            }

            if (isSelected) {
                dayStyle += '';
            }

            html += `
            <div class="aspect-square p-1">
                <div class="day-cell w-full h-full ${dayStyle} rounded-xl flex flex-col items-center justify-center cursor-pointer transition-all duration-200 hover:scale-120 relative overflow-hidden"
                     onclick="selectDate('${jalaliDate}', ${hasReminders})"
                     data-date="${jalaliDate}"
                     data-reminders="${reminderCount}">
                    
                    
                    <div class="text-sm font-semibold mb-1">
                        ${day}  ${hasReminders ? `<div class="w-2 h-2 ${indicatorStyle} rounded-full"></div>`: ''}
                    </div>
                </div>
            </div>`;
        }

        daysContainer.html(html);
    }


    function selectDate(dateStr, hasReminders) {
        selectedDate = dateStr;


        $('.day-cell').removeClass('ring-2 ring-blue-500 ring-opacity-50 scale-105');


        $(`.day-cell[data-date="${dateStr}"]`).addClass('ring-2 ring-blue-500 ring-opacity-50 scale-105');

        $('#reminder_date').val(dateStr);

        showDayReminders(dateStr);
    }



    function changeMonth(delta) {
        currentJalaliDate = currentJalaliDate.add('month', delta);
        renderCalendar();
    }


    function changeYear(delta) {
        currentJalaliDate = currentJalaliDate.add('year', delta);
        renderCalendar();
    }



    function loadReminders(lead_id) {
        $.get('apis/get_reminders.php', {
            lead_id
        }, function(res) {
            if (res.ok && res.reminders && res.reminders.length > 0) {

                remindersByDate = {};
                res.reminders.forEach(r => {
                    const date = r.reminder_date || r.reminder_datetime.split(' ')[0];
                    if (!remindersByDate[date]) remindersByDate[date] = [];
                    remindersByDate[date].push(r);
                });
                updateBadges($('#notesBadge').text() || 0, res.reminders.length);
            } else {
                remindersByDate = {};
                updateBadges($('#notesBadge').text() || 0, 0);
            }
            renderCalendar();
        }).fail(() => {
            showSnackbar('خطا در بارگذاری یادآوری‌ها', 'error');
            remindersByDate = {};
            renderCalendar();
        });
    }


    function showDayReminders(dateStr) {
        const reminders = remindersByDate[dateStr] || [];
        const remindersCount = reminders.length;


        $('#dayRemindersTitle').text(`یادآوری‌های ${dateStr}`);
        $('#remindersCount').text(remindersCount);

        const dayRemindersList = $('#dayRemindersList');
        const emptyRemindersState = $('#emptyRemindersState');

        if (remindersCount > 0) {

            emptyRemindersState.addClass('hidden');
            dayRemindersList.removeClass('hidden');


            let html = '';
            reminders.forEach((reminder, index) => {
                html += `
                <div class="bg-white border border-border rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-200">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-6 h-6 bg-blue-100 text-primary rounded-full flex items-center justify-center text-xs font-bold">
                                    ${index + 1}
                                </div>
                                <p class="text-sm font-medium leading-relaxed flex-1">
                                    ${escapeHtml(reminder.reminder_text)}
                                </p>
                            </div>
                            
                            
                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 mr-9">
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-time text-xs'></i>
                                    ساعت: ${reminder.reminder_time}
                                </span>
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-user text-xs'></i>
                                    ${reminder.lead_name || 'لید جاری'}
                                </span>
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-calendar text-xs'></i>
                                    ${formatDate(reminder.created_at)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="flex justify-end items-center gap-2 pt-3 border-t border-gray-100">
                        <button onclick="editReminder(${reminder.id})" 
                                class="text-primary hover:text-blue-800 text-sm flex items-center gap-1 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                            <i class='bx bx-edit text-base'></i>
                            ویرایش
                        </button>
                        <button onclick="deleteReminder(${reminder.id})" 
                                class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                            <i class='bx bx-trash text-base'></i>
                            حذف
                        </button>
                    </div>
                </div>`;
            });
            dayRemindersList.html(html);
        } else {

            dayRemindersList.addClass('hidden');
            emptyRemindersState.removeClass('hidden');
        }


        $('#dayRemindersModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }


    function deleteReminder(id) {
        if (!confirm('آیا از حذف این یادآوری اطمینان دارید؟')) return;

        $.post('apis/delete_reminder.php', {
            id
        }, function(res) {
            if (res.ok) {
                showSnackbar('یادآوری با موفقیت حذف شد');
                const currentLeadId = $('#reminder_lead_id').val();
                loadReminders(currentLeadId);
                closeModal('dayRemindersModal');
            } else {
                showSnackbar(res.error || 'خطا در حذف یادآوری', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    }


    function openAddReminderModal() {
        const dateStr = $('#reminder_date').val();

        if (!dateStr) {
            showSnackbar('لطفا ابتدا یک تاریخ از تقویم انتخاب کنید', 'error');
            return;
        }


        $('#reminderForm')[0].reset();
        $('#reminder_time').val('12:00');
        $('#reminder_date').val(dateStr)

        $('#addReminderModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }

    function showDayReminders(dateStr) {
        const reminders = remindersByDate[dateStr] || [];
        $('#dayRemindersTitle').text(`یادآوری‌های ${dateStr}`);
        let index = 0;
        if (reminders.length > 0) {
            let html = '';
            reminders.forEach(reminder => {

                html += `
            <div class="bg-background p-3 rounded-lg border mb-3">
                <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-6 h-6 bg-blue-100 text-primary rounded-full flex items-center justify-center text-xs font-bold">
                                    ${index + 1}
                                </div>
                                <p class="text-sm font-medium leading-relaxed flex-1">
                                    ${escapeHtml(reminder.reminder_text)}
                                </p>
                            </div>
                            
                            
                            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 mr-9">
            
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-user text-xs'></i>
                                    ${reminder.user.name || ' '}
                                </span>
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-box text-xs'></i>
                                    ${reminder.lead_name || 'لید جاری'}
                                </span>
                                <span class="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded">
                                    <i class='bx bx-time text-xs'></i>
                                    ${reminder.reminder_time}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="flex justify-end items-center gap-2 pt-3 border-t border-gray-100">
                        <button onclick="editReminder(${reminder.id})" 
                                class="text-primary hover:text-blue-800 text-sm flex items-center gap-1 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                            <i class='bx bx-edit text-base'></i>
                            ویرایش
                        </button>
                        <button onclick="deleteReminder(${reminder.id})" 
                                class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                            <i class='bx bx-trash text-base'></i>
                            حذف
                        </button>
                    </div>
            </div>`;
                index += 1;
            });
            $('#dayRemindersList').html(html);
        } else {
            $('#dayRemindersList').html(`
            <div class="text-center py-8">
                <i class='bx bx-bell-off text-4xl text-gray-300 mb-2'></i>
                <p class="text-gray-500">هیچ یادآوری ثبت نشده است</p>
            </div>
        `);
        }

        $('#dayRemindersModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }


    $('#reminderForm').on('submit', function(e) {
        e.preventDefault();

        const data = {
            lead_id: $('#reminder_lead_id').val(),
            reminder_date: $('#reminder_date').val(),
            reminder_time: $('#reminder_time').val(),
            reminder_text: $('#reminder_text').val().trim()
        };


        if (!data.reminder_text) {
            showSnackbar('لطفا متن یادآوری را وارد کنید', 'error');
            return;
        }

        $.post('apis/add_reminder.php', data, function(res) {
            if (res.ok) {
                showSnackbar('یادآوری با موفقیت ثبت شد ✅');


                closeModal('addReminderModal');


                const currentLeadId = $('#reminder_lead_id').val();
                loadReminders(currentLeadId);


                const currentDate = $('#reminder_date').val();
                if (currentDate) {
                    setTimeout(() => {
                        showDayReminders(currentDate);
                    }, 500);
                }
            } else {
                showSnackbar(res.error || 'خطا در ثبت یادآوری', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    });


    function editReminder(reminderId) {

        const currentDate = $('#reminder_date').val();
        const reminders = remindersByDate[currentDate] || [];
        const reminder = reminders.find(r => r.id == reminderId);

        if (!reminder) {
            showSnackbar('یادآوری مورد نظر یافت نشد', 'error');
            return;
        }


        $('#reminder_date').val(reminder.reminder_date || reminder.reminder_datetime.split(' ')[0]);
        $('#reminder_time').val(reminder.reminder_time);
        $('#reminder_text').val(reminder.reminder_text);


        $('#reminderForm button[type="submit"]')
            .html('<i class=\"bx bx-edit\"></i> بروزرسانی یادآوری')
            .removeClass('bg-green-600 hover:bg-green-700')
            .addClass('bg-primary hover:bg-blue-700');


        $('#reminderForm').off('submit').on('submit', function(e) {
            e.preventDefault();

            const updateData = {
                id: reminderId,
                reminder_date: $('#reminder_date').val(),
                reminder_time: $('#reminder_time').val(),
                reminder_text: $('#reminder_text').val().trim()
            };

            $.post('apis/edit_reminder.php', updateData, function(res) {
                if (res.ok) {
                    showSnackbar('یادآوری با موفقیت بروزرسانی شد');



                    closeModal('addReminderModal');
                    closeModal("dayRemindersModal");
                    const currentLeadId = $('#reminder_lead_id').val();
                    loadReminders(currentLeadId);



                } else {
                    showSnackbar(res.error || 'خطا در بروزرسانی یادآوری', 'error');
                }
            });
        });


        $('#addReminderModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }


    function resetReminderForm() {
        $('#reminderForm')[0].reset();
        $('#reminderForm button[type="submit"]')
            .html('<i class=\"bx bx-bell\"></i> ثبت یادآوری')
            .removeClass('bg-primary hover:bg-blue-700')
            .addClass('bg-green-600 hover:bg-green-700');


        $('#reminderForm').off('submit').on('submit', function(e) {
            e.preventDefault();

        });
    }

    function deleteReminder(id) {
        if (!confirm('آیا از حذف این یادآوری اطمینان دارید؟ این عمل قابل بازگشت نیست.')) return;

        $.post('apis/delete_reminder.php', {
            id
        }, function(res) {
            if (res.ok) {
                showSnackbar('یادآوری با موفقیت حذف شد 🗑️');
                const currentLeadId = $('#reminder_lead_id').val();
                loadReminders(currentLeadId);


                const currentDate = $('#reminder_date').val();
                if (currentDate && $('#dayRemindersModal').is(':visible')) {
                    setTimeout(() => {
                        showDayReminders(currentDate);
                    }, 500);
                }
            } else {
                showSnackbar(res.error || 'خطا در حذف یادآوری', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    }


    function closeModal(modalId) {
        $('#' + modalId).addClass('hidden');
        $('body').removeClass('modal-open');


        if (modalId === 'addReminderModal') {
        }
    }

    function loadReminders(lead_id) {
        $.get('apis/get_reminders.php', {
            lead_id
        }, function(res) {
            if (res.ok && res.reminders && res.reminders.length > 0) {

                remindersByDate = {};
                res.reminders.forEach(r => {
                    const date = r.reminder_datetime;
                    if (!remindersByDate[date]) remindersByDate[date] = [];
                    remindersByDate[date].push(r);
                });
                updateBadges($('#notesBadge').text() || 0, res.reminders.length);
            } else {
                remindersByDate = {};
                updateBadges($('#notesBadge').text() || 0, 0);
            }
            renderCalendar();
        }).fail(() => {
            showSnackbar('خطا در بارگذاری یادآوری‌ها', 'error');
            remindersByDate = {};
            renderCalendar();
        });
    }

    let currentCategoryFilter = ''; // جدید: برای فیلتر کردن بر اساس دسته‌بندی

    function openTemplatesModal(textareaId, category = 'message') {
        currentTextareaId = textareaId;
        currentCategoryFilter = category; // مثلاً 'message', 'note', 'reminder'
        loadTemplates('', category); // با فیلتر دسته‌بندی
        $('#templatesModal').removeClass('hidden');
        $('body').addClass('modal-open');
    }

    function loadTemplates(search = '', category = '') {
        $('#templatesList').html('<div class="text-center py-4"><div class="loading mx-auto"></div><p class="text-gray-500 mt-2">در حال بارگذاری قالب‌ها...</p></div>');

        $.get('apis/get_templates.php', {
            search: search,
            category: category || '' // فقط قالب‌های این دسته را بیاور
        }, function(res) {
            if (res.ok && res.templates && res.templates.length > 0) {
                let html = '';
                res.templates.forEach(template => {
                    const escapedContent = template.content
                        .replace(/`/g, '\\`')
                        .replace(/\${/g, '\\${');

                    const categoryLabel = template.category === 'message' ? 'پیامک' :
                        template.category === 'note' ? 'یادداشت' :
                        template.category === 'reminder' ? 'یادآوری' : 'عمومی';

                    html += `
                <div class="bg-background border border-border rounded-lg p-4 cursor-pointer hover:border-primary transition-all template-item">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-medium text-text">${escapeHtml(template.title)}</h4>
                        <span class="text-xs text-gray-500 bg-muted/50 px-2 py-1 rounded">${categoryLabel}</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3 whitespace-pre-wrap">${escapeHtml(template.content)}</p>
                    <button onclick="selectTemplate(\`${escapedContent}\`)" 
                            class="w-full bg-primary hover:bg-primary-dark text-white py-2 rounded-lg text-sm">
                        انتخاب این قالب
                    </button>
                </div>`;
                });
                $('#templatesList').html(html);
            } else {
                const emptyMsg = category ?
                    `هیچ قالبی در دسته "${category === 'message' ? 'پیامک' : category === 'note' ? 'یادداشت' : 'یادآوری'}" یافت نشد` :
                    'هیچ قالبی یافت نشد';
                $('#templatesList').html(`<div class="text-center py-8 text-gray-500">${emptyMsg}</div>`);
            }
        }).fail(() => {
            $('#templatesList').html('<div class="text-center py-8 text-red-500">خطا در بارگذاری قالب‌ها</div>');
        });
    }

    function selectTemplate(content) {
        if (!currentTextareaId) return;

        const lead = window.currentLead;

        if (lead) {
            content = content
                .replace(/\[name\]/g, lead.name || '')
                .replace(/\[phone\]/g, lead.phone || '')
                .replace(/\[project\]/g, lead.project_name || '')
                .replace(/\[status\]/g, lead.status_fa || '')
                .replace(/\[note\]/g, lead.last_note || '');
        }

        $(`#${currentTextareaId}`).val(content);
        closeModal('templatesModal');
    }


    $('#templateSearch').on('input', function() {
        const search = $(this).val().trim();
        loadTemplates(search);
    });

    function editNote(note_id, current_text) {
        $(`#note-text-${note_id}`).addClass('hidden');
        $(`#edit-form-${note_id}`).removeClass('hidden');
    }

    function cancelEditNote(note_id) {
        $(`#note-text-${note_id}`).removeClass('hidden');
        $(`#edit-form-${note_id}`).addClass('hidden');
    }

    function saveEditNote(note_id) {
        const new_text = $(`#edit-text-${note_id}`).val().trim();
        if (!new_text) return showSnackbar('متن نمی‌تواند خالی باشد', 'error');

        $.post('apis/edit_lead_note.php', {
            note_id,
            note: new_text
        }, function(res) {
            if (res.ok) {
                $(`#note-text-${note_id}`).text(new_text).removeClass('hidden');
                $(`#edit-form-${note_id}`).addClass('hidden');
                showSnackbar('یادداشت با موفقیت ویرایش شد');
            } else {
                showSnackbar(res.error || 'خطا در ویرایش', 'error');
            }
        });
    }

    function deleteNote(note_id) {
        if (!confirm('آیا از حذف این یادداشت اطمینان دارید؟')) return;

        $.post('apis/delete_lead_note.php', {
            note_id
        }, function(res) {
            if (res.ok) {
                const lead_id = $('#modal_lead_id').val();
                loadNotesHistory(lead_id);
                showSnackbar('یادداشت حذف شد');
            } else {
                showSnackbar(res.error || 'خطا در حذف', 'error');
            }
        });
    }

    // متغیر برای ذخیره تعداد تراکنش‌های جدید
    let newTransactionsCount = 0;

    function checkTransactions(force = false) {
        const leadId = $('#modal_lead_id').val();
        const phone = $('#modal_phone').val();
        if (!leadId) return;

        // اگر قبلاً چک شده و فورس نباشه، فقط نمایش بده
        if (!force && $('#transactionsList').html() !== '') {
            return;
        }

        $('#transactionsLoading').removeClass('hidden');
        $('#transactionsEmpty').addClass('hidden');
        $('#transactionsList').addClass('hidden').empty();
        $('#transactionsSummary').addClass('hidden');
        newTransactionsCount = 0;

        $.post('apis/check_transactions.php', {
            lead_id: leadId
        }, function(res) {
            $('#transactionsLoading').addClass('hidden');

            if (res.success && res.transactions && res.transactions.length > 0) {
                let html = '';
                res.transactions.forEach(t => {
                    html += `
                <div class="bg-white border border-border rounded-xl p-5 shadow-sm hover:shadow transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <i class='bx bx-check-circle text-green-500 text-2xl'></i>
                                <span class="font-semibold text-lg">${t.amount} تومان</span>
                            </div>
                            <p class="text-sm text-gray-600">تاریخ: ${formatDate(t.date)}</p>
                            ${t.description !== '-' ? `<p class="text-sm text-gray-700 mt-2"><strong>توضیحات:</strong> ${escapeHtml(t.description)}</p>` : ''}
                        </div>
                        <div class="text-left">
                            <span class="text-xs bg-green-100 text-green-800 px-3 py-1 rounded-full">پرداخت موفق</span>
                        </div>
                    </div>
                </div>`;
                });

                $('#transactionsList').html(html).removeClass('hidden');
                $('#transactionsCount').text(res.total_found);
                $('#transactionsSummary').removeClass('hidden');

                // نمایش بج اگر تراکنش جدید باشه
                if (res.total_found > 0) {
                    newTransactionsCount = res.total_found;
                    $('#transactionsBadge').removeClass('hidden').text(newTransactionsCount > 99 ? '99+' : newTransactionsCount);
                }
            } else {
                $('#transactionsEmpty').removeClass('hidden');
                $('#transactionsBadge').addClass('hidden');
            }
        }).fail(() => {
            $('#transactionsLoading').addClass('hidden');
            showSnackbar('خطا در ارتباط با سرور زرین‌پال', 'error');
        });
    }




    $(document).ready(() => {
        loadPage();


        $(document).on('click', function(e) {
            if ($(e.target).hasClass('modal-overlay')) {
                closeModal('leadModal');
                closeModal('templatesModal');
            }
        });
    });
</script>

<style>
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #ffffff40;
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* استایل اسکرول بار سفارشی */
    #modalContent::-webkit-scrollbar {
        width: 6px;
    }

    #modalContent::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 3px;
    }

    #modalContent::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }

    #modalContent::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.3);
    }

    /* استایل برای حالت hover روی دکمه‌های ارسال پیام */
    .messaging-btn:hover {
        transform: translateY(-2px);
    }

    /* استایل برای تاریخ‌های تقویم */
    #calendarDays>div:hover {
        transform: scale(1.05);
        transition: transform 0.2s;
    }

    /* جلوگیری از اسکرول بدنه صفحه وقتی مودال باز است */
    body.modal-open {
        overflow: hidden;
    }

    /* استایل برای آیتم‌های قالب */
    .template-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
</style>
