import os, zipfile, html

SRC = '/home/msi_thin/projects/NUTrace'
TPL = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE.docx'
OUT = '/mnt/c/Users/MSI THIN/Documents/APPENDIX/APPENDIX B. RELEVANT SOURCE_FILLED.docx'

SECTIONS = [
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


def read_block(path, a, b):
    with open(os.path.join(SRC, path), encoding='utf-8', errors='replace') as fh:
        lines = fh.read().replace('\r\n', '\n').split('\n')
    out = []
    for i in range(a - 1, min(b, len(lines))):
        out.append(lines[i].replace('\t', '    ').rstrip())
    while out and out[-1] == '':
        out.pop()
    while out and out[0] == '':
        out.pop(0)
    return out


def esc(t):
    return html.escape(t, quote=False)


def run(text, mono=False, bold=False, italic=False, size=None, color=None):
    rpr = '<w:rPr>'
    if mono:
        rpr += '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>'
    if bold:
        rpr += '<w:b/>'
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


body = []
body.append(para([run('APPENDIX B. RELEVANT SOURCE', bold=True, size=32)], style='Heading1'))
body.append(para([run('Source code: WEB:', size=24)]))
body.append(para([run(
    'The excerpts below are quoted from the NU Trace web application (Laravel / PHP). Each excerpt names the file '
    'it comes from and the line range it occupies in that file, so it can be located and re-checked at any time. '
    'Indentation and wording are unchanged from the source.', size=20, italic=True)]))
body.append(para([run('', size=20)]))

for title, blocks in SECTIONS:
    body.append(para([run(title, bold=True, size=26)], style='Heading2'))
    body.append(para([run('', size=20)]))
    for path, a, b, caption in blocks:
        lines = read_block(path, a, b)
        body.append(para([run('%s  |  lines %d-%d  |  %s' % (path, a, a + len(lines) - 1, caption),
                             size=19, bold=True, color='1F3864')]))
        for ln in lines:
            body.append(para([run('    ' + ln if ln else '', mono=True, size=16)], shade='F5F5F5'))
        body.append(para([run('', size=18)]))

body.append(para([run('Summary of source files cited', bold=True, size=26)], style='Heading2'))
for f in ['routes/web.php', 'app/Support/AssetCode.php', 'app/Support/AssetTransfer.php', 'app/Support/AuditTrail.php',
          'app/Support/Disposals.php', 'app/Http/Middleware/LogUserLogin.php',
          'app/Http/Controllers/TransferController.php', 'app/Providers/AppServiceProvider.php', 'app/Models/Asset.php',
          'app/Models/EmployeeNumber.php', 'app/Support/Media.php', 'resources/views/admin/dashboard.blade.php',
          'resources/views/admin/assets/asset_registry.blade.php']:
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
