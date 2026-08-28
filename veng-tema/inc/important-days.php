<?php
/**
 * Önemli günler takvimi: resmi bayramlar, uluslararası (BM) günler ve bölgede
 * ortak kutlanan kültürel bayramlar. Tarafsız/tanımlayıcı dille, tek bir grubu
 * öne çıkarmadan hazırlanmıştır.
 */
function veng_get_important_days() {
	return array(
		array( 'md' => '01-01', 'title' => 'Yılbaşı' ),
		array( 'md' => '03-08', 'title' => 'Dünya Kadınlar Günü' ),
		array(
			'md'    => '03-21',
			'title' => 'Nevruz',
			'desc'  => 'Baharın gelişini müjdeleyen; başta Türk ve Kürt halkları olmak üzere bölgede pek çok toplum tarafından kutlanan, UNESCO tarafından da tescillenmiş ortak bahar bayramı.',
		),
		array( 'md' => '04-23', 'title' => 'Ulusal Egemenlik ve Çocuk Bayramı' ),
		array( 'md' => '05-01', 'title' => 'Emek ve Dayanışma Günü' ),
		array( 'md' => '05-19', 'title' => "Atatürk'ü Anma, Gençlik ve Spor Bayramı" ),
		array( 'md' => '05-21', 'title' => 'Dünya Kültürel Çeşitlilik Günü' ),
		array( 'md' => '06-05', 'title' => 'Dünya Çevre Günü' ),
		array( 'md' => '07-15', 'title' => 'Demokrasi ve Milli Birlik Günü' ),
		array( 'md' => '08-30', 'title' => 'Zafer Bayramı' ),
		array( 'md' => '09-21', 'title' => 'Dünya Barış Günü' ),
		array( 'md' => '10-05', 'title' => 'Dünya Öğretmenler Günü' ),
		array( 'md' => '10-29', 'title' => 'Cumhuriyet Bayramı' ),
		array( 'md' => '11-10', 'title' => "Atatürk'ü Anma Günü" ),
		array( 'md' => '11-19', 'title' => 'Dünya Erkekler Günü' ),
		array( 'md' => '11-25', 'title' => 'Kadına Yönelik Şiddete Karşı Uluslararası Mücadele Günü' ),
		array( 'md' => '12-03', 'title' => 'Dünya Engelliler Günü' ),
		array( 'md' => '12-10', 'title' => 'İnsan Hakları Günü' ),
	);
}

function veng_find_todays_important_day() {
	$today_md = current_time( 'm-d' );
	foreach ( veng_get_important_days() as $day ) {
		if ( $day['md'] === $today_md ) {
			return $day;
		}
	}
	return null;
}

function veng_find_next_important_day() {
	$now = current_time( 'timestamp' );
	$best = null;
	$best_diff = PHP_INT_MAX;
	foreach ( veng_get_important_days() as $day ) {
		list( $m, $d ) = explode( '-', $day['md'] );
		$year = (int) date( 'Y', $now );
		$ts = mktime( 0, 0, 0, (int) $m, (int) $d, $year );
		if ( $ts <= $now ) {
			$ts = mktime( 0, 0, 0, (int) $m, (int) $d, $year + 1 );
		}
		$diff = (int) floor( ( $ts - $now ) / DAY_IN_SECONDS );
		if ( $diff < $best_diff ) {
			$best_diff = $diff;
			$best = $day;
			$best['days_left'] = $diff;
		}
	}
	return $best;
}
