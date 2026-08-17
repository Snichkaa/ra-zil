<?php
/**
 * Разбор CommerceML-выгрузки старого сайта (Битрикс) в _import/content-xml.json.
 *
 * Берём только инфоблоки с реальным контентом агентства.
 * Демо-данные шаблона intec (бренды, вакансии, тарифы, статьи про баню и т.п.)
 * не трогаем совсем. Два спорных инфоблока помечаем suspect_demo.
 *
 * Ничего не импортирует в WordPress — только читает XML и пишет JSON.
 */

declare(strict_types=1);

const XML_DIR  = __DIR__ . '/old-bitrix/upload/additional';
const OUT_FILE = __DIR__ . '/content-xml.json';

/**
 * Что разбираем: файл => [тип записи, человекочитаемое имя инфоблока, спорный ли].
 */
const SOURCES = [
    '5.xml'  => ['service',        'Услуги',          false],
    '21.xml' => ['review',         'Отзывы',          false],
    '29.xml' => ['faq',            'Вопрос - ответ',  false],
    '31.xml' => ['city',           'Контакты',        false],
    '1.xml'  => ['city',           'Мультигород',     false],
    '2.xml'  => ['product',        'Товары',          false],
    '11.xml' => ['advantage',      'Иконки',          false],
    '23.xml' => ['staff',          'Сотрудники',      true],
    '8.xml'  => ['service_review', 'Услуги. Отзывы',  true],
];

/**
 * Города: что делать с каждым. Хабаровск нужен, Комсомольск — на согласование,
 * Амурск и Биробиджан уже убраны из структуры сайта.
 */
const CITY_STATUS = [
    'khabarovsk'  => 'keep',
    'komsomolsk'  => 'review',
    'amursk'      => 'removed',
    'birobidzhan' => 'removed',
];

/** Схлопывает пробелы в одну строку. */
function oneLine(string $s): string
{
    return trim((string) preg_replace('~\s+~u', ' ', $s));
}

/** Текст первого узла по запросу или ''. */
function nodeText(DOMXPath $xp, string $q, ?DOMNode $ctx = null): string
{
    $n = $xp->query($q, $ctx)->item(0);

    return $n === null ? '' : trim((string) $n->textContent);
}

/**
 * Свойства записи: Ид => ['value' => …, 'type' => …].
 *
 * @return array<string, array{value: string, type: string}>
 */
function readProps(DOMXPath $xp, DOMElement $item): array
{
    $out = [];
    foreach ($xp->query('./ЗначенияСвойств/ЗначенияСвойства', $item) as $p) {
        $id = nodeText($xp, './Ид', $p);
        if ($id === '') {
            continue;
        }
        $out[$id] = [
            'value' => nodeText($xp, './Значение', $p),
            'type'  => nodeText($xp, './Тип', $p),
        ];
    }

    return $out;
}

/** Проверяет наличие файла картинки на диске. */
function imageExists(string $rel): bool
{
    if ($rel === '') {
        return false;
    }

    return is_file(XML_DIR . '/' . ltrim($rel, '/'));
}

// ---------------------------------------------------------------- разбор

$records = [];
$stats   = [];
$missing = [];

foreach (SOURCES as $file => [$type, $ibLabel, $suspect]) {
    $path = XML_DIR . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "НЕТ ФАЙЛА: $path\n");
        continue;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $ok = $doc->loadXML((string) file_get_contents($path));
    libxml_clear_errors();

    if (!$ok) {
        fwrite(STDERR, "НЕ РАЗОБРАЛСЯ: $file\n");
        continue;
    }

    $xp = new DOMXPath($doc);

    // Названия свойств лежат в классификаторе: Ид => Наименование.
    // Без них адреса и телефоны городов остались бы под ключами «1», «3», «4».
    $labels = [];
    foreach ($xp->query('//Свойства/Свойство') as $p) {
        $pid = nodeText($xp, './Ид', $p);
        $pnm = nodeText($xp, './Наименование', $p);
        if ($pid !== '') {
            $labels[$pid] = $pnm;
        }
    }

    foreach ($xp->query('//Товар') as $item) {
        if (!$item instanceof DOMElement) {
            continue;
        }

        $props = readProps($xp, $item);

        $take = static function (string $key) use (&$props): string {
            if (!isset($props[$key])) {
                return '';
            }
            $v = $props[$key]['value'];
            unset($props[$key]);

            return $v;
        };

        $name        = oneLine(nodeText($xp, './Наименование', $item));
        $slug        = $take('CML2_CODE');
        $date        = $take('CML2_ACTIVE_FROM');
        $previewText = $take('CML2_PREVIEW_TEXT');
        $detailText  = $take('CML2_DETAIL_TEXT');
        $image       = $take('CML2_PREVIEW_PICTURE');

        // Картинки из <Картинка> — отдельно от превью-картинки свойства.
        $pictures = [];
        foreach ($xp->query('./Картинка', $item) as $pic) {
            $rel = trim((string) $pic->textContent);
            if ($rel !== '') {
                $pictures[] = ['path' => $rel, 'exists' => imageExists($rel)];
                if (!imageExists($rel)) {
                    $missing[] = "$file → $rel";
                }
            }
        }

        // Разделы инфоблока, если запись к ним привязана.
        $groups = [];
        foreach ($xp->query('./Группы/Ид', $item) as $g) {
            $groups[] = trim((string) $g->textContent);
        }

        $imgExists = imageExists($image);
        if ($image !== '' && !$imgExists) {
            $missing[] = "$file → $image";
        }

        // Остаток свойств: CML2_* оставляем всегда, прочие — только непустые
        // (в выгрузке десятки пустых числовых свойств-заглушек).
        $rest   = [];
        $fields = [];
        foreach ($props as $id => $p) {
            // Числовые Ид свойств PHP превращает в int-ключи — возвращаем к строке.
            $id = (string) $id;

            if (str_starts_with($id, 'CML2_')) {
                $rest[$id] = $p['value'];
                continue;
            }
            if ($p['value'] === '') {
                continue;
            }
            $fields[] = [
                'id'    => $id,
                'label' => $labels[$id] ?? '',
                'value' => $p['value'],
            ];
        }
        ksort($rest);

        $rec = [
            'type'         => $type,
            'source_file'  => $file,
            'infoblock'    => $ibLabel,
            'bitrix_id'    => nodeText($xp, './Ид', $item),
            'name'         => $name,
            'slug'         => $slug,
            'date'         => $date,
            'preview_text' => $previewText,
            'detail_text'  => $detailText,
            'image'        => $image,
            'image_exists' => $imgExists,
            // empty — строго по detail_text, как в задании.
            'empty'        => trim(strip_tags($detailText)) === '',
            // Но у отзывов и FAQ весь текст лежит в preview_text, поэтому
            // отдельно отмечаем записи, где нет вообще никакого текста.
            'preview_len'  => mb_strlen(trim(strip_tags($previewText))),
            'detail_len'   => mb_strlen(trim(strip_tags($detailText))),
            'no_text'      => trim(strip_tags($previewText)) === '' && trim(strip_tags($detailText)) === '',
        ];

        if ($suspect) {
            $rec['suspect_demo'] = true;
        }

        if ($type === 'city') {
            $rec['city_status'] = CITY_STATUS[$slug] ?? 'unknown';
        }

        $rec['pictures']  = $pictures;
        $rec['groups']    = $groups;
        $rec['fields']    = $fields;
        $rec['cml_props'] = $rest;

        $records[] = $rec;

        $key = $type . ($suspect ? ' (suspect_demo)' : '');
        $stats[$key] ??= ['всего' => 0, 'пустых' => 0, 'без текста' => 0, 'с картинкой' => 0, 'картинка битая' => 0];
        $stats[$key]['всего']++;
        if ($rec['empty']) {
            $stats[$key]['пустых']++;
        }
        if ($rec['no_text']) {
            $stats[$key]['без текста']++;
        }
        if ($image !== '') {
            $stats[$key][$imgExists ? 'с картинкой' : 'картинка битая']++;
        }
    }
}

// ---------------------------------------------------------------- запись

$imagesTotal   = 0;
$imagesOk      = 0;
$imagesBroken  = 0;
foreach ($records as $r) {
    if ($r['image'] !== '') {
        $imagesTotal++;
        $r['image_exists'] ? $imagesOk++ : $imagesBroken++;
    }
    foreach ($r['pictures'] as $p) {
        $imagesTotal++;
        $p['exists'] ? $imagesOk++ : $imagesBroken++;
    }
}

$result = [
    'source'          => 'CommerceML-выгрузка старого сайта ra-zil.ru (1С-Битрикс)',
    'source_dir'      => '_import/old-bitrix/upload/additional',
    'parsed_files'    => array_keys(SOURCES),
    'note'            => 'Демо-данные шаблона intec (бренды, вакансии, тарифы, статьи, проекты, галереи) не включены. '
                       . 'Инфоблоки 23.xml и 8.xml помечены suspect_demo — принадлежность к реальному контенту под вопросом.',
    'records_total'   => count($records),
    'stats_by_type'   => $stats,
    'images'          => [
        'ссылок всего' => $imagesTotal,
        'файл найден'  => $imagesOk,
        'файл потерян' => $imagesBroken,
    ],
    'records'         => $records,
];

file_put_contents(
    OUT_FILE,
    json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);

// ---------------------------------------------------------------- отчёт

echo str_repeat('=', 72) . PHP_EOL;
printf("%-24s %6s %8s %11s %9s %7s\n", 'тип', 'всего', 'empty', 'без текста', 'картинок', 'битых');
echo str_repeat('-', 72) . PHP_EOL;
foreach ($stats as $type => $s) {
    printf(
        "%-24s %6d %8d %11d %9d %7d\n",
        $type,
        $s['всего'],
        $s['пустых'],
        $s['без текста'],
        $s['с картинкой'],
        $s['картинка битая']
    );
}
echo str_repeat('-', 72) . PHP_EOL;
printf(
    "%-24s %6d %8d %11d\n",
    'ИТОГО',
    count($records),
    count(array_filter($records, static fn(array $r): bool => $r['empty'])),
    count(array_filter($records, static fn(array $r): bool => $r['no_text']))
);
echo str_repeat('=', 72) . PHP_EOL;

echo PHP_EOL . 'Картинок (превью + <Картинка>): ' . $imagesTotal
    . ' | найдено: ' . $imagesOk
    . ' | битых: ' . $imagesBroken . PHP_EOL;

if ($missing !== []) {
    echo PHP_EOL . 'Битые пути:' . PHP_EOL;
    foreach (array_unique($missing) as $m) {
        echo '  ' . $m . PHP_EOL;
    }
}

echo PHP_EOL . 'Города:' . PHP_EOL;
foreach ($records as $r) {
    if ($r['type'] === 'city') {
        printf("  %-12s %-24s %-9s %s\n", $r['slug'], $r['name'], $r['city_status'], $r['source_file']);
    }
}

echo PHP_EOL . 'Файл: ' . OUT_FILE . ' (' . number_format((float) filesize(OUT_FILE)) . ' байт)' . PHP_EOL;
