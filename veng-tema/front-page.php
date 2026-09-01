<?php get_header(); ?>

<?php veng_render_special_day_banner(); ?>

<div class="container layout">
	<div style="min-width:0;">

		<h1 class="sr-only"><?php bloginfo( 'name' ); ?><?php echo get_bloginfo( 'description' ) ? ' — ' . esc_html( get_bloginfo( 'description' ) ) : ' — Güncel Haberler'; ?></h1>

		<?php
		$featured_q = new WP_Query( array(
			'post_type' => 'post', 'posts_per_page' => 5,
			'tax_query' => array( array( 'taxonomy' => 'rozet', 'field' => 'slug', 'terms' => 'one-cikan' ) ),
		) );
		if ( ! $featured_q->have_posts() ) {
			$featured_q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 5 ) );
		}
		$posts = $featured_q->posts;
		$main = $posts[0] ?? null;
		$side = array_slice( $posts, 1, 4 );
		?>

		<?php if ( $main ) : $cats = get_the_category( $main->ID ); ?>
		<section class="hero">
			<a class="hero-main" href="<?php echo esc_url( get_permalink( $main ) ); ?>">
				<?php echo get_the_post_thumbnail( $main, 'veng-card' ); ?>
				<div class="hero-overlay">
					<div>
						<?php if ( $cats ) : ?><span class="badge"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
						<h2><?php echo esc_html( get_the_title( $main ) ); ?></h2>
					</div>
				</div>
			</a>
			<div style="display:flex;flex-direction:column;gap:16px;">
				<?php foreach ( $side as $p ) : veng_render_hcard( $p->ID ); endforeach; ?>
			</div>
		</section>
		<?php endif; wp_reset_postdata(); ?>

		<?php veng_ad_slot( 'Reklam Alanı · 728×90' ); ?>

		<?php foreach ( get_categories( array( 'orderby' => 'id', 'number' => 6 ) ) as $cat ) :
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
