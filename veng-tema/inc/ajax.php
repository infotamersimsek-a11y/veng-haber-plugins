<?php
/**
 * AJAX uç noktaları: anket oyu, bülten kaydı, iletişim formu
 */

function veng_ajax_vote_poll() {
	check_ajax_referer( 'veng_nonce', 'nonce' );

	$poll_id = isset( $_POST['poll_id'] ) ? intval( $_POST['poll_id'] ) : 0;
	$index   = isset( $_POST['option_index'] ) ? intval( $_POST['option_index'] ) : -1;

	if ( ! $poll_id || 'anket' !== get_post_type( $poll_id ) ) {
		wp_send_json_error( array( 'message' => 'Anket bulunamadı.' ), 404 );
	}

	$cookie_key = 'veng_voted_' . $poll_id;
	if ( isset( $_COOKIE[ $cookie_key ] ) ) {
		wp_send_json_error( array( 'message' => 'Bu ankete zaten oy verdiniz.' ), 400 );
	}

	$options = get_post_meta( $poll_id, '_veng_secenekler', true );
	if ( ! is_array( $options ) || ! isset( $options[ $index ] ) ) {
		wp_send_json_error( array( 'message' => 'Seçenek bulunamadı.' ), 404 );
	}

	$options[ $index ]['votes'] = intval( $options[ $index ]['votes'] ) + 1;
	update_post_meta( $poll_id, '_veng_secenekler', $options );

	setcookie( $cookie_key, '1', time() + YEAR_IN_SECONDS, '/' );

	wp_send_json_success( array( 'options' => $options ) );
}
add_action( 'wp_ajax_veng_vote_poll', 'veng_ajax_vote_poll' );
add_action( 'wp_ajax_nopriv_veng_vote_poll', 'veng_ajax_vote_poll' );

function veng_ajax_newsletter() {
	check_ajax_referer( 'veng_nonce', 'nonce' );
	$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Geçerli bir e-posta girin.' ), 400 );
	}

	$existing = get_page_by_title( $email, OBJECT, 'ne_kaydi' );
	if ( ! $existing ) {
		wp_insert_post( array(
			'post_type'   => 'ne_kaydi',
			'post_title'  => $email,
			'post_status' => 'publish',
		) );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_veng_newsletter', 'veng_ajax_newsletter' );
add_action( 'wp_ajax_nopriv_veng_newsletter', 'veng_ajax_newsletter' );

function veng_ajax_contact() {
	check_ajax_referer( 'veng_nonce', 'nonce' );
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

	if ( ! $name || ! is_email( $email ) || ! $subject || ! $message ) {
		wp_send_json_error( array( 'message' => 'Tüm alanları doldurun.' ), 400 );
	}

	wp_insert_post( array(
		'post_type'    => 'iletisim_mesaji',
		'post_title'   => $subject . ' — ' . $name,
		'post_content' => "E-posta: {$email}\n\n{$message}",
		'post_status'  => 'publish',
	) );

	wp_send_json_success();
}
add_action( 'wp_ajax_veng_contact', 'veng_ajax_contact' );
add_action( 'wp_ajax_nopriv_veng_contact', 'veng_ajax_contact' );
