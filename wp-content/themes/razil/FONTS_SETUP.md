# Настройка локальных шрифтов

Тема использует два открытых шрифта с русской поддержкой, загружаемых локально:

## PT Serif (для заголовков)
- **Официальный сайт**: https://fonts.google.com/specimen/PT+Serif
- **Начертания, необходимые**:
  - Regular (400)
  - Bold (700)

**Файлы для скачивания** (в формате .woff2):
1. `pt-serif-400.woff2` → `assets/fonts/pt-serif-400.woff2`
2. `pt-serif-700.woff2` → `assets/fonts/pt-serif-700.woff2`

## Golos Text (для основного текста)
- **Официальный сайт**: https://github.com/OdessaTextile/Golos-Text (или Google Fonts)
- **Начертания, необходимые**:
  - Regular (400)
  - Semibold (600)

**Файлы для скачивания** (в формате .woff2):
1. `golos-text-400.woff2` → `assets/fonts/golos-text-400.woff2`
2. `golos-text-600.woff2` → `assets/fonts/golos-text-600.woff2`

## Инструкции

1. Скачайте файлы шрифтов в формате .woff2 с Google Fonts или официальных сайтов
2. Убедитесь, что каждый файл содержит подмножество символов только Cyrillic + Latin (для оптимизации веса)
3. Поместите файлы в папку `wp-content/themes/razil/assets/fonts/`
4. Файлы автоматически подхватятся при загрузке `assets/css/fonts.css`

## Проверка

После добавления файлов шрифтов:
- Откройте сайт и проверьте, что шрифты загружаются локально
- В DevTools проверьте Network tab, убедитесь что нет запросов на внешние CDN
- Проверьте контраст текста в Lighthouse

## Альтернатива (временная)

Если шрифты еще не добавлены, тема будет использовать системные шрифты:
- PT Serif → serif (Times New Roman на Windows)
- Golos Text → sans-serif (Arial на Windows)

Это обеспечит функциональность, но без официального брендирования.
