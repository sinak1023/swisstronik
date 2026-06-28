<?php

$user_id = $_SESSION["id"];
$user_info = (new Users($db))->get_by_id($user_id);
$persian_date = new PersianDate();

$total_leads = $db->fetch("SELECT COUNT(*) as cnt FROM leads WHERE assigned_to = ?", [$user_id])['cnt'] ?? 0;
$total_projects = $db->fetch("SELECT COUNT(DISTINCT p.id) as cnt FROM projects p LEFT JOIN leads l ON l.project_id = p.id WHERE p.created_by = ? OR l.assigned_to = ?", [$user_id, $user_id])['cnt'] ?? 0;
$today_reminders = $db->fetch("SELECT COUNT(*) as cnt FROM reminders WHERE user_id = ? AND DATE(reminder_datetime) = CURDATE() AND is_done = 0", [$user_id])['cnt'] ?? 0;
$pending_tasks = $db->fetch("SELECT COUNT(*) as cnt FROM user_tasks WHERE user_id = ? AND is_completed = 0", [$user_id])['cnt'] ?? 0;
$completed_tasks_today = $db->fetch("SELECT COUNT(*) as cnt FROM user_tasks WHERE user_id = ? AND is_completed = 1 AND DATE(updated_at) = CURDATE()", [$user_id])['cnt'] ?? 0;


$recent_leads = $db->fetchAll("SELECT l.name, l.phone, l.status, p.name as project_name, l.created_at FROM leads l LEFT JOIN projects p ON l.project_id = p.id WHERE l.assigned_to = ? ORDER BY l.created_at DESC LIMIT 6", [$user_id]);


$upcoming_reminders = $db->fetchAll("SELECT r.*, l.name as lead_name FROM reminders r LEFT JOIN leads l ON r.lead_id = l.id WHERE r.user_id = ? AND r.is_done = 0 AND r.reminder_datetime >= NOW() ORDER BY r.reminder_datetime ASC LIMIT 5", [$user_id]);


$user_tasks = $db->fetchAll("SELECT * FROM user_tasks WHERE user_id = ? ORDER BY position ASC, id ASC", [$_SESSION['id']]);


?>

<div class="max-w-7xl mx-auto p-4 md:p-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-text">خوش آمدید، <?= htmlspecialchars($user_info['name']) ?>!</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">امروز <?= $persian_date->jdate('l d F Y') ?> - عملکرد شما در یک نگاه</p>
        </div>
        <div class="text-sm text-gray-500">
            آخرین ورود: <?= $user_info['last_login'] ? $persian_date->jdate('d F Y - H:i', strtotime($user_info['last_login'])) : 'نامشخص' ?>
        </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm">کل لیدهای من</p>
                    <p class="text-3xl font-bold mt-2 count" data-target="<?= $total_leads ?>"><?= $total_leads ?></p>
                </div>
                <i class='bx bx-user-plus text-5xl opacity-30'></i>
            </div>
        </div>
        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm">پروژه فعال</p>
                    <p class="text-3xl font-bold mt-2 count" data-target="<?= $total_projects ?>"><?= $total_projects ?></p>
                </div>
                <i class='bx bx-folder-open text-5xl opacity-30'></i>
            </div>
        </div>
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm">یادآوری امروز</p>
                    <p class="text-3xl font-bold mt-2 count" data-target="<?= $today_reminders ?>"><?= $today_reminders ?></p>
                </div>
                <i class='bx bx-bell text-5xl opacity-30'></i>
            </div>
        </div>
        <div class="bg-gradient-to-r from-yellow-500 to-red-600 text-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm">تسک در انتظار</p>
                    <p class="text-3xl font-bold mt-2 count" data-target="<?= $pending_tasks ?>"><?= $pending_tasks ?></p>
                </div>
                <i class='bx bx-task text-5xl opacity-30'></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        
        <div class="xl:col-span-1">
            <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="text-xl font-bold text-text flex items-center gap-2">
                        <i class='bx bx-check-square text-primary'></i> تسک‌های من
                    </h2>
                    <button onclick="showAddTaskModal()" class="text-primary hover:bg-muted p-2 rounded-lg transition">
                        <i class='bx bx-plus text-xl'></i>
                    </button>
                </div>

                <div id="tasks-container" class="space-y-3 min-h-96">
                    <?php if (empty($user_tasks)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class='bx bx-task text-5xl mb-3 text-gray-300'></i>
                            <p>هیچ تسکی ثبت نشده</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_tasks as $task): ?>
                            <?= renderTaskItem($task) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="xl:col-span-2 space-y-6">
            
            <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
                <h2 class="text-xl font-bold mb-5 flex items-center gap-2 text-text">
                    <i class='bx bx-alarm text-yellow-500'></i> یادآوری‌های نزدیک
                </h2>
                <div class="space-y-4">
                    <?php foreach ($upcoming_reminders as $r): ?>
                        <div class="bg-background border border-border rounded-xl p-5 hover:shadow-md transition">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-text"><?= htmlspecialchars($r['reminder_text']) ?></p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <i class='bx bx-user'></i> <?= htmlspecialchars($r['lead_name'] ?? '—') ?>
                                        | <i class='bx bx-phone'></i> <?= $r['phone'] ?>
                                    </p>
                                </div>
                                <span class="text-xs bg-primary/10 text-primary px-4 py-2 rounded-full font-medium">
                                    <?= $persian_date->jdate('d F H:i', strtotime($r['reminder_datetime'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    
    <div class="mt-8 bg-surface rounded-2xl shadow-sm border border-border overflow-hidden">
        <div class="p-6 border-b border-border">
            <h2 class="text-xl font-bold text-text flex items-center gap-2">
                <i class='bx bx-list-check text-green-500'></i> آخرین لیدهای اختصاصی
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-muted">
                    <tr>
                        <th class="px-6 py-4 text-sm font-semibold text-text text-right">نام</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text text-right">پروژه</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text text-center">شماره</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text text-center">وضعیت</th>
                        <th class="px-6 py-4 text-sm font-semibold text-text text-center">زمان</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($recent_leads as $lead): ?>
                        <tr class="hover:bg-muted transition">
                            <td class="px-6 py-4 text-text"><?= htmlspecialchars($lead['name'] ?? 'بینام') ?></td>
                            <td class="px-6 py-4 text-gray-600 text-sm"><?= htmlspecialchars($lead['project_name'] ?? '—') ?></td>
                            <td class="px-6 py-4 text-center font-mono text-sm"><?= $lead['phone'] ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-xs <?= getStatusClass($lead['status']) ?>">
                                    <?= getStatusLabel($lead['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500">
                                <?= $persian_date->jdate('d F - H:i', strtotime($lead['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
function renderTaskItem($task)
{
    $checked = $task['is_completed'] ? 'checked' : '';
    $line = $task['is_completed'] ? 'line-through text-gray-500' : '';
    return "
    <div class='task-item bg-background border border-border rounded-xl p-4 flex items-center gap-3 cursor-move hover:bg-muted transition-shadow group' data-id='{$task['id']}'>
        <input type='checkbox' $checked onchange='toggleTask({$task['id']}, this.checked)' class='w-5 h-5 text-primary rounded focus:ring-primary'>
        <span class='flex-1 $line task-title' ondblclick='editTask({$task['id']}, this)'>" . htmlspecialchars($task['title']) . "</span>
        <button onclick='deleteTask({$task['id']})' class='opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-2 rounded-lg transition'>
            <i class='bx bx-trash'></i>
        </button>
    </div>";
}

function getStatusClass($status)
{
    return match ($status) {
        'success' => 'bg-green-100 text-green-800',
        'purchased' => 'bg-blue-100 text-blue-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-yellow-100 text-yellow-800'
    };
}

function getStatusLabel($status)
{
    return ['pending' => 'در انتظار', 'success' => 'موفق', 'not_answer' => 'پاسخ نداد', 'following' => 'در پیگیری', 'purchased' => 'خریداری', 'rejected' => 'رد شده'][$status] ?? $status;
}
?>


<div id="addTaskModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-surface rounded-2xl shadow-2xl border border-border p-6 w-full max-w-md">
        <h3 class="text-xl font-bold mb-4 text-text">افزودن تسک جدید</h3>
        <input type="text" id="newTaskTitle" class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:ring-2 focus:ring-primary" placeholder="عنوان تسک را وارد کنید..." autofocus>
        <div class="flex gap-3 mt-6">
            <button onclick="addTask()" class="flex-1 bg-primary hover:bg-primary-dark text-white py-3 rounded-lg font-medium transition">افزودن</button>
            <button onclick="closeAddTaskModal()" class="flex-1 bg-muted text-text py-3 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-700 transition">لغو</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('.count').forEach(el => {
            const target = parseInt(el.dataset.target);
            let count = 0;
            const inc = target / 50;
            const timer = setInterval(() => {
                count += inc;
                if (count >= target) {
                    el.textContent = target.toLocaleString('fa-IR');
                    clearInterval(timer);
                } else {
                    el.textContent = Math.floor(count).toLocaleString('fa-IR');
                }
            }, 30);
        });


        new Sortable(document.getElementById('tasks-container'), {
            animation: 150,
            onEnd: function(evt) {
                const ids = Array.from(evt.to.children).map(el => el.dataset.id);
                fetch('apis/update_task_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order: ids
                    })
                });
            }
        });
    });

    function showAddTaskModal() {
        document.getElementById('addTaskModal').classList.remove('hidden');
        document.getElementById('newTaskTitle').focus();
    }

    function closeAddTaskModal() {
        document.getElementById('addTaskModal').classList.add('hidden');
        document.getElementById('newTaskTitle').value = '';
    }

    function createTaskHtml(taskId, title, isCompleted = false) {
        const checked = isCompleted ? 'checked' : '';
        const lineThrough = isCompleted ? 'line-through text-gray-500' : '';

        return `
    <div class="task-item bg-background border border-border rounded-xl p-4 flex items-center gap-3 cursor-move hover:bg-muted transition-shadow group" data-id="${taskId}">
        <input type="checkbox" ${checked} onchange="toggleTask(${taskId}, this.checked)" class="w-5 h-5 text-primary rounded focus:ring-primary">
        <span class="flex-1 ${lineThrough} task-title" ondblclick="editTask(${taskId}, this)">${escapeHtml(title)}</span>
        <button onclick="deleteTask(${taskId})" class="opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-2 rounded-lg transition">
            <i class="bx bx-trash"></i>
        </button>
    </div>`;
    }

    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addTask() {
        const titleInput = document.getElementById('newTaskTitle');
        const title = titleInput.value.trim();
        if (!title) {
            showSnackbar('عنوان نمی‌تواند خالی باشد', 'error');
            return;
        }

        
        const addBtn = document.querySelector('#addTaskModal button[onclick="addTask()"]');
        const originalText = addBtn.textContent;
        addBtn.disabled = true;
        addBtn.textContent = 'در حال افزودن...';

        fetch('apis/add_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'title=' + encodeURIComponent(title)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    
                    const taskHtml = createTaskHtml(res.task_id || Date.now(), title, false);
                    const container = document.getElementById('tasks-container');

                    
                    if (container.querySelector('.text-center.py-12')) {
                        container.innerHTML = '';
                    }

                    container.insertAdjacentHTML('beforeend', taskHtml);

                    
                    closeAddTaskModal();
                    showSnackbar('تسک با موفقیت اضافه شد', 'success');
                    updateTaskCounter();
                } else {
                    showSnackbar(res.message || 'خطا در افزودن تسک', 'error');
                }
            })
            .catch(() => {
                showSnackbar('خطا در ارتباط با سرور', 'error');
            })
            .finally(() => {
                addBtn.disabled = false;
                addBtn.textContent = originalText;
            });
    }

    function toggleTask(id, completed) {
        const data = {
            id: id,
            completed: completed
        };
        $.post('apis/toggle_task.php', data, function(res) {
            if (res.ok) {
                const item = document.querySelector(`.task-item[data-id="${id}"]`);
                const span = item.querySelector('.task-title');
                if (completed) {
                    span.classList.add('line-through', 'text-gray-500');
                } else {
                    span.classList.remove('line-through', 'text-gray-500');
                }
                updateTaskCounter();
            } else {
                showSnackbar(res.error || 'خطا در ذخیره', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });

    }

    function deleteTask(id) {
        if (!confirm('حذف این تسک؟')) return;
        const data = {
            id: id
        };
        $.post('apis/delete_task.php', data, function(res) {
            if (res.ok) {
                document.querySelector(`.task-item[data-id="${id}"]`).remove();
                updateTaskCounter();
            } else {
                showSnackbar(res.error || 'خطا در ذخیره', 'error');
            }
        }).fail(() => {
            showSnackbar('خطا در ارتباط با سرور', 'error');
        });

    }

    function editTask(id, el) {
        const oldText = el.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = oldText;
        input.className = 'w-full px-2 py-1 border border-primary rounded';
        input.onblur = () => saveEdit(id, input.value.trim() || oldText, el);
        input.onkeydown = e => {
            if (e.key === 'Enter') input.blur();
        };
        el.replaceWith(input);
        input.focus();
    }

    function saveEdit(id, newTitle, el) {
        fetch('apis/edit_task.php', {
            method: 'POST',
            body: 'id=' + id + '&title=' + encodeURIComponent(newTitle)
        }).then(() => {
            const span = document.createElement('span');
            span.className = 'flex-1 task-title';
            span.textContent = newTitle;
            span.ondblclick = () => editTask(id, span);
            el.replaceWith(span);
        });
    }

    function updateTaskCounter() {
        const pending = document.querySelectorAll('#tasks-container input[type="checkbox"]:not(:checked)').length;
        document.querySelector('[data-target="<?= $pending_tasks ?>"]').textContent = pending;
    }
</script>