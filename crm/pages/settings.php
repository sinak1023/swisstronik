<?php
$settings = new Settings($db);
$s = $settings->all();
$active = $s['active_sms_panel'] ?? 'ippanel';
?>

<div class="max-w-4xl mx-auto p-4 md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-bold text-text">تنظیمات سیستم</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">پنل پیامک فعال، اعتبارنامه‌ها، توکن بات تلگرام و اطلاعات مدیر</p>
    </div>

    <form id="settingsForm" class="space-y-6">

        <!-- انتخاب پنل پیامک فعال -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4 flex items-center gap-2">
                <i class='bx bx-message-rounded-dots text-primary'></i> پنل پیامک فعال
            </h2>
            <div class="flex flex-col sm:flex-row gap-4">
                <label class="flex items-center gap-2 cursor-pointer border border-border rounded-lg px-4 py-3 flex-1">
                    <input type="radio" name="active_sms_panel" value="ippanel" <?= $active === 'ippanel' ? 'checked' : '' ?>>
                    <span class="text-text font-medium">آی‌پی پنل (IPPanel)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer border border-border rounded-lg px-4 py-3 flex-1">
                    <input type="radio" name="active_sms_panel" value="melipayamak" <?= $active === 'melipayamak' ? 'checked' : '' ?>>
                    <span class="text-text font-medium">ملی پیامک (Melipayamak)</span>
                </label>
            </div>
        </div>

        <!-- آی‌پی پنل -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4">اطلاعات آی‌پی پنل</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-text mb-2">نام کاربری</label>
                    <input type="text" name="ippanel_username" value="<?= htmlspecialchars($s['ippanel_username'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">رمز عبور</label>
                    <input type="text" name="ippanel_password" value="<?= htmlspecialchars($s['ippanel_password'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">شماره خط</label>
                    <input type="text" name="ippanel_number" value="<?= htmlspecialchars($s['ippanel_number'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
            </div>
        </div>

        <!-- ملی پیامک -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4">اطلاعات ملی پیامک</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-text mb-2">نام کاربری</label>
                    <input type="text" name="melipayamak_username" value="<?= htmlspecialchars($s['melipayamak_username'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">رمز عبور</label>
                    <input type="text" name="melipayamak_password" value="<?= htmlspecialchars($s['melipayamak_password'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">شماره خط</label>
                    <input type="text" name="melipayamak_number" value="<?= htmlspecialchars($s['melipayamak_number'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
            </div>
        </div>

        <!-- بات تلگرام و مدیر -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4 flex items-center gap-2">
                <i class='bx bxl-telegram text-primary'></i> بات تلگرام و اطلاعات مدیر
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm text-text mb-2">توکن بات تلگرام</label>
                    <input type="text" name="bot_token" value="<?= htmlspecialchars($s['bot_token'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">شماره موبایل مدیر (دریافت پیامک فروش)</label>
                    <input type="text" name="manager_phone" value="<?= htmlspecialchars($s['manager_phone'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">آیدی عددی تلگرام مدیر (نوتیف فروش)</label>
                    <input type="text" name="manager_telegram_id" value="<?= htmlspecialchars($s['manager_telegram_id'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2">
                <i class='bx bx-save'></i> ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>

<div id="snackbar" class="fixed bottom-6 left-6 px-6 py-3 rounded-xl bg-green-600 text-white font-medium shadow-lg hidden" style="z-index: 9999;"></div>

<script>
    function showSnackbar(message, type = 'success') {
        const sb = $('#snackbar');
        sb.text(message).removeClass('hidden bg-green-600 bg-red-600')
            .addClass(type === 'success' ? 'bg-green-600' : 'bg-red-600');
        setTimeout(() => sb.addClass('hidden'), 3000);
    }

    $('#settingsForm').on('submit', function(e) {
        e.preventDefault();
        $.post('apis/save_settings.php', $(this).serialize(), function(res) {
            if (res.ok) {
                showSnackbar('تنظیمات با موفقیت ذخیره شد');
            } else {
                showSnackbar(res.error || 'خطا در ذخیره', 'error');
            }
        }, 'json').fail(() => showSnackbar('خطا در ارتباط با سرور', 'error'));
    });
</script>
