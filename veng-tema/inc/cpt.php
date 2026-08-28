<?php
/**
 * Özel içerik tipleri (Custom Post Types)
 */

function veng_register_post_types() {

	register_post_type( 'makale', array(
		'labels' => array(
			'name'          => 'Makaleler',
			'singular_name' => 'Makale',
			'add_new_item'  => 'Yeni Makale Ekle',
			'edit_item'     => 'Makaleyi Düzenle',
			'all_items'     => 'Tüm Makaleler',
		),
		'public'       => true,
		'has_archive'  => 'makale',
		'rewrite'      => array( 'slug' => 'makale' ),
		'menu_icon'    => 'dashicons-edit-page',
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments', 'revisions' ),
		'show_in_rest' => true,
		'taxonomies'   => array( 'post_tag', 'rozet' ),
	) );

	register_post_type( 'foto_galeri', array(
		'labels' => array(
			'name'          => 'Foto Galeriler',
			'singular_name' => 'Foto Galeri',
			'add_new_item'  => 'Yeni Galeri Ekle',
			'edit_item'     => 'Galeriyi Düzenle',
			'all_items'     => 'Tüm Foto Galeriler',
		),
		'public'       => true,
		'has_archive'  => 'foto-galeri',
		'rewrite'      => array( 'slug' => 'foto-galeri' ),
		'menu_icon'    => 'dashicons-format-gallery',
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
		'show_in_rest' => true,
		'taxonomies'   => array( 'galeri_kategori' ),
	) );

	register_post_type( 'video_galeri', array(
		'labels' => array(
			'name'          => 'Video Galeriler',
			'singular_name' => 'Video',
			'add_new_item'  => 'Yeni Video Ekle',
			'edit_item'     => 'Videoyu Düzenle',
			'all_items'     => 'Tüm Videolar',
		),
		'public'       => true,
		'has_archive'  => 'video-galeri',
		'rewrite'      => array( 'slug' => 'video-galeri' ),
		'menu_icon'    => 'dashicons-format-video',
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
		'show_in_rest' => true,
		'taxonomies'   => array( 'video_kategori' ),
	) );

	register_post_type( 'firma', array(
		'labels' => array(
			'name'          => 'Firma Rehberi',
			'singular_name' => 'Firma',
			'add_new_item'  => 'Yeni Firma Ekle',
			'edit_item'     => 'Firmayı Düzenle',
			'all_items'     => 'Firma Rehberi',
		),
		'public'       => true,
		'has_archive'  => 'firma-rehberi',
		'rewrite'      => array( 'slug' => 'firma-rehberi' ),
		'menu_icon'    => 'dashicons-store',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		'show_in_rest' => true,
		'taxonomies'   => array( 'firma_kategori' ),
	) );

	register_post_type( 'resmi_ilan', array(
		'labels' => array(
			'name'          => 'Resmi İlanlar',
			'singular_name' => 'Resmi İlan',
			'add_new_item'  => 'Yeni İlan Ekle',
			'edit_item'     => 'İlanı Düzenle',
			'all_items'     => 'Resmi İlanlar',
		),
		'public'       => true,
		'has_archive'  => 'resmi-ilanlar',
		'rewrite'      => array( 'slug' => 'resmi-ilanlar' ),
		'menu_icon'    => 'dashicons-media-document',
		'supports'     => array( 'title', 'editor', 'revisions' ),
		'show_in_rest' => true,
	) );

	register_post_type( 'anket', array(
		'labels' => array(
			'name'          => 'Anketler',
			'singular_name' => 'Anket',
			'add_new_item'  => 'Yeni Anket Ekle',
			'edit_item'     => 'Anketi Düzenle',
			'all_items'     => 'Anketler',
		),
		'public'       => true,
		'has_archive'  => 'anketler',
		'rewrite'      => array( 'slug' => 'anket' ),
		'menu_icon'    => 'dashicons-chart-bar',
		'supports'     => array( 'title', 'revisions' ),
		'show_in_rest' => false,
	) );

	// Gizli tipler: bülten aboneleri ve iletişim mesajları (yalnızca yönetim panelinde listelenir).
	register_post_type( 'ne_kaydi', array(
		'labels'       => array( 'name' => 'Bülten Aboneleri', 'singular_name' => 'Abone' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'veng-mesajlar',
		'supports'     => array( 'title' ),
	) );

	register_post_type( 'iletisim_mesaji', array(
		'labels'       => array( 'name' => 'İletişim Mesajları', 'singular_name' => 'Mesaj' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'veng-mesajlar',
		'supports'     => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'veng_register_post_types' );

/**
 * "Mesajlar" için basit bir üst menü grubu.
 */
function veng_register_messages_menu() {
	add_menu_page( 'Mesajlar', 'Mesajlar', 'edit_posts', 'veng-mesajlar', function () {
		echo '<div class="wrap"><h1>Mesajlar</h1><p>Sol menüden Bülten Aboneleri veya İletişim Mesajları\'nı seçin.</p></div>';
	}, 'dashicons-email', 27 );
}
add_action( 'admin_menu', 'veng_register_messages_menu' );
