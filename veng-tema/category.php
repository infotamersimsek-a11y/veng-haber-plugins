<?php get_header(); $cat = get_queried_object(); ?>
<div class="container layout">
	<div style="min-width:0;">
		<h1 style="font-size:26px;font-weight:800;margin-bottom:24px;display:flex;align-items:center;gap:8px;">
			<span class="bar" style="width:8px;height:24px;border-radius:4px;background:var(--theme);display:inline-block;"></span>
			<?php echo esc_html( $cat->name ); ?>
		</h1>
		<?php if ( have_posts() ) : ?>
			<div class="grid">
				<?php while ( have_posts() ) : the_post(); veng_render_gcard( get_the_ID() ); endwhile; ?>
			</div>
			<?php veng_pagination(); ?>
		<?php else : ?>
			<p style="color:var(--muted);">Bu kategoride henüz haber bulunmuyor.</p>
		<?php endif; ?>
	</div>
	<?php veng_render_sidebar(); ?>
</div>
<?php get_footer(); ?>
