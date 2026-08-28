<?php get_header(); while ( have_posts() ) : the_post(); ?>
<div class="container" style="max-width:760px;padding:40px 16px;">
	<h1 style="font-size:26px;font-weight:800;margin-bottom:24px;"><?php the_title(); ?></h1>
	<div class="article-content"><?php the_content(); ?></div>
</div>
<?php endwhile; get_footer(); ?>
