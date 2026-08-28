<?php get_header(); ?>
<div class="container" style="text-align:center;padding:80px 16px;">
	<h1 style="font-size:48px;font-weight:800;margin-bottom:12px;">404</h1>
	<p style="color:var(--muted);margin-bottom:24px;">Aradığınız sayfa bulunamadı.</p>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn">Ana Sayfaya Dön</a>
</div>
<?php get_footer(); ?>
