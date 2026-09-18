<?php
require_once __DIR__ . '/../../wp-load.php';
echo "home_url()                 = " . home_url() . "\n";
echo "get_permalink(21)          = " . get_permalink(21) . "\n";
echo "get_page_by_path('otzyvy') = ";
$pg = get_page_by_path('otzyvy');
echo $pg ? "ID {$pg->ID}\n" : "null\n";
if (function_exists('razil_form_page_url')) {
    echo "razil_form_page_url('otzyvy') = " . razil_form_page_url('otzyvy') . "\n";
}
echo "\n-- review-form.php lines 70-95 --\n";
echo implode('', array_slice(file(WP_CONTENT_DIR . '/plugins/razil-core/inc/review-form.php'), 69, 26));
echo "\n-- review-form.php lines 185-215 (markup start, any id?) --\n";
echo implode('', array_slice(file(WP_CONTENT_DIR . '/plugins/razil-core/inc/review-form.php'), 184, 31));
