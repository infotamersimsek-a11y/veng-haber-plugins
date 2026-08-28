	<footer class="site-footer">
		<div class="footer-grid">
			<div>
				<div class="logo" style="font-size:20px;margin-bottom:8px;">
					<?php
					$parts = explode( ' ', get_bloginfo( 'name' ), 2 );
					echo '<span>' . esc_html( $parts[0] ) . '</span>' . ( isset( $parts[1] ) ? ' ' . esc_html( $parts[1] ) : '' );
					?>
				</div>
				<p style="font-size:13px;color:var(--muted);margin:0 0 6px;"><?php echo esc_html( get_theme_mod( 'veng_address', 'Diyarbakır, Türkiye' ) ); ?></p>
				<p style="font-size:13px;color:var(--muted);margin:0 0 12px;"><?php echo esc_html( get_theme_mod( 'veng_contact_email' ) ); ?> · <?php echo esc_html( get_theme_mod( 'veng_contact_phone' ) ); ?></p>
				<div style="display:flex;gap:12px;">
					<a href="<?php echo esc_url( home_url( '/rss.xml' ) ); ?>">RSS</a>
				</div>
			</div>
			<div>
				<h4>Kategoriler</h4>
				<?php
				$veng_footer_cats = get_categories( array( 'orderby' => 'id' ) );
				$veng_footer_visible = array_slice( $veng_footer_cats, 0, 4 );
				$veng_footer_rest = array_slice( $veng_footer_cats, 4 );
				?>
				<ul>
					<?php foreach ( $veng_footer_visible as $cat ) : ?>
						<li><a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $veng_footer_rest ) : ?>
				<details class="footer-more">
					<summary>Devamı »</summary>
					<ul>
						<?php foreach ( $veng_footer_rest as $cat ) : ?>
							<li><a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</details>
				<?php endif; ?>
			</div>
			<div>
				<h4>Modüller</h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/yazarlar/' ) ); ?>">Yazarlar</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'foto_galeri' ) ); ?>">Foto Galeri</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'video_galeri' ) ); ?>">Video Galeri</a></li>
					<li><a href="<?php echo esc_url( home_url( '/yerel-haberler/' ) ); ?>">Yerel Haberler</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'anket' ) ); ?>">Anketler</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'resmi_ilan' ) ); ?>">Resmi İlanlar</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'firma' ) ); ?>">Firma Rehberi</a></li>
				</ul>
			</div>
			<div>
				<h4>Kurumsal</h4>
				<ul>
					<?php $hakkimizda = get_page_by_path( 'hakkimizda' ); ?>
					<?php if ( $hakkimizda ) : ?><li><a href="<?php echo esc_url( get_permalink( $hakkimizda ) ); ?>">Hakkımızda</a></li><?php endif; ?>
					<li><a href="<?php echo esc_url( home_url( '/iletisim/' ) ); ?>">İletişim</a></li>
				</ul>
			</div>
		</div>

		<div class="footer-grid" style="grid-template-columns:1fr;padding-top:0;">
			<form id="veng-newsletter-form" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
				<div>
					<strong style="font-size:14px;">Bültenimize Katılın</strong>
					<div style="font-size:12px;color:var(--muted);">Günün önemli haberleri e-postanıza gelsin.</div>
				</div>
				<input type="email" name="email" required placeholder="E-posta adresiniz" style="flex:1;min-width:200px;border-radius:8px;border:1px solid var(--border);background:var(--bg);padding:8px 12px;color:var(--fg);" />
				<button type="submit" class="btn">Abone Ol</button>
				<span class="form-msg" style="font-size:12px;"></span>
			</form>
		</div>

		<div class="footer-bottom">
			<span>© <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. Tüm hakları saklıdır.</span>
			<span>Bu bir demo sitedir · Google News ve BİK uyumlu altyapı örneği</span>
		</div>
	</footer>

	<button type="button" id="veng-back-to-top" aria-label="Başa dön" title="Başa dön">↑</button>

	<?php wp_footer(); ?>
</body>
</html>
