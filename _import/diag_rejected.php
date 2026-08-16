<?php
/**
 * Диагностика: собирает ВСЕ href с уже найденных страниц и показывает те,
 * что не попали в urls.txt. Помогает убедиться, что фильтры ничего не срезали.
 * Ничего не импортирует, urls.txt не меняет.
 */

declare(strict_types=1);

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
$host = 'ra-zil.ru';

function grab(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'header'          => "User-Agent: " . UA . "\r\n",
            'timeout'         => 20,
            'follow_location' => 1,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

$urls = array_values(array_filter(array_map('trim', file(__DIR__ . '/urls.txt'))));

// Набор путей, которые уже есть в списке (для сравнения без учёта хоста/слэша).
$known = [];
foreach ($urls as $u) {
    $known[strtolower(rtrim(parse_url($u, PHP_URL_PATH) ?: '/', '/')) ?: '/'] = true;
}

$internal = [];
$external = [];

foreach ($urls as $url) {
    $html = grab($url);
    if ($html === null) {
        echo "FAIL $url\n";
        continue;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    foreach ($doc->getElementsByTagName('a') as $a) {
        $href = trim((string) $a->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#')) {
            continue;
        }
        if (preg_match('~^(mailto|tel|javascript|callto|skype|whatsapp|viber|sms|data):~i', $href)) {
            continue;
        }

        $h = parse_url($href, PHP_URL_HOST);
        if ($h !== null && strtolower($h) !== $host && strtolower($h) !== 'www.' . $host) {
            $external[strtolower($h)] = ($external[strtolower($h)] ?? 0) + 1;
            continue;
        }

        $path = parse_url($href, PHP_URL_PATH) ?: '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, './');
        }
        $key = strtolower(rtrim($path, '/')) ?: '/';
        if (isset($known[$key])) {
            continue;
        }
        $internal[$href] = ($internal[$href] ?? 0) + 1;
    }

    usleep(200000);
}

echo "\n=== ВНУТРЕННИЕ ССЫЛКИ ВНЕ СПИСКА (" . count($internal) . " уникальных) ===\n";
ksort($internal);
foreach ($internal as $u => $n) {
    printf("%3dx  %s\n", $n, $u);
}

echo "\n=== ВНЕШНИЕ ДОМЕНЫ (" . count($external) . ") ===\n";
arsort($external);
foreach ($external as $d => $n) {
    printf("%3dx  %s\n", $n, $d);
}
