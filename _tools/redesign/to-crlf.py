# -*- coding: utf-8 -*-
"""Приведение файла к CRLF без отметки порядка байтов.

Редактор и часть инструментов пишут файлы с одиночным переводом строки,
а в репозитории все текстовые файлы хранятся с CRLF. Скрипт снимает
отметку порядка байтов, если она есть, и переводит все переводы строк
в CRLF, не трогая остальное содержимое.

Чтение и запись строго двоичные: в текстовом режиме Python сам
подменяет переводы строк, и скрипт молча ничего бы не исправил.

Запуск: python _tools/redesign/to-crlf.py <файл> ...
"""

import os
import sys


def fix(path):
    """Возвращает True, если файл пришлось переписать."""
    with open(path, "rb") as fh:
        data = fh.read()

    before = data

    if data.startswith(b"\xef\xbb\xbf"):
        data = data[3:]

    # Сначала все CRLF в LF, потом одиночные CR в LF, и только затем
    # общий перевод в CRLF. Иначе у файла со смешанными концами строк
    # появились бы последовательности CRCRLF.
    data = data.replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    data = data.replace(b"\n", b"\r\n")

    if data == before:
        return False

    with open(path, "wb") as fh:
        fh.write(data)
    return True


def main(argv):
    if not argv:
        print(__doc__)
        return 2

    for path in argv:
        if not os.path.isfile(path):
            print("НЕТ ФАЙЛА %s" % path)
            return 1
        changed = fix(path)
        shown = os.path.relpath(path).replace("\\", "/")
        print("%s %s" % ("ИСПРАВЛЕН" if changed else "БЕЗ ИЗМЕНЕНИЙ", shown))

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
