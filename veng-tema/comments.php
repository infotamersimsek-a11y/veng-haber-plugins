<?php
if ( post_password_required() ) return;
?>
<h2 style="font-size:18px;font-weight:800;margin-bottom:16px;">
	Yorumlar (<?php echo get_comments_number(); ?>)
</h2>

<?php if ( have_comments() ) : ?>
	<div style="margin-bottom:24px;">
		<?php
		wp_list_comments( array(
			'style'      => 'div',
			'short_ping' => true,
			'callback'   => function ( $comment, $args, $depth ) {
				?>
				<div class="comment-item">
					<div style="display:flex;justify-content:space-between;margin-bottom:4px;">
						<strong style="font-size:14px;"><?php comment_author(); ?></strong>
						<span style="font-size:12px;color:var(--muted);"><?php echo esc_html( veng_time_ago( strtotime( get_comment_date( 'c' ) ) ) ); ?></span>
					</div>
					<p style="font-size:14px;color:var(--muted);margin:0;"><?php comment_text(); ?></p>
				</div>
				<?php
			},
		) );
		?>
	</div>
<?php else : ?>
	<p style="font-size:14px;color:var(--muted);margin-bottom:20px;">Henüz yorum yapılmamış. İlk yorumu siz yapın.</p>
<?php endif; ?>

<?php if ( comments_open() ) :
	comment_form( array(
		'title_reply'          => '',
		'class_form'           => 'comment-form',
		'comment_field'        => '<textarea name="comment" id="comment" placeholder="Yorumunuz" rows="4" required></textarea>',
		'fields'               => array(
			'author' => '<input type="text" name="author" id="author" placeholder="Adınız" required />',
			'email'  => '<input type="email" name="email" id="email" placeholder="E-posta (yayınlanmaz)" required />',
		),
		'label_submit'         => 'Yorum Yap',
		'submit_button'        => '<button type="submit" class="btn">%4$s</button>',
		'comment_notes_before' => '',
		'comment_notes_after'  => '',
	) );
endif; ?>
