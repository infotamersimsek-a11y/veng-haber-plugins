<?php
/**
 * Stil ve script kayıtları
 */

function veng_enqueue_assets() {
	wp_enqueue_style( 'veng-google-fonts', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap', array(), null );
	wp_enqueue_style( 'veng-style', get_stylesheet_uri(), array(), VENG_THEME_VERSION );

	wp_enqueue_script( 'veng-main', VENG_THEME_URI . '/assets/main.js', array(), VENG_THEME_VERSION, true );
	wp_localize_script( 'veng-main', 'VengData', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'veng_nonce' ),
	) );

	if ( is_singular( array( 'post', 'makale' ) ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'veng_enqueue_assets' );

/**
 * Karanlık mod class'ını <html> etiketine erken uygula (flaş önleme).
 */
function veng_dark_mode_inline_script() {
	echo "<script>(function(){try{var s=localStorage.getItem('vengDarkMode');var d=s==='true'||(s===null&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',d);}catch(e){}})();</script>\n";
}
add_action( 'wp_head', 'veng_dark_mode_inline_script', 1 );

function veng_theme_color_inline_style() {
	$color = get_theme_mod( 'veng_accent_color', '#5b21b6' );
	echo '<style>:root{--theme:' . esc_attr( $color ) . ';}</style>' . "\n";
}
add_action( 'wp_head', 'veng_theme_color_inline_style', 2 );

/**
 * PWA: manifest + tema rengi + Android/iOS "ana ekrana ekle" meta etiketleri.
 * Bir Android telefonda Chrome'da siteyi açıp "Ana ekrana ekle" ile gerçek
 * uygulama gibi (kendi ikonu, adres çubuğu olmadan, offline destekli) kurulabilir.
 */
function veng_pwa_head_tags() {
	$color = get_theme_mod( 'veng_accent_color', '#5b21b6' );
	echo '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
	echo '<meta name="theme-color" content="' . esc_attr( $color ) . '">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( VENG_THEME_URI . '/assets/icon-192.png' ) . '">' . "\n";
}
add_action( 'wp_head', 'veng_pwa_head_tags', 3 );

function veng_pwa_service_worker_register() {
	?>
	<script>
	if ('serviceWorker' in navigator) {
		window.addEventListener('load', function () {
			navigator.serviceWorker.register('/service-worker.js').catch(function () {});
		});
	}
	</script>
	<?php
}
add_action( 'wp_footer', 'veng_pwa_service_worker_register' );
