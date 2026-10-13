import os, zipfile
import xml.etree.ElementTree as ET

WEB = '/home/msi_thin/projects/NUTrace'
MOB = '/mnt/c/capstone/assetapp_mobile/assetapp'
OUT = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE_FILLED.docx'
W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'

BLOCKS = [
 ('WEB', WEB, 'routes/web.php', [(906, 991), (4574, 4621), (4623, 4663), (4697, 4714), (4716, 4791), (1420, 1496),
                                 (1499, 1570), (1627, 1689), (2996, 3080), (3439, 3509), (3552, 3615), (2541, 2607),
                                 (62, 98), (5412, 5474), (1190, 1284), (133, 159), (183, 264), (100, 130)]),
 ('WEB', WEB, 'app/Support/Media.php', [(275, 317)]),
 ('WEB', WEB, 'resources/views/admin/dashboard.blade.php', [(494, 560)]),
 ('WEB', WEB, 'resources/views/admin/assets/asset_registry.blade.php', [(715, 760)]),
 ('WEB', WEB, 'app/Support/AssetCode.php', [(126, 188)]),
 ('WEB', WEB, 'app/Models/Asset.php', [(1, 50)]),
 ('WEB', WEB, 'app/Support/Disposals.php', [(149, 184), (186, 263)]),
 ('WEB', WEB, 'app/Http/Controllers/TransferController.php', [(1, 135)]),
 ('WEB', WEB, 'app/Support/AssetTransfer.php', [(293, 355), (449, 502)]),
 ('WEB', WEB, 'app/Support/AuditTrail.php', [(1, 67)]),
 ('WEB', WEB, 'app/Http/Middleware/LogUserLogin.php', [(1, 34)]),
 ('WEB', WEB, 'app/Providers/AppServiceProvider.php', [(35, 40)]),
 ('WEB', WEB, 'app/Models/EmployeeNumber.php', [(1, 18)]),
 ('MOBILE', MOB, 'components/asset-scanner.tsx', [(29, 75), (91, 143)]),
 ('MOBILE', MOB, 'components/QRViewModal.tsx', [(134, 154)]),
 ('MOBILE', MOB, 'lib/assetCode.ts', [(15, 109)]),
 ('MOBILE', MOB, 'lib/assetService.ts', [(527, 624), (626, 659), (1055, 1113)]),
 ('MOBILE', MOB, 'app/asset-registry.tsx', [(245, 337)]),
 ('MOBILE', MOB, 'lib/lifecycle.ts', [(17, 67), (69, 116)]),
 ('MOBILE', MOB, 'lib/maintenanceService.ts', [(290, 356), (431, 524)]),
 ('MOBILE', MOB, 'lib/repairService.ts', [(607, 692), (780, 869)]),
 ('MOBILE', MOB, 'lib/disposalService.ts', [(379, 461), (560, 633)]),
 ('MOBILE', MOB, 'lib/pulloutService.ts', [(69, 174)]),
 ('MOBILE', MOB, 'lib/transferService.ts', [(311, 403)]),
 ('MOBILE', MOB, 'lib/auditService.ts', [(39, 67), (88, 109), (139, 166)]),
 ('MOBILE', MOB, 'app/login.tsx', [(29, 83)]),
 ('MOBILE', MOB, 'lib/userService.ts', [(928, 1005), (1090, 1170)]),
 ('MOBILE', MOB, 'lib/passwordHash.ts', [(1, 37)]),
]

z = zipfile.ZipFile(OUT)
root = ET.fromstring(z.read('word/document.xml'))
texts = []
for p in root.iter(W + 'p'):
    texts.append(''.join(t.text or '' for t in p.iter(W + 't')))
blob = '\n'.join(texts)
textset = set(texts)

print('zip parts            :', len(z.namelist()))
print('paragraphs           :', len(texts))
for part in ('Source code: WEB', 'Source code: MOBILE'):
    print('part present         : %-22s %s' % (part, part in blob))

heads = [t.strip() for t in texts if t.strip().startswith('B.') and len(t.strip()) < 60]
print('section headings     :', len(heads))
from collections import Counter
print('   per section       :', dict(Counter(heads)))

missing = []
total = 0
for label, rootdir, path, ranges in BLOCKS:
    for a, b in ranges:
        lines = open(os.path.join(rootdir, path), encoding='utf-8', errors='replace').read().replace('\r\n', '\n').split('\n')
        block = [l.replace('\t', '    ').rstrip() for l in lines[a - 1:b]]
        first, last = a, b
        while block and block[-1] == '':
            block.pop()
            last -= 1
        while block and block[0] == '':
            block.pop(0)
            first += 1
        cap = '%s  |  lines %d-%d' % (path, first, last)
        if cap not in blob:
            missing.append(('caption', label, cap))
        for ln in block:
            total += 1
            if ln.strip() == '':
                continue
            if ('    ' + ln) not in textset:
                missing.append((label, path, a, ln[:60]))
print('source lines embedded:', total)
print('missing excerpts     :', len(missing))
for m in missing[:8]:
    print('   !!', m)

# the web part must be byte-identical to the previously delivered web-only file
WEBONLY = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE_WEB.docx'
if os.path.exists(WEBONLY):
    wz = zipfile.ZipFile(WEBONLY)
    wroot = ET.fromstring(wz.read('word/document.xml'))
    wtexts = [''.join(t.text or '' for t in p.iter(W + 't')) for p in wroot.iter(W + 'p')]
    print('web-only file exists : True')
    print('web part unchanged   :', all(t in textset for t in wtexts if t.strip()))
print('filled docx bytes    :', os.path.getsize(OUT))
