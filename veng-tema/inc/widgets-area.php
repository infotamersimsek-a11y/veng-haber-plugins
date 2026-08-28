<?php
/**
 * Widget alanları (opsiyonel, ileride genişletmek için)
 */

function veng_register_sidebars() {
	register_sidebar( array(
		'name'          => 'Alt Bilgi Widget Alanı',
		'id'            => 'veng-footer',
		'before_widget' => '<div class="card">',
		'after_widget'  => '</div>',
		'before_title'  => '<div class="widget-title">',
		'after_title'   => '</div>',
	) );
}
add_action( 'widgets_init', 'veng_register_sidebars' );
