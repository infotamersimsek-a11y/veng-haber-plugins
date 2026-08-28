<?php get_header(); ?>
<div class="container" style="max-width:800px;">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;">Anketler</h1>
	<div class="grid" style="grid-template-columns:repeat(2,1fr);">
		<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
			get_template_part( 'template-parts/poll-widget', null, array( 'poll_id' => get_the_ID() ) );
		endwhile; else : ?>
			<p style="color:var(--muted);">Şu anda aktif anket bulunmuyor.</p>
		<?php endif; ?>
	</div>
</div>
<?php get_footer(); ?>
