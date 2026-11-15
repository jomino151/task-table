#!/usr/bin/env python3
"""Compile languages/*.po into .mo files and the JSON files the block scripts load.

Usage: python3 dev/make-translations.py   (needs msgfmt from gettext)

Each block script gets languages/task-table-<locale>-<script handle>.json with the
strings its source files pass to __(), _x(), _n() or sprintf(__()).
"""
import glob, json, os, re, subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, 'languages')
# Script handle (as generate_block_asset_handle() names it) -> source files.
SCRIPTS = {
    'task-table-task-table-editor-script': ['src/task-table/index.js', 'src/task-table/edit.js'],
    'task-table-new-event-signup-editor-script': ['src/new-event-signup/index.js', 'src/new-event-signup/edit.js'],
    'task-table-new-event-signup-view-script': ['src/new-event-signup/view.js'],
}
LIT = r"""'((?:\\.|[^'\\])*)'"""
CALL = re.compile(r"\b(?:__|_x|_n)\(\s*" + LIT)


def unescape(s):
    return re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), s)


def read_po(path):
    entries, header = {}, ''
    for block in open(path, encoding='utf-8').read().split('\n\n'):
        ctx = re.search(r'^msgctxt "(.*)"', block, re.M)
        mid = re.search(r'^msgid ((?:".*"\n?)+)', block, re.M)
        mstr = re.search(r'^msgstr ((?:".*"\n?)+)', block, re.M)
        if not mid or not mstr:
            continue
        join = lambda m: unescape(''.join(re.findall(r'"(.*)"', m.group(1))))
        key, val = join(mid), join(mstr)
        if key == '':
            header = val
        elif val:
            entries[(ctx.group(1) if ctx else None, key)] = val
    return header, entries


for po in sorted(glob.glob(os.path.join(LANG, '*.po'))):
    subprocess.run(['msgfmt', '-o', po[:-3] + '.mo', po], check=True)
    locale = os.path.basename(po)[len('task-table-'):-3]
    header, entries = read_po(po)
    plural = re.search(r'Plural-Forms: (.*)', header).group(1).strip()
    for handle, files in SCRIPTS.items():
        msgs = {'': {'domain': 'messages', 'lang': locale, 'plural-forms': plural}}
        for f in files:
            path = os.path.join(ROOT, f)
            if not os.path.exists(path):
                continue
            for m in CALL.finditer(open(path, encoding='utf-8').read()):
                s = unescape(m.group(1))
                if (None, s) in entries:
                    msgs[s] = [entries[(None, s)]]
        out = os.path.join(LANG, f'task-table-{locale}-{handle}.json')
        with open(out, 'w', encoding='utf-8') as fh:
            json.dump({'domain': 'messages', 'locale_data': {'messages': msgs}}, fh, ensure_ascii=False, indent='\t', sort_keys=True)
            fh.write('\n')
        print(f'{os.path.relpath(out, ROOT)}: {len(msgs) - 1} strings')
