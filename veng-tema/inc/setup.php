<?php
/**
 * Tema kurulumu: destekler, menüler, resim boyutları
 */

function veng_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'align-wide' );

	set_post_thumbnail_size( 1200, 800, true );
	add_image_size( 'veng-card', 600, 450, true );
	add_image_size( 'veng-thumb', 300, 200, true );

	register_nav_menus( array(
		'primary' => __( 'Ana Menü', 'veng-haber' ),
		'footer'  => __( 'Alt Menü', 'veng-haber' ),
	) );

	// Yorumlar tüm içerik tiplerinde varsayılan açık.
	add_post_type_support( 'makale', 'comments' );
}
add_action( 'after_setup_theme', 'veng_theme_setup' );

/**
 * Varsayılan kategorileri ana menüye otomatik ekle (ilk kurulum kolaylığı).
 */
function veng_maybe_generate_primary_menu() {
	if ( has_nav_menu( 'primary' ) ) {
		return;
	}
	$menu_id = wp_create_nav_menu( 'Ana Menü' );
	if ( is_wp_error( $menu_id ) ) {
		return;
	}
	$categories = get_categories( array( 'orderby' => 'id', 'order' => 'ASC' ) );
	foreach ( $categories as $cat ) {
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'     => $cat->name,
			'menu-item-object'    => 'category',
			'menu-item-object-id' => $cat->term_id,
			'menu-item-type'      => 'taxonomy',
			'menu-item-status'    => 'publish',
		) );
	}
	$extra = array(
		'Yazarlar'      => home_url( '/yazarlar/' ),
		'Foto Galeri'   => get_post_type_archive_link( 'foto_galeri' ),
		'Video Galeri'  => get_post_type_archive_link( 'video_galeri' ),
		'Firma Rehberi' => get_post_type_archive_link( 'firma' ),
		'Anketler'      => get_post_type_archive_link( 'anket' ),
		'Resmi İlanlar' => get_post_type_archive_link( 'resmi_ilan' ),
	);
	foreach ( $extra as $title => $url ) {
		if ( ! $url ) {
			continue;
		}
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'  => $title,
			'menu-item-url'    => $url,
			'menu-item-status' => 'publish',
			'menu-item-type'   => 'custom',
		) );
	}
	$locations = get_theme_mod( 'nav_menu_locations' );
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}
add_action( 'after_switch_theme', 'veng_maybe_generate_primary_menu' );

/**
 * Özet (excerpt) tüm CPT'lerde etkin, uzunluğu ayarlanmış.
 */
function veng_excerpt_length( $length ) {
	return 30;
}
add_filter( 'excerpt_length', 'veng_excerpt_length' );

function veng_excerpt_more( $more ) {
	return '…';
}
add_filter( 'excerpt_more', 'veng_excerpt_more' );

/**
 * Yazar arşivinde haber + makale birlikte listelensin, /yazarlar/ URL tabanı kullanılsın.
 */
function veng_author_archive_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_author() ) {
		$query->set( 'post_type', array( 'post', 'makale' ) );
	}
}
add_action( 'pre_get_posts', 'veng_author_archive_query' );

function veng_set_author_base( $wp_rewrite ) {
	$wp_rewrite->author_base = 'yazarlar';
}
add_action( 'init', function () {
	global $wp_rewrite;
	veng_set_author_base( $wp_rewrite );
} );
