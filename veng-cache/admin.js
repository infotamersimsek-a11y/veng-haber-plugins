(function () {
	// Sekme geçişleri
	var tabs = document.querySelectorAll( '.veng-cache-tab' );
	var panels = document.querySelectorAll( '.veng-cache-panel' );
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			tabs.forEach( function ( t ) { t.classList.remove( 'active' ); } );
			tab.classList.add( 'active' );
			panels.forEach( function ( p ) {
				p.style.display = ( p.getAttribute( 'data-panel' ) === tab.getAttribute( 'data-tab' ) ) ? '' : 'none';
			} );
		} );
	} );

	function post( action, extra ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', VengCache.nonce );
		Object.keys( extra || {} ).forEach( function ( k ) {
			if ( Array.isArray( extra[ k ] ) ) {
				extra[ k ].forEach( function ( v ) { body.append( k + '[]', v ); } );
			} else {
				body.set( k, extra[ k ] );
			}
		} );
		return fetch( VengCache.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
	}

	// Ayarları kaydet
	var saveBtn = document.getElementById( 'vc-save-settings' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			var status = document.getElementById( 'vc-save-status' );
			var enabled = document.getElementById( 'vc-enabled' ).checked;
			var ttl = document.getElementById( 'vc-ttl' ).value;
			saveBtn.disabled = true;
			status.textContent = 'Kaydediliyor…';
			post( 'veng_cache_save_settings', { enabled: enabled ? '1' : '', ttl: ttl } ).then( function ( res ) {
				saveBtn.disabled = false;
				status.textContent = res.success ? 'Kaydedildi ✓' : 'Hata';
				var badge = document.getElementById( 'veng-cache-status-badge' );
				if ( badge ) {
					badge.className = 'veng-cache-hero-badge ' + ( enabled ? 'on' : 'off' );
					badge.textContent = enabled ? '● Aktif' : '○ Kapalı';
				}
				setTimeout( function () { status.textContent = ''; }, 2500 );
			} );
		} );
	}

	// Önbelleği temizle
	var clearBtn = document.getElementById( 'vc-clear-cache' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			var status = document.getElementById( 'vc-clear-status' );
			clearBtn.disabled = true;
			status.textContent = 'Temizleniyor…';
			post( 'veng_cache_clear' ).then( function ( res ) {
				clearBtn.disabled = false;
				if ( res.success ) {
					status.textContent = res.data.deleted + ' dosya silindi ✓';
					document.getElementById( 'vc-clear-count' ).textContent = res.data.stats.count;
					document.getElementById( 'vc-clear-size' ).textContent = formatBytes( res.data.stats.bytes );
					var statCount = document.getElementById( 'vc-stat-count' );
					if ( statCount ) statCount.textContent = res.data.stats.count;
				} else {
					status.textContent = 'Hata';
				}
				setTimeout( function () { status.textContent = ''; }, 3000 );
			} );
		} );
	}

	// Veritabanı temizliği
	var dbBtn = document.getElementById( 'vc-db-cleanup' );
	if ( dbBtn ) {
		dbBtn.addEventListener( 'click', function () {
			var status = document.getElementById( 'vc-db-status' );
			var items = Array.prototype.slice.call( document.querySelectorAll( 'input[name="vc-db-item"]:checked' ) ).map( function ( el ) { return el.value; } );
			if ( ! items.length ) {
				status.textContent = 'Bir şey seçmedin.';
				return;
			}
			dbBtn.disabled = true;
			status.textContent = 'Temizleniyor… (biraz sürebilir)';
			post( 'veng_cache_db_cleanup', { items: items } ).then( function ( res ) {
				dbBtn.disabled = false;
				if ( res.success ) {
					status.textContent = res.data.deleted + ' kayıt silindi ✓';
					Object.keys( res.data.counts ).forEach( function ( key ) {
						var el = document.getElementById( 'vc-db-' + key );
						if ( el ) el.textContent = res.data.counts[ key ];
					} );
				} else {
					status.textContent = 'Hata';
				}
				setTimeout( function () { status.textContent = ''; }, 3000 );
			} );
		} );
	}

	function formatBytes( bytes ) {
		if ( bytes < 1024 ) return bytes + ' B';
		if ( bytes < 1048576 ) return Math.round( bytes / 1024 ) + ' KB';
		return ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
	}
})();
