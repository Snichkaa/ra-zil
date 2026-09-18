<?php
/**
 * Этап 4.0. Разведка CSS. Только чтение.
 * Источники: tokens.css, main.css, editor-styles.css,
 * сгенерированный ядром global stylesheet из theme.json,
 * и таблица блоков ядра.
 */
require_once __DIR__ . '/../../wp-load.php';

/* ------------------------------------------------------------------ сбор источников */

$sources = [];
$themeCss = WP_CONTENT_DIR . '/themes/razil/assets/css/';
foreach (['tokens.css', 'main.css', 'editor-styles.css'] as $f) {
    $sources[$f] = file_get_contents($themeCss . $f);
}

// Сгенерированный ядром из theme.json.
$sources['[WP global stylesheet из theme.json]'] = wp_get_global_stylesheet();

// Таблица стилей блоков ядра (wp-block-library), она НЕ снимается темой.
$core = ABSPATH . WPINC . '/css/dist/block-library/style.min.css';
if (file_exists($core)) { $sources['[core block-library/style.min.css]'] = file_get_contents($core); }
// Снимается только wp-block-library-theme.
$coreTheme = ABSPATH . WPINC . '/css/dist/block-library/theme.min.css';
if (file_exists($coreTheme)) { $sources['[core block-library/theme.min.css — СНЯТА темой]'] = file_get_contents($coreTheme); }

/* ------------------------------------------------------------------ разбор правил */

/** Диапазоны @-блоков, чтобы знать контекст правила. */
function at_blocks(string $css): array {
    $out = [];
    $len = strlen($css);
    for ($i = 0; $i < $len; $i++) {
        if ($css[$i] !== '@') { continue; }
        $brace = strpos($css, '{', $i);
        $semi  = strpos($css, ';', $i);
        if ($brace === false) { break; }
        if ($semi !== false && $semi < $brace) { $i = $semi; continue; } // @import и подобные
        $prelude = trim(substr($css, $i, $brace - $i));
        // сбалансированный проход
        $depth = 0; $j = $brace;
        for (; $j < $len; $j++) {
            if ($css[$j] === '{') { $depth++; }
            elseif ($css[$j] === '}') { $depth--; if ($depth === 0) { break; } }
        }
        $out[] = ['prelude' => preg_replace('/\s+/', ' ', $prelude), 'start' => $i, 'end' => $j];
        $i = $brace; // вложенные @ найдём на следующих итерациях
    }
    return $out;
}

function rules_of(string $css): array {
    $ats = at_blocks($css);
    $rules = [];
    if (!preg_match_all('/([^{}@][^{}]*?)\{([^{}]*)\}/s', $css, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return $rules;
    }
    foreach ($m as $set) {
        $sel = trim($set[1][0]);
        $off = $set[1][1];
        if ($sel === '') { continue; }
        $ctx = [];
        foreach ($ats as $a) { if ($off > $a['start'] && $off < $a['end']) { $ctx[] = $a['prelude']; } }
        $rules[] = [
            'selector' => preg_replace('/\s+/', ' ', $sel),
            'selector_raw' => $sel,
            'decls'    => trim($set[2][0]),
            'offset'   => $off,
            'line'     => substr_count(substr($css, 0, $off), "\n") + 1,
            'at'       => $ctx,
        ];
    }
    return $rules;
}

$parsed = [];
foreach ($sources as $name => $css) { $parsed[$name] = rules_of($css); }

function show_rule(string $src, array $r): void {
    $at = $r['at'] ? implode(' / ', $r['at']) . ' { ' : '';
    printf("--- %s : строка %d%s\n", $src, $r['line'], $r['at'] ? '   в ' . implode(' / ', $r['at']) : '');
    echo $r['selector_raw'] . " {\n";
    foreach (preg_split('/;\s*/', $r['decls']) as $d) {
        $d = trim($d);
        if ($d !== '') { echo "\t" . $d . ";\n"; }
    }
    echo "}\n";
}

/** Специфичность: (id, class/attr/pseudo-class, element/pseudo-element) по худшему из составных селекторов. */
function specificity(string $selector): array {
    $best = [0, 0, 0];
    foreach (explode(',', $selector) as $part) {
        $p = trim($part);
        if ($p === '') { continue; }
        // :where() не добавляет специфичности — вырезаем вместе с содержимым
        $p2 = preg_replace('/:where\([^()]*\)/i', '', $p);
        $ids = preg_match_all('/#[\w-]+/', $p2);
        $cls = preg_match_all('/\.[\w-]+/', $p2)
             + preg_match_all('/\[[^\]]+\]/', $p2)
             + preg_match_all('/:(?!:)(?!where\b)[\w-]+(\([^()]*\))?/i', $p2);
        $els = preg_match_all('/(^|[\s>+~])([a-zA-Z][\w-]*)/', $p2)
             + preg_match_all('/::[\w-]+/', $p2);
        $s = [$ids, $cls, $els];
        if ($s[0] * 10000 + $s[1] * 100 + $s[2] > $best[0] * 10000 + $best[1] * 100 + $best[2]) { $best = $s; }
    }
    return $best;
}

/* ================================================================== 4.0.1 */
echo "################################################################\n";
echo "## 4.0.1. ПРАВИЛА .rz-first-aid*\n";
echo "################################################################\n";
$needles401 = ['.rz-first-aid__warning', '.rz-first-aid__note', '.rz-first-aid__steps', '.rz-first-aid'];
$seen = [];
$n401 = 0;
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (strpos($r['selector'], '.rz-first-aid') === false) { continue; }
        $key = $src . '|' . $r['offset'];
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        show_rule($src, $r);
        $n401++;
    }
}
printf("\nвсего правил с .rz-first-aid: %d\n", $n401);

/* ================================================================== 4.0.2 */
echo "\n################################################################\n";
echo "## 4.0.2. ПОЗИЦИОННЫЕ ПРАВИЛА, попадающие в rz-first-aid__warning\n";
echo "################################################################\n";

// Точные шаблоны из задания.
$literal = [
    '.rz-first-aid > p',
    '.rz-section > p',
    '.wp-block-group > p',
    '+ p',
    '+ .rz-first-aid__warning',
    'p + p',
];
echo "-- поиск буквальных шаблонов --\n";
foreach ($literal as $lit) {
    $hits = 0;
    foreach ($parsed as $src => $rules) {
        foreach ($rules as $r) {
            if (strpos($r['selector'], $lit) !== false) { $hits++; show_rule($src, $r); }
        }
    }
    printf("«%s»: %d вхождений%s\n", $lit, $hits, $hits === 0 ? ' -> не найдено' : '');
}

echo "\n-- поиск позиционных псевдоклассов в контексте .rz-first-aid / .rz-section / .rz-advantages --\n";
$posPseudo = [':last-child', ':first-child', ':nth-child', ':nth-of-type', ':last-of-type', ':first-of-type', '* + *'];
$found402 = [];
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        $sel = $r['selector'];
        $hasPos = false;
        foreach ($posPseudo as $pp) { if (strpos($sel, $pp) !== false) { $hasPos = true; break; } }
        if (!$hasPos) { continue; }
        $inCtx = (strpos($sel, '.rz-first-aid') !== false)
              || (strpos($sel, '.rz-section') !== false)
              || (strpos($sel, '.wp-block-group') !== false)
              || (strpos($sel, 'is-layout') !== false);
        if (!$inCtx) { continue; }
        $found402[] = [$src, $r];
        show_rule($src, $r);
    }
}
printf("\nпозиционных правил в релевантном контексте: %d\n", count($found402));

echo "\n-- ВСЕ правила, где вообще есть комбинатор '+' и которые могут достать до абзаца --\n";
$adj = 0;
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (strpos($r['selector'], '+') === false) { continue; }
        // только те, что теоретически матчат p внутри группы секции
        if (!preg_match('/(\bp\b|\*|:not\(|is-layout|wp-block-group|rz-)/', $r['selector'])) { continue; }
        $adj++;
        show_rule($src, $r);
    }
}
printf("\nправил с комбинатором '+', способных достать до абзаца: %d\n", $adj);

/* ================================================================== 4.0.3 */
echo "\n################################################################\n";
echo "## 4.0.3. ПРАВИЛА .rz-reviews*\n";
echo "################################################################\n";
$n403 = 0;
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (!preg_match('/\.rz-reviews__link|\.rz-reviews-section|\.rz-reviews__head/', $r['selector'])) { continue; }
        show_rule($src, $r);
        $n403++;
    }
}
printf("\nвсего: %d\n", $n403);

echo "\n-- ВЫРАВНИВАНИЕ у .rz-reviews__link --\n";
$alignFound = false;
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (strpos($r['selector'], '.rz-reviews__link') === false) { continue; }
        if (preg_match('/text-align\s*:\s*([^;]+)/i', $r['decls'], $m)) {
            printf("НАЙДЕНО: %s -> text-align: %s   (%s строка %d)\n",
                $r['selector'], trim($m[1]), $src, $r['line']);
            $alignFound = true;
        }
    }
}
if (!$alignFound) { echo "text-align у .rz-reviews__link НЕ задан ни в одном правиле\n"; }

echo "\n-- заодно: всё, что задаёт выравнивание внутри .rz-reviews-section --\n";
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (strpos($r['selector'], 'rz-reviews') === false) { continue; }
        if (preg_match('/text-align|justify-content|align-items|margin-inline|margin-left|margin-right/i', $r['decls'])) {
            show_rule($src, $r);
        }
    }
}

/* ================================================================== 4.0.4 */
echo "\n################################################################\n";
echo "## 4.0.4. .rz-btn-outline И БАЗОВАЯ ЗАЛИВКА КНОПОК\n";
echo "################################################################\n";
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (strpos($r['selector'], '.rz-btn-outline') === false) { continue; }
        show_rule($src, $r);
        printf("    специфичность: (%d,%d,%d)\n", ...specificity($r['selector']));
    }
}

echo "\n-- правила из theme.json elements.button (сгенерированные ядром) --\n";
foreach ($parsed as $src => $rules) {
    foreach ($rules as $r) {
        if (!preg_match('/wp-element-button|wp-block-button__link/', $r['selector'])) { continue; }
        if (!preg_match('/background|color/i', $r['decls'])) { continue; }
        show_rule($src, $r);
        printf("    специфичность: (%d,%d,%d)\n", ...specificity($r['selector']));
    }
}

echo "\n-- ПОРЯДОК ПЕЧАТИ ТАБЛИЦ СТИЛЕЙ (фактическая очередь WP) --\n";
do_action('wp_enqueue_scripts');
global $wp_styles;
if ($wp_styles) {
    $wp_styles->all_deps($wp_styles->queue);
    foreach ($wp_styles->to_do as $i => $handle) {
        $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';
        $inline = $wp_styles->get_data($handle, 'after');
        printf("  %2d. %-28s %s%s\n", $i + 1, $handle, $src ? $src : '(инлайн)',
            $inline ? '  [+ инлайн ' . strlen(is_array($inline) ? implode('', $inline) : $inline) . ' байт]' : '');
    }
}

/* ================================================================== 4.0.5 */
echo "\n################################################################\n";
echo "## 4.0.5. КНОПКА rz-reviews-list__more В render.php\n";
echo "################################################################\n";
$render = WP_CONTENT_DIR . '/themes/razil/src/blocks/reviews/render.php';
$lines = file($render);
printf("файл: src/blocks/reviews/render.php, %d строк\n\n", count($lines));
// показываем окно вокруг кнопки и всю ветку display
foreach ($lines as $i => $l) {
    if (strpos($l, 'rz-reviews-list__more') !== false || strpos($l, "'display'") !== false
        || strpos($l, '$rz_display') !== false || strpos($l, 'limit') !== false) {
        printf("%3d| %s", $i + 1, $l);
    }
}
echo "\n-- ветка display=list целиком (строки 125-160) --\n";
for ($i = 124; $i < 160 && $i < count($lines); $i++) { printf("%3d| %s", $i + 1, $lines[$i]); }

echo "\n-- начало render.php: как читаются атрибуты (строки 1-60) --\n";
for ($i = 0; $i < 60 && $i < count($lines); $i++) { printf("%3d| %s", $i + 1, $lines[$i]); }

/* ================================================================== 4.0.6 */
echo "\n################################################################\n";
echo "## 4.0.6. .rz-callout И .rz-emergency-tel В CSS\n";
echo "################################################################\n";
foreach (['tokens.css', 'main.css', 'editor-styles.css'] as $f) {
    $css = $sources[$f];
    printf("%-20s rz-callout: %d   rz-emergency-tel: %d   (%d байт, md5 %s)\n",
        $f, substr_count($css, 'rz-callout'), substr_count($css, 'rz-emergency-tel'),
        strlen($css), md5($css));
}
$g = $sources['[WP global stylesheet из theme.json]'];
printf("%-20s rz-callout: %d   rz-emergency-tel: %d\n", 'global stylesheet',
    substr_count($g, 'rz-callout'), substr_count($g, 'rz-emergency-tel'));
