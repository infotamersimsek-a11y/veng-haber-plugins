<?php
/* Template Name: Yazarlar */
get_header();
$authors = get_users( array( 'has_published_posts' => array( 'post', 'makale' ) ) );
?>
<div class="container">
	<h1 style="font-size:26px;font-weight:800;margin:24px 0;">Yazarlar</h1>
	<div class="grid">
		<?php foreach ( $authors as $author ) :
			$count = count_user_posts( $author->ID, array( 'post', 'makale' ) );
			?>
			<a href="<?php echo esc_url( get_author_posts_url( $author->ID ) ); ?>" class="card" style="display:flex;align-items:center;gap:12px;">
				<?php echo get_avatar( $author->ID, 56, '', '', array( 'style' => 'border-radius:50%;' ) ); ?>
				<div>
					<div style="font-weight:700;"><?php echo esc_html( $author->display_name ); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo esc_html( get_the_author_meta( 'veng_title', $author->ID ) ); ?></div>
					<div style="font-size:12px;color:var(--muted);"><?php echo intval( $count ); ?> yazı</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</div>
<?php get_footer(); ?>
