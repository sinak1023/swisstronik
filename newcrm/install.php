<?php
/**
 * نصاب گرافیکی CRM
 * - اطلاعات دیتابیس و حساب مدیر را می‌گیرد
 * - فایل db_config.php را می‌سازد
 * - تمام جدول‌های موردنیاز را از صفر ایجاد می‌کند
 * - نقش «مدیر کل» با همهٔ دسترسی‌ها و یک کاربر مدیر می‌سازد
 *
 * پس از نصب موفق، فایل install.lock ساخته می‌شود و این صفحه دیگر اجرا نمی‌شود.
 * برای نصب مجدد، فایل‌های install.lock و db_config.php را حذف کنید.
 */

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
session_start();

$lockFile = __DIR__ . '/install.lock';
$alreadyInstalled = file_exists($lockFile);

// لیست دسترسی‌ها از config.php خوانده می‌شود (برای ساخت نقش مدیر کل)
$permissions_list = [];
if (file_exists(__DIR__ . '/config.php')) {
    // فقط برای دریافت permissions_list (اتصال ناموفق دیتابیس در این مرحله بی‌اهمیت است)
    @include __DIR__ . '/config.php';
}
if (isset($config['permissions_list'])) {
    $permissions_list = array_keys($config['permissions_list']);
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = (string)($_POST['db_pass'] ?? '');

    $admin_name  = trim($_POST['admin_name'] ?? '');
    $admin_phone = trim($_POST['admin_phone'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass  = (string)($_POST['admin_pass'] ?? '');

    $bot_token       = trim($_POST['bot_token'] ?? '');
    $manager_phone   = trim($_POST['manager_phone'] ?? '');

    // اعتبارسنجی اولیه
    if ($db_name === '' || $db_user === '') $errors[] = 'نام دیتابیس و نام کاربری دیتابیس الزامی است.';
    if ($admin_name === '' || $admin_phone === '') $errors[] = 'نام و شماره موبایل مدیر الزامی است.';
    if (strlen($admin_pass) < 6) $errors[] = 'رمز عبور مدیر باید حداقل ۶ کاراکتر باشد.';

    $pdo = null;
    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            );
        } catch (PDOException $e) {
            $errors[] = 'اتصال به دیتابیس ناموفق بود: ' . $e->getMessage();
        }
    }

    if (empty($errors) && $pdo) {
        try {
            create_schema($pdo);
            seed_settings($pdo, $bot_token, $manager_phone);
            $role_id = create_admin_role($pdo, $permissions_list);
            create_admin_user($pdo, $admin_name, $admin_phone, $admin_email, $admin_pass, $role_id);

            // نوشتن فایل اطلاعات دیتابیس
            write_db_config(__DIR__ . '/db_config.php', $db_host, $db_name, $db_user, $db_pass, $bot_token);

            // قفل نصب
            @file_put_contents($lockFile, 'installed at ' . date('Y-m-d H:i:s'));

            $success = true;
        } catch (Exception $e) {
            $errors[] = 'خطا در ساخت جدول‌ها: ' . $e->getMessage();
        }
    }
}

// ---------- توابع نصب ----------

function write_db_config($path, $host, $name, $user, $pass, $bot)
{
    $esc = fn($v) => addslashes($v);
    $php = "<?php\n";
    $php .= "// این فایل توسط install.php ساخته شده است.\n";
    $php .= "\$config['db_host'] = '" . $esc($host) . "';\n";
    $php .= "\$config['db_name'] = '" . $esc($name) . "';\n";
    $php .= "\$config['db_user'] = '" . $esc($user) . "';\n";
    $php .= "\$config['db_pass'] = '" . $esc($pass) . "';\n";
    if ($bot !== '') {
        $php .= "\$config['bot_token'] = '" . $esc($bot) . "';\n";
    }
    if (@file_put_contents($path, $php) === false) {
        throw new Exception('نوشتن فایل db_config.php ناموفق بود؛ دسترسی نوشتن پوشه را بررسی کنید.');
    }
}

function create_schema($pdo)
{
    $charset = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `permissions` LONGTEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(190) NULL,
        `phone` VARCHAR(20) NULL,
        `password` VARCHAR(255) NULL,
        `role_id` INT NULL,
        `permissions` LONGTEXT NULL,
        `status` TINYINT(1) NOT NULL DEFAULT 1,
        `telegram_info` TEXT NULL,
        `telegram_chat_id` VARCHAR(60) NULL,
        `last_login` VARCHAR(40) NULL,
        `refus_try` INT NOT NULL DEFAULT 0,
        `limit_login` VARCHAR(40) NULL,
        `refus_try_reset` INT NOT NULL DEFAULT 0,
        `limit_reset` VARCHAR(40) NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_phone` (`phone`),
        INDEX `idx_email` (`email`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `projects` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(190) NOT NULL,
        `description` TEXT NULL,
        `created_by` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_created_by` (`created_by`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NULL,
        `name` VARCHAR(190) NULL,
        `phone` VARCHAR(20) NOT NULL,
        `phone_norm` VARCHAR(20) NULL,
        `whatsapp_phone` VARCHAR(20) NULL,
        `telegram_phone` VARCHAR(20) NULL,
        `telegram_id` VARCHAR(60) NULL,
        `bale_phone` VARCHAR(20) NULL,
        `notes` TEXT NULL,
        `assigned_to` INT NULL,
        `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
        `called` TINYINT(1) NOT NULL DEFAULT 0,
        `called_at` DATETIME NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_project_phone` (`project_id`, `phone`),
        INDEX `idx_assigned` (`assigned_to`),
        INDEX `idx_status` (`status`),
        INDEX `idx_phone_norm` (`phone_norm`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `lead_numbers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `phone_norm` VARCHAR(20) NULL,
        `assigned_to` INT NULL,
        `assigned_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        INDEX `idx_phone_norm` (`phone_norm`),
        INDEX `idx_lead` (`lead_id`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `lead_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `user_id` INT NULL,
        `note` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_phone` (`phone`),
        INDEX `idx_lead` (`lead_id`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `reminders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `user_id` INT NULL,
        `reminder_datetime` DATETIME NULL,
        `reminder_text` TEXT NULL,
        `is_done` TINYINT(1) NOT NULL DEFAULT 0,
        `pre_notified` TINYINT(1) NOT NULL DEFAULT 0,
        `sms_status` VARCHAR(20) NULL,
        `sms_message_id` VARCHAR(60) NULL,
        `sent_at` DATETIME NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_dt` (`reminder_datetime`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `position` INT NOT NULL DEFAULT 0,
        `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
        `task_date` DATE NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `task_date`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(190) NOT NULL,
        `content` TEXT NULL,
        `category` VARCHAR(50) NULL DEFAULT 'عمومی',
        `user_id` INT NULL,
        `project_id` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `skey` VARCHAR(100) NOT NULL UNIQUE,
        `svalue` TEXT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `phone_norm` VARCHAR(20) NULL,
        `user_id` INT NULL,
        `project_id` INT NULL,
        `amount` BIGINT NULL,
        `transaction_ref` VARCHAR(100) NULL,
        `transaction_date` DATETIME NULL,
        `payment_type` VARCHAR(20) NOT NULL DEFAULT 'full',
        `source` VARCHAR(20) NOT NULL DEFAULT 'zarinpal',
        `receipt_image` VARCHAR(255) NULL,
        `period` VARCHAR(120) NULL,
        `note` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_txn` (`transaction_ref`),
        INDEX `idx_user` (`user_id`),
        INDEX `idx_phone_norm` (`phone_norm`),
        INDEX `idx_txn_date` (`transaction_date`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `call_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `user_id` INT NOT NULL,
        `channel` VARCHAR(20) NOT NULL DEFAULT 'normal',
        `status` VARCHAR(30) NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `created_at`),
        INDEX `idx_channel` (`channel`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `lead_id` INT NULL,
        `phone` VARCHAR(20) NULL,
        `user_id` INT NOT NULL,
        `channel` VARCHAR(20) NOT NULL DEFAULT 'sms',
        `message` TEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'sent',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `created_at`),
        INDEX `idx_channel` (`channel`)
    ) $charset");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `action` VARCHAR(60) NOT NULL,
        `entity` VARCHAR(60) NULL,
        `entity_id` INT NULL,
        `meta` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `created_at`)
    ) $charset");
}

function seed_settings($pdo, $bot_token, $manager_phone)
{
    $defaults = [
        'active_sms_panel'     => 'ippanel',
        'ippanel_username'     => '',
        'ippanel_password'     => '',
        'ippanel_number'       => '',
        'melipayamak_username' => '',
        'melipayamak_password' => '',
        'melipayamak_number'   => '',
        'bot_token'            => $bot_token,
        'manager_phone'        => $manager_phone,
        'manager_telegram_id'  => '',
        'ippanel_apikey'       => '',
        'sms_mode'             => 'normal',
        'pattern_reminder'     => '',
        'pattern_sale_expert'  => '',
        'pattern_sale_manager' => '',
        'zarinpal_token'       => '',
        'zarinpal_terminal_id' => '',
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
}

function create_admin_role($pdo, $permissions_list)
{
    // اگر نقشی با این نام وجود دارد، همان را برگردان
    $existing = $pdo->query("SELECT id FROM roles WHERE name = 'مدیر کل' LIMIT 1")->fetch();
    if ($existing) {
        // به‌روزرسانی دسترسی‌ها به کامل‌ترین حالت
        $pdo->prepare("UPDATE roles SET permissions = ? WHERE id = ?")
            ->execute([json_encode($permissions_list, JSON_UNESCAPED_UNICODE), $existing['id']]);
        return $existing['id'];
    }
    $stmt = $pdo->prepare("INSERT INTO roles (name, permissions) VALUES ('مدیر کل', ?)");
    $stmt->execute([json_encode($permissions_list, JSON_UNESCAPED_UNICODE)]);
    return $pdo->lastInsertId();
}

function create_admin_user($pdo, $name, $phone, $email, $pass, $role_id)
{
    // جلوگیری از تکرار بر اساس شماره
    $exists = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $exists->execute([$phone]);
    if ($exists->fetch()) {
        throw new Exception('کاربری با این شماره موبایل از قبل وجود دارد.');
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role_id, status, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $stmt->execute([$name, $email ?: null, $phone, $hash, $role_id]);
    return $pdo->lastInsertId();
}

// مقادیر برای حفظ ورودی‌ها هنگام خطا
function old($k, $d = '') { return htmlspecialchars($_POST[$k] ?? $d); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>body{font-family:Tahoma,Arial,sans-serif}</style>
</head>
<body class="bg-gray-100 min-h-screen py-10">
<div class="max-w-2xl mx-auto px-4">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">نصب سیستم CRM</h1>
        <p class="text-gray-500 mt-2">اطلاعات دیتابیس و حساب مدیر را وارد کنید تا جدول‌ها ساخته شوند</p>
    </div>

    <?php if ($alreadyInstalled): ?>
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded-2xl p-6 text-center">
            <h2 class="text-xl font-bold mb-2">سیستم قبلاً نصب شده است</h2>
            <p>برای نصب مجدد، فایل‌های <code>install.lock</code> و <code>db_config.php</code> را حذف کنید.</p>
            <a href="login" class="inline-block mt-4 bg-blue-600 text-white px-6 py-2.5 rounded-lg">ورود به پنل</a>
        </div>
    <?php elseif ($success): ?>
        <div class="bg-green-50 border border-green-300 text-green-800 rounded-2xl p-6 text-center">
            <h2 class="text-2xl font-bold mb-2">✅ نصب با موفقیت انجام شد</h2>
            <p class="mb-2">جدول‌ها ساخته شدند و حساب مدیر ایجاد شد.</p>
            <p class="text-sm text-red-600 font-bold">برای امنیت، همین حالا فایل <code>install.php</code> را حذف کنید.</p>
            <a href="login" class="inline-block mt-4 bg-green-600 text-white px-8 py-3 rounded-lg font-bold">ورود به پنل</a>
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-300 text-red-800 rounded-xl p-4 mb-6">
                <ul class="list-disc pr-5 space-y-1">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-6">
            <div class="bg-white rounded-2xl shadow p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-800">۱) اطلاعات دیتابیس (MySQL)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">هاست دیتابیس</label>
                        <input name="db_host" value="<?= old('db_host', 'localhost') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">نام دیتابیس</label>
                        <input name="db_name" value="<?= old('db_name') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">کاربر دیتابیس</label>
                        <input name="db_user" value="<?= old('db_user') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">رمز دیتابیس</label>
                        <input name="db_pass" type="text" value="<?= old('db_pass') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-800">۲) حساب مدیر</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">نام مدیر</label>
                        <input name="admin_name" value="<?= old('admin_name') ?>" class="w-full border rounded-lg px-3 py-2" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">شماره موبایل (نام کاربری ورود)</label>
                        <input name="admin_phone" value="<?= old('admin_phone') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr" required>
                    </div>
                    <div>
                        <label class="block text-sm mb-1">ایمیل (اختیاری)</label>
                        <input name="admin_email" type="email" value="<?= old('admin_email') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">رمز عبور (حداقل ۶ کاراکتر)</label>
                        <input name="admin_pass" type="text" class="w-full border rounded-lg px-3 py-2" dir="ltr" required>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow p-6">
                <h2 class="text-lg font-bold mb-4 text-gray-800">۳) تنظیمات اولیه (اختیاری)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">توکن بات تلگرام</label>
                        <input name="bot_token" value="<?= old('bot_token') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">شماره موبایل مدیر برای پیامک فروش</label>
                        <input name="manager_phone" value="<?= old('manager_phone') ?>" class="w-full border rounded-lg px-3 py-2" dir="ltr">
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-3">این موارد بعداً از منوی «تنظیمات سیستم» هم قابل ویرایش‌اند.</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl text-lg">
                شروع نصب و ساخت جدول‌ها
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
