#!/usr/bin/env python3
"""Extract readable text (paragraphs + tables) from a .docx file."""
import sys
import zipfile
import xml.etree.ElementTree as ET

W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'


def cell_text(cell):
    parts = []
    for p in cell.iter(W + 'p'):
        runs = [t.text or '' for t in p.iter(W + 't')]
        line = ''.join(runs).strip()
        if line:
            parts.append(line)
    return ' '.join(parts)


def dump(path):
    with zipfile.ZipFile(path) as z:
        xml = z.read('word/document.xml')
    root = ET.fromstring(xml)
    body = root.find(W + 'body')

    for el in body:
        tag = el.tag.replace(W, '')
        if tag == 'p':
            text = ''.join(t.text or '' for t in el.iter(W + 't')).strip()
            if text:
                print(text)
        elif tag == 'tbl':
            print('--- TABLE ---')
            for row in el.findall(W + 'tr'):
                cells = [cell_text(c) for c in row.findall(W + 'tc')]
                print(' | '.join(cells))
            print('--- END TABLE ---')


for path in sys.argv[1:]:
    print('=' * 30, path.split('/')[-1], '=' * 30)
    dump(path)
    print()
