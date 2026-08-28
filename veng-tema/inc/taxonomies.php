<?php
/**
 * Özel taksonomiler
 */

function veng_register_taxonomies() {

	register_taxonomy( 'sehir', array( 'post' ), array(
		'labels' => array(
			'name'          => 'Şehirler (Yerel Haber)',
			'singular_name' => 'Şehir',
			'add_new_item'  => 'Yeni Şehir Ekle',
		),
		'public'            => true,
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'yerel-haberler' ),
	) );

	register_taxonomy( 'rozet', array( 'post', 'makale' ), array(
		'labels' => array(
			'name'          => 'Rozetler',
			'singular_name' => 'Rozet',
		),
		'public'       => true,
		'hierarchical' => false,
		'show_in_rest' => true,
		'rewrite'      => array( 'slug' => 'rozet' ),
	) );

	register_taxonomy( 'galeri_kategori', array( 'foto_galeri' ), array(
		'labels'       => array( 'name' => 'Galeri Kategorileri', 'singular_name' => 'Kategori' ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite'      => array( 'slug' => 'foto-galeri-kategori' ),
	) );

	register_taxonomy( 'video_kategori', array( 'video_galeri' ), array(
		'labels'       => array( 'name' => 'Video Kategorileri', 'singular_name' => 'Kategori' ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite'      => array( 'slug' => 'video-galeri-kategori' ),
	) );

	register_taxonomy( 'firma_kategori', array( 'firma' ), array(
		'labels'       => array( 'name' => 'Firma Kategorileri', 'singular_name' => 'Kategori' ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite'      => array( 'slug' => 'firma-kategori' ),
	) );
}
add_action( 'init', 'veng_register_taxonomies' );

/**
 * Rozet taksonomisi için varsayılan terimler (öne çıkan, trend, editörün seçtikleri).
 */
function veng_maybe_seed_rozet_terms() {
	if ( ! term_exists( 'one-cikan', 'rozet' ) ) {
		wp_insert_term( 'Öne Çıkan', 'rozet', array( 'slug' => 'one-cikan' ) );
	}
	if ( ! term_exists( 'trend', 'rozet' ) ) {
		wp_insert_term( 'Trend', 'rozet', array( 'slug' => 'trend' ) );
	}
	if ( ! term_exists( 'editorun-secimi', 'rozet' ) ) {
		wp_insert_term( "Editörün Seçimi", 'rozet', array( 'slug' => 'editorun-secimi' ) );
	}
}
add_action( 'init', 'veng_maybe_seed_rozet_terms', 20 );
