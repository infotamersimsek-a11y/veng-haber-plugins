<?php get_header(); $term = get_queried_object(); ?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;"><?php echo ( is_tax() && $term ) ? esc_html( $term->name . ' ' ) : ''; ?>Foto Galeri</h1>
	<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;">
		<?php foreach ( get_terms( array( 'taxonomy' => 'galeri_kategori', 'hide_empty' => false ) ) as $t ) : ?>
			<a href="<?php echo esc_url( get_term_link( $t ) ); ?>" style="padding:6px 14px;border-radius:999px;border:1px solid var(--border);font-size:13px;font-weight:700;"><?php echo esc_html( $t->name ); ?></a>
		<?php endforeach; ?>
	</div>
	<div class="grid">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); veng_render_gcard( get_the_ID() ); endwhile; ?>
			<?php veng_pagination(); ?>
		<?php else : ?>
			<p style="color:var(--muted);">Galeri bulunamadı.</p>
		<?php endif; ?>
	</div>
</div>
<?php get_footer(); ?>
