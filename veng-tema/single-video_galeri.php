<?php get_header(); while ( have_posts() ) : the_post();
	$cats = get_the_terms( get_the_ID(), 'video_kategori' );
	?>
<div class="container" style="max-width:800px;">
	<?php if ( $cats ) : ?><span style="font-size:12px;font-weight:700;color:var(--theme);"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
	<h1 style="font-size:24px;font-weight:800;margin:6px 0 16px;"><?php the_title(); ?></h1>
	<div style="border-radius:12px;overflow:hidden;background:#000;margin-bottom:16px;">
		<?php the_content(); ?>
	</div>
	<div style="margin-bottom:16px;"><?php veng_share_buttons(); ?></div>
	<?php if ( get_the_excerpt() ) : ?><p style="color:var(--muted);"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
</div>
<?php endwhile; get_footer(); ?>
