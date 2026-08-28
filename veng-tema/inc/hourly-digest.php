<?php
/**
 * Sidebar: "Bugün Ne Oldu" — bugün yayınlanan haberlerden en son 5 tanesini
 * madde madde, tek cümlelik özetlerle listeler. Başlığa tıklayınca habere gider.
 * Sonuç 10 dakika önbelleklenir; ziyaretçi "Şimdi Tara" ile anlık yeniden üretim
 * tetikleyebilir (kötüye kullanmayı önlemek için kısa bir bekleme kilidi var).
 */

function veng_hourly_digest_posts() {
	$today = getdate( current_time( 'timestamp' ) );
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'date_query'     => array( array(
			'year'  => $today['year'],
			'month' => $today['mon'],
			'day'   => $today['mday'],
		) ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	if ( ! $posts ) {
		$posts = get_posts( array(
			'post_type'      => array( 'post', 'makale' ),
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
	}
	return $posts;
}

/** Her haber için tek cümlelik, kısa öz bir satır üretir (mümkünse AI ile, yoksa özetten). */
function veng_hourly_digest_blurbs( $posts ) {
	$fallback = array();
	foreach ( $posts as $p ) {
		$fallback[] = wp_trim_words( wp_strip_all_tags( has_excerpt( $p ) ? get_the_excerpt( $p ) : $p->post_content ), 14, '…' );
	}

	$api_key = get_option( 'veng_oh_anthropic_api_key' );
	if ( ! $api_key ) {
		return $fallback;
	}

	$lines = array();
	foreach ( $posts as $i => $p ) {
		$lines[] = ( $i + 1 ) . '. Başlık: "' . $p->post_title . '" — Özet: "' . mb_substr( wp_strip_all_tags( has_excerpt( $p ) ? get_the_excerpt( $p ) : $p->post_content ), 0, 300 ) . '"';
	}

	$prompt = "Aşağıda bugün Veng Haber'de yayınlanan " . count( $posts ) . " haberin başlığı ve özeti var. Her biri için TEK KISA CÜMLE halinde, tarafsız, sade bir Türkçe özet yaz (madde başlığı tekrar etme, sadece o haberde ne olduğunu tek cümlede anlat).\n\n"
		. implode( "\n", $lines ) . "\n\n"
		. "Şunu JSON dizisi olarak döndür, başka hiçbir şey yazma: [\"1. haberin tek cümlesi\", \"2. haberin tek cümlesi\", ...] — dizi uzunluğu tam olarak " . count( $posts ) . ' olmalı, aynı sırada.';

	$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 30,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		),
		'body'    => wp_json_encode( array(
			'model'      => 'claude-sonnet-5',
			'max_tokens' => 1024,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );

	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return $fallback;
	}

	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$text = '';
	foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
			$text = trim( $block['text'] );
			break;
		}
	}
	if ( ! preg_match( '/\[[\s\S]*\]/', $text, $m ) ) {
		return $fallback;
	}
	$blurbs = json_decode( $m[0], true );
	if ( ! is_array( $blurbs ) || count( $blurbs ) !== count( $posts ) ) {
		return $fallback;
	}
	return $blurbs;
}

function veng_hourly_digest_generate() {
	$posts = veng_hourly_digest_posts();
	if ( ! $posts ) {
		return array( 'items' => array() );
	}

	$blurbs = veng_hourly_digest_blurbs( $posts );

	$items = array();
	foreach ( $posts as $i => $p ) {
		$items[] = array(
			'title' => $p->post_title,
			'link'  => get_permalink( $p ),
			'blurb' => $blurbs[ $i ] ?? '',
		);
	}
	return array( 'items' => $items );
}

function veng_hourly_digest_get( $force = false ) {
	if ( ! $force ) {
		$cached = get_transient( 'veng_hourly_digest' );
		if ( false !== $cached ) {
			return $cached;
		}
	}
	$digest = veng_hourly_digest_generate();
	$digest['time'] = current_time( 'timestamp' );
	set_transient( 'veng_hourly_digest', $digest, 10 * MINUTE_IN_SECONDS );
	return $digest;
}

function veng_hourly_digest_time_label( $timestamp ) {
	return 'Güncellendi: ' . date_i18n( 'H:i', $timestamp );
}

function veng_render_hourly_digest_items( $items ) {
	if ( ! $items ) {
		echo '<p style="font-size:13px;color:var(--muted);margin:10px 0 4px;">Bugün henüz haber yayınlanmadı.</p>';
		return;
	}
	echo '<ol id="veng-hourly-digest-list" class="digest-list">';
	foreach ( $items as $i => $item ) {
		echo '<li class="digest-item">';
		echo '<span class="digest-badge">' . intval( $i + 1 ) . '</span>';
		echo '<div>';
		echo '<a href="' . esc_url( $item['link'] ) . '" class="digest-title">' . esc_html( $item['title'] ) . '</a>';
		if ( ! empty( $item['blurb'] ) ) {
			echo '<div class="digest-blurb">' . esc_html( $item['blurb'] ) . '</div>';
		}
		echo '</div>';
		echo '</li>';
	}
	echo '</ol>';
}

function veng_render_hourly_digest_widget() {
	$digest = veng_hourly_digest_get();
	?>
	<div class="card digest-card" id="veng-hourly-digest">
		<div class="digest-header">
			<span class="digest-pulse" aria-hidden="true"></span>
			<span class="digest-heading">🗞️ BUGÜN NE OLDU</span>
			<button type="button" id="veng-hourly-digest-refresh" class="digest-refresh-btn">⟳ Şimdi Tara</button>
		</div>
		<?php veng_render_hourly_digest_items( $digest['items'] ); ?>
		<div id="veng-hourly-digest-time" class="digest-time"><?php echo esc_html( veng_hourly_digest_time_label( $digest['time'] ) ); ?></div>
	</div>
	<?php
}

function veng_ajax_hourly_digest_refresh() {
	check_ajax_referer( 'veng_nonce', 'nonce' );

	if ( get_transient( 'veng_hourly_digest_lock' ) ) {
		$digest = veng_hourly_digest_get();
	} else {
		set_transient( 'veng_hourly_digest_lock', 1, 20 );
		$digest = veng_hourly_digest_get( true );
	}

	ob_start();
	veng_render_hourly_digest_items( $digest['items'] );
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html, 'time' => veng_hourly_digest_time_label( $digest['time'] ) ) );
}
add_action( 'wp_ajax_veng_hourly_digest_refresh', 'veng_ajax_hourly_digest_refresh' );
add_action( 'wp_ajax_nopriv_veng_hourly_digest_refresh', 'veng_ajax_hourly_digest_refresh' );
