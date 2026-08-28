<?php get_header(); while ( have_posts() ) : the_post();
	$cats = get_the_terms( get_the_ID(), 'galeri_kategori' );
	?>
<div class="container" style="max-width:900px;">
	<?php if ( $cats ) : ?><span style="font-size:12px;font-weight:700;color:var(--theme);"><?php echo esc_html( $cats[0]->name ); ?></span><?php endif; ?>
	<h1 style="font-size:24px;font-weight:800;margin:6px 0 16px;"><?php the_title(); ?></h1>
	<div style="margin-bottom:20px;"><?php veng_share_buttons(); ?></div>
	<div class="article-content">
		<?php the_content(); ?>
	</div>
</div>
<?php endwhile; get_footer(); ?>
