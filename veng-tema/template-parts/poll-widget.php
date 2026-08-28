<?php
/**
 * Anket widget'ı. Kullanım: get_template_part( 'template-parts/poll-widget', null, array( 'poll_id' => $id ) );
 */
$poll_id = $args['poll_id'] ?? get_the_ID();
$options = get_post_meta( $poll_id, '_veng_secenekler', true );
if ( ! is_array( $options ) || empty( $options ) ) return;
$total = array_sum( wp_list_pluck( $options, 'votes' ) );
$voted = isset( $_COOKIE[ 'veng_voted_' . $poll_id ] );
?>
<div class="card poll-widget" data-poll-id="<?php echo esc_attr( $poll_id ); ?>">
	<div class="widget-title">ANKET</div>
	<div style="font-weight:700;margin-bottom:12px;"><?php echo esc_html( get_the_title( $poll_id ) ); ?></div>
	<?php foreach ( $options as $i => $opt ) :
		$pct = $total > 0 ? round( ( $opt['votes'] / $total ) * 100 ) : 0;
		?>
		<button class="poll-option" <?php echo $voted ? 'disabled' : ''; ?>>
			<?php if ( $voted ) : ?><span class="poll-option-fill" style="width:<?php echo esc_attr( $pct ); ?>%;"></span><?php endif; ?>
			<span class="poll-option-row">
				<span><?php echo esc_html( $opt['text'] ); ?></span>
				<?php if ( $voted ) : ?><span class="poll-pct"><?php echo esc_html( '%' . $pct ); ?></span><?php endif; ?>
			</span>
		</button>
	<?php endforeach; ?>
	<?php if ( $voted ) : ?><div style="font-size:12px;color:var(--muted);"><?php echo intval( $total ); ?> oy</div><?php endif; ?>
</div>
