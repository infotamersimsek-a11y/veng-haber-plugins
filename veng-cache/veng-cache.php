<?php
/**
 * Plugin Name: Veng Cache
 * Description: Tam sayfa önbellekleme (dosya tabanlı), gzip sıkıştırma, tarayıcı önbellek başlıkları ve veritabanı temizliği ile siteyi hızlandırır. Girişli ziyaretçilere, aramalara ve admin'e dokunmaz; yeni haber yayınlanınca önbellek otomatik temizlenir.
 * Version: 2.0.1
 * Text Domain: veng-cache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Otomatik güncelleme: GitHub'daki paylaşılan depoyu kontrol eder, "Güncelleme mevcut" bildirimini
// wp-admin'de gösterir — artık zip indirip elle yüklemeye gerek yok.
if ( file_exists( __DIR__ . '/puc/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/puc/plugin-update-checker.php';
	$veng_cache_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/infotamersimsek-a11y/veng-haber-plugins/',
		__FILE__,
		'veng-cache'
	);
	$veng_cache_update_checker->setBranch( 'main' );
	$veng_cache_update_checker->getVcsApi()->enableReleaseAssets( '/^veng-cache\.zip$/' );
}

define( 'VENG_CACHE_DIR', WP_CONTENT_DIR . '/cache/veng-cache/' );
define( 'VENG_CACHE_URI', plugins_url( '', __FILE__ ) );
define( 'VENG_CACHE_PATH', plugin_dir_path( __FILE__ ) );

require VENG_CACHE_PATH . 'admin-page.php';

function veng_cache_enabled() {
	return '1' === get_option( 'veng_cache_enabled', '1' );
}

function veng_cache_ttl() {
	return (int) get_option( 'veng_cache_ttl', 900 );
}

/** Bu istek önbelleklenebilir mi: sadece anonim, GET, sorgu dizesi olmayan, admin/ajax/rest olmayan sayfa istekleri. */
function veng_cache_is_cacheable_request() {
	if ( ! veng_cache_enabled() ) {
		return false;
	}
	if ( is_admin() || is_user_logged_in() ) {
		return false;
	}
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return false;
	}
	if ( ! empty( $_GET ) ) {
		return false;
	}
	if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) || defined( 'XMLRPC_REQUEST' ) ) {
		return false;
	}
	if ( is_search() || is_404() || is_preview() ) {
		return false;
	}
	// Yorum yazmış (comment_author çerezi olan) ya da başka kişiselleştirilmiş çerezi olan
	// ziyaretçiye herkese ortak önbelleği gösterme.
	foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
		if ( 0 === strpos( $cookie_name, 'wordpress_' ) || 0 === strpos( $cookie_name, 'comment_author' ) ) {
			return false;
		}
	}
	return true;
}

function veng_cache_key() {
	$host = $_SERVER['HTTP_HOST'] ?? '';
	$uri = $_SERVER['REQUEST_URI'] ?? '/';
	return md5( $host . $uri );
}

function veng_cache_file_path() {
	return VENG_CACHE_DIR . veng_cache_key() . '.html';
}

function veng_cache_maybe_serve() {
	if ( ! veng_cache_is_cacheable_request() ) {
		return;
	}

	$file = veng_cache_file_path();
	if ( file_exists( $file ) && ( filemtime( $file ) > time() - veng_cache_ttl() ) ) {
		header( 'X-Veng-Cache: HIT' );
		header( 'Cache-Control: public, max-age=' . veng_cache_ttl() );
		if ( extension_loaded( 'zlib' ) && ! ini_get( 'zlib.output_compression' ) ) {
			ob_start( 'ob_gzhandler' );
		}
		readfile( $file );
		exit;
	}

	header( 'X-Veng-Cache: MISS' );
	header( 'Cache-Control: public, max-age=' . veng_cache_ttl() );
	if ( extension_loaded( 'zlib' ) && ! ini_get( 'zlib.output_compression' ) ) {
		ob_start( 'ob_gzhandler' );
	}
	ob_start( 'veng_cache_capture' );
}
add_action( 'template_redirect', 'veng_cache_maybe_serve', 0 );

function veng_cache_capture( $buffer ) {
	if ( strlen( $buffer ) > 0 && http_response_code() === 200 && veng_cache_is_cacheable_request() ) {
		if ( ! is_dir( VENG_CACHE_DIR ) ) {
			wp_mkdir_p( VENG_CACHE_DIR );
		}
		@file_put_contents( veng_cache_file_path(), $buffer );
	}
	return $buffer;
}

/** Yeni/güncellenen içerik anında görünsün diye tüm önbelleği temizler (kaba ama güvenli). */
function veng_cache_clear() {
	if ( ! is_dir( VENG_CACHE_DIR ) ) {
		return 0;
	}
	$count = 0;
	foreach ( glob( VENG_CACHE_DIR . '*.html' ) ?: array() as $file ) {
		if ( @unlink( $file ) ) {
			$count++;
		}
	}
	return $count;
}
add_action( 'save_post', 'veng_cache_clear' );
add_action( 'deleted_post', 'veng_cache_clear' );
add_action( 'switch_theme', 'veng_cache_clear' );
add_action( 'customize_save_after', 'veng_cache_clear' );
add_action( 'activated_plugin', 'veng_cache_clear' );
add_action( 'deactivated_plugin', 'veng_cache_clear' );

function veng_cache_stats() {
	$count = 0;
	$bytes = 0;
	$files = array();
	if ( is_dir( VENG_CACHE_DIR ) ) {
		foreach ( glob( VENG_CACHE_DIR . '*.html' ) ?: array() as $file ) {
			$count++;
			$size = filesize( $file );
			$bytes += $size;
			$files[] = array( 'size' => $size, 'age' => time() - filemtime( $file ) );
		}
	}
	usort( $files, fn( $a, $b ) => $a['age'] <=> $b['age'] );
	return array( 'count' => $count, 'bytes' => $bytes, 'recent' => array_slice( $files, 0, 8 ) );
}

/** --- Veritabanı temizliği: revizyonlar, taslaklar, çöp, spam, süresi dolmuş transient'lar --- */

function veng_cache_db_counts() {
	global $wpdb;
	return array(
		'revisions'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
		'auto_drafts'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
		'trashed_posts'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
		'spam_comments'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
		'trashed_comments'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
		'expired_transients' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" ),
	);
}

function veng_cache_db_cleanup( $items ) {
	global $wpdb;
	$deleted = 0;

	if ( in_array( 'revisions', $items, true ) ) {
		foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" ) as $id ) {
			wp_delete_post_revision( $id );
			$deleted++;
		}
	}
	if ( in_array( 'auto_drafts', $items, true ) ) {
		foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ) as $id ) {
			wp_delete_post( $id, true );
			$deleted++;
		}
	}
	if ( in_array( 'trashed_posts', $items, true ) ) {
		foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" ) as $id ) {
			wp_delete_post( $id, true );
			$deleted++;
		}
	}
	if ( in_array( 'spam_comments', $items, true ) ) {
		foreach ( $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ) as $id ) {
			wp_delete_comment( $id, true );
			$deleted++;
		}
	}
	if ( in_array( 'trashed_comments', $items, true ) ) {
		foreach ( $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ) as $id ) {
			wp_delete_comment( $id, true );
			$deleted++;
		}
	}
	if ( in_array( 'expired_transients', $items, true ) ) {
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" );
		$wpdb->query(
			"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
			 WHERE a.option_name LIKE '\_transient\_%' AND a.option_name NOT LIKE '\_transient\_timeout\_%'
			 AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
			 AND b.option_value < UNIX_TIMESTAMP()"
		);
		$deleted += $before;
	}

	return $deleted;
}

/** --- AJAX --- */

function veng_cache_ajax_clear() {
	check_ajax_referer( 'veng_cache_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Yetkiniz yok.', 403 );
	}
	$n = veng_cache_clear();
	wp_send_json_success( array( 'deleted' => $n, 'stats' => veng_cache_stats() ) );
}
add_action( 'wp_ajax_veng_cache_clear', 'veng_cache_ajax_clear' );

function veng_cache_ajax_db_cleanup() {
	check_ajax_referer( 'veng_cache_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Yetkiniz yok.', 403 );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$items = isset( $_POST['items'] ) ? array_map( 'sanitize_key', (array) $_POST['items'] ) : array();
	$deleted = veng_cache_db_cleanup( $items );
	wp_send_json_success( array( 'deleted' => $deleted, 'counts' => veng_cache_db_counts() ) );
}
add_action( 'wp_ajax_veng_cache_db_cleanup', 'veng_cache_ajax_db_cleanup' );

function veng_cache_ajax_save_settings() {
	check_ajax_referer( 'veng_cache_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Yetkiniz yok.', 403 );
	}
	update_option( 'veng_cache_enabled', ! empty( $_POST['enabled'] ) ? '1' : '0' );
	update_option( 'veng_cache_ttl', max( 60, intval( $_POST['ttl'] ?? 900 ) ) );
	wp_send_json_success();
}
add_action( 'wp_ajax_veng_cache_save_settings', 'veng_cache_ajax_save_settings' );
