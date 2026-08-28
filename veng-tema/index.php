<?php get_header(); ?>
<div class="container layout">
	<div style="min-width:0;">
		<?php if ( is_search() ) : ?>
			<h1 style="font-size:22px;font-weight:800;margin-bottom:8px;">Arama Sonuçları</h1>
			<p style="font-size:14px;color:var(--muted);margin-bottom:24px;">&quot;<?php echo esc_html( get_search_query() ); ?>&quot; için <?php echo (int) $GLOBALS['wp_query']->found_posts; ?> sonuç bulundu.</p>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div style="display:flex;flex-direction:column;gap:16px;">
				<?php while ( have_posts() ) : the_post(); veng_render_hcard( get_the_ID() ); endwhile; ?>
			</div>
			<?php veng_pagination(); ?>
		<?php else : ?>
			<p style="color:var(--muted);">Sonuç bulunamadı.</p>
		<?php endif; ?>
	</div>
	<?php veng_render_sidebar(); ?>
</div>
<?php get_footer(); ?>
