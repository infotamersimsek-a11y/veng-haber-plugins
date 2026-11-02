<?php
/**
 * Plugin Name: Veng Güvenlik
 * Description: Bot/tarama trafiğini kaydeder ve izler (404 patlamaları, kaba kuvvet girişleri), aşırı istek yapan IP'leri otomatik geçici engeller, panelde en çok istek yapan IP/adres/bot tablolarını gösterir. Sunucu loguna bakmadan wp-admin'den takip edilir.
 * Version: 1.0.0
 * Author: Veng Haber
 * Text Domain: veng-guvenlik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/puc/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/puc/plugin-update-checker.php';
	$veng_sec_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/infotamersimsek-a11y/veng-haber-plugins/',
		__FILE__,
		'veng-guvenlik'
	);
	$veng_sec_update_checker->setBranch( 'main' );
	$veng_sec_vcs_api = $veng_sec_update_checker->getVcsApi();
	$veng_sec_vcs_api->enableReleaseAssets( '/^veng-guvenlik\.zip$/' );
	// Depoyu birden çok eklenti paylaşıyor — sadece "-guvenlik" etiketli sürümleri eşle.
	$veng_sec_vcs_api->setReleaseVersionFilter( '/-guvenlik$/' );
}

define( 'VENG_SEC_DB_VERSION', '1' );
define( 'VENG_SEC_RETENTION_DAYS', 7 );

/*
 * TASARIM NOTLARI (bu eklenti, sayfa isteğinde ağır iş yapmama kuralına göre yazıldı):
 * - Normal sayfa görüntülemelerinde TEK ekstra iş: autoload edilen engel listesi
 *   (option) bellekten okunur — ek DB sorgusu yok.
 * - DB'ye yazma/sayma SADECE şüpheli isteklerde (404, başarısız giriş) olur.
 * - Bir IP eşiği aşınca engellenir; engellenen istekler loglanmaz (log şişmez).
 * - Raporlama sorguları indeksli, LIMIT'li ve 7 günle sınırlı küçük tabloda çalışır.
 */

function veng_sec_settings() {
	return wp_parse_args(
		get_option( 'veng_sec_settings', array() ),
		array(
			'enabled'        => '1',
			'threshold'      => 30,   // 60 sn içinde en fazla bu kadar şüpheli istek
			'block_minutes'  => 60,
			'whitelist'      => '',
		)
	);
}

/* ---------------- IP tespiti (Cloudflare güvenli) ---------------- */

function veng_sec_cloudflare_ranges() {
	return array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);
}

function veng_sec_ip_in_cidr( $ip, $cidr ) {
	list( $subnet, $bits ) = array_pad( explode( '/', $cidr ), 2, '' );
	$ip_bin     = @inet_pton( $ip );
	$subnet_bin = @inet_pton( $subnet );
	if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
		return false;
	}
	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
		return false;
	}
	$rem = $bits % 8;
	if ( $rem ) {
		$mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;
		if ( ( ord( $ip_bin[ $bytes ] ) & $mask ) !== ( ord( $subnet_bin[ $bytes ] ) & $mask ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Gerçek ziyaretçi IP'si. CF-Connecting-IP başlığına SADECE istek gerçekten Cloudflare
 * IP aralığından geliyorsa güvenilir — yoksa herhangi biri sahte başlıkla başkasını
 * engellettirebilirdi.
 */
function veng_sec_client_ip() {
	static $ip = null;
	if ( null !== $ip ) {
		return $ip;
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$ip     = $remote;
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		$candidate = trim( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] );
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			foreach ( veng_sec_cloudflare_ranges() as $range ) {
				if ( veng_sec_ip_in_cidr( $remote, $range ) ) {
					$ip = $candidate;
					break;
				}
			}
		}
	}
	return $ip;
}

function veng_sec_country() {
	if ( empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
		return '';
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	foreach ( veng_sec_cloudflare_ranges() as $range ) {
		if ( veng_sec_ip_in_cidr( $remote, $range ) ) {
			return strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $_SERVER['HTTP_CF_IPCOUNTRY'] ), 0, 2 ) );
		}
	}
	return '';
}

function veng_sec_is_whitelisted( $ip ) {
	$s = veng_sec_settings();
	foreach ( preg_split( '/\R+/', (string) $s['whitelist'] ) as $entry ) {
		$entry = trim( $entry );
		if ( '' === $entry ) {
			continue;
		}
		if ( false !== strpos( $entry, '/' ) ? veng_sec_ip_in_cidr( $ip, $entry ) : $entry === $ip ) {
			return true;
		}
	}
	return false;
}

/** User-Agent kolayca taklit edilir — bu yüzden muaf değil, sadece eşiği 5 kat gevşetir. */
function veng_sec_claims_search_bot() {
	return (bool) preg_match( '/(googlebot|bingbot|yandexbot|duckduckbot|applebot|facebookexternalhit)/i', veng_sec_ua() );
}

function veng_sec_ua() {
	return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 250 ) : '';
}

/* ---------------- Engel listesi (autoload option, ek sorgu yok) ---------------- */

function veng_sec_blocked_list() {
	$list = get_option( 'veng_sec_blocked', array() );
	return is_array( $list ) ? $list : array();
}

function veng_sec_block_ip( $ip, $minutes, $reason, $manual = false ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}
	$list = veng_sec_blocked_list();
	$now  = time();
	foreach ( $list as $k => $row ) {
		if ( $row['until'] < $now ) {
			unset( $list[ $k ] );
		}
	}
	$list[ $ip ] = array(
		'until'  => $now + max( 1, (int) $minutes ) * MINUTE_IN_SECONDS,
		'time'   => $now,
		'reason' => substr( $reason, 0, 80 ),
		'manual' => $manual ? 1 : 0,
	);
	if ( count( $list ) > 300 ) {
		uasort( $list, function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
		$list = array_slice( $list, 0, 300, true );
	}
	update_option( 'veng_sec_blocked', $list, true );
	return true;
}

function veng_sec_unblock_ip( $ip ) {
	$list = veng_sec_blocked_list();
	unset( $list[ $ip ] );
	update_option( 'veng_sec_blocked', $list, true );
	delete_transient( 'veng_sec_hits_' . md5( $ip ) );
}

/** Her istekte en erken noktada: engelli IP'ye hiçbir şey render etmeden 403. */
function veng_sec_enforce_block() {
	$s = veng_sec_settings();
	if ( '1' !== (string) $s['enabled'] || is_user_logged_in() ) {
		return;
	}
	$list = veng_sec_blocked_list();
	if ( empty( $list ) ) {
		return;
	}
	$ip = veng_sec_client_ip();
	if ( isset( $list[ $ip ] ) && $list[ $ip ]['until'] > time() && ! veng_sec_is_whitelisted( $ip ) ) {
		status_header( 403 );
		nocache_headers();
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/plain; charset=utf-8' );
		exit( 'Erisim gecici olarak engellendi.' );
	}
}
add_action( 'init', 'veng_sec_enforce_block', 0 );

/* ---------------- Şüpheli istek sayımı + log ---------------- */

/** @return bool true: eşik aşıldı ve IP engellendi */
function veng_sec_register_hit( $weight, $reason ) {
	$s  = veng_sec_settings();
	$ip = veng_sec_client_ip();
	if ( '1' !== (string) $s['enabled'] || ! filter_var( $ip, FILTER_VALIDATE_IP ) || veng_sec_is_whitelisted( $ip ) ) {
		return false;
	}
	$key   = 'veng_sec_hits_' . md5( $ip );
	$count = (int) get_transient( $key ) + (int) $weight;
	set_transient( $key, $count, 60 );
	$limit = (int) $s['threshold'] * ( veng_sec_claims_search_bot() ? 5 : 1 );
	if ( $count > $limit ) {
		veng_sec_block_ip( $ip, (int) $s['block_minutes'], 'Otomatik: ' . $reason . ' (' . $count . '/dk)' );
		return true;
	}
	return false;
}

function veng_sec_log_row( $reason, $status ) {
	global $wpdb;
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? substr( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), 0, 250 ) : '';
	$wpdb->insert(
		$wpdb->prefix . 'veng_sec_log',
		array(
			'ts'      => time(),
			'ip'      => substr( veng_sec_client_ip(), 0, 45 ),
			'country' => veng_sec_country(),
			'uri'     => $uri,
			'status'  => (int) $status,
			'ua'      => veng_sec_ua(),
			'reason'  => substr( $reason, 0, 20 ),
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
	);
}

function veng_sec_on_shutdown() {
	if ( is_user_logged_in() || is_admin() ) {
		return;
	}
	$status = (int) http_response_code();
	if ( 404 !== $status ) {
		return;
	}
	if ( veng_sec_is_whitelisted( veng_sec_client_ip() ) ) {
		return;
	}
	if ( ! veng_sec_register_hit( 1, '404 patlaması' ) ) {
		veng_sec_log_row( '404', 404 );
	}
}
add_action( 'shutdown', 'veng_sec_on_shutdown' );

function veng_sec_on_login_failed() {
	if ( ! veng_sec_register_hit( 6, 'başarısız giriş' ) ) {
		veng_sec_log_row( 'giris', 401 );
	}
}
add_action( 'wp_login_failed', 'veng_sec_on_login_failed' );

/* ---------------- Tablo + temizlik ---------------- */

function veng_sec_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = $wpdb->prefix . 'veng_sec_log';
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts int(10) unsigned NOT NULL,
		ip varchar(45) NOT NULL DEFAULT '',
		country varchar(2) NOT NULL DEFAULT '',
		uri varchar(255) NOT NULL DEFAULT '',
		status smallint(5) unsigned NOT NULL DEFAULT 0,
		ua varchar(255) NOT NULL DEFAULT '',
		reason varchar(20) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY ts (ts),
		KEY ip (ip)
	) {$charset};" );
	update_option( 'veng_sec_db_ver', VENG_SEC_DB_VERSION );
}

function veng_sec_maybe_upgrade() {
	if ( is_admin() && get_option( 'veng_sec_db_ver' ) !== VENG_SEC_DB_VERSION ) {
		veng_sec_install_table();
	}
}
add_action( 'init', 'veng_sec_maybe_upgrade' );

function veng_sec_activate() {
	veng_sec_install_table();
	if ( ! wp_next_scheduled( 'veng_sec_prune_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'veng_sec_prune_event' );
	}
}
register_activation_hook( __FILE__, 'veng_sec_activate' );

function veng_sec_deactivate() {
	wp_clear_scheduled_hook( 'veng_sec_prune_event' );
}
register_deactivation_hook( __FILE__, 'veng_sec_deactivate' );

function veng_sec_prune() {
	global $wpdb;
	$cut = time() - VENG_SEC_RETENTION_DAYS * DAY_IN_SECONDS;
	for ( $i = 0; $i < 20; $i++ ) {
		$n = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}veng_sec_log WHERE ts < %d LIMIT 5000", $cut ) );
		if ( ! $n ) {
			break;
		}
	}
	$list = veng_sec_blocked_list();
	$now  = time();
	foreach ( $list as $k => $row ) {
		if ( $row['until'] < $now ) {
			unset( $list[ $k ] );
		}
	}
	update_option( 'veng_sec_blocked', $list, true );
}
add_action( 'veng_sec_prune_event', 'veng_sec_prune' );

/* ---------------- Yönetim sayfası ---------------- */

function veng_sec_register_menu() {
	add_menu_page( 'Veng Güvenlik', 'Veng Güvenlik', 'manage_options', 'veng-guvenlik', 'veng_sec_admin_page', 'dashicons-shield', 26 );
}
add_action( 'admin_menu', 'veng_sec_register_menu' );

function veng_sec_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'veng_sec_log';

	if ( isset( $_POST['veng_sec_action'] ) && check_admin_referer( 'veng_sec' ) ) {
		$action = sanitize_key( wp_unslash( $_POST['veng_sec_action'] ) );
		if ( 'save' === $action ) {
			update_option( 'veng_sec_settings', array(
				'enabled'       => isset( $_POST['enabled'] ) ? '1' : '0',
				'threshold'     => max( 5, min( 500, (int) ( $_POST['threshold'] ?? 30 ) ) ),
				'block_minutes' => max( 1, min( 10080, (int) ( $_POST['block_minutes'] ?? 60 ) ) ),
				'whitelist'     => sanitize_textarea_field( wp_unslash( $_POST['whitelist'] ?? '' ) ),
			), true );
			echo '<div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div>';
		} elseif ( 'block' === $action ) {
			$ip = trim( (string) wp_unslash( $_POST['ip'] ?? '' ) );
			if ( $ip === veng_sec_client_ip() ) {
				echo '<div class="notice notice-error"><p>Kendi IP adresini engelleyemezsin.</p></div>';
			} elseif ( veng_sec_block_ip( $ip, max( 1, (int) ( $_POST['minutes'] ?? 1440 ) ), 'Elle engellendi', true ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $ip ) . ' engellendi.</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Geçersiz IP.</p></div>';
			}
		} elseif ( 'unblock' === $action ) {
			veng_sec_unblock_ip( trim( (string) wp_unslash( $_POST['ip'] ?? '' ) ) );
			echo '<div class="notice notice-success"><p>Engel kaldırıldı.</p></div>';
		} elseif ( 'clear' === $action ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			echo '<div class="notice notice-success"><p>Kayıtlar temizlendi.</p></div>';
		}
	}

	$s     = veng_sec_settings();
	$range = ( isset( $_GET['range'] ) && '7d' === $_GET['range'] ) ? '7d' : '24h';
	$since = time() - ( '7d' === $range ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS );

	$total   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ts > %d", $since ) );
	$top_ips = $wpdb->get_results( $wpdb->prepare( "SELECT ip, MAX(country) country, COUNT(*) c, MAX(ts) last FROM {$table} WHERE ts > %d GROUP BY ip ORDER BY c DESC LIMIT 15", $since ) );
	$top_uri = $wpdb->get_results( $wpdb->prepare( "SELECT uri, COUNT(*) c FROM {$table} WHERE ts > %d GROUP BY uri ORDER BY c DESC LIMIT 15", $since ) );
	$top_ua  = $wpdb->get_results( $wpdb->prepare( "SELECT ua, COUNT(*) c FROM {$table} WHERE ts > %d GROUP BY ua ORDER BY c DESC LIMIT 10", $since ) );
	$top_cc  = $wpdb->get_results( $wpdb->prepare( "SELECT country, COUNT(*) c FROM {$table} WHERE ts > %d GROUP BY country ORDER BY c DESC LIMIT 10", $since ) );
	$recent  = $wpdb->get_results( "SELECT ts, ip, country, uri, status, reason FROM {$table} ORDER BY id DESC LIMIT 40" );
	$blocked = array_filter( veng_sec_blocked_list(), function ( $r ) { return $r['until'] > time(); } );
	$nonce   = wp_nonce_field( 'veng_sec', '_wpnonce', true, false );
	?>
	<div class="wrap">
		<h1>Veng Güvenlik</h1>
		<p>404 patlamaları ve başarısız girişler kaydedilir; bir IP 60 saniyede eşiği aşarsa geçici engellenir. Normal ziyaretçiler ve arama botları etkilenmez. Senin IP'n: <code><?php echo esc_html( veng_sec_client_ip() ); ?></code></p>
		<p>
			<a class="button<?php echo '24h' === $range ? ' button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=veng-guvenlik&range=24h' ) ); ?>">Son 24 saat</a>
			<a class="button<?php echo '7d' === $range ? ' button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=veng-guvenlik&range=7d' ) ); ?>">Son 7 gün</a>
			<strong style="margin-left:12px;">Kaydedilen şüpheli istek: <?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
			<strong style="margin-left:12px;">Şu an engelli IP: <?php echo esc_html( count( $blocked ) ); ?></strong>
		</p>

		<h2>Engelli IP'ler</h2>
		<?php if ( $blocked ) : ?>
			<table class="widefat striped"><thead><tr><th>IP</th><th>Sebep</th><th>Bitiş</th><th></th></tr></thead><tbody>
			<?php foreach ( $blocked as $ip => $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $ip ); ?></code></td>
					<td><?php echo esc_html( $row['reason'] ); ?></td>
					<td><?php echo esc_html( wp_date( 'd.m.Y H:i', $row['until'] ) ); ?></td>
					<td><form method="post"><?php echo $nonce; // phpcs:ignore ?><input type="hidden" name="veng_sec_action" value="unblock"><input type="hidden" name="ip" value="<?php echo esc_attr( $ip ); ?>"><button class="button">Engeli kaldır</button></form></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p>Şu an engelli IP yok.</p>
		<?php endif; ?>
		<form method="post" style="margin-top:10px;"><?php echo $nonce; // phpcs:ignore ?>
			<input type="hidden" name="veng_sec_action" value="block">
			<input type="text" name="ip" placeholder="IP adresi" class="regular-text">
			<select name="minutes"><option value="60">1 saat</option><option value="1440" selected>1 gün</option><option value="10080">7 gün</option><option value="525600">1 yıl</option></select>
			<button class="button">Elle engelle</button>
		</form>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px;margin-top:20px;">
			<div><h2>En çok istek yapan IP'ler</h2>
				<table class="widefat striped"><thead><tr><th>IP</th><th>Ülke</th><th>İstek</th><th>Son</th><th></th></tr></thead><tbody>
				<?php foreach ( $top_ips as $r ) : ?>
					<tr><td><code><?php echo esc_html( $r->ip ); ?></code></td><td><?php echo esc_html( $r->country ?: '-' ); ?></td><td><?php echo esc_html( number_format_i18n( $r->c ) ); ?></td><td><?php echo esc_html( wp_date( 'd.m H:i', $r->last ) ); ?></td>
					<td><form method="post"><?php echo $nonce; // phpcs:ignore ?><input type="hidden" name="veng_sec_action" value="block"><input type="hidden" name="ip" value="<?php echo esc_attr( $r->ip ); ?>"><input type="hidden" name="minutes" value="1440"><button class="button button-small">Engelle</button></form></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $top_ips ) : ?><tr><td colspan="5">Kayıt yok.</td></tr><?php endif; ?>
				</tbody></table></div>
			<div><h2>En çok istenen adresler</h2>
				<table class="widefat striped"><thead><tr><th>Adres</th><th>İstek</th></tr></thead><tbody>
				<?php foreach ( $top_uri as $r ) : ?>
					<tr><td style="word-break:break-all;"><?php echo esc_html( $r->uri ); ?></td><td><?php echo esc_html( number_format_i18n( $r->c ) ); ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $top_uri ) : ?><tr><td colspan="2">Kayıt yok.</td></tr><?php endif; ?>
				</tbody></table></div>
			<div><h2>Tarayıcı / bot (User-Agent)</h2>
				<table class="widefat striped"><thead><tr><th>User-Agent</th><th>İstek</th></tr></thead><tbody>
				<?php foreach ( $top_ua as $r ) : ?>
					<tr><td style="word-break:break-all;"><?php echo esc_html( $r->ua ?: '(boş)' ); ?></td><td><?php echo esc_html( number_format_i18n( $r->c ) ); ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $top_ua ) : ?><tr><td colspan="2">Kayıt yok.</td></tr><?php endif; ?>
				</tbody></table></div>
			<div><h2>Ülkeler</h2>
				<table class="widefat striped"><thead><tr><th>Ülke</th><th>İstek</th></tr></thead><tbody>
				<?php foreach ( $top_cc as $r ) : ?>
					<tr><td><?php echo esc_html( $r->country ?: 'bilinmiyor' ); ?></td><td><?php echo esc_html( number_format_i18n( $r->c ) ); ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $top_cc ) : ?><tr><td colspan="2">Kayıt yok.</td></tr><?php endif; ?>
				</tbody></table></div>
		</div>

		<h2 style="margin-top:24px;">Son 40 olay</h2>
		<table class="widefat striped"><thead><tr><th>Zaman</th><th>IP</th><th>Ülke</th><th>Adres</th><th>Durum</th></tr></thead><tbody>
		<?php foreach ( $recent as $r ) : ?>
			<tr><td><?php echo esc_html( wp_date( 'd.m H:i:s', $r->ts ) ); ?></td><td><code><?php echo esc_html( $r->ip ); ?></code></td><td><?php echo esc_html( $r->country ?: '-' ); ?></td><td style="word-break:break-all;"><?php echo esc_html( $r->uri ); ?></td><td><?php echo esc_html( $r->status . ' ' . $r->reason ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( ! $recent ) : ?><tr><td colspan="5">Kayıt yok.</td></tr><?php endif; ?>
		</tbody></table>

		<h2 style="margin-top:24px;">Ayarlar</h2>
		<form method="post"><?php echo $nonce; // phpcs:ignore ?>
			<input type="hidden" name="veng_sec_action" value="save">
			<table class="form-table">
				<tr><th>Koruma</th><td><label><input type="checkbox" name="enabled" <?php checked( '1', (string) $s['enabled'] ); ?>> Açık (otomatik engelleme)</label></td></tr>
				<tr><th>Eşik</th><td><input type="number" name="threshold" value="<?php echo esc_attr( $s['threshold'] ); ?>" min="5" max="500"> şüpheli istek / 60 sn (başarısız giriş 6 puan sayılır)</td></tr>
				<tr><th>Engel süresi</th><td><input type="number" name="block_minutes" value="<?php echo esc_attr( $s['block_minutes'] ); ?>" min="1" max="10080"> dakika</td></tr>
				<tr><th>Beyaz liste</th><td><textarea name="whitelist" rows="4" class="large-text code" placeholder="Her satıra bir IP veya CIDR (ör. 1.2.3.4 ya da 10.0.0.0/24)"><?php echo esc_textarea( $s['whitelist'] ); ?></textarea><p class="description">Arama botu User-Agent'ı taşıyanlara eşik 5 kat gevşek uygulanır (User-Agent taklit edilebildiği için tam muaf değil). Giriş yapmış kullanıcılar asla engellenmez.</p></td></tr>
			</table>
			<p><button class="button button-primary">Kaydet</button></p>
		</form>
		<form method="post" onsubmit="return confirm('Tüm kayıtlar silinecek. Emin misin?');"><?php echo $nonce; // phpcs:ignore ?><input type="hidden" name="veng_sec_action" value="clear"><button class="button">Kayıtları temizle</button></form>
	</div>
	<?php
}
