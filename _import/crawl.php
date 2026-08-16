<?php
/**
 * Краулер для сбора списка внутренних URL сайта ra-zil.ru.
 * Только сбор ссылок — контент не парсится и не сохраняется.
 */

declare(strict_types=1);

const START_URL   = 'https://ra-zil.ru/';
const MAX_DEPTH   = 4;
const DELAY_MS    = 300;
const OUT_FILE    = __DIR__ . DIRECTORY_SEPARATOR . 'urls.txt';
const USER_AGENT  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

// GET-параметры сортировки/фильтрации/трекинга — URL с ними отбрасываем целиком.
const JUNK_PARAMS = [
    'sort', 'order', 'orderby', 'filter', 'price', 'view', 'display', 'limit',
    'per_page', 'perpage', 'page_size', 'paged', 'offset', 'min_price', 'max_price',
    'brand', 'color', 'size', 'attribute', 'rating', 'search', 's', 'q',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'yclid', 'gclid', 'fbclid', 'from', 'ref', 'replytocom', 'add-to-cart',
];

// Расширения файлов — не HTML, обходить бессмысленно.
const SKIP_EXT = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf',
    'zip', 'rar', '7z', 'tar', 'gz', 'exe', 'dmg',
    'mp3', 'mp4', 'avi', 'mov', 'wmv', 'webm', 'mkv', 'wav',
    'css', 'js', 'json', 'xml', 'rss', 'txt',
];

$host = parse_url(START_URL, PHP_URL_HOST);

/**
 * Загружает HTML по URL. Возвращает null, если не HTML или ошибка.
 */
function fetch(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: " . USER_AGENT . "\r\n"
                               . "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n"
                               . "Accept-Language: ru-RU,ru;q=0.9\r\n",
            'timeout'         => 20,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }

    $status      = 0;
    $contentType = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($h, 'Content-Type:') === 0) {
            $contentType = strtolower(trim(substr($h, 13)));
        }
    }

    if ($status !== 200) {
        return null;
    }
    if ($contentType !== '' && strpos($contentType, 'text/html') === false) {
        return null;
    }

    return $body;
}

/**
 * Приводит относительный href к абсолютному URL относительно базового.
 */
function resolveUrl(string $href, string $base): ?string
{
    $href = trim($href);
    if ($href === '') {
        return null;
    }

    // Протокол-относительные: //example.com/path
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
    $bhost  = $bp['host'] ?? '';
    $bpath  = $bp['path'] ?? '/';

    if (str_starts_with($href, '/')) {
        $path = $href;
    } else {
        $dir  = rtrim(substr($bpath, 0, (int) strrpos($bpath, '/') + 1), '/');
        $path = $dir . '/' . $href;
    }

    // Схлопываем ./ и ../
    $parts = [];
    $qpos  = strcspn($path, '?#');
    $tail  = substr($path, $qpos);
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
    $clean = '/' . implode('/', $parts);
    if (str_ends_with($path, '/') && !str_ends_with($clean, '/')) {
        $clean .= '/';
    }

    return $scheme . '://' . $bhost . $clean . $tail;
}

/**
 * Нормализует URL: убирает якорь, мусорные параметры, приводит к единому виду.
 * Возвращает null, если URL нужно отбросить.
 */
function normalize(?string $url, string $host): ?string
{
    if ($url === null) {
        return null;
    }

    $p = parse_url($url);
    if ($p === false || !isset($p['scheme'], $p['host'])) {
        return null;
    }

    // Только http/https
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return null;
    }

    // Только тот же домен (с www и без)
    $h = strtolower($p['host']);
    if ($h !== $host && $h !== 'www.' . $host && 'www.' . $h !== $host) {
        return null;
    }

    $path = $p['path'] ?? '/';
    if ($path === '') {
        $path = '/';
    }

    // Отбрасываем файлы по расширению
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, SKIP_EXT, true)) {
        return null;
    }

    // Служебные пути WordPress
    if (preg_match('~^/(wp-admin|wp-json|wp-content|wp-includes|xmlrpc\.php|feed)(/|$)~i', $path)) {
        return null;
    }
    if (preg_match('~/feed/?$~i', $path)) {
        return null;
    }

    // Разбираем query: URL с мусорными параметрами отбрасываем целиком
    $query = '';
    if (isset($p['query']) && $p['query'] !== '') {
        parse_str($p['query'], $params);
        foreach (array_keys($params) as $key) {
            $k = strtolower((string) $key);
            if (in_array($k, JUNK_PARAMS, true) || str_starts_with($k, 'utm_') || str_starts_with($k, 'filter')) {
                return null;
            }
        }
        if ($params !== []) {
            ksort($params);
            $query = '?' . http_build_query($params);
        }
    }

    // Единый хост и завершающий слэш для путей без расширения
    if ($ext === '' && !str_ends_with($path, '/')) {
        $path .= '/';
    }

    return strtolower($p['scheme']) . '://' . $host . $path . $query;
}

/**
 * Извлекает href из HTML через DOMDocument.
 *
 * @return string[]
 */
function extractHrefs(string $html, string $baseUrl): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    // Уважаем <base href>, если он есть
    $base  = $baseUrl;
    $bases = $doc->getElementsByTagName('base');
    if ($bases->length > 0) {
        $bh = trim((string) $bases->item(0)->getAttribute('href'));
        if ($bh !== '') {
            $base = resolveUrl($bh, $baseUrl) ?? $baseUrl;
        }
    }

    $out = [];
    foreach ($doc->getElementsByTagName('a') as $a) {
        $href = trim((string) $a->getAttribute('href'));
        if ($href === '') {
            continue;
        }

        // Якоря, mailto:, tel:, javascript: и прочие схемы-не-http
        if (str_starts_with($href, '#')) {
            continue;
        }
        if (preg_match('~^(mailto|tel|javascript|callto|skype|whatsapp|viber|sms|data|ftp):~i', $href)) {
            continue;
        }

        // Отрезаем фрагмент
        $hashPos = strpos($href, '#');
        if ($hashPos !== false) {
            $href = substr($href, 0, $hashPos);
        }
        if (trim($href) === '') {
            continue;
        }

        $out[] = resolveUrl($href, $base);
    }

    return array_filter($out);
}

// --- Обход в ширину с ограничением глубины ---

$start = normalize(START_URL, $host);
if ($start === null) {
    fwrite(STDERR, "Не удалось нормализовать стартовый URL\n");
    exit(1);
}

$seen    = [$start => true];   // все известные URL (в т.ч. не обойдённые)
$found   = [$start => true];   // итоговый список
$queue   = [[$start, 0]];
$visited = 0;
$failed  = 0;

while ($queue !== []) {
    [$url, $depth] = array_shift($queue);

    $html = fetch($url);
    $visited++;

    if ($html === null) {
        $failed++;
        fwrite(STDERR, sprintf("[%d] FAIL d=%d %s\n", $visited, $depth, $url));
        usleep(DELAY_MS * 1000);
        continue;
    }

    $newCount = 0;
    if ($depth < MAX_DEPTH) {
        foreach (extractHrefs($html, $url) as $raw) {
            $norm = normalize($raw, $host);
            if ($norm === null || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm]  = true;
            $found[$norm] = true;
            $queue[]      = [$norm, $depth + 1];
            $newCount++;
        }
    }

    fwrite(STDERR, sprintf(
        "[%d/%d] d=%d +%d %s\n",
        $visited,
        $visited + count($queue),
        $depth,
        $newCount,
        $url
    ));

    usleep(DELAY_MS * 1000);
}

$urls = array_keys($found);
sort($urls, SORT_STRING);

file_put_contents(OUT_FILE, implode(PHP_EOL, $urls) . PHP_EOL);

echo PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
echo 'Запрошено страниц: ' . $visited . PHP_EOL;
echo 'Ошибок/не-HTML:    ' . $failed . PHP_EOL;
echo 'Всего URL найдено: ' . count($urls) . PHP_EOL;
echo 'Файл:              ' . OUT_FILE . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
