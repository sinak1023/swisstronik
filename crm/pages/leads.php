<?php

$leads_func = new Leads($db);
$projects_func = new Projects($db);
$users_func = new Users($db);


$project_id = (int)($_GET['project_id'] ?? 0);
$project = $project_id ? $projects_func->get_by_id($project_id) : null;

$all_projects = $projects_func->get_all();
$all_users = $users_func->get_all();
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
                <?= $project ? 'لیدهای پروژه: ' . htmlspecialchars($project['name']) : 'مدیریت لیدها' ?>
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?= $project ? 'مدیریت لیدهای این پروژه' : 'جستجو، تخصیص و مدیریت همه لیدها' ?>
            </p>
        </div>
        <div class="flex gap-2">
            <?php if ($project): ?>
                <button onclick="showImportModal(<?= $project['id'] ?>, '<?= addslashes($project['name']) ?>')" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl">
                    <i class='bx bx-upload'></i> ایمپورت
                </button>
                <button onclick="exportLeads(<?= $project['id'] ?>)" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl">
                    <i class='bx bx-download'></i> اکسپورت
                </button>
            <?php endif; ?>
        </div>
    </div>


    <div class="bg-surface rounded-2xl shadow-sm border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm mb-2">نام</label>
                <input id="search_name" type="text" class="w-full px-4 py-2 border rounded-lg bg-background" placeholder="نام...">
            </div>
            <div>
                <label class="block text-sm mb-2">شماره</label>
                <input id="search_phone" type="text" class="w-full px-4 py-2 border rounded-lg bg-background" placeholder="شماره...">
            </div>
            <?php if (!$project): ?>
                <div>
                    <label class="block text-sm mb-2">پروژه</label>
                    <select id="search_project" class="w-full px-4 py-2 border rounded-lg bg-background">
                        <option value="">همه</option>
                        <?php foreach ($all_projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm mb-2">کارشناس</label>
                <select id="search_assigned" class="w-full px-4 py-2 border rounded-lg bg-background select2 bg-background select3">
                    <option value="">همه</option>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm mb-2">وضعیت تخصیص</label>
                <select id="search_assigned_status" class="w-full px-4 py-2 border rounded-lg bg-background">
                    <option value="">همه</option>
                    <option value="assigned">تخصیص داده شده</option>
                    <option value="unassigned">تخصیص داده نشده</option>
                </select>
            </div>
            <div>
                <label class="block text-sm mb-2">وضعیت</label>
                <select id="search_status" class="w-full px-4 py-2 border rounded-lg bg-background">
                    <option value="">همه</option>
                    <?php foreach ($statuses as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex justify-end mt-4">
            <button id="searchBtn" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg flex gap-2">
                <i class='bx bx-search'></i> جستجو
            </button>
        </div>
    </div>


    <div id="actionBar" class="hidden sticky top-4 z-10 mb-6">
        <div class="bg-surface border rounded-2xl shadow-lg p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm">
                <input type="checkbox" id="selectAllVisible" class="rounded">
                <span id="selectedCount">0</span> لید انتخاب شده
            </div>
            <div class="flex gap-2 flex-wrap">
                <button onclick="showAssignModal()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl text-sm font-medium">
                    <i class='bx bx-user-plus'></i> تخصیص
                </button>
                <button onclick="showUnassignConfirmModal()" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
                    <i class='bx bx-user-minus'></i> حذف تخصیص
                </button>
                <button onclick="showDeleteConfirmModal()" class="inline-flex items-center gap-2 bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-xl text-sm font-medium">
                    <i class='bx bx-trash'></i> حذف
                </button>
            </div>
        </div>
    </div>


    <div class="bg-surface rounded-2xl shadow-sm border overflow-hidden">
        <table class="w-full text-center">
            <thead class="bg-muted/50 border-b">
                <tr>
                    <th class="px-6 py-4"><input type="checkbox" id="selectAll"></th>
                    <th class="px-6 py-4 text-sm font-semibold">نام</th>
                    <th class="px-6 py-4 text-sm font-semibold">شماره</th>
                    <th class="px-6 py-4 text-sm font-semibold">کارشناس</th>
                    <th class="px-6 py-4 text-sm font-semibold">وضعیت</th>
                    <th class="px-6 py-4 text-sm font-semibold">تاریخ ایجاد</th>
                    <th class="px-6 py-4 text-sm font-semibold">تاریخچه</th>
                    <th class="px-6 py-4 text-sm font-semibold">عملیات</th>
                </tr>
            </thead>
            <tbody id="leadsTableBody" class="divide-y"></tbody>
        </table>
        <div class="flex items-center justify-between px-6 py-4 border-t">
            <p id="paginationInfo">در حال بارگذاری...</p>
            <div id="paginationLinks" class="flex gap-1"></div>
        </div>
    </div>
</div>


<div id="assignModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">تخصیص گروهی لیدها</h3>
        <form id="assignForm">
            <div class="mb-4 w-full">
                <label class="block text-sm font-medium mb-2">کارشناس</label>
                <select id="assign_user" class="w-full px-4 py-2 border rounded-lg bg-background select3" required>
                    <option value="">انتخاب کنید</option>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" id="roundRobin">
                    <span class="text-sm">توزیع چرخشی بین چند کارشناس</span>
                </label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('assignModal')" class="px-4 py-2 bg-muted hover:bg-muted/80 rounded-lg">لغو</button>
                <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg flex items-center gap-2">
                    <span>تخصیص</span>
                </button>
            </div>
        </form>
    </div>
</div>


<div id="unassignConfirmModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">تایید حذف تخصیص</h3>
        <p class="mb-4">آیا مطمئن هستید که می‌خواهید تخصیص <span id="unassignCount"></span> لید را حذف کنید؟</p>
        <div class="flex justify-end gap-2">
            <button onclick="closeModal('unassignConfirmModal')" class="px-4 py-2 bg-muted hover:bg-muted/80 rounded-lg">لغو</button>
            <button onclick="confirmUnassign()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">تایید</button>
        </div>
    </div>
</div>


<div id="deleteConfirmModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-red-600">تایید حذف دائمی</h3>
        <p class="mb-4">آیا مطمئن هستید که می‌خواهید <span id="deleteCount"></span> لید را <strong>به‌طور دائمی حذف کنید</strong>؟</p>
        <p class="text-xs text-red-600 mb-4">این عمل قابل بازگشت نیست!</p>
        <div class="flex justify-end gap-2">
            <button onclick="closeModal('deleteConfirmModal')" class="px-4 py-2 bg-muted hover:bg-muted/80 rounded-lg">لغو</button>
            <button onclick="confirmDelete()" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-lg">حذف دائمی</button>
        </div>
    </div>
</div>


<div id="historyModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border w-full max-w-2xl max-h-96 overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">تاریخچه تخصیص شماره: <span id="historyPhone"></span></h3>
            <button onclick="closeModal('historyModal')" class="text-gray-500 hover:text-gray-700">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        <div id="historyContent" class="text-sm"></div>
    </div>
</div>



<div id="importModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center" style="z-index: 1000;">
    <div class="absolute inset-0 bg-surface rounded-2xl shadow-xl border my-10 pb-4 mx-4 md:w-full md:max-w-4xl md:mx-auto  overflow-y-auto no-scrollbar">

        <div class="flex justify-between items-center mb-6 m-5">
            <h3 class="text-xl font-bold">ایمپورت لیدها برای: <span id="import_project_name" class="text-primary"></span></h3>
            <button onclick="closeModal('importModal')" class="text-gray-500 hover:text-gray-700">
                <i class="bx bx-x text-3xl"></i>
            </button>
        </div>
        <div class="mx-5 my-6">
            <input type="hidden" id="import_project_id">

            <form id="importForm" enctype="multipart/form-data">
                <!-- مرحله 1: آپلود فایل -->
                <div id="step1" class="m-5">
                    <label class="block text-sm font-medium mb-2">فایل لیدها (CSV, Excel, TXT, JSON, SQL)</label>
                    <input type="file" id="import_file" accept=".csv,.xlsx,.xls,.txt,.json,.sql,.db"
                        class="w-full p-4 border-2 border-dashed rounded-xl bg-background/50 text-center cursor-pointer hover:border-primary transition">
                    <p id="selectedFileName" class="text-sm text-primary mt-3 text-center hidden"></p>
                    <p class="text-xs text-gray-500 mt-3 text-center">
                        پشتیبانی از: CSV، Excel، TXT، JSON، SQL Dump
                    </p>
                    <div class="flex justify-center mt-4">
                        <button type="button" id="uploadBtn" onclick="previewImport()" disabled
                            class="px-8 py-3 bg-primary hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-bold flex items-center gap-2">
                            <i class="bx bx-upload"></i>
                            آپلود و ادامه
                        </button>
                    </div>
                </div>

                <!-- مرحله 2: پیش‌نمایش و تنظیمات -->
                <div id="step2" class="hidden mt-6 space-y-6  m-5">

                    <!-- پیش‌نمایش جدول -->
                    <div>
                        <h4 class="font-semibold mb-3 text-lg">پیش‌نمایش داده‌ها</h4>
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="w-full text-xs">
                                <thead id="previewHeader" class="bg-muted/50"></thead>
                                <tbody id="previewBody" class="divide-y"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- تنظیمات ستون‌ها (برای فایل‌های ساختاریافته) -->
                    <div id="structuredConfig" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-2">ستون‌های نام و فامیلی <span class="text-red-500">*</span></label>
                            <select id="name_columns" multiple class="w-full p-3 border rounded-lg select4" size="5">
                                <!-- پر میشه -->
                            </select>
                            <p class="text-xs text-gray-500 mt-2">چند ستون را انتخاب کنید (مثلاً نام، فامیلی، نام پدر)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">ستون شماره موبایل <span class="text-red-500">*</span></label>
                            <select id="phone_column" class="w-full p-3 border rounded-lg">
                                <option value="">انتخاب کنید</option>
                                <!-- پر میشه -->
                            </select>
                        </div>
                    </div>

                    <!-- تنظیمات فایل متنی ساده -->
                    <div id="txtConfig" class="hidden space-y-4 p-5 bg-blue-50 dark:bg-blue-900/20 rounded-xl border">
                        <h4 class="font-semibold text-primary">تنظیمات فایل متنی (TXT)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm mb-1">جداکننده</label>
                                <select id="txt_delimiter" class="w-full p-3 border rounded-lg">
                                    <option value=":">دونقطه (:)</option>
                                    <option value=",">کاما (,)</option>
                                    <option value=";">سمی‌کالن (;)</option>
                                    <option value="|">پایپ (|)</option>
                                    <option value="\t">تب (Tab)</option>
                                    <option value=" ">فاصله (Space)</option>
                                </select>
                            </div>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" id="txt_has_name" checked class="rounded">
                                    <span>فایل شامل نام است</span>
                                </label>
                                <div class="ml-6 space-y-2">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="txt_order" value="name_first" checked>
                                        <span>نام اول، شماره دوم</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="txt_order" value="phone_first">
                                        <span>شماره اول، نام دوم</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- گزینه نادیده گرفتن نام -->
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="ignore_name">
                        <span>نادیده گرفتن نام (همه "بینام" ذخیره شوند)</span>
                    </label>
                </div>

                <!-- نوار پیشرفت -->
                <div id="importProgress" class="hidden mt-6">
                    <div class="flex justify-between text-sm mb-2">
                        <span>در حال پردازش...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div id="progressBar" class="bg-primary h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <!-- دکمه‌ها -->
                <div class="flex justify-end gap-3 mt-8">
                    <button type="button" onclick="closeModal('importModal')"
                        class="px-6 py-3 bg-muted hover:bg-muted/80 rounded-xl font-medium">
                        لغو
                    </button>
                    <button type="submit" id="importBtn" class="hidden px-8 py-3 bg-green-600 hover:bg-green-700 text-white rounded-xl font-bold flex items-center gap-2">
                        <i class="bx bx-upload"></i>
                        شروع ایمپورت
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 z-60 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index: 1300"></div>

<script>
    let currentPage = 1;
    let currentFilters = {};
    let selectedLeads = [];
    let importData = {};
    let lastChecked = null;


    function closeModal(id) {
        $(`#${id}`).addClass('hidden');
    }

    function showSnackbar(message, type = 'success') {
        const snackbar = $('#snackbar');
        snackbar.text(message);
        snackbar.removeClass('bg-green-500 bg-red-500 dark:bg-green-600 dark:bg-red-600');
        snackbar.addClass(type === 'success' ? 'bg-green-500 dark:bg-green-600' : 'bg-red-500 dark:bg-red-600');
        snackbar.removeClass('hidden');
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
            project_id: <?= $project_id ?>
        }).toString();

        $.get('apis/search_leads.php?' + params, function(data) {
            if (data.ok) {
                renderTable(data.leads);
                renderPagination(data.page, data.pages, data.total);
                updateSelectionUI();
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => showSnackbar('خطا در ارتباط با سرور', 'error'));
    }

    function renderTable(leads) {
        const tbody = $('#leadsTableBody');
        tbody.empty();

        if (leads.length === 0) {
            tbody.append('<tr><td colspan="8" class="py-8 text-gray-500">هیچ لیدی یافت نشد</td></tr>');
            return;
        }

        leads.forEach(lead => {
            const row = `
            <tr class="hover:bg-muted/30 transition-colors">
                <td class="px-6 py-4"><input type="checkbox" class="lead-check" value="${lead.id}"></td>
                <td class="px-6 py-4">${escapeHtml(lead.name)}</td>
                <td class="px-6 py-4">${escapeHtml(lead.phone)}</td>
                <td class="px-6 py-4">${escapeHtml(lead.assignee_name || 'اختصاص نیافته')}</td>
                <td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-xs ${getStatusClass(lead.status)}">${getStatusText(lead.status)}</span></td>
                <td class="px-6 py-4">${formatDate(lead.created_at)}</td>
                <td class="px-6 py-4">
                    <button onclick="showHistory('${lead.phone}')" class="text-primary hover:underline text-sm">
                        <i class='bx bx-history'></i> تاریخچه
                    </button>
                </td>
                <td class="px-6 py-4">
                    ${lead.assigned_to ? `<button onclick="unassignLead(${lead.id})" class="text-red-600 hover:underline text-sm"><i class='bx bx-user-minus'></i> حذف تخصیص</button>` : ''}
                </td>
            </tr>`;
            tbody.append(row);
        });

        $('.lead-check').on('change', handleCheckboxChange);
        $('#selectAll').off('change').on('change', function() {
            $('.lead-check').prop('checked', this.checked);
            updateSelectedLeads();
        });
    }

    function handleCheckboxChange(e) {
        if (e.shiftKey && lastChecked) {
            const checkboxes = $('.lead-check');
            const start = checkboxes.index(lastChecked);
            const end = checkboxes.index(this);
            const checked = $(this).prop('checked');
            checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', checked);
        }
        lastChecked = this;
        updateSelectedLeads();
    }

    function updateSelectedLeads() {
        selectedLeads = $('.lead-check:checked').map((_, el) => $(el).val()).get();
        updateSelectionUI();
    }

    function updateSelectionUI() {
        const count = selectedLeads.length;
        $('#selectedCount').text(count);
        $('#actionBar').toggleClass('hidden', count === 0);
        $('#selectAllVisible').prop('checked', count > 0 && count === $('.lead-check').length);
    }


    $('#searchBtn').on('click', () => {
        const filters = {
            name: $('#search_name').val().trim(),
            phone: $('#search_phone').val().trim(),
            assigned_to: $('#search_assigned').val(),
            assigned_status: $('#search_assigned_status').val(),
            status: $('#search_status').val(),
            project_id: $('#search_project').val() || <?= $project_id ?>
        };
        loadPage(1, filters);
    });

    $('#search_name, #search_phone').on('keypress', e => {
        if (e.which === 13) $('#searchBtn').click();
    });


    function renderPagination(page, pages, total) {
        $('#paginationInfo').text(`صفحه ${page} از ${pages} (کل: ${total})`);
        const links = $('#paginationLinks').empty();

        for (let i = 1; i <= pages; i++) {
            const active = i === page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text';
            links.append(`<a onclick="loadPage(${i}, currentFilters)" class="px-3 py-1.5 rounded-lg text-sm font-medium ${active} transition-colors cursor-pointer">${i}</a>`);
        }
    }


    function showAssignModal() {
        if (selectedLeads.length === 0) return;
        $('#assignForm')[0].reset();
        $('#assign_user').removeAttr('multiple').prop('disabled', false);
        if ($('#assign_user').data('select2')) $('#assign_user').select2('destroy');
        $('#assign_user').select2({
            dropdownParent: $('#assignModal')
        });
        $('#assignModal').removeClass('hidden');
    }

    $('#roundRobin').on('change', function() {
        $('#assign_user').select2('destroy');
        setTimeout(() => {
            const options = {
                dropdownParent: $('#assignModal')
            };
            if (this.checked) options.multiple = true;
            $('#assign_user').select2(options);
        }, 2000);
    });

    $('#assignForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const orig = btn.html();
        btn.html('<span class="loading"></span> در حال تخصیص...').prop('disabled', true);

        const isRR = $('#roundRobin').is(':checked');
        const user_ids = $('#assign_user').val();

        if (!user_ids || user_ids.length === 0 || (isRR && user_ids.length < 2)) {
            showSnackbar('حداقل یک کارشناس انتخاب کنید' + (isRR ? ' (برای چرخشی حداقل دو)' : ''), 'error');
            btn.html(orig).prop('disabled', false);
            return;
        }

        $.post('apis/assign_leads.php', {
            lead_ids: JSON.stringify(selectedLeads),
            user_ids: JSON.stringify(user_ids),
            round_robin: isRR ? 1 : 0
        }, function(res) {
            if (res.ok) {
                showSnackbar(`تخصیص موفق! ${res.assigned_count} لید تخصیص یافت.`);
                closeModal('assignModal');
                loadPage(currentPage);
            } else {
                showSnackbar(res.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });


    function showUnassignConfirmModal() {
        if (selectedLeads.length === 0) return;
        $('#unassignCount').text(selectedLeads.length);
        $('#unassignConfirmModal').removeClass('hidden');
    }

    function confirmUnassign() {
        closeModal('unassignConfirmModal');
        $.post('apis/assign_leads.php', {
            lead_ids: JSON.stringify(selectedLeads),
            unassign: 1
        }, function(res) {
            if (res.ok) {
                showSnackbar(`حذف تخصیص موفق! ${res.unassigned_count} لید آزاد شد.`);
                loadPage(currentPage);
            } else {
                showSnackbar(res.error, 'error');
            }
        });
    }


    function showDeleteConfirmModal() {
        if (selectedLeads.length === 0) return;
        $('#deleteCount').text(selectedLeads.length);
        $('#deleteConfirmModal').removeClass('hidden');
    }

    function confirmDelete() {
        closeModal('deleteConfirmModal');
        $.post('apis/delete_leads.php', {
            lead_ids: JSON.stringify(selectedLeads)
        }, function(res) {
            if (res.ok) {
                showSnackbar(`حذف موفق! ${res.deleted_count} لید حذف شد.`);
                loadPage(currentPage);
            } else {
                showSnackbar(res.error, 'error');
            }
        });
    }


    function unassignLead(lead_id) {
        if (!confirm('آیا مطمئن هستید که می‌خواهید تخصیص این لید را حذف کنید؟')) return;
        $.post('apis/assign_leads.php', {
            lead_ids: JSON.stringify([lead_id]),
            unassign: 1
        }, function(res) {
            if (res.ok) showSnackbar('حذف تخصیص موفق!');
            loadPage(currentPage);
        });
    }


    function showHistory(phone) {
        $('#historyPhone').text(phone);
        $('#historyContent').html('<p class="text-center py-4">در حال بارگذاری...</p>');
        $.get('apis/get_assignment_history.php', {
            phone
        }, function(res) {
            if (res.ok && res.history.length > 0) {
                let html = '<table class="w-full text-xs"><thead class="bg-muted/50"><tr><th class="px-3 py-2 text-left">کارشناس</th><th class="px-3 py-2 text-left">زمان</th></tr></thead><tbody>';
                res.history.forEach(h => {
                    html += `<tr class="border-t"><td class="px-3 py-2">${escapeHtml(h.user_name || 'حذف شده')}</td><td class="px-3 py-2">${formatDate(h.assigned_at)}</td></tr>`;
                });
                html += '</tbody></table>';
                $('#historyContent').html(html);
            } else {
                $('#historyContent').html('<p class="text-center py-4 text-gray-500">تاریخچه‌ای یافت نشد</p>');
            }
            $('#historyModal').removeClass('hidden');
        });
    }


    // باز کردن مودال
    function showImportModal(project_id, project_name) {
        $('#import_project_id').val(project_id);
        $('#import_project_name').text(project_name);
        $('#importForm')[0].reset();
        $('#step1, #step2, #importProgress, #importBtn').addClass('hidden');
        $('#step1').removeClass('hidden');
        $('#structuredConfig, #txtConfig').addClass('hidden');
        $('#selectedFileName').addClass('hidden').text('');
        $('#uploadBtn').prop('disabled', true);
        importData = {};
        $('#importModal').removeClass('hidden');
    }

    // مرحله ۱: فقط انتخاب فایل — دکمهٔ آپلود فعال می‌شود (بدون اقدام خودکار)
    $('#import_file').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            $('#selectedFileName').text('فایل انتخاب‌شده: ' + file.name).removeClass('hidden');
            $('#uploadBtn').prop('disabled', false);
        } else {
            $('#selectedFileName').addClass('hidden');
            $('#uploadBtn').prop('disabled', true);
        }
    });

    // مرحله ۲: با کلیک دکمهٔ آپلود، پیش‌نمایش گرفته می‌شود
    function previewImport() {
        const file = $('#import_file')[0].files[0];
        if (!file) {
            showSnackbar('ابتدا یک فایل انتخاب کنید', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('project_id', $('#import_project_id').val());
        formData.append('file', file);
        formData.append('preview', '1');

        const btn = $('#uploadBtn');
        const orig = btn.html();
        btn.prop('disabled', true).html('<span class="loading"></span> در حال بارگذاری...');

        $.ajax({
            url: 'apis/import_leads.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.ok && res.preview) {
                    importData = res;
                    renderImportPreview(res);
                    $('#step1').addClass('hidden');
                    $('#step2, #importBtn').removeClass('hidden');
                } else {
                    showSnackbar(res.error || 'خطا در پیش‌نمایش فایل', 'error');
                }
            },
            error: () => showSnackbar('خطا در پردازش فایل. فرمت یا محتوای فایل را بررسی کنید', 'error'),
            complete: () => btn.prop('disabled', false).html(orig)
        });
    }

    function renderImportPreview(data) {
        const headers = data.headers || [];
        const sample = data.sample || [];
        const is_txt = data.is_txt || false;

        // پاک کردن قبلی
        $('#previewHeader').empty();
        $('#previewBody').empty();
        $('#name_columns').empty();
        $('#phone_column').empty().append('<option value="">انتخاب کنید</option>');

        if (is_txt) {
            // فایل متنی ساده
            $('#txtConfig').removeClass('hidden');
            $('#structuredConfig').addClass('hidden');
            $('#ignore_name').prop('checked', false).closest('label').hide();

            // هدر ستون‌های عمومی
            let maxCols = Math.max(...sample.map(r => r.length), 2);
            let headerRow = '<tr>';
            for (let i = 0; i < maxCols; i++) {
                headerRow += `<th class="px-4 py-2 text-center bg-gray-100">ستون ${i+1}</th>`;
            }
            headerRow += '</tr>';
            $('#previewHeader').html(headerRow);

            // بدنه
            sample.forEach(row => {
                let cells = '';
                for (let i = 0; i < maxCols; i++) {
                    cells += `<td class="px-4 py-2 text-center border-t">${escapeHtml(row[i] || '')}</td>`;
                }
                $('#previewBody').append(`<tr>${cells}</tr>`);
            });

            if (data.delimiter) $('#txt_delimiter').val(data.delimiter === '\t' ? '\t' : data.delimiter);

        } else {
            // فایل ساختاریافته (CSV, Excel, JSON, SQL)
            $('#txtConfig').addClass('hidden');
            $('#structuredConfig').removeClass('hidden');
            $('#ignore_name').closest('label').show();

            // هدر
            let headerRow = '';
            headers.forEach(h => {
                headerRow += `<th class="px-4 py-2 text-xs font-medium text-center bg-gray-100">${escapeHtml(h || '(خالی)')}</th>`;
            });
            $('#previewHeader').html(`<tr>${headerRow}</tr>`);

            // بدنه
            sample.forEach(row => {
                let cells = '';
                headers.forEach((_, i) => {
                    cells += `<td class="px-4 py-2 text-center border-t text-xs">${escapeHtml(row[i] || '')}</td>`;
                });
                $('#previewBody').append(`<tr>${cells}</tr>`);
            });

            // پر کردن سلکت‌ها
            headers.forEach((h, i) => {
                const text = escapeHtml(h || `ستون ${i+1}`);
                $('#name_columns').append(`<option value="${i}">${text}</option>`);
                $('#phone_column').append(`<option value="${i}">${text}</option>`);
            });

            // حدس هوشمند
            const namePatterns = [/نام|name|first|family|فامیلی|fullname/i];
            const phonePatterns = [/شماره|phone|mobile|موبایل|تلفن|number/i];

            const nameIdxs = headers.map((h, i) => namePatterns.some(p => p.test(h)) ? i : -1).filter(x => x !== -1);
            const phoneIdx = headers.findIndex(h => phonePatterns.some(p => p.test(h)));

            if (nameIdxs.length > 0) $('#name_columns').val(nameIdxs);
            if (phoneIdx !== -1) $('#phone_column').val(phoneIdx);
        }

        // فعال کردن Select2
        setTimeout(() => {
            $('.select4').select2({
                dropdownParent: $('#importModal'),
                width: '100%'
            });
        }, 100);
    }

    // ارسال نهایی
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        const file = $('#import_file')[0].files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('project_id', $('#import_project_id').val());
        formData.append('file', file);

        if (importData.is_txt) {
            formData.append('delimiter', $('#txt_delimiter').val());
            formData.append('has_name', $('#txt_has_name').is(':checked') ? 1 : 0);
            formData.append('name_first', $('input[name="txt_order"]:checked').val() === 'name_first' ? 1 : 0);
        } else {
            const nameCols = $('#name_columns').val();
            const phoneCol = $('#phone_column').val();
            if ((!nameCols || nameCols.length === 0) && !$('#ignore_name').is(':checked')) {
                showSnackbar('لطفاً ستون نام را انتخاب کنید یا "نادیده گرفتن نام" را فعال کنید', 'error');
                return;
            }
            if (!phoneCol) {
                showSnackbar('لطفاً ستون شماره موبایل را انتخاب کنید', 'error');
                return;
            }
            formData.append('name_columns', JSON.stringify(nameCols || []));
            formData.append('phone_column', phoneCol);
            formData.append('ignore_name', $('#ignore_name').is(':checked') ? 1 : 0);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'apis/import_leads.php', true);

        xhr.upload.onprogress = e => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                $('#progressBar').css('width', percent + '%');
                $('#progressPercent').text(percent + '%');
            }
        };

        xhr.onload = function() {
            $('#importBtn').prop('disabled', false).html('<i class="bx bx-upload"></i> شروع ایمپورت');
            let res;
            try {
                res = JSON.parse(xhr.responseText);
            } catch (err) {
                showSnackbar('پاسخ نامعتبر از سرور دریافت شد', 'error');
                return;
            }
            closeModal('importModal');
            if (res.ok) {
                let msg = `ایمپورت موفق! ${res.imported} لید اضافه شد، ${res.skipped} رد شد`;
                if (res.cross_campaign_count > 0) {
                    msg += ` — ${res.cross_campaign_count} شماره سابقهٔ کمپین دیگر دارد`;
                }
                showSnackbar(msg);
                if (res.cross_campaign && res.cross_campaign.length > 0) {
                    showCrossCampaign(res.cross_campaign);
                }
                loadPage(currentPage);
            } else {
                showSnackbar(res.error || 'خطا در ایمپورت', 'error');
            }
        };
        xhr.onerror = function() {
            $('#importBtn').prop('disabled', false).html('<i class="bx bx-upload"></i> شروع ایمپورت');
            showSnackbar('خطا در ارتباط با سرور', 'error');
        };

        $('#importProgress').removeClass('hidden');
        $('#importBtn').prop('disabled', true).html('در حال پردازش...');
        xhr.send(formData);
    });

    // نمایش شماره‌هایی که در کمپین‌های قبلی سابقه داشته‌اند + آخرین کارشناس
    function showCrossCampaign(items) {
        let rows = items.map(it => `
            <tr class="border-t">
                <td class="px-3 py-2 dir-ltr text-center">${escapeHtml(it.phone)}</td>
                <td class="px-3 py-2 text-center">${escapeHtml(it.project_name || '—')}</td>
                <td class="px-3 py-2 text-center">${escapeHtml(it.last_handler || 'بدون کارشناس')}</td>
                <td class="px-3 py-2 text-center text-xs text-gray-500">${it.last_date ? formatDate(it.last_date) : '—'}</td>
            </tr>`).join('');

        const html = `
        <div id="crossModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4" style="z-index:1400;">
            <div class="bg-surface rounded-2xl shadow-xl border w-full max-w-3xl max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center px-6 py-4 border-b">
                    <h3 class="text-lg font-bold">شماره‌های دارای سابقه در کمپین‌های دیگر (${items.length})</h3>
                    <button onclick="$('#crossModal').remove()" class="text-gray-500 hover:text-gray-700"><i class="bx bx-x text-2xl"></i></button>
                </div>
                <div class="overflow-y-auto p-4">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50"><tr>
                            <th class="px-3 py-2">شماره</th><th class="px-3 py-2">کمپین/پروژه قبلی</th>
                            <th class="px-3 py-2">آخرین کارشناس</th><th class="px-3 py-2">تاریخ</th>
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t flex justify-end">
                    <button onclick="$('#crossModal').remove()" class="px-6 py-2.5 bg-muted hover:bg-muted/80 rounded-lg">بستن</button>
                </div>
            </div>
        </div>`;
        $('#crossModal').remove();
        $('body').append(html);
    }

    function exportLeads(project_id) {
        const btn = $('button[onclick="exportLeads(' + project_id + ')"]');
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال تولید...').prop('disabled', true);

        $.post('apis/export_leads.php', {
            project_id
        }, function(res) {
            if (res.ok) {
                const blob = new Blob([res.csv], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `leads_project_${project_id}_${new Date().toISOString().slice(0,10)}.csv`;
                link.click();
                showSnackbar('فایل CSV با موفقیت دانلود شد');
            } else {
                showSnackbar(res.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    }

    $(document).ready(() => {
        loadPage();
    });
</script>

<style>
    .loading {
        display: inline-block;
        width: 16px;
        height: 16px;
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
</style>

<?php
$extra_footer = ($extra_footer ?? '') . '
<script>
    $(".select2").select2();
</script>
    ';
?>