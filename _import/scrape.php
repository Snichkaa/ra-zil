<?php
/**
 * Сбор контента со страниц ra-zil.ru в _import/content.json.
 * Только сбор — ничего не импортирует в WordPress.
 *
 * Структура сайта (1С-Битрикс, шаблон «intec Universe»):
 *   div.intec-template-header      — шапка и меню            (отбрасываем)
 *   div.intec-template-breadcrumb  — хлебные крошки          (забираем отдельно)
 *   div.intec-template-title       — блок с H1               (забираем как h1)
 *   div.intec-template-page        — ★ основной контент      (забираем)
 *   div.intec-template-footer      — подвал и форма заявки   (отбрасываем)
 */

declare(strict_types=1);

const IN_FILE    = __DIR__ . DIRECTORY_SEPARATOR . 'urls.txt';
const OUT_FILE   = __DIR__ . DIRECTORY_SEPARATOR . 'content.json';
const DELAY_MS   = 300;
const HOST       = 'ra-zil.ru';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

/** Класс контейнера с основным контентом. */
const CONTENT_CLASS    = 'intec-template-page';
const TITLE_CLASS      = 'intec-template-title';
const BREADCRUMB_CLASS = 'intec-template-breadcrumb';

/** Атрибуты, в которых lazy-load прячет настоящий URL картинки. */
const LAZY_ATTRS = ['data-src', 'data-original', 'data-lazy-src', 'data-echo', 'data-url'];

// ---------------------------------------------------------------- утилиты

/**
 * Загружает страницу. Возвращает [html, status] или [null, status].
 *
 * @return array{0: ?string, 1: int}
 */
function fetchPage(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: " . USER_AGENT . "\r\n"
                               . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
                               . "Accept-Language: ru-RU,ru;q=0.9\r\n",
            'timeout'         => 30,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $ctx);

    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
            $status = (int) $m[1];
        }
    }

    return [$body === false ? null : $body, $status];
}

/** Схлопывает пробелы в одну строку. */
function oneLine(string $s): string
{
    return trim((string) preg_replace('~\s+~u', ' ', $s));
}

/**
 * Делает абсолютный URL из href относительно базового.
 */
function absolutize(string $href, string $base): ?string
{
    $href = trim($href);
    if ($href === '') {
        return null;
    }

    if (str_starts_with($href, '//')) {
        $href = (parse_url($base, PHP_URL_SCHEME) ?: 'https') . ':' . $href;
    }

    $p = parse_url($href);
    if ($p === false) {
        return null;
    }
    if (isset($p['scheme'])) {
        return $href;
    }

    $bp     = parse_url($base);
    $scheme = $bp['scheme'] ?? 'https';
    $bhost  = $bp['host'] ?? HOST;
    $bpath  = $bp['path'] ?? '/';

    if (str_starts_with($href, '/')) {
        $path = $href;
    } else {
        $dir  = rtrim(substr($bpath, 0, (int) strrpos($bpath, '/') + 1), '/');
        $path = $dir . '/' . $href;
    }

    $qpos  = strcspn($path, '?#');
    $tail  = substr($path, $qpos);
    $parts = [];
    foreach (explode('/', substr($path, 0, $qpos)) as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $seg;
    }

    return $scheme . '://' . $bhost . '/' . implode('/', $parts) . $tail;
}

/** Внутренняя ли ссылка (тот же домен). */
function isInternal(?string $url): bool
{
    if ($url === null) {
        return false;
    }
    $p = parse_url($url);
    if (!isset($p['scheme'], $p['host'])) {
        return false;
    }
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return false;
    }
    $h = strtolower($p['host']);

    return $h === HOST || $h === 'www.' . HOST;
}

/** Ищет элемент по классу (точное совпадение одного из классов). */
function byClass(DOMXPath $xp, string $class, ?DOMNode $ctx = null): ?DOMElement
{
    $q = '//div[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
    if ($ctx !== null) {
        $q = '.' . $q;
    }
    $n = $xp->query($q, $ctx)->item(0);

    return $n instanceof DOMElement ? $n : null;
}

/**
 * Копия узла с удалёнными поддеревьями по списку XPath-запросов.
 *
 * @param list<string> $remove
 */
function pruned(DOMElement $el, array $remove): DOMElement
{
    // Кодировку задаём явно: иначе saveHTML() на этом документе
    // превратит всю кириллицу в числовые сущности вида &#1050;.
    $doc           = new DOMDocument('1.0', 'UTF-8');
    $doc->encoding = 'UTF-8';
    $doc->appendChild($doc->importNode($el->cloneNode(true), true));

    $xp = new DOMXPath($doc);
    foreach ($remove as $q) {
        foreach (iterator_to_array($xp->query($q)) as $n) {
            $n->parentNode?->removeChild($n);
        }
    }

    /** @var DOMElement $root */
    $root = $doc->documentElement;

    return $root;
}

/** Копия узла без script/style/noscript. */
function stripNoise(DOMElement $el): DOMElement
{
    return pruned($el, ['//script | //style | //noscript']);
}

/**
 * Копия блока контента без служебных элементов.
 *
 * На двухколоночных страницах (памятники, оградки, реквизиты, политика…)
 * внутрь intec-template-page попадает левое меню intec-content-left —
 * это навигация, а не текст страницы, поэтому вырезаем.
 */
function cleanContent(DOMElement $el): DOMElement
{
    return pruned($el, [
        '//script | //style | //noscript',
        '//div[contains(concat(" ", normalize-space(@class), " "), " intec-content-left ")]',
        '//div[contains(concat(" ", normalize-space(@class), " "), " c-menu ")]',
        '//*[@data-role="menu"]',
    ]);
}

/**
 * Текст элемента с сохранением абзацных переносов.
 */
function readableText(DOMElement $el): string
{
    $blocks = [
        'p', 'div', 'br', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'section', 'article', 'header', 'footer', 'ul', 'ol', 'table', 'blockquote', 'hr',
    ];

    $buf = '';
    $walk = function (DOMNode $node) use (&$walk, &$buf, $blocks): void {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $buf .= $child->nodeValue;
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $isBlock = in_array($child->tagName, $blocks, true);
            if ($isBlock) {
                $buf .= "\n";
            }
            if ($child->tagName === 'td' || $child->tagName === 'th') {
                $buf .= ' ';
            }
            $walk($child);
            if ($isBlock) {
                $buf .= "\n";
            }
        }
    };
    $walk($el);

    // Нормализуем: пробелы внутри строк схлопываем, пустые строки — максимум одна.
    $lines = array_map(
        static fn(string $l): string => trim((string) preg_replace('~[ \t\x{00A0}]+~u', ' ', $l)),
        explode("\n", $buf)
    );
    $out  = [];
    $prev = '';
    foreach ($lines as $l) {
        if ($l === '' && $prev === '') {
            continue;
        }
        $out[] = $l;
        $prev  = $l;
    }

    return trim(implode("\n", $out));
}

/** Внутренний HTML элемента. */
function innerHtml(DOMElement $el): string
{
    $html = '';
    foreach ($el->childNodes as $child) {
        $html .= $el->ownerDocument->saveHTML($child);
    }

    return trim($html);
}

/**
 * Собирает <img> из поддерева с учётом lazy-load.
 *
 * @return list<array<string, mixed>>
 */
function collectImages(DOMElement $scope, string $base): array
{
    $out  = [];
    $seen = [];

    foreach ($scope->getElementsByTagName('img') as $img) {
        $lazyAttr = null;
        $raw      = '';

        foreach (LAZY_ATTRS as $attr) {
            $v = trim((string) $img->getAttribute($attr));
            if ($v !== '' && !str_starts_with($v, 'data:')) {
                $raw      = $v;
                $lazyAttr = $attr;
                break;
            }
        }
        if ($raw === '') {
            $raw = trim((string) $img->getAttribute('src'));
        }
        if ($raw === '') {
            continue;
        }

        $src = str_starts_with($raw, 'data:') ? $raw : absolutize($raw, $base);
        if ($src === null || isset($seen[$src])) {
            continue;
        }
        $seen[$src] = true;

        $out[] = [
            'src'         => $src,
            'alt'         => oneLine((string) $img->getAttribute('alt')),
            'title'       => oneLine((string) $img->getAttribute('title')),
            'lazy'        => $lazyAttr !== null,
            'lazy_attr'   => $lazyAttr,
            'placeholder' => $lazyAttr !== null ? trim((string) $img->getAttribute('src')) : '',
        ];
    }

    return $out;
}

/**
 * Собирает внутренние ссылки из поддерева.
 *
 * @return list<array<string, string>>
 */
function collectLinks(DOMElement $scope, string $base): array
{
    $out  = [];
    $seen = [];

    foreach ($scope->getElementsByTagName('a') as $a) {
        $href = trim((string) $a->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#')) {
            continue;
        }
        if (preg_match('~^(mailto|tel|javascript|callto|skype|whatsapp|viber|sms|data|ftp):~i', $href)) {
            continue;
        }

        $abs = absolutize($href, $base);
        if (!isInternal($abs)) {
            continue;
        }
        if (isset($seen[$abs])) {
            continue;
        }
        $seen[$abs] = true;

        $out[] = [
            'url'  => $abs,
            'text' => oneLine((string) $a->textContent),
        ];
    }

    return $out;
}

/** Значение <meta name="..."> или <meta property="...">. */
function metaValue(DOMXPath $xp, string $key): string
{
    $q = sprintf(
        '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%1$s"'
        . ' or translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%1$s"]',
        $key
    );
    $n = $xp->query($q)->item(0);

    return $n instanceof DOMElement ? oneLine((string) $n->getAttribute('content')) : '';
}

// ---------------------------------------------------------------- основной цикл

if (!is_file(IN_FILE)) {
    fwrite(STDERR, 'Не найден ' . IN_FILE . "\n");
    exit(1);
}

$urls = array_values(array_filter(array_map('trim', (array) file(IN_FILE))));
if ($urls === []) {
    fwrite(STDERR, "urls.txt пуст\n");
    exit(1);
}

$pages  = [];
$errors = [];
$total  = count($urls);
$i      = 0;

foreach ($urls as $url) {
    $i++;

    [$html, $status] = fetchPage($url);
    if ($html === null || $status !== 200) {
        $errors[] = ['url' => $url, 'status' => $status];
        fwrite(STDERR, sprintf("[%d/%d] ОШИБКА %d  %s\n", $i, $total, $status, $url));
        usleep(DELAY_MS * 1000);
        continue;
    }

    // CR из исходника DOM сериализует обратно как &#13; — убираем заранее.
    $html = (string) preg_replace('~\r\n?~', "\n", $html);

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);

    // --- head
    $titleNode = $xp->query('//title')->item(0);
    $canonNode = $xp->query('//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]')->item(0);

    // --- контейнеры шаблона
    $contentEl = byClass($xp, CONTENT_CLASS);
    $titleEl   = byClass($xp, TITLE_CLASS);
    $crumbEl   = byClass($xp, BREADCRUMB_CLASS);

    // --- H1: сначала из блока заголовка, иначе первый h1 внутри контента
    $h1 = $titleEl !== null ? oneLine(readableText(stripNoise($titleEl))) : '';
    if ($h1 === '' && $contentEl !== null) {
        $n = $contentEl->getElementsByTagName('h1')->item(0);
        if ($n !== null) {
            $h1 = oneLine((string) $n->textContent);
        }
    }
    if ($h1 === '') {
        $n = $xp->query('//h1')->item(0);
        $h1 = $n !== null ? oneLine((string) $n->textContent) : '';
    }

    // --- контент
    $text        = '';
    $contentHtml = '';
    $images      = [];
    $links       = [];
    $headings    = [];

    if ($contentEl !== null) {
        $clean = cleanContent($contentEl);

        $text        = readableText($clean);
        $contentHtml = innerHtml($clean);
        $images      = collectImages($clean, $url);
        $links       = collectLinks($clean, $url);

        $cxp = new DOMXPath($clean->ownerDocument);
        foreach ($cxp->query('//h2 | //h3 | //h4') as $h) {
            if (!$h instanceof DOMElement) {
                continue;
            }
            $t = oneLine((string) $h->textContent);
            if ($t !== '') {
                $headings[] = ['level' => (int) substr($h->tagName, 1), 'text' => $t];
            }
        }
    }

    // --- хлебные крошки
    $breadcrumbs = [];
    if ($crumbEl !== null) {
        foreach ($crumbEl->getElementsByTagName('a') as $a) {
            $t = oneLine((string) $a->textContent);
            if ($t === '') {
                continue;
            }
            $breadcrumbs[] = ['text' => $t, 'url' => absolutize((string) $a->getAttribute('href'), $url)];
        }

        // Текущая страница в крошках обычно не ссылка — берём последний
        // непустой текстовый узел вне <a>, отбрасывая разделители.
        $tail = '';
        foreach ($xp->query('.//text()[not(ancestor::a)]', $crumbEl) as $t) {
            $v = oneLine((string) $t->nodeValue);
            $v = trim($v, "/>»→|·-– \u{00A0}");
            if ($v !== '') {
                $tail = $v;
            }
        }
        if ($tail !== '') {
            $breadcrumbs[] = ['text' => $tail, 'url' => null];
        }
    }

    // --- вся страница (для полноты охвата)
    $bodyEl     = $xp->query('//body')->item(0);
    $imagesAll  = $bodyEl instanceof DOMElement ? collectImages($bodyEl, $url) : [];
    $linksAll   = $bodyEl instanceof DOMElement ? collectLinks($bodyEl, $url) : [];

    $pages[] = [
        'url'              => $url,
        'http_status'      => $status,
        'title'            => $titleNode !== null ? oneLine((string) $titleNode->textContent) : '',
        'meta_description' => metaValue($xp, 'description'),
        'meta_keywords'    => metaValue($xp, 'keywords'),
        'canonical'        => $canonNode instanceof DOMElement ? absolutize((string) $canonNode->getAttribute('href'), $url) : '',
        'og'               => [
            'title'       => metaValue($xp, 'og:title'),
            'description' => metaValue($xp, 'og:description'),
            'image'       => metaValue($xp, 'og:image'),
            'type'        => metaValue($xp, 'og:type'),
            'url'         => metaValue($xp, 'og:url'),
        ],
        'h1'               => $h1,
        'breadcrumbs'      => $breadcrumbs,
        'headings'         => $headings,
        'content_found'    => $contentEl !== null,
        'text_length'      => mb_strlen($text),
        'text'             => $text,
        'content_html'     => $contentHtml,
        'images'           => $images,
        'links'            => $links,
        'images_all'       => $imagesAll,
        'links_all'        => $linksAll,
    ];

    fwrite(STDERR, sprintf(
        "[%d/%d] OK  текст:%-5d img:%-2d ссылок:%-2d  %s\n",
        $i,
        $total,
        mb_strlen($text),
        count($images),
        count($links),
        $url
    ));

    usleep(DELAY_MS * 1000);
}

$result = [
    'source'          => 'https://ra-zil.ru/',
    'content_selector' => 'div.' . CONTENT_CLASS,
    'pages_total'     => count($pages),
    'errors'          => $errors,
    'pages'           => $pages,
];

$json = json_encode(
    $result,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
);

file_put_contents(OUT_FILE, $json);

echo PHP_EOL . str_repeat('=', 64) . PHP_EOL;
echo 'Обработано страниц:  ' . count($pages) . ' из ' . $total . PHP_EOL;
echo 'Ошибок:              ' . count($errors) . PHP_EOL;
echo 'Контейнер не найден: ' . count(array_filter($pages, static fn(array $p): bool => !$p['content_found'])) . PHP_EOL;
echo 'Суммарно текста:     ' . array_sum(array_column($pages, 'text_length')) . ' симв.' . PHP_EOL;
echo 'Файл:                ' . OUT_FILE . ' (' . number_format((float) filesize(OUT_FILE)) . ' байт)' . PHP_EOL;
echo str_repeat('=', 64) . PHP_EOL;
