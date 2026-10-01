<?php get_header(); ?>

<div class="container layout">
	<div style="min-width:0;">

		<h1 class="sr-only"><?php bloginfo( 'name' ); ?><?php echo get_bloginfo( 'description' ) ? ' — ' . esc_html( get_bloginfo( 'description' ) ) : ' — Güncel Haberler'; ?></h1>

		<?php veng_render_hero_slider(); ?>

		<?php
		// orderby=id yalnızca en eski oluşturulan 6 kategoriyi gösteriyordu. orderby=count da
		// güvenilmezdi: WordPress'in kategori sayısı önbelleği, toplu SQL silme işlemlerinden
		// sonra güncellenmeden eski/yanlış değerde kalabiliyor (ör. gerçekte 2 yazısı olan bir
		// kategori önbellekte "70" görünebiliyor) — bu da hangi 6 kategorinin seçildiğini
		// bozuyordu. Bunun yerine GERÇEK, o anki yayınlanmış yazı sayısını doğrudan sorgula.
		global $wpdb;
		$top_cat_ids = $wpdb->get_col(
			"SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt
			 INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			 WHERE tt.taxonomy = 'category' AND p.post_status = 'publish' AND p.post_type = 'post'
			 GROUP BY tt.term_id ORDER BY COUNT(*) DESC LIMIT 6"
		);
		$top_cats = array_filter( array_map( 'get_category', $top_cat_ids ) );
		foreach ( $top_cats as $cat ) :
			$cat_q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 6, 'cat' => $cat->term_id ) );
			if ( ! $cat_q->have_posts() ) { wp_reset_postdata(); continue; }
			?>
			<section class="section-title-wrap">
				<div class="section-title">
					<h2><span class="bar"></span><?php echo esc_html( $cat->name ); ?></h2>
					<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">Tümünü Gör →</a>
				</div>
				<div class="grid">
					<?php while ( $cat_q->have_posts() ) : $cat_q->the_post(); veng_render_gcard( get_the_ID() ); endwhile; ?>
				</div>
			</section>
			<?php wp_reset_postdata(); ?>
		<?php endforeach; ?>

		<div style="display:grid;grid-template-columns:1fr;gap:32px;margin-top:16px;">
			<?php
			$gal_q = new WP_Query( array( 'post_type' => 'foto_galeri', 'posts_per_page' => 4 ) );
			if ( $gal_q->have_posts() ) : ?>
			<section>
				<div class="section-title"><h2><span class="bar"></span>Foto Galeri</h2><a href="<?php echo esc_url( get_post_type_archive_link( 'foto_galeri' ) ); ?>">Tümünü Gör →</a></div>
				<div class="grid" style="grid-template-columns:repeat(2,1fr);">
					<?php while ( $gal_q->have_posts() ) : $gal_q->the_post(); veng_render_gcard( get_the_ID() ); endwhile; wp_reset_postdata(); ?>
				</div>
			</section>
			<?php endif; ?>

			<?php
			$vid_q = new WP_Query( array( 'post_type' => 'video_galeri', 'posts_per_page' => 4 ) );
			if ( $vid_q->have_posts() ) : ?>
			<section>
				<div class="section-title"><h2><span class="bar"></span>Video Galeri</h2><a href="<?php echo esc_url( get_post_type_archive_link( 'video_galeri' ) ); ?>">Tümünü Gör →</a></div>
				<div class="grid" style="grid-template-columns:repeat(2,1fr);">
					<?php while ( $vid_q->have_posts() ) : $vid_q->the_post(); veng_render_gcard( get_the_ID() ); endwhile; wp_reset_postdata(); ?>
				</div>
			</section>
			<?php endif; ?>
		</div>
	</div>

	<?php veng_render_sidebar(); ?>
</div>

<?php get_footer(); ?>
