<?php get_header(); $term = get_queried_object(); ?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;"><?php echo esc_html( $term->name ); ?> Video Galeri</h1>
	<div class="grid">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); veng_render_gcard( get_the_ID() ); endwhile; ?>
			<?php veng_pagination(); ?>
		<?php else : ?>
			<p style="color:var(--muted);">Bu kategoride video yok.</p>
		<?php endif; ?>
	</div>
</div>
<?php get_footer(); ?>
