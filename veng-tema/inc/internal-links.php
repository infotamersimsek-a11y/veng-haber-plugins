<?php
/**
 * Otomatik site içi bağlantılama: haber/makale içeriğinde geçen kategori ve
 * etiket isimlerini ilk geçtikleri yerde ilgili arşiv sayfasına bağlar.
 * SEO ve kullanıcı gezinmesi için — her yazıda en fazla 4 otomatik link.
 */

function veng_auto_internal_links( $content ) {
	if ( ! is_singular( array( 'post', 'makale' ) ) || is_admin() || is_feed() ) {
		return $content;
	}

	global $post;
	if ( ! $post ) {
		return $content;
	}

	$current_url = get_permalink( $post );
	$targets = array();

	foreach ( get_categories() as $cat ) {
		$url = get_category_link( $cat->term_id );
		if ( $url !== $current_url ) {
			$targets[ $cat->name ] = $url;
		}
	}
	foreach ( get_tags() as $tag ) {
		$url = get_tag_link( $tag->term_id );
		if ( $url !== $current_url ) {
			$targets[ $tag->name ] = $url;
		}
	}

	if ( ! $targets ) {
		return $content;
	}

	// Uzun terimler önce denensin ("Yapay Zeka" "Zeka"dan önce eşleşsin).
	uksort( $targets, function ( $a, $b ) {
		return mb_strlen( $b ) <=> mb_strlen( $a );
	} );

	$max_links = 4;
	$linked = 0;
	$used = array();

	// İçeriği etiketlere göre parçala; yalnızca düz metin parçalarında link ekle,
	// mevcut <a>...</a> blokları ve diğer HTML etiketlerine dokunma.
	$parts = preg_split( '/(<a[^>]*>.*?<\/a>|<[^>]+>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! $parts ) {
		return $content;
	}

	foreach ( $parts as &$part ) {
		if ( $linked >= $max_links ) {
			break;
		}
		if ( '' === $part || '<' === $part[0] ) {
			continue; // HTML etiketi / mevcut link bloğu — dokunma.
		}

		foreach ( $targets as $name => $url ) {
			if ( $linked >= $max_links ) {
				break;
			}
			if ( isset( $used[ $name ] ) || mb_strlen( $name ) < 4 ) {
				continue;
			}
			$pattern = '/\b(' . preg_quote( $name, '/' ) . ')\b/iu';
			if ( preg_match( $pattern, $part ) ) {
				$part = preg_replace(
					$pattern,
					'<a href="' . esc_url( $url ) . '" class="veng-auto-link">$1</a>',
					$part,
					1
				);
				$used[ $name ] = true;
				$linked++;
			}
		}
	}
	unset( $part );

	return implode( '', $parts );
}
add_filter( 'the_content', 'veng_auto_internal_links', 20 );
