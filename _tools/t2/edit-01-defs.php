<?php
/**
 * Общие определения трёх замен Этапа 2. Только данные, ничего не пишет.
 */
const RZ_FRONT = '/themes/razil/templates/front-page.html';

function rz_front_path(): string {
    return WP_CONTENT_DIR . RZ_FRONT;
}

function rz_edits(): array {
    return [
        '2.1' => [
            'old' => 'Все расчёты проходят через кассу с выдачей чека',
            'new' => 'Все расчёты проходят с использованием контрольно-кассовой техники в соответствии с законодательством РФ',
        ],
        '2.2' => [
            'old' => 'от оформления документов до благоустройства',
            'new' => 'от оформления документов для захоронения до благоустройства',
        ],
        '2.3' => [
            'old' => '<h2>Что делать, если уход наступил дома</h2>',
            'new' => '<h2>Что делать, если уход из жизни близкого Вам человека наступил дома</h2>',
        ],
    ];
}

/** Контекст ±$pad символов вокруг подстроки, по символам, а не по байтам. */
function rz_ctx(string $hay, string $needle, int $pad = 80): string {
    $bytePos = strpos($hay, $needle);
    if ($bytePos === false) { return '(подстрока не найдена)'; }
    $charPos = mb_strlen(substr($hay, 0, $bytePos), 'UTF-8');
    $start   = max(0, $charPos - $pad);
    $len     = ($charPos - $start) + mb_strlen($needle, 'UTF-8') + $pad;
    return mb_substr($hay, $start, $len, 'UTF-8');
}
