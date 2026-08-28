<?php get_header(); ?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;">Firma Rehberi</h1>
	<?php foreach ( get_terms( array( 'taxonomy' => 'firma_kategori', 'hide_empty' => true ) ) as $cat_term ) :
		$q = new WP_Query( array( 'post_type' => 'firma', 'posts_per_page' => -1, 'tax_query' => array( array( 'taxonomy' => 'firma_kategori', 'field' => 'term_id', 'terms' => $cat_term->term_id ) ) ) );
		if ( ! $q->have_posts() ) { wp_reset_postdata(); continue; }
		?>
		<section style="margin-bottom:32px;">
			<h2 style="font-size:17px;font-weight:700;margin-bottom:14px;"><?php echo esc_html( $cat_term->name ); ?></h2>
			<div class="grid">
				<?php while ( $q->have_posts() ) : $q->the_post(); ?>
					<div class="card">
						<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
							<?php if ( has_post_thumbnail() ) echo get_the_post_thumbnail( get_the_ID(), 'veng-thumb', array( 'style' => 'width:44px;height:44px;border-radius:8px;object-fit:cover;', 'loading' => 'lazy' ) ); ?>
							<strong><?php the_title(); ?></strong>
						</div>
						<?php if ( get_the_excerpt() ) : ?><p style="font-size:13px;color:var(--muted);margin-bottom:10px;"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
						<div style="font-size:13px;color:var(--muted);display:flex;flex-direction:column;gap:4px;">
							<?php $adres = get_post_meta( get_the_ID(), '_veng_adres', true ); if ( $adres ) : ?><span>📍 <?php echo esc_html( $adres ); ?></span><?php endif; ?>
							<?php $tel = get_post_meta( get_the_ID(), '_veng_telefon', true ); if ( $tel ) : ?><span>📞 <?php echo esc_html( $tel ); ?></span><?php endif; ?>
							<?php $web = get_post_meta( get_the_ID(), '_veng_website', true ); if ( $web ) : ?><span>🌐 <a href="<?php echo esc_url( $web ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', $web ) ); ?></a></span><?php endif; ?>
						</div>
					</div>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
<?php get_footer(); ?>
