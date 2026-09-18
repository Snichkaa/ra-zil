<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb, $wp_filter;

$BS = chr(92); // обратный слеш; в heredoc этой среды литерал не выживает

echo "=== 1. get_option('active_plugins') ===\n";
var_export(get_option('active_plugins'));
echo "\n\n";

echo "=== 1b. все установленные плагины ===\n";
if (!function_exists('get_plugins')) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
foreach (get_plugins() as $file => $data) {
    printf("%-46s %-30s v%-10s active=%s\n", $file, $data['Name'], $data['Version'], is_plugin_active($file) ? 'YES' : 'no');
}
echo "\n";

echo "=== 2. опции в БД ===\n";
foreach (['wpseo', 'wpseo_titles', 'rank-math-options-titles', 'rank-math-options-general', 'rank_math_titles'] as $o) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT option_id, option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options} WHERE option_name=%s", $o));
    if ($row) {
        printf("%-28s ЕСТЬ  option_id=%-5s len=%-7s autoload=%s\n", $o, $row->option_id, $row->len, $row->autoload);
    } else {
        printf("%-28s НЕТ в wp_options\n", $o);
    }
}
echo "\nвсе option_name LIKE 'rank%math%':\n";
$rm = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'rank%math%'");
echo $rm ? implode("\n", $rm) . "\n" : "(нет ни одной)\n";
echo "\nвсе option_name LIKE 'wpseo%':\n";
$ys = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpseo%'");
echo $ys ? implode("\n", $ys) . "\n" : "(нет ни одной)\n";
echo "\n";

echo "=== 3. callback'и на фильтрах заголовка ===\n";
$describe = function ($cb) use ($BS) {
    try {
        if ($cb instanceof Closure)       { $r = new ReflectionFunction($cb); $n = 'Closure'; }
        elseif (is_string($cb))           { $r = new ReflectionFunction($cb); $n = $cb; }
        elseif (is_array($cb))            { $cls = is_object($cb[0]) ? get_class($cb[0]) : $cb[0];
                                            $r = new ReflectionMethod($cls, $cb[1]); $n = $cls . '::' . $cb[1]; }
        elseif (is_object($cb))           { $r = new ReflectionMethod(get_class($cb), '__invoke'); $n = get_class($cb) . '::__invoke'; }
        else                              { return ['(неопознан)', '']; }
        $f = str_replace($BS, '/', (string) $r->getFileName());
        $f = str_replace(str_replace($BS, '/', ABSPATH), '', $f);
        return [$n, $f . ':' . $r->getStartLine()];
    } catch (Throwable $e) { return ['(reflection error)', $e->getMessage()]; }
};
$dump = function ($hook) use (&$wp_filter, $describe) {
    echo "--- {$hook} ---\n";
    if (empty($wp_filter[$hook])) { echo "  (callback'ов нет)\n"; return; }
    foreach ($wp_filter[$hook]->callbacks as $prio => $cbs) {
        foreach ($cbs as $key => $cb) {
            list($n, $src) = $describe($cb['function']);
            printf("  prio=%-4s %-62s %s\n", $prio, $n, $src);
        }
    }
};
foreach (['pre_get_document_title', 'document_title_parts', 'wp_title'] as $h) { $dump($h); }

echo "\nПРИМЕЧАНИЕ: фронтовые интеграции Yoast/Rank Math цепляются позже, на 'wp'.\n";
echo "Ниже тот же дамп после do_action('wp') на смоделированном запросе /uslugi/organizatsiya-pohoron/.\n\n";

$GLOBALS['wp_the_query'] = new WP_Query(['p' => 11, 'post_type' => 'services']);
$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
$GLOBALS['post']         = get_post(11);
do_action('wp', $GLOBALS['wp']);

foreach (['pre_get_document_title', 'document_title_parts'] as $h) { $dump($h . ''); }
echo "\n";
foreach (['pre_get_document_title', 'document_title_parts'] as $h) {
    echo "has_filter('{$h}') = " . var_export(has_filter($h), true) . "\n";
}

echo "\n=== 4. мета ID=11 ===\n";
foreach (['rank_math_title', 'rank_math_description', '_yoast_wpseo_title', '_yoast_wpseo_metadesc'] as $k) {
    $cnt = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=11 AND meta_key=%s", $k));
    $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=11 AND meta_key=%s", $k));
    printf("%-24s строк=%s  значение=%s\n", $k, $cnt, $raw === null ? '(ключа нет)' : '[' . $raw . ']');
}
echo "\nвсе meta_key записи 11:\n";
print_r($wpdb->get_col("SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id=11 ORDER BY meta_key"));
echo "\nmeta_key LIKE 'rank_math%' по всей postmeta:\n";
$rmk = $wpdb->get_results("SELECT meta_key, COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key LIKE 'rank_math%' GROUP BY meta_key");
echo $rmk ? print_r($rmk, true) : "(нет ни одного)\n";
echo "\nmeta_key LIKE '_yoast%' по всей postmeta:\n";
$yk = $wpdb->get_results("SELECT meta_key, COUNT(*) c FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yoast%' GROUP BY meta_key");
echo $yk ? print_r($yk, true) : "(нет ни одного)\n";
