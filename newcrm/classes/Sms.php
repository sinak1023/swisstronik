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

    /**
     * ارسال هوشمند برای یک «بخش» سیستم (یادآوری، فروش به کارشناس، فروش به مدیر و ...).
     * اگر حالت ارسال روی «پترن» باشد و برای آن بخش کد پترن تنظیم شده باشد،
     * با پترن ارسال می‌شود؛ در غیر این صورت پیامک متنی عادی ($fallbackText) ارسال می‌گردد.
     *
     * @param string $section   نام بخش (reminder|sale_expert|sale_manager)
     * @param array  $vars      متغیرهای پترن به‌صورت ترتیبی [نام => مقدار]
     *                          (آی‌پی‌پنل از نام‌ها و ملی‌پیامک از ترتیب مقادیر استفاده می‌کند)
     * @param string $fallbackText متن پیامک عادی
     */
    public function notify($phone, $section, $vars, $fallbackText)
    {
        $mode = $this->settings->get('sms_mode', 'normal');
        $patternCode = $this->settings->get('pattern_' . $section, '');

        if ($mode === 'pattern' && !empty($patternCode)) {
            return $this->sendPattern($phone, $patternCode, $vars);
        }
        return $this->send($phone, $fallbackText);
    }

    /**
     * ارسال پیامک با پترن (الگو) — برای آی‌پی‌پنل و ملی‌پیامک.
     */
    public function sendPattern($phone, $patternCode, $vars)
    {
        $phone = Phone::localFormat($phone);
        if ($phone === '') {
            return ['status' => 'failed', 'error' => 'شماره نامعتبر'];
        }

        $panel = $this->settings->get('active_sms_panel', 'ippanel');
        if ($panel === 'melipayamak') {
            return $this->sendPatternMelipayamak($phone, $patternCode, $vars);
        }
        return $this->sendPatternIpPanel($phone, $patternCode, $vars);
    }

    private function sendPatternIpPanel($phone, $patternCode, $vars)
    {
        // آی‌پی‌پنل برای پترن از کلید API (نه نام‌کاربری/رمز) استفاده می‌کند
        $apikey = $this->settings->get('ippanel_apikey', '');
        $sender = $this->settings->get('ippanel_number', '');
        if (empty($apikey) || empty($sender)) {
            return ['status' => 'failed', 'error' => 'برای ارسال با پترن، «کلید API آی‌پی‌پنل» و «شماره خط» را در تنظیمات وارد کنید'];
        }

        $url = "https://api2.ippanel.com/api/v1/sms/pattern/normal/send";
        $payload = [
            'code'      => $patternCode,
            'sender'    => $sender,
            'recipient' => $phone,
            'variable'  => (object)$vars,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: AccessKey ' . $apikey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'failed', 'error' => 'خطا در اتصال: ' . $err];
        }
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($result, true);
        if ($http >= 200 && $http < 300) {
            return ['status' => 'success', 'message_id' => $data['data']['message_id'] ?? ($data['data'] ?? null)];
        }
        $err = is_array($data) ? ($data['error_message'] ?? ($data['message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE))) : ($result ?: 'خطای ناشناخته');
        return ['status' => 'failed', 'error' => $err];
    }

    private function sendPatternMelipayamak($phone, $bodyId, $vars)
    {
        $username = $this->settings->get('melipayamak_username', '');
        $password = $this->settings->get('melipayamak_password', '');
        if (empty($username) || empty($password)) {
            return ['status' => 'failed', 'error' => 'برای ارسال با پترن، نام‌کاربری و رمز ملی‌پیامک را در تنظیمات وارد کنید'];
        }

        // ملی‌پیامک متغیرها را به‌ترتیب و با ; جدا می‌گیرد
        $text = implode(';', array_map('strval', array_values($vars)));

        $url = "https://rest.melipayamak.com/api/SendSMS/BaseServiceNumber";
        $payload = [
            'username' => $username,
            'password' => $password,
            'text'     => $text,
            'to'       => $phone,
            'bodyId'   => (int)$bodyId,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'failed', 'error' => 'خطا در اتصال: ' . $err];
        }
        curl_close($ch);

        $data = json_decode($result, true);
        if (is_array($data) && (($data['RetStatus'] ?? 0) == 1)) {
            return ['status' => 'success', 'message_id' => $data['Value'] ?? null];
        }
        $err = is_array($data) ? ($data['StrRetStatus'] ?? 'خطای ناشناخته') : ($result ?: 'خطای ناشناخته');
        return ['status' => 'failed', 'error' => $err];
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
