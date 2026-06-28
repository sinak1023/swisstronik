<?php

/**
 * ابزار نرمال‌سازی شماره موبایل برای مقایسه‌ی بین کمپین‌ها/پروژه‌ها.
 * هدف: شماره‌ای که در یک کمپین با صفر و در کمپین دیگر بدون صفر (یا با +98) ثبت شده،
 * به یک «کلید یکتا» (۱۰ رقم آخر) نگاشت شود تا تاریخچه و کارشناس قبلی قابل تشخیص باشد.
 */
class Phone
{
    /** تبدیل ارقام فارسی/عربی به انگلیسی */
    public static function toEnglishDigits($str)
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $str = str_replace($fa, $en, (string)$str);
        $str = str_replace($ar, $en, $str);
        return $str;
    }

    /**
     * کلید نرمال‌شده: فقط ارقام، حذف پیش‌شماره 98/0098/+98، و گرفتن ۱۰ رقم آخر.
     * مثال‌ها:
     *   09123456789  -> 9123456789
     *   9123456789   -> 9123456789
     *   +989123456789-> 9123456789
     *   00989123456789 -> 9123456789
     */
    public static function normalize($phone)
    {
        $digits = preg_replace('/\D/', '', self::toEnglishDigits($phone));
        if ($digits === '') return '';

        // حذف پیش‌شماره بین‌المللی
        if (strpos($digits, '0098') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '98') === 0 && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        // حذف صفر ابتدایی
        $digits = ltrim($digits, '0');

        // کلید مقایسه = ۱۰ رقم آخر
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }

    /** شماره استاندارد برای ارسال پیامک داخلی (با صفر ابتدایی) */
    public static function localFormat($phone)
    {
        $norm = self::normalize($phone);
        if ($norm === '') return '';
        return '0' . $norm;
    }

    /** شماره با پیش‌شماره 98 برای لینک واتساپ/تلگرام */
    public static function intlFormat($phone)
    {
        $norm = self::normalize($phone);
        if ($norm === '') return '';
        return '98' . $norm;
    }
}
