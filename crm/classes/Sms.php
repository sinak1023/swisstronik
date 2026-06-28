<?php

/**
 * ارسال پیامک با پشتیبانی از دو پنل: آی‌پی پنل (ippanel) و ملی پیامک (melipayamak).
 * پنل فعال و اعتبارنامه‌ها از جدول settings خوانده می‌شوند و از پنل قابل تغییرند.
 */
class Sms
{
    private $db;
    private $settings;

    public function __construct($db)
    {
        $this->db = $db;
        $this->settings = new Settings($db);
    }

    /**
     * @return array ['status' => 'success'|'failed', 'message_id' => ?, 'error' => ?]
     */
    public function send($phone, $message)
    {
        $phone = Phone::localFormat($phone);
        if ($phone === '') {
            return ['status' => 'failed', 'error' => 'شماره نامعتبر'];
        }

        $panel = $this->settings->get('active_sms_panel', 'ippanel');
        if ($panel === 'melipayamak') {
            return $this->sendViaMelipayamak($phone, $message);
        }
        return $this->sendViaIpPanel($phone, $message);
    }

    private function sendViaIpPanel($phone, $message)
    {
        $username = $this->settings->get('ippanel_username', '');
        $password = $this->settings->get('ippanel_password', '');
        $number   = $this->settings->get('ippanel_number', '');

        if (empty($username) || empty($password) || empty($number)) {
            return ['status' => 'failed', 'error' => 'تنظیمات آی‌پی پنل ناقص است'];
        }

        $url = "https://ippanel.com/services.jspd";
        $params = [
            'uname'   => $username,
            'pass'    => $password,
            'from'    => $number,
            'message' => $message,
            'to'      => json_encode([$phone]),
            'op'      => 'send'
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'failed', 'error' => 'خطا در اتصال: ' . $err];
        }
        curl_close($ch);

        $parts = explode(',', (string)$result);
        if (($parts[0] ?? '') === '0') {
            return ['status' => 'success', 'message_id' => $parts[1] ?? null];
        }
        return ['status' => 'failed', 'error' => $parts[1] ?? ($result ?: 'خطای ناشناخته')];
    }

    private function sendViaMelipayamak($phone, $message)
    {
        $username = $this->settings->get('melipayamak_username', '');
        $password = $this->settings->get('melipayamak_password', '');
        $number   = $this->settings->get('melipayamak_number', '');

        if (empty($username) || empty($password) || empty($number)) {
            return ['status' => 'failed', 'error' => 'تنظیمات ملی پیامک ناقص است'];
        }

        $url = "https://rest.melipayamak.com/api/SendSMS/SendSMS";
        $payload = [
            'username' => $username,
            'password' => $password,
            'to'       => $phone,
            'from'     => $number,
            'text'     => $message,
            'isflash'  => false,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'failed', 'error' => 'خطا در اتصال: ' . $err];
        }
        curl_close($ch);

        $data = json_decode($result, true);
        // RetStatus = 1 یعنی موفق، Value شناسه پیام است
        if (is_array($data) && (($data['RetStatus'] ?? 0) == 1)) {
            return ['status' => 'success', 'message_id' => $data['Value'] ?? null];
        }
        $err = is_array($data) ? ($data['StrRetStatus'] ?? 'خطای ناشناخته') : ($result ?: 'خطای ناشناخته');
        return ['status' => 'failed', 'error' => $err];
    }
}
