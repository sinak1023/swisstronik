<?php
$users_function = new Users($db);
$roles_function = new Roles($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);


$all_roles = $roles_function->get_all();
$roles_map = [];
foreach ($all_roles as $role) {
    $roles_map[$role['id']] = $role['name'];
}


$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 100;
$offset = ($page - 1) * $limit;
$filters = [];


if (!empty($_GET['role_id'])) {
    $filters['role_id'] = (int)$_GET['role_id'];
}
if (isset($_GET['status']) && in_array($_GET['status'], ['0', '1'])) {
    $filters['status'] = (int)$_GET['status'];
}


$users = $users_function->get_all_with_pagination($filters, $limit, $offset);
$total = $users_function->get_total_count($filters);
$total_pages = ceil($total / $limit);

echo '<script>
if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}
</script>';
?>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">مدیریت کاربران</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">ایجاد، ویرایش و مدیریت کاربران سیستم</p>
        </div>
        <button onclick="showAddModal()" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark active:bg-primary-darker text-white font-medium px-5 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all duration-200">
            <i class='bx bx-plus text-lg'></i>
            کاربر جدید
        </button>
    </div>

    
    <div class="bg-surface rounded-2xl shadow-sm border border-border p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-text mb-2">جستجو بر اساس نام</label>
                <input id="search_name" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder=" نام  ..." value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">شماره تلفن</label>
                <input id="search_phone" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder="شماره تلفن..." value="<?= htmlspecialchars($_GET['search_phone'] ?? '') ?>" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">ایمیل</label>
                <input id="search_email" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder="ایمیل..." value="<?= htmlspecialchars($_GET['search_email'] ?? '') ?>" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">نقش</label>
                <select id="search_role" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
                    <option value="">همه نقش‌ها</option>
                    <?php foreach ($all_roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= (!empty($_GET['role_id']) && $_GET['role_id'] == $role['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">وضعیت</label>
                <select id="search_status" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
                    <option value="">همه کاربران</option>
                    <option value="1" <?= (isset($_GET['status']) && $_GET['status'] == '1') ? 'selected' : '' ?>>فعال</option>
                    <option value="0" <?= (isset($_GET['status']) && $_GET['status'] == '0') ? 'selected' : '' ?>></option>
                </select>
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
                        <th class="px-6 py-4 text-sm font-semibold text-text">نام کامل</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">شماره تلفن</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">ایمیل</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text">وضعیت / نقش</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text no-print">عملیات</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody" class="divide-y divide-border">
                    <?php foreach ($users as $user): 
                        $role_name = $user['role_id'] && isset($roles_map[$user['role_id']]) ? $roles_map[$user['role_id']] : 'بدون نقش';
                        $status_text = $user['status'] == 1 ? 'فعال' : 'غیرفعال';
                        $status_color = $user['status'] == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
                    ?>
                        <tr id="user_<?= $user['id'] ?>" class="hover:bg-muted/30 transition-colors duration-150">
                            <td class="px-6 py-4 text-text font-medium text-sm ">
                                <?= htmlspecialchars($user['name'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 text-text text-sm "><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                            <td class="px-6 py-4 text-text text-sm "><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td class="px-6 py-4 text-sm ">
                                <div class="flex items-center gap-2 justify-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= $status_color ?>">
                                        <?= $status_text ?>
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        <?= htmlspecialchars($role_name) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 no-print">
                                <div class="flex items-center gap-2 justify-center">
                                    <button onclick="editUser(<?= $user['id'] ?>)" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="ویرایش">
                                        <i class='bx bx-edit text-lg'></i>
                                    </button>
                                    <button onclick="showChangePasswordModal(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['name'])) ?>')" class="p-2 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition-colors" title="تغییر رمز عبور">
                                        <i class='bx bx-key text-lg'></i>
                                    </button>
                                    <button onclick="showResetLimitsConfirm(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['name'])) ?>')" class="p-2 text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-lg transition-colors" title="رفع محدودیت ورود">
                                        <i class='bx bx-lock-open-alt text-lg'></i>
                                    </button>
                                    <?php if ($user['status'] == 1): ?>
                                        <button onclick="showDeleteUserConfirm(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['name'])) ?>')" 
                                                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                                                title="غیرفعال‌سازی">
                                            <i class='bx bx-trash text-lg'></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="showActivateUserConfirm(<?= $user['id'] ?>, '<?= addslashes(htmlspecialchars($user['name'])) ?>')" 
                                                class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors"
                                                title="فعال‌سازی">
                                            <i class='bx bx-check-circle text-lg'></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        
        <div id="paginationContainer" class="flex items-center justify-between px-6 py-4 border-t border-border bg-surface">
            <p id="paginationInfo" class="text-sm text-gray-600 dark:text-gray-400">
                نمایش <?= count($users) ?> از <?= $total ?> کاربر
            </p>
            <div id="paginationLinks" class="flex gap-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" onclick="loadPage(<?= $i ?>); return false;" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $i === $page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text' ?> transition-colors">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>


<div id="addUserModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg border border-border">
        <div class="flex items-center justify-between p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-plus text-primary text-xl'></i>
                ایجاد کاربر جدید
            </h2>
            <button onclick="$('#addUserModal').addClass('hidden')" class="p-2 hover:bg-muted rounded-lg transition-colors">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        <form id="addForm" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-text mb-2">نام</label>
                <input id="add_name" name="name" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">شماره تلفن</label>
                <input id="add_phone" name="phone" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">ایمیل</label>
                <input id="add_email" name="email" type="email" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">رمز عبور</label>
                <input id="add_password" name="password" type="password" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">نقش</label>
                <select id="add_role_id" name="role_id" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
                    <option value="">انتخاب نقش</option>
                    <?php foreach ($all_roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">آیدی عددی تلگرام (برای ارسال نوتیف)</label>
                <input id="add_telegram_chat_id" name="telegram_chat_id" type="text" dir="ltr" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder="مثلاً 123456789">
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="$('#addUserModal').addClass('hidden')" class="px-5 py-2.5 bg-muted hover:bg-muted/80 text-text rounded-lg font-medium transition-colors">
                    انصراف
                </button>
                <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                    <i class='bx bx-save'></i>
                    ذخیره
                </button>
            </div>
        </form>
    </div>
</div>


<div id="editUserModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-lg border border-border">
        <div class="flex items-center justify-between p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-edit text-primary text-xl'></i>
                ویرایش کاربر
            </h2>
            <button onclick="$('#editUserModal').addClass('hidden')" class="p-2 hover:bg-muted rounded-lg transition-colors">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        <form id="editForm" class="p-6 space-y-4">
            <input type="hidden" id="edit_id">
            <div>
                <label class="block text-sm font-medium text-text mb-2">نام</label>
                <input id="edit_name" name="name" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">شماره تلفن</label>
                <input id="edit_phone" name="phone" type="text" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">ایمیل</label>
                <input id="edit_email" name="email" type="email" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">نقش</label>
                <select id="edit_role_id" name="role_id" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text">
                    <option value="">انتخاب نقش</option>
                    <?php foreach ($all_roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">آیدی عددی تلگرام (برای ارسال نوتیف)</label>
                <input id="edit_telegram_chat_id" name="telegram_chat_id" type="text" dir="ltr" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" placeholder="مثلاً 123456789">
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="$('#editUserModal').addClass('hidden')" class="px-5 py-2.5 bg-muted hover:bg-muted/80 text-text rounded-lg font-medium transition-colors">
                    انصراف
                </button>
                <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                    <i class='bx bx-save'></i>
                    ذخیره تغییرات
                </button>
            </div>
        </form>
    </div>
</div>


<div id="changePasswordModal" class="hidden fixed inset-0 bg-background/50 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-md border border-border">
        <div class="flex items-center justify-between p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-key text-primary text-xl'></i>
                تغییر رمز عبور
            </h2>
            <button onclick="$('#changePasswordModal').addClass('hidden')" class="p-2 hover:bg-muted rounded-lg transition-colors">
                <i class='bx bx-x text-xl'></i>
            </button>
        </div>
        <form id="changePasswordForm" class="p-6 space-y-4">
            <input type="hidden" id="cp_user_id">
            <p class="text-sm text-gray-600 dark:text-gray-400">کاربر: <span id="cp_user_name" class="font-medium"></span></p>
            <div>
                <label class="block text-sm font-medium text-text mb-2">رمز عبور جدید</label>
                <input id="cp_password" type="password" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-text mb-2">تکرار رمز عبور</label>
                <input id="cp_password_confirm" type="password" class="w-full px-4 py-2.5 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background text-text" required>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="$('#changePasswordModal').addClass('hidden')" class="px-5 py-2.5 bg-muted hover:bg-muted/80 text-text rounded-lg font-medium transition-colors">
                    انصراف
                </button>
                <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                    <i class='bx bx-save'></i>
                    ذخیره
                </button>
            </div>
        </form>
    </div>
</div>


<div id="deleteUserModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-sm border border-border p-6">
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-text">غیرفعال‌سازی کاربر</h3>
            <p id="deleteUserMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-400"></p>
        </div>
        <div class="flex gap-3">
            <button id="confirmDeleteUserBtn" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-medium py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all">
                غیرفعال کن
            </button>
            <button onclick="$('#deleteUserModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-2.5 rounded-xl transition-colors">
                انصراف
            </button>
        </div>
    </div>
</div>


<div id="activateUserModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-sm border border-border p-6">
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-text">فعال‌سازی کاربر</h3>
            <p id="activateUserMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-400"></p>
        </div>
        <div class="flex gap-3">
            <button id="confirmActivateUserBtn" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all">
                فعال کن
            </button>
            <button onclick="$('#activateUserModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-2.5 rounded-xl transition-colors">
                انصراف
            </button>
        </div>
    </div>
</div>


<div id="resetLimitsModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-surface rounded-2xl shadow-2xl w-full max-w-sm border border-border p-6">
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-yellow-100 dark:bg-yellow-900/20 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-text">رفع محدودیت ورود</h3>
            <p id="resetLimitsMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-400"></p>
        </div>
        <div class="flex gap-3">
            <button id="confirmResetLimitsBtn" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all">
                رفع محدودیت
            </button>
            <button onclick="$('#resetLimitsModal').addClass('hidden')" class="flex-1 bg-muted hover:bg-muted/80 text-text font-medium py-2.5 rounded-xl transition-colors">
                انصراف
            </button>
        </div>
    </div>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 z-60 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index: 60"></div>

<script>
    const rolesMap = <?= json_encode($roles_map) ?>;
    let actionUserId = null;
    let actionUserName = null;
    let currentPage = <?= $page ?>;
    let currentFilters = <?= json_encode($filters) ?>;

    function showAddModal() {
        $('#addForm')[0].reset();
        $('#addUserModal').removeClass('hidden');
    }

    function showChangePasswordModal(id, name) {
        $('#cp_user_id').val(id);
        $('#cp_user_name').text(name);
        $('#changePasswordForm')[0].reset();
        $('#changePasswordModal').removeClass('hidden');
    }

    function showDeleteUserConfirm(id, name) {
        actionUserId = id;
        actionUserName = name;
        $('#deleteUserMessage').text(`آیا مطمئنید می‌خواهید کاربر "${name}" را غیرفعال کنید؟`);
        $('#deleteUserModal').removeClass('hidden');
    }

    function showActivateUserConfirm(id, name) {
        actionUserId = id;
        actionUserName = name;
        $('#activateUserMessage').text(`آیا مطمئنید می‌خواهید کاربر "${name}" را فعال کنید؟`);
        $('#activateUserModal').removeClass('hidden');
    }

    function showResetLimitsConfirm(id, name) {
        actionUserId = id;
        actionUserName = name;
        $('#resetLimitsMessage').text(`آیا از رفع محدودیت ورود و ریست رمز عبور برای "${name}" مطمئنید؟`);
        $('#resetLimitsModal').removeClass('hidden');
    }

    function editUser(id) {
        $.get('apis/get_user.php', {id: id}, function(data) {
            if (data.ok) {
                const user = data.user;
                $('#edit_id').val(user.id);
                $('#edit_name').val(user.name || '');
                $('#edit_phone').val(user.phone || '');
                $('#edit_email').val(user.email || '');
                $('#edit_role_id').val(user.role_id || '');
                $('#edit_telegram_chat_id').val(user.telegram_chat_id || '');
                $('#editUserModal').removeClass('hidden');
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => showSnackbar('خطا در ارتباط با سرور', 'error'));
    }

    function loadPage(page = 1, filters = {}) {
        currentPage = page;
        currentFilters = filters;
        const params = new URLSearchParams({page, limit: 100, ...filters}).toString();
        window.history.replaceState({}, '', `?${params}`);

        $.get('apis/search_users.php', {page, limit: 100, ...filters}, function(data) {
            if (data.ok) {
                $('#usersTableBody').html(data.users.map(user => {
                    const roleName = user.role_id && rolesMap[user.role_id] ? rolesMap[user.role_id] : 'بدون نقش';
                    const statusText = user.status == 1 ? 'فعال' : 'غیرفعال';
                    const statusColor = user.status == 1 ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
                    const actionButton = user.status == 1
                        ? `<button onclick="showDeleteUserConfirm(${user.id}, '${escapeHtml(user.name || '')}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="غیرفعال‌سازی"><i class='bx bx-trash text-lg'></i></button>`
                        : `<button onclick="showActivateUserConfirm(${user.id}, '${escapeHtml(user.name || '')}')" class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors" title="فعال‌سازی"><i class='bx bx-check-circle text-lg'></i></button>`;

                    return `
                        <tr id="user_${user.id}" class="hover:bg-muted/30 transition-colors duration-150">
                            <td class="px-6 py-4 text-text text-sm  font-medium">${escapeHtml(user.name || '')}</td>
                            <td class="px-6 py-4 text-text text-sm ">${escapeHtml(user.phone || '')}</td>
                            <td class="px-6 py-4 text-text text-sm ">${escapeHtml(user.email || '')}</td>
                            <td class="px-6 py-4 text-sm ">
                                <div class="flex items-center gap-2 justify-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusColor}">
                                        ${statusText}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        ${escapeHtml(roleName)}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 no-print">
                                <div class="flex items-center gap-2 justify-center">
                                    <button onclick="editUser(${user.id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="ویرایش">
                                        <i class='bx bx-edit text-lg'></i>
                                    </button>
                                    <button onclick="showChangePasswordModal(${user.id}, '${escapeHtml(user.name || '')}')" class="p-2 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition-colors" title="تغییر رمز عبور">
                                        <i class='bx bx-key text-lg'></i>
                                    </button>
                                    <button onclick="showResetLimitsConfirm(${user.id}, '${escapeHtml(user.name || '')}')" class="p-2 text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-lg transition-colors" title="رفع محدودیت ورود">
                                        <i class='bx bx-lock-open-alt text-lg'></i>
                                    </button>
                                    ${actionButton}
                                </div>
                            </td>
                        </tr>
                    `;
                }).join(''));

                $('#paginationInfo').text(`نمایش ${data.users.length} از ${data.total} کاربر`);
                const pages = [];
                for (let i = 1; i <= data.total_pages; i++) {
                    pages.push(`<a onclick="loadPage(${i}, currentFilters)" class="px-3 py-1.5 rounded-lg text-sm font-medium ${i === page ? 'bg-primary text-white' : 'bg-muted hover:bg-muted/80 text-text'} transition-colors">${i}</a>`);
                }
                $('#paginationLinks').html(pages.join(''));
            } else {
                showSnackbar(data.error, 'error');
            }
        }).fail(() => showSnackbar('خطا در ارتباط با سرور', 'error'));
    }

    
    $('#searchButton').on('click', () => {
        const filters = {
            name: $('#search_name').val().trim(),
            phone: $('#search_phone').val().trim(),
            email: $('#search_email').val().trim(),
            role_id: $('#search_role').val(),
            status: $('#search_status').val()
        };
        loadPage(1, filters);
    });

    $('#search_name, #search_phone, #search_email, #search_role, #search_status').on('change keypress', e => {
        if (e.type === 'change' || e.which === 13) $('#searchButton').click();
    });

    
    $('#addForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال ذخیره...').prop('disabled', true);

        const data = {
            name: $('#add_name').val().trim(),
            phone: $('#add_phone').val().trim(),
            email: $('#add_email').val().trim(),
            password: $('#add_password').val(),
            role_id: $('#add_role_id').val(),
            telegram_chat_id: $('#add_telegram_chat_id').val().trim()
        };

        $.post('apis/add_user.php', data, function(response) {
            if (response.ok) {
                showSnackbar('کاربر با موفقیت اضافه شد');
                $('#addUserModal').addClass('hidden');
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
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال ذخیره...').prop('disabled', true);

        const data = {
            id: $('#edit_id').val(),
            name: $('#edit_name').val().trim(),
            phone: $('#edit_phone').val().trim(),
            email: $('#edit_email').val().trim(),
            role_id: $('#edit_role_id').val(),
            telegram_chat_id: $('#edit_telegram_chat_id').val().trim()
        };

        $.post('apis/edit_user.php', data, function(response) {
            if (response.ok) {
                showSnackbar('تغییرات با موفقیت ذخیره شد');
                $('#editUserModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(response.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();
        const pass1 = $('#cp_password').val();
        const pass2 = $('#cp_password_confirm').val();
        if (pass1 !== pass2) {
            showSnackbar('رمزهای عبور مطابقت ندارند', 'error');
            return;
        }

        const btn = $(this).find('button[type="submit"]');
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال ذخیره...').prop('disabled', true);

        $.post('apis/edit_user_password.php', {
            id: $('#cp_user_id').val(),
            password: pass1
        }, function(res) {
            if (res.ok) {
                showSnackbar('رمز عبور با موفقیت تغییر کرد');
                $('#changePasswordModal').addClass('hidden');
            } else {
                showSnackbar(res.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#confirmDeleteUserBtn').on('click', function() {
        const btn = $(this);
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال پردازش...').prop('disabled', true);

        $.post('apis/toggle_user_status.php', {id: actionUserId, status: 0}, function(res) {
            if (res.ok) {
                showSnackbar('کاربر با موفقیت غیرفعال شد');
                $('#deleteUserModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(res.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#confirmActivateUserBtn').on('click', function() {
        const btn = $(this);
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال پردازش...').prop('disabled', true);

        $.post('apis/toggle_user_status.php', {id: actionUserId, status: 1}, function(res) {
            if (res.ok) {
                showSnackbar('کاربر با موفقیت فعال شد');
                $('#activateUserModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(res.error, 'error');
            }
        }).always(() => btn.html(orig).prop('disabled', false));
    });

    
    $('#confirmResetLimitsBtn').on('click', function() {
        const btn = $(this);
        const orig = btn.html();
        btn.html('<i class="bx bx-loader-alt animate-spin"></i> در حال پردازش...').prop('disabled', true);

        $.post('apis/reset_user_limits.php', {id: actionUserId}, function(res) {
            if (res.ok) {
                showSnackbar('محدودیت‌ها با موفقیت رفع شد');
                $('#resetLimitsModal').addClass('hidden');
                loadPage(currentPage, currentFilters);
            } else {
                showSnackbar(res.error, 'error');
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

    
    $(document).on('keydown', e => { if (e.key === 'Escape') $('.fixed.inset-0').addClass('hidden'); });
    $(document).on('click', '.fixed.inset-0', e => { if (e.target === e.currentTarget) $(e.currentTarget).addClass('hidden'); });

    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .bx-loader-alt { animation: spin 1s linear infinite; }
</style>