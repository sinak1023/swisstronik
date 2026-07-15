<?php

/**
 * ارسال پیام به تلگرام از طریق بات. توکن بات از جدول settings خوانده می‌شود
 * (و در صورت نبودن، از config.php به‌عنوان fallback).
 */
class Telegram
{
    private $db;
    private $token;

    public function __construct($db)
    {
        $this->db = $db;
        $settings = new Settings($db);
        global $config;
        $this->token = $settings->get('bot_token', $config['bot_token'] ?? '');
    }

    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return bool
     */
    public function sendMessage($chatId, $text, $reply_markup = null)
    {
        if (empty($this->token) || empty($chatId)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $data = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $ok = !curl_errno($ch);
        curl_close($ch);

        if (!$ok) return false;
        $decoded = json_decode($res, true);
        return is_array($decoded) && !empty($decoded['ok']);
    }

    /**
     * استخراج chat_id کاربر از روی telegram_chat_id دستی یا telegram_info (اتصال خودکار).
     */
    public static function resolveChatId($user)
    {
        if (!empty($user['telegram_chat_id'])) {
            return $user['telegram_chat_id'];
        }
        if (!empty($user['telegram_info'])) {
            $info = json_decode($user['telegram_info'], true);
            if (!empty($info['id'])) return $info['id'];
        }
        return null;
    }
}
