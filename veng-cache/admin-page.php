<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function veng_cache_admin_menu() {
	add_menu_page( 'Veng Cache', 'Veng Cache', 'manage_options', 'veng-cache', 'veng_cache_render_page', 'dashicons-performance', 59 );
}
add_action( 'admin_menu', 'veng_cache_admin_menu' );

function veng_cache_admin_enqueue( $hook ) {
	if ( 'toplevel_page_veng-cache' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'veng-cache-admin', VENG_CACHE_URI . '/admin.css', array(), '2.0.0' );
	wp_enqueue_script( 'veng-cache-admin', VENG_CACHE_URI . '/admin.js', array(), '2.0.0', true );
	wp_localize_script( 'veng-cache-admin', 'VengCache', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'veng_cache_admin' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'veng_cache_admin_enqueue' );

function veng_cache_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled = veng_cache_enabled();
	$ttl = veng_cache_ttl();
	$stats = veng_cache_stats();
	$db = veng_cache_db_counts();
	$db_total = array_sum( $db );
	$gzip_available = extension_loaded( 'zlib' );
	?>
	<div class="wrap veng-cache-wrap">
		<div class="veng-cache-hero">
			<div>
				<h1>⚡ Veng Cache</h1>
				<p>Tam sayfa önbellekleme, gzip sıkıştırma ve veritabanı temizliğiyle siteyi hızlandırır.</p>
			</div>
			<div class="veng-cache-hero-badge <?php echo $enabled ? 'on' : 'off'; ?>" id="veng-cache-status-badge">
				<?php echo $enabled ? '● Aktif' : '○ Kapalı'; ?>
			</div>
		</div>

		<div class="veng-cache-stat-row">
			<div class="veng-cache-tile">
				<span class="veng-cache-tile-num" id="vc-stat-count"><?php echo intval( $stats['count'] ); ?></span>
				<span class="veng-cache-tile-label">Önbellekteki Sayfa</span>
			</div>
			<div class="veng-cache-tile">
				<span class="veng-cache-tile-num" id="vc-stat-size"><?php echo esc_html( size_format( $stats['bytes'] ) ); ?></span>
				<span class="veng-cache-tile-label">Disk Kullanımı</span>
			</div>
			<div class="veng-cache-tile">
				<span class="veng-cache-tile-num"><?php echo $gzip_available ? '✓' : '✗'; ?></span>
				<span class="veng-cache-tile-label">Gzip Sıkıştırma</span>
			</div>
			<div class="veng-cache-tile">
				<span class="veng-cache-tile-num"><?php echo intval( $db_total ); ?></span>
				<span class="veng-cache-tile-label">Temizlenebilir Kayıt</span>
			</div>
		</div>

		<div class="veng-cache-tabs">
			<button type="button" class="veng-cache-tab active" data-tab="genel">🏠 Genel</button>
			<button type="button" class="veng-cache-tab" data-tab="veritabani">🗄️ Veritabanı Temizliği</button>
			<button type="button" class="veng-cache-tab" data-tab="istatistik">📊 İstatistikler</button>
		</div>

		<div class="veng-cache-panel" data-panel="genel">
			<div class="veng-cache-card">
				<h2>Önbellek Ayarları</h2>
				<label class="veng-cache-toggle-row">
					<input type="checkbox" id="vc-enabled" <?php checked( $enabled ); ?> />
					<span>Önbelleklemeyi etkinleştir</span>
				</label>
				<div class="veng-cache-field">
					<label for="vc-ttl">Önbellek süresi (saniye)</label>
					<input type="number" id="vc-ttl" value="<?php echo esc_attr( $ttl ); ?>" min="60" step="60" />
					<p class="description">Bu süre dolunca sayfa yeniden oluşturulur. Yeni haber yayınlanınca zaten anında temizleniyor, bu sadece güvenlik payı.</p>
				</div>
				<button type="button" class="button button-primary" id="vc-save-settings">Ayarları Kaydet</button>
				<span id="vc-save-status" class="veng-cache-inline-status"></span>
			</div>

			<div class="veng-cache-card">
				<h2>Önbellek</h2>
				<p><strong id="vc-clear-count"><?php echo intval( $stats['count'] ); ?></strong> sayfa önbellekte, <strong id="vc-clear-size"><?php echo esc_html( size_format( $stats['bytes'] ) ); ?></strong>.</p>
				<button type="button" class="button button-hero veng-cache-danger" id="vc-clear-cache">🗑️ Önbelleği Şimdi Temizle</button>
				<span id="vc-clear-status" class="veng-cache-inline-status"></span>
			</div>
		</div>

		<div class="veng-cache-panel" data-panel="veritabani" style="display:none;">
			<div class="veng-cache-card">
				<h2>Veritabanı Temizliği</h2>
				<p>Kullanılmayan kayıtlar veritabanını şişirip sorguları yavaşlatır. Neyi temizleyeceğini seç:</p>
				<div class="veng-cache-db-list">
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="revisions" checked /> <span>Yazı Revizyonları</span> <strong id="vc-db-revisions"><?php echo intval( $db['revisions'] ); ?></strong></label>
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="auto_drafts" checked /> <span>Otomatik Taslaklar</span> <strong id="vc-db-auto_drafts"><?php echo intval( $db['auto_drafts'] ); ?></strong></label>
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="trashed_posts" checked /> <span>Çöpteki Yazılar</span> <strong id="vc-db-trashed_posts"><?php echo intval( $db['trashed_posts'] ); ?></strong></label>
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="spam_comments" checked /> <span>Spam Yorumlar</span> <strong id="vc-db-spam_comments"><?php echo intval( $db['spam_comments'] ); ?></strong></label>
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="trashed_comments" checked /> <span>Çöpteki Yorumlar</span> <strong id="vc-db-trashed_comments"><?php echo intval( $db['trashed_comments'] ); ?></strong></label>
					<label class="veng-cache-db-row"><input type="checkbox" name="vc-db-item" value="expired_transients" checked /> <span>Süresi Dolmuş Geçici Veriler</span> <strong id="vc-db-expired_transients"><?php echo intval( $db['expired_transients'] ); ?></strong></label>
				</div>
				<button type="button" class="button button-hero veng-cache-danger" id="vc-db-cleanup">🧹 Seçilenleri Temizle</button>
				<span id="vc-db-status" class="veng-cache-inline-status"></span>
			</div>
		</div>

		<div class="veng-cache-panel" data-panel="istatistik" style="display:none;">
			<div class="veng-cache-card">
				<h2>Önbellekteki Son Sayfalar</h2>
				<?php if ( empty( $stats['recent'] ) ) : ?>
					<p>Henüz önbellekte sayfa yok.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th>Boyut</th><th>Yaş</th></tr></thead>
						<tbody>
						<?php foreach ( $stats['recent'] as $f ) : ?>
							<tr><td><?php echo esc_html( size_format( $f['size'] ) ); ?></td><td><?php echo esc_html( human_time_diff( time() - $f['age'], time() ) ); ?> önce</td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}
