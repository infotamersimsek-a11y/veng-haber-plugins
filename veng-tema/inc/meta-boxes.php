<?php
/**
 * Basit meta kutuları: firma, resmi ilan, anket
 */

/** RSS içe aktarıcının kaynak bilgisini yazabilmesi için REST API'ye açık meta alanları. */
function veng_register_rest_meta() {
	register_post_meta( 'post', '_veng_source_url', array(
		'type' => 'string', 'single' => true, 'show_in_rest' => true,
		'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );
	register_post_meta( 'post', '_veng_source_name', array(
		'type' => 'string', 'single' => true, 'show_in_rest' => true,
		'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );
}
add_action( 'init', 'veng_register_rest_meta' );

function veng_add_meta_boxes() {
	add_meta_box( 'veng_firma_meta', 'Firma Bilgileri', 'veng_render_firma_meta_box', 'firma', 'normal', 'high' );
	add_meta_box( 'veng_ilan_meta', 'İlan Bilgileri', 'veng_render_ilan_meta_box', 'resmi_ilan', 'normal', 'high' );
	add_meta_box( 'veng_anket_meta', 'Anket Seçenekleri', 'veng_render_anket_meta_box', 'anket', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'veng_add_meta_boxes' );

function veng_render_firma_meta_box( $post ) {
	wp_nonce_field( 'veng_save_meta', 'veng_meta_nonce' );
	$adres   = get_post_meta( $post->ID, '_veng_adres', true );
	$telefon = get_post_meta( $post->ID, '_veng_telefon', true );
	$website = get_post_meta( $post->ID, '_veng_website', true );
	?>
	<p><label>Adres<br /><input type="text" name="veng_adres" value="<?php echo esc_attr( $adres ); ?>" style="width:100%" /></label></p>
	<p><label>Telefon<br /><input type="text" name="veng_telefon" value="<?php echo esc_attr( $telefon ); ?>" style="width:100%" /></label></p>
	<p><label>Web Sitesi<br /><input type="url" name="veng_website" value="<?php echo esc_attr( $website ); ?>" style="width:100%" /></label></p>
	<?php
}

function veng_render_ilan_meta_box( $post ) {
	wp_nonce_field( 'veng_save_meta', 'veng_meta_nonce' );
	$kurum     = get_post_meta( $post->ID, '_veng_kurum', true );
	$son_tarih = get_post_meta( $post->ID, '_veng_son_tarih', true );
	?>
	<p><label>Kurum<br /><input type="text" name="veng_kurum" value="<?php echo esc_attr( $kurum ); ?>" style="width:100%" /></label></p>
	<p><label>Son Başvuru/Tarih<br /><input type="date" name="veng_son_tarih" value="<?php echo esc_attr( $son_tarih ); ?>" /></label></p>
	<?php
}

function veng_render_anket_meta_box( $post ) {
	wp_nonce_field( 'veng_save_meta', 'veng_meta_nonce' );
	$secenekler = get_post_meta( $post->ID, '_veng_secenekler', true );
	if ( ! is_array( $secenekler ) ) {
		$secenekler = array();
	}
	$lines = array();
	foreach ( $secenekler as $s ) {
		$lines[] = $s['text'] . ' :: ' . intval( $s['votes'] );
	}
	?>
	<p>Her satıra bir seçenek yazın. Oy sayısını değiştirmek isterseniz <code>Seçenek metni :: oy sayısı</code> biçimini kullanabilirsiniz (opsiyonel).</p>
	<textarea name="veng_secenekler" rows="6" style="width:100%"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
	<?php
}

function veng_save_meta_boxes( $post_id ) {
	if ( ! isset( $_POST['veng_meta_nonce'] ) || ! wp_verify_nonce( $_POST['veng_meta_nonce'], 'veng_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( isset( $_POST['veng_adres'] ) ) {
		update_post_meta( $post_id, '_veng_adres', sanitize_text_field( $_POST['veng_adres'] ) );
	}
	if ( isset( $_POST['veng_telefon'] ) ) {
		update_post_meta( $post_id, '_veng_telefon', sanitize_text_field( $_POST['veng_telefon'] ) );
	}
	if ( isset( $_POST['veng_website'] ) ) {
		update_post_meta( $post_id, '_veng_website', esc_url_raw( $_POST['veng_website'] ) );
	}
	if ( isset( $_POST['veng_kurum'] ) ) {
		update_post_meta( $post_id, '_veng_kurum', sanitize_text_field( $_POST['veng_kurum'] ) );
	}
	if ( isset( $_POST['veng_son_tarih'] ) ) {
		update_post_meta( $post_id, '_veng_son_tarih', sanitize_text_field( $_POST['veng_son_tarih'] ) );
	}
	if ( isset( $_POST['veng_secenekler'] ) ) {
		$existing = get_post_meta( $post_id, '_veng_secenekler', true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing_votes = array();
		foreach ( $existing as $e ) {
			$existing_votes[ $e['text'] ] = intval( $e['votes'] );
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", $_POST['veng_secenekler'] ) ) );
		$options = array();
		foreach ( $lines as $line ) {
			if ( strpos( $line, '::' ) !== false ) {
				list( $text, $votes ) = array_map( 'trim', explode( '::', $line, 2 ) );
				$options[] = array( 'text' => sanitize_text_field( $text ), 'votes' => intval( $votes ) );
			} else {
				$text  = sanitize_text_field( $line );
				$votes = isset( $existing_votes[ $text ] ) ? $existing_votes[ $text ] : 0;
				$options[] = array( 'text' => $text, 'votes' => $votes );
			}
		}
		update_post_meta( $post_id, '_veng_secenekler', $options );
	}
}
add_action( 'save_post', 'veng_save_meta_boxes' );

/**
 * Yazar profili için ek alan: unvan (ör. Köşe Yazarı, Spor Yorumcusu).
 */
function veng_user_extra_fields( $user ) {
	$title = get_the_author_meta( 'veng_title', $user->ID );
	?>
	<h2>Veng Haber Yazar Bilgisi</h2>
	<table class="form-table">
		<tr>
			<th><label for="veng_title">Unvan</label></th>
			<td><input type="text" name="veng_title" id="veng_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" placeholder="Örn: Köşe Yazarı" /></td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'veng_user_extra_fields' );
add_action( 'edit_user_profile', 'veng_user_extra_fields' );

function veng_save_user_extra_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['veng_title'] ) ) {
		update_user_meta( $user_id, 'veng_title', sanitize_text_field( $_POST['veng_title'] ) );
	}
}
add_action( 'personal_options_update', 'veng_save_user_extra_fields' );
add_action( 'edit_user_profile_update', 'veng_save_user_extra_fields' );
