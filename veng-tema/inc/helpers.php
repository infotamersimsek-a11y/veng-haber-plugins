<?php
/**
 * Şablon yardımcıları: kartlar, hava durumu, piyasa verisi, paylaşım, sayfalama
 */

function veng_time_ago( $timestamp ) {
	$diff = time() - $timestamp;
	if ( $diff < 60 ) return 'az önce';
	if ( $diff < 3600 ) return floor( $diff / 60 ) . ' dk önce';
	if ( $diff < 86400 ) return floor( $diff / 3600 ) . ' sa önce';
	if ( $diff < 7 * 86400 ) return floor( $diff / 86400 ) . ' gün önce';
	return date_i18n( 'd F Y', $timestamp );
}

function veng_post_link( $post_id ) {
	return get_permalink( $post_id );
}

function veng_render_hcard( $post_id ) {
	$cats = get_the_category( $post_id );
	?>
	<a class="hcard" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<div class="hcard-thumb"><?php echo get_the_post_thumbnail( $post_id, 'veng-thumb', array( 'loading' => 'lazy' ) ); ?></div>
		<div>
			<?php if ( $cats ) : ?><span class="cat"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
			<h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
			<div class="meta"><?php echo esc_html( veng_time_ago( get_post_time( 'U', false, $post_id ) ) ); ?></div>
		</div>
	</a>
	<?php
}

function veng_render_gcard( $post_id ) {
	$cats = get_the_category( $post_id );
	?>
	<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<div class="gcard-thumb"><?php echo get_the_post_thumbnail( $post_id, 'veng-card', array( 'loading' => 'lazy' ) ); ?></div>
		<?php if ( $cats ) : ?><span class="cat" style="color:var(--theme);font-size:11px;font-weight:700;"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
		<h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
		<div class="meta" style="font-size:12px;color:var(--muted);"><?php echo esc_html( veng_time_ago( get_post_time( 'U', false, $post_id ) ) ); ?></div>
	</a>
	<?php
}

function veng_render_compact( $post_id, $index = null ) {
	?>
	<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php if ( $index ) : ?><span class="idx"><?php echo intval( $index ); ?></span><?php endif; ?>
		<span><?php echo esc_html( get_the_title( $post_id ) ); ?></span>
	</a>
	<?php
}

/** Hava durumu (Open-Meteo, ücretsiz, key gerektirmez) */
function veng_get_weather( $city = null ) {
	$city = $city ?: get_theme_mod( 'veng_weather_city', 'Diyarbakır' );
	$cache_key = 'veng_weather_' . md5( $city );
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$geo = wp_remote_get( 'https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode( $city ) . '&count=1&language=tr&format=json', array( 'timeout' => 5 ) );
	if ( is_wp_error( $geo ) ) return null;
	$geo_data = json_decode( wp_remote_retrieve_body( $geo ), true );
	if ( empty( $geo_data['results'][0] ) ) return null;
	$loc = $geo_data['results'][0];

	$fc = wp_remote_get( "https://api.open-meteo.com/v1/forecast?latitude={$loc['latitude']}&longitude={$loc['longitude']}&current=temperature_2m,weather_code&daily=temperature_2m_max,temperature_2m_min&timezone=auto", array( 'timeout' => 5 ) );
	if ( is_wp_error( $fc ) ) return null;
	$fc_data = json_decode( wp_remote_retrieve_body( $fc ), true );
	if ( empty( $fc_data['current'] ) ) return null;

	$codes = array(
		0 => array( 'Açık', '☀️' ), 1 => array( 'Az Bulutlu', '🌤️' ), 2 => array( 'Parçalı Bulutlu', '⛅' ), 3 => array( 'Kapalı', '☁️' ),
		45 => array( 'Sisli', '🌫️' ), 61 => array( 'Hafif Yağmurlu', '🌧️' ), 63 => array( 'Yağmurlu', '🌧️' ), 71 => array( 'Karlı', '❄️' ), 95 => array( 'Gök Gürültülü', '⛈️' ),
	);
	$code = $fc_data['current']['weather_code'];
	$desc = $codes[ $code ] ?? array( '—', '🌡️' );

	$result = array(
		'city'  => $city,
		'temp'  => round( $fc_data['current']['temperature_2m'] ),
		'max'   => round( $fc_data['daily']['temperature_2m_max'][0] ),
		'min'   => round( $fc_data['daily']['temperature_2m_min'][0] ),
		'label' => $desc[0],
		'icon'  => $desc[1],
	);
	set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
	return $result;
}

/** Piyasa verisi (USD/EUR ücretsiz API, altın/BIST için opsiyonel ücretli key .env benzeri wp-config sabitleri ile) */
function veng_get_market_rates() {
	$cache_key = 'veng_market_rates';
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$rates = array();

	$fx = wp_remote_get( 'https://open.er-api.com/v6/latest/USD', array( 'timeout' => 5 ) );
	if ( ! is_wp_error( $fx ) ) {
		$fx_data = json_decode( wp_remote_retrieve_body( $fx ), true );
		if ( ! empty( $fx_data['rates']['TRY'] ) && ! empty( $fx_data['rates']['EUR'] ) ) {
			$usd_try = $fx_data['rates']['TRY'];
			$eur_try = $usd_try / $fx_data['rates']['EUR'];
			$rates[] = array( 'label' => 'USD/TRY', 'value' => number_format( $usd_try, 2 ), 'live' => true );
			$rates[] = array( 'label' => 'EUR/TRY', 'value' => number_format( $eur_try, 2 ), 'live' => true );
		}
	}
	if ( count( $rates ) < 2 ) {
		$rates = array(
			array( 'label' => 'USD/TRY', 'value' => '—', 'live' => false ),
			array( 'label' => 'EUR/TRY', 'value' => '—', 'live' => false ),
		);
	}

	$btc = wp_remote_get( 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=try', array( 'timeout' => 5 ) );
	$btc_val = '—'; $btc_live = false;
	if ( ! is_wp_error( $btc ) ) {
		$btc_data = json_decode( wp_remote_retrieve_body( $btc ), true );
		if ( ! empty( $btc_data['bitcoin']['try'] ) ) {
			$btc_val = '₺' . number_format( $btc_data['bitcoin']['try'], 0, ',', '.' );
			$btc_live = true;
		}
	}
	$rates[] = array( 'label' => 'Bitcoin', 'value' => $btc_val, 'live' => $btc_live );

	// Gerçek zamanlı Gram Altın / BIST 100 için ücretli API key eklenebilir: wp-config.php içine
	// define('VENG_GOLD_API_KEY', '...'); tanımlayıp burada goldapi.io benzeri servise bağlanabilirsiniz.
	if ( defined( 'VENG_GOLD_API_KEY' ) && VENG_GOLD_API_KEY ) {
		$gold = wp_remote_get( 'https://www.goldapi.io/api/XAU/TRY', array( 'timeout' => 5, 'headers' => array( 'x-access-token' => VENG_GOLD_API_KEY ) ) );
		$gold_data = ! is_wp_error( $gold ) ? json_decode( wp_remote_retrieve_body( $gold ), true ) : null;
		if ( ! empty( $gold_data['price'] ) ) {
			$rates[] = array( 'label' => 'Gram Altın', 'value' => '₺' . number_format( $gold_data['price'] / 31.1, 0, ',', '.' ), 'live' => true );
		} else {
			$rates[] = array( 'label' => 'Gram Altın', 'value' => '₺2.850 (demo)', 'live' => false );
		}
	} else {
		$rates[] = array( 'label' => 'Gram Altın', 'value' => '₺2.850 (demo)', 'live' => false );
	}
	$rates[] = array( 'label' => 'BIST 100', 'value' => '10.245 (demo)', 'live' => false );

	set_transient( $cache_key, $rates, 15 * MINUTE_IN_SECONDS );
	return $rates;
}

/** Üst bardaki kayan "Son Dakika" şeridi: en yeni haberler + döviz/altın. */
function veng_render_breaking_ticker() {
	$posts = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 8, 'orderby' => 'date', 'order' => 'DESC' ) );
	if ( ! $posts ) return;

	$rates = veng_get_market_rates();
	$rate_labels = array();
	foreach ( $rates as $r ) {
		if ( in_array( $r['label'], array( 'USD/TRY', 'EUR/TRY', 'Gram Altın' ), true ) ) {
			$rate_labels[] = $r['label'] . ' ' . $r['value'];
		}
	}

	$items = array();
	foreach ( $posts as $p ) {
		$items[] = '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>';
	}
	foreach ( $rate_labels as $rl ) {
		$items[] = '<span class="ticker-rate">' . esc_html( $rl ) . '</span>';
	}
	$track = implode( '<span class="ticker-sep">●</span>', $items );
	?>
	<div class="breaking-ticker">
		<span class="breaking-label">Son Dakika</span>
		<div class="ticker-viewport">
			<div class="ticker-track">
				<span class="ticker-group"><?php echo $track; ?></span>
				<span class="ticker-group" aria-hidden="true"><?php echo $track; ?></span>
			</div>
		</div>
	</div>
	<?php
}

/** Mobilde hava durumu + piyasaları yatay kayan tek şerit halinde gösterir (sadece mobil, CSS ile). */
function veng_render_mobile_info_ticker() {
	$weather = veng_get_weather();
	$rates = veng_get_market_rates();

	$items = array();
	if ( $weather ) {
		$items[] = '<span class="ticker-rate">' . esc_html( $weather['icon'] . ' ' . $weather['city'] . ' ' . $weather['temp'] . '°' ) . '</span>';
	}
	foreach ( $rates as $r ) {
		$items[] = '<span class="ticker-rate">' . esc_html( $r['label'] . ' ' . $r['value'] ) . '</span>';
	}
	if ( ! $items ) return;

	$track = implode( '<span class="ticker-sep">●</span>', $items );
	?>
	<div class="mobile-info-ticker">
		<div class="ticker-viewport">
			<div class="ticker-track">
				<span class="ticker-group"><?php echo $track; ?></span>
				<span class="ticker-group" aria-hidden="true"><?php echo $track; ?></span>
			</div>
		</div>
	</div>
	<?php
}

/** Diyarbakır, Van, Mardin, Batman, Şırnak, Siirt, Hakkari, Bitlis — bölge hava durumu. */
function veng_render_region_weather() {
	$cities = array( 'Diyarbakır', 'Van', 'Mardin', 'Batman', 'Şırnak', 'Siirt', 'Hakkari', 'Bitlis' );
	?>
	<div class="card">
		<div class="widget-title">BÖLGE HAVA DURUMU</div>
		<div class="region-weather-list">
			<?php foreach ( $cities as $city ) :
				$w = veng_get_weather( $city );
				if ( ! $w ) continue;
				?>
				<div class="region-weather-row">
					<span><?php echo esc_html( $w['icon'] ); ?> <?php echo esc_html( $city ); ?></span>
					<strong><?php echo esc_html( $w['temp'] ); ?>°</strong>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/** Gerçek reklam gelene kadar geçici olarak devre dışı — boş kutu görünmesin diye hiçbir şey basmıyor. */
function veng_ad_slot( $label = 'Reklam Alanı', $height = 100 ) {
	return;
}

function veng_render_sidebar() {
	$weather = veng_get_weather();
	$rates = veng_get_market_rates();
	?>
	<aside style="display:flex;flex-direction:column;gap:20px;">
		<?php veng_render_hourly_digest_widget(); ?>

		<?php if ( $weather ) : ?>
		<div class="card sidebar-hide-mobile">
			<div class="widget-title">HAVA DURUMU</div>
			<div class="weather-row">
				<div>
					<div style="font-weight:700;"><?php echo esc_html( $weather['city'] ); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo esc_html( $weather['label'] ); ?></div>
				</div>
				<div style="display:flex;align-items:center;gap:8px;">
					<span style="font-size:24px;"><?php echo esc_html( $weather['icon'] ); ?></span>
					<span style="font-size:24px;font-weight:800;"><?php echo esc_html( $weather['temp'] ); ?>°</span>
				</div>
			</div>
			<div style="font-size:12px;color:var(--muted);margin-top:8px;">Yüksek <?php echo esc_html( $weather['max'] ); ?>° · Düşük <?php echo esc_html( $weather['min'] ); ?>°</div>
		</div>
		<?php endif; ?>

		<?php veng_render_region_weather(); ?>

		<div class="card sidebar-hide-mobile">
			<div class="widget-title">PİYASALAR</div>
			<?php foreach ( $rates as $r ) : ?>
				<div class="market-row"><span style="color:var(--muted);"><?php echo esc_html( $r['label'] ); ?></span><span style="font-weight:700;"><?php echo esc_html( $r['value'] ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<?php
		// Trend Haberler: elle etiketleme değil, gerçek görüntülenme sayısına göre (son 14 gün) otomatik hesaplanır.
		$trend_q = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 5,
			'date_query'     => array( array( 'after' => '14 days ago' ) ),
			'meta_key'       => '_veng_views',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		) );
		if ( ! $trend_q->have_posts() ) {
			// Son 14 günde görüntülenmiş haber yoksa (yeni kurulum), tüm zamanların en çok görüntülenenine düş.
			$trend_q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 5, 'meta_key' => '_veng_views', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );
		}
		if ( $trend_q->have_posts() ) : ?>
		<div class="card">
			<div class="widget-title">TREND HABERLER</div>
			<div class="compact-list">
				<?php $i = 1; while ( $trend_q->have_posts() ) : $trend_q->the_post(); veng_render_compact( get_the_ID(), $i++ ); endwhile; ?>
			</div>
		</div>
		<?php endif; wp_reset_postdata(); ?>

		<?php
		$pick_q = new WP_Query( array( 'post_type' => array( 'post', 'makale' ), 'posts_per_page' => 5, 'tax_query' => array( array( 'taxonomy' => 'rozet', 'field' => 'slug', 'terms' => 'editorun-secimi' ) ) ) );
		if ( $pick_q->have_posts() ) : ?>
		<div class="card">
			<div class="widget-title">EDİTÖRÜN SEÇTİKLERİ</div>
			<div class="compact-list">
				<?php while ( $pick_q->have_posts() ) : $pick_q->the_post(); veng_render_compact( get_the_ID() ); endwhile; ?>
			</div>
		</div>
		<?php endif; wp_reset_postdata(); ?>

		<?php
		$poll_q = new WP_Query( array( 'post_type' => 'anket', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( $poll_q->have_posts() ) : $poll_q->the_post(); ?>
			<?php get_template_part( 'template-parts/poll-widget', null, array( 'poll_id' => get_the_ID() ) ); ?>
		<?php endif; wp_reset_postdata(); ?>

		<?php
		$firma_q = new WP_Query( array( 'post_type' => 'firma', 'posts_per_page' => 4, 'orderby' => 'rand' ) );
		if ( $firma_q->have_posts() ) : ?>
		<div class="card">
			<div class="widget-title">FİRMA REHBERİ</div>
			<div class="compact-list">
				<?php while ( $firma_q->have_posts() ) : $firma_q->the_post(); ?>
					<a href="<?php the_permalink(); ?>"><span><?php the_title(); ?></span></a>
				<?php endwhile; ?>
			</div>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'firma' ) ); ?>" style="display:block;text-align:center;font-size:12px;font-weight:700;margin-top:10px;color:var(--theme);">Tümünü Gör →</a>
		</div>
		<?php endif; wp_reset_postdata(); ?>

		<?php veng_render_important_days_widget(); ?>
		<?php veng_render_radio_widget(); ?>
		<?php veng_render_social_widget(); ?>
	</aside>
	<?php
}

/** Takvim / önemli günler kartı: bugün varsa onu, yoksa en yakın önemli günü gösterir. */
function veng_render_important_days_widget() {
	$today_day = veng_find_todays_important_day();
	$next_day = $today_day ? null : veng_find_next_important_day();
	?>
	<div class="card">
		<div class="widget-title">TAKVİM · ÖNEMLİ GÜNLER</div>
		<div class="important-day-date"><?php echo esc_html( date_i18n( 'd F Y, l' ) ); ?></div>
		<?php if ( $today_day ) : ?>
			<div class="important-day-box">
				<strong><?php echo esc_html( $today_day['title'] ); ?></strong>
				<?php if ( ! empty( $today_day['desc'] ) ) : ?><p><?php echo esc_html( $today_day['desc'] ); ?></p><?php endif; ?>
			</div>
		<?php elseif ( $next_day ) : ?>
			<div class="important-day-box important-day-upcoming">
				<span class="muted-label">Yaklaşan</span>
				<strong><?php echo esc_html( $next_day['title'] ); ?></strong>
				<span class="days-left"><?php echo esc_html( $next_day['days_left'] ); ?> gün sonra</span>
			</div>
		<?php else : ?>
			<p style="font-size:13px;color:var(--muted);">Bugün için özel bir gün bulunmuyor.</p>
		<?php endif; ?>
	</div>
	<?php
}

/** Veng Radyo yapısal yer tutucusu (gerçek yayın entegre edildiğinde doldurulur). */
function veng_render_radio_widget() {
	?>
	<div class="card radio-widget">
		<div class="widget-title">VENG RADYO</div>
		<div class="radio-player">
			<button type="button" class="radio-play-btn" aria-label="Veng Radyo dinle">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7-11-7Z"/></svg>
			</button>
			<div>
				<div style="font-weight:700;font-size:13px;">Canlı Yayın</div>
				<div style="font-size:11px;color:var(--muted);">Haberler · Müzik · Söyleşi (demo)</div>
			</div>
			<span class="radio-live-dot" aria-hidden="true"></span>
		</div>
	</div>
	<?php
}

/** Sosyal medya hesapları: Özelleştir → Site Ayarları altında tanımlı linkleri gösterir. */
function veng_render_social_widget() {
	$accounts = array(
		'facebook'  => array(
			'label' => 'Facebook',
			'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94Z"/></svg>',
		),
		'x'         => array(
			'label' => 'X',
			'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.24 2.75h3.06l-6.69 7.64 7.87 10.86h-6.16l-4.82-6.55-5.52 6.55H2.9l7.16-8.17L2.5 2.75h6.32l4.36 5.99 5.06-5.99Z"/></svg>',
		),
		'instagram' => array(
			'label' => 'Instagram',
			'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153.509.5.902 1.105 1.153 1.772.247.637.415 1.363.465 2.428.048 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 0 1-1.153 1.772c-.5.508-1.105.902-1.772 1.153-.637.247-1.363.415-2.428.465-1.066.048-1.405.06-4.122.06-2.717 0-3.056-.012-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 0 1-1.772-1.153 4.904 4.904 0 0 1-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 0 1 1.153-1.772A4.897 4.897 0 0 1 5.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 1.802c-2.67 0-2.986.01-4.04.059-.976.045-1.505.207-1.858.344-.466.181-.8.399-1.15.748-.35.35-.567.683-.748 1.15-.137.353-.3.882-.344 1.857-.048 1.055-.058 1.37-.058 4.04 0 2.67.01 2.986.058 4.04.045.976.207 1.505.344 1.858.181.466.399.8.748 1.15.35.35.683.567 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.04.058 2.67 0 2.987-.01 4.04-.058.976-.045 1.505-.207 1.858-.344.466-.181.8-.399 1.15-.748.35-.35.567-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.054.058-1.37.058-4.04 0-2.67-.01-2.987-.058-4.04-.045-.976-.207-1.505-.344-1.858a3.09 3.09 0 0 0-.748-1.15 3.09 3.09 0 0 0-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.055-.048-1.37-.058-4.04-.058zm0 4.594a5.604 5.604 0 1 1 0 11.208A5.604 5.604 0 0 1 12 8.396zm0 1.802a3.802 3.802 0 1 0 0 7.604 3.802 3.802 0 0 0 0-7.604zm5.845-1.996a1.31 1.31 0 1 1-2.62 0 1.31 1.31 0 0 1 2.62 0z"/></svg>',
		),
		'linkedin'  => array(
			'label' => 'LinkedIn',
			'icon'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 1 1 0-4.124 2.062 2.062 0 0 1 0 4.124zM7.119 20.452H3.554V9h3.565v11.452z"/></svg>',
		),
	);
	$has_any = false;
	foreach ( $accounts as $key => $a ) {
		if ( get_theme_mod( 'veng_social_' . $key ) ) {
			$has_any = true;
			break;
		}
	}
	?>
	<div class="card">
		<div class="widget-title">SOSYAL MEDYA</div>
		<div class="social-icons-row">
			<?php foreach ( $accounts as $key => $a ) :
				$url = get_theme_mod( 'veng_social_' . $key ) ?: home_url( '/' );
				?>
				<a class="social-icon-btn" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $a['label'] ); ?>"><?php echo $a['icon']; ?></a>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $has_any ) : ?>
			<p style="font-size:11px;color:var(--muted);margin-top:8px;">Hesaplar Görünüm → Özelleştir → Site Ayarları'ndan eklenebilir.</p>
		<?php endif; ?>
	</div>
	<?php
}

function veng_share_buttons( $url = '', $title = '', $show_label = true ) {
	$url = $url ?: get_permalink();
	$title = $title ?: get_the_title();
	?>
	<div class="share-buttons">
		<?php if ( $show_label ) : ?><span style="font-size:11px;font-weight:800;color:var(--muted);margin-right:4px;">PAYLAŞ</span><?php endif; ?>
		<a href="https://wa.me/?text=<?php echo rawurlencode( $title . ' ' . $url ); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.33 4.95L2 22l5.29-1.39a9.9 9.9 0 0 0 4.75 1.21h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Z"/></svg>
		</a>
		<a href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( $title ); ?>&url=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener" aria-label="X">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.24 2.75h3.06l-6.69 7.64 7.87 10.86h-6.16l-4.82-6.55-5.52 6.55H2.9l7.16-8.17L2.5 2.75h6.32l4.36 5.99 5.06-5.99Z"/></svg>
		</a>
		<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $url ); ?>" target="_blank" rel="noopener" aria-label="Facebook">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94Z"/></svg>
		</a>
	</div>
	<?php
}

/** Kompakt sayfalama: 1 2 3 ... 70 yerine 1 2 3 … 12 13 … Sonraki → şeklinde. */
function veng_pagination() {
	global $wp_query;
	$total = (int) $wp_query->max_num_pages;
	if ( $total <= 1 ) {
		return;
	}
	$current = max( 1, (int) get_query_var( 'paged' ) );

	// Gösterilecek sayfa numaraları: ilk, son, mevcutun ±1 komşusu.
	$show = array( 1, $total, $current );
	for ( $i = $current - 1; $i <= $current + 1; $i++ ) {
		if ( $i >= 1 && $i <= $total ) {
			$show[] = $i;
		}
	}
	$show = array_unique( $show );
	sort( $show );

	echo '<div class="pagination">';

	if ( $current > 1 ) {
		echo '<a href="' . esc_url( get_pagenum_link( $current - 1 ) ) . '" class="pagination-nav">← Önceki</a>';
	}

	$prev = 0;
	foreach ( $show as $page_num ) {
		if ( $prev && $page_num - $prev > 1 ) {
			echo '<span class="pagination-gap">…</span>';
		}
		if ( $page_num === $current ) {
			echo '<span class="current">' . $page_num . '</span>';
		} else {
			echo '<a href="' . esc_url( get_pagenum_link( $page_num ) ) . '">' . $page_num . '</a>';
		}
		$prev = $page_num;
	}

	if ( $current < $total ) {
		echo '<a href="' . esc_url( get_pagenum_link( $current + 1 ) ) . '" class="pagination-nav">Sonraki →</a>';
	}

	echo '</div>';
}
