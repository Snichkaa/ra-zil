<?php
/** Import the selected full-screen home photo. Originals are never modified. */
if ( PHP_SAPI !== 'cli' ) { exit( 'CLI only.' ); }
ini_set( 'memory_limit', '512M' );
$_SERVER['HTTP_HOST'] = 'ra-zil.test';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$input = $argv[1] ?? '';
if ( ! is_file( $input ) ) { throw new RuntimeException( 'Pass the original SU402959.JPG path.' ); }
$hash = hash_file( 'sha256', $input );
$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_rz_source_photo_sha256', 'meta_value' => $hash ) );
if ( $existing ) {
    $id = $existing[0]->ID;
} else {
    $uploads = wp_upload_dir( '2026/10' );
    if ( $uploads['error'] ) { throw new RuntimeException( $uploads['error'] ); }
    $name = 'kazarmenny-glavnyy-ekran.webp';
    $dest = $uploads['path'] . '/' . $name;
    if ( file_exists( $dest ) ) { throw new RuntimeException( 'Destination already exists.' ); }
    $editor = wp_get_image_editor( $input );
    if ( is_wp_error( $editor ) ) { throw new RuntimeException( $editor->get_error_message() ); }
    $editor->set_quality( 80 );
    $resized = $editor->resize( 2560, 1707, false );
    if ( is_wp_error( $resized ) ) { throw new RuntimeException( $resized->get_error_message() ); }
    $saved = $editor->save( $dest, 'image/webp' );
    unset( $editor );
    if ( is_wp_error( $saved ) ) { throw new RuntimeException( $saved->get_error_message() ); }
    $id = wp_insert_attachment( array( 'post_title' => 'Здание агентства — первый экран главной', 'post_mime_type' => 'image/webp', 'post_status' => 'inherit', 'guid' => $uploads['url'] . '/' . $name ), $dest, 0, true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    update_post_meta( $id, '_wp_attachment_image_alt', 'Здание агентства «Земля и Люди» с парковкой в переулке Казарменном, 9 в Хабаровске' );
    update_post_meta( $id, '_rz_source_photo', basename( $input ) );
    update_post_meta( $id, '_rz_source_photo_sha256', $hash );
    $metadata = wp_generate_attachment_metadata( $id, $dest );
    if ( empty( $metadata['sizes'] ) ) { throw new RuntimeException( 'Missing responsive image sizes.' ); }
    wp_update_attachment_metadata( $id, $metadata );
}
echo wp_json_encode( array( 'id' => $id, 'file' => get_post_meta( $id, '_wp_attached_file', true ), 'bytes' => filesize( get_attached_file( $id ) ), 'sha256' => $hash, 'metadata' => wp_get_attachment_metadata( $id ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
