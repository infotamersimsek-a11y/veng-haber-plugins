(function () {
	var btn = document.getElementById( 'veng-seo-scan-btn' );
	var status = document.getElementById( 'veng-seo-status' );
	var results = document.getElementById( 'veng-seo-results' );
	var tabsWrap = document.getElementById( 'veng-seo-tabs' );
	var tabs = tabsWrap.querySelectorAll( '.veng-seo-tab' );

	var cache = {};
	var statusLabels = { pass: 'Geçti', warn: 'Uyarı', fail: 'Hata' };
	var catIcons = { 'Performans': '⚡', 'SEO': '🔍', 'Erişilebilirlik': '♿', 'En İyi Uygulamalar': '✅' };

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str == null ? '' : String( str );
		return div.innerHTML;
	}

	function gaugeColor( score ) {
		if ( score >= 90 ) return '#0cce6b';
		if ( score >= 50 ) return '#ffa400';
		return '#ff4e42';
	}

	function renderGauge( score ) {
		var color = gaugeColor( score );
		return '<div class="veng-seo-gauge" style="--score:' + score + ';--gauge-color:' + color + ';color:' + color + ';">' +
			'<span>' + score + '</span></div>';
	}

	function renderCategory( cat ) {
		var html = '<div class="veng-seo-cat-card">' +
			'<div class="veng-seo-cat-head">' +
			renderGauge( cat.score ) +
			'<div class="veng-seo-cat-title">' + ( catIcons[ cat.label ] || '' ) + ' ' + escapeHtml( cat.label ) + '</div>' +
			'</div>' +
			'<table class="widefat striped veng-seo-check-table"><tbody>';
		cat.checks.forEach( function ( c ) {
			html += '<tr><td class="veng-seo-check-label">' + escapeHtml( c.label ) + '</td>' +
				'<td style="width:80px;"><span class="veng-seo-badge veng-seo-' + c.status + '">' + statusLabels[ c.status ] + '</span></td>' +
				'<td class="veng-seo-check-detail">' + escapeHtml( c.detail || '' ) + '</td></tr>';
		} );
		html += '</tbody></table></div>';
		return html;
	}

	function renderError( message ) {
		results.innerHTML = '<div class="veng-seo-error"><strong>Tarama başarısız:</strong> ' + escapeHtml( message ) + '<br><span style="font-weight:400;">Site bir CDN/güvenlik duvarı (ör. Cloudflare) arkasındaysa sunucunun kendine attığı istek engellenmiş olabilir. Birkaç saniye sonra "Şimdi Tara" ile tekrar deneyin.</span></div>';
	}

	function render( data ) {
		var html = '<div class="veng-seo-overall">' + renderGauge( data.overall ) + '<div class="veng-seo-cat-title" style="font-size:18px;">Genel Puan</div></div>';
		html += '<div class="veng-seo-cat-grid">';
		data.categories.forEach( function ( cat ) {
			html += renderCategory( cat );
		} );
		html += '</div>';
		results.innerHTML = html;
	}

	function runScan( device ) {
		if ( cache[ device ] ) {
			render( cache[ device ] );
			status.textContent = 'Tarama tamamlandı (' + ( 'mobile' === device ? 'Mobil' : 'Masaüstü' ) + ').';
			return;
		}
		status.textContent = 'Taranıyor (' + ( 'mobile' === device ? 'Mobil' : 'Masaüstü' ) + ')… birkaç saniye sürebilir.';
		results.innerHTML = '';

		var body = new URLSearchParams();
		body.set( 'action', 'veng_seo_scan' );
		body.set( 'nonce', VengSeo.nonce );
		body.set( 'device', device );

		fetch( VengSeo.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res.success ) {
					cache[ device ] = res.data;
					status.textContent = 'Tarama tamamlandı (' + ( 'mobile' === device ? 'Mobil' : 'Masaüstü' ) + ').';
					render( res.data );
				} else {
					status.textContent = 'Hata (' + ( 'mobile' === device ? 'Mobil' : 'Masaüstü' ) + ')';
					renderError( res.data || 'Bilinmeyen hata.' );
				}
			} )
			.catch( function ( err ) {
				status.textContent = 'İstek başarısız (' + ( 'mobile' === device ? 'Mobil' : 'Masaüstü' ) + ')';
				renderError( String( err ) );
			} );
	}

	btn.addEventListener( 'click', function () {
		cache = {};
		tabsWrap.style.display = '';
		tabs.forEach( function ( t ) { t.classList.remove( 'active' ); } );
		tabs[ 0 ].classList.add( 'active' );
		runScan( 'desktop' );
	} );

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			tabs.forEach( function ( t ) { t.classList.remove( 'active' ); } );
			tab.classList.add( 'active' );
			runScan( tab.getAttribute( 'data-device' ) );
		} );
	} );

	// Görsel sıkıştırma
	var compressBtn = document.getElementById( 'veng-seo-img-compress-btn' );
	if ( compressBtn ) {
		compressBtn.addEventListener( 'click', function () {
			var imgStatus = document.getElementById( 'veng-seo-img-status' );
			compressBtn.disabled = true;
			imgStatus.textContent = 'Sıkıştırılıyor… birkaç saniye sürebilir.';

			var body = new URLSearchParams();
			body.set( 'action', 'veng_seo_compress_images' );
			body.set( 'nonce', VengSeo.nonce );

			fetch( VengSeo.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					compressBtn.disabled = false;
					if ( res.success ) {
						document.getElementById( 'veng-seo-img-ok' ).textContent = res.data.stats.ok;
						document.getElementById( 'veng-seo-img-over' ).textContent = res.data.stats.over;
						document.getElementById( 'veng-seo-img-total' ).textContent = res.data.stats.total;
						document.getElementById( 'veng-seo-img-over-tile' ).classList.toggle( 'warn', res.data.stats.over > 0 );
						imgStatus.textContent = res.data.fixed + ' görsel sıkıştırıldı' + ( res.data.remaining > 0 ? ', ' + res.data.remaining + ' daha kaldı — tekrar bas.' : ' ✓' );
					} else {
						imgStatus.textContent = 'Hata: ' + ( res.data || 'bilinmeyen hata' );
					}
				} )
				.catch( function ( err ) {
					compressBtn.disabled = false;
					imgStatus.textContent = 'İstek başarısız: ' + err;
				} );
		} );
	}
})();
