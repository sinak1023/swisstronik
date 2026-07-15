<?php
$roles_function = new Roles($db);
$admin_info = (new Users($db))->get_by_id($_SESSION["id"]);


$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$roles = $roles_function->get_all();
$total = count($roles);
$total_pages = ceil($total / $limit);

$permissions_list = $config['permissions_list'];
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">مدیریت نقش‌ها</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">ایجاد، ویرایش و مدیریت دسترسی‌های کاربران</p>
        </div>
        <button onclick="showAddModal()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark active:bg-primary-darker text-white font-medium px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
            <i class='bx bx-plus text-lg'></i>
            نقش جدید
        </button>
    </div>

    
    <div class="bg-surface rounded-2xl shadow-sm border border-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-center">
                <thead class="bg-muted/50 border-b border-border">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-text ">نام نقش</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">مجوزها</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text no-print">عملیات</th>
                    </tr>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border  text-center">
                    <?php foreach ($roles as $role): ?>
                        <tr id="role_<?= $role['id'] ?>" class="hover:bg-muted/30 transition-colors duration-150">
                            <td class="px-6 py-4 text-text text-sm"><?= htmlspecialchars($role['name']) ?></td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex flex-wrap gap-1 max-w-md">
                                    <?php
                                    $perms = json_decode($role['permissions'], true) ?? [];
                                    foreach ($perms as $perm):
                                        $label = $permissions_list[$perm] ?? $perm;
                                    ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                            <?= htmlspecialchars($label) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (empty($perms)): ?>
                                        <span class="text-gray-500 text-xs">هیچ مجوزی ندارد</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-sm no-print">
                                <div class="flex items-center gap-2 justify-center">
                                    <button onclick="editRole(<?= $role['id'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                        <i class='bx bx-edit text-lg'></i>
                                    </button>
                                    <button onclick="showDeleteConfirm(<?= $role['id'] ?>, '<?= addslashes(htmlspecialchars($role['name'])) ?>')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                        <i class='bx bx-trash text-lg'></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between px-6 py-4 border-t border-border bg-surface">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    نمایش <?= count($roles) ?> از <?= $total ?> نقش
                </p>
                <div class="flex gap-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $i === $page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text' ?> transition-colors">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addRoleModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4" style="z-index: 50;">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg border border-border">
        <div class="flex items-center justify-between p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-plus text-primary text-xl'></i>
                ایجاد نقش جدید
            </h2>
            <button onclick="$('#addRoleModal').addClass('hidden')" class="p-2 hover:bg-muted rounded-lg transition-colors">
                <i class='bx bx-plus text-lg'></i>
            </button>
        </div>
        <form id="addForm" class="p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-text mb-2">نام نقش</label>
                <input type="text" name="name" required class="w-full px-4 py-3 rounded-xl border border-border bg-background text-text focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-3">مجوزها</label>
                <div class="space-y-3 max-h-64 overflow-y-auto p-2">
                    <?php foreach ($permissions_list as $key => $desc): ?>
                        <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-muted/50 cursor-pointer transition-colors">
                            <input type="checkbox" name="permissions[]" value="<?= $key ?>" class="w-5 h-5 text-primary rounded focus:ring-primary">
                            <span class="text-text"><?= htmlspecialchars($desc) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary-dark active:bg-primary-darker text-white font-medium py-3 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                    ایجاد نقش
                </button>
                <button type="button" onclick="$('#addRoleModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-3 rounded-xl transition-colors">
                    انصراف
                </button>
            </div>
        </form>
    </div>
</div>


<div id="editRoleModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg border border-border">
        <div class="flex items-center justify-between p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-edit text-primary text-xl'></i>

                ویرایش نقش
            </h2>
            <button onclick="$('#editRoleModal').addClass('hidden')" class="p-2 hover:bg-muted rounded-lg transition-colors">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        <form id="editForm" class="p-6 space-y-5">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-text mb-2">نام نقش</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-3 rounded-xl border border-border bg-background text-text focus:ring-2 focus:ring-primary focus:border-transparent transition-all">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-3">مجوزها</label>
                <div class="space-y-3 max-h-64 overflow-y-auto p-2">
                    <?php foreach ($permissions_list as $key => $desc): ?>
                        <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-muted/50 cursor-pointer transition-colors">
                            <input type="checkbox" name="permissions[]" value="<?= $key ?>" class="w-5 h-5 text-primary rounded focus:ring-primary">
                            <span class="text-text"><?= htmlspecialchars($desc) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary hover:bg-primary-dark active:bg-primary-darker text-white font-medium py-3 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
                    ذخیره تغییرات
                </button>
                <button type="button" onclick="$('#editRoleModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-3 rounded-xl transition-colors">
                    انصراف
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
            <h3 class="text-lg font-bold text-text">حذف نقش</h3>
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
    let deleteRoleId = null;
    const permissionsList = <?php echo json_encode($permissions_list); ?>;

    
    function showAddModal() {
        $('#addForm')[0].reset();
        $('#addRoleModal').removeClass('hidden');
    }

    
    function editRole(id) {
        $.get('apis/get_role.php', {
            id: id
        }, function(data) {
            if (data.ok) {
                $('#edit_id').val(data.role.id);
                $('#edit_name').val(data.role.name);

                $('#editForm input[name="permissions[]"]').prop('checked', false);

                (data.role.permissions || []).forEach(p => {
                    $(`#editForm input[name="permissions[]"][value="${p}"]`).prop('checked', true);
                });

                $('#editRoleModal').removeClass('hidden');
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });
    }

    
    function showDeleteConfirm(id, name) {
        deleteRoleId = id;
        $('#deleteMessage').text(`آیا از حذف نقش "${name}" مطمئن هستید؟`);
        $('#deleteConfirmModal').removeClass('hidden');
    }

    
    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const original = btn.html();
        btn.prop('disabled', true).html('<span class="loading"></span> در حال ایجاد...');

        const perms = $('#addForm input[name="permissions[]"]:checked').map(function() {
            return this.value;
        }).get();

        $.post('apis/add_role.php', {
            name: $('#addForm input[name="name"]').val().trim(),
            permissions: perms
        }, function(data) {

            if (data.ok) {
                showSnackbar('نقش با موفقیت ایجاد شد');
                $('#addRoleModal').addClass('hidden');
                addRoleToTable(data.role_id, $('#addForm input[name="name"]').val().trim(), perms);
                $('#addForm')[0].reset();
            } else {
                showSnackbar(data.error, 'error');
            }
        }).always(() => {
            btn.prop('disabled', false).html(original);
        });
    });

    
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const original = btn.html();
        btn.prop('disabled', true).html('<span class="loading"></span> در حال ذخیره...');

        const perms = $('#editForm input[name="permissions[]"]:checked').map(function() {
            return this.value;
        }).get();

        $.post('apis/edit_role.php', {
            id: $('#edit_id').val(),
            name: $('#edit_name').val().trim(),
            permissions: perms
        }, function(data) {

            if (data.ok) {
                showSnackbar('نقش با موفقیت به‌روزرسانی شد');
                $('#editRoleModal').addClass('hidden');
                updateRoleInTable($('#edit_id').val(), $('#edit_name').val().trim(), perms);
            } else {
                showSnackbar(data.error, 'error');
            }
        }).always(() => {
            btn.prop('disabled', false).html(original);
        });
    });

    
    $('#confirmDeleteBtn').on('click', function() {
        const btn = $(this);
        const original = btn.html();
        btn.prop('disabled', true).html('<span class="loading"></span> در حال حذف...');

        $.post('apis/delete_role.php', {
            id: deleteRoleId
        }, function(data) {
            if (data.ok) {
                showSnackbar('نقش با موفقیت حذف شد');
                $('#deleteConfirmModal').addClass('hidden');
                $(`#role_${deleteRoleId}`).fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                showSnackbar(data.error, 'error');
            }
        }).always(() => {
            btn.prop('disabled', false).html(original);
        });
    });

    
    function addRoleToTable(id, name, permissions) {
        const row = `
            <tr id="role_${id}" class="hover:bg-muted/30 transition-colors duration-150">
                <td class="px-6 py-4 text-text font-medium">${escapeHtml(name)}</td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1 max-w-md">
                        ${permissions.length > 0 ? permissions.map(p => `
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                ${escapeHtml(permissionsList[p] || p)}
                            </span>
                        `).join('') : '<span class="text-gray-500 text-xs">هیچ مجوزی ندارد</span>'}
                    </div>
                </td>
                <td class="px-6 py-4 no-print">
                    <div class="flex items-center gap-2">
                        <button onclick="editRole(${id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                            <i class='bx bx-edit text-lg'></i>
                        </button>
                        <button onclick="showDeleteConfirm(${id}, '${escapeHtml(name)}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class='bx bx-trash text-lg'></i>
                        </button>
                    </div>
                </td>
            </tr>`;

        
        if ($('table tbody tr').length === 0) {
            $('table tbody').html(row);
        } else {
            $('table tbody').prepend(row);
        }
    }

    
    function updateRoleInTable(id, name, permissions) {
        const $row = $(`#role_${id}`);
        const permsHtml = permissions.length > 0 ? permissions.map(p => `
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                ${escapeHtml(permissionsList[p] || p)}
            </span>
        `).join('') : '<span class="text-gray-500 text-xs">هیچ مجوزی ندارد</span>';

        $row.find('td:nth-child(1)').text(escapeHtml(name));
        $row.find('td:nth-child(2) .flex').html(permsHtml);
        $row.find('td:nth-child(3) button').first().attr('onclick', `editRole(${id})`);
        $row.find('td:nth-child(3) button').last().attr('onclick', `showDeleteConfirm(${id}, '${escapeHtml(name)}')`);

        
        $row.addClass('bg-green-50 dark:bg-green-900/20').delay(1000).queue(function() {
            $(this).removeClass('bg-green-50 dark:bg-green-900/20').dequeue();
        });
    }

    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    
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

    @keyframes slide-in-from-bottom {
        from {
            transform: translateY(100%);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes slide-out-to-bottom {
        from {
            transform: translateY(0);
            opacity: 1;
        }

        to {
            transform: translateY(100%);
            opacity: 0;
        }
    }
</style>