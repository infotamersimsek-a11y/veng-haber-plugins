<?php
/**
 * Veng Haber tema fonksiyonları
 */

// Dosya değişim zamanına göre otomatik değişir — tarayıcı/host CSS'i eskisi gibi önbellekte tutamaz.
define( 'VENG_THEME_VERSION', (string) @filemtime( __DIR__ . '/style.css' ) ?: '1.0.' . time() );
define( 'VENG_THEME_DIR', get_template_directory() );
define( 'VENG_THEME_URI', get_template_directory_uri() );

// Otomatik güncelleme: GitHub'daki paylaşılan depoyu kontrol eder, "Güncelleme mevcut" bildirimini
// wp-admin'de gösterir — artık zip indirip elle yüklemeye gerek yok.
if ( file_exists( __DIR__ . '/puc/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/puc/plugin-update-checker.php';
	$veng_tema_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/infotamersimsek-a11y/veng-haber-plugins/',
		__FILE__,
		'veng'
	);
	$veng_tema_update_checker->setBranch( 'main' );
	$veng_tema_update_checker->getVcsApi()->enableReleaseAssets( '/^veng-tema\.zip$/' );
}

require VENG_THEME_DIR . '/inc/setup.php';
require VENG_THEME_DIR . '/inc/enqueue.php';
require VENG_THEME_DIR . '/inc/cpt.php';
require VENG_THEME_DIR . '/inc/taxonomies.php';
require VENG_THEME_DIR . '/inc/meta-boxes.php';
require VENG_THEME_DIR . '/inc/customizer.php';
require VENG_THEME_DIR . '/inc/seo.php';
require VENG_THEME_DIR . '/inc/ajax.php';
require VENG_THEME_DIR . '/inc/important-days.php';
require VENG_THEME_DIR . '/inc/hourly-digest.php';
require VENG_THEME_DIR . '/inc/helpers.php';
require VENG_THEME_DIR . '/inc/widgets-area.php';
require VENG_THEME_DIR . '/inc/internal-links.php';
