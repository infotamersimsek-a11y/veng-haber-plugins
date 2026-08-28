<?php get_header(); $sehir = get_queried_object(); ?>
<div class="container layout">
	<div style="min-width:0;">
		<h1 style="font-size:26px;font-weight:800;margin-bottom:24px;"><?php echo esc_html( $sehir->name ); ?> Haberleri</h1>
		<?php if ( have_posts() ) : ?>
			<div class="grid">
				<?php while ( have_posts() ) : the_post(); veng_render_gcard( get_the_ID() ); endwhile; ?>
			</div>
			<?php veng_pagination(); ?>
		<?php else : ?>
			<p style="color:var(--muted);">Bu şehir için henüz haber bulunmuyor.</p>
		<?php endif; ?>
	</div>
	<?php veng_render_sidebar(); ?>
</div>
<?php get_footer(); ?>
