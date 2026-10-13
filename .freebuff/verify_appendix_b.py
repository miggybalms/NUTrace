import os, zipfile, html
import xml.etree.ElementTree as ET

SRC = '/home/msi_thin/projects/NUTrace'
ORIG = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE.docx'
OUT = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE_FILLED.docx'
W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'

import importlib.util
spec = importlib.util.spec_from_file_location('gen', os.path.join(SRC, '.freebuff/gen_appendix_b.py'))
gen = importlib.util.module_from_spec(spec)
# don't execute (it would rewrite the file); replicate the section list instead
SECTIONS = [
 ('B.1 QRBarcode Asset Identification', [
   ('routes/web.php', 906, 991), ('app/Support/Media.php', 275, 317),
   ('resources/views/admin/dashboard.blade.php', 494, 560),
   ('resources/views/admin/assets/asset_registry.blade.php', 715, 760)]),
 ('B.2 Bulk Asset Registration', [
   ('routes/web.php', 4574, 4621), ('routes/web.php', 4623, 4663), ('routes/web.php', 4697, 4714),
   ('routes/web.php', 4716, 4791), ('app/Support/AssetCode.php', 126, 188)]),
 ('B.3 Asset Lifecycle Management', [
   ('routes/web.php', 1420, 1496), ('routes/web.php', 1499, 1570), ('routes/web.php', 1627, 1689),
   ('app/Models/Asset.php', 1, 50)]),
 ('B.4 Repair and Replacement Workflow', [
   ('routes/web.php', 2996, 3080), ('routes/web.php', 3439, 3509), ('routes/web.php', 3552, 3615)]),
 ('B.5 Disposal and Archiving', [
   ('app/Support/Disposals.php', 149, 184), ('app/Support/Disposals.php', 186, 263),
   ('routes/web.php', 2541, 2607)]),
 ('B.6 Bulk Pullout', [('routes/web.php', 62, 98), ('routes/web.php', 5412, 5474)]),
 ('B.7 Employee Transfer and Accountability', [
   ('app/Http/Controllers/TransferController.php', 1, 135), ('app/Support/AssetTransfer.php', 293, 355),
   ('app/Support/AssetTransfer.php', 449, 502)]),
 ('B.8 Audit Logging', [
   ('app/Support/AuditTrail.php', 1, 67), ('app/Http/Middleware/LogUserLogin.php', 1, 34),
   ('app/Providers/AppServiceProvider.php', 35, 40), ('routes/web.php', 1190, 1284)]),
 ('B.9 Authentication and Employee ID Validation', [
   ('routes/web.php', 133, 159), ('routes/web.php', 183, 264), ('routes/web.php', 100, 130),
   ('app/Models/EmployeeNumber.php', 1, 18)]),
]

z = zipfile.ZipFile(OUT)
parts = z.namelist()
root = ET.fromstring(z.read('word/document.xml'))
paras = root.iter(W + 'p')
texts = []
for p in paras:
    txt = ''.join(t.text or '' for t in p.iter(W + 't'))
    texts.append(txt)
print('zip parts            :', len(parts))
print('paragraphs           :', len(texts))
print('non-empty paragraphs :', sum(1 for t in texts if t.strip()))
blob = '\n'.join(texts)

heads = [t for t in texts if t.strip().startswith('B.') and len(t) < 60 and '.' in t.strip()[1:3]]
print('section headings     :', len(heads))
for h in heads:
    print('   ', h)

missing = []
total = 0
for title, blocks in SECTIONS:
    assert title in blob, 'missing section: ' + title
    for path, a, b in blocks:
        lines = open(os.path.join(SRC, path), encoding='utf-8', errors='replace').read().replace('\r\n', '\n').split('\n')
        block = [l.replace('\t', '    ').rstrip() for l in lines[a - 1:b]]
        while block and block[-1] == '':
            block.pop()
        while block and block[0] == '':
            block.pop()
        cap = '%s  |  lines %d-%d' % (path, a, a + len(block) - 1)
        if cap not in blob:
            missing.append(('caption', cap))
        for ln in block:
            total += 1
            if ln.strip() == '':
                continue
            if ('    ' + ln) not in texts:
                missing.append((path, a, ln[:60]))
print('source lines embedded:', total)
print('missing excerpts     :', len(missing))
for m in missing[:10]:
    print('   !!', m)

print('original docx bytes  :', os.path.getsize(ORIG))
print('filled docx bytes    :', os.path.getsize(OUT))
zo = zipfile.ZipFile(ORIG)
zo_root = ET.fromstring(zo.read('word/document.xml'))
zo_txt = ''.join(t.text or '' for t in zo_root.iter(W + 't'))
print('original still blank :', ('B.1' not in zo_txt and 'Asset' not in zo_txt))
