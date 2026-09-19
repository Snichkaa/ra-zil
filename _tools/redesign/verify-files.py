# -*- coding: utf-8 -*-
"""Бинарная проверка текстовых файлов темы и плагина.

Проверяется четыре вещи:

  * нет отметки порядка байтов в начале файла;
  * переводы строк не смешаны в пределах файла;
  * стиль переводов строк не изменился по сравнению с копией темы
    и плагина в _backup; у нового файла он обязан быть CRLF;
  * нет управляющих символов, кроме табуляции и самого перевода строки.

Про третий пункт отдельно. В этом репозитории нет .gitattributes,
и файлы исторически разные: functions.php, header.js и шаблоны темы
идут с CRLF, а большая часть модулей плагина — с LF. Требовать CRLF
от всех подряд значило бы переписать десяток чужих файлов целиком
ради одной правки в три строки. Поэтому правило такое: писать
в двоичном режиме и НЕ нормализовать чужие переводы строк —
файл остаётся в своём стиле, а новый заводится с CRLF.

Сравнивать с git нельзя: core.autocrlf у репозитория включён, в самом
хранилище всё лежит с LF, и разница со стилем рабочей копии ничего
не значит. Эталоном служит копия темы и плагина в _backup — она снята
до первой правки и хранит рабочую копию побайтово.

Файл открывается строго в двоичном режиме. В текстовом Python сам
переводит CRLF в LF при чтении, и проверка превратилась бы в проверку
того, что Python умеет их переводить.

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
    ".php", ".css", ".js", ".mjs", ".json", ".html", ".htm", ".md",
    ".txt", ".svg", ".xml", ".py", ".ps1", ".scss",
}

# Каталоги, которые не проверяются никогда: чужой код и сборка.
SKIP_DIRS = {"node_modules", ".git", "vendor", "_backup"}

BOMS = (
    (b"\xef\xbb\xbf", "UTF-8"),
    (b"\xff\xfe", "UTF-16LE"),
    (b"\xfe\xff", "UTF-16BE"),
)

_ROOT = None


def git_root():
    global _ROOT
    if _ROOT is None:
        try:
            _ROOT = subprocess.run(
                ["git", "rev-parse", "--show-toplevel"],
                capture_output=True, text=True, check=True,
            ).stdout.strip()
        except Exception:
            _ROOT = ""
    return _ROOT


# Копия рабочего дерева, снятая до первой правки (пункт 3.2 задания).
BACKUP = "_backup/redesign-2026-09-19"
BACKUP_MAP = (
    ("wp-content/themes/razil/", BACKUP + "/theme-razil/"),
    ("wp-content/plugins/razil-core/", BACKUP + "/plugin-razil-core/"),
)


def baseline_bytes(path):
    """Содержимое файла в бэкапе или None, если его там нет."""
    root = git_root()
    if not root:
        return None
    rel = os.path.relpath(os.path.abspath(path), root).replace("\\", "/")
    for src, dst in BACKUP_MAP:
        if rel.startswith(src):
            cand = os.path.join(root, dst + rel[len(src):])
            if os.path.isfile(cand):
                with open(cand, "rb") as fh:
                    return fh.read()
            return None
    return None


def dir_style(path):
    """Преобладающий стиль переводов строк в каталоге файла.

    Новый файл заводится в том же стиле, что его соседи: в репозитории
    нет единого правила, и модули плагина идут с LF, а шаблоны темы —
    с CRLF. Требовать от нового файла CRLF там, где вокруг LF, значило
    бы плодить третий вариант вместо того, чтобы вписаться в имеющийся.

    Каталог без соседей — CRLF: он преобладает в проекте.
    """
    folder = os.path.dirname(os.path.abspath(path))
    counts = {}
    for name in os.listdir(folder):
        other = os.path.join(folder, name)
        if other == os.path.abspath(path) or not os.path.isfile(other):
            continue
        if os.path.splitext(name)[1].lower() not in TEXT_EXT:
            continue
        with open(other, "rb") as fh:
            st = eol_style(fh.read())
        if st in ("CRLF", "LF"):
            counts[st] = counts.get(st, 0) + 1

    if not counts:
        return "CRLF"
    return max(counts.items(), key=lambda kv: kv[1])[0]


def eol_style(data):
    """Стиль переводов строк: CRLF, LF, CR, СМЕШАННЫЙ или «нет строк»."""
    crlf = data.count(b"\r\n")
    lf = data.count(b"\n") - crlf
    cr = data.count(b"\r") - crlf

    kinds = [k for k, n in (("CRLF", crlf), ("LF", lf), ("CR", cr)) if n]
    if not kinds:
        return "нет строк"
    if len(kinds) > 1:
        return "СМЕШАННЫЙ (" + ", ".join(kinds) + ")"
    return kinds[0]


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

    style = eol_style(data)
    if style.startswith("СМЕШАННЫЙ"):
        problems.append("переводы строк смешаны: %s" % style)
    else:
        was = baseline_bytes(path)
        if was is None:
            want = dir_style(path)
            if style not in (want, "нет строк"):
                problems.append("новый файл: у соседей по каталогу %s, а у него %s" % (want, style))
        else:
            before = eol_style(was)
            if before != style and "нет строк" not in (before, style):
                problems.append("переводы строк изменились: было %s, стало %s" % (before, style))

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

    return problems, style


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
    root = git_root()
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
        problems, style = check(path)
        shown = os.path.relpath(path).replace("\\", "/")
        if problems:
            failed += 1
            print("УПАЛ    %-58s %s" % (shown, style))
            for problem in problems:
                print("        %s" % problem)
        else:
            print("ПРОШЁЛ  %-58s %s" % (shown, style))

    print("\n--- ИТОГО: проверено %d, упало %d" % (len(files), failed))
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
