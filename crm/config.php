<?php
$config = [
    'db_host' => 'localhost',
    'db_name' => 'sinalent_crm', //sinalent_crm
    'db_user' => 'sinalent_crm', //sinalent_crm
    'db_pass' => 'PXLLudKM1tWDwg)5', //PXLLudKM1tWDwg)5
    'log_path' => __DIR__ . '/logs/log',
    'app_version' => '1.1',
    'ip_panel'=>[
        'username'=>'',
        'password'=>'',
        'number'=>''
    ],
    'bot_token'=>'8289543181:AAF-lUqme41ab8YC5nG7QeYK1swA-qrghf8',
    'permissions_list' => [
        'pages/users.php' => 'دسترسی به کاربران',
        'apis/add_user.php' => 'افزودن کاربر',
        'apis/delete_user.php' => 'حذف کاربر',
        'apis/edit_user.php' => 'ویرایش کاربر',
        'apis/edit_user_password.php' => 'تغییر رمز عبور کاربر',
        'apis/reset_user_limits.php' => 'بازنشانی محدودیت های کاربر',
        'apis/toggle_user_status.php' => 'تغییر ئضعیت کاربر',
        'pages/roles.php' => 'مدیریت نقش‌ها',
        'apis/add_role.php' => 'افزودن نقش',
        'apis/delete_role.php' => 'حذف نقش',
        'apis/edit_role.php' => 'ویرایش نقش',
        'pages/projects.php' => 'مدیریت پروژه‌های فروش',
        'apis/add_project.php' => 'افزودن پروژه',
        'apis/edit_project.php' => 'ویرایش پروژه',
        'apis/delete_project.php' => 'حذف پروژه',
        'apis/get_project.php' => 'دریافت پروژه',
        'apis/search_projects.php' => 'جستجوی پروژه‌ها',
        'pages/leads.php' => 'مدیریت لیدها',
        'apis/assign_leads.php' => 'تخصیص لیدها',
        'apis/export_leads.php' => 'اکسپورت لیدها',
        'apis/import_leads.php' => 'ایمپورت لیدها',
        "apis/get_assignment_history.php" => 'تاریخچه اختصاص لید',
        'apis/delete_leads.php' => 'حذف لیدها',
        'pages/my_leads.php' => 'مشاهده لیدهای اختصاصی',
        'apis/edit_leads.php' => 'ویرایش لیدهای اختصاصی',
        'apis/delete_reminder.php' => 'حذف یادآوری',
        'apis/add_reminder.php' => 'افزودن یادآوری',
        'apis/edit_reminder.php' => 'ویرایش یادآوری',
        'apis/add_lead_note.php' => 'افزودن یادداشت',
        'apis/edit_lead_note.php' => 'ویرایش یادداشت لید',
        'apis/delete_lead_note.php' => 'حذف یادداشت لید',
        'pages/message_templates.php' => 'مدیریت قالب‌های پیام',
        'apis/add_template.php' => 'افزودن قالب پیام',
        'apis/edit_template.php' => 'ویرایش قالب پیام',
        'apis/delete_template.php' => 'حذف قالب پیام',
        'apis/get_templates.php' => 'دریافت قالب‌های پیام',
        'apis/send_sms.php'=>'ارسال پیامک',
        'apis/check_transactions.php'=>'مشاهده تراکنش های پرداختی'
    ]
];

spl_autoload_register(function ($namespace) {
    $namespace = str_replace("\\", '/', $namespace);
    require_once 'classes/' . $namespace . '.php';
});

ini_set('log_errors', 1);
ini_set('error_log', $config['log_path']);
date_default_timezone_set('Asia/Tehran');

$db = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$version_app = $config['app_version'];
$Router = new Router;
if (isset($Router->query)) {
    $query = $Router->query;
}

$ex = $Router->explode_dir($Router->dir);

