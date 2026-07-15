<?php
$templates_func = new MessageTemplates($db);
$projects_func = new Projects($db);
$users_func = new Users($db);

$admin_info = $users_func->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $role = (new Roles($db))->get_by_id($admin_info['role_id']);
    if ($role) $permissions = json_decode($role['permissions'], true) ?? [];
}

$current_user_id = $_SESSION["id"];

$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

$all_projects = $projects_func->get_all();
$projects_map = [];
foreach ($all_projects as $p) {
    $projects_map[$p['id']] = $p['name'];
}
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">قالب‌های پیام من</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?= $can_manage_all ? 'مدیریت تمام قالب‌های پیام کاربران' : 'فقط قالب‌هایی که خودتان اضافه کرده‌اید' ?>
            </p>
        </div>
        <button onclick="showAddModal()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white font-medium px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all">
            <i class='bx bx-plus text-lg'></i>
            قالب جدید
        </button>
    </div>

    <!-- فیلترها -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-text mb-2">جستجو در عنوان و محتوا</label>
                <input id="search_name" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text" placeholder="جستجو...">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">پروژه</label>
                <select id="search_project" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text">
                    <option value="">همه پروژه‌ها</option>
                    <option value="0">قالب عمومی</option>
                    <?php foreach ($all_projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">دسته‌بندی</label>
                <select id="search_category" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text">
                    <option value="">همه دسته‌ها</option>
                    <option value="message">پیامک </option>
                    <option value="note">یادداشت </option>
                    <option value="reminder"> یادآوری</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end mt-4">
            <button id="searchButton" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class='bx bx-search'></i> جستجو
            </button>
        </div>
    </div>

    <!-- جدول قالب‌ها -->
    <div class="bg-surface rounded-2xl shadow-sm border border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-center">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-text">عنوان قالب</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">متن پیام</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">دسته‌بندی</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">پروژه</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">تاریخ ایجاد</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text no-print">عملیات</th>
                    </tr>
                </thead>
                <tbody id="templatesTableBody" class="divide-y divide-border">
                    <!-- با JS پر میشه -->
                </tbody>
            </table>
        </div>


    </div>
</div>

<!-- مودال افزودن/ویرایش -->
<div id="addTemplateModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border border-border w-full max-w-2xl">
        <h3 class="text-lg font-semibold text-text mb-4" id="modalTitle">افزودن قالب جدید</h3>
        <form id="templateForm">
            <input type="hidden" id="edit_id" value="0">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-text mb-2">عنوان قالب *</label>
                    <input type="text" id="template_title" required class="w-full px-4 py-2.5 border border-border rounded-lg focus:ring-2 focus:ring-primary bg-background text-text">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text mb-2">متن پیام *</label>
                    <textarea id="template_content" rows="6" required class="w-full px-4 py-2.5 border border-border rounded-lg focus:ring-2 focus:ring-primary bg-background text-text resize-none"></textarea>
                    <p class="text-xs text-gray-500 mt-2">شورت‌کدها: [name], [phone], [project], [status]</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text mb-2">دسته‌بندی</label>
                        <select id="template_category" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text">
                            <option value="message">پیامک </option>
                            <option value="note">یادداشت </option>
                            <option value="reminder"> یادآوری</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text mb-2">پروژه (اختیاری)</label>
                        <select id="template_project" class="w-full px-4 py-2.5 border border-border rounded-lg bg-background text-text">
                            <option value="">قالب عمومی</option>
                            <?php foreach ($all_projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="$('#addTemplateModal').addClass('hidden')" class="px-5 py-2.5 border border-border rounded-lg hover:bg-muted">انصراف</button>
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg flex items-center gap-2">
                    <span id="submitBtnText">ذخیره قالب</span>
                </button>
            </div>
        </form>
    </div>
</div>
<div id="snackbar" class="fixed bottom-6 left-6 z-60 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index : 60"></div>


<script>
    let currentPage = 1;
    let currentFilters = {
        name: '',
        project: '',
        category: '',
        user: ''
    };

    function loadPage(page = 1, filters = currentFilters) {
        currentPage = page;
        currentFilters = filters;

        const params = new URLSearchParams({
            page,
            limit: 12,
            search: filters.name || '',
            project_id: filters.project || '',
            category: filters.category || '',
            user_id: filters.user || '',
            all: <?= $can_manage_all ? '1' : '0' ?>
        });

        $('#templatesTableBody').html('<tr><td colspan="10" class="py-12"><div class="loading mx-auto"></div></td></tr>');

        $.get('apis/get_templates.php?' + params, function(data) {
            if (data.ok) {
                let tbody = '';
                data.templates.forEach(t => {
                    const projectName = t.project_id ? escapeHtml(<?= json_encode($projects_map) ?>[t.project_id] || 'نامشخص') : '<span class="text-green-600 font-medium">عمومی</span>';
                    const creator = <?= $can_manage_all ? '`<small class="text-gray-500">${escapeHtml(t.creator_name || "نامشخص")}</small>`' : '""' ?>;
                    const category = ((t.category == 'message') ? 'پیامک' : ((t.category == 'note') ? 'یادآوری' : 'یادداشت'));

                    tbody += `
                <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-6 py-4 text-sm font-medium">${escapeHtml(t.title)}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">${escapeHtml(t.content).replace(/\n/g, ' ⏎ ')}</td>
                    <td class="px-6 py-4"><span class="bg-muted/70 px-3 py-1 rounded-full text-xs">${escapeHtml(category)}</span></td>
                    <td class="px-6 py-4 text-sm">${projectName}</td>
                    <td class="px-6 py-4 text-xs text-gray-500">${formatDate(t.created_at)}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="editTemplate(${t.id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg">
                                <i class='bx bx-edit'></i>
                            </button>
                            <button onclick="deleteTemplate(${t.id}, '${escapeHtml(t.title)}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
                });
                $('#templatesTableBody').html(tbody || '<tr><td colspan="10" class="py-16 text-gray-500">هیچ قالبی یافت نشد</td></tr>');

                const pages = [];
                for (let i = 1; i <= data.total_pages; i++) {
                    pages.push(`<a onclick="loadPage(${i})" class="px-3 py-1.5 rounded-lg text-sm font-medium ${i === page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text'} cursor-pointer">${i}</a>`);
                }
                $('#paginationLinks').html(pages.join(''));
            }
        }).fail(() => {
            $('#templatesTableBody').html('<tr><td colspan="10" class="py-16 text-red-500">خطا در بارگذاری</td></tr>');
        });
    }

    function showAddModal() {
        $('#modalTitle').text('افزودن قالب جدید');
        $('#submitBtnText').text('ذخیره قالب');
        $('#templateForm')[0].reset();
        $('#edit_id').val('0');
        $('#template_category').val('عمومی');
        $('#addTemplateModal').removeClass('hidden');
    }

    function editTemplate(id) {
        $.get('apis/get_templates.php', {
            id
        }, function(res) {
            if (res.ok && res.templates[0]) {
                const t = res.templates[0];
                $('#modalTitle').text('ویرایش قالب');
                $('#submitBtnText').text('به‌روزرسانی');
                $('#edit_id').val(t.id);
                $('#template_title').val(t.title);
                $('#template_content').val(t.content);
                $('#template_category').val(t.category || 'عمومی');
                $('#template_project').val(t.project_id || '');
                $('#addTemplateModal').removeClass('hidden');
            }
        });
    }

    function deleteTemplate(id, title) {
        if (!confirm(`آیا از حذف قالب "${title}" مطمئن هستید؟`)) return;
        $.post('apis/delete_template.php', {
            id
        }, function(res) {
            if (res.ok) {
                showSnackbar('قالب با موفقیت حذف شد');
                loadPage(currentPage);
            } else {
                showSnackbar(res.error || 'خطا در حذف', 'error');
            }
        });
    }

    $('#templateForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#edit_id').val();
        const url = id === '0' ? 'apis/add_template.php' : 'apis/edit_template.php';

        $.post(url, {
            id: id === '0' ? undefined : id,
            title: $('#template_title').val().trim(),
            content: $('#template_content').val().trim(),
            category: $('#template_category').val().trim(),
            project_id: $('#template_project').val() || null
        }, function(res) {
            if (res.ok) {
                showSnackbar(id === '0' ? 'قالب اضافه شد' : 'قالب به‌روزرسانی شد');
                $('#addTemplateModal').addClass('hidden');
                loadPage(currentPage);
            } else {
                showSnackbar(res.error || 'خطا در ذخیره', 'error');
            }
        });
    });

    $('#searchButton').on('click', () => {
        currentFilters = {
            name: $('#search_name').val().trim(),
            project: $('#search_project').val(),
            category: $('#search_category').val(),
            user: $('#search_user').val() || ''
        };
        loadPage(1, currentFilters);
    });

    // جستجو با Enter
    $('#search_name').on('keypress', e => e.which === 13 && $('#searchButton').click());

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

    function showSnackbar(message, type = 'success') {
        const snackbar = $('#snackbar');
        snackbar.text(message);
        snackbar.removeClass('bg-green-500 bg-red-500 dark:bg-green-600 dark:bg-red-600');
        snackbar.addClass(type === 'success' ? 'bg-green-500 dark:bg-green-600' : 'bg-red-500 dark:bg-red-600');
        snackbar.removeClass('hidden');
        setTimeout(() => snackbar.addClass('hidden'), 3000);
    }

    $(document).ready(() => loadPage());
</script>

<style>
    .loading {
        width: 32px;
        height: 32px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>