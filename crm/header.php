<html lang="fa" dir="rtl" class="light">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>CRM</title>
    <meta content="CRM Dashboard" name="description" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <link type="text/css" rel="stylesheet" href="assets/css/style.css" />


    <link type="text/css" rel="stylesheet" href="assets/plugins/persian-datepicker/jalalidatepicker.css" />


    <link type="text/css" href="assets/plugins/x-editable/css/bootstrap-editable.css" rel="stylesheet">


    <link type="text/css" rel="stylesheet" href="assets/plugins/clockpicker/jquery-clockpicker.css" />


    <link type="text/css" rel="stylesheet" href="assets/plugins/select2/css/select2.css" />

    <?php
    if (file_exists('assets/css/pages/' . $ex[0] . '.css')) {
        echo "<link href=\"assets/css/pages/" . $ex[0] . ".css?v=$version_app\" rel=\"stylesheet\" type=\"text/css\">";
    }
    ?>

    <style>
        .project-item {
            transition: all 0.15s ease;
        }

        .project-item:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .dark .project-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
    </style>


    <script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
</head>]


<body class="bg-background text-text">


    <script>
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme) {
            document.documentElement.classList.add(savedTheme);
        } else {
            const prefersDark = window.matchMedia(
                "(prefers-color-scheme: dark)"
            ).matches;
            const theme = prefersDark ? "dark" : "light";
            document.documentElement.classList.add(theme);
            localStorage.setItem("theme", theme);
        }
    </script>
    <?php if ($ex[0] == "login" || $ex[0] == "forgot_password") { ?>
        <button id="theme-toggle" class="fixed top-4 left-4 z-50 bg-white dark:bg-gray-800 text-gray-800 dark:text-white p-2 rounded-full shadow-md hover:shadow-lg transition duration-300">
            <i class='bx bx-sun' id="theme-icon" style="font-size: 1.5rem;"></i>
        </button>
    <?php } ?>
    <?php
    if (isset($_SESSION["id"]) && (int)$_SESSION["id"] > 0) {
        $users_function = new Users($db);
        $user_info = $users_function->get_by_id($_SESSION["id"]);

        $permissions = [];
        if ($user_info['role_id'] > 0) {
            $roles_function = new Roles($db);
            $role = $roles_function->get_by_id($user_info['role_id']);
            if ($role) {
                $permissions = json_decode($role['permissions'], true) ?? [];
            }
        }

        if ((int)$user_info['status'] < 1) {
            unset($_SESSION["loginok"]);
            unset($_SESSION["id"]);
            echo '<script> location.replace("login"); </script>';
        }

        $user_projects_sql = "
            SELECT DISTINCT 
                p.id, 
                p.name, 
                p.description,
                COUNT(l.id) as leads_count
            FROM projects p
            LEFT JOIN leads l ON l.project_id = p.id 
                AND (l.assigned_to = ? OR p.created_by = ?)
            WHERE p.created_by = ? OR l.assigned_to = ?
            GROUP BY p.id, p.name, p.description
            ORDER BY p.created_at DESC
            LIMIT 20
        ";
        $user_projects = $db->fetchAll($user_projects_sql, [
            $_SESSION["id"],
            $_SESSION["id"],
            $_SESSION["id"],
            $_SESSION["id"]
        ]);
    ?>
        <nav class="bg-header-bg text-white p-4 fixed w-full top-0 shadow-lg" style="z-index: 10">
            <div class="container mx-auto flex justify-between items-center">

                <button class="md:hidden order-first" onclick="$('#sidebar').toggleClass('hidden')">
                    <i class='bx bx-menu text-2xl'></i>
                </button>


                <div class="flex items-center gap-3">
                    <img src="assets/img/crm.svg" alt="logo" class="h-8">
                    <p class="text-lg font-bold">عنوان</p>
                </div>


                <div class="relative z-20">
                    <button id="userMenuButton" class="flex items-center gap-2 bg-primary-dark hover:bg-primary-darker text-white px-4 py-2 rounded-xl transition-all duration-200 font-medium border border-border">
                        <span><?= htmlspecialchars($user_info['name']) ?></span>
                        <i class='bx bx-chevron-down text-xl'></i>
                    </button>


                    <div id="userMenu" class="absolute right-0 mt-3 w-64 bg-surface rounded-2xl shadow-xl border border-border overflow-hidden hidden z-20 -mr-48" style="z-index: 20;">
                        <div class="p-4 border-b border-border">
                            <p class="text-sm font-medium text-text"><?= htmlspecialchars($user_info['name']) ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= htmlspecialchars($user_info['phone'] ?? '—') ?></p>
                        </div>

                        <a href="javascript:void(0);" onclick="lockScreen();" class="flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors">
                            <i class='bx bx-lock-alt text-xl'></i>
                            <span>قفل صفحه</span>
                        </a>

                        <a href="javascript:void(0);" id="print-page" class="flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors">
                            <i class='bx bx-printer text-xl'></i>
                            <span>پرینت صفحه</span>
                        </a>

                        <a href="javascript:void(0);" id="theme-toggle" class="w-full flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors text-left">
                            <i class='bx bx-moon text-xl' id="theme-icon"></i>
                            <span id="theme-text">حالت تاریک</span>
                        </a>
                        <?php
                        $telegram_info = !empty($user_info['telegram_info']) ? json_decode($user_info['telegram_info'], true) : null;
                        if ($telegram_info): ?>
                            <a href="javascript:void(0);" onclick="disconnectTelegram(<?= $user_info['id'] ?>)" class="flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors text-red-600 dark:text-red-400">
                                <i class='bx bxl-telegram text-xl'></i>
                                <span>قطع ارتباط تلگرام</span>
                            </a>
                        <?php else: ?>
                            <a href="https://t.me/CrmDeomoConnectBot" target="_blank" class="flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors text-blue-600 dark:text-blue-400">
                                <i class='bx bxl-telegram text-xl'></i>
                                <span>اتصال به تلگرام</span>
                            </a>
                        <?php endif; ?>
                        <a href="logout" class="flex items-center gap-3 px-4 py-3 hover:bg-muted transition-colors text-red-600 dark:text-red-400">
                            <i class='bx bx-log-out text-xl'></i>
                            <span>خروج</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div id="sidebar" class="hidden md:block fixed mt-1 right-0 w-64 h-full bg-surface shadow-lg p-4 overflow-y-auto mt-11" style="z-index: 9">


            <ul class="space-y-2">

                <li>
                    <a href="dashboard" class="block px-4 py-2 hover:bg-muted rounded flex items-center">
                        <i class='bx bx-home text-lg ml-2'></i>
                        داشبورد
                    </a>
                </li>
                <?php if (in_array('pages/users.php', $permissions)) { ?>
                    <li>
                        <a href="users" class="block px-4 py-2 hover:bg-muted rounded flex items-center">
                            <i class='bx bx-user text-lg ml-2'></i>
                            کاربران
                        </a>
                    </li>
                <?php } ?>
                <?php if (in_array('pages/projects.php', $permissions)) { ?>
                    <li>
                        <a href="projects" class="block px-4 py-2 hover:bg-muted rounded flex items-center">
                            <i class='bx bx-briefcase text-lg ml-2'></i>
                            مدیریت پروژه ها
                        </a>
                    </li>
                <?php } ?>
                <?php if (in_array('pages/roles.php', $permissions)) { ?>
                    <li>
                        <a href="roles" class="block px-4 py-2 hover:bg-muted rounded flex items-center">
                            <i class='bx bx-shield-alt-2 text-lg ml-2'></i>
                            مدیریت نقش‌ها
                        </a>
                    </li>
                <?php } ?>
                <?php if (in_array('pages/message_templates.php', $permissions)): ?>
                    <li>
                        <a href="message_templates" class="block px-4 py-2.5 hover:bg-muted rounded-lg flex items-center transition-all">
                            <i class='bx bx-library text-lg ml-3'></i>
                            <span>قالب‌های پیام</span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (!empty($user_projects)): ?>
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3 px-2">
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                پروژه‌های من
                            </h3>
                            <?php if (count($user_projects) > 5): ?>
                                <a href="projects" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    همه
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-1">
                            <?php foreach (array_slice($user_projects, 0, 8) as $proj): ?>
                                <div class="project-item group relative rounded-lg cursor-pointer">
                                    <a href="my_leads?project_id=<?= $proj['id'] ?>" class="flex items-center justify-between px-3 py-2.5">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <i class='bx bx-folder text-lg text-gray-400 dark:text-gray-500'></i>
                                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                                <?= htmlspecialchars($proj['name']) ?>
                                            </span>
                                        </div>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 mr-2">
                                            <?= $proj['leads_count'] ?? 0 ?>
                                        </span>
                                    </a>


                                    <div class="absolute left-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded" onclick="event.preventDefault(); event.stopPropagation(); toggleProjectMenu(<?= $proj['id'] ?>)">
                                            <i class='bx bx-dots-horizontal-rounded text-gray-600 dark:text-gray-400'></i>
                                        </button>
                                    </div>


                                    <div id="project-menu-<?= $proj['id'] ?>" class="hidden absolute left-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
                                        <a href="leads?project_id=<?= $proj['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <i class='bx bx-list-ul text-base'></i>
                                            <span>لیدها</span>
                                        </a>
                                        <a href="fixed_texts?project_id=<?= $proj['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <i class='bx bx-message-square-dots text-base'></i>
                                            <span>متن‌های ثابت</span>
                                        </a>
                                        <a href="project_edit?id=<?= $proj['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <i class='bx bx-cog text-base'></i>
                                            <span>تنظیمات</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </ul>

        </div>
    <?php } ?>

    <div class="  <?php echo ((isset($_SESSION["id"]) && (int)$_SESSION["id"] > 0) ?  "mt-16 md:mr-64 p-4 mx-auto " : "") ?>">

        <div id="loadingOverlay" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="border-4 border-t-4 border-primary border-opacity-25 rounded-full animate-spin w-12 h-12 border-t-primary"></div>
        </div>


        <div id="lockScreenModal" class="fixed inset-0 bg-background bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 hidden" style="z-index: 55">
            <div class="bg-surface p-8 rounded-lg shadow-xl">
                <p class="my-3">لطفا رمز عبور خود را وارد نمایید</p>
                <input type="password" id="unlockPassword" placeholder="رمز عبور" class="w-full px-4 py-2 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-primary text-text bg-background">
                <button id="unlockScreen" onclick="unlockScreen()" class="bg-primary text-white py-2 px-4 rounded-lg hover:bg-primary-darker">باز کردن</button>
            </div>
        </div>

        <script>
            function disconnectTelegram(userId) {
                if (!confirm('آیا از قطع ارتباط تلگرام مطمئن هستید؟')) return;

                fetch('apis/disconnect_telegram.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'user_id=' + userId
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            alert('ارتباط تلگرام با موفقیت قطع شد.');
                            location.reload();
                        } else {
                            alert('خطا: ' + res.message);
                        }
                    });
            }
        </script>