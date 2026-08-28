<?php get_header(); while ( have_posts() ) : the_post(); ?>
<div class="container layout">
	<article style="min-width:0;">
		<span class="badge">Makale</span>
		<h1 class="article-title" style="margin-top:12px;"><?php the_title(); ?></h1>
		<?php if ( get_the_excerpt() ) : ?><p class="article-summary"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>

		<div class="article-meta">
			<a class="author-box" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 40, '', '', array( 'class' => 'author-avatar' ) ); ?>
				<div>
					<div style="font-weight:700;font-size:14px;"><?php the_author(); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo esc_html( get_the_author_meta( 'veng_title' ) ); ?> · <?php echo esc_html( get_the_date( 'd F Y, H:i' ) ); ?></div>
				</div>
			</a>
			<?php veng_share_buttons(); ?>
		</div>

		<?php if ( has_post_thumbnail() ) : ?><div class="article-cover"><?php the_post_thumbnail( 'full' ); ?></div><?php endif; ?>

		<div class="article-content news-article-content"><?php the_content(); ?></div>

		<?php
		$others = new WP_Query( array( 'post_type' => 'makale', 'author' => get_the_author_meta( 'ID' ), 'post__not_in' => array( get_the_ID() ), 'posts_per_page' => 4 ) );
		if ( $others->have_posts() ) : ?>
		<section style="margin-top:32px;">
			<h2 style="font-size:16px;font-weight:800;margin-bottom:12px;"><?php the_author(); ?> Yazıları</h2>
			<ul style="padding-left:18px;">
				<?php while ( $others->have_posts() ) : $others->the_post(); ?>
					<li style="margin-bottom:6px;"><a href="<?php the_permalink(); ?>" style="font-weight:700;"><?php the_title(); ?></a></li>
				<?php endwhile; ?>
			</ul>
		</section>
		<?php endif; wp_reset_postdata(); ?>
	</article>
	<?php veng_render_sidebar(); ?>
</div>
<?php endwhile; get_footer(); ?>
