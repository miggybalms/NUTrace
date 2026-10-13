import os, zipfile, html

WEB = '/home/msi_thin/projects/NUTrace'
MOB = '/mnt/c/capstone/assetapp_mobile/assetapp'
TPL = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE.docx'
OUT = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE_FILLED.docx'

WEB_SECTIONS = [
 ('B.1 QRBarcode Asset Identification', [
   ('routes/web.php', 906, 991, 'Asset lookup endpoint reached by a scanned QR / barcode payload'),
   ('app/Support/Media.php', 275, 317, 'Server-side QR label generation and storage for an Asset Code'),
   ('resources/views/admin/dashboard.blade.php', 494, 560, 'Camera scanning in the browser (html5-qrcode with jsQR fallback) and lookup'),
   ('resources/views/admin/assets/asset_registry.blade.php', 715, 760, 'Live QR label rendered from the Asset Code on the registration form'),
 ]),
 ('B.2 Bulk Asset Registration', [
   ('routes/web.php', 4574, 4621, 'Registry form validation, including the quantity of units to register'),
   ('routes/web.php', 4623, 4663, 'Asset Code mode: auto-generated codes or the office own custom codes, verified first'),
   ('routes/web.php', 4697, 4714, 'QR label saved for each registered asset'),
   ('routes/web.php', 4716, 4791, 'The per-unit loop that creates every asset, its audit entry and its QR label'),
   ('app/Support/AssetCode.php', 126, 188, 'Availability and validation of Asset Codes (no duplicate, no repeat in one submission)'),
 ]),
 ('B.3 Asset Lifecycle Management', [
   ('routes/web.php', 1420, 1496, 'Automatic transition to For Checking when the lifespan expires or maintenance is overdue'),
   ('routes/web.php', 1499, 1570, 'Maintenance completion, with the next maintenance date recalculated from the interval'),
   ('routes/web.php', 1627, 1689, 'Lifespan evaluation decisions (return to Active, repair, replacement, disposal)'),
   ('app/Models/Asset.php', 1, 50, 'Asset model: lifecycle fields, date casts and the in-inventory scope'),
 ]),
 ('B.4 Repair and Replacement Workflow', [
   ('routes/web.php', 2996, 3080, 'Repair request creation: request row, repair row, lifecycle change and audit entry'),
   ('routes/web.php', 3439, 3509, 'Repair completion: the result decides whether the asset returns to Active'),
   ('routes/web.php', 3552, 3615, 'Repair converted to Replacement, with the repair closed and the owner notified'),
 ]),
 ('B.5 Disposal and Archiving', [
   ('app/Support/Disposals.php', 149, 184, 'A disposal record is created from the approved request (the request is the source transaction)'),
   ('app/Support/Disposals.php', 186, 263, 'Archiving: one transaction that archives the record and takes the asset out of the inventory'),
   ('routes/web.php', 2541, 2607, 'The archive endpoint (Admin only), with the audit entry written alongside it'),
 ]),
 ('B.6 Bulk Pullout', [
   ('routes/web.php', 62, 98, 'One pullout transaction for many assets: pullout row plus one pullout_items row per asset'),
   ('routes/web.php', 5412, 5474, 'Bulk pullout endpoint: validates every asset, rejects unavailable ones, records the whole set'),
 ]),
 ('B.7 Employee Transfer and Accountability', [
   ('app/Http/Controllers/TransferController.php', 1, 135, 'Transfer / Employee Relocation controller: who holds what, and the confirmation'),
   ('app/Support/AssetTransfer.php', 293, 355, 'The transfer itself: assets are re-assigned, never re-created'),
   ('app/Support/AssetTransfer.php', 449, 502, 'Accountability history: the previous holder is closed and the new one opened per asset'),
 ]),
 ('B.8 Audit Logging', [
   ('app/Support/AuditTrail.php', 1, 67, 'Sign-in and sign-out events recorded in the audit trail (no network details)'),
   ('app/Http/Middleware/LogUserLogin.php', 1, 34, 'Middleware that records a successful sign-in once per session'),
   ('app/Providers/AppServiceProvider.php', 35, 40, 'Sign-outs recorded through the Logout event, covering every way out of the system'),
   ('routes/web.php', 1190, 1284, 'Audit log page: filtering, search, pagination and removal of any leftover address'),
 ]),
 ('B.9 Authentication and Employee ID Validation', [
   ('routes/web.php', 133, 159, 'Sign-in: credential check and role-based landing (Admin, Department Head, Employee)'),
   ('routes/web.php', 183, 264, 'Registration: the employee number must exist in employee_numbers, and may register only once'),
   ('routes/web.php', 100, 130, 'Password policy helper: rejects passwords built from the employee name, email or number'),
   ('app/Models/EmployeeNumber.php', 1, 18, 'Employee number model: the roster row a user account is bound to'),
 ]),
]

MOBILE_SECTIONS = [
 ('B.1 QRBarcode Asset Identification', [
   ('components/asset-scanner.tsx', 29, 75, 'Scanned QR payload decoded to an asset key and resolved against the live assets table'),
   ('components/asset-scanner.tsx', 91, 143, 'One scan fires once; the asset record is opened from the resolved row'),
   ('components/QRViewModal.tsx', 134, 154, 'The asset QR sticker rendered from the Asset Code value'),
 ]),
 ('B.2 Bulk Asset Registration', [
   ('lib/assetCode.ts', 15, 109, 'Asset-code rules: auto-generated codes, the office own codes and the duplicate guard'),
   ('lib/assetService.ts', 527, 624, 'Registering one asset: asset row, photo record and its own audit entry'),
   ('lib/assetService.ts', 626, 659, 'Bulk duplicate guard — checks every planned code against assets.Asset_code first'),
   ('app/asset-registry.tsx', 245, 337, 'The registry plans the codes for a batch and validates them before saving'),
 ]),
 ('B.3 Asset Lifecycle Management', [
   ('lib/lifecycle.ts', 17, 67, 'Which lifecycle states a requester may see, and why an asset is unavailable'),
   ('lib/lifecycle.ts', 69, 116, 'Maintenance eligibility per status, and the status each request type produces'),
   ('lib/maintenanceService.ts', 290, 356, 'Recording preventive maintenance: completion date, next schedule and audit trail'),
   ('lib/maintenanceService.ts', 431, 524, 'Asset evaluation run for expired lifespan / overdue maintenance'),
 ]),
 ('B.4 Repair and Replacement Workflow', [
   ('lib/repairService.ts', 607, 692, 'Repair request submission: validation, request row and one repairs row per asset'),
   ('lib/repairService.ts', 780, 869, 'Admin decision on a repair: status, cost, result and the fields written back'),
   ('lib/assetService.ts', 1055, 1113, 'Repair sent to replacement: the new asset is created and linked to the old one'),
 ]),
 ('B.5 Disposal and Archiving', [
   ('lib/disposalService.ts', 379, 461, 'Archiving a disposal record — the asset leaves the inventory, the record is kept'),
   ('lib/disposalService.ts', 560, 633, 'Creating the disposal record(s) and flagging the asset as Disposal'),
 ]),
 ('B.6 Bulk Pullout', [
   ('lib/pulloutService.ts', 69, 174, 'Bulk pullout: one request, one pullout row per asset, one audit entry per asset'),
 ]),
 ('B.7 Employee Transfer and Accountability', [
   ('lib/transferService.ts', 311, 403, 'Employee transfer: the request is created first, then the assets are repointed'),
 ]),
 ('B.8 Audit Logging', [
   ('lib/auditService.ts', 39, 67, 'Network details stripped out of any audit text before an admin can read it'),
   ('lib/auditService.ts', 88, 109, 'A mobile sign-in or sign-out written into the shared audit trail'),
   ('lib/auditService.ts', 139, 166, 'Appending to audit_logs: best-effort, so logging never blocks the action'),
 ]),
 ('B.9 Authentication and Employee ID Validation', [
   ('app/login.tsx', 29, 84, 'Sign-in: credentials verified in the database, then the role chooses the landing area'),
   ('lib/userService.ts', 928, 1005, 'Employee number lookup — the number must be on file, and only an active row may register'),
   ('lib/userService.ts', 1090, 1170, 'Creating the account against the employee record, with the duplicate checks in front of it'),
   ('lib/passwordHash.ts', 1, 37, 'Password hashing that both the mobile app and the Laravel web app can verify'),
 ]),
]


def read_block(root, path, a, b):
    """Return (code lines, first line number, last line number).

    Blank lines at either edge are dropped from what is shown, and the reported
    line numbers follow the lines that are actually printed.
    """
    with open(os.path.join(root, path), encoding='utf-8', errors='replace') as fh:
        lines = fh.read().replace('\r\n', '\n').split('\n')
    out = [lines[i].replace('\t', '    ').rstrip() for i in range(a - 1, min(b, len(lines)))]
    first, last = a, min(b, len(lines))
    while out and out[-1] == '':
        out.pop()
        last -= 1
    while out and out[0] == '':
        out.pop(0)
        first += 1
    return out, first, last


def esc(t):
    return html.escape(t, quote=False)


def run(text, mono=False, bold=False, italic=False, size=None, color=None, underline=False):
    rpr = '<w:rPr>'
    if mono:
        rpr += '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>'
    if bold:
        rpr += '<w:b/>'
    if underline:
        rpr += '<w:u w:val="single"/>'
    if italic:
        rpr += '<w:i/>'
    if color:
        rpr += '<w:color w:val="%s"/>' % color
    if size:
        rpr += '<w:sz w:val="%d"/><w:szCs w:val="%d"/>' % (size, size)
    rpr += '</w:rPr>'
    return '<w:r>%s<w:t xml:space="preserve">%s</w:t></w:r>' % (rpr, esc(text))


def para(runs, style=None, shade=None):
    ppr = '<w:pPr>'
    if style:
        ppr += '<w:pStyle w:val="%s"/>' % style
    if shade:
        ppr += '<w:shd w:val="clear" w:color="auto" w:fill="%s"/>' % shade
    ppr += '<w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'
    ppr += '</w:pPr>'
    return '<w:p>%s%s</w:p>' % (ppr, ''.join(runs))


def render_part(label, root, sections, note):
    body = [para([run('Source code: %s' % label, bold=True, size=28, underline=True)], style='Heading1'),
            para([run(note, size=20, italic=True)]),
            para([run('', size=20)])]
    report = []
    for title, blocks in sections:
        body.append(para([run(title, bold=True, size=26)], style='Heading2'))
        body.append(para([run('', size=20)]))
        for path, a, b, caption in blocks:
            lines, first, last = read_block(root, path, a, b)
            body.append(para([run('%s  |  lines %d-%d  |  %s' % (path, first, last, caption),
                                 size=19, bold=True, color='1F3864')]))
            for ln in lines:
                body.append(para([run('    ' + ln if ln else '', mono=True, size=16)], shade='F5F5F5'))
            body.append(para([run('', size=18)]))
            report.append((label, path, first, last, len(lines), lines[-1][:70] if lines else ''))
    return body, report


body = [para([run('APPENDIX B. RELEVANT SOURCE', bold=True, size=32)], style='Heading1'),
        para([run('Source code: WEB:  and  Mobile Application:', size=24)]),
        para([run('The excerpts below are quoted verbatim from the NU Trace system: first the web application '
                  '(Laravel / PHP), then the mobile application (React Native / Expo, TypeScript). Each excerpt '
                  'names the file it comes from and the line range it occupies in that file, so it can be located '
                  'and re-checked at any time. Indentation and wording are unchanged from the source.', size=20, italic=True)]),
        para([run('', size=20)])]

web_body, web_report = render_part('WEB', WEB, WEB_SECTIONS,
    'Server-rendered web application — the Laravel project. Request handling lives in routes/web.php; the reusable '
    'rules live in app/Support and app/Http.')
mob_body, mob_report = render_part('MOBILE', MOB, MOBILE_SECTIONS,
    'Mobile application — the React Native / Expo project (assetapp). Screen logic lives in app/, and the shared '
    'data rules live in lib/.')

body += web_body + mob_body
body.append(para([run('Summary of source files cited', bold=True, size=26)], style='Heading2'))
for f in ['WEB — routes/web.php', 'WEB — app/Support/AssetCode.php', 'WEB — app/Support/AssetTransfer.php',
          'WEB — app/Support/AuditTrail.php', 'WEB — app/Support/Disposals.php',
          'WEB — app/Http/Middleware/LogUserLogin.php', 'WEB — app/Http/Controllers/TransferController.php',
          'WEB — app/Providers/AppServiceProvider.php', 'WEB — app/Models/Asset.php',
          'WEB — app/Models/EmployeeNumber.php', 'WEB — app/Support/Media.php',
          'WEB — resources/views/admin/dashboard.blade.php',
          'WEB — resources/views/admin/assets/asset_registry.blade.php',
          'MOBILE — app/login.tsx', 'MOBILE — app/asset-registry.tsx', 'MOBILE — components/asset-scanner.tsx',
          'MOBILE — components/QRViewModal.tsx', 'MOBILE — lib/assetCode.ts', 'MOBILE — lib/assetService.ts',
          'MOBILE — lib/lifecycle.ts', 'MOBILE — lib/maintenanceService.ts', 'MOBILE — lib/repairService.ts',
          'MOBILE — lib/disposalService.ts', 'MOBILE — lib/pulloutService.ts', 'MOBILE — lib/transferService.ts',
          'MOBILE — lib/auditService.ts', 'MOBILE — lib/userService.ts', 'MOBILE — lib/passwordHash.ts']:
    body.append(para([run('- ' + f, mono=True, size=18)]))

sect = ('<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>'
        '<w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="720" w:footer="720" w:gutter="0"/>'
        '<w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>')

doc = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
       'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
       '<w:body>' + ''.join(body) + sect + '</w:body></w:document>')

zin = zipfile.ZipFile(TPL, 'r')
zout = zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED)
for item in zin.infolist():
    data = zin.read(item.filename)
    if item.filename == 'word/document.xml':
        data = doc.encode('utf-8')
    zout.writestr(item, data)
zout.close()
zin.close()

print('written:', OUT, os.path.getsize(OUT), 'bytes')
print()
for label, path, a, b, n, last in web_report + mob_report:
    print('%-6s %-52s %4d-%-4d %3d lines | ends: %s' % (label, path, a, b, n, last))
