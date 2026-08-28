<?php get_header(); while ( have_posts() ) : the_post();
	$cats = get_the_category();
	$sehirler = get_the_terms( get_the_ID(), 'sehir' );
	$views = veng_get_views( get_the_ID() );
	$word_count = str_word_count( wp_strip_all_tags( get_the_content() ) );
	$minutes = max( 1, ceil( $word_count / 200 ) );
	?>
<div class="container layout">
	<article style="min-width:0;">
		<nav class="breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Ana Sayfa</a>
			<?php if ( $cats ) : ?><span>/</span><a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a><?php endif; ?>
			<?php if ( $sehirler ) : ?><span>/</span><a href="<?php echo esc_url( get_term_link( $sehirler[0] ) ); ?>"><?php echo esc_html( $sehirler[0]->name ); ?></a><?php endif; ?>
		</nav>

		<div class="article-header">
			<?php if ( $cats ) : ?><a class="badge" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a><?php endif; ?>
			<h1 class="article-title" id="article-title"><?php the_title(); ?></h1>
			<?php if ( get_the_excerpt() ) : ?><p class="article-summary"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</div>

		<div class="article-meta">
			<div class="author-box">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 40, '', '', array( 'class' => 'author-avatar' ) ); ?>
				<div>
					<div style="font-weight:700;font-size:14px;"><?php the_author(); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo esc_html( get_the_date( 'd F Y, H:i' ) ); ?> · <?php echo $minutes; ?> dk okuma · <?php echo esc_html( number_format_i18n( $views ) ); ?> görüntülenme</div>
				</div>
			</div>
			<?php veng_share_buttons(); ?>
		</div>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="article-cover"><?php the_post_thumbnail( 'full' ); ?></div>
		<?php endif; ?>

		<div class="article-content news-article-content">
			<?php the_content(); ?>
		</div>

		<?php veng_ad_slot( 'Reklam Alanı · 336×280', 120 ); ?>

		<?php $tags = get_the_tags(); if ( $tags ) : ?>
		<div class="tag-list">
			<?php foreach ( $tags as $tag ) : ?>
				<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="share-buttons-bottom">
			<span class="widget-title" style="margin:0 0 8px;">BU HABERİ PAYLAŞ</span>
			<?php veng_share_buttons( '', '', false ); ?>
		</div>

		<?php
		// Aynı kategoriden ilgili haberler; yeterli sonuç yoksa aynı etiketlerden tamamla.
		$related_ids = array( get_the_ID() );
		$related_posts = get_posts( array(
			'post_type' => 'post', 'posts_per_page' => 4,
			'post__not_in' => $related_ids,
			'cat' => $cats ? $cats[0]->term_id : 0,
		) );
		$related_ids = array_merge( $related_ids, wp_list_pluck( $related_posts, 'ID' ) );

		if ( count( $related_posts ) < 4 && $tags ) {
			$extra = get_posts( array(
				'post_type' => 'post', 'posts_per_page' => 4 - count( $related_posts ),
				'post__not_in' => $related_ids,
				'tag__in' => wp_list_pluck( $tags, 'term_id' ),
			) );
			$related_posts = array_merge( $related_posts, $extra );
		}

		if ( $related_posts ) : ?>
		<section style="margin-top:40px;">
			<h2 style="font-size:18px;font-weight:800;margin-bottom:16px;">İlgili Haberler</h2>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
				<?php foreach ( $related_posts as $rp ) : veng_render_hcard( $rp->ID ); endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<section class="comments">
			<?php comments_template(); ?>
		</section>

		<div id="veng-infinite-feed" data-current-date="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" data-loading="0">
			<div id="veng-infinite-sentinel"></div>
		</div>
	</article>

	<?php veng_render_sidebar(); ?>
</div>
<?php endwhile; get_footer(); ?>
