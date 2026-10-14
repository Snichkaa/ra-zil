<?php
/** Apply the approved page map through WordPress and Yoast APIs. CLI only. */
if ( PHP_SAPI !== 'cli' ) { exit( 'CLI only.' ); }
$_SERVER['HTTP_HOST'] = 'ra-zil.test';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 2 ) . '/wp-load.php';

$apply = in_array( '--apply', $argv, true );
$map = json_decode( file_get_contents( __DIR__ . '/seo-map-2026-10.json' ), true, 512, JSON_THROW_ON_ERROR );
if ( count( $map ) !== 11 || ! class_exists( 'WPSEO_Meta' ) || ! function_exists( 'YoastSEO' ) ) {
    throw new RuntimeException( 'Expected eleven approved pages and active Yoast SEO.' );
}
$meta_keys = array( 'title', 'metadesc', 'focuskw', 'opengraph-title', 'opengraph-description', 'twitter-title', 'twitter-description' );
$option_keys = array( 'title-ptarchive-services', 'metadesc-ptarchive-services', 'social-title-ptarchive-services', 'social-description-ptarchive-services' );
$state = array( 'blog_public' => get_option( 'blog_public' ), 'posts' => array(), 'options' => array() );
$ids = array();
foreach ( $map as $seo_row ) {
    foreach ( array( 'url', 'h1', 'title', 'description' ) as $key ) {
        if ( empty( $seo_row[$key] ) ) { throw new RuntimeException( 'Incomplete approved row.' ); }
    }
    if ( $seo_row['url'] === '/uslugi/' ) { continue; }
    $target_id = $seo_row['url'] === '/' ? (int) get_option( 'page_on_front' ) : url_to_postid( home_url( $seo_row['url'] ) );
    $target_post = get_post( $target_id );
    if ( ! $target_post || $target_post->post_status !== 'publish' ) { throw new RuntimeException( 'Missing page: ' . $seo_row['url'] ); }
    $ids[$seo_row['url']] = $target_id;
    $row = array( 'id' => $target_id, 'url' => $seo_row['url'], 'title' => $target_post->post_title, 'excerpt' => $target_post->post_excerpt, 'slug' => $target_post->post_name, 'status' => $target_post->post_status, 'content_md5' => md5( $target_post->post_content ), 'meta' => array() );
    foreach ( $meta_keys as $key ) {
        $row['meta'][$key] = array( 'exists' => metadata_exists( 'post', $target_id, '_yoast_wpseo_' . $key ), 'value' => get_post_meta( $target_id, '_yoast_wpseo_' . $key, true ) );
    }
    $state['posts'][] = $row;
}
foreach ( $option_keys as $key ) { $state['options'][$key] = WPSEO_Options::get( $key ); }
foreach ( array( 'front-page', 'archive-services' ) as $slug ) {
    if ( get_block_template( 'razil//' . $slug, 'wp_template' )->source !== 'theme' ) {
        throw new RuntimeException( 'Template overridden in database: ' . $slug );
    }
}
$backup = ABSPATH . '_backup/seo-2026-10-14/seo-state.json';
if ( ! file_exists( $backup ) ) {
    wp_mkdir_p( dirname( $backup ) );
    file_put_contents( $backup, wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
}
if ( ! $apply ) {
    echo 'Preflight OK: ' . count( $ids ) . ' posts, one archive; backup saved. blog_public=' . $state['blog_public'] . "\n";
    exit;
}

// wp_update_post merges the saved content; preserve its existing SVG and block HTML.
kses_remove_filters();
$builder = YoastSEO()->classes->get( \Yoast\WP\SEO\Builders\Indexable_Builder::class );
$repository = YoastSEO()->classes->get( \Yoast\WP\SEO\Repositories\Indexable_Repository::class );
$results = array();
foreach ( $map as $seo_row ) {
    if ( $seo_row['url'] === '/uslugi/' ) {
        foreach ( array( 'title' => 'title', 'metadesc' => 'description', 'social-title' => 'title', 'social-description' => 'description' ) as $prefix => $key ) {
            WPSEO_Options::set( $prefix . '-ptarchive-services', $seo_row[$key] );
            if ( WPSEO_Options::get( $prefix . '-ptarchive-services' ) !== $seo_row[$key] ) { throw new RuntimeException( 'Archive option was not saved.' ); }
        }
        $indexable = $repository->find_for_post_type_archive( 'services', false );
        $builder->build_for_post_type_archive( 'services', $indexable );
        $results[] = array( 'url' => $seo_row['url'], 'archive' => true );
        continue;
    }
    $target_id = $ids[$seo_row['url']];
    $before = get_post( $target_id );
    $content_hash = md5( $before->post_content );
    $update = array( 'ID' => $target_id, 'post_name' => $before->post_name );
    if ( $seo_row['url'] !== '/' ) { $update['post_title'] = $seo_row['h1']; }
    if ( $seo_row['url'] === '/uslugi/organizatsiya-pohoron/' ) {
        $update['post_excerpt'] = 'Организуем достойную церемонию прощания и погребения: возьмём на себя документы, транспорт и поминальный обед. Наш специалист приедет в любое время суток и будет рядом на всех этапах прощания.';
    }
    WPSEO_Meta::set_value( 'title', $seo_row['title'], $target_id );
    WPSEO_Meta::set_value( 'metadesc', $seo_row['description'], $target_id );
    WPSEO_Meta::set_value( 'focuskw', explode( '; ', $seo_row['queries'] )[0], $target_id );
    $saved = wp_update_post( wp_slash( $update ), true );
    if ( is_wp_error( $saved ) ) { throw new RuntimeException( $saved->get_error_message() ); }
    $after = get_post( $target_id );
    if ( $content_hash !== md5( $after->post_content ) || $after->post_name !== $before->post_name || $after->post_status !== $before->post_status ) {
        throw new RuntimeException( 'Unexpected content, slug or status change: ' . $target_id );
    }
    if ( get_post_meta( $target_id, '_yoast_wpseo_title', true ) !== $seo_row['title'] || get_post_meta( $target_id, '_yoast_wpseo_metadesc', true ) !== $seo_row['description'] ) {
        throw new RuntimeException( 'Metadata was not saved: ' . $target_id );
    }
    $indexable = $repository->find_by_id_and_type( $target_id, 'post', false );
    $builder->build_for_id_and_type( $target_id, 'post', $indexable );
    $results[] = array( 'url' => $seo_row['url'], 'id' => $target_id, 'title' => $after->post_title );
}
if ( get_option( 'blog_public' ) !== $state['blog_public'] ) { throw new RuntimeException( 'Indexing setting changed unexpectedly.' ); }
echo wp_json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
