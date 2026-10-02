<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="topbar">
		<span><?php echo esc_html( date_i18n( 'l, d F Y' ) ); ?></span>
		<div class="topbar-social">
			<?php foreach ( array( 'facebook' => 'Facebook', 'x' => 'X', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn' ) as $key => $label ) :
				$url = get_theme_mod( 'veng_social_' . $key );
				if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; endforeach; ?>
			<a href="<?php echo esc_url( home_url( '/wp-admin/' ) ); ?>">Yönetim Paneli</a>
		</div>
	</div>

	<div class="brandbar">
		<div style="display:flex;align-items:center;gap:4px;">
			<button type="button" class="menu-toggle" id="veng-menu-toggle" aria-expanded="false" aria-controls="veng-site-menu" aria-label="Menüyü aç">
				<span></span><span></span><span></span>
			</button>
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				$parts = explode( ' ', get_bloginfo( 'name' ), 2 );
				echo '<span>' . esc_html( $parts[0] ) . '</span>' . ( isset( $parts[1] ) ? ' ' . esc_html( $parts[1] ) : '' );
				?>
			</a>
		</div>
		<div class="header-actions">
			<button class="icon-btn" id="veng-search-toggle" aria-label="Ara">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
			</button>
			<button class="icon-btn" id="veng-dark-toggle" aria-label="Karanlık mod">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
			</button>
		</div>
	</div>

	<div id="veng-searchbox" class="searchbox" style="display:none">
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="text" name="s" aria-label="Sitede ara" placeholder="Haber, yazar, konu ara..." value="<?php echo esc_attr( get_search_query() ); ?>" />
		</form>
	</div>

	<?php veng_render_market_bar(); ?>

	<?php
	// Önceden mobilde tüm sayfayı kaplama bug'ı yüzünden kapatılmıştı — yükseklik artık
	// hem masaüstü hem mobil için kilitli (height/max-height/min-height + overflow:hidden),
	// taşma ihtimali kalmadı. Referans sitedeki yatay "SON DAKİKA" şeridiyle aynı mantık.
	veng_render_breaking_ticker();
	?>

	<?php veng_render_mobile_info_ticker(); ?>

	<?php
	// Her giriş için birden fazla aday slug: sitedeki gerçek taksonomi farklı adlandırılmış olabilir (ör. politika/siyaset).
	$veng_main_menu_defs = array(
		array( 'gundem' ),
		array( 'ekonomi' ),
		array( 'dunya' ),
		array( 'spor' ),
		array( 'yasam' ),
		array( 'siyaset', 'politika' ),
		array( 'teknoloji' ),
		array( 'saglik', 'saglik-haberleri' ),
	);
	$veng_nav_cats = array();
	foreach ( $veng_main_menu_defs as $veng_slug_candidates ) {
		$cat = null;
		foreach ( $veng_slug_candidates as $veng_slug ) {
			$cat = get_category_by_slug( $veng_slug );
			if ( $cat ) break;
		}
		if ( $cat ) $veng_nav_cats[] = $cat;
	}
	?>
	<nav class="categorynav">
		<?php foreach ( $veng_nav_cats as $cat ) : ?>
			<?php if ( 'gundem' === $cat->slug ) : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Veng Haber</a>
			<?php else : ?>
				<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
</header>

<div class="menu-overlay" id="veng-menu-overlay" hidden></div>
<nav class="site-menu" id="veng-site-menu" aria-hidden="true">
	<div class="site-menu-head">
		<span class="site-menu-title">Kategoriler</span>
		<button type="button" class="menu-close" id="veng-menu-close" aria-label="Menüyü kapat">&times;</button>
	</div>
	<ul class="site-menu-list">
		<li class="site-menu-item"><div class="site-menu-row"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Ana Sayfa</a></div></li>
		<?php foreach ( $veng_nav_cats as $cat ) : ?>
			<?php if ( 'gundem' === $cat->slug ) : ?>
				<li class="site-menu-item"><div class="site-menu-row"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Veng Haber</a></div></li>
			<?php else : ?>
				<li class="site-menu-item"><div class="site-menu-row"><a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></div></li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
</nav>
