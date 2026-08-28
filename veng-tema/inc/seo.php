<?php
/**
 * SEO: JSON-LD, sitemap.xml, rss.xml (haber namespace), robots.txt, llms.txt
 */

/** Görüntülenme sayacı */
function veng_track_view() {
	if ( is_singular( array( 'post', 'makale' ) ) && ! is_admin() ) {
		$id = get_queried_object_id();
		$count = (int) get_post_meta( $id, '_veng_views', true );
		update_post_meta( $id, '_veng_views', $count + 1 );
	}
}
add_action( 'wp', 'veng_track_view' );

function veng_get_views( $post_id ) {
	return (int) get_post_meta( $post_id, '_veng_views', true );
}

/** JSON-LD çıktı */
function veng_json_ld_head() {
	if ( is_singular( 'post' ) ) {
		global $post;
		$author_id = $post->post_author;
		$categories = get_the_category( $post->ID );
		$tags = get_the_tags( $post->ID );

		$data = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'NewsArticle',
			'headline'        => get_the_title( $post ),
			'name'            => get_the_title( $post ),
			'description'     => get_the_excerpt( $post ),
			'url'             => get_permalink( $post ),
			'mainEntityOfPage'=> array( '@type' => 'WebPage', '@id' => get_permalink( $post ) ),
			'datePublished'   => get_the_date( 'c', $post ),
			'dateModified'    => get_the_modified_date( 'c', $post ),
			'author'          => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $author_id ) ),
			'publisher'       => veng_publisher_jsonld(),
			'inLanguage'      => 'tr-TR',
			'wordCount'       => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			'timeRequired'    => 'PT' . max( 1, ceil( str_word_count( wp_strip_all_tags( $post->post_content ) ) / 200 ) ) . 'M',
			'articleSection'  => $categories ? $categories[0]->name : '',
			'keywords'        => $tags ? implode( ', ', wp_list_pluck( $tags, 'name' ) ) : '',
		);
		if ( has_post_thumbnail( $post ) ) {
			$data['image'] = array( '@type' => 'ImageObject', 'url' => get_the_post_thumbnail_url( $post, 'full' ) );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";

		$breadcrumb = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => home_url( '/' ) ),
			),
		);
		if ( $categories ) {
			$breadcrumb['itemListElement'][] = array( '@type' => 'ListItem', 'position' => 2, 'name' => $categories[0]->name, 'item' => get_category_link( $categories[0]->term_id ) );
		}
		$breadcrumb['itemListElement'][] = array( '@type' => 'ListItem', 'position' => count( $breadcrumb['itemListElement'] ) + 1, 'name' => get_the_title( $post ), 'item' => get_permalink( $post ) );
		echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb ) . '</script>' . "\n";
	}

	if ( is_singular( 'makale' ) ) {
		global $post;
		$data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => get_the_title( $post ),
			'description'   => get_the_excerpt( $post ),
			'url'           => get_permalink( $post ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'author'        => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
			'publisher'     => veng_publisher_jsonld(),
			'inLanguage'    => 'tr-TR',
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
	}

	if ( is_front_page() ) {
		$data = array(
			'@context'       => 'https://schema.org',
			'@type'          => 'WebSite',
			'name'           => get_bloginfo( 'name' ),
			'url'            => home_url( '/' ),
			'potentialAction'=> array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => home_url( '/?s={search_term_string}' ) ),
				'query-input' => 'required name=search_term_string',
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n";
		echo '<script type="application/ld+json">' . wp_json_encode( veng_publisher_jsonld() ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'veng_json_ld_head', 5 );

/** Open Graph / Twitter Card meta etiketleri (JSON-LD'ye ek olarak; sosyal medya önizlemeleri için gerekli). */
function veng_default_og_image() {
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( $url ) return $url;
	}
	return '';
}

/** Google'ın arama sonucu snippet'inde kullandığı klasik meta description + canonical link. */
function veng_meta_description_and_canonical() {
	$description = get_bloginfo( 'name' ) . ' — ' . ( get_bloginfo( 'description' ) ?: 'Güncel haberler, son dakika gelişmeleri.' );
	$url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );

	if ( is_singular( array( 'post', 'makale' ) ) ) {
		global $post;
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$url = get_permalink( $post );
	} elseif ( is_category() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->description ) ) {
			$description = wp_strip_all_tags( $term->description );
		}
		$url = get_term_link( $term );
	} elseif ( is_singular() ) {
		$url = get_permalink();
	}

	echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 40, '…' ) ) . '">' . "\n";
	if ( is_string( $url ) && $url ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'veng_meta_description_and_canonical', 3 );

function veng_og_meta_tags() {
	$title       = wp_get_document_title();
	$description = get_bloginfo( 'description' );
	$url         = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
	$type        = 'website';
	$image       = veng_default_og_image();

	if ( is_singular( array( 'post', 'makale' ) ) ) {
		global $post;
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
		$url         = get_permalink( $post );
		$type        = 'article';
		if ( has_post_thumbnail( $post ) ) {
			$image = get_the_post_thumbnail_url( $post, 'full' );
		}
	} elseif ( is_singular() ) {
		$url = get_permalink();
	}

	echo "\n<!-- Open Graph / Twitter -->\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:locale" content="tr_TR">' . "\n";
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
	if ( $image ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'veng_og_meta_tags', 4 );

function veng_publisher_jsonld() {
	$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_url( get_theme_mod( 'custom_logo' ) ) : '';
	return array(
		'@type' => 'Organization',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
		'logo'  => array( '@type' => 'ImageObject', 'url' => $logo ),
		'sameAs' => array_values( array_filter( array(
			get_theme_mod( 'veng_social_facebook' ),
			get_theme_mod( 'veng_social_x' ),
			get_theme_mod( 'veng_social_instagram' ),
			get_theme_mod( 'veng_social_linkedin' ),
		) ) ),
	);
}

/** --- Özel rewrite: /sitemap.xml, /rss.xml, /robots.txt, /llms.txt --- */

function veng_add_rewrite_rules() {
	add_rewrite_rule( '^sitemap\.xml$', 'index.php?veng_route=sitemap', 'top' );
	add_rewrite_rule( '^rss\.xml$', 'index.php?veng_route=rss', 'top' );
	add_rewrite_rule( '^robots\.txt$', 'index.php?veng_route=robots', 'top' );
	add_rewrite_rule( '^llms\.txt$', 'index.php?veng_route=llms', 'top' );
	add_rewrite_rule( '^manifest\.json$', 'index.php?veng_route=manifest', 'top' );
	add_rewrite_rule( '^service-worker\.js$', 'index.php?veng_route=sw', 'top' );
	add_rewrite_rule( '^\.well-known/assetlinks\.json$', 'index.php?veng_route=assetlinks', 'top' );
}
add_action( 'init', 'veng_add_rewrite_rules' );

function veng_query_vars( $vars ) {
	$vars[] = 'veng_route';
	return $vars;
}
add_filter( 'query_vars', 'veng_query_vars' );

function veng_handle_custom_routes() {
	$route = get_query_var( 'veng_route' );
	if ( ! $route ) {
		return;
	}

	if ( 'sitemap' === $route ) {
		header( 'Content-Type: application/xml; charset=utf-8' );
		echo veng_build_sitemap();
		exit;
	}
	if ( 'rss' === $route ) {
		header( 'Content-Type: application/rss+xml; charset=utf-8' );
		echo veng_build_rss();
		exit;
	}
	if ( 'robots' === $route ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "User-agent: *\n";
		echo "Content-Signal: ai-train=yes, search=yes, ai-input=yes\n";
		echo "Disallow: /wp-admin\n";
		echo "Allow: /wp-admin/admin-ajax.php\n\n";
		echo 'Sitemap: ' . home_url( '/sitemap.xml' ) . "\n\n";
		echo "# LLM site özeti: /llms.txt\n";
		exit;
	}
	if ( 'llms' === $route ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo veng_build_llms_txt();
		exit;
	}
	if ( 'manifest' === $route ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo veng_build_manifest();
		exit;
	}
	if ( 'sw' === $route ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		echo veng_build_service_worker();
		exit;
	}
	if ( 'assetlinks' === $route ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		echo veng_build_assetlinks();
		exit;
	}
}
add_action( 'template_redirect', 'veng_handle_custom_routes' );

/** PWA: manifest.json — Android'de "Ana ekrana ekle" ile uygulama gibi kurulum için. */
function veng_build_manifest() {
	$name = get_bloginfo( 'name' );
	$theme_color = get_theme_mod( 'veng_accent_color', '#5b21b6' );
	$manifest = array(
		'name'             => $name,
		'short_name'       => $name,
		'description'      => get_bloginfo( 'description' ) ?: ( $name . ' — güncel haberler' ),
		'start_url'        => home_url( '/?utm_source=pwa' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'orientation'      => 'portrait-primary',
		'background_color' => '#ffffff',
		'theme_color'      => $theme_color,
		'lang'             => 'tr-TR',
		'icons'            => array(
			array( 'src' => VENG_THEME_URI . '/assets/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ),
			array( 'src' => VENG_THEME_URI . '/assets/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ),
		),
	);
	return wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** PWA: temel offline destek — sayfa/görsel önbellekleme (network-first, cache fallback). */
function veng_build_service_worker() {
	return <<<JS
const VENG_CACHE = 'veng-haber-v1';
const OFFLINE_URL = '/';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== VENG_CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/wp-admin') || url.pathname.startsWith('/wp-json')) return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(VENG_CACHE).then((cache) => cache.put(event.request, copy));
        return response;
      })
      .catch(() =>
        caches.match(event.request).then((cached) => cached || caches.match(OFFLINE_URL))
      )
  );
});
JS;
}

function veng_disable_canonical_redirect_for_routes( $redirect_url ) {
	if ( get_query_var( 'veng_route' ) ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'veng_disable_canonical_redirect_for_routes' );

function veng_xml_esc( $str ) {
	return htmlspecialchars( $str, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function veng_build_sitemap() {
	$urls = array();
	$urls[] = array( 'loc' => home_url( '/' ), 'priority' => '1.0', 'changefreq' => 'daily' );

	foreach ( get_categories() as $cat ) {
		$urls[] = array( 'loc' => get_category_link( $cat->term_id ), 'priority' => '0.8', 'changefreq' => 'daily' );
	}
	foreach ( get_terms( array( 'taxonomy' => 'sehir', 'hide_empty' => false ) ) as $sehir ) {
		$urls[] = array( 'loc' => get_term_link( $sehir ), 'priority' => '0.6', 'changefreq' => 'daily' );
	}

	$two_days_ago = time() - 2 * DAY_IN_SECONDS;

	$q = new WP_Query( array( 'post_type' => array( 'post', 'makale' ), 'post_status' => 'publish', 'posts_per_page' => -1 ) );
	while ( $q->have_posts() ) {
		$q->the_post();
		$entry = array(
			'loc'        => get_permalink(),
			'lastmod'    => get_the_modified_date( 'c' ),
			'priority'   => '0.9',
			'changefreq' => 'hourly',
		);
		if ( get_post_time( 'U' ) > $two_days_ago && 'post' === get_post_type() ) {
			$entry['news'] = array(
				'title' => get_the_title(),
				'date'  => get_the_date( 'c' ),
			);
		}
		$urls[] = $entry;
	}
	wp_reset_postdata();

	foreach ( array( 'foto_galeri', 'video_galeri', 'firma', 'resmi_ilan' ) as $pt ) {
		$q2 = new WP_Query( array( 'post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => -1 ) );
		while ( $q2->have_posts() ) {
			$q2->the_post();
			$urls[] = array( 'loc' => get_permalink(), 'lastmod' => get_the_modified_date( 'c' ), 'priority' => '0.5', 'changefreq' => 'weekly' );
		}
		wp_reset_postdata();
	}

	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	foreach ( $urls as $u ) {
		$xml .= "  <url>\n    <loc>" . veng_xml_esc( $u['loc'] ) . "</loc>\n";
		if ( ! empty( $u['lastmod'] ) ) {
			$xml .= '    <lastmod>' . veng_xml_esc( $u['lastmod'] ) . "</lastmod>\n";
		}
		$xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
		$xml .= '    <priority>' . $u['priority'] . "</priority>\n";
		if ( ! empty( $u['news'] ) ) {
			$xml .= "    <news:news>\n      <news:publication>\n        <news:name>" . veng_xml_esc( get_bloginfo( 'name' ) ) . "</news:name>\n        <news:language>tr</news:language>\n      </news:publication>\n      <news:publication_date>" . veng_xml_esc( $u['news']['date'] ) . "</news:publication_date>\n      <news:title>" . veng_xml_esc( $u['news']['title'] ) . "</news:title>\n    </news:news>\n";
		}
		$xml .= "  </url>\n";
	}
	$xml .= '</urlset>';
	return $xml;
}

function veng_build_rss() {
	$q = new WP_Query( array( 'post_type' => array( 'post', 'makale' ), 'post_status' => 'publish', 'posts_per_page' => 40, 'orderby' => 'date', 'order' => 'DESC' ) );
	$items = '';
	while ( $q->have_posts() ) {
		$q->the_post();
		$cats = get_the_category();
		$items .= "    <item>\n";
		$items .= '      <title>' . veng_xml_esc( get_the_title() ) . "</title>\n";
		$items .= '      <link>' . veng_xml_esc( get_permalink() ) . "</link>\n";
		$items .= '      <guid isPermaLink="true">' . veng_xml_esc( get_permalink() ) . "</guid>\n";
		$items .= '      <description>' . veng_xml_esc( get_the_excerpt() ) . "</description>\n";
		if ( $cats ) {
			$items .= '      <category>' . veng_xml_esc( $cats[0]->name ) . "</category>\n";
		}
		$items .= '      <pubDate>' . get_the_date( 'D, d M Y H:i:s O' ) . "</pubDate>\n";
		$items .= "    </item>\n";
	}
	wp_reset_postdata();

	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<rss version="2.0"><channel>' . "\n";
	$xml .= '  <title>' . veng_xml_esc( get_bloginfo( 'name' ) ) . "</title>\n";
	$xml .= '  <link>' . veng_xml_esc( home_url( '/' ) ) . "</link>\n";
	$xml .= '  <description>' . veng_xml_esc( get_bloginfo( 'description' ) ) . "</description>\n";
	$xml .= "  <language>tr-TR</language>\n";
	$xml .= $items;
	$xml .= '</channel></rss>';
	return $xml;
}

/**
 * Android uygulaması (TWA) doğrulaması: bu dosya olmadan uygulama tarayıcı
 * çerçevesi (adres çubuğu) ile açılır. Keystore değişirse SHA-256 parmak izi
 * de güncellenmeli: keytool -list -v -keystore android.keystore -alias venghaber
 */
function veng_build_assetlinks() {
	$data = array(
		array(
			'relation' => array( 'delegate_permission/common.handle_all_urls' ),
			'target'   => array(
				'namespace'               => 'android_app',
				'package_name'            => 'com.venghaber.app',
				'sha256_cert_fingerprints' => array(
					'F0:BF:29:05:FE:7A:2D:FC:41:DC:67:2D:14:B3:9F:71:A0:A7:E0:4D:1B:84:1D:39:0C:07:CD:09:F2:02:29:5A',
				),
			),
		),
	);
	return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

function veng_build_llms_txt() {
	$name = get_bloginfo( 'name' );
	$home = home_url( '/' );
	return <<<TXT
# {$name}

> {$name} Haber Yazılımı Demosu (WordPress)

Bu site bir haber ve medya yönetim platformudur. Yayınlanan içerikler haber, makale, foto galeri, video galeri ve anket türlerini kapsar.

## Ana Sayfa ve Keşif
- Ana Sayfa: {$home}
- RSS Akışı: {$home}rss.xml
- Site Haritası: {$home}sitemap.xml

## Modüller
- Foto Galeri: {$home}foto-galeri/
- Video Galeri: {$home}video-galeri/
- Firma Rehberi: {$home}firma-rehberi/
- Anketler: {$home}anket/
- Resmi İlanlar: {$home}resmi-ilanlar/
- Yerel Haberler: {$home}yerel-haberler/

## Optional
- robots.txt: {$home}robots.txt
- ads.txt: {$home}ads.txt
TXT;
}
