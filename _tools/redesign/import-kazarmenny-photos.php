<?php
/** Import the four selected photos and prepare page content, without publishing it. */
if ( PHP_SAPI !== 'cli' ) { exit( 'CLI only.' ); }
ini_set( 'memory_limit', '512M' );
$_SERVER['HTTP_HOST'] = 'ra-zil.test';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$source = $argv[1] ?? '';
if ( ! is_dir( $source ) ) { throw new RuntimeException( 'Pass the source photo directory.' ); }
$backup = ABSPATH . '_backup/photos-2026-10-13/posts/';
$output = __DIR__ . '/photos-content/';
wp_mkdir_p( $output );
$selection = array(
 'hall' => array( 'SU402848.JPG', 'kazarmenny-zal-proshchaniya.webp', 'Зал прощания на Казарменном', 'Светлый зал прощания с витражами, скамьями и цветочными композициями' ),
 'exhibition' => array( 'SU402900.JPG', 'kazarmenny-vystavochnye-obraztsy.webp', 'Выставочные образцы памятников и оград', 'Выставочная площадка агентства: образцы памятников, оград и покрытий' ),
 'building' => array( 'SU402976.JPG', 'kazarmenny-zdanie-i-territoriya.webp', 'Здание агентства на Казарменном', 'Кирпичное здание агентства с крыльцом и цветниками в переулке Казарменном' ),
 'directions' => array( 'SU402979.JPG', 'kazarmenny-ukazateli.webp', 'Указатели на территории агентства', 'Указатели к залу прощания и клиентскому сервису на территории агентства' ),
);
$uploads = wp_upload_dir( '2026/10' );
if ( $uploads['error'] ) { throw new RuntimeException( $uploads['error'] ); }
$photos = array();
foreach ( $selection as $key => [ $original, $name, $title, $alt ] ) {
 $input = $source . '/' . $original;
 $hash = hash_file( 'sha256', $input );
 $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_rz_source_photo_sha256', 'meta_value' => $hash ) );
 if ( $existing ) {
  $id = $existing[0]->ID;
 } else {
  $dest = $uploads['path'] . '/' . $name;
  if ( file_exists( $dest ) ) { throw new RuntimeException( 'File already exists: ' . $name ); }
  $editor = wp_get_image_editor( $input );
  if ( is_wp_error( $editor ) ) { throw new RuntimeException( $editor->get_error_message() ); }
  $editor->set_quality( 84 );
  $resized = $editor->resize( 1920, 1280, false );
  if ( is_wp_error( $resized ) ) { throw new RuntimeException( $resized->get_error_message() ); }
  $saved = $editor->save( $dest, 'image/webp' );
  unset( $editor );
  if ( is_wp_error( $saved ) ) { throw new RuntimeException( $saved->get_error_message() ); }
  $id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => 'image/webp', 'post_status' => 'inherit', 'guid' => $uploads['url'] . '/' . $name ), $dest, 0, true );
  if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
  update_post_meta( $id, '_wp_attachment_image_alt', $alt );
  update_post_meta( $id, '_rz_source_photo', $original );
  update_post_meta( $id, '_rz_source_photo_sha256', $hash );
  $metadata = wp_generate_attachment_metadata( $id, $dest );
  if ( empty( $metadata['sizes'] ) ) { throw new RuntimeException( 'Missing responsive image sizes.' ); }
  wp_update_attachment_metadata( $id, $metadata );
 }
 $photos[$key] = array( 'id' => $id, 'source' => $original, 'sha256' => $hash, 'src' => wp_make_link_relative( wp_get_attachment_url( $id ) ), 'alt' => $alt, 'bytes' => filesize( get_attached_file( $id ) ), 'metadata' => wp_get_attachment_metadata( $id ) );
 echo $key . ': attachment ' . $id . ', ' . $photos[$key]['bytes'] . " bytes\n";
}
file_put_contents( $output . 'selection.json', wp_json_encode( $photos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n" );

function rz_selected_photo_block( $photo, $class, $caption = '' ) {
 $attrs = wp_json_encode( array( 'id' => $photo['id'], 'sizeSlug' => 'full', 'linkDestination' => 'none', 'className' => $class ) );
 $html = '<figure class="wp-block-image size-full ' . esc_attr( $class ) . '"><img src="' . esc_url( $photo['src'] ) . '" alt="' . esc_attr( $photo['alt'] ) . '" class="wp-image-' . $photo['id'] . '"/>';
 if ( $caption ) { $html .= '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>'; }
 return '<!-- wp:image ' . $attrs . " -->\n" . $html . "</figure>\n<!-- /wp:image -->";
}
foreach ( array( 11, 14, 18, 19 ) as $id ) {
 $content = file_get_contents( $backup . $id . '.html' );
 if ( $id === 11 || $id === 14 || $id === 19 ) {
  $attribute = $id === 19 ? 'data-slot="1"' : 'data-photo-number="' . ( $id === 11 ? '1' : '2' ) . '"';
  $key = $id === 11 ? 'hall' : ( $id === 14 ? 'exhibition' : 'directions' );
  $class = $id === 19 ? 'rz-contact-photo' : 'rz-svc-photo';
  $caption = $id === 11 ? 'Зал прощания агентства на Казарменном' : ( $id === 14 ? 'Образцы памятников, оград и покрытий на выставочной площадке' : 'Указатели на территории агентства' );
  $pattern = '~<!-- wp:html -->\s*<div class="rz-photo-slot[^>]*' . $attribute . '[^>]*>.*?</div>\s*<!-- /wp:html -->~s';
  $content = preg_replace_callback( $pattern, fn() => rz_selected_photo_block( $photos[$key], $class, $caption ), $content, -1, $count );
  if ( $count !== 1 ) { throw new RuntimeException( 'Expected one placeholder on page ' . $id ); }
 } else {
  foreach ( array( 'Наша территория' => 'building', 'Зал прощания' => 'hall' ) as $heading => $key ) {
   $needle = '<!-- wp:heading {"level":3} -->' . "\n" . '<h3 class="wp-block-heading">' . $heading . '</h3>';
   if ( substr_count( $content, $needle ) !== 1 ) { throw new RuntimeException( 'Missing heading: ' . $heading ); }
   $content = str_replace( $needle, rz_selected_photo_block( $photos[$key], 'rz-info-photo' ) . "\n\n" . $needle, $content );
  }
 }
 file_put_contents( $output . $id . '.html', $content );
}
