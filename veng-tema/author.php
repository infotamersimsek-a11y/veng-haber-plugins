<?php get_header(); $author_id = get_queried_object_id(); ?>
<div class="container layout">
	<div style="min-width:0;">
		<div style="display:flex;align-items:center;gap:16px;margin-bottom:32px;">
			<?php echo get_avatar( $author_id, 80, '', '', array( 'style' => 'border-radius:50%;' ) ); ?>
			<div>
				<h1 style="font-size:24px;font-weight:800;margin:0;"><?php the_author_meta( 'display_name', $author_id ); ?></h1>
				<div style="font-size:13px;color:var(--muted);"><?php echo esc_html( get_the_author_meta( 'veng_title', $author_id ) ); ?></div>
				<?php if ( get_the_author_meta( 'description', $author_id ) ) : ?>
					<p style="font-size:13px;color:var(--muted);margin-top:8px;max-width:480px;"><?php the_author_meta( 'description', $author_id ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div style="display:flex;flex-direction:column;gap:16px;">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); veng_render_hcard( get_the_ID() ); endwhile; ?>
				<?php veng_pagination(); ?>
			<?php else : ?>
				<p style="color:var(--muted);">Henüz yayınlanmış yazı yok.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php veng_render_sidebar(); ?>
</div>
<?php get_footer(); ?>
