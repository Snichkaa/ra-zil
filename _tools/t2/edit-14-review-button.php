<?php
/**
 * Этап 4.2. Кнопка «Оставить отзыв на сайте» последним ребёнком
 * внутренней группы блока [13]. Ветка без align.
 * Порядок: кандидат в памяти -> круговорот на кандидате -> запись -> сверка с диском.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$content = file_get_contents($file);

echo "######## ИСХОДНОЕ СОСТОЯНИЕ ########\n";
printf("до : %d байт, md5 %s\n", strlen($content), md5($content));
$expectMd5 = '86741a1827f5ad465e20b0f385b157bc';
printf("ожидалось после 4.1: %s -> %s\n", $expectMd5, md5($content) === $expectMd5 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
if (md5($content) !== $expectMd5) { echo "STOP\n"; exit(1); }

$nOtzyvyBefore = substr_count($content, 'href="/otzyvy/"');
$nBtnBefore = substr_count($content, 'rz-btn-outline');
printf("\n«href=\"/otzyvy/\"» до: %d\n", $nOtzyvyBefore);
printf("«rz-btn-outline» до: %d\n", $nBtnBefore);
printf("строк до: %d   CR до: %d\n", count(explode("\r\n", $content)), substr_count($content, "\r"));

/* ---------------------------------------------- old_str */
echo "\n######## OLD_STR: три строки абзаца rz-reviews__link ########\n";

$marker = '<!-- wp:paragraph {"className":"rz-reviews__link"} -->';
$n = substr_count($content, $marker);
printf("вхождений делимитера: %d -> %s\n", $n, $n === 1 ? 'OK' : 'ОШИБКА');
if ($n !== 1) { echo "STOP\n"; exit(1); }

$mPos = strpos($content, $marker);
$prevBreak = strrpos(substr($content, 0, $mPos), "\r\n");
$oStart = ($prevBreak === false) ? 0 : $prevBreak + 2;
$indent = substr($content, $oStart, $mPos - $oStart);
printf("фактический отступ абзаца: %d табов, hex %s\n", strlen($indent), bin2hex($indent));
if (trim($indent, "\t") !== '' || $indent === '') { echo "STOP: неожиданный отступ\n"; exit(1); }

$closer = '<!-- /wp:paragraph -->';
$cPos = strpos($content, $closer, $mPos);
$afterCloser = $cPos + strlen($closer);
if (substr($content, $afterCloser, 2) !== "\r\n") { echo "STOP: нет CRLF после делимитера\n"; exit(1); }
$oEnd = $afterCloser + 2;
$old = substr($content, $oStart, $oEnd - $oStart);

printf("old_str: байты %d..%d, длина %d, строк %d\n", $oStart, $oEnd - 1, strlen($old), substr_count($old, "\r\n"));
echo "old_str целиком:\n>>>" . $old . "<<<\n";

/* ---------------------------------------------- new_str */
echo "\n######## NEW_STR: абзац без изменений + пустая строка + кнопка ########\n";

$T = $indent;            // 2 таба, как у абзаца
$T2 = $indent . "\t";    // 3 таба для <a>, как у соседних вложений

$button = $T . '<!-- wp:button {"className":"rz-btn-outline"} -->' . "\r\n"
        . $T . '<div class="wp-block-button rz-btn-outline">' . "\r\n"
        . $T2 . '<a class="wp-block-button__link wp-element-button" href="/otzyvy/">Оставить отзыв на сайте</a>' . "\r\n"
        . $T . '</div>' . "\r\n"
        . $T . '<!-- /wp:button -->' . "\r\n";

// Пустая строка-разделитель: так разделены все соседние блоки этой группы
// (строки 334 и 336 файла). Без неё кнопка была бы единственным
// исключением в блоке.
$new = $old . "\r\n" . $button;

printf("строк в кнопке: %d\n", substr_count($button, "\r\n"));
printf("пустая строка-разделитель: 1\n");
printf("итого новых строк: %+d\n", substr_count($new, "\r\n") - substr_count($old, "\r\n"));
printf("прирост байт: %+d\n", strlen($new) - strlen($old));
echo "new_str целиком:\n>>>" . $new . "<<<\n";

printf("\nабзац rz-reviews__link внутри new_str не изменён: %s\n",
    strpos($new, $old) === 0 ? 'YES (new начинается ровно с old)' : 'NO');

/* ---------------------------------------------- кандидат */
echo "\n######## КАНДИДАТ В ПАМЯТИ ########\n";
$cnt = 0;
$cand = str_replace($old, $new, $content, $cnt);
printf("str_replace count: %d -> %s\n", $cnt, $cnt === 1 ? 'OK' : 'ОШИБКА');
if ($cnt !== 1) { echo "STOP: не пишу\n"; exit(1); }
printf("кандидат: %d байт (%+d)\n", strlen($cand), strlen($cand) - strlen($content));

/* ---------------------------------------------- проверки на кандидате */
echo "\n######## ПРОВЕРКИ НА КАНДИДАТЕ, ДО ЗАПИСИ ########\n";
$round = serialize_blocks(parse_blocks($cand));
$roundOk = ($round === $cand);
printf("serialize_blocks(parse_blocks(cand)) === cand: %s\n", $roundOk ? 'YES' : 'NO');
if (!$roundOk) {
    $n2 = min(strlen($cand), strlen($round));
    for ($i = 0; $i < $n2; $i++) { if ($cand[$i] !== $round[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "кандидат  >>>" . substr($cand, max(0, $i - 120), 240) . "<<<\n";
    echo "круговорот>>>" . substr($round, max(0, $i - 120), 240) . "<<<\n";
    echo "STOP: не пишу\n"; exit(1);
}

$dash = chr(45) . chr(45);
$viol = 0; $checked = 0;
foreach (explode("\r\n", $cand) as $ln => $line) {
    $t = ltrim($line, "\t ");
    if (strpos($t, '<!-- wp:') !== 0 && strpos($t, '<!-- /wp:') !== 0) { continue; }
    $checked++;
    $inner = substr($t, 4);
    $tail = strrpos($inner, '-->');
    if ($tail !== false) { $inner = substr($inner, 0, $tail); }
    $inner = rtrim($inner, '/');
    if (strpos($inner, $dash) !== false) { $viol++; printf("  НАРУШЕНИЕ строка %d: %s\n", $ln + 1, $line); }
}
printf("строк-делимитеров проверено: %d, нарушений: %d -> %s\n", $checked, $viol, $viol === 0 ? 'OK' : 'ОШИБКА');
if ($viol !== 0) { echo "STOP: не пишу\n"; exit(1); }

printf("«Оставить отзыв на сайте» в кандидате: %d\n", substr_count($cand, 'Оставить отзыв на сайте'));
printf("«href=\"/otzyvy/\"» в кандидате: %d\n", substr_count($cand, 'href="/otzyvy/"'));
printf("«rz-btn-outline» в кандидате: %d\n", substr_count($cand, 'rz-btn-outline'));
printf("«textColor» в кнопке: %d (должно быть 0)\n", substr_count($button, 'textColor'));
printf("«has-surface-color» в кнопке: %d (должно быть 0)\n", substr_count($button, 'has-surface-color'));
printf("«wp:buttons» в кандидате: %d (обёртки core/buttons нет)\n", preg_match_all('/<!--\s*\/?wp:buttons\b/', $cand));

/* ---------------------------------------------- запись */
echo "\n######## ЗАПИСЬ ########\n";
$written = file_put_contents($file, $cand);
printf("file_put_contents вернул: %s (кандидат %d байт)\n", var_export($written, true), strlen($cand));

clearstatcache(true, $file);
$reread = file_get_contents($file);
printf("\nпрочитанное с диска: %d байт, md5 %s\n", strlen($reread), md5($reread));
printf("кандидат в памяти  : %d байт, md5 %s\n", strlen($cand), md5($cand));
$transportOk = ($reread === $cand);
printf("ПОБАЙТОВО РАВНО КАНДИДАТУ: %s\n", $transportOk ? 'YES' : 'NO');

/* ---------------------------------------------- проверки после записи */
echo "\n######## ПРОВЕРКИ ПОСЛЕ ЗАПИСИ ########\n";
$nText = substr_count($reread, 'Оставить отзыв на сайте');
$nHref = substr_count($reread, 'href="/otzyvy/"');
$nBtn  = substr_count($reread, 'rz-btn-outline');
$lines = explode("\r\n", $reread);
$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
$roundAfter = serialize_blocks(parse_blocks($reread));

printf("«Оставить отзыв на сайте»: %d -> %s (требуется 1)\n", $nText, $nText === 1 ? 'OK' : 'ОШИБКА');
printf("«href=\"/otzyvy/\"» было / стало: %d / %d -> %s\n", $nOtzyvyBefore, $nHref,
    ($nOtzyvyBefore === 0 && $nHref === 1) ? 'OK' : 'ОШИБКА');
printf("«rz-btn-outline»: %d -> %s (требуется 2: в JSON и в class)\n", $nBtn, $nBtn === 2 ? 'OK' : 'ОШИБКА');
printf("строк: %d   CR: %d   LF: %d   одиночных CR: %d   одиночных LF: %d\n",
    count($lines), $cr, $lf, $cr - $crlf, $lf - $crlf);
printf("размер: %d (%+d к 23491)\n", strlen($reread), strlen($reread) - 23491);
printf("по арифметике: 23491 - %d + %d = %d -> %s\n", strlen($old), strlen($new),
    23491 - strlen($old) + strlen($new), strlen($reread) === 23491 - strlen($old) + strlen($new) ? 'СОШЛОСЬ' : 'НЕ СОШЛОСЬ');
printf("md5: %s\n", md5($reread));
printf("parse_blocks -> serialize_blocks identical: %s\n", $reread === $roundAfter ? 'YES' : 'NO');
printf("первые 8 байт: %s   последние 2 байта: %s   BOM: %s\n",
    implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)),
    implode(' ', str_split(bin2hex(substr($reread, -2)), 2)),
    substr($reread, 0, 3) === "\xEF\xBB\xBF" ? 'ЕСТЬ' : 'нет');

echo "\n-- ДЕРЕВО БЛОКА [13] ЦЕЛИКОМ --\n";
$blocks = parse_blocks($reread);
$walk = function ($bs, $path, $depth) use (&$walk) {
    foreach ($bs as $i => $b) {
        $p = $path === '' ? (string) $i : $path . '.' . $i;
        $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
        if ($name === '(null)' && trim($b['innerHTML']) === '') { continue; }
        $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
        $extra = $b['attrs'];
        unset($extra['className']);
        printf("%s[%s] %-20s %s%s\n", str_repeat('  ', $depth), $p, $name,
            $cn !== '' ? "className='" . $cn . "'" : '',
            $extra ? '  attrs=' . json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
        if (!empty($b['innerBlocks'])) { $walk($b['innerBlocks'], $p, $depth + 1); }
    }
};
$walk([13 => $blocks[13]], '', 0);

echo "\n-- РЕНДЕР БЛОКА [13]: фактические классы кнопки --\n";
$rendered = do_blocks(serialize_block($blocks[13]));
if (preg_match('/<div class="([^"]*wp-block-button[^"]*)"/', $rendered, $mDiv)) {
    echo "div.wp-block-button: " . $mDiv[1] . "\n";
    foreach (preg_split('/\s+/', trim($mDiv[1])) as $c) { echo "  - " . $c . "\n"; }
}
if (preg_match('/<a class="([^"]*wp-block-button__link[^"]*)"[^>]*href="([^"]*)"/', $rendered, $mA)) {
    echo "\n<a> class: " . $mA[1] . "\n";
    foreach (preg_split('/\s+/', trim($mA[1])) as $c) { echo "  - " . $c . "\n"; }
    echo "<a> href : " . $mA[2] . "\n";
}
$hasSurface = (strpos($rendered, 'has-surface-color') !== false);
printf("\nhas-surface-color в отрендеренном блоке [13]: %s -> %s\n",
    $hasSurface ? 'ЕСТЬ' : 'НЕТ', $hasSurface ? 'ОШИБКА' : 'OK');

echo "\n-- готовая разметка кнопки в DOM --\n";
if (preg_match('#<div class="[^"]*wp-block-button[^"]*">.*?</div>#us', $rendered, $mFull)) {
    echo $mFull[0] . "\n";
}

echo "\n-- ВЫЧИСЛЕННЫЙ ФОН И ЦВЕТ: какое правило побеждает --\n";
$mainCss = file_get_contents(WP_CONTENT_DIR . '/themes/razil/assets/css/main.css');
$glob = wp_get_global_stylesheet();

echo "\nПРАВИЛО А — базовая заливка (global-styles, таблица #2 в очереди печати):\n";
foreach (explode('}', $glob) as $chunk) {
    if (strpos($chunk, ':where(.wp-element-button, .wp-block-button__link)') !== false
        && strpos($chunk, 'background-color') !== false) {
        echo '  ' . trim($chunk) . "}\n";
        break;
    }
}
echo "  селектор: :root :where(.wp-element-button, .wp-block-button__link)\n";
echo "  специфичность: (0,1,0)  — :root весит один класс, :where() не весит вовсе\n";

echo "\nПРАВИЛО Б — аутлайн (main.css, таблица #4 в очереди печати):\n";
$pos = strpos($mainCss, '.rz-btn-outline .wp-block-button__link,');
$end = strpos($mainCss, '}', $pos);
echo '  ' . trim(substr($mainCss, $pos, $end - $pos + 1)) . "\n";
echo "  селектор: .rz-btn-outline .wp-block-button__link\n";
echo "  специфичность: (0,2,0)\n";

$specA = [0, 1, 0];
$specB = [0, 2, 0];
$winner = ($specB[1] > $specA[1]) ? 'Б (аутлайн)' : (($specA[1] > $specB[1]) ? 'А (заливка)' : 'по порядку: Б, main.css печатается позже');
printf("\nсравнение: А (0,1,0) против Б (0,2,0)\n");
printf("ПОБЕЖДАЕТ: правило %s\n", $winner);
printf("  background-color = transparent\n");
printf("  color            = var(--rz-action)\n");
printf("  border           = 1px solid var(--rz-action)\n");
printf("аутлайн выигрывает и по специфичности, и по порядку печати\n");

$fillWins = ($specA[1] > $specB[1]);
if ($fillWins) { echo "\nSTOP: побеждает заливка, кнопка неверна\n"; exit(1); }
printf("\nвердикт: заливка НЕ перекрывает прозрачный фон -> кнопка верна\n");

$ok = $roundOk && $transportOk && $viol === 0 && $nText === 1 && $nHref === 1 && $nBtn === 2
    && ($cr - $crlf) === 0 && ($lf - $crlf) === 0 && $reread === $roundAfter && !$hasSurface && !$fillWins;
echo "\nЭТАП 4.2: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
