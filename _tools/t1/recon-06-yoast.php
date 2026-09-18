<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$t = get_option('wpseo_titles');
foreach (['title-services','metadesc-services','title-ptarchive-services','metadesc-ptarchive-services','breadcrumbs-enable','breadcrumbs-sep'] as $k) {
    printf("%-30s = %s\n", $k, isset($t[$k]) ? '[' . $t[$k] . ']' : '(нет ключа)');
}
echo "\n=== фактический <title> и description сейчас (Yoast surface) ===\n";
foreach ([11,13,15] as $id) {
    $m = YoastSEO()->meta->for_post($id);
    printf("ID=%-3s title       = %s\n", $id, $m->title);
    printf("ID=%-3s description = %s\n", $id, $m->description);
    printf("ID=%-3s breadcrumb  = %s\n", $id, json_encode(array_column($m->breadcrumbs, 'text'), JSON_UNESCAPED_UNICODE));
    echo "\n";
}
