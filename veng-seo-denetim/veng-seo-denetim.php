<?php
/**
 * Plugin Name: Veng SEO Denetim
 * Description: Sitenin kendi canlı sayfalarını anlık tarar; Performans, SEO, Erişilebilirlik ve En İyi Uygulamalar kategorilerinde (Lighthouse/PageSpeed Insights tarzı) puanlar, Masaüstü ve Mobil için ayrı ayrı analiz yapar.
 * Version: 2.0.1
 * Text Domain: veng-seo-denetim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Otomatik güncelleme: GitHub'daki paylaşılan depoyu kontrol eder, "Güncelleme mevcut" bildirimini
// wp-admin'de gösterir — artık zip indirip elle yüklemeye gerek yok.
if ( file_exists( __DIR__ . '/puc/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/puc/plugin-update-checker.php';
	$veng_seo_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/infotamersimsek-a11y/veng-haber-plugins/',
		__FILE__,
		'veng-seo-denetim'
	);
	$veng_seo_update_checker->setBranch( 'main' );
	$veng_seo_update_checker->getVcsApi()->enableReleaseAssets( '/^veng-seo-denetim\.zip$/' );
}

function veng_seo_log( $msg ) {
	error_log( '[VengSeo] ' . $msg );
}

/**
 * Görsel sıkıştırma: Veng Oto Haber (veya ileride başka bir eklenti) yeni bir görsel
 * sideload ettiğinde 'veng_image_sideloaded' hook'unu ateşler, biz burada dinleyip
 * anında sıkıştırıyoruz. Bu eklenti tek başına da (Oto Haber olmadan) çalışır —
 * "Eski Görselleri Sıkıştır" ve 30 dakikalık otomatik tarama, mevcut haberlerdeki
 * öne çıkan görselleri kontrol eder.
 */
add_action( 'veng_image_sideloaded', 'veng_seo_compress_image' );

/** Orijinal genişliğe göre mantıklı bir hedef genişlik seçer — büyük görsel daha çok küçülür, zaten küçük olan büyütülmez. */
function veng_seo_target_width( $orig_w ) {
	if ( $orig_w > 2400 ) {
		return 1600;
	}
	if ( $orig_w > 1600 ) {
		return 1200;
	}
	if ( $orig_w > 1000 ) {
		return 1000;
	}
	return $orig_w;
}

/**
 * WebP, aynı görsel kalitede JPEG/PNG'den belirgin şekilde küçük dosya üretir — bu yüzden
 * format kalite-merdiveni yerine tek adımda WebP'ye çevirip hedefe ulaşıyoruz. Hâlâ üstteyse
 * tek bir ek sıkma kademesi yeterli oluyor (eski JPEG kalite-merdiveni karmaşık ve güvenilmezdi).
 */
function veng_seo_compress_image( $attachment_id, $max_bytes = 153600 ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return;
	}
	$is_webp = 'webp' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( $is_webp && filesize( $file ) <= $max_bytes ) {
		return; // zaten WebP ve hedef altında, tekrar işlemeye gerek yok
	}

	// Bazı paylaşımlı hostlarda düşük bellek limiti büyük görselleri işlerken fatal hataya
	// yol açıyordu — WP'nin kendi görsel işleme için bellek limiti yükseltme fonksiyonu.
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}

	try {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			veng_seo_log( 'Görsel sıkıştırma (editor) başarısız #' . $attachment_id . ': ' . $editor->get_error_message() );
			return;
		}
		$size = $editor->get_size();
		$target_w = veng_seo_target_width( $size ? $size['width'] : 0 );
		if ( $size && $target_w && $size['width'] > $target_w ) {
			$editor->resize( $target_w, 9999, false );
		}
		$editor->set_quality( 75 );
		$webp_path = preg_replace( '/\.\w+$/', '.webp', $file );
		$saved = $editor->save( $webp_path, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			veng_seo_log( 'Görsel WebP dönüşümü başarısız #' . $attachment_id . ': ' . $saved->get_error_message() );
			return;
		}
		$new_file = $saved['path'];
		clearstatcache( true, $new_file );

		// Hâlâ hedefin üstündeyse tek bir ek kademe: kaliteyi ve boyutu biraz daha düşür.
		if ( filesize( $new_file ) > $max_bytes ) {
			$editor2 = wp_get_image_editor( $new_file );
			if ( ! is_wp_error( $editor2 ) ) {
				$editor2->resize( max( 500, (int) round( $target_w * 0.7 ) ), 9999, false );
				$editor2->set_quality( 55 );
				$editor2->save( $new_file, 'image/webp' );
			}
		}

		if ( $new_file !== $file && file_exists( $new_file ) ) {
			@unlink( $file );
			update_attached_file( $attachment_id, $new_file );
			$file = $new_file;
		}
	} catch ( \Throwable $e ) {
		veng_seo_log( 'Görsel sıkıştırma istisna #' . $attachment_id . ': ' . $e->getMessage() );
		return;
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
	wp_update_attachment_metadata( $attachment_id, $metadata );
}

/** Hedef: WebP formatında VE 150KB altında. İkisinden biri eksikse işlenmesi gerekiyor demektir. */
function veng_seo_image_needs_processing( $file, $max_bytes = 153600 ) {
	$is_webp = 'webp' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	return ! $is_webp || filesize( $file ) > $max_bytes;
}

/** Kaç otomatik haber görseli hedefte (WebP + 150KB altında), kaçı hâlâ işlenmeyi bekliyor. */
function veng_seo_compression_stats() {
	$total = 0;
	$over = 0;
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_veng_source_name',
		'fields'         => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			continue;
		}
		$file = get_attached_file( $thumb_id );
		if ( ! $file || ! file_exists( $file ) ) {
			continue;
		}
		$total++;
		if ( veng_seo_image_needs_processing( $file ) ) {
			$over++;
		}
	}
	return array( 'total' => $total, 'over' => $over, 'ok' => $total - $over );
}

/**
 * Yüzlerce haberin görselini tek tek diskten okumak (stat çağrısı) sayfa açılışını
 * yavaşlatıyordu — sonucu 5 dakika önbellekler. Sıkıştırma işlemi bitince önbellek
 * hemen temizlenir, o an güncel sayı görünür.
 */
function veng_seo_compression_stats_cached() {
	$cached = get_transient( 'veng_seo_compression_stats' );
	if ( false !== $cached ) {
		return $cached;
	}
	$stats = veng_seo_compression_stats();
	set_transient( 'veng_seo_compression_stats', $stats, 5 * MINUTE_IN_SECONDS );
	return $stats;
}

/**
 * Daha önce sıkıştırılmadan yüklenmiş eski haber görsellerini (150KB üstü) küçültür.
 * Bazı hostlarda tek istekte yüzlerce büyük görseli işlemek zaman aşımı/bellek hatasına yol
 * açıyordu — bu yüzden her çalıştırmada en fazla $batch_size görsel işlenir.
 */
function veng_seo_backfill_compress_images( $batch_size = 25 ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$fixed = 0;
	$remaining = 0;
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_veng_source_name',
		'fields'         => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			continue;
		}
		$file = get_attached_file( $thumb_id );
		if ( ! $file || ! file_exists( $file ) || ! veng_seo_image_needs_processing( $file ) ) {
			continue;
		}
		if ( $fixed >= $batch_size ) {
			$remaining++;
			continue;
		}
		veng_seo_compress_image( $thumb_id );
		$fixed++;
	}
	return array( 'fixed' => $fixed, 'remaining' => $remaining );
}

function veng_seo_cron_schedules( $schedules ) {
	$schedules['veng_seo_thirty_minutes'] = array(
		'interval' => 30 * MINUTE_IN_SECONDS,
		'display'  => 'Her 30 Dakikada Bir (Veng Görsel Sıkıştırma)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'veng_seo_cron_schedules' );

/** Görsel sıkıştırmayı gözden kaçan haberler için 30 dakikada bir otomatik tarar, sonucu kaydeder. */
function veng_seo_compress_sweep() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$r = veng_seo_backfill_compress_images( 40 );
	update_option( 'veng_seo_last_compress_run', array(
		'time'      => current_time( 'mysql' ),
		'fixed'     => $r['fixed'],
		'remaining' => $r['remaining'],
	) );
	set_transient( 'veng_seo_compression_stats', veng_seo_compression_stats(), 5 * MINUTE_IN_SECONDS );
}
add_action( 'veng_seo_compress_sweep_event', 'veng_seo_compress_sweep' );

/** Eklenti aktifleştirilir aktifleştirilmez eski görselleri sıkıştırır — elle "Şimdi Tara"ya basmaya gerek kalmaz. */
function veng_seo_activate() {
	if ( ! wp_next_scheduled( 'veng_seo_compress_sweep_event' ) ) {
		// Zamanı "şimdi" vermek ilk taramayı hemen "vadesi geldi" yapar — senkron çalıştırmaya
		// gerek yok, dışarıdan kurulan cron ping (cron-job.org/UptimeRobot) birkaç dakika
		// içinde wp-cron.php'yi tetikleyip bunu otomatik çalıştırır. Aktivasyon isteğinin
		// kendi içinde 40 görseli senkron sıkıştırmak, zayıf sunucularda 502'ye yol açıyordu.
		wp_schedule_event( time(), 'veng_seo_thirty_minutes', 'veng_seo_compress_sweep_event' );
	}
}
register_activation_hook( __FILE__, 'veng_seo_activate' );

function veng_seo_deactivate() {
	$timestamp = wp_next_scheduled( 'veng_seo_compress_sweep_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'veng_seo_compress_sweep_event' );
	}
}
register_deactivation_hook( __FILE__, 'veng_seo_deactivate' );

function veng_seo_admin_menu() {
	add_menu_page(
		'SEO Denetim',
		'SEO Denetim',
		'manage_options',
		'veng-seo-denetim',
		'veng_seo_render_page',
		'dashicons-search',
		58
	);
}
add_action( 'admin_menu', 'veng_seo_admin_menu' );

function veng_seo_enqueue( $hook ) {
	if ( 'toplevel_page_veng-seo-denetim' !== $hook ) {
		return;
	}
	wp_enqueue_script( 'veng-seo-denetim-js', plugins_url( 'admin.js', __FILE__ ), array(), '2.0.0', true );
	wp_localize_script( 'veng-seo-denetim-js', 'VengSeo', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'veng_seo_scan' ),
	) );
	wp_enqueue_style( 'veng-seo-denetim-css', plugins_url( 'admin.css', __FILE__ ), array(), '2.0.0' );
}
add_action( 'admin_enqueue_scripts', 'veng_seo_enqueue' );

function veng_seo_render_page() {
	$img_stats = veng_seo_compression_stats_cached();
	?>
	<div class="wrap veng-seo-wrap">
		<div class="veng-seo-hero">
			<div>
				<h1>🔍 SEO Denetim</h1>
				<p>Google PageSpeed Insights / Search Console / Lighthouse'un baktığı sinyalleri anlık kontrol eder. Masaüstü ve mobil ayrı taranır.</p>
			</div>
		</div>

		<div class="veng-seo-card" id="veng-seo-img-card">
			<h2>🖼️ Görsel Sıkıştırma</h2>
			<div class="veng-seo-stat-row">
				<div class="veng-seo-tile">
					<span class="veng-seo-tile-num" id="veng-seo-img-ok"><?php echo intval( $img_stats['ok'] ); ?></span>
					<span class="veng-seo-tile-label">WebP + 150KB Altında</span>
				</div>
				<div class="veng-seo-tile <?php echo $img_stats['over'] > 0 ? 'warn' : ''; ?>" id="veng-seo-img-over-tile">
					<span class="veng-seo-tile-num" id="veng-seo-img-over"><?php echo intval( $img_stats['over'] ); ?></span>
					<span class="veng-seo-tile-label">Sıkıştırma Bekliyor</span>
				</div>
				<div class="veng-seo-tile">
					<span class="veng-seo-tile-num" id="veng-seo-img-total"><?php echo intval( $img_stats['total'] ); ?></span>
					<span class="veng-seo-tile-label">Toplam Görsel</span>
				</div>
			</div>
			<p>Yeni çekilen haber görselleri otomatik WebP'ye çevrilip 150KB altına indiriliyor, ayrıca sistem her 30 dakikada bir gözden kaçanları kendiliğinden tarar. Elle de tetikleyebilirsin:</p>
			<button type="button" class="button button-primary" id="veng-seo-img-compress-btn">Eski Görselleri WebP'ye Çevir (150KB)</button>
			<span id="veng-seo-img-status" class="veng-seo-inline-status"></span>
			<?php $last_sweep = get_option( 'veng_seo_last_compress_run' ); ?>
			<?php if ( $last_sweep ) : ?>
				<p class="veng-seo-last-run">Son otomatik tarama: <strong><?php echo esc_html( $last_sweep['time'] ); ?></strong> — <?php echo intval( $last_sweep['fixed'] ); ?> görsel sıkıştırıldı<?php echo $last_sweep['remaining'] > 0 ? ', ' . intval( $last_sweep['remaining'] ) . ' kaldı' : ''; ?>.</p>
			<?php else : ?>
				<p class="veng-seo-last-run">Otomatik tarama henüz çalışmadı — ilk 30 dakika içinde çalışacak.</p>
			<?php endif; ?>
		</div>

		<div class="veng-seo-card">
			<h2>Canlı Tarama</h2>
			<button type="button" class="button button-primary button-hero" id="veng-seo-scan-btn">Şimdi Tara</button>
			<span id="veng-seo-status" style="margin-left:12px;"></span>

			<div id="veng-seo-tabs" class="veng-seo-tabs" style="display:none;">
				<button type="button" class="veng-seo-tab active" data-device="desktop">🖥️ Masaüstü</button>
				<button type="button" class="veng-seo-tab" data-device="mobile">📱 Mobil</button>
			</div>

			<div id="veng-seo-results" style="margin-top:24px;"></div>
		</div>
	</div>
	<?php
}

function veng_seo_check_row( $label, $status, $detail = '' ) {
	return array( 'label' => $label, 'status' => $status, 'detail' => $detail );
}

/**
 * Sitenin kendi kendine (loopback) attığı istekler, Cloudflare gibi bir CDN/WAF arkasındaysa
 * dışarı çıkıp geri dönmek zorunda kalır — bot koruması bunu engelleyebilir/zaman aşımına
 * uğratabilir (özellikle art arda ikinci taramada, ör. mobil sekmesi). Normal istek başarısız
 * olursa 127.0.0.1 + Host başlığıyla tekrar deneyip isteği CDN'e hiç çıkmadan doğrudan
 * sunucunun kendi web sunucusuna yönlendiriyoruz.
 */
function veng_seo_loopback_request( $method, $url, $args = array() ) {
	$args = array_merge( array( 'timeout' => 20, 'sslverify' => false ), $args );
	$fn = 'head' === $method ? 'wp_remote_head' : 'wp_remote_get';

	$res = $fn( $url, $args );
	$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
	if ( ! is_wp_error( $res ) && $code > 0 && $code < 500 ) {
		return $res;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return $res;
	}
	$local_url = ( $parts['scheme'] ?? 'https' ) . '://127.0.0.1'
		. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
		. ( $parts['path'] ?? '/' )
		. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	$args['headers'] = array_merge( (array) ( $args['headers'] ?? array() ), array( 'Host' => $parts['host'] ) );

	return $fn( $local_url, $args );
}

function veng_seo_category_score( $checks ) {
	if ( empty( $checks ) ) {
		return 0;
	}
	$sum = 0;
	foreach ( $checks as $c ) {
		$sum += ( 'pass' === $c['status'] ) ? 100 : ( 'warn' === $c['status'] ? 50 : 0 );
	}
	return (int) round( $sum / count( $checks ) );
}

function veng_seo_device_ua( $device ) {
	return 'mobile' === $device
		? 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36'
		: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
}

/** ---------------- PERFORMANS (PageSpeed Insights tarzı) ---------------- */
function veng_seo_cat_performance( $html, $headers, $elapsed_ms, $home ) {
	$checks = array();

	$checks[] = veng_seo_check_row( 'Sunucu yanıt süresi', $elapsed_ms < 800 ? 'pass' : ( $elapsed_ms < 2000 ? 'warn' : 'fail' ), $elapsed_ms . ' ms (ideal: <800ms)' );

	$size_kb = round( strlen( $html ) / 1024 );
	$checks[] = veng_seo_check_row( 'HTML sayfa boyutu', $size_kb < 100 ? 'pass' : ( $size_kb < 250 ? 'warn' : 'fail' ), $size_kb . ' KB (ideal: <100KB)' );

	$encoding = is_array( $headers ) || is_object( $headers ) ? ( $headers['content-encoding'] ?? '' ) : '';
	$checks[] = veng_seo_check_row( 'Sıkıştırma (gzip/br)', $encoding ? 'pass' : 'warn', $encoding ? "Aktif: {$encoding}" : 'Content-Encoding başlığı yok, sunucu sıkıştırma yapmıyor olabilir.' );

	$cache_control = is_array( $headers ) || is_object( $headers ) ? ( $headers['cache-control'] ?? '' ) : '';
	$checks[] = veng_seo_check_row( 'Tarayıcı önbellekleme', $cache_control ? 'pass' : 'warn', $cache_control ? "Cache-Control: {$cache_control}" : 'Cache-Control başlığı yok.' );

	preg_match_all( '/<head[^>]*>([\s\S]*?)<\/head>/i', $html, $head_m );
	$head_html = $head_m[1][0] ?? '';
	$blocking_css = preg_match_all( '/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i', $head_html );
	$blocking_js = 0;
	preg_match_all( '/<script\b([^>]*)>/i', $head_html, $script_m );
	foreach ( $script_m[1] as $attrs ) {
		if ( false !== stripos( $attrs, 'src=' ) && false === stripos( $attrs, 'defer' ) && false === stripos( $attrs, 'async' ) ) {
			$blocking_js++;
		}
	}
	$blocking_total = $blocking_css + $blocking_js;
	$checks[] = veng_seo_check_row( 'Render-engelleyen kaynaklar (head)', $blocking_total <= 2 ? 'pass' : ( $blocking_total <= 5 ? 'warn' : 'fail' ), "{$blocking_css} CSS + {$blocking_js} defer/async olmayan script <head> içinde." );

	preg_match_all( '/<img\b[^>]*>/i', $html, $imgs );
	$total_imgs = count( $imgs[0] );
	$lazy = 0;
	foreach ( $imgs[0] as $tag ) {
		if ( preg_match( '/loading=["\']lazy["\']/i', $tag ) ) {
			$lazy++;
		}
	}
	if ( $total_imgs > 3 ) {
		$pct = round( ( $lazy / $total_imgs ) * 100 );
		$checks[] = veng_seo_check_row( 'Görsel tembel yükleme (lazy load)', $pct >= 60 ? 'pass' : ( $pct >= 20 ? 'warn' : 'fail' ), "{$lazy}/{$total_imgs} görselde loading=\"lazy\" var (%{$pct})." );
	}

	// İlk harici CSS dosyasında küçültme (minification) sezgisel kontrolü.
	if ( preg_match( '/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $cssm ) ) {
		$css_url = html_entity_decode( $cssm[1], ENT_QUOTES, 'UTF-8' );
		if ( 0 === strpos( $css_url, '//' ) ) {
			$css_url = 'https:' . $css_url;
		} elseif ( 0 === strpos( $css_url, '/' ) ) {
			$css_url = rtrim( $home, '/' ) . $css_url;
		}
		$css_res = veng_seo_loopback_request( 'get', $css_url, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $css_res ) && 200 === wp_remote_retrieve_response_code( $css_res ) ) {
			$css_body = wp_remote_retrieve_body( $css_res );
			$lines = substr_count( $css_body, "\n" );
			$bytes = max( 1, strlen( $css_body ) );
			$ratio = $lines / ( $bytes / 1000 );
			$checks[] = veng_seo_check_row( 'CSS küçültme (minification)', $ratio < 5 ? 'pass' : 'warn', $ratio < 5 ? 'İlk CSS dosyası küçültülmüş görünüyor.' : 'İlk CSS dosyası küçültülmemiş olabilir (fazla satır sonu).' );
		}
	}

	return array( 'label' => 'Performans', 'checks' => $checks, 'score' => veng_seo_category_score( $checks ) );
}

/** ---------------- SEO (Search Console / Mobile-Friendly tarzı) ---------------- */
function veng_seo_cat_seo( $html, $home, $device ) {
	$checks = array();

	if ( preg_match( '/<title>(.*?)<\/title>/is', $html, $m ) ) {
		$len = mb_strlen( trim( wp_strip_all_tags( $m[1] ) ) );
		$checks[] = veng_seo_check_row( '<title> etiketi', ( $len >= 10 && $len <= 65 ) ? 'pass' : 'warn', $len . ' karakter (ideal: 10-65) — "' . trim( wp_strip_all_tags( $m[1] ) ) . '"' );
	} else {
		$checks[] = veng_seo_check_row( '<title> etiketi', 'fail', 'Bulunamadı.' );
	}

	if ( preg_match( '/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
		$len = mb_strlen( $m[1] );
		$checks[] = veng_seo_check_row( 'Meta description', ( $len >= 50 && $len <= 165 ) ? 'pass' : 'warn', $len . ' karakter (ideal: 50-165)' );
	} else {
		$checks[] = veng_seo_check_row( 'Meta description', 'fail', 'Bulunamadı.' );
	}

	$checks[] = veng_seo_check_row( 'Canonical link', preg_match( '/<link[^>]*rel=["\']canonical["\']/i', $html ) ? 'pass' : 'warn', 'Yinelenen içerik sinyalini önler.' );

	$h1_count = preg_match_all( '/<h1[^>]*>/i', $html );
	$h2_count = preg_match_all( '/<h2[^>]*>/i', $html );
	$h3_count = preg_match_all( '/<h3[^>]*>/i', $html );
	if ( 1 === $h1_count ) {
		$checks[] = veng_seo_check_row( 'H1 sayısı', 'pass', '1 adet H1 (doğru).' );
	} elseif ( 0 === $h1_count ) {
		$checks[] = veng_seo_check_row( 'H1 sayısı', 'fail', 'Sayfada H1 yok.' );
	} else {
		$checks[] = veng_seo_check_row( 'H1 sayısı', 'warn', $h1_count . ' adet H1 var, birden fazla H1 önerilmez.' );
	}
	$hierarchy_ok = ! ( $h3_count > 0 && 0 === $h2_count );
	$checks[] = veng_seo_check_row( 'Başlık hiyerarşisi (H1→H2→H3)', $hierarchy_ok ? 'pass' : 'warn', "H1:{$h1_count} H2:{$h2_count} H3:{$h3_count}" . ( $hierarchy_ok ? '' : ' — H2 atlanıp doğrudan H3 kullanılmış.' ) );

	// robots meta noindex — kritik, siteyi Google'dan tamamen gizleyebilir.
	if ( preg_match( '/<meta[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
		$has_noindex = false !== stripos( $m[1], 'noindex' );
		$checks[] = veng_seo_check_row( 'Robots meta (noindex kontrolü)', $has_noindex ? 'fail' : 'pass', $has_noindex ? 'DİKKAT: noindex var — sayfa Google\'da hiç görünmez!' : 'noindex yok, sayfa indekslenebilir.' );
	} else {
		$checks[] = veng_seo_check_row( 'Robots meta (noindex kontrolü)', 'pass', 'Robots meta yok, varsayılan indekslenebilir davranış geçerli.' );
	}

	$checks[] = veng_seo_check_row( 'HTML dil (lang) özniteliği', preg_match( '/<html[^>]*\blang=["\'][a-zA-Z-]+["\']/i', $html ) ? 'pass' : 'fail', 'Google ve ekran okuyucular için gerekli.' );

	$og_count = preg_match( '/property=["\']og:title["\']/i', $html ) + preg_match( '/property=["\']og:description["\']/i', $html ) + preg_match( '/property=["\']og:image["\']/i', $html );
	$checks[] = veng_seo_check_row( 'Open Graph etiketleri', 3 === $og_count ? 'pass' : ( $og_count > 0 ? 'warn' : 'fail' ), "{$og_count}/3 — sosyal medya paylaşım önizlemesi." );

	if ( preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>([\s\S]*?)<\/script>/i', $html, $ldm ) ) {
		$types = array();
		foreach ( $ldm[1] as $block ) {
			if ( preg_match_all( '/"@type"\s*:\s*"([^"]+)"/', $block, $tm ) ) {
				$types = array_merge( $types, $tm[1] );
			}
		}
		$types = array_unique( $types );
		$checks[] = veng_seo_check_row( 'JSON-LD (schema.org)', 'pass', implode( ', ', $types ) );
	} else {
		$checks[] = veng_seo_check_row( 'JSON-LD (schema.org)', 'fail', 'Yapısal veri bulunamadı — Google zengin sonuçlar için kullanır.' );
	}

	if ( 'mobile' === $device ) {
		$vp_ok = preg_match( '/<meta[^>]*name=["\']viewport["\'][^>]*content=["\'][^"\']*width=device-width/i', $html );
		$checks[] = veng_seo_check_row( 'Mobil viewport doğruluğu', $vp_ok ? 'pass' : 'fail', $vp_ok ? 'width=device-width mevcut.' : 'viewport eksik veya width=device-width içermiyor — Google Mobile-Friendly testinde düşer.' );
	}

	preg_match_all( '/<img\b[^>]*>/i', $html, $imgs );
	$total_imgs = count( $imgs[0] );
	$with_alt = 0;
	foreach ( $imgs[0] as $tag ) {
		if ( preg_match( '/\balt=["\'][^"\']+["\']/i', $tag ) ) {
			$with_alt++;
		}
	}
	if ( $total_imgs > 0 ) {
		$pct = round( ( $with_alt / $total_imgs ) * 100 );
		$checks[] = veng_seo_check_row( 'Görsel alt metin kapsamı', $pct >= 90 ? 'pass' : ( $pct >= 50 ? 'warn' : 'fail' ), "{$with_alt}/{$total_imgs} (%{$pct})" );
	}

	$robots = veng_seo_loopback_request( 'get', $home . 'robots.txt', array( 'timeout' => 15 ) );
	if ( ! is_wp_error( $robots ) && 200 === wp_remote_retrieve_response_code( $robots ) ) {
		$has_sitemap_ref = false !== stripos( wp_remote_retrieve_body( $robots ), 'Sitemap:' );
		$checks[] = veng_seo_check_row( 'robots.txt', $has_sitemap_ref ? 'pass' : 'warn', $has_sitemap_ref ? 'Erişilebilir, Sitemap: satırı var.' : 'Sitemap: satırı yok.' );
	} else {
		$checks[] = veng_seo_check_row( 'robots.txt', 'fail', 'Erişilemedi.' );
	}

	$sitemap = veng_seo_loopback_request( 'get', $home . 'sitemap.xml', array( 'timeout' => 15 ) );
	if ( ! is_wp_error( $sitemap ) && 200 === wp_remote_retrieve_response_code( $sitemap ) ) {
		$body = wp_remote_retrieve_body( $sitemap );
		$valid_xml = 0 === strpos( trim( $body ), '<?xml' );
		$url_count = substr_count( $body, '<loc>' );
		$checks[] = veng_seo_check_row( 'sitemap.xml', $valid_xml ? 'pass' : 'warn', $valid_xml ? "Geçerli XML, {$url_count} URL." : 'Geçerli XML gibi görünmüyor.' );
	} else {
		$checks[] = veng_seo_check_row( 'sitemap.xml', 'fail', 'Erişilemedi.' );
	}

	$missing = veng_seo_loopback_request( 'get', $home . 'bu-sayfa-kesinlikle-yok-' . time(), array( 'timeout' => 15 ) );
	if ( ! is_wp_error( $missing ) ) {
		$code = wp_remote_retrieve_response_code( $missing );
		$checks[] = veng_seo_check_row( '404 davranışı', 404 === $code ? 'pass' : 'fail', 404 === $code ? 'Doğru şekilde 404 dönüyor.' : "{$code} dönüyor (404 bekleniyordu — \"soft 404\")." );
	}

	$checks = veng_seo_content_sample_checks( $checks );
	$checks = veng_seo_broken_link_check( $checks, $home, $html );

	return array( 'label' => 'SEO', 'checks' => $checks, 'score' => veng_seo_category_score( $checks ) );
}

/** Son yayınlanan yazılardan başlık/görsel/içerik uzunluğu örneklemi (cihazdan bağımsız, DB üzerinden). */
function veng_seo_content_sample_checks( $checks ) {
	$posts = get_posts( array( 'post_type' => array( 'post', 'makale' ), 'posts_per_page' => 10, 'post_status' => 'publish' ) );
	if ( ! $posts ) {
		return $checks;
	}
	$short_title = 0;
	$no_thumb = 0;
	$no_alt = 0;
	$thin_content = 0;
	foreach ( $posts as $p ) {
		$title_len = mb_strlen( $p->post_title );
		if ( $title_len < 10 || $title_len > 70 ) {
			$short_title++;
		}
		if ( ! has_post_thumbnail( $p ) ) {
			$no_thumb++;
		} else {
			$alt = get_post_meta( get_post_thumbnail_id( $p ), '_wp_attachment_image_alt', true );
			if ( '' === trim( (string) $alt ) ) {
				$no_alt++;
			}
		}
		if ( str_word_count( wp_strip_all_tags( $p->post_content ) ) < 150 ) {
			$thin_content++;
		}
	}
	$n = count( $posts );
	$checks[] = veng_seo_check_row( "Başlık uzunluğu (son {$n} yazı)", 0 === $short_title ? 'pass' : 'warn', "{$short_title}/{$n} yazı 10-70 karakter aralığı dışında." );
	$checks[] = veng_seo_check_row( "Öne çıkan görsel (son {$n} yazı)", 0 === $no_thumb ? 'pass' : ( $no_thumb < $n / 2 ? 'warn' : 'fail' ), "{$no_thumb}/{$n} yazıda yok." );
	$checks[] = veng_seo_check_row( "Görsel alt metni (son {$n} yazı)", 0 === $no_alt ? 'pass' : 'warn', "{$no_alt}/{$n} yazıda eksik." );
	$checks[] = veng_seo_check_row( "İçerik uzunluğu (son {$n} yazı)", 0 === $thin_content ? 'pass' : 'warn', "{$thin_content}/{$n} yazı 150 kelimeden kısa (\"thin content\")." );
	return $checks;
}

function veng_seo_broken_link_check( $checks, $home, $html ) {
	$host = wp_parse_url( $home, PHP_URL_HOST );

	preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\']/i', $html, $mm );
	$links = array();
	foreach ( $mm[1] as $href ) {
		if ( 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) || 0 === strpos( $href, 'javascript:' ) ) {
			continue;
		}
		if ( 0 === strpos( $href, '/' ) ) {
			$href = rtrim( $home, '/' ) . $href;
		}
		if ( wp_parse_url( $href, PHP_URL_HOST ) !== $host ) {
			continue;
		}
		$links[ $href ] = true;
	}
	$links = array_slice( array_keys( $links ), 0, 20 );

	$broken = array();
	foreach ( $links as $link ) {
		$r = veng_seo_loopback_request( 'head', $link, array( 'timeout' => 8 ) );
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		if ( $code >= 400 || 0 === $code ) {
			$broken[] = $link . ' (' . ( $code ?: 'yanıt yok' ) . ')';
		}
	}

	if ( empty( $links ) ) {
		$checks[] = veng_seo_check_row( 'İç link kontrolü', 'warn', 'Taranacak iç link bulunamadı.' );
	} elseif ( empty( $broken ) ) {
		$checks[] = veng_seo_check_row( 'İç link kontrolü', 'pass', count( $links ) . ' iç link kontrol edildi, kırık link yok.' );
	} else {
		$checks[] = veng_seo_check_row( 'İç link kontrolü', 'fail', count( $broken ) . ' kırık link: ' . implode( ', ', array_slice( $broken, 0, 5 ) ) );
	}
	return $checks;
}

/** ---------------- ERİŞİLEBİLİRLİK (Lighthouse Accessibility tarzı) ---------------- */
function veng_seo_cat_accessibility( $html ) {
	$checks = array();

	$checks[] = veng_seo_check_row( 'HTML dil (lang) özniteliği', preg_match( '/<html[^>]*\blang=["\'][a-zA-Z-]+["\']/i', $html ) ? 'pass' : 'fail', 'Ekran okuyucular doğru telaffuz için kullanır.' );

	preg_match_all( '/<img\b[^>]*>/i', $html, $imgs );
	$total_imgs = count( $imgs[0] );
	$with_alt = 0;
	foreach ( $imgs[0] as $tag ) {
		if ( preg_match( '/\balt=["\'][^"\']*["\']/i', $tag ) ) {
			$with_alt++;
		}
	}
	if ( $total_imgs > 0 ) {
		$pct = round( ( $with_alt / $total_imgs ) * 100 );
		$checks[] = veng_seo_check_row( 'Görsellerde alt özniteliği', $pct >= 90 ? 'pass' : ( $pct >= 50 ? 'warn' : 'fail' ), "{$with_alt}/{$total_imgs} (%{$pct})" );
	}

	if ( preg_match( '/<meta[^>]*name=["\']viewport["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
		$locked = ( false !== stripos( $m[1], 'user-scalable=no' ) ) || preg_match( '/maximum-scale=\s*1(\.0*)?\b/i', $m[1] );
		$checks[] = veng_seo_check_row( 'Yakınlaştırma kilidi yok', $locked ? 'fail' : 'pass', $locked ? 'viewport yakınlaştırmayı engelliyor (user-scalable=no / maximum-scale=1) — erişilebilirlik ihlali.' : 'Kullanıcı yakınlaştırabilir.' );
	}

	$search_inputs = preg_match_all( '/<input\b[^>]*type=["\'](?:search|text)["\'][^>]*>/i', $html, $inputs );
	if ( $search_inputs > 0 ) {
		$labeled = 0;
		foreach ( $inputs[0] as $tag ) {
			if ( preg_match( '/aria-label=["\'][^"\']+["\']/i', $tag ) || preg_match( '/\bid=["\']([^"\']+)["\']/i', $tag ) ) {
				$labeled++;
			}
		}
		$checks[] = veng_seo_check_row( 'Form alanlarında etiket (aria-label/id)', $labeled === $search_inputs ? 'pass' : 'warn', "{$labeled}/{$search_inputs} input'ta aria-label veya id var." );
	}

	preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $html, $anchors );
	$generic = 0;
	$generic_words = array( 'tıkla', 'buraya', 'devamı', 'read more', 'click here', 'here' );
	foreach ( $anchors[1] as $text ) {
		$t = mb_strtolower( trim( wp_strip_all_tags( $text ) ) );
		if ( $t && in_array( $t, $generic_words, true ) ) {
			$generic++;
		}
	}
	$checks[] = veng_seo_check_row( 'Link metni açıklayıcılığı', 0 === $generic ? 'pass' : 'warn', 0 === $generic ? 'Genel geçer ("tıkla" vb.) link metni bulunamadı.' : "{$generic} link genel geçer metin kullanıyor." );

	$checks[] = veng_seo_check_row( 'Renk kontrastı', 'warn', 'Sunucu tarafında ölçülemez — tarayıcıda Lighthouse/DevTools ile manuel kontrol önerilir.' );

	return array( 'label' => 'Erişilebilirlik', 'checks' => $checks, 'score' => veng_seo_category_score( $checks ) );
}

/** ---------------- EN İYİ UYGULAMALAR (Lighthouse Best Practices tarzı) ---------------- */
function veng_seo_cat_best_practices( $html, $headers, $home, $device ) {
	$checks = array();

	$scheme = wp_parse_url( $home, PHP_URL_SCHEME );
	$checks[] = veng_seo_check_row( 'HTTPS', 'https' === $scheme ? 'pass' : 'fail', 'https' === $scheme ? 'Site HTTPS üzerinden yayında.' : 'Site HTTPS kullanmıyor.' );

	if ( 'https' === $scheme ) {
		preg_match_all( '/(?:src|href)=["\']http:\/\/[^"\']+["\']/i', $html, $mixed );
		$checks[] = veng_seo_check_row( 'Karışık içerik (mixed content)', empty( $mixed[0] ) ? 'pass' : 'fail', empty( $mixed[0] ) ? 'HTTP üzerinden yüklenen kaynak yok.' : count( $mixed[0] ) . ' kaynak http:// üzerinden yükleniyor.' );
	}

	$checks[] = veng_seo_check_row( 'DOCTYPE', 0 === stripos( trim( $html ), '<!doctype html>' ) ? 'pass' : 'warn', '' );
	$checks[] = veng_seo_check_row( 'Karakter kodlaması (charset)', preg_match( '/<meta[^>]*charset=/i', $html ) ? 'pass' : 'fail', '' );
	$checks[] = veng_seo_check_row( 'Favicon', preg_match( '/<link[^>]*rel=["\'](?:shortcut icon|icon)["\']/i', $html ) ? 'pass' : 'warn', '' );

	if ( 'mobile' === $device ) {
		$checks[] = veng_seo_check_row( 'apple-touch-icon', preg_match( '/<link[^>]*rel=["\']apple-touch-icon["\']/i', $html ) ? 'pass' : 'warn', 'iOS ana ekrana eklemede kullanılır.' );
		$checks[] = veng_seo_check_row( 'theme-color', preg_match( '/<meta[^>]*name=["\']theme-color["\']/i', $html ) ? 'pass' : 'warn', 'Mobil tarayıcı adres çubuğu rengini belirler.' );
	}

	$server_header = is_array( $headers ) || is_object( $headers ) ? ( $headers['server'] ?? '' ) : '';
	if ( $server_header ) {
		$exposes_version = (bool) preg_match( '/\/[\d.]+/', $server_header );
		$checks[] = veng_seo_check_row( 'Sunucu bilgisi ifşası', $exposes_version ? 'warn' : 'pass', $exposes_version ? "Server başlığı sürüm bilgisi veriyor: {$server_header}" : '' );
	}

	return array( 'label' => 'En İyi Uygulamalar', 'checks' => $checks, 'score' => veng_seo_category_score( $checks ) );
}

/** Bir cihaz (desktop/mobile) için anasayfayı ilgili User-Agent ile çekip 4 kategoriyi hesaplar. */
function veng_seo_run_full_scan( $device ) {
	$home = home_url( '/' );
	$start = microtime( true );
	$res = veng_seo_loopback_request( 'get', $home, array( 'timeout' => 25, 'user-agent' => veng_seo_device_ua( $device ) ) );
	$elapsed_ms = round( ( microtime( true ) - $start ) * 1000 );

	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return array( 'error' => 'Anasayfa yüklenemedi: ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) ) );
	}

	$html = wp_remote_retrieve_body( $res );
	$headers = wp_remote_retrieve_headers( $res );

	$categories = array(
		veng_seo_cat_performance( $html, $headers, $elapsed_ms, $home ),
		veng_seo_cat_seo( $html, $home, $device ),
		veng_seo_cat_accessibility( $html ),
		veng_seo_cat_best_practices( $html, $headers, $home, $device ),
	);

	$overall = 0;
	foreach ( $categories as $cat ) {
		$overall += $cat['score'];
	}
	$overall = (int) round( $overall / count( $categories ) );

	return array( 'categories' => $categories, 'overall' => $overall );
}

function veng_seo_ajax_scan() {
	check_ajax_referer( 'veng_seo_scan', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Yetkiniz yok.', 403 );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$device = ( isset( $_POST['device'] ) && 'mobile' === $_POST['device'] ) ? 'mobile' : 'desktop';
	$result = veng_seo_run_full_scan( $device );

	if ( isset( $result['error'] ) ) {
		wp_send_json_error( $result['error'] );
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_veng_seo_scan', 'veng_seo_ajax_scan' );

function veng_seo_ajax_compress_images() {
	check_ajax_referer( 'veng_seo_scan', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Yetkiniz yok.', 403 );
	}
	$r = veng_seo_backfill_compress_images();
	$stats = veng_seo_compression_stats();
	set_transient( 'veng_seo_compression_stats', $stats, 5 * MINUTE_IN_SECONDS );
	wp_send_json_success( array( 'fixed' => $r['fixed'], 'remaining' => $r['remaining'], 'stats' => $stats ) );
}
add_action( 'wp_ajax_veng_seo_compress_images', 'veng_seo_ajax_compress_images' );
