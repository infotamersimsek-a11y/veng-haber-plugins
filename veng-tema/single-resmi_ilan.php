<?php get_header(); while ( have_posts() ) : the_post();
	$kurum = get_post_meta( get_the_ID(), '_veng_kurum', true );
	$son_tarih = get_post_meta( get_the_ID(), '_veng_son_tarih', true );
	?>
<div class="container" style="max-width:700px;">
	<div style="font-size:12px;color:var(--muted);margin:24px 0 4px;"><?php echo esc_html( $kurum ); ?> · <?php echo esc_html( get_the_date() ); ?></div>
	<h1 style="font-size:22px;font-weight:800;margin-bottom:16px;"><?php the_title(); ?></h1>
	<div class="article-content"><?php the_content(); ?></div>
	<?php if ( $son_tarih ) : ?>
		<div class="card" style="margin-top:20px;font-size:14px;"><strong>Son başvuru/tarih:</strong> <?php echo esc_html( date_i18n( 'd F Y', strtotime( $son_tarih ) ) ); ?></div>
	<?php endif; ?>
</div>
<?php endwhile; get_footer(); ?>
