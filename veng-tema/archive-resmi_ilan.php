<?php get_header(); ?>
<div class="container" style="max-width:800px;">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0 6px;">Resmi İlanlar</h1>
	<p style="font-size:13px;color:var(--muted);margin-bottom:24px;">Basın İlan Kurumu (BİK) mevzuatı kapsamında yayınlanan resmi ilanlar.</p>
	<div style="display:flex;flex-direction:column;gap:12px;">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			<a href="<?php the_permalink(); ?>" class="card">
				<div style="font-size:12px;color:var(--muted);margin-bottom:4px;"><?php echo esc_html( get_post_meta( get_the_ID(), '_veng_kurum', true ) ); ?> · <?php echo esc_html( get_the_date() ); ?></div>
				<strong><?php the_title(); ?></strong>
			</a>
		<?php endwhile; veng_pagination(); else : ?>
			<p style="color:var(--muted);">Yayında resmi ilan bulunmuyor.</p>
		<?php endif; ?>
	</div>
</div>
<?php get_footer(); ?>
