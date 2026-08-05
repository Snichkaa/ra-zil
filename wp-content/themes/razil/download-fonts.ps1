# Скрипт для загрузки локальных шрифтов для темы razil
# Этот скрипт скачивает PT Serif и Golos Text в формате .woff2

$FontsDir = "assets\fonts"

Write-Host "Загрузка шрифтов для темы razil..."
Write-Host ""

# Проверяем наличие curl
if (-not (Get-Command curl -ErrorAction SilentlyContinue)) {
    Write-Host "⚠ curl не найден. Используйте wget или скачайте шрифты вручную с:"
    Write-Host "  - PT Serif: https://fonts.google.com/specimen/PT+Serif"
    Write-Host "  - Golos Text: https://fonts.google.com/specimen/Golos+Text"
    exit 1
}

# Массив со ссылками на шрифты из Google Fonts
# Эти ссылки получены из исходного кода страницы Google Fonts
$fonts = @(
    @{
        name = "pt-serif-400.woff2"
        url = "https://fonts.gstatic.com/s/ptserif/v19/jizBRFVJM25kh9aNZj-q6tZiOV8.woff2"
    },
    @{
        name = "pt-serif-700.woff2"
        url = "https://fonts.gstatic.com/s/ptserif/v19/jizARFVJM25kh9aNZj-q6tZaV1k_Wvs.woff2"
    },
    @{
        name = "golos-text-400.woff2"
        url = "https://fonts.gstatic.com/s/golostext/v2/-F_wfjtqLzI2JPCgCV8qPKb_.woff2"
    },
    @{
        name = "golos-text-600.woff2"
        url = "https://fonts.gstatic.com/s/golostext/v2/-F_wfjtqLzI2JPCgCV8qPKZQUZk.woff2"
    }
)

# Загружаем каждый шрифт
$success = 0
foreach ($font in $fonts) {
    Write-Host -NoNewline "Загрузка $($font.name)... "
    try {
        $filePath = Join-Path $FontsDir $font.name
        curl -s -o $filePath $font.url

        # Проверяем размер файла
        $size = (Get-Item $filePath).Length
        if ($size -gt 1000) {
            Write-Host "✓ OK ($([math]::Round($size/1KB, 1)) KB)"
            $success++
        } else {
            Write-Host "✗ Файл слишком мал, возможно ошибка"
            Remove-Item $filePath -Force
        }
    } catch {
        Write-Host "✗ Ошибка: $_"
    }
}

Write-Host ""
Write-Host "Успешно загружено: $success из $($fonts.Count) шрифтов"

if ($success -eq $fonts.Count) {
    Write-Host "✓ Все шрифты готовы!"
} else {
    Write-Host "⚠ Некоторые шрифты не были загружены. Проверьте интернет соединение."
    Write-Host ""
    Write-Host "Альтернатива: скачайте шрифты вручную:"
    Write-Host "1. Перейдите на https://fonts.google.com"
    Write-Host "2. Найдите PT Serif и Golos Text"
    Write-Host "3. Выберите нужные начертания (400, 600/700)"
    Write-Host "4. Скачайте в формате WOFF2"
    Write-Host "5. Поместите файлы в папку: wp-content/themes/razil/assets/fonts/"
}
