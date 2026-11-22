<?php
/** Verified legacy URLs. Edge/server rules must use the same map at launch. */
defined( 'ABSPATH' ) || exit;
function razil_legacy_urls(): array {
	return array(
		'/index.html' => '/',
		'/organizatsiya_pokhoron.html' => '/uslugi/organizatsiya-pohoron/',
		'/krematsiya.html' => '/uslugi/krematsiya/',
		'/transportirovka_umershikh.html' => '/uslugi/transportirovka/',
		'/blagoustroystva_mest_zakhoroneniya.html' => '/uslugi/blagoustroystvo/',
		'/yuridicheskaya_pomoshch.html' => '/uslugi/yuridicheskaya-pomoshch/',
		'/rekvizity-khabarovsk.html' => '/rekvizity/',
		'/politika.html' => '/politika/',
		'/soglasie.html' => '/soglasie/',
		'/nashi-raboty.html' => '/uslugi/blagoustroystvo/',
		'/pamyatniki.html' => '/uslugi/blagoustroystvo/',
		'/blagoustroystvo.html' => '/uslugi/blagoustroystvo/',
		'/ogradki.html' => '/uslugi/blagoustroystvo/',
		// Owner confirmed: no services in these cities and no lifetime contract.
		'/birobidzhan.html' => null,
		'/amursk.html' => null,
		'/rekvizity-birobidzhan.html' => null,
		'/rekvizity-amursk.html' => null,
		'/prizhiznennyy_dogovor.html' => null,
	);
}
add_action( 'template_redirect', static function () {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
	$map = razil_legacy_urls();
	if ( ! array_key_exists( $path, $map ) ) { return; }
	if ( null === $map[ $path ] ) {
		wp_die( 'Эта страница удалена. Агентство «Земля и Люди» оказывает услуги в Хабаровске. <a href="' . esc_url( home_url( '/uslugi/' ) ) . '">Посмотреть действующие услуги</a>.', 'Страница удалена', array( 'response' => 410 ) );
	}
	wp_safe_redirect( home_url( $map[ $path ] ), 301, 'Razil legacy URL migration' );
	exit;
}, -10 );
