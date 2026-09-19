# -*- coding: utf-8 -*-
"""Разбор CSS на блоки верхнего уровня и пересборка в заданном порядке.

Перестановка правил в CSS — операция опасная: при равной специфичности
исход решает порядок строк, и в main.css таких мест несколько, они прямо
отмечены комментариями. Поэтому перестановка делается не руками, а этим
скриптом, и проверяется снимком вычисленных стилей.

Скрипт ничего не переписывает: он режет файл на куски и склеивает их
обратно. Текст правил не меняется ни на байт — при склейке это
проверяется сравнением множества кусков и суммы длин.

Режимы:
    list <файл.css>                   печатает опись кусков
    emit <файл.css> <порядок.txt>     печатает файл в новом порядке

Файл порядка: по одному номеру куска в строке; строки, начинающиеся
с #, — заголовки разделов, они вставляются в вывод как комментарии.
"""

import sys


def split_chunks(data):
    """Режет текст CSS на куски верхнего уровня.

    Кусок — это одна конструкция верхнего уровня (правило, @media,
    @font-face) вместе с прилипшими к ней сверху комментариями
    и пустыми строками. Так комментарий, объясняющий правило,
    переезжает вместе с ним.

    Глубина скобок считается с пропуском комментариев и строк:
    фигурная скобка внутри content: "{" не должна открывать блок.
    """
    lines = data.split('\r\n')
    chunks = []
    cur = []
    depth = 0
    in_comment = False
    seen_rule = False   # в текущем куске уже есть конструкция, не только комментарии

    for line in lines:
        cur.append(line)

        i = 0
        in_str = None
        while i < len(line):
            two = line[i:i + 2]
            if in_comment:
                if two == '*/':
                    in_comment = False
                    i += 2
                    continue
                i += 1
                continue
            if in_str:
                if line[i] == '\\':
                    i += 2
                    continue
                if line[i] == in_str:
                    in_str = None
                i += 1
                continue
            if two == '/*':
                in_comment = True
                i += 2
                continue
            if line[i] in '"\'':
                in_str = line[i]
                i += 1
                continue
            if line[i] == '{':
                depth += 1
                seen_rule = True
            elif line[i] == '}':
                depth -= 1
            i += 1

        # Кусок закрывается на пустой строке вне блока и вне комментария,
        # если в нём уже была хотя бы одна конструкция.
        if depth == 0 and not in_comment and seen_rule and line.strip() == '':
            chunks.append('\r\n'.join(cur))
            cur = []
            seen_rule = False

    if cur:
        chunks.append('\r\n'.join(cur))

    return chunks


def headline(chunk):
    """Первая содержательная строка куска — для описи."""
    for line in chunk.split('\r\n'):
        s = line.strip()
        if s and not s.startswith('/*') and not s.startswith('*') and not s.startswith('*/'):
            return s[:96]
    for line in chunk.split('\r\n'):
        s = line.strip()
        if s:
            return s[:96]
    return '(пусто)'


def main(argv):
    if len(argv) < 2:
        print(__doc__)
        return 2

    mode, path = argv[0], argv[1]

    with open(path, 'rb') as fh:
        data = fh.read().decode('utf-8')

    chunks = split_chunks(data)

    # Склейка обязана давать исходный файл байт в байт. Если нет —
    # резать дальше нельзя, и порядок пересобирать тоже.
    if '\r\n'.join(chunks) != data:
        print('РАЗБОР НЕ СОШЁЛСЯ: склейка кусков не равна исходному файлу')
        return 1

    if mode == 'list':
        print('# Кусков: %d, строк: %d' % (len(chunks), data.count('\r\n') + 1))
        for i, c in enumerate(chunks):
            nlines = c.count('\r\n') + 1
            print('%4d  %4d стр  %s' % (i, nlines, headline(c)))
        return 0

    if mode == 'emit':
        if len(argv) < 3:
            print('Нужен файл порядка')
            return 2

        with open(argv[2], 'rb') as fh:
            order_lines = fh.read().decode('utf-8').replace('\r\n', '\n').split('\n')

        out = []
        used = []
        for line in order_lines:
            s = line.strip()
            if not s:
                continue
            if s.startswith('#'):
                title = s.lstrip('#').strip()
                out.append('/* ' + title + ' */\r\n')
                continue
            idx = int(s)
            out.append(chunks[idx])
            used.append(idx)

        if sorted(used) != list(range(len(chunks))):
            missing = sorted(set(range(len(chunks))) - set(used))
            dupes = sorted({i for i in used if used.count(i) > 1})
            print('ПОРЯДОК НЕПОЛНЫЙ: пропущено %s, повторено %s' % (missing, dupes))
            return 1

        result = '\r\n'.join(out)

        # Ни один кусок не потерян и не изменён: сравниваем множества.
        if sorted(chunks) != sorted([c for c in out if not (c.startswith('/* ') and c.endswith(' */\r\n'))]):
            print('СКЛЕЙКА ИСКАЗИЛА КУСКИ')
            return 1

        sys.stdout.buffer.write(result.encode('utf-8'))
        return 0

    print('Неизвестный режим %s' % mode)
    return 2


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
