<?php
/**
 * راه‌اندازی نشست (Session) مستقل از هاست.
 * این فایل باید «قبل از» هر session_start() اجرا شود.
 *
 * چرا؟ روی بعضی هاست‌ها مسیر پیش‌فرض ذخیرهٔ سشن قابل‌نوشتن نیست یا مشترک است،
 * بنابراین سشن بعد از لاگین گم می‌شود. اینجا سشن را در پوشهٔ داخلی پروژه ذخیره می‌کنیم
 * تا روی همهٔ هاست‌ها پایدار بماند.
 */
if (session_status() === PHP_SESSION_NONE) {
    $__sessDir = __DIR__ . '/sessions';
    if (!is_dir($__sessDir)) {
        @mkdir($__sessDir, 0700, true);
    }
    if (is_dir($__sessDir) && is_writable($__sessDir)) {
        session_save_path($__sessDir);
    }

    // عمر طولانی نشست (۲۴ ساعت) تا کاربر زود خارج نشود
    @ini_set('session.gc_maxlifetime', 86400);
    @ini_set('session.cookie_lifetime', 86400);
    @ini_set('session.use_strict_mode', 1);
}
