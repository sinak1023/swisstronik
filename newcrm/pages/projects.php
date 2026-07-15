<?php

$projects_function = new Projects($db);
$roles_function = new Roles($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);
$persian_date = new PersianDate();

$all_roles = $roles_function->get_all();
$roles_map = [];
foreach ($all_roles as $role) {
    $roles_map[$role['id']] = $role['name'];
}


$page = 1;
$limit = 10;
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">مدیریت پروژه‌های فروش</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">ایجاد، ویرایش و مدیریت پروژه‌های نامحدود</p>
        </div>
        <button onclick="showAddModal()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark active:bg-primary-darker text-white font-medium px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
            <i class='bx bx-plus text-lg'></i>

            پروژه جدید
        </button>
    </div>

    
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-text mb-2">جستجو بر اساس نام پروژه</label>
                <input id="search_name" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder="نام پروژه..." autocomplete="off">
            </div>
        </div>
        <div class="flex justify-end mt-4">
            <button id="searchButton" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-medium transition-colors flex items-center gap-2">
                <i class='bx bx-search'></i>
                جستجو
            </button>
        </div>
    </div>

    
    <div class="bg-surface rounded-2xl shadow-sm border border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-center">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-text">نام پروژه</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">توضیحات</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">تعداد لیدها</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">ایجاد شده توسط</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">تاریخ ایجاد</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text no-print">عملیات</th>
                    </tr>
                </thead>
                <tbody id="projectsTableBody" class="divide-y divide-border">
                    
                </tbody>
            </table>
        </div>

        
        <div class="flex items-center justify-between px-6 py-4 border-t border-border bg-surface">
            <p class="text-sm text-gray-600 dark:text-gray-400" id="paginationInfo">در حال بارگذاری...</p>
            <div class="flex gap-1" id="paginationLinks"></div>
        </div>
    </div>
</div>


<div id="addProjectModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border border-border w-full max-w-md">
        <h3 class="text-lg font-semibold text-text mb-4">افزودن پروژه جدید</h3>
        <form id="addForm">
            <div class="mb-4">
                <label class="block text-sm font-medium text-text mb-2">نام پروژه</label>
                <input type="text" id="add_name" required class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text" placeholder="مثال: کمپین تابستان ۱۴۰۴">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-text mb-2">توضیحات (اختیاری)</label>
                <textarea id="add_description" rows="3" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text" placeholder="توضیح مختصر درباره پروژه..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="$('#addProjectModal').addClass('hidden')" class="px-4 py-2 text-text bg-muted hover:bg-muted/80 rounded-lg transition-colors">لغو</button>
                <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg transition-colors flex items-center gap-2">
                    <span>ذخیره</span>
                </button>
            </div>
        </form>
    </div>
</div>


<div id="editProjectModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface p-6 rounded-2xl shadow-xl border border-border w-full max-w-md">
        <h3 class="text-lg font-semibold text-text mb-4">ویرایش پروژه</h3>
        <form id="editForm">
            <input type="hidden" id="edit_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-text mb-2">نام پروژه</label>
                <input type="text" id="edit_name" required class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-text mb-2">توضیحات</label>
                <textarea id="edit_description" rows="3" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary bg-background text-text"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="$('#editProjectModal').addClass('hidden')" class="px-4 py-2 text-text bg-muted hover:bg-muted/80 rounded-lg transition-colors">لغو</button>
                <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg transition-colors flex items-center gap-2">
                    <span>به‌روزرسانی</span>
                </button>
            </div>
        </form>
    </div>
</div>




<div id="deleteConfirmModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-sm border border-border p-6">
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-text">حذف پروژه</h3>
            <p id="deleteMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-400"></p>
        </div>
        <div class="flex gap-3">
            <button id="confirmDeleteBtn" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all">
                حذف
            </button>
            <button onclick="$('#deleteConfirmModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-2.5 rounded-xl transition-colors">
                انصراف
            </button>
        </div>
    </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 z-60 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index : 60"></div>


<script>
    let deleteProjectId = null;
    let currentPage = 1;
    let currentFilters = {
        name: ''
    };

    
    function loadPage(page = 1, filters = currentFilters) {
        currentPage = page;
        currentFilters = {
            ...filters
        };

        $.get('apis/search_projects.php', {
            page: page,
            limit: 10,
            name: filters.name || ''
        }, function(data) {
            if (data.ok) {
                const tbody = $('#projectsTableBody');
                tbody.empty();

                if (data.projects.length === 0) {
                    tbody.append(`
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            پروژه‌ای یافت نشد
                        </td>
                    </tr>
                `);
                } else {
                    data.projects.forEach(p => {
                        const row = `
                        <tr id="project_${p.id}" class="hover:bg-muted/30 transition-colors duration-150">
                            <td class="px-6 py-4 text-text font-medium">
                                <a href="leads?project_id=${p.id}" class="text-primary hover:underline">
                                    ${escapeHtml(p.name)}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-text text-sm max-w-xs truncate">
                                ${escapeHtml(p.description || '—')}
                            </td>
                            <td class="px-6 py-4 text-text">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    ${p.lead_count || 0}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-text text-sm">
                                ${escapeHtml(p.creator_name)}
                            </td>
                            <td class="px-6 py-4 text-text text-sm">
                                ${p.created_at}
                            </td>
                            <td class="px-6 py-4 no-print">
                                <div class="flex items-center gap-2 justify-center">
                                    <button onclick="editProject(${p.id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                        <i class='bx bx-edit text-lg'></i>
                                    </button>
                                    <button onclick="showDeleteConfirm(${p.id}, '${escapeHtml(p.name)}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <i class='bx bx-trash text-lg'></i>
                                    </button>
                                    <a href="leads?project_id=${p.id}" class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors">
                                        <i class='bx bx-list-ul text-lg'></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    `;
                        tbody.append(row);
                    });
                }

                $('#paginationInfo').text(`نمایش ${data.projects.length} از ${data.total} پروژه`);
                renderPagination(page, data.total_pages);
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => showSnackbar('خطا در ارتباط با سرور', 'error'));
    }

    
    function renderPagination(page, pages) {
        const links = $('#paginationLinks').empty();
        if (!pages || pages <= 1) return;

        const btn = (label, target, opts = {}) => {
            const disabled = opts.disabled ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer';
            const active = opts.active ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text';
            return `<a ${opts.disabled ? '' : `onclick="loadPage(${target}, currentFilters)"`} class="px-3 py-1.5 rounded-lg text-sm font-medium ${active} ${disabled} transition-colors">${label}</a>`;
        };

        let html = '';
        html += btn('« اول', 1, {disabled: page === 1});
        html += btn('‹', page - 1, {disabled: page === 1});
        let start = Math.max(1, page - 2);
        let end = Math.min(pages, page + 2);
        if (start > 1) html += `<span class="px-2 text-text/50">…</span>`;
        for (let i = start; i <= end; i++) html += btn(i, i, {active: i === page});
        if (end < pages) html += `<span class="px-2 text-text/50">…</span>`;
        html += btn('›', page + 1, {disabled: page === pages});
        html += btn('آخر »', pages, {disabled: page === pages});
        html += `<span class="inline-flex items-center gap-1 mr-2">
            <input type="number" min="1" max="${pages}" id="jumpPage" placeholder="${page}"
                class="w-16 px-2 py-1.5 border rounded-lg bg-background text-text text-sm text-center"
                onkeydown="if(event.key==='Enter'){jumpToPage(${pages})}">
            <button onclick="jumpToPage(${pages})" class="px-3 py-1.5 rounded-lg text-sm bg-primary text-white">برو</button>
        </span>`;
        links.html(html);
    }

    function jumpToPage(pages) {
        let p = parseInt($('#jumpPage').val());
        if (isNaN(p) || p < 1) p = 1;
        if (p > pages) p = pages;
        loadPage(p, currentFilters);
    }

    $(document).ready(() => loadPage());

    
    $('#searchButton').on('click', () => {
        const name = $('#search_name').val().trim();
        currentFilters.name = name;
        loadPage(1, currentFilters);
    });
    $('#search_name').on('keypress', e => {
        if (e.which === 13) $('#searchButton').click();
    });

    
    function showAddModal() {
        $('#addForm')[0].reset();
        $('#addProjectModal').removeClass('hidden');
    }

    function editProject(id) {
        $.get('apis/get_project.php', {
            id
        }, function(data) {
            if (data.ok) {
                $('#edit_id').val(data.project.id);
                $('#edit_name').val(data.project.name);
                $('#edit_description').val(data.project.description || '');
                $('#editProjectModal').removeClass('hidden');
            } else {
                showSnackbar(data.error, 'error');
            }
        });
    }

    function showDeleteConfirm(id, name) {
        deleteProjectId = id;
        $('#deleteMessage').text(`آیا از حذف پروژه "${name}" مطمئن هستید؟`);
        $('#deleteConfirmModal').removeClass('hidden');
    }

    
    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const orig = btn.html();
        btn.html('<span class="loading"></span> در حال ذخیره...').prop('disabled', true);

        const data = {
            name: $('#add_name').val().trim(),
            description: $('#add_description').val().trim()
        };

        $.post('apis/add_project.php', data, function(response) {
            if (response.ok) {
                showSnackbar('پروژه با موفقیت اضافه شد');
                $('#addProjectModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(response.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const orig = btn.html();
        btn.html('<span class="loading"></span> در حال ذخیره...').prop('disabled', true);

        const data = {
            id: $('#edit_id').val(),
            name: $('#edit_name').val().trim(),
            description: $('#edit_description').val().trim()
        };

        $.post('apis/edit_project.php', data, function(response) {
            if (response.ok) {
                showSnackbar('تغییرات با موفقیت ذخیره شد');
                $('#editProjectModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(response.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#confirmDeleteBtn').on('click', function() {
        const btn = $(this);
        const orig = btn.html();
        btn.html('<span class="loading"></span> در حال حذف...').prop('disabled', true);

        $.post('apis/delete_project.php', {
            id: deleteProjectId
        }, function(response) {
            if (response.ok) {
                showSnackbar('پروژه با موفقیت حذف شد');
                $('#deleteConfirmModal').addClass('hidden');
                $(`#project_${deleteProjectId}`).fadeOut(300, function() {
                    $(this).remove();
                });
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(response.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    function showSnackbar(message, type = 'success') {
        const snackbar = $('#snackbar');
        snackbar.text(message);
        snackbar.removeClass('bg-green-500 bg-red-500 dark:bg-green-600 dark:bg-red-600');
        snackbar.addClass(type === 'success' ? 'bg-green-500 dark:bg-green-600' : 'bg-red-500 dark:bg-red-600');
        snackbar.removeClass('hidden');
        setTimeout(() => snackbar.addClass('hidden'), 3000);
    }


    
    $(document).on('keydown', e => {
        if (e.key === 'Escape') $('.fixed.inset-0').addClass('hidden');
    });
    $(document).on('click', '.fixed.inset-0', e => {
        if (e.target === e.currentTarget) $(e.currentTarget).addClass('hidden');
    });

    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
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