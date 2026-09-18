<?php
require_once __DIR__ . '/../../wp-load.php';
global $wpdb;

echo "=== 1. Поиск rz-first-aid__warning в wp_posts (все типы, все статусы) ===\n";
$rows = $wpdb->get_results(
    "SELECT ID, post_type, post_status, post_title
       FROM {$wpdb->posts}
      WHERE post_content LIKE '%rz-first-aid__warning%'"
);
if ( ! $rows ) {
    echo "(нет ни одной записи в БД)\n";
} else {
    foreach ( $rows as $r ) {
        echo sprintf( "id=%d type=%s status=%s title=%s\n", $r->ID, $r->post_type, $r->post_status, $r->post_title );
    }
}

echo "\n=== 2. Поиск во всех файлах темы (html/php/json, без node_modules) ===\n";
$base = str_replace( DIRECTORY_SEPARATOR, '/', get_stylesheet_directory() );
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
$hits = 0;
foreach ( $it as $f ) {
    $p = str_replace( DIRECTORY_SEPARATOR, '/', $f->getPathname() );
    if ( strpos( $p, '/node_modules/' ) !== false ) continue;
    if ( ! preg_match( '/[.](html|php|json)$/', $p ) ) continue;
    $c = file_get_contents( $p );
    if ( strpos( $c, 'rz-first-aid__warning' ) === false ) continue;
    $rel = str_replace( $base . '/', '', $p );
    foreach ( explode( "\n", $c ) as $i => $line ) {
        if ( strpos( $line, 'rz-first-aid__warning' ) !== false ) {
            $hits++;
            echo sprintf( "%s:%d: %s\n", $rel, $i + 1, trim( $line ) );
        }
    }
}
echo "ВСЕГО совпадений в файлах темы: {$hits}\n";

echo "\n=== 3. Отрендеренный абзац с живой главной ===\n";
$html = wp_remote_retrieve_body( wp_remote_get( home_url( '/' ), array( 'timeout' => 20 ) ) );
if ( preg_match_all( '#<p class="[^"]*rz-first-aid__warning[^"]*"[^>]*>.*?</p>#s', $html, $m ) ) {
    echo "найдено абзацев на странице: " . count( $m[0] ) . "\n";
    foreach ( $m[0] as $i => $one ) {
        echo "--- [{$i}] ---\n{$one}\n";
    }
} else {
    echo "(в отрендеренной странице не найдено)\n";
}
