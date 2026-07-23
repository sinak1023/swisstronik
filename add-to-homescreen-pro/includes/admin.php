<?php
/**
 * Add to Home Screen Pro — admin settings page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'a2hsp_admin_menu' );
function a2hsp_admin_menu() {
	add_options_page(
		'Add to Home Screen Pro',
		'A2HS Pro',
		'manage_options',
		'a2hsp-settings',
		'a2hsp_settings_page'
	);
}

add_action( 'admin_init', 'a2hsp_register_settings' );
function a2hsp_register_settings() {
	register_setting( 'a2hsp_group', 'a2hsp_settings', 'a2hsp_sanitize' );
}

add_action( 'admin_enqueue_scripts', 'a2hsp_admin_assets' );
function a2hsp_admin_assets( $hook ) {
	if ( 'settings_page_a2hsp-settings' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style( 'a2hsp-admin', A2HSP_URL . 'assets/admin.css', array(), A2HSP_VERSION );
	wp_enqueue_script( 'a2hsp-admin', A2HSP_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), A2HSP_VERSION, true );
}

function a2hsp_sanitize( $input ) {
	$d   = a2hsp_default_settings();
	$out = array();

	$out['enabled']         = empty( $input['enabled'] ) ? 0 : 1;
	$out['app_name']        = sanitize_text_field( $input['app_name'] ?? $d['app_name'] );
	$out['short_name']      = sanitize_text_field( $input['short_name'] ?? $d['short_name'] );
	$out['description']     = sanitize_textarea_field( $input['description'] ?? '' );
	$out['icon_url']        = esc_url_raw( $input['icon_url'] ?? '' );

	$shots = array();
	if ( ! empty( $input['screenshots'] ) && is_array( $input['screenshots'] ) ) {
		foreach ( $input['screenshots'] as $u ) {
			$u = esc_url_raw( $u );
			if ( $u ) {
				$shots[] = $u;
			}
		}
	}
	$out['screenshots']  = array_slice( $shots, 0, 8 );
	$out['splash_image'] = esc_url_raw( $input['splash_image'] ?? '' );

	foreach ( array( 'show_subtitle', 'show_host', 'show_description', 'show_features', 'show_screenshots', 'show_later' ) as $flag ) {
		$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
	}

	$out['theme_color']      = sanitize_hex_color( $input['theme_color'] ?? '' ) ?: $d['theme_color'];
	$out['background_color'] = sanitize_hex_color( $input['background_color'] ?? '' ) ?: $d['background_color'];
	$out['accent_color']     = sanitize_hex_color( $input['accent_color'] ?? '' ) ?: $d['accent_color'];

	$out['display_style'] = in_array( $input['display_style'] ?? '', array( 'sheet', 'fullscreen' ), true ) ? $input['display_style'] : 'sheet';
	$out['dark_mode']     = in_array( $input['dark_mode'] ?? '', array( 'auto', 'light', 'dark' ), true ) ? $input['dark_mode'] : 'auto';
	$out['direction']     = in_array( $input['direction'] ?? '', array( 'rtl', 'ltr' ), true ) ? $input['direction'] : 'rtl';

	$out['auto_show']       = empty( $input['auto_show'] ) ? 0 : 1;
	$out['delay_seconds']   = max( 0, absint( $input['delay_seconds'] ?? 3 ) );
	$out['dismiss_days']    = max( 0, absint( $input['dismiss_days'] ?? 7 ) );
	$out['max_display']     = max( 0, absint( $input['max_display'] ?? 3 ) );
	$out['min_visits']      = max( 0, absint( $input['min_visits'] ?? 0 ) );
	$out['show_on_desktop'] = empty( $input['show_on_desktop'] ) ? 0 : 1;

	foreach ( array(
		'txt_title', 'txt_subtitle', 'txt_install', 'txt_later',
		'txt_ios_step1', 'txt_ios_step2', 'txt_ios_step3',
		'txt_inapp', 'txt_inapp_hint', 'txt_copy_link', 'txt_copied',
		'txt_success', 'txt_guide_title',
	) as $key ) {
		$out[ $key ] = sanitize_text_field( $input[ $key ] ?? $d[ $key ] );
	}
	$out['txt_features'] = sanitize_textarea_field( $input['txt_features'] ?? $d['txt_features'] );

	return $out;
}

function a2hsp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = a2hsp_get_settings();

	$text_field = function ( $key, $label, $hint = '' ) use ( $s ) {
		echo '<tr><th scope="row"><label for="a2hsp_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" id="a2hsp_' . esc_attr( $key ) . '" name="a2hsp_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[ $key ] ) . '" class="regular-text">';
		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<div class="wrap a2hsp-wrap">
		<h1>Add to Home Screen Pro <span class="a2hsp-ver">v<?php echo esc_html( A2HSP_VERSION ); ?></span></h1>

		<h2 class="nav-tab-wrapper a2hsp-tabs">
			<a href="#a2hsp-tab-general" class="nav-tab nav-tab-active">عمومی</a>
			<a href="#a2hsp-tab-design" class="nav-tab">ظاهر</a>
			<a href="#a2hsp-tab-media" class="nav-tab">لوگو و تصاویر</a>
			<a href="#a2hsp-tab-elements" class="nav-tab">المان‌های پاپ‌آپ</a>
			<a href="#a2hsp-tab-behavior" class="nav-tab">رفتار نمایش</a>
			<a href="#a2hsp-tab-texts" class="nav-tab">متن‌ها</a>
		</h2>

		<form method="post" action="options.php">
			<?php settings_fields( 'a2hsp_group' ); ?>

			<div id="a2hsp-tab-general" class="a2hsp-tab active">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">فعال‌سازی</th>
						<td><label><input type="checkbox" name="a2hsp_settings[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>> پلاگین فعال باشد</label></td>
					</tr>
					<?php
					$text_field( 'app_name', 'نام اپلیکیشن' );
					$text_field( 'short_name', 'نام کوتاه', 'زیر آیکون در صفحه اصلی گوشی نمایش داده می‌شود (حداکثر ۱۲ حرف توصیه می‌شود).' );
					?>
					<tr>
						<th scope="row"><label for="a2hsp_description">توضیحات اپ</label></th>
						<td><textarea id="a2hsp_description" name="a2hsp_settings[description]" rows="3" class="large-text"><?php echo esc_textarea( $s['description'] ); ?></textarea>
						<p class="description">در پاپ‌آپ نصب زیر نام اپ نمایش داده می‌شود.</p></td>
					</tr>
				</table>
			</div>

			<div id="a2hsp-tab-design" class="a2hsp-tab">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">حالت نمایش پاپ‌آپ</th>
						<td>
							<label><input type="radio" name="a2hsp_settings[display_style]" value="sheet" <?php checked( $s['display_style'], 'sheet' ); ?>> Bottom Sheet (از پایین باز می‌شود + بک‌دراپ تمام‌صفحه)</label><br>
							<label><input type="radio" name="a2hsp_settings[display_style]" value="fullscreen" <?php checked( $s['display_style'], 'fullscreen' ); ?>> تمام‌صفحه (کل صفحه را می‌پوشاند)</label>
						</td>
					</tr>
					<tr>
						<th scope="row">حالت تیره</th>
						<td>
							<select name="a2hsp_settings[dark_mode]">
								<option value="auto" <?php selected( $s['dark_mode'], 'auto' ); ?>>خودکار (بر اساس سیستم کاربر)</option>
								<option value="light" <?php selected( $s['dark_mode'], 'light' ); ?>>همیشه روشن</option>
								<option value="dark" <?php selected( $s['dark_mode'], 'dark' ); ?>>همیشه تیره</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">جهت متن</th>
						<td>
							<select name="a2hsp_settings[direction]">
								<option value="rtl" <?php selected( $s['direction'], 'rtl' ); ?>>راست به چپ (فارسی)</option>
								<option value="ltr" <?php selected( $s['direction'], 'ltr' ); ?>>چپ به راست</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>رنگ اصلی (دکمه نصب)</label></th>
						<td><input type="text" class="a2hsp-color" name="a2hsp_settings[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label>رنگ تم مرورگر (theme color)</label></th>
						<td><input type="text" class="a2hsp-color" name="a2hsp_settings[theme_color]" value="<?php echo esc_attr( $s['theme_color'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label>رنگ پس‌زمینه اسپلش</label></th>
						<td><input type="text" class="a2hsp-color" name="a2hsp_settings[background_color]" value="<?php echo esc_attr( $s['background_color'] ); ?>"></td>
					</tr>
				</table>
			</div>

			<div id="a2hsp-tab-media" class="a2hsp-tab">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">آیکون اپ</th>
						<td>
							<div class="a2hsp-icon-preview">
								<?php if ( $s['icon_url'] ) : ?>
									<img src="<?php echo esc_url( $s['icon_url'] ); ?>" alt="">
								<?php endif; ?>
							</div>
							<input type="hidden" id="a2hsp_icon_url" name="a2hsp_settings[icon_url]" value="<?php echo esc_attr( $s['icon_url'] ); ?>">
							<button type="button" class="button" id="a2hsp-pick-icon">انتخاب از کتابخانه رسانه</button>
							<button type="button" class="button" id="a2hsp-remove-icon">حذف</button>
							<p class="description">تصویر مربع PNG با ابعاد حداقل ۵۱۲×۵۱۲ پیکسل. اگر خالی باشد از آیکون سایت استفاده می‌شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">اسپلش اسکرین (iOS)</th>
						<td>
							<div class="a2hsp-splash-preview a2hsp-icon-preview">
								<?php if ( $s['splash_image'] ) : ?>
									<img src="<?php echo esc_url( $s['splash_image'] ); ?>" alt="" style="width:auto;height:160px;border-radius:12px;">
								<?php endif; ?>
							</div>
							<input type="hidden" id="a2hsp_splash_url" name="a2hsp_settings[splash_image]" value="<?php echo esc_attr( $s['splash_image'] ); ?>">
							<button type="button" class="button" id="a2hsp-pick-splash">انتخاب از کتابخانه رسانه</button>
							<button type="button" class="button" id="a2hsp-remove-splash">حذف</button>
							<p class="description">تصویر عمودی (مثلاً ۱۲۹۰×۲۷۹۶) که هنگام باز شدن وب‌اپ در iOS نمایش داده می‌شود. در اندروید اسپلش به‌صورت خودکار از «رنگ پس‌زمینه اسپلش» + آیکون اپ + نام اپ ساخته می‌شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">اسکرین‌شات‌ها (گالری پاپ‌آپ)</th>
						<td>
							<div id="a2hsp-shots" class="a2hsp-shots-admin">
								<?php foreach ( (array) $s['screenshots'] as $shot ) : ?>
									<div class="a2hsp-shot-item">
										<img src="<?php echo esc_url( $shot ); ?>" alt="">
										<input type="hidden" name="a2hsp_settings[screenshots][]" value="<?php echo esc_attr( $shot ); ?>">
										<button type="button" class="a2hsp-shot-remove">&times;</button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button" id="a2hsp-add-shots">افزودن اسکرین‌شات</button>
							<p class="description">تصاویر عمودی موبایل (نسبت ۹:۱۶ مثل ۱۰۸۰×۱۹۲۰). حداکثر ۸ تصویر. به‌صورت گالری قابل اسکرول در پاپ‌آپ نمایش داده می‌شوند.</p>
						</td>
					</tr>
				</table>
			</div>

			<div id="a2hsp-tab-elements" class="a2hsp-tab">
				<p style="margin-top:16px">هر المان پاپ‌آپ را می‌توانید جداگانه روشن یا خاموش کنید. متن هر بخش هم از تب «متن‌ها» قابل ویرایش است.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">برچسب زیرعنوان</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_subtitle]" value="1" <?php checked( $s['show_subtitle'], 1 ); ?>> نمایش برچسب «رایگان • بدون نیاز به اپ‌استور» کنار نام اپ</label></td>
					</tr>
					<tr>
						<th scope="row">آدرس دامنه</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_host]" value="1" <?php checked( $s['show_host'], 1 ); ?>> نمایش دامنه سایت زیر نام اپ</label></td>
					</tr>
					<tr>
						<th scope="row">توضیحات اپ</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_description]" value="1" <?php checked( $s['show_description'], 1 ); ?>> نمایش متن توضیحات زیر هدر</label></td>
					</tr>
					<tr>
						<th scope="row">کارت‌های ویژگی</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_features]" value="1" <?php checked( $s['show_features'], 1 ); ?>> نمایش سه کارت ویژگی (آفلاین، سرعت، تمام‌صفحه)</label></td>
					</tr>
					<tr>
						<th scope="row">گالری اسکرین‌شات</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_screenshots]" value="1" <?php checked( $s['show_screenshots'], 1 ); ?>> نمایش گالری اسکرین‌شات‌ها</label></td>
					</tr>
					<tr>
						<th scope="row">دکمه «بعداً»</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_later]" value="1" <?php checked( $s['show_later'], 1 ); ?>> نمایش دکمه «بعداً» زیر دکمه نصب</label></td>
					</tr>
				</table>
			</div>

			<div id="a2hsp-tab-behavior" class="a2hsp-tab">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">نمایش خودکار</th>
						<td><label><input type="checkbox" name="a2hsp_settings[auto_show]" value="1" <?php checked( $s['auto_show'], 1 ); ?>> پاپ‌آپ به‌صورت خودکار نمایش داده شود</label>
						<p class="description">با شورت‌کد <code>[a2hs_button]</code> یا اتریبیوت <code>data-a2hsp-open</code> روی هر دکمه‌ای می‌توانید پاپ‌آپ را دستی هم باز کنید.</p></td>
					</tr>
					<tr>
						<th scope="row"><label>تأخیر نمایش (ثانیه)</label></th>
						<td><input type="number" min="0" name="a2hsp_settings[delay_seconds]" value="<?php echo esc_attr( $s['delay_seconds'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label>فاصله بعد از بستن (روز)</label></th>
						<td><input type="number" min="0" name="a2hsp_settings[dismiss_days]" value="<?php echo esc_attr( $s['dismiss_days'] ); ?>" class="small-text">
						<p class="description">اگر کاربر پاپ‌آپ را ببندد، تا این تعداد روز دوباره نمایش داده نمی‌شود.</p></td>
					</tr>
					<tr>
						<th scope="row"><label>حداکثر دفعات نمایش خودکار</label></th>
						<td><input type="number" min="0" name="a2hsp_settings[max_display]" value="<?php echo esc_attr( $s['max_display'] ); ?>" class="small-text">
						<p class="description">۰ یعنی نامحدود.</p></td>
					</tr>
					<tr>
						<th scope="row"><label>حداقل تعداد بازدید قبل از نمایش</label></th>
						<td><input type="number" min="0" name="a2hsp_settings[min_visits]" value="<?php echo esc_attr( $s['min_visits'] ); ?>" class="small-text">
						<p class="description">مثلاً ۲ یعنی از سومین بازدید کاربر نمایش داده شود. ۰ یعنی از اولین بازدید.</p></td>
					</tr>
					<tr>
						<th scope="row">نمایش در دسکتاپ</th>
						<td><label><input type="checkbox" name="a2hsp_settings[show_on_desktop]" value="1" <?php checked( $s['show_on_desktop'], 1 ); ?>> در کامپیوتر هم نمایش داده شود</label></td>
					</tr>
				</table>
			</div>

			<div id="a2hsp-tab-texts" class="a2hsp-tab">
				<table class="form-table" role="presentation">
					<?php
					$text_field( 'txt_title', 'عنوان پاپ‌آپ' );
					$text_field( 'txt_subtitle', 'زیرعنوان (برچسب کنار نام اپ)' );
					$text_field( 'txt_install', 'متن دکمه نصب' );
					$text_field( 'txt_later', 'متن دکمه «بعداً»' );
					?>
					<tr>
						<th scope="row"><label for="a2hsp_txt_features">ویژگی‌ها (هر خط یک مورد، حداکثر ۳)</label></th>
						<td><textarea id="a2hsp_txt_features" name="a2hsp_settings[txt_features]" rows="3" class="large-text"><?php echo esc_textarea( $s['txt_features'] ); ?></textarea></td>
					</tr>
					<?php
					$text_field( 'txt_guide_title', 'عنوان راهنمای نصب' );
					$text_field( 'txt_ios_step1', 'iOS — مرحله ۱' );
					$text_field( 'txt_ios_step2', 'iOS — مرحله ۲' );
					$text_field( 'txt_ios_step3', 'iOS — مرحله ۳' );
					$text_field( 'txt_inapp', 'پیام مرورگر داخلی (اینستاگرام و…)' );
					$text_field( 'txt_inapp_hint', 'راهنمای مرورگر داخلی' );
					$text_field( 'txt_copy_link', 'متن دکمه کپی لینک' );
					$text_field( 'txt_copied', 'پیام بعد از کپی' );
					$text_field( 'txt_success', 'پیام موفقیت نصب' );
					?>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>

		<div class="a2hsp-note">
			<strong>نکات مهم:</strong>
			<ul>
				<li>برای نصب‌شدن وب‌اپ، سایت حتماً باید روی <strong>HTTPS</strong> باشد.</li>
				<li>در اندروید/کروم از دیالوگ نصب بومی استفاده می‌شود؛ در iOS و مرورگرهای بدون پشتیبانی، راهنمای تصویری مرحله‌به‌مرحله نمایش داده می‌شود.</li>
				<li>مرورگرهای داخلی اینستاگرام/تلگرام امکان نصب ندارند — پلاگین به کاربر می‌گوید صفحه را در مرورگر اصلی باز کند و دکمه کپی لینک می‌دهد.</li>
			</ul>
		</div>
	</div>
	<?php
}
