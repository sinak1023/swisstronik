<?php
/**
 * Plugin Name: Add to Home Screen Pro (PWA)
 * Plugin URI:  https://example.com/add-to-homescreen-pro
 * Description: پاپ‌آپ تمام‌صفحه نصب وب‌اپ به سبک اپ‌استور — با گالری اسکرین‌شات، راهنمای اختصاصی هر مرورگر (Safari ،Chrome iOS، سامسونگ، فایرفاکس، مرورگر داخلی اینستاگرام/تلگرام)، حالت تیره، و پنل تنظیمات کامل.
 * Version:     2.2.0
 * Author:      You
 * License:     GPL-2.0+
 * Text Domain: a2hsp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'A2HSP_VERSION', '2.2.0' );
define( 'A2HSP_URL', plugin_dir_url( __FILE__ ) );
define( 'A2HSP_PATH', plugin_dir_path( __FILE__ ) );

require_once A2HSP_PATH . 'includes/admin.php';

/**
 * Default settings
 */
function a2hsp_default_settings() {
	return array(
		// App identity
		'enabled'          => 1,
		'app_name'         => get_bloginfo( 'name' ),
		'short_name'       => get_bloginfo( 'name' ),
		'description'      => get_bloginfo( 'description' ),
		'icon_url'         => '',
		'screenshots'      => array(), // array of URLs
		'splash_image'     => '',      // iOS splash screen image (portrait)
		// Design
		'theme_color'      => '#123F76',
		'background_color' => '#123F76',
		'accent_color'     => '#123F76',
		'display_style'    => 'sheet',      // sheet | fullscreen
		'dark_mode'        => 'auto',       // auto | light | dark
		'direction'        => 'rtl',        // rtl | ltr
		'ios_statusbar'    => 1,            // paint iOS standalone status bar with theme color
		// Element visibility toggles
		'show_subtitle'    => 1,
		'show_host'        => 1,
		'show_description' => 1,
		'show_features'    => 1,
		'show_screenshots' => 1,
		'show_later'       => 1,
		// Behavior
		'auto_show'        => 1,
		'delay_seconds'    => 3,
		'dismiss_days'     => 7,
		'max_display'      => 3,            // max auto-shows per user (0 = unlimited)
		'min_visits'       => 0,            // visits before first auto-show
		'show_on_desktop'  => 0,
		// Texts (Persian defaults, all editable)
		'txt_title'        => 'نصب اپلیکیشن',
		'txt_subtitle'     => 'رایگان • بدون نیاز به اپ‌استور',
		'txt_install'      => 'نصب اپلیکیشن',
		'txt_later'        => 'بعداً',
		'txt_features'     => "دسترسی سریع از صفحه اصلی\nاجرای تمام‌صفحه بدون نوار مرورگر\nکارکرد آفلاین و سرعت بالا",
		'txt_ios_step1'    => 'در نوار پایین صفحه، دکمه «اشتراک‌گذاری» را بزنید',
		'txt_ios_step2'    => 'گزینه «Add to Home Screen» را انتخاب کنید',
		'txt_ios_step3'    => 'در بالای صفحه روی «Add» بزنید',
		'txt_inapp'        => 'برای نصب، این صفحه را در مرورگر اصلی گوشی باز کنید',
		'txt_inapp_hint'   => 'از منوی «···» بالای صفحه، «باز کردن در مرورگر» را انتخاب کنید',
		'txt_copy_link'    => 'کپی لینک',
		'txt_copied'       => 'کپی شد!',
		'txt_success'      => 'اپلیکیشن با موفقیت نصب شد 🎉',
		'txt_guide_title'  => 'راهنمای نصب',
	);
}

function a2hsp_get_settings() {
	$saved = get_option( 'a2hsp_settings', array() );
	return wp_parse_args( $saved, a2hsp_default_settings() );
}

/**
 * ---------------------------------------------------------------
 *  Web App Manifest  (served at /?a2hsp_manifest=1)
 * ---------------------------------------------------------------
 */
add_action( 'template_redirect', 'a2hsp_maybe_output_manifest', 0 );
function a2hsp_maybe_output_manifest() {
	if ( ! isset( $_GET['a2hsp_manifest'] ) ) {
		return;
	}

	$s    = a2hsp_get_settings();
	$icon = $s['icon_url'] ? $s['icon_url'] : get_site_icon_url( 512 );

	$icons = array();
	if ( $icon ) {
		foreach ( array( '192x192', '512x512' ) as $size ) {
			$icons[] = array(
				'src'     => esc_url_raw( $icon ),
				'sizes'   => $size,
				'type'    => 'image/png',
				'purpose' => 'any',
			);
		}
		$icons[] = array(
			'src'     => esc_url_raw( $icon ),
			'sizes'   => '512x512',
			'type'    => 'image/png',
			'purpose' => 'maskable',
		);
	}

	$screenshots = array();
	foreach ( (array) $s['screenshots'] as $shot ) {
		if ( ! $shot ) {
			continue;
		}
		$screenshots[] = array(
			'src'         => esc_url_raw( $shot ),
			'sizes'       => '1080x1920',
			'type'        => 'image/png',
			'form_factor' => 'narrow',
		);
	}

	$manifest = array(
		'id'               => home_url( '/' ),
		'name'             => $s['app_name'],
		'short_name'       => $s['short_name'],
		'description'      => $s['description'],
		'start_url'        => home_url( '/?utm_source=pwa' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'display_override' => array( 'standalone', 'minimal-ui' ),
		'orientation'      => 'any',
		'dir'              => $s['direction'],
		'lang'             => get_bloginfo( 'language' ),
		'theme_color'      => $s['theme_color'],
		'background_color' => $s['background_color'],
		'icons'            => $icons,
	);
	if ( $screenshots ) {
		$manifest['screenshots'] = $screenshots;
	}

	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

/**
 * ---------------------------------------------------------------
 *  iOS splash screens  (served at /?a2hsp_splash=WxH)
 *
 *  iOS only shows apple-touch-startup-image when the PNG matches the
 *  device's exact pixel resolution, so we render one per device size
 *  with GD and cache it in the uploads folder.
 * ---------------------------------------------------------------
 */

/**
 * Portrait device list: array( css-width, css-height, device-pixel-ratio )
 */
function a2hsp_splash_devices() {
	return array(
		array( 320, 568, 2 ),
		array( 375, 667, 2 ),
		array( 414, 736, 3 ),
		array( 375, 812, 3 ),
		array( 390, 844, 3 ),
		array( 393, 852, 3 ),
		array( 414, 896, 2 ),
		array( 414, 896, 3 ),
		array( 428, 926, 3 ),
		array( 430, 932, 3 ),
		array( 768, 1024, 2 ),
		array( 810, 1080, 2 ),
		array( 834, 1112, 2 ),
		array( 834, 1194, 2 ),
		array( 1024, 1366, 2 ),
	);
}

function a2hsp_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		$hex = '123F76';
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Load a GD image from a media URL (local attachment path preferred).
 */
function a2hsp_load_image( $url ) {
	if ( ! $url ) {
		return false;
	}
	$data = false;
	$id   = attachment_url_to_postid( $url );
	if ( $id ) {
		$path = get_attached_file( $id );
		if ( $path && file_exists( $path ) ) {
			$data = file_get_contents( $path );
		}
	}
	if ( ! $data ) {
		$res = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$data = wp_remote_retrieve_body( $res );
		}
	}
	if ( ! $data ) {
		return false;
	}
	$img = @imagecreatefromstring( $data );
	if ( $img ) {
		if ( function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $img );
		}
		imagealphablending( $img, true );
		imagesavealpha( $img, true );
	}
	return $img ? $img : false;
}

/**
 * Render (and cache) a splash PNG at the exact requested pixel size.
 * Uses the uploaded splash image (cover-cropped); falls back to
 * background color + centered app icon, mirroring Android's splash.
 */
function a2hsp_generate_splash( $w, $h ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}
	$s      = a2hsp_get_settings();
	$icon   = $s['icon_url'] ? $s['icon_url'] : get_site_icon_url( 512 );
	$upload = wp_upload_dir();
	$dir    = $upload['basedir'] . '/a2hsp-splash';
	$sig    = substr( md5( $s['splash_image'] . '|' . $icon . '|' . $s['background_color'] ), 0, 12 );
	$file   = $dir . '/splash-' . $sig . '-' . $w . 'x' . $h . '.png';

	if ( file_exists( $file ) ) {
		return $file;
	}
	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	$img = imagecreatetruecolor( $w, $h );
	list( $r, $g, $b ) = a2hsp_hex_to_rgb( $s['background_color'] );
	imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
	imagealphablending( $img, true );

	if ( $s['splash_image'] && ( $src = a2hsp_load_image( $s['splash_image'] ) ) ) {
		// Cover: scale to fill the canvas, center-cropped
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = max( $w / $sw, $h / $sh );
		$nw    = (int) round( $sw * $scale );
		$nh    = (int) round( $sh * $scale );
		imagecopyresampled( $img, $src, (int) ( ( $w - $nw ) / 2 ), (int) ( ( $h - $nh ) / 2 ), 0, 0, $nw, $nh, $sw, $sh );
		imagedestroy( $src );
	} elseif ( $icon && ( $src = a2hsp_load_image( $icon ) ) ) {
		// Background color + centered icon (~30% of width)
		$sw = imagesx( $src );
		$sh = imagesy( $src );
		$tw = (int) round( $w * 0.3 );
		$th = (int) round( $tw * $sh / $sw );
		imagecopyresampled( $img, $src, (int) ( ( $w - $tw ) / 2 ), (int) ( ( $h - $th ) / 2 ), 0, 0, $tw, $th, $sw, $sh );
		imagedestroy( $src );
	}

	imagepng( $img, $file, 6 );
	imagedestroy( $img );
	return file_exists( $file ) ? $file : false;
}

add_action( 'template_redirect', 'a2hsp_maybe_output_splash', 0 );
function a2hsp_maybe_output_splash() {
	if ( ! isset( $_GET['a2hsp_splash'] ) ) {
		return;
	}
	$size = sanitize_text_field( wp_unslash( $_GET['a2hsp_splash'] ) );
	if ( ! preg_match( '/^(\d{3,4})x(\d{3,4})$/', $size, $m ) ) {
		status_header( 404 );
		exit;
	}
	$w = (int) $m[1];
	$h = (int) $m[2];

	// Only whitelisted device resolutions are rendered
	$allowed = false;
	foreach ( a2hsp_splash_devices() as $d ) {
		if ( $d[0] * $d[2] === $w && $d[1] * $d[2] === $h ) {
			$allowed = true;
			break;
		}
	}
	if ( ! $allowed ) {
		status_header( 404 );
		exit;
	}

	$file = a2hsp_generate_splash( $w, $h );
	if ( ! $file ) {
		status_header( 404 );
		exit;
	}
	header( 'Content-Type: image/png' );
	header( 'Cache-Control: public, max-age=31536000, immutable' );
	header( 'Content-Length: ' . filesize( $file ) );
	readfile( $file );
	exit;
}

/**
 * ---------------------------------------------------------------
 *  Service Worker  (served at /?a2hsp_sw=1)
 * ---------------------------------------------------------------
 */
add_action( 'template_redirect', 'a2hsp_maybe_output_sw', 0 );
function a2hsp_maybe_output_sw() {
	if ( ! isset( $_GET['a2hsp_sw'] ) ) {
		return;
	}

	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: /' );
	?>
const CACHE = 'a2hsp-cache-v<?php echo esc_js( A2HSP_VERSION ); ?>';

self.addEventListener('install', (e) => {
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET' || !e.request.url.startsWith('http')) return;
  e.respondWith(
    fetch(e.request)
      .then((res) => {
        if (res.ok && res.type === 'basic') {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(e.request, copy));
        }
        return res;
      })
      .catch(() => caches.match(e.request).then((m) => m || caches.match('/')))
  );
});
	<?php
	exit;
}

/**
 * ---------------------------------------------------------------
 *  <head> tags
 * ---------------------------------------------------------------
 */
add_action( 'wp_head', 'a2hsp_head_tags', 1 );
function a2hsp_head_tags() {
	$s = a2hsp_get_settings();
	if ( ! $s['enabled'] ) {
		return;
	}
	$manifest_url = add_query_arg( 'a2hsp_manifest', '1', home_url( '/' ) );

	echo "\n<!-- Add to Home Screen Pro -->\n";
	echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">' . "\n";
	echo '<meta name="theme-color" content="' . esc_attr( $s['theme_color'] ) . '">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $s['short_name'] ) . '">' . "\n";

	$icon = $s['icon_url'] ? $s['icon_url'] : get_site_icon_url( 180 );
	if ( $icon ) {
		echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '">' . "\n";
	}

	/*
	 * iOS splash screens (apple-touch-startup-image).
	 * iOS ignores images whose pixel size doesn't exactly match the
	 * device, so each link points at the server-side generator that
	 * renders the exact resolution. Android splash comes from the
	 * manifest automatically.
	 */
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		foreach ( a2hsp_splash_devices() as $d ) {
			$href = add_query_arg( 'a2hsp_splash', ( $d[0] * $d[2] ) . 'x' . ( $d[1] * $d[2] ), home_url( '/' ) );
			printf(
				'<link rel="apple-touch-startup-image" media="(device-width: %1$dpx) and (device-height: %2$dpx) and (-webkit-device-pixel-ratio: %3$d) and (orientation: portrait)" href="%4$s">' . "\n",
				$d[0],
				$d[1],
				$d[2],
				esc_url( $href )
			);
		}
	} elseif ( $s['splash_image'] ) {
		// No GD on this server: fall back to the raw image (works only
		// on devices whose resolution happens to match it)
		foreach ( a2hsp_splash_devices() as $d ) {
			printf(
				'<link rel="apple-touch-startup-image" media="(device-width: %1$dpx) and (device-height: %2$dpx) and (-webkit-device-pixel-ratio: %3$d) and (orientation: portrait)" href="%4$s">' . "\n",
				$d[0],
				$d[1],
				$d[2],
				esc_url( $s['splash_image'] )
			);
		}
	}

	/*
	 * iOS standalone status bar tint.
	 * In an installed web app iOS ignores theme-color; the only way to
	 * get a colored bar is black-translucent + painting the top
	 * safe-area ourselves.
	 */
	if ( ! empty( $s['ios_statusbar'] ) ) {
		echo '<style id="a2hsp-statusbar">@media all and (display-mode: standalone){'
			. 'body{padding-top:env(safe-area-inset-top,0px) !important;}'
			. 'body::before{content:"";position:fixed;top:0;left:0;right:0;height:env(safe-area-inset-top,0px);'
			. 'background:' . esc_attr( $s['theme_color'] ) . ';z-index:2147483647;pointer-events:none;}'
			. '}</style>' . "\n";
	}
}

/**
 * ---------------------------------------------------------------
 *  Front-end assets
 * ---------------------------------------------------------------
 */
add_action( 'wp_enqueue_scripts', 'a2hsp_enqueue_assets' );
function a2hsp_enqueue_assets() {
	$s = a2hsp_get_settings();
	if ( ! $s['enabled'] ) {
		return;
	}

	wp_enqueue_style( 'a2hsp', A2HSP_URL . 'assets/a2hs-pro.css', array(), A2HSP_VERSION );
	wp_enqueue_script( 'a2hsp', A2HSP_URL . 'assets/a2hs-pro.js', array(), A2HSP_VERSION, true );

	// Manual-trigger button ([a2hs_button]) picks up the accent color
	wp_add_inline_style( 'a2hsp', '.a2hsp-open-btn{background:' . esc_attr( $s['accent_color'] ) . ' !important;color:#fff !important;}' );

	$icon = $s['icon_url'] ? $s['icon_url'] : get_site_icon_url( 512 );

	wp_localize_script( 'a2hsp', 'A2HSP', array(
		'swUrl'       => add_query_arg( 'a2hsp_sw', '1', home_url( '/' ) ),
		'swScope'     => wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/',
		'appName'     => $s['app_name'],
		'description' => $s['description'],
		'icon'        => $icon ? $icon : '',
		'screenshots' => array_values( array_filter( (array) $s['screenshots'] ) ),
		'accent'      => $s['accent_color'],
		'style'       => $s['display_style'],
		'darkMode'    => $s['dark_mode'],
		'dir'         => $s['direction'],
		'autoShow'    => (int) $s['auto_show'],
		'delay'       => (int) $s['delay_seconds'],
		'dismissDays' => (int) $s['dismiss_days'],
		'maxDisplay'  => (int) $s['max_display'],
		'minVisits'   => (int) $s['min_visits'],
		'desktop'     => (int) $s['show_on_desktop'],
		'themeColor'  => $s['theme_color'],
		'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
		'show'        => array(
			'subtitle'    => (int) $s['show_subtitle'],
			'host'        => (int) $s['show_host'],
			'description' => (int) $s['show_description'],
			'features'    => (int) $s['show_features'],
			'screenshots' => (int) $s['show_screenshots'],
			'later'       => (int) $s['show_later'],
		),
		'txt'         => array(
			'title'      => $s['txt_title'],
			'subtitle'   => $s['txt_subtitle'],
			'install'    => $s['txt_install'],
			'later'      => $s['txt_later'],
			'features'   => array_values( array_filter( array_map( 'trim', explode( "\n", $s['txt_features'] ) ) ) ),
			'iosStep1'   => $s['txt_ios_step1'],
			'iosStep2'   => $s['txt_ios_step2'],
			'iosStep3'   => $s['txt_ios_step3'],
			'inapp'      => $s['txt_inapp'],
			'inappHint'  => $s['txt_inapp_hint'],
			'copyLink'   => $s['txt_copy_link'],
			'copied'     => $s['txt_copied'],
			'success'    => $s['txt_success'],
			'guideTitle' => $s['txt_guide_title'],
		),
	) );
}

/**
 * Shortcode: [a2hs_button text="نصب اپلیکیشن"]
 * Renders a button that opens the install popup manually.
 */
add_shortcode( 'a2hs_button', function ( $atts ) {
	$s    = a2hsp_get_settings();
	$atts = shortcode_atts( array( 'text' => $s['txt_install'], 'class' => '' ), $atts );
	return '<button type="button" class="a2hsp-open-btn ' . esc_attr( $atts['class'] ) . '" data-a2hsp-open>'
		. esc_html( $atts['text'] ) . '</button>';
} );
