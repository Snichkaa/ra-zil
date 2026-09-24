# -*- coding: utf-8 -*-
"""Сравнение двух снимков текста сайта.

Отвечает на два вопроса задания: не пропало ли предложение и не
появилось ли новое. Перестановка допустима, поэтому сравниваются
множества, а не порядок.

Отдельно сверяются телефоны, ссылки tel: и денежные суммы: их
множества обязаны совпадать точно.

Запуск: python _tools/redesign/compare-text.py <до.txt> <после.txt>
         [--t1] [--skip <подстрока имени группы>]

  --t1    нормализация по правилу Т1 задания от 24 сентября: у пункта
          списка разрешено поменять регистр первой буквы и убрать
          конечные «;» и «.». Обе стороны приводятся к одному виду,
          иначе перенос пункта в новую разметку выглядел бы как
          пропажа старого предложения и появление нового.

  --skip  группа с такой подстрокой в имени выбрасывается из обоих
          снимков. Нужно для временной тестовой записи, на которой
          обкатываются паттерны: её рыба не содержание сайта.

Код возврата 1, если что-то пропало или появилось.
"""

import re
import sys


def norm_t1(s):
    """Пункт списка по правилу Т1: без конечных «;» «.», с малой буквы."""
    s = s.rstrip()
    while s and s[-1] in ';.':
        s = s[:-1].rstrip()
    if s:
        s = s[0].lower() + s[1:]
    return s


def load(path, skip=None):
    """Снимок -> (множество предложений, отображение предложение->страницы)."""
    text = open(path, 'rb').read().decode('utf-8')
    groups = {}
    current = None
    for line in text.replace('\r\n', '\n').split('\n'):
        if line.startswith('===== '):
            current = line[6:].strip()
            if skip and skip in current:
                current = None
                continue
            groups[current] = []
            continue
        if line.startswith('----- ') or not line.strip():
            continue
        if current is not None:
            groups[current].append(line.rstrip())
    return groups


def sentences(groups, t1=False):
    out = {}
    for page, lines in groups.items():
        for s in lines:
            out.setdefault(norm_t1(s) if t1 else s, set()).add(page)
    return out


# Телефон в любом виде, в каком он встречается в текстах сайта.
RE_TEL = re.compile(r'(?:\+7|8)[\s ]?\(?\d{3,4}\)?[\s -]?\d{2,3}[\s -]?\d{2}[\s -]?\d{2}|\b(?:112|103|102|101|03|02|01)\b')
# Денежная сумма: число с разделителями и знак рубля.
RE_MONEY = re.compile(r'\d[\d \s]*(?:[.,]\d+)?\s*(?:₽|руб\.?|рублей|рубля)')


def extract(groups, rx):
    found = {}
    for page, lines in groups.items():
        for s in lines:
            for m in rx.findall(s):
                key = re.sub(r'[\s ]+', ' ', m).strip()
                found.setdefault(key, set()).add(page)
    return found


def report(title, before, after):
    """Печатает расхождения множеств. Возвращает их число."""
    lost = sorted(set(before) - set(after))
    added = sorted(set(after) - set(before))

    print('--- %s: было %d, стало %d, пропало %d, появилось %d'
          % (title, len(before), len(after), len(lost), len(added)))

    for s in lost:
        print('    ПРОПАЛО:   %s' % s[:160])
        print('               (было на: %s)' % '; '.join(sorted(before[s])[:3]))
    for s in added:
        print('    ПОЯВИЛОСЬ: %s' % s[:160])
        print('               (стало на: %s)' % '; '.join(sorted(after[s])[:3]))

    return len(lost) + len(added)


def main(argv):
    if len(argv) < 2:
        print(__doc__)
        return 2

    t1 = '--t1' in argv
    skip = None
    if '--skip' in argv:
        skip = argv[argv.index('--skip') + 1]
    files = [a for a in argv if not a.startswith('--') and a != skip]

    gb, ga = load(files[0], skip), load(files[1], skip)

    if t1:
        print('--- нормализация Т1 включена: регистр первой буквы и '
              'конечные «;» «.» не считаются расхождением')
    if skip:
        print('--- группы с «%s» в имени пропущены в обоих снимках' % skip)

    problems = 0
    problems += report('Предложения', sentences(gb, t1), sentences(ga, t1))
    problems += report('Телефоны', extract(gb, RE_TEL), extract(ga, RE_TEL))
    problems += report('Денежные суммы', extract(gb, RE_MONEY), extract(ga, RE_MONEY))

    pages_b, pages_a = set(gb), set(ga)
    if pages_b != pages_a:
        print('--- Состав групп изменился')
        for p in sorted(pages_b - pages_a):
            print('    ПРОПАЛА ГРУППА:   %s' % p)
        for p in sorted(pages_a - pages_b):
            print('    ПОЯВИЛАСЬ ГРУППА: %s' % p)

    print()
    if problems:
        print('--- УПАЛ: расхождений %d' % problems)
        return 1

    print('--- ПРОШЁЛ: текст, телефоны и суммы совпадают')
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
