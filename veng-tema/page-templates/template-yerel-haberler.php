<?php
/* Template Name: Yerel Haberler */
get_header();
$cities = get_terms( array( 'taxonomy' => 'sehir', 'hide_empty' => false ) );
?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;">Yerel Haberler</h1>
	<div class="grid">
		<?php foreach ( $cities as $city ) : ?>
			<a href="<?php echo esc_url( get_term_link( $city ) ); ?>" class="card" style="display:flex;align-items:center;gap:12px;">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--theme)" stroke-width="2"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
				<div>
					<div style="font-weight:700;"><?php echo esc_html( $city->name ); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo intval( $city->count ); ?> haber</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php get_footer(); ?>
