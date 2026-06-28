<?php
// bot.php - Telegram Bot
require_once 'config.php';

// توکن از تنظیمات قابل‌ویرایش پنل خوانده می‌شود (و در صورت نبود، از config.php)
$botToken = (new Settings($db))->get('bot_token', $config['bot_token'] ?? '');
$apiURL = "https://api.telegram.org/bot{$botToken}";


function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => $reply_markup
    ];
    file_get_contents($apiURL . "/sendMessage?" . http_build_query($data));
}

$update = json_decode(file_get_contents("php://input"), true);
$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');

if (!$chat_id) die();

$userStateFile = "states/{$chat_id}.json";
$state = file_exists($userStateFile) ? json_decode(file_get_contents($userStateFile), true) : ['step' => 'start'];

if ($text === '/start') {
    $state = ['step' => 'ask_username'];
    file_put_contents($userStateFile, json_encode($state));
    sendMessage($chat_id, "سلام! خوش آمدید به ربات اتصال به پنل مدیریت\n\nلطفاً نام کاربری (ایمیل یا شماره موبایل) خود را وارد کنید:");
    die();
}

if ($state['step'] === 'ask_username') {
    $state['username'] = $text;
    $state['step'] = 'ask_password';
    file_put_contents($userStateFile, json_encode($state));
    sendMessage($chat_id, "نام کاربری ذخیره شد.\n\nحالا رمز عبور خود را وارد کنید:");
    die();
}

if ($state['step'] === 'ask_password') {
    $username = $state['username'];
    $password = $text;

    $users = new Users($db);
    $user = $users->get_user($username);

    if ($user && password_verify($password, $user['password'])) {
        // اطلاعات تلگرام
        $telegram_info = json_encode([
            'id' => $message['from']['id'],
            'username' => $message['from']['username'] ?? '',
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'connected_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

        // به‌روزرسانی کاربر
        $users->update($user['id'], ['telegram_info' => $telegram_info]);

        sendMessage($chat_id, "اتصال با موفقیت انجام شد! \n\nحالا در پنل مدیریت می‌توانید اعلان‌ها را از تلگرام دریافت کنید.");
        @unlink($userStateFile); // پاک کردن وضعیت
    } else {
        sendMessage($chat_id, "نام کاربری یا رمز عبور اشتباه است. دوباره تلاش کنید.\n\n/start برای شروع مجدد");
    }
}