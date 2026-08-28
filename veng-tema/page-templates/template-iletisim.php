<?php
/* Template Name: İletişim */
get_header();
?>
<div class="container" style="max-width:900px;">
	<div class="layout" style="grid-template-columns:1fr 1fr;padding-top:40px;">
		<div>
			<h1 style="font-size:26px;font-weight:800;margin-bottom:24px;">İletişim</h1>
			<div style="display:flex;flex-direction:column;gap:16px;font-size:14px;">
				<div>✉️ <?php echo esc_html( get_theme_mod( 'veng_contact_email' ) ); ?></div>
				<div>📞 <?php echo esc_html( get_theme_mod( 'veng_contact_phone' ) ); ?></div>
				<div>📍 <?php echo esc_html( get_theme_mod( 'veng_address' ) ); ?></div>
			</div>
		</div>
		<div>
			<form id="veng-contact-form" class="comment-form">
				<input type="text" name="name" placeholder="Adınız Soyadınız" required />
				<input type="email" name="email" placeholder="E-posta" required />
				<input type="text" name="subject" placeholder="Konu" required />
				<textarea name="message" rows="5" placeholder="Mesajınız" required></textarea>
				<button type="submit" class="btn">Gönder</button>
				<div class="form-msg" style="font-size:13px;margin-top:8px;"></div>
			</form>
		</div>
	</div>
</div>
<?php get_footer(); ?>
