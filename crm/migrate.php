<?php
/**
 * اسکریپت مهاجرت دیتابیس (Idempotent)
 * این فایل را یک‌بار از طریق مرورگر یا CLI اجرا کنید:
 *   php migrate.php
 * یا  https://yourdomain/crm/migrate.php?key=MIGRATE_SECRET
 *
 * تمام تغییرات این فایل امن و قابل تکرار هستند (در صورت وجود ستون/جدول، رد می‌شوند).
 */

require_once __DIR__ . '/config.php';

// محافظت ساده هنگام اجرا از طریق مرورگر
if (php_sapi_name() !== 'cli') {
    $provided = $_GET['key'] ?? '';
    if ($provided !== 'crm_migrate_2024') {
        http_response_code(403);
        die('Forbidden. Run via CLI or provide ?key=crm_migrate_2024');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = $db->getConnection();
$dbName = $config['db_name'];
$log = [];

function out($msg)
{
    global $log;
    $log[] = $msg;
    echo $msg . "\n";
}

function column_exists($pdo, $dbName, $table, $column)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$dbName, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function table_exists($pdo, $dbName, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $stmt->execute([$dbName, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function index_exists($pdo, $dbName, $table, $index)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$dbName, $table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function add_column($pdo, $dbName, $table, $column, $definition)
{
    if (!table_exists($pdo, $dbName, $table)) {
        out("  skip (table '$table' not found) -> $column");
        return;
    }
    if (column_exists($pdo, $dbName, $table, $column)) {
        out("  exists: $table.$column");
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    out("  added: $table.$column");
}

function add_index($pdo, $dbName, $table, $index, $columns)
{
    if (!table_exists($pdo, $dbName, $table)) return;
    if (index_exists($pdo, $dbName, $table, $index)) {
        out("  index exists: $table.$index");
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index` ($columns)");
    out("  index added: $table.$index");
}

try {
    // ===== 1) جدول تنظیمات سراسری =====
    out("\n[1] settings table");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `skey` VARCHAR(100) NOT NULL UNIQUE,
        `svalue` TEXT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("  ok");

    // مقادیر پیش‌فرض تنظیمات (فقط اگر وجود نداشته باشند)
    $defaults = [
        'active_sms_panel'      => 'ippanel',           // ippanel | melipayamak
        'ippanel_username'      => $config['ip_panel']['username'] ?? '',
        'ippanel_password'      => $config['ip_panel']['password'] ?? '',
        'ippanel_number'        => $config['ip_panel']['number'] ?? '',
        'melipayamak_username'  => '',
        'melipayamak_password'  => '',
        'melipayamak_number'    => '',
        'bot_token'             => $config['bot_token'] ?? '',
        'manager_phone'         => '',                   // شماره مدیر برای دریافت پیامک فروش
        'manager_telegram_id'   => '',                   // آی‌دی عددی تلگرام مدیر
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
    out("  seeded defaults");

    // ===== 2) ستون‌های لید (روش‌های تماس + وضعیت تماس) =====
    out("\n[2] leads columns");
    add_column($pdo, $dbName, 'leads', 'whatsapp_phone', "`whatsapp_phone` VARCHAR(20) NULL AFTER `phone`");
    add_column($pdo, $dbName, 'leads', 'telegram_phone', "`telegram_phone` VARCHAR(20) NULL AFTER `whatsapp_phone`");
    add_column($pdo, $dbName, 'leads', 'telegram_id', "`telegram_id` VARCHAR(60) NULL AFTER `telegram_phone`");
    add_column($pdo, $dbName, 'leads', 'bale_phone', "`bale_phone` VARCHAR(20) NULL AFTER `telegram_id`");
    add_column($pdo, $dbName, 'leads', 'called', "`called` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    add_column($pdo, $dbName, 'leads', 'called_at', "`called_at` DATETIME NULL AFTER `called`");

    // ===== 3) تسک‌های روزانه =====
    out("\n[3] user_tasks columns");
    add_column($pdo, $dbName, 'user_tasks', 'task_date', "`task_date` DATE NULL");
    add_column($pdo, $dbName, 'user_tasks', 'created_at', "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");
    add_column($pdo, $dbName, 'user_tasks', 'updated_at', "`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    // مقداردهی task_date برای رکوردهای قدیمی
    if (table_exists($pdo, $dbName, 'user_tasks') && column_exists($pdo, $dbName, 'user_tasks', 'task_date')) {
        $pdo->exec("UPDATE `user_tasks` SET `task_date` = CURDATE() WHERE `task_date` IS NULL");
        out("  backfilled task_date");
    }
    add_index($pdo, $dbName, 'user_tasks', 'idx_user_date', '`user_id`, `task_date`');

    // ===== 4) کاربران: آی‌دی عددی تلگرام دستی =====
    out("\n[4] users columns");
    add_column($pdo, $dbName, 'users', 'telegram_chat_id', "`telegram_chat_id` VARCHAR(60) NULL");

    // ===== 5) یادآوری‌ها: اعلان ۵ دقیقه قبل =====
    out("\n[5] reminders columns");
    add_column($pdo, $dbName, 'reminders', 'pre_notified', "`pre_notified` TINYINT(1) NOT NULL DEFAULT 0");
    add_column($pdo, $dbName, 'reminders', 'is_done', "`is_done` TINYINT(1) NOT NULL DEFAULT 0");
    add_column($pdo, $dbName, 'reminders', 'sms_status', "`sms_status` VARCHAR(20) NULL");
    add_column($pdo, $dbName, 'reminders', 'sms_message_id', "`sms_message_id` VARCHAR(60) NULL");
    add_column($pdo, $dbName, 'reminders', 'sent_at', "`sent_at` DATETIME NULL");
    add_column($pdo, $dbName, 'reminders', 'created_at', "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");

    // ===== 6) یادداشت‌ها: created_at =====
    out("\n[6] lead_notes columns");
    add_column($pdo, $dbName, 'lead_notes', 'created_at', "`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");
    add_column($pdo, $dbName, 'lead_notes', 'updated_at', "`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // ===== 7) شماره نرمال‌شده برای مقایسه بین کمپین‌ها =====
    out("\n[7] phone normalization");
    add_column($pdo, $dbName, 'leads', 'phone_norm', "`phone_norm` VARCHAR(20) NULL AFTER `phone`");
    add_column($pdo, $dbName, 'lead_numbers', 'phone_norm', "`phone_norm` VARCHAR(20) NULL");
    add_index($pdo, $dbName, 'leads', 'idx_phone_norm', '`phone_norm`');
    add_index($pdo, $dbName, 'lead_numbers', 'idx_phone_norm', '`phone_norm`');

    // ===== 8) جدول فروش =====
    out("\n[8] sales table");
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
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_txn` (`transaction_ref`),
        INDEX `idx_user` (`user_id`),
        INDEX `idx_phone_norm` (`phone_norm`),
        INDEX `idx_txn_date` (`transaction_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("  ok");

    // ===== 9) لاگ تماس‌ها (تماس گرفتم) =====
    out("\n[9] call_logs table");
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("  ok");

    // ===== 10) لاگ پیام‌های ارسالی =====
    out("\n[10] message_logs table");
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("  ok");

    // ===== 11) لاگ فعالیت کاربران (برای ادمین) =====
    out("\n[11] activity_logs table");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `action` VARCHAR(60) NOT NULL,
        `entity` VARCHAR(60) NULL,
        `entity_id` INT NULL,
        `meta` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_date` (`user_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    out("  ok");

    // ===== 12) backfill phone_norm (در PHP برای سازگاری با MySQL 5.7) =====
    out("\n[12] backfill phone_norm");
    require_once __DIR__ . '/classes/Phone.php';
    foreach (['leads', 'lead_numbers'] as $tbl) {
        if (!table_exists($pdo, $dbName, $tbl) || !column_exists($pdo, $dbName, $tbl, 'phone_norm')) continue;
        $rows = $pdo->query("SELECT id, phone FROM `$tbl` WHERE `phone_norm` IS NULL OR `phone_norm` = ''")->fetchAll(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare("UPDATE `$tbl` SET `phone_norm` = ? WHERE id = ?");
        foreach ($rows as $r) {
            $upd->execute([Phone::normalize($r['phone']), $r['id']]);
        }
        out("  $tbl backfilled (" . count($rows) . " rows)");
    }

    out("\n✅ Migration finished successfully.");
} catch (Exception $e) {
    out("\n❌ Migration error: " . $e->getMessage());
    http_response_code(500);
}
