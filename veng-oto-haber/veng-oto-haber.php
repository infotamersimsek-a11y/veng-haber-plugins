<?php
/**
 * Plugin Name: Veng Oto Haber
 * Description: RSS kaynaklarından otomatik haber çeker, Claude ile editöryel kurallara göre yeniden yazar ve yayınlar. Tema bağımsız çalışır, hangi tema aktif olursa olsun devam eder.
 * Version: 1.0.2
 * Author: Veng Haber
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Otomatik güncelleme: GitHub'daki paylaşılan depoyu kontrol eder, "Güncelleme mevcut" bildirimini
// wp-admin'de gösterir — artık zip indirip elle yüklemeye gerek yok.
if ( file_exists( __DIR__ . '/puc/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/puc/plugin-update-checker.php';
	$veng_oh_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/infotamersimsek-a11y/veng-haber-plugins/',
		__FILE__,
		'veng-oto-haber'
	);
	$veng_oh_update_checker->setBranch( 'main' );
	$veng_oh_update_checker->getVcsApi()->enableReleaseAssets( '/^veng-oto-haber\.zip$/' );
}

/**
 * Gerçek, doğrulanmış Türkçe/Türkiye'de yayın yapan haber kaynakları.
 * 'type' => 'rss' (varsayılan) klasik RSS/Atom/RDF; 'newssitemap' Google
 * News sitemap formatı (özet/görsel yok, madde başına ayrıca makale
 * sayfasından og:description/og:image çekilir).
 *
 * Dışarıda bırakılanlar (araştırılıp bilinçli olarak eklenmedi):
 * - Mezopotamya Ajansı: çalışanları PKK'nın "basın komitesi" bağlantısı
 *   iddiasıyla tutuklandı, Almanya'da Özgür Politika ile birlikte kapatıldı.
 * - Ajansa Welat (ajansawelat1.com) / Azadiya Welat: 2016'da "terör örgütü
 *   propagandası" gerekçesiyle KHK ile kapatıldı; "1" ekli alan adı, bloke
 *   edilen Kürt yayın organlarında sık görülen yedek-domain deseniyle
 *   örtüşüyor. Aynı sebeple alınmıyor — istenen "ekran görüntüsü al, Word'e
 *   aktar, sisteme yükle" yöntemi de bu kısıtı dolaşmak için kullanılmıyor.
 * Amida Haber: JS ile render edilen site, ne RSS ne sitemap'te gerçek
 * makale listesi var, sayfa kaynağında da gömülü veri yok — sunucu tarafı
 * PHP ile güvenilir çekilemiyor. Çalışan bir RSS/API adresi bulunursa
 * eklenebilir.
 * Rudaw: RSS/sitemap yok ama anasayfanın kendi gömülü verisinden
 * (veng_oh_parse_rudaw_embedded) çekiliyor — resmi bir API olmadığından
 * Rudaw sitesini yeniden tasarlarsa bu kaynak sessizce 0 haber dönebilir.
 */
function veng_oh_feeds() {
	return array(
		array( 'url' => 'https://feeds.bbci.co.uk/turkce/rss.xml', 'category' => 'dunya', 'source' => 'BBC Türkçe' ),
		array( 'url' => 'http://tr.sputniknews.com/export/rss2/archive/index.xml', 'category' => 'dunya', 'source' => 'Sputnik Türkiye' ),
		array( 'url' => 'http://rss.dw.com/rdf/rss-tur-all', 'category' => 'dunya', 'source' => 'DW Türkçe' ),
		array( 'url' => 'https://feeds.feedburner.com/euronews/tr/home', 'category' => 'dunya', 'source' => 'Euronews Türkçe' ),
		array( 'url' => 'http://www.evrensel.net/rss/haber.xml', 'category' => 'gundem', 'source' => 'Evrensel' ),
		array( 'url' => 'https://www.birgun.net/rss/home', 'category' => 'gundem', 'source' => 'BirGün' ),
		array( 'url' => 'https://www.mucadelegazetesi.com.tr/sitemap-news.xml', 'category' => 'gundem', 'source' => 'Mücadele Gazetesi', 'type' => 'newssitemap' ),
		array( 'url' => 'https://www.rudaw.net/turkish', 'category' => 'dunya', 'source' => 'Rudaw', 'type' => 'rudaw_embedded' ),
	);
}

function veng_oh_strip( $str ) {
	$str = preg_replace( '/<!\[CDATA\[|\]\]>/', '', (string) $str );
	$str = wp_strip_all_tags( $str );
	return trim( html_entity_decode( html_entity_decode( $str, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES, 'UTF-8' ) );
}

/** RSS 2.0 (<item>), RDF/RSS 1.0 (<item rdf:about="...">) ve Atom (<entry>) formatlarını destekler. */
function veng_oh_parse_feed( $xml ) {
	$items = array();
	$is_atom = (bool) preg_match( '/<entry[\s>]/', $xml );
	// \s|> sınırı zorunlu: yoksa RDF feed'lerdeki <items><rdf:Seq>...</rdf:Seq></items>
	// sarmalayıcısı da "<item...>" gibi eşleşip sahte bir haber üretiyor.
	$block_pattern = $is_atom
		? '/<entry(?:\s[^>]*)?>([\s\S]*?)<\/entry>/'
		: '/<item(?:\s[^>]*)?>([\s\S]*?)<\/item>/';

	preg_match_all( $block_pattern, $xml, $blocks );

	foreach ( $blocks[0] as $i => $block ) {
		$pick = function ( $tag ) use ( $block ) {
			if ( preg_match( '/<' . $tag . '(?:\s[^>]*)?>([\s\S]*?)<\/' . $tag . '>/i', $block, $m ) ) {
				return veng_oh_strip( $m[1] );
			}
			return '';
		};

		$title = $pick( 'title' );
		$link = '';
		if ( $is_atom ) {
			if ( preg_match( '/<link[^>]*rel=["\']alternate["\'][^>]*href=["\']([^"\']+)["\']/i', $block, $m )
				|| preg_match( '/<link[^>]*href=["\']([^"\']+)["\']/i', $block, $m )
				|| preg_match( '/<id>([\s\S]*?)<\/id>/i', $block, $m ) ) {
				$link = trim( $m[1] );
			}
		} else {
			$link = $pick( 'link' );
			if ( ! $link && preg_match( '/rdf:about=["\']([^"\']+)["\']/i', $blocks[0][ $i ], $m ) ) {
				$link = trim( $m[1] );
			}
		}
		$summary = $pick( $is_atom ? 'summary' : 'description' );
		$image = veng_oh_extract_image( $block );

		if ( $title && $link ) {
			$items[] = array( 'title' => $title, 'link' => $link, 'summary' => $summary, 'image' => $image );
		}
	}
	return $items;
}

/**
 * Rudaw'ın anasayfası RSS/sitemap sunmuyor (Next.js, tamamen istemci tarafında render
 * ediliyor) — ama sayfanın kendi HTML'ine, kendi ön yüzünün kullandığı ham makale verisi
 * (başlık/özet/görsel/kategori/ID) "self.__next_f.push([1,\"...\"])" bloklarıyla gömülü
 * geliyor. Bunları birleştirip JSON nesnelerini regex ile çıkarıyoruz. Bu resmi bir API
 * değil, Rudaw kendi sitesini yeniden tasarlarsa bu ayrıştırıcı bozulabilir — o durumda
 * sessizce 0 haber döner, cron'u durdurmaz.
 */
function veng_oh_parse_rudaw_embedded( $html ) {
	$items = array();
	if ( ! preg_match_all( '/self\.__next_f\.push\(\[1,(".*?")\]\)/s', $html, $chunks ) ) {
		return $items;
	}
	$full = '';
	foreach ( $chunks[1] as $json_str ) {
		$decoded = json_decode( $json_str );
		if ( null !== $decoded ) {
			$full .= $decoded;
		}
	}
	$pattern = '/\{"ID":(\d+),.*?"Title":"((?:[^"\\\\]|\\\\.)*)".*?"Summary":"((?:[^"\\\\]|\\\\.)*)".*?"imgPath":"([^"]*)".*?"categorySlug":"([a-z]+)"\}/s';
	if ( ! preg_match_all( $pattern, $full, $matches, PREG_SET_ORDER ) ) {
		return $items;
	}
	$seen = array();
	foreach ( $matches as $obj ) {
		$id = $obj[1];
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$title = json_decode( '"' . $obj[2] . '"' );
		$summary = json_decode( '"' . $obj[3] . '"' );
		$img_path = $obj[4];
		$cat_slug = $obj[5];
		if ( ! $title ) {
			continue;
		}
		$items[] = array(
			'title'   => veng_oh_strip( (string) $title ),
			'link'    => "https://www.rudaw.net/turkish/categories/{$cat_slug}/{$id}",
			'summary' => veng_oh_strip( (string) $summary ),
			'image'   => $img_path ? ( 'https://images.rudaw.net' . $img_path ) : '',
		);
	}
	return $items;
}

/** Google News sitemap formatını ayrıştırır (<url><loc>..</loc><news:title>..</news:title></url>). */
function veng_oh_parse_newssitemap( $xml ) {
	$items = array();
	preg_match_all( '/<url>([\s\S]*?)<\/url>/', $xml, $blocks );
	foreach ( $blocks[1] as $block ) {
		if ( preg_match( '/<loc>([^<]+)<\/loc>/', $block, $lm )
			&& preg_match( '/<news:title>([\s\S]*?)<\/news:title>/', $block, $tm ) ) {
			$items[] = array(
				'title'   => veng_oh_strip( $tm[1] ),
				'link'    => trim( $lm[1] ),
				'summary' => '',
				'image'   => '',
			);
		}
	}
	return $items;
}

/** RSS/sitemap'te özet ya da görsel gelmediyse makale sayfasından og:description / og:image çeker. */
function veng_oh_fetch_og_meta( $url ) {
	$res = wp_remote_get( $url, array(
		'timeout'    => 12,
		'user-agent' => 'Mozilla/5.0 (compatible; VengHaberBot/1.0; +https://venghaber.com)',
	) );
	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
		return array( 'summary' => '', 'image' => '' );
	}
	$html = wp_remote_retrieve_body( $res );
	$summary = '';
	$image = '';
	if ( preg_match( '/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
		$summary = veng_oh_strip( $m[1] );
	}
	if ( preg_match( '/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m ) ) {
		// HTML özniteliklerinde & işareti &amp; olarak kaçırılır — decode etmeden kullanınca
		// sorgu parametreli görsel URL'leri (ör. Rudaw'ın Next.js image proxy'si) bozuluyordu.
		$image = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
	}
	return array( 'summary' => $summary, 'image' => $image );
}

/** Feed öğesinden kapak görseli URL'i çıkarır: media:thumbnail, media:content, enclosure, sonra gömülü <img>. */
function veng_oh_extract_image( $block ) {
	if ( preg_match( '/<media:thumbnail\b[^>]*url=["\']([^"\']+)["\']/i', $block, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/<media:content\b[^>]*>/i', $block, $tag ) ) {
		$tag_str = $tag[0];
		if ( preg_match( '/url=["\']([^"\']+)["\']/i', $tag_str, $m ) ) {
			$url = $m[1];
			// medium="video" veya .mp4/.webm gibi video uzantılarını görsel sanıp indirme.
			$is_video = false !== stripos( $tag_str, 'medium="video"' ) || preg_match( '/\.(mp4|webm|mov|m3u8|avi)(\?|$)/i', $url );
			if ( ! $is_video ) {
				return $url;
			}
		}
	}
	if ( preg_match( '/<enclosure\b[^>]*>/i', $block, $tag ) && stripos( $tag[0], 'image' ) !== false
		&& preg_match( '/url=["\']([^"\']+)["\']/i', $tag[0], $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/<img\b[^>]*src=["\']([^"\']+)["\']/i', $block, $m ) ) {
		return $m[1];
	}
	return '';
}

function veng_oh_already_imported( $link ) {
	$existing = get_posts( array(
		'post_type'      => 'post',
		'meta_key'       => '_veng_source_url',
		'meta_value'     => $link,
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
	) );
	return ! empty( $existing );
}

/** Kaynak haberi Veng Haber editöryel kurallarına göre kendi cümleleriyle yeniden yazar. */
function veng_oh_editorial_rewrite( $item, $source ) {
	$api_key = get_option( 'veng_oh_anthropic_api_key' );
	if ( ! $api_key ) {
		// Sessizce çıkıp haberi ham özetle eklemek fark edilmiyordu — artık logda görünür.
		veng_oh_log( 'API anahtarı kayıtlı değil, haber yeniden yazılmadan (ham özetle) ekleniyor: ' . $item['title'] );
		return null;
	}

	$prompt = "Aşağıda \"{$source}\" kaynağından alınan bir haberin başlığı ve özeti var. Bunu Veng Haber editöryel kurallarına göre KENDİ CÜMLELERİNLE, özgün biçimde yeniden yaz (birebir kopyalama veya sadece kelime değiştirme yasak).\n\n"
		. "Editöryel kurallar (BBC Türkçe tarzı hedeflenir):\n"
		. "- Türkçe, ölçülü, sade, doğrudan haber dili kullan; BBC Türkçe'deki gibi resmi ama anlaşılır bir ton benimse.\n"
		. "- İlk cümlede konunun özünü doğrudan ver (inverted pyramid); dramatik/duygusal sıfatlar, ünlem, abartı kullanma.\n"
		. "- Hiçbir taraf/kişi/kurum/örgüt lehine veya aleyhine yorum, övgü ya da suçlama içermesin; yalnızca bilinen olguları aktar.\n"
		. "- İddia/açıklama niteliğindeki ifadeleri \"iddia edildi\", \"açıklandı\", \"belirtildi\" gibi temkinli atıf kalıplarıyla ver, kesin doğru gibi sunma.\n"
		. "- Uydurma detay, sayı, isim veya alıntı ekleme; yalnızca verilen bilgiyle sınırlı kal, belirsizse genel ifade kullan.\n"
		. "- Magazinsel/abartılı ifadelerden kaçın, gazetecilik standardına uygun ol.\n\n"
		. 'Orijinal başlık: "' . $item['title'] . "\"\n"
		. 'Orijinal özet: "' . mb_substr( $item['summary'], 0, 600 ) . "\"\n\n"
		. "Şunu JSON olarak döndür (başka hiçbir şey yazma):\n"
		. '{"title": "...", "summary": "...", "content_html": "<p>...</p><p>...</p>"}' . "\n"
		. 'content_html en az 2 paragraf olsun.';

	$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 40,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		),
		'body'    => wp_json_encode( array(
			'model'      => 'claude-sonnet-5',
			'max_tokens' => 2048,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );

	if ( is_wp_error( $res ) ) {
		veng_oh_log( 'Anthropic isteği başarısız: ' . $res->get_error_message() );
		return null;
	}
	if ( wp_remote_retrieve_response_code( $res ) !== 200 ) {
		veng_oh_log( 'Anthropic API ' . wp_remote_retrieve_response_code( $res ) . ': ' . wp_remote_retrieve_body( $res ) );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$text = '';
	foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
			$text = $block['text'];
			break;
		}
	}
	if ( ! preg_match( '/\{[\s\S]*\}/', $text, $m ) ) {
		veng_oh_log( 'AI yanıtından JSON çıkarılamadı: ' . mb_substr( $text, 0, 200 ) );
		return null;
	}
	$parsed = json_decode( $m[0], true );
	if ( empty( $parsed['title'] ) || empty( $parsed['content_html'] ) ) {
		return null;
	}
	return $parsed;
}

function veng_oh_import_item( $item, $feed ) {
	if ( veng_oh_already_imported( $item['link'] ) ) {
		return false;
	}

	if ( empty( $item['summary'] ) || empty( $item['image'] ) ) {
		$og = veng_oh_fetch_og_meta( $item['link'] );
		if ( empty( $item['summary'] ) ) {
			$item['summary'] = $og['summary'];
		}
		if ( empty( $item['image'] ) ) {
			$item['image'] = $og['image'];
		}
	}

	$category = get_category_by_slug( $feed['category'] );
	$title = $item['title'];
	$excerpt = mb_substr( $item['summary'], 0, 300 );
	$body_html = '<p>' . esc_html( $excerpt ) . '</p>';

	$draft = veng_oh_editorial_rewrite( $item, $feed['source'] );
	if ( $draft ) {
		$title = $draft['title'];
		$excerpt = mb_substr( $draft['summary'] ?: $excerpt, 0, 300 );
		$body_html = $draft['content_html'];
	}

	$attribution = '<p><em>Güncel Haber Kaynak: ' . esc_html( $feed['source'] ) . '</em></p>';

	$status = get_option( 'veng_oh_auto_publish', '1' ) === '1' ? 'publish' : 'draft';

	$post_id = wp_insert_post( array(
		'post_type'     => 'post',
		'post_title'    => wp_strip_all_tags( $title ),
		'post_excerpt'  => wp_strip_all_tags( $excerpt ),
		'post_content'  => $body_html . "\n" . $attribution,
		'post_status'   => $status,
		'post_category' => $category ? array( $category->term_id ) : array(),
	), true );

	if ( is_wp_error( $post_id ) ) {
		veng_oh_log( 'wp_insert_post hata: ' . $post_id->get_error_message() );
		return false;
	}

	update_post_meta( $post_id, '_veng_source_url', $item['link'] );
	update_post_meta( $post_id, '_veng_source_name', $feed['source'] );

	if ( ! empty( $item['image'] ) ) {
		veng_oh_set_featured_image( $post_id, $item['image'], wp_strip_all_tags( $title ) );
	}

	return $post_id;
}

/** Feed'deki görsel URL'ini indirip kapak görseli olarak ayarlar; başarısız olursa sessizce geçilir. */
/** .jfif WP'nin varsayılan izinli mime listesinde yok — media_sideload_image() bu yüzden reddediyordu. */
function veng_oh_allow_jfif_mime( $mimes ) {
	$mimes['jfif'] = 'image/jpeg';
	return $mimes;
}

function veng_oh_set_featured_image( $post_id, $image_url, $title ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	add_filter( 'upload_mimes', 'veng_oh_allow_jfif_mime' );
	$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );
	remove_filter( 'upload_mimes', 'veng_oh_allow_jfif_mime' );

	if ( is_wp_error( $attachment_id ) ) {
		veng_oh_log( 'Görsel indirilemedi (' . $image_url . '): ' . $attachment_id->get_error_message() );
		return;
	}
	// media_sideload_image()'ın $desc parametresi ek başlığı ayarlar ama _wp_attachment_image_alt
	// meta'sını YAZMIYOR — bu yüzden the_post_thumbnail() görsellerde alt="" boş çıkıyordu.
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
	// Görsel sıkıştırma bu eklentide değil, SEO Denetim eklentisinde yaşıyor (aktifse burayı dinler).
	do_action( 'veng_image_sideloaded', $attachment_id );
	set_post_thumbnail( $post_id, $attachment_id );
}

/** Eski "Bu haber ... kaynağından derlenerek ... habere git" linkli kaynak notunu sade "Güncel Haber Kaynak: X" ile değiştirir. */
function veng_oh_backfill_attribution() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$fixed = 0;
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_veng_source_name',
		'fields'         => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		$content = get_post_field( 'post_content', $post_id );
		if ( false === strpos( $content, 'habere git' ) ) {
			continue;
		}
		$source = get_post_meta( $post_id, '_veng_source_name', true );
		$new_content = preg_replace(
			'/<p><em>Bu haber[\s\S]*?<\/em><\/p>/u',
			'<p><em>Güncel Haber Kaynak: ' . esc_html( $source ) . '</em></p>',
			$content
		);
		if ( $new_content !== $content ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );
			$fixed++;
		}
	}
	return $fixed;
}

/** Öne çıkan görseli olmayan otomatik-çekilmiş haberleri kaynak makale linkinden (og:image) yeniden dener. */
function veng_oh_backfill_missing_images() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$fixed = 0;
	$failed = 0;
	$posts = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_key'       => '_veng_source_name',
		'fields'         => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			continue;
		}
		$source_url = get_post_meta( $post_id, '_veng_source_url', true );
		if ( ! $source_url ) {
			continue;
		}
		$og = veng_oh_fetch_og_meta( $source_url );
		if ( empty( $og['image'] ) ) {
			$failed++;
			continue;
		}
		veng_oh_set_featured_image( $post_id, $og['image'], get_the_title( $post_id ) );
		if ( has_post_thumbnail( $post_id ) ) {
			$fixed++;
		} else {
			$failed++;
		}
	}
	return array( 'fixed' => $fixed, 'failed' => $failed );
}

/** Alt metni boş olan, otomatik çekilmiş haberlerin öne çıkan görsellerini haber başlığıyla doldurur. */
function veng_oh_backfill_image_alt() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	$fixed = 0;
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
		$alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		if ( '' !== trim( (string) $alt ) ) {
			continue;
		}
		update_post_meta( $thumb_id, '_wp_attachment_image_alt', sanitize_text_field( get_the_title( $post_id ) ) );
		$fixed++;
	}
	return $fixed;
}

function veng_oh_log( $msg ) {
	error_log( '[VengOtoHaber] ' . $msg );
}

function veng_oh_run_import() {
	// Arka planda (wp-cron) çalışıyor, kullanıcı sayfası bloklamıyor — yavaş kaynaklar
	// toplamda 30sn'yi geçince PHP'nin varsayılan max_execution_time'ı tüm importu
	// fatal ile öldürüp kalan kaynakları hiç denemeden bırakıyordu.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$items_per_feed = 5;
	$created = 0;
	$lines = array();

	foreach ( veng_oh_feeds() as $feed ) {
		$res = wp_remote_get( $feed['url'], array(
			'timeout'    => 15,
			'user-agent' => 'Mozilla/5.0 (compatible; VengHaberBot/1.0; +https://venghaber.com)',
		) );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			$lines[] = $feed['source'] . '/' . $feed['category'] . ': alınamadı';
			continue;
		}
		$type = $feed['type'] ?? 'rss';
		$parser = 'veng_oh_parse_feed';
		if ( 'newssitemap' === $type ) {
			$parser = 'veng_oh_parse_newssitemap';
		} elseif ( 'rudaw_embedded' === $type ) {
			$parser = 'veng_oh_parse_rudaw_embedded';
		}
		$items = array_slice( $parser( wp_remote_retrieve_body( $res ) ), 0, $items_per_feed );
		$feed_created = 0;
		foreach ( $items as $item ) {
			if ( veng_oh_import_item( $item, $feed ) ) {
				$feed_created++;
				$created++;
			}
		}
		$lines[] = $feed['source'] . '/' . $feed['category'] . ': ' . $feed_created . ' yeni';
	}

	update_option( 'veng_oh_last_run', array(
		'time'    => current_time( 'mysql' ),
		'created' => $created,
		'lines'   => $lines,
	) );

	veng_oh_enforce_post_cap();

	return $created;
}
add_action( 'veng_oh_import_event', 'veng_oh_run_import' );

/**
 * Otomatik çekilmiş haberleri ve görsellerini kalıcı olarak siler — SADECE
 * '_veng_source_name' meta'sı olan (botun eklediği) yazılara dokunur, elle yazılmış
 * haberler asla silinmez. wp_delete_post() döngüsü yerine toplu SQL kullanır.
 * Tek seferde tümünü silmeye çalışmak zayıf sunucularda 502'ye yol açtı — bu yüzden
 * her çağrıda en fazla $batch_size işlenir, çağıran taraf "posts" boş dönene kadar
 * tekrar tekrar çağırır (bkz. veng_oh_maybe_run_pending_wipe).
 */
/**
 * Verilen yazı ID'lerini (ve öne çıkan görsellerini) toplu SQL ile siler — wp_delete_post()
 * döngüsü binlerce kayıtta çok yavaş kalıp 500/502 hatasına yol açıyordu, bu çok daha hızlı.
 */
function veng_oh_bulk_delete_posts( $post_ids ) {
	global $wpdb;
	if ( empty( $post_ids ) ) {
		return array( 'posts' => 0, 'attachments' => 0, 'files' => 0 );
	}

	// Görselleri (öne çıkan resim) silinmeden önce dosya yollarını topla.
	$attachment_ids = array();
	foreach ( $post_ids as $pid ) {
		$thumb_id = get_post_thumbnail_id( $pid );
		if ( $thumb_id ) {
			$attachment_ids[] = (int) $thumb_id;
		}
	}
	$attachment_ids = array_unique( $attachment_ids );

	$files_deleted = 0;
	foreach ( $attachment_ids as $aid ) {
		$file = get_attached_file( $aid );
		if ( $file && file_exists( $file ) ) {
			$dir = dirname( $file );
			$meta = wp_get_attachment_metadata( $aid );
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $s ) {
					$sf = $dir . '/' . $s['file'];
					if ( file_exists( $sf ) ) {
						@unlink( $sf );
					}
				}
			}
			@unlink( $file );
			$files_deleted++;
		}
	}

	$post_chunks = array_chunk( $post_ids, 200 );
	foreach ( $post_chunks as $chunk ) {
		$list = implode( ',', array_map( 'intval', $chunk ) );
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$list})" );
		$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$list})" );
		$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_post_ID IN ({$list})" );
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$list})" );
	}

	if ( ! empty( $attachment_ids ) ) {
		$att_chunks = array_chunk( $attachment_ids, 200 );
		foreach ( $att_chunks as $chunk ) {
			$list = implode( ',', array_map( 'intval', $chunk ) );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$list})" );
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$list})" );
		}
	}

	return array( 'posts' => count( $post_ids ), 'attachments' => count( $attachment_ids ), 'files' => $files_deleted );
}

function veng_oh_wipe_all_auto_content( $batch_size = 200 ) {
	global $wpdb;
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
		 WHERE p.post_type IN ('post','makale') LIMIT %d",
		'_veng_source_name',
		$batch_size
	) );

	$result = veng_oh_bulk_delete_posts( $post_ids );
	if ( $result['posts'] > 0 ) {
		veng_oh_log( 'Tam temizlik: ' . $result['posts'] . ' otomatik haber, ' . $result['attachments'] . ' görsel (' . $result['files'] . ' dosya) silindi.' );
	}
	return $result;
}

/**
 * Yeni yüklenen eklenti kodu çalıştığında (sen buton falan tıklamadan, sadece wp-admin'e
 * her girişte) otomatik haberleri parça parça temizler — tek istekte binlercesini silmeye
 * çalışmak zayıf sunucuda 502/500'e yol açtı, bu yüzden her sayfa girişinde en fazla 200
 * işlenir, sayaç (option) üstünde birikir, hepsi bitince bayrak set edilip durur.
 * Aktivasyon isteğinin kendisinde çalışmaz (o istek zaten kırılgan) — sonraki normal
 * sayfa yüklemelerinde devam eder.
 */
function veng_oh_maybe_run_pending_wipe() {
	if ( ! is_admin() || '1' === get_option( 'veng_oh_full_wipe_v1_done' ) ) {
		return;
	}
	if ( isset( $_GET['action'] ) && 'activate' === $_GET['action'] ) {
		return;
	}

	$result = veng_oh_wipe_all_auto_content( 200 );

	$cumulative = get_option( 'veng_oh_full_wipe_v1_result' );
	if ( ! is_array( $cumulative ) ) {
		$cumulative = array( 'posts' => 0, 'attachments' => 0, 'files' => 0 );
	}
	$cumulative['posts'] = ( $cumulative['posts'] ?? 0 ) + $result['posts'];
	$cumulative['attachments'] = ( $cumulative['attachments'] ?? 0 ) + $result['attachments'];
	$cumulative['files'] = ( $cumulative['files'] ?? 0 ) + $result['files'];
	$cumulative['time'] = current_time( 'mysql' );
	update_option( 'veng_oh_full_wipe_v1_result', $cumulative );

	if ( 0 === $result['posts'] ) {
		update_option( 'veng_oh_full_wipe_v1_done', '1' );
	}
}
add_action( 'plugins_loaded', 'veng_oh_maybe_run_pending_wipe' );

/**
 * Otomatik çekilen haberler toplamı belli bir sayıyı geçmesin diye en eskilerini siler.
 * SADECE '_veng_source_name' meta'sı olan (yani otomatik çekilmiş) yazılara dokunur —
 * elle yazılmış haberler asla silinmez. Toplu SQL kullanır (veng_oh_bulk_delete_posts),
 * tek seferde en fazla $batch_size siler (sunucuyu boğmamak için).
 */
function veng_oh_enforce_post_cap( $max_posts = 3000, $batch_size = 200 ) {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
	global $wpdb;
	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
		 WHERE p.post_type IN ('post','makale') AND p.post_status = 'publish'",
		'_veng_source_name'
	) );
	if ( $total <= $max_posts ) {
		return 0;
	}
	$to_delete = min( $batch_size, $total - $max_posts );

	$ids = get_posts( array(
		'post_type'      => array( 'post', 'makale' ),
		'post_status'    => 'publish',
		'posts_per_page' => $to_delete,
		'meta_key'       => '_veng_source_name',
		'orderby'        => 'date',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );
	$result = veng_oh_bulk_delete_posts( $ids );
	veng_oh_log( "Otomatik haber sınırı ({$max_posts}) aşılmıştı, {$total} kayıttan " . $result['posts'] . ' en eski otomatik haber silindi.' );
	return $result['posts'];
}

function veng_oh_cron_schedules( $schedules ) {
	$schedules['veng_oh_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => 'Her 15 Dakikada Bir (Veng Oto Haber)',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'veng_oh_cron_schedules' );

function veng_oh_activate() {
	if ( ! wp_next_scheduled( 'veng_oh_import_event' ) ) {
		// Zamanı "şimdi" vermek ilk taramayı hemen "vadesi geldi" yapar — senkron çalıştırmaya
		// gerek yok, dışarıdan kurulan cron ping (cron-job.org/UptimeRobot) birkaç dakika
		// içinde wp-cron.php'yi tetikleyip bunu otomatik çalıştırır. Aktivasyon isteğinin
		// kendi içinde ağır işi senkron yapmak, zayıf sunucularda 502/zaman aşımına yol açıyordu.
		wp_schedule_event( time(), 'veng_oh_fifteen_minutes', 'veng_oh_import_event' );
	}
}
register_activation_hook( __FILE__, 'veng_oh_activate' );

function veng_oh_deactivate() {
	$timestamp = wp_next_scheduled( 'veng_oh_import_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'veng_oh_import_event' );
	}
}
register_deactivation_hook( __FILE__, 'veng_oh_deactivate' );

/** Ayarlar sayfası: tek yerde API anahtarı, yayın modu, durum ve manuel çalıştırma. */
function veng_oh_register_settings_page() {
	add_menu_page( 'Veng Oto Haber', 'Oto Haber', 'manage_options', 'veng-oto-haber', 'veng_oh_settings_page', 'dashicons-rss', 25 );
}
add_action( 'admin_menu', 'veng_oh_register_settings_page' );

function veng_oh_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$wipe_result = get_option( 'veng_oh_full_wipe_v1_result' );
	$wipe_done = '1' === get_option( 'veng_oh_full_wipe_v1_done' );
	if ( $wipe_result ) {
		if ( $wipe_done ) {
			echo '<div class="notice notice-success"><p><strong>Tam temizlik tamamlandı</strong> (' . esc_html( $wipe_result['time'] ) . '): toplam ' . intval( $wipe_result['posts'] ) . ' otomatik haber ve ' . intval( $wipe_result['attachments'] ) . ' görsel kalıcı olarak silindi. Elle yazdığın haberler dokunulmadan duruyor.</p></div>';
		} else {
			echo '<div class="notice notice-warning"><p><strong>Temizlik devam ediyor…</strong> Şu ana kadar ' . intval( $wipe_result['posts'] ) . ' haber silindi. Sunucuyu boğmamak için parça parça yapılıyor — bu sayfayı birkaç kez yenile, otomatik devam edecek.</p></div>';
		}
	}

	if ( isset( $_POST['veng_oh_save_settings'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		update_option( 'veng_oh_anthropic_api_key', sanitize_text_field( wp_unslash( $_POST['veng_oh_anthropic_api_key'] ?? '' ) ) );
		update_option( 'veng_oh_auto_publish', isset( $_POST['veng_oh_auto_publish'] ) ? '1' : '0' );
		echo '<div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div>';
	}

	if ( isset( $_POST['veng_oh_run_now'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		$created = veng_oh_run_import();
		echo '<div class="notice notice-success"><p>Manuel tarama tamamlandı: ' . intval( $created ) . ' yeni haber eklendi.</p></div>';
	}

	if ( isset( $_POST['veng_oh_backfill_alt'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		$fixed = veng_oh_backfill_image_alt();
		echo '<div class="notice notice-success"><p>Görsel alt metni dolduruldu: ' . intval( $fixed ) . ' görsel güncellendi.</p></div>';
	}

	if ( isset( $_POST['veng_oh_backfill_images'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		$r = veng_oh_backfill_missing_images();
		echo '<div class="notice notice-success"><p>Eksik görsel taraması tamamlandı: ' . intval( $r['fixed'] ) . ' görsel yüklendi, ' . intval( $r['failed'] ) . ' haberde kaynak sayfasında da görsel bulunamadı.</p></div>';
	}

	if ( isset( $_POST['veng_oh_backfill_attribution'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		$fixed = veng_oh_backfill_attribution();
		echo '<div class="notice notice-success"><p>Kaynak notu güncellendi: ' . intval( $fixed ) . ' haberde sadeleştirildi.</p></div>';
	}

	if ( isset( $_POST['veng_oh_enforce_cap'] ) && check_admin_referer( 'veng_oh_settings' ) ) {
		// Elle tetiklenen temizlik — toplu SQL kullanıyor ama yine de tek istekte 200'le sınırlı,
		// hâlâ fazlaysa butona tekrar basman yeterli.
		$deleted = veng_oh_enforce_post_cap( 3000, 200 );
		echo '<div class="notice notice-success"><p>' . intval( $deleted ) . ' en eski otomatik haber silindi. Hâlâ 3000 üstündeyse butona tekrar bas.</p></div>';
	}

	$api_key = get_option( 'veng_oh_anthropic_api_key', '' );
	$auto_publish = get_option( 'veng_oh_auto_publish', '1' ) === '1';
	$last = get_option( 'veng_oh_last_run' );
	$next_cron = wp_next_scheduled( 'veng_oh_import_event' );
	global $wpdb;
	$auto_post_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
		 WHERE p.post_type IN ('post','makale') AND p.post_status = 'publish'",
		'_veng_source_name'
	) );
	?>
	<div class="wrap">
		<h1>Veng Oto Haber</h1>
		<p>RSS kaynaklarından haberleri çeker, Claude ile editöryel kurallara göre yeniden yazar, kategoriye göre yayınlar. Her 15 dakikada bir kendi kendine çalışır (site ziyaretiyle tetiklenir).</p>

		<form method="post">
			<?php wp_nonce_field( 'veng_oh_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="veng_oh_anthropic_api_key">Claude (Anthropic) API Anahtarı</label></th>
					<td>
						<input type="text" id="veng_oh_anthropic_api_key" name="veng_oh_anthropic_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="sk-ant-..." />
						<?php if ( $api_key ) : ?>
							<span style="color:#166534;font-weight:700;">✓ Kayıtlı (yeniden yazma aktif)</span>
						<?php else : ?>
							<span style="color:#991b1b;font-weight:700;">✗ Kayıtlı değil — haberler yeniden yazılmadan, ham özetle ekleniyor!</span>
						<?php endif; ?>
						<p class="description">Boşsa haberler yeniden yazılmadan, kaynak özetiyle eklenir.</p>
					</td>
				</tr>
				<tr>
					<th>Otomatik yayınla</th>
					<td>
						<label><input type="checkbox" name="veng_oh_auto_publish" <?php checked( $auto_publish ); ?> /> Çekilen haberleri direkt yayınla (kapalıysa taslak olarak eklenir)</label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="veng_oh_save_settings" class="button button-primary">Ayarları Kaydet</button>
				<button type="submit" name="veng_oh_run_now" class="button">Şimdi Çalıştır</button>
				<button type="submit" name="veng_oh_backfill_alt" class="button">Eski Görsellere Alt Metin Doldur</button>
				<button type="submit" name="veng_oh_backfill_images" class="button">Eksik Görselleri Tespit Et ve Yükle</button>
				<button type="submit" name="veng_oh_backfill_attribution" class="button">Kaynak Notunu Sadeleştir</button>
				<button type="submit" name="veng_oh_enforce_cap" class="button">Fazla Haberleri Şimdi Sil (3000 sınırı)</button>
			</p>
		</form>
		<p class="description">"Eski Görsellere Alt Metin Doldur": geçmiş bir hata yüzünden otomatik çekilen haberlerin görsellerinde alt metin (SEO/erişilebilirlik için) hiç ayarlanmıyordu. Bu düzeltildi, yeni haberler otomatik alt metinle geliyor — bu buton geçmiş haberleri de tek seferde doldurur.<br>"Eksik Görselleri Tespit Et ve Yükle": öne çıkan görseli hiç olmayan otomatik haberleri bulur, kaynak makale linkine tekrar gidip görseli indirmeyi dener.<br>"Kaynak Notunu Sadeleştir": eski "...habere git" linkli kaynak notunu sade "Güncel Haber Kaynak: X" ile değiştirir.<br>"Fazla Haberleri Şimdi Sil": otomatik çekilen haberler 3000'i geçtiyse en eskilerini siler — elle yazdığın haberlere hiç dokunmaz, sadece <code>_veng_source_name</code> meta'sı olanlara (yani botun eklediklerine) bakar. Bu zaten her 15 dakikalık taramada kendiliğinden de çalışır, bu buton sadece hemen istersen.</p>

		<h2>Durum</h2>
		<p><strong>Toplam otomatik haber:</strong> <?php echo esc_html( number_format_i18n( $auto_post_count ) ); ?> / 3.000<?php echo $auto_post_count > 3000 ? ' <span style="color:#991b1b;font-weight:700;">— sınır aşıldı, en yakın taramada otomatik temizlenecek</span>' : ''; ?></p>
		<?php if ( $last ) : ?>
			<p><strong>Son tarama:</strong> <?php echo esc_html( $last['time'] ); ?> — <?php echo intval( $last['created'] ); ?> yeni haber eklendi.</p>
			<ul>
				<?php foreach ( (array) $last['lines'] as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p>Henüz otomatik tarama çalışmadı. "Şimdi Çalıştır" ile ilk taramayı başlatabilirsin.</p>
		<?php endif; ?>
		<p><strong>Sıradaki otomatik çalışma:</strong> <?php echo $next_cron ? esc_html( date_i18n( 'd F Y, H:i', $next_cron ) ) : 'planlanmadı'; ?></p>
	</div>
	<?php
}
