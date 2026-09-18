<?php
require dirname(__DIR__, 2) . '/wp-load.php';
$t = get_option('wpseo_titles');
echo "wpseo_titles['separator'] = [" . ($t['separator'] ?? '(нет)') . "]\n";
echo "title-services            = [" . ($t['title-services'] ?? '(нет)') . "]\n";
echo "metadesc-services         = [" . ($t['metadesc-services'] ?? '(нет)') . "]\n";
echo "blogname (sitename)       = [" . get_bloginfo('name') . "]\n";
echo "\n=== раскрытие плейсхолдеров на ID=11 (read-only, wpseo_replace_vars) ===\n";
$src = 'Организация похорон в Хабаровске %%sep%% %%sitename%%';
echo "шаблон  = [" . $src . "]\n";
echo "результат = [" . wpseo_replace_vars($src, get_post(11)) . "]\n";
echo "разведка  = [Организация похорон в Хабаровске - Земля и Люди]\n";
echo "совпадение: " . (wpseo_replace_vars($src, get_post(11)) === 'Организация похорон в Хабаровске - Земля и Люди' ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
