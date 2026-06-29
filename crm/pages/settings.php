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

        <!-- حالت ارسال پیامک و پترن‌ها -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4 flex items-center gap-2">
                <i class='bx bx-slider text-primary'></i> حالت ارسال پیامک‌های سیستمی
            </h2>
            <div class="flex flex-col sm:flex-row gap-4 mb-4">
                <label class="flex items-center gap-2 cursor-pointer border border-border rounded-lg px-4 py-3 flex-1">
                    <input type="radio" name="sms_mode" value="normal" <?= ($s['sms_mode'] ?? 'normal') === 'normal' ? 'checked' : '' ?>>
                    <span class="text-text font-medium">ارسال متنی عادی</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer border border-border rounded-lg px-4 py-3 flex-1">
                    <input type="radio" name="sms_mode" value="pattern" <?= ($s['sms_mode'] ?? 'normal') === 'pattern' ? 'checked' : '' ?>>
                    <span class="text-text font-medium">ارسال با پترن (الگو)</span>
                </label>
            </div>

            <div class="bg-muted/30 border border-border rounded-xl p-4 mb-4 text-sm text-text/80 leading-relaxed">
                <p class="font-semibold mb-2">راهنمای متغیرهای پترن:</p>
                <ul class="list-disc pr-5 space-y-1">
                    <li><b>یادآوری:</b> متغیرها به‌ترتیب <code dir="ltr">text</code> (متن یادآوری)، <code dir="ltr">time</code> (ساعت)</li>
                    <li><b>فروش به کارشناس:</b> <code dir="ltr">lead</code> (نام لید)، <code dir="ltr">amount</code> (مبلغ)</li>
                    <li><b>فروش به مدیر:</b> <code dir="ltr">expert</code> (کارشناس)، <code dir="ltr">lead</code> (لید)، <code dir="ltr">amount</code> (مبلغ)</li>
                </ul>
                <p class="mt-2 text-xs text-gray-500">در آی‌پی‌پنل نام متغیرها باید دقیقاً همین‌ها باشد. در ملی‌پیامک متغیرها به‌همین ترتیب و با «;» جایگزین می‌شوند.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-text mb-2">کد پترن یادآوری</label>
                    <input type="text" name="pattern_reminder" value="<?= htmlspecialchars($s['pattern_reminder'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">کد پترن فروش (کارشناس)</label>
                    <input type="text" name="pattern_sale_expert" value="<?= htmlspecialchars($s['pattern_sale_expert'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div>
                    <label class="block text-sm text-text mb-2">کد پترن فروش (مدیر)</label>
                    <input type="text" name="pattern_sale_manager" value="<?= htmlspecialchars($s['pattern_sale_manager'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm text-text mb-2">کلید API آی‌پی‌پنل (فقط برای ارسال با پترن)</label>
                    <input type="text" name="ippanel_apikey" value="<?= htmlspecialchars($s['ippanel_apikey'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
                    <p class="text-xs text-gray-500 mt-1">برای ملی‌پیامک نیازی به این کلید نیست؛ از همان نام‌کاربری/رمز استفاده می‌شود و کد پترن همان bodyId است.</p>
                </div>
            </div>
        </div>

        <!-- زرین‌پال -->
        <div class="bg-surface rounded-2xl shadow-sm border border-border p-6">
            <h2 class="text-lg font-bold text-text mb-4 flex items-center gap-2">
                <i class='bx bx-credit-card text-primary'></i> اتصال به زرین‌پال (بررسی تراکنش‌ها)
            </h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm text-text mb-2">توکن دسترسی زرین‌پال (Access Token)</label>
                    <textarea name="zarinpal_token" rows="3" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr text-xs"><?= htmlspecialchars($s['zarinpal_token'] ?? '') ?></textarea>
                </div>
                <div class="md:w-1/3">
                    <label class="block text-sm text-text mb-2">شناسهٔ ترمینال (Terminal ID)</label>
                    <input type="text" name="zarinpal_terminal_id" value="<?= htmlspecialchars($s['zarinpal_terminal_id'] ?? '') ?>" class="w-full px-4 py-2 border border-border rounded-lg bg-background text-text dir-ltr">
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
