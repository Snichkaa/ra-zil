# -*- coding: utf-8 -*-
"""Бинарная проверка текстовых файлов темы и плагина.

Проверяется три вещи: нет отметки порядка байтов в начале файла,
все переводы строк — CRLF, нет управляющих символов кроме табуляции
и самого перевода строки.

Файл открывается строго в двоичном режиме. В текстовом режиме Python
сам переводит CRLF в LF при чтении, и проверка переводов строк
превратилась бы в проверку того, что Python умеет их переводить.

Запуск:
    python _tools/redesign/verify-files.py <файл|каталог> ...
    python _tools/redesign/verify-files.py --git   # изменённые в git

Код возврата 1, если хоть один файл не прошёл.
"""

import os
import subprocess
import sys

# Расширения, для которых проверка осмысленна. Двоичные файлы —
# шрифты, изображения, архивы — сюда не попадают: в них и BOM-подобная
# последовательность, и одиночный перевод строки законны.
TEXT_EXT = {
    ".php", ".css", ".js", ".json", ".html", ".htm", ".md",
    ".txt", ".svg", ".xml", ".py", ".ps1", ".scss",
}

# Каталоги, которые не проверяются никогда: чужой код и сборка.
SKIP_DIRS = {"node_modules", ".git", "vendor", "_backup"}

BOMS = (
    (b"\xef\xbb\xbf", "UTF-8"),
    (b"\xff\xfe", "UTF-16LE"),
    (b"\xfe\xff", "UTF-16BE"),
)


def check(path):
    """Список замечаний по одному файлу. Пустой список — файл прошёл."""
    problems = []

    with open(path, "rb") as fh:
        data = fh.read()

    if not data:
        return problems

    for bom, name in BOMS:
        if data.startswith(bom):
            problems.append("отметка порядка байтов %s в начале файла" % name)
            break

    # Одиночные LF: перевод строки, перед которым нет возврата каретки.
    lone_lf = []
    # Одиночные CR: возврат каретки, за которым не идёт перевод строки.
    lone_cr = []
    line = 1
    for i, byte in enumerate(data):
        if byte == 0x0A:
            if i == 0 or data[i - 1] != 0x0D:
                lone_lf.append(line)
            line += 1
        elif byte == 0x0D:
            if i + 1 >= len(data) or data[i + 1] != 0x0A:
                lone_cr.append(line)

    if lone_lf:
        problems.append(
            "одиночный перевод строки без возврата каретки: строк %d, первая %d"
            % (len(lone_lf), lone_lf[0])
        )
    if lone_cr:
        problems.append(
            "возврат каретки без перевода строки: раз %d, первый около строки %d"
            % (len(lone_cr), lone_cr[0])
        )

    # Управляющие символы. Табуляция, возврат каретки и перевод строки
    # разрешены, остальное в исходниках означает случайную вставку.
    bad = {}
    for byte in data:
        if byte < 0x20 and byte not in (0x09, 0x0A, 0x0D):
            bad[byte] = bad.get(byte, 0) + 1
    if bad:
        listed = ", ".join(
            "0x%02X x%d" % (code, count) for code, count in sorted(bad.items())
        )
        problems.append("управляющие символы: %s" % listed)

    return problems


def collect(targets):
    """Пути к файлам, которые надо проверить."""
    files = []
    for target in targets:
        if os.path.isdir(target):
            for root, dirs, names in os.walk(target):
                dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
                for name in names:
                    if os.path.splitext(name)[1].lower() in TEXT_EXT:
                        files.append(os.path.join(root, name))
        elif os.path.isfile(target):
            files.append(target)
        else:
            print("НЕТ ФАЙЛА %s" % target)
    return sorted(set(files))


def from_git():
    """Изменённые и новые файлы по данным git."""
    root = subprocess.run(
        ["git", "rev-parse", "--show-toplevel"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()

    out = subprocess.run(
        ["git", "status", "--porcelain", "--untracked-files=all"],
        capture_output=True, text=True, check=True, cwd=root,
    ).stdout

    files = []
    for entry in out.splitlines():
        if not entry.strip():
            continue
        name = entry[3:].strip().strip('"')
        # Переименование: git печатает "было -> стало".
        if " -> " in name:
            name = name.split(" -> ", 1)[1]
        path = os.path.join(root, name)
        if os.path.isfile(path) and os.path.splitext(path)[1].lower() in TEXT_EXT:
            files.append(path)
    return sorted(set(files))


def main(argv):
    if not argv:
        print(__doc__)
        return 2

    files = from_git() if argv[0] == "--git" else collect(argv)

    if not files:
        print("--- проверять нечего")
        return 0

    failed = 0
    for path in files:
        problems = check(path)
        shown = os.path.relpath(path).replace("\\", "/")
        if problems:
            failed += 1
            print("УПАЛ    %s" % shown)
            for problem in problems:
                print("        %s" % problem)
        else:
            print("ПРОШЁЛ  %s" % shown)

    print("\n--- ИТОГО: проверено %d, упало %d" % (len(files), failed))
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
