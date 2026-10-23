#!/usr/bin/env python3
"""Build the panel-recommendation demonstration guide (.docx).

No third-party libraries: the document is written as OOXML directly, the same
way the appendix generators in this folder work. Screenshots come from
screenshots/guide/final, already cropped and stripped of the browser badge.
"""

import html
import os
import struct
import zipfile
from datetime import datetime, timezone

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SHOTS = os.path.join(ROOT, 'screenshots', 'guide', 'final')
OUT_DIR = os.path.join(ROOT, 'build')
OUT = os.path.join(OUT_DIR, 'Panel Recommendations - Demonstration Trigger Guide.docx')

W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
NS = (
    f'xmlns:w="{W}" '
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
    'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
    'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
    'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"'
)

CONTENT_W = 9638  # twips between the A4 margins


def esc(text):
    return html.escape(text, quote=False)


def run(text, bold=False, italic=False, size=None, color=None, mono=False, underline=False):
    rpr = '<w:rPr>'
    if mono:
        rpr += '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>'
    if bold:
        rpr += '<w:b/>'
    if italic:
        rpr += '<w:i/>'
    if color:
        rpr += f'<w:color w:val="{color}"/>'
    if size:
        rpr += f'<w:sz w:val="{int(size * 2)}"/><w:szCs w:val="{int(size * 2)}"/>'
    if underline:
        rpr += '<w:u w:val="single"/>'
    rpr += '</w:rPr>'
    return f'<w:r>{rpr}<w:t xml:space="preserve">{esc(text)}</w:t></w:r>'


def para(runs, style='Normal', align=None, before=0, after=80, left=None, hanging=None,
         keep_next=False, keep_lines=False, shade=None, border_left=None, line=264):
    if isinstance(runs, str):
        runs = [run(runs)]
    ppr = '<w:pPr>'
    ppr += f'<w:pStyle w:val="{style}"/>'
    if keep_next:
        ppr += '<w:keepNext/>'
    if keep_lines:
        ppr += '<w:keepLines/>'
    if border_left:
        ppr += ('<w:pBdr><w:left w:val="single" w:sz="18" w:space="8" '
                f'w:color="{border_left}"/></w:pBdr>')
    if shade:
        ppr += f'<w:shd w:val="clear" w:color="auto" w:fill="{shade}"/>'
    ppr += f'<w:spacing w:before="{before}" w:after="{after}" w:line="{line}" w:lineRule="auto"/>'
    if left is not None:
        ppr += f'<w:ind w:left="{left}"' + (f' w:hanging="{hanging}"' if hanging else '') + '/>'
    if align:
        ppr += f'<w:jc w:val="{align}"/>'
    ppr += '</w:pPr>'
    return f'<w:p>{ppr}{"".join(runs)}</w:p>'


def h(text, level=1):
    return para([run(text)], style=f'Heading{level}')


def body(text, **kw):
    return para([run(text)], **kw)


def label_line(label, text):
    return para([run(label + '  ', bold=True, color='0F2143'), run(text)], style='Meta')


def say(text):
    return para([run('Say:  ', bold=True, italic=True, color='7A5A16'),
                 run(text, italic=True)], style='Say')


def bullet(text):
    return para([run('•  ', color='BF9A3C'), run(text)], left=280, hanging=280, after=40)


def step(number, text):
    return para([run(f'{number}.  ', bold=True, color='0F2143'), run(text)],
                left=340, hanging=340, after=60)


def quote(text):
    return para([run('“' + text + '”', italic=True)], style='Quote')


def mono_block(lines):
    out = []
    for i, line in enumerate(lines):
        out.append(para([run(line, mono=True, size=9)],
                        style='Mono', before=(80 if i == 0 else 0),
                        after=(80 if i == len(lines) - 1 else 0)))
    return ''.join(out)


def page_break():
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'


def table(rows, widths, header=True, size=8.5, first_col_bold=False, align_first_center=False):
    grid = ''.join(f'<w:gridCol w:w="{w}"/>' for w in widths)
    borders = ('<w:tblBorders>'
               '<w:top w:val="single" w:sz="6" w:color="D8D2C4"/>'
               '<w:left w:val="single" w:sz="6" w:color="D8D2C4"/>'
               '<w:bottom w:val="single" w:sz="6" w:color="D8D2C4"/>'
               '<w:right w:val="single" w:sz="6" w:color="D8D2C4"/>'
               '<w:insideH w:val="single" w:sz="4" w:color="E4DED0"/>'
               '<w:insideV w:val="single" w:sz="4" w:color="E4DED0"/>'
               '</w:tblBorders>')
    tbl_pr = (f'<w:tblPr><w:tblW w:w="{sum(widths)}" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
              f'{borders}<w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="90" w:type="dxa"/>'
              '<w:bottom w:w="60" w:type="dxa"/><w:right w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr>')

    xml = [f'<w:tbl>{tbl_pr}<w:tblGrid>{grid}</w:tblGrid>']
    for r_index, row in enumerate(rows):
        is_head = header and r_index == 0
        shade = '0F2143' if is_head else ('FBF8F0' if r_index % 2 == 0 else None)
        tr_pr = '<w:trPr><w:cantSplit/>' + ('<w:tblHeader/>' if is_head else '') + '</w:trPr>'
        cells = []
        for c_index, text in enumerate(row):
            color = 'FFFFFF' if is_head else '1F2937'
            bold = is_head or (first_col_bold and c_index == 0)
            jc = '<w:jc w:val="center"/>' if (align_first_center and c_index == 0) else ''
            cell_shade = (f'<w:shd w:val="clear" w:color="auto" w:fill="{shade}"/>' if shade else '')
            tc_pr = (f'<w:tcPr><w:tcW w:w="{widths[c_index]}" w:type="dxa"/>{cell_shade}'
                     f'<w:vAlign w:val="center"/></w:tcPr>')
            pieces = str(text).split('\n')
            runs = []
            for k, piece in enumerate(pieces):
                if k:
                    runs.append('<w:r><w:br/></w:r>')
                runs.append(run(piece, bold=bold, size=size, color=color))
            p = (f'<w:p><w:pPr><w:pStyle w:val="TableText"/>{jc}'
                 '<w:spacing w:before="20" w:after="20" w:line="240" w:lineRule="auto"/></w:pPr>'
                 + ''.join(runs) + '</w:p>')
            cells.append(f'<w:tc>{tc_pr}{p}</w:tc>')
        xml.append(f'<w:tr>{tr_pr}{"".join(cells)}</w:tr>')
    xml.append('</w:tbl>')
    xml.append(para([run('')], after=40))
    return ''.join(xml)


# --------------------------------------------------------------------------- images

def png_size(path):
    with open(path, 'rb') as fh:
        head = fh.read(33)
    return struct.unpack('>II', head[16:24])


class Figures:
    def __init__(self):
        self.rels = []
        self.used = set()
        self.doc_pr = 100

    def add(self, number, filename, width_in, caption):
        if number in self.used:
            return ''
        self.used.add(number)
        path = os.path.join(SHOTS, filename)
        px_w, px_h = png_size(path)
        cx = int(width_in * 914400)
        cy = int(cx * px_h / px_w)
        rid = f'rId{10 + len(self.rels)}'
        self.rels.append((rid, filename))
        self.doc_pr += 1
        drawing = (
            '<w:p><w:pPr><w:pStyle w:val="Figure"/><w:keepNext/>'
            '<w:spacing w:before="120" w:after="40" w:line="240" w:lineRule="auto"/>'
            '<w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            '<wp:inline distT="0" distB="0" distL="0" distR="0">'
            f'<wp:extent cx="{cx}" cy="{cy}"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
            f'<wp:docPr id="{self.doc_pr}" name="Figure {number}"/>'
            '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            f'<pic:pic><pic:nvPicPr><pic:cNvPr id="{self.doc_pr}" name="{esc(filename)}"/>'
            '<pic:cNvPicPr/></pic:nvPicPr>'
            f'<pic:blipFill><a:blip r:embed="{rid}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{cx}" cy="{cy}"/></a:xfrm>'
            '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
        )
        caption_p = para([run(f'Figure {number} — {caption}', italic=True, size=8.5, color='6B7280')],
                         align='center', after=200)
        return drawing + caption_p


FIGS = Figures()

FIGURES = {
    1: ('02-requests-filter.png', 6.5,
        'Requests: the period and sort controls, two pending rows with their approve / reject buttons, '
        'and the count line under the table.'),
    2: ('03-assets-overview-v4.png', 6.4,
        'Assets opens as a department index: totals and a lifecycle split per department, not a list of every asset.'),
    3: ('04-department-assets-v2.png', 6.5,
        'Registrar’s assets: filters at the top, Print Labels in the toolbar, and the page count under the table.'),
    4: ('05-label-settings-v4.png', 4.7,
        'The Printing settings panel on the label sheet: sticker width and height, QR size and layout.'),
    5: ('06-sticker-closeup-v2.png', 4.2,
        'One sticker: NU seal, asset name, AST-0001, location, category and the signed QR square.'),
    6: ('07-reports-v4.png', 6.5,
        'Reports: report type, date range, status and department, with Export CSV, Print and the totals the filters produced.'),
    7: ('08-disposal-forms-v2.png', 6.5,
        'Disposal → Official Disposal Forms: the five stages, the form queue and the Print action.'),
    8: ('08b-disposal-form-v2.png', 6.5,
        'The digital disposal form, prefilled from the request and the asset record, with the official code legend.'),
    9: ('10-asset-import.png', 6.5,
        'Import Existing Assets: the template, the file step and the rules the import follows.'),
    10: ('09-service-life-review-v2.png', 6.5,
         'Service-life review: assets at or past the three-year mark, the policy card and each review status.'),
    11: ('11-employees-roles.png', 6.5,
         'Employee Management: the role each account holds and whether it has registered.'),
    12: ('11b-employee-import.png', 6.5,
         'Import CSV: the employee masterlist template, the file check and the required columns.'),
    13: ('13-qr-scanner.png', 6.0,
         'Dashboard → Scanner on a genuine sticker: “Verified sticker — the signature is genuine and matches the asset in the database.”'),
    14: ('14-employee-request-form.png', 3.5,
         'The employee’s Submit Request form. Attach Photo is marked required; submitting without one is refused.'),
    15: ('15-notifications-v2.png', 6.5,
         'The employee’s notification bell: the disposal approval is waiting for Nena.'),
}


def fig(number, width=None):
    filename, default_width, caption = FIGURES[number]
    return FIGS.add(number, filename, width or default_width, caption)


# --------------------------------------------------------------------------- content

RECS = [
    dict(
        n=1,
        title='Faster lists of assets and QR codes',
        words='Improve loading time and responsiveness for large lists of assets and QR codes.',
        where='Assets (sidebar) → any department → View All',
        steps=[
            'Click Assets. The page is a department index: each department shows its total and a lifecycle split.',
            'Click View All on Registrar. The list finishes with “Showing 1–10 of 10 assets”, so only one page is drawn.',
            'Narrow the list with Search, Categories or Location. The table reloads with the filter applied instead of '
            'sorting an already-loaded list in the browser.',
        ],
        say_text='The totals come from one GROUP BY, the rows are paged in SQL, and the full registry leaves through '
                 'the CSV download rather than the screen. At 60 assets or 60,000 the page runs the same number of queries.',
        figs=[2, 3],
    ),
    dict(
        n=2,
        title='Monthly filtering and sorting for requests',
        words='Monthly filtering and sorting for request records.',
        where='Requests',
        steps=[
            'Open Requests. The default order is “Pending first”, so the rows waiting on the office sit at the top.',
            'Set PERIOD to Last month and SORT to Newest first, then click Apply.',
            'The footer reads “Showing 1–2 of 2 requests in September 2026”. Switch SORT to Oldest first and apply again '
            'to reverse the two rows.',
            'Set PERIOD to Custom range to show the two date fields.',
        ],
        say_text='The filter is on the date the request was filed, not the asset’s age. Both the page and Export Report '
                 'read the same filter, so the CSV cannot disagree with the screen.',
        figs=[1],
    ),
    dict(
        n=3,
        title='Reports by month, year or date range',
        words='Reports for newly acquired / repaired / disposed assets by month, year or date range.',
        where='Reports',
        steps=[
            'Open Reports.',
            'Set REPORT to Asset Disposals and DATE RANGE to This year, then click Apply Filters.',
            'Point at the line “Generated … · N assets match these criteria”, the totals under it, and the Records table.',
            'Click Print for the printable sheet; Export CSV writes the same rows.',
        ],
        say_text='Six report types share the same filters: Current Asset Inventory, Asset Acquisitions, Repairs and '
                 'Maintenance, Asset Transfers, Asset Disposals, and Requests and Workflow Statuses.',
        figs=[6],
    ),
    dict(
        n=4,
        title='CSV export for authorised users',
        words='CSV export for authorized users.',
        where='Reports · Requests · Assets · Employee Management · Audit Log',
        steps=[
            'On Reports, click Export CSV at the right of the report header (Figure 6).',
            'On Requests the same action is Export Report (Figure 1); on Assets it is Download Inventory; Employee '
            'Management has Export CSV in its header (Figure 12).',
            'Open the downloaded file to show the columns match the table.',
        ],
        say_text='Every export is built from the same filters and the same order as the screen it sits on, so the sheet '
                 'the panel just saw is the sheet in the file.',
        figs=[],
    ),
    dict(
        n=5,
        title='Bulk QR printing, selected by location',
        words='Bulk QR printing, assets selectable by location.',
        where='Assets → Registrar → View All',
        steps=[
            'Set Location to Registrar Office (or pick a Category).',
            'Leave the status chips on All.',
            'Click Print Labels. A print sheet opens with one sticker for every asset matching the filters.',
        ],
        say_text='Print Labels prints one sticker per asset matching the filters above; a whole office can be labelled in '
                 'one job, and the sheet can also be opened by a list of codes at /admin/assets/labels.',
        figs=[3, 5],
    ),
    dict(
        n=6,
        title='Sticker design',
        words='QR sticker design: NU logo (transparent), asset code, location.',
        where='The label sheet (Print Labels, or /admin/assets/labels?codes=AST-0001)',
        steps=[
            'Open the sheet for one asset and point at the sticker.',
            'Name the parts in order: the NU seal, the asset name, the Asset Code, Location, Category and the QR square.',
            'Mention the printed line “DO NOT REMOVE THIS TAG”.',
        ],
        say_text='The QR does not just hold the code: it carries the signed payload (it begins with NUT1), which is what '
                 'makes a scanned sticker provable later.',
        figs=[5],
    ),
    dict(
        n=7,
        title='Printing settings',
        words='Printing settings: sticker dimensions, code size, page layout, asset selection.',
        where='Label sheet → Printing settings panel',
        steps=[
            'Click the bar “Print settings — sticker size, QR size and layout”.',
            'Change Sticker width or height, drag the QR size slider, or switch Layout to A4 sheet.',
            'Click Save settings; the sticker redraws. Reset to stock returns to 50 × 25 mm with a 21 mm code.',
        ],
        say_text='Sizes are clamped on the server. Below the printable minimum the sheet warns instead of printing a '
                 'sticker whose code cannot be read. The choice lasts for the browser session; the defaults live in '
                 'config/asset_label.php.',
        figs=[4],
    ),
    dict(
        n=8,
        title='The digital disposal form, with supporting documents',
        words='Digital disposal form with supporting documents attached.',
        where='Disposal → Official Disposal Forms',
        steps=[
            'Point at the five stages across the top: Awaiting preparation, Ready to print, Printed — awaiting signed '
            'form, Signed form received, Disposal finalized. Form #1 sits at Ready to print.',
            'Click Prepare (or Review) on form #1. The form is prefilled from the request and the asset record: office, '
            'expected date, method of disposal, the item row and the official code legend.',
            'Click Print selected. Printing never marks an asset disposed.',
            'Use the signed-copy step on the form to attach the scanned copy that Accounting returns, then finalize.',
        ],
        say_text='The paper form and the record are the same object. A PDF, JPG, PNG or WEBP scan up to 5 MB is attached '
                 'to the form, so the signed sheet is filed with the transaction that produced it.',
        figs=[7, 8],
    ),
    dict(
        n=9,
        title='Migrating existing asset records',
        words='Module to migrate existing university asset records.',
        where='Assets → Import Inventory',
        steps=[
            'Click Import Inventory in the top-right of Assets.',
            'Click Download template, choose the office workbook, then Read headers.',
            'Confirm which column is which, review the rows, and commit in batches.',
        ],
        say_text='PROPERTY ID NO is the duplicate key: a second upload of the same file skips what is already registered '
                 'instead of duplicating it, and an existing row is never overwritten. Each imported asset gets its own '
                 'signed sticker.',
        figs=[9],
    ),
    dict(
        n=10,
        title='Bulk employee (faculty) import',
        words='Bulk CSV import of faculty-member records.',
        where='Employee Management → Import CSV (or Import Masterlist)',
        steps=[
            'Open the Import CSV tab and click Download CSV template.',
            'Fill Employee Number, Full Name and Department. Email and Status are optional.',
            'Choose File, click Check file for the preview, then Import.',
        ],
        say_text='The file is limited to 2 MB and 2,000 rows, an employee already on the masterlist is skipped rather '
                 'than overwritten, and importing never creates an account or grants a role.',
        figs=[12],
    ),
    dict(
        n=11,
        title='Digitally signed QR authenticity',
        words='Digitally signed QR authenticity checks.',
        where='Dashboard → Scanner (Quick Summary)',
        steps=[
            'Click Scanner, then Start Camera, and allow the camera when the browser asks.',
            'Point it at a printed sticker — AST-0001 in the demonstration data.',
            'The panel draws a green “Verified sticker” banner above the asset card.',
        ],
        say_text='Four other verdicts exist and each means something different: Unsigned sticker (printed before signing '
                 'was introduced), Failed authenticity check (the signature does not match the payload), Unknown signing '
                 'key, Sticker revoked, and Verification unavailable — which is the server honestly saying it holds no '
                 'key, never an accusation against the sticker.',
        figs=[13],
    ),
    dict(
        n=12,
        title='The three-year mark before a disposal',
        words='Disposal notifications; confirm the three-year rule first.',
        where='Disposal → Service-life review',
        steps=[
            'Click the Service-life review tab.',
            'Point at the policy card on the right: the mark is 3 years, set in config/disposal.php and '
            'DISPOSAL_AGE_REVIEW_YEARS.',
            'Point at the totals — 13 assets at the mark, the oldest 10 years 4 months, 2 already in a disposal request — '
            'and at the oldest-first table.',
        ],
        say_text='This is a review prompt, not a disposal list: nothing here approves a disposal or changes a lifecycle '
                 'status. Assets without an acquisition date, and assets already Disposed or Pulled out, are not listed.',
        figs=[10],
    ),
    dict(
        n=13,
        title='Approval is required, and a request cannot be edited',
        words='Required approval workflow; no editing until approved.',
        where='Requests',
        steps=[
            'Click the Pending tab. Two requests are waiting.',
            'Click the green check on a pending row — or open the row and use Approve at the foot of Request Details — '
            'then confirm in the dialog.',
            'The status becomes Approved and the row’s actions change to “No actions”.',
            'Reject asks for a reason; that reason is stored as the request’s Admin Remarks.',
        ],
        say_text='Only the Asset Management Office can approve, self-approval is refused, and Request Details says '
                 '“Submitted by the requester and cannot be edited” — there is no edit path once a request is in.',
        figs=[1],
    ),
    dict(
        n=14,
        title='Separate accounts and role-based permissions',
        words='Separate accounts and role-based permissions.',
        where='Employee Management · sign-in',
        steps=[
            'Open Employee Management and point at the ACCOUNT / REGISTRATION column: Admin, Department Head, Employee.',
            'Click the pencil on an account to show the Role select. A role is granted here, one account at a time.',
            'Add Employee adds a roster entry; the employee’s own registration creates the account. Importing never '
            'grants a role.',
            'To prove the separation, sign in as grace@nu-lipa.edu.ph (Department Head) and open /admin — it is refused; '
            'the same happens for an employee account.',
        ],
        say_text='There are three areas — /admin, /department-head and /users — behind one gate (RequireAdminArea and '
                 'Access), and the department an account sits in never grants an area by itself.',
        figs=[11],
    ),
    dict(
        n=15,
        title='Query optimisation',
        words='Optimize DB queries for asset retrieval, monthly filtering, reports.',
        where='The same screens as 1 and 2 — no separate page',
        steps=[
            'Show the department index and the paged list (Figure 2 and Figure 3), then the filtered requests (Figure 1).',
            'Say where the work happens rather than clicking anything new.',
        ],
        say_text='Filtering and paging run in SQL, indexes cover property_id_no, department_id, inventory_removed_at, '
                 'notifications(user_id, is_read) and request_items.asset_id, and the listing was measured at about '
                 '1.8 ms over 400 assets in AssetListingPerformanceTest.',
        figs=[],
    ),
    dict(
        n=16,
        title='A photograph is required with every request',
        words='Photographs mandatory where photographic evidence is required.',
        where='Employee area → Requests → Submit Request (/user/request-asset)',
        steps=[
            'Choose a Request Type, add an asset code, and write the note.',
            'Click Submit Request without a photo: the form stops at Attach Photo and says a photo is required.',
            'Attach the photo and submit.',
        ],
        say_text='The rule is enforced twice — in the form and again on the server — and the mobile app refuses to submit '
                 'without it or if the upload fails. The photo is what the office sees in Request Details as evidence.',
        figs=[14],
    ),
    dict(
        n=17,
        title='Disposal notifications, before and after approval',
        words='Disposal notifications; better disposal handling and notifications after approval.',
        where='Requests (approve) → the employee’s bell',
        steps=[
            'As Admin, approve the pending disposal request REQ-0002 on Requests.',
            'Sign in as nena@nu-lipa.edu.ph: the bell carries a badge.',
            'Open the bell for “Disposal Request Approved” and its message.',
            'Finalizing the disposal on the form sends the requester a second notice.',
        ],
        say_text='Notifications are per account; the approval itself is also written to the audit trail, so the paper '
                 'trail and the notice agree.',
        figs=[15],
    ),
    dict(
        n=18,
        title='Monthly and yearly summaries',
        words='Reporting for acquired / repaired / disposed assets, monthly and yearly summaries.',
        where='Reports',
        steps=[
            'Set REPORT to Asset Acquisitions, DATE RANGE to This year, and Apply Filters.',
            'Repeat for Repairs and Maintenance, then for Asset Disposals.',
            'Point at the totals and the records, then at Print and Export CSV.',
        ],
        say_text='Each report has the same date presets and the same totals on screen, in the CSV and on the printed '
                 'sheet, so the three summaries cannot tell different stories.',
        figs=[],
    ),
    dict(
        n=19,
        title='Lazy loading where it matters',
        words='Lazy loading where appropriate.',
        where='Employee or department-head asset list · any asset page with photos',
        steps=[
            'Open DevTools (F12) → Network → Img.',
            'Scroll the list: photos are requested as they come into view.',
            'Nothing below the fold is fetched up front.',
        ],
        say_text='The lists draw 10 rows at a time and photos carry loading="lazy", so a long list costs the network only '
                 'what the panel can actually see.',
        figs=[],
    ),
]

CUE_ROWS = [
    ['#', 'The panel asked for', 'Open', 'The one thing to click', 'Fig.'],
    ['1', 'Faster lists of assets and QR codes', 'Assets', 'Registrar → View All; narrow by Location', '2, 3'],
    ['2', 'Monthly filter and sort on requests', 'Requests', 'Period: Last month · Sort: Newest first · Apply', '1'],
    ['3', 'Reports by month, year or date range', 'Reports', 'Report + This year → Apply Filters', '6'],
    ['4', 'CSV export for authorised users', 'Reports / Requests', 'Export CSV · Export Report', '6, 1'],
    ['5', 'Bulk QR printing by location', 'Assets → Registrar → View All', 'Location → Print Labels', '3'],
    ['6', 'Sticker design', 'Label sheet', 'Point at the sticker', '5'],
    ['7', 'Printing settings', 'Label sheet', 'Print settings → QR size → Save settings', '4'],
    ['8', 'Digital disposal form + documents', 'Disposal → Official Disposal Forms', 'Prepare on form #1; print; attach signed copy', '7, 8'],
    ['9', 'Migrate existing asset records', 'Assets → Import Inventory', 'Choose File → Read headers', '9'],
    ['10', 'Bulk employee import', 'Employee Management → Import CSV', 'Download template → Check file', '12'],
    ['11', 'Signed QR authenticity', 'Dashboard → Scanner', 'Start Camera → scan AST-0001', '13'],
    ['12', 'Three-year mark before disposal', 'Disposal → Service-life review', 'Point at the 13 assets and the policy card', '10'],
    ['13', 'Approval required, no editing', 'Requests', 'Green check on a Pending row → confirm', '1'],
    ['14', 'Separate accounts and roles', 'Employee Management', 'Role column; /admin refused for Grace', '11'],
    ['15', 'Query optimisation', 'Same screens as 1', 'Say the numbers — no extra click', '2, 3'],
    ['16', 'Photo required with a request', 'Employee → Submit Request', 'Submit without a photo', '14'],
    ['17', 'Disposal notifications', 'Bell (employee)', 'Approve REQ-0002 → sign in as Nena → bell', '15'],
    ['18', 'Monthly and yearly summaries', 'Reports', 'Three report types with This year', '6'],
    ['19', 'Lazy loading', 'Employee assets list', 'DevTools → Network → Img, then scroll', '3'],
]


def build():
    b = []

    # ---------------------------------------------------------------- cover
    b.append(para([run('NU TRACE', bold=True, size=9, color='BF9A3C')], after=30))
    b.append(para([run('Panel Recommendations', bold=True, size=30, color='0F2143')], after=0))
    b.append(para([run('Demonstration Trigger Guide', bold=True, size=30, color='0F2143')], after=120))
    b.append(para([run('Where each of the 19 panel recommendations lives, what to click to show it, and what to say '
                       'while it is on screen.', size=13, color='5C6474')], after=220))
    b.append(para([run('NU Lipa · Centralized Asset Lifecycle System with QR Technology', size=10, color='1F2937')], after=20))
    b.append(para([run('Capstone Project 2 demonstration · prepared 23 October 2026', size=10, color='1F2937')], after=260))

    b.append(para([run('This is a cue sheet, not a manual. The numbers follow the panel’s revised 19-row form. Every '
                       'screenshot was taken from the running system on its demonstration data set, so the screen in the '
                       'guide is the screen in front of the panel.', size=10.5)], after=200,
                  border_left='BF9A3C'))

    b.append(para([run('Inside', bold=True, size=10, color='0F2143')], after=40))
    for line in [
        'The short version — six lines to hold in your head',
        'The cue sheet: 19 rows, one control each',
        'Before the panel arrives: accounts, start, reseed, checklist',
        'The 19 walkthroughs, with screenshots',
        'The phone, recovery lines, and a timed rehearsal',
    ]:
        b.append(bullet(line))

    b.append(para([run('')], after=0))
    b.append(para([run('A note on the screenshots: they show the demonstration data set, not the live university records. '
                       'Names, codes and counts in the guide are the ones the panel will see if the data is reseeded '
                       'before the demonstration.', italic=True, size=9, color='6B7280')], after=0))
    b.append(page_break())

    # ---------------------------------------------------------------- short version
    b.append(h('The short version', 1))
    b.append(body('Nineteen recommendations are spread over six office screens and one employee screen. If you remember '
                  'only this page, you can still find everything during the demonstration.'))
    b.append(para([run('')], after=40))
    for line in [
        'The demonstration runs at http://localhost:8899 on its own data set: 18 assets, 8 requests, one disposal form '
        'ready to print, 13 assets past the three-year mark.',
        'Three accounts carry the whole story: the Asset Management Office (amo@nu-lipa.edu.ph), an employee '
        '(nena@nu-lipa.edu.ph) and a Department Head (grace@nu-lipa.edu.ph). The password is DemoPass!2026 for all three.',
        'Most of the list lives on five screens: Requests, Assets (and its label sheet), Reports, Disposal, Employee '
        'Management.',
        'Three moments need something in your hand: a printed sticker for the signed-QR check (11), a photo for the '
        'request form (16), and the returned signed form if you want to show the disposal being finalized (8).',
        'Two recommendations have no screen of their own: query optimisation (15) is proved on the screens of 1 and 2, '
        'and lazy loading (19) is proved in the browser’s Network panel.',
        'If time collapses, show 2, 5, 6, 7, 11, 12 and 16 — filters, bulk labels, the sticker, its settings, the '
        'signed scan, the age review and the photo rule. They are the most visible and the quickest to explain.',
    ]:
        b.append(bullet(line))

    b.append(para([run('One sentence to open with', bold=True, size=11, color='0F2143')], before=200, after=60))
    b.append(para([run('“Every recommendation on the panel’s form is already in the system; I will show each one where '
                       'it lives rather than describe it.”', italic=True, size=11)], border_left='BF9A3C', after=120))

    b.append(page_break())

    # ---------------------------------------------------------------- cue sheet
    b.append(h('The cue sheet', 1))
    b.append(body('One row per recommendation. Read the middle column to yourself, open the screen, and click the '
                  'control in the fourth column. The figure numbers point at the pictures later in this guide.'))
    b.append(para([run('')], after=60))
    b.append(table(CUE_ROWS, [560, 2380, 2400, 3518, 780], size=7.6, align_first_center=True))
    b.append(para([run('Rows 1–19 follow the panel’s revised form. Recommendation 12’s second half — whether a younger '
                       'asset may ever be disposed of — is a policy answer, not a screen: give the sentence in the '
                       'walkthrough and say the office has not overridden the mark.', italic=True, size=8.5,
                       color='6B7280')], after=0))
    b.append(page_break())

    # ---------------------------------------------------------------- before you start
    b.append(h('Before the panel arrives', 1))
    b.append(body('Five minutes of preparation removes almost every way the demonstration can stumble.'))
    b.append(para([run('The three accounts', bold=True, size=11, color='0F2143')], before=120, after=60))
    b.append(table([
        ['Role', 'Sign in', 'What it proves'],
        ['Asset Management Office (Admin)', 'amo@nu-lipa.edu.ph\nDemoPass!2026',
         'Every office screen. Stay on this account for most of the walkthrough.'],
        ['Employee', 'nena@nu-lipa.edu.ph\nDemoPass!2026',
         'Submitting a request and the photo rule (16), and her own notifications (17).'],
        ['Department Head', 'grace@nu-lipa.edu.ph\nDemoPass!2026',
         'That /admin is refused to a head who is not in the Asset Management Office (14).'],
    ], [2500, 2900, 4238], size=8.5))

    b.append(para([run('Start the demonstration (only if it is not already running)', bold=True, size=11,
                       color='0F2143')], before=120, after=60))
    b.append(mono_block([
        'cd ~/projects/NUTrace',
        'docker start nutrace-demo          # if the container already exists',
        '# first time only:',
        'docker run -d --name nutrace-demo -p 8899:8080 \\',
        '  -v "$PWD:/var/www/html" nutrace-app:local \\',
        '  php artisan serve --env=demo --host=0.0.0.0 --port=8080',
    ]))
    b.append(body('Then open http://localhost:8899. The demo uses SQLite (database/demo.sqlite) and writes mail to the '
                  'log, so nothing reaches a real inbox and no live record is touched.'))

    b.append(para([run('Reload the data at any time', bold=True, size=11, color='0F2143')], before=140, after=60))
    b.append(mono_block(['docker exec nutrace-demo php .freebuff/demo_seed.php']))
    b.append(body('This returns the numbers used in the screenshots: 18 assets, 8 requests (#REQ-0001 to #REQ-0008), '
                  'form #1 ready to print, 2 assets already in a disposal request, and one unread notification for Nena. '
                  'Run it if a demo action leaves the data in a state you do not want to explain.'))

    b.append(para([run('Checklist, two minutes before', bold=True, size=11, color='0F2143')], before=160, after=60))
    for line in [
        'Signed in as the Asset Management Office; the sidebar shows Dashboard, Assets, Requests, Disposal, Reports.',
        'A printed AST-0001 sticker within reach for the signed-QR check. If the printer is not available, the phone app '
        'can scan the same sticker.',
        'These tabs already open, so no typing happens on stage: Requests · Assets · Registrar’s assets · the label '
        'sheet for AST-0001 · Reports · Disposal (Forms and Service-life review) · Employee Management.',
        'The camera permitted for localhost, if you will use the web Scanner.',
        'This guide printed, or open on a second screen, with the cue sheet facing you.',
    ]:
        b.append(para([run('☐  ', size=11, color='BF9A3C'), run(line)], left=300, hanging=300, after=40))

    b.append(page_break())

    # ---------------------------------------------------------------- walkthroughs
    b.append(h('The 19 walkthroughs', 1))
    b.append(body('Each recommendation gets the words the panel used, the screen it lives on, the clicks that show it, '
                  'the sentence to say, and the figure. Screenshots are from the demonstration data set.'))
    b.append(para([run('')], after=80))

    for rec in RECS:
        b.append(h(f'{rec["n"]} · {rec["title"]}', 2))
        b.append(quote(rec['words']))
        b.append(label_line('Where', rec['where']))
        b.append(para([run('Do this', bold=True, size=9.5, color='0F2143')], before=80, after=40, keep_next=True))
        for i, s in enumerate(rec['steps'], 1):
            b.append(step(i, s))
        b.append(say(rec['say_text']))
        for f in rec['figs']:
            b.append(fig(f))
        b.append(para([run('')], after=20))

    b.append(page_break())

    # ---------------------------------------------------------------- mobile
    b.append(h('If the panel asks about the phone', 1))
    b.append(body('The mobile app is the same system with a camera and no desk. Three of the recommendations are '
                  'stronger on the phone than on the web; the rest are office work.'))
    for line in [
        'Signed QR (11) — the app checks the signature on the device, using the public keys it fetches from '
        '/qr/public-keys, and reports the same verdicts as the web Scanner: Verified, Unsigned, Failed authenticity '
        'check, Unknown signing key, Revoked, Verification unavailable.',
        'Photo required (16) — Submit Request marks the photo required, refuses to submit without it, and refuses if the '
        'upload fails. submitUserRequest rejects a request with no photo a second time on the server.',
        'Requests and notifications (17) — my-requests shows the same statuses the office sets, and the bell carries the '
        'notices for approvals and finalization.',
        'If the venue Wi-Fi is unreliable, demonstrate the same three points on the web screens; nothing is lost except '
        'the phone in hand.',
    ]:
        b.append(bullet(line))

    b.append(h('If something goes wrong', 1))
    recovery = [
        ['The camera will not start',
         'Allow the camera for localhost in the browser’s site settings and press Start Camera again. If it still '
         'refuses, scan the sticker with the phone app; the verdict banner is the same.'],
        ['The scanner says “Verification unavailable”',
         'That server holds no signing key, so the sticker is neither confirmed nor rejected. Say exactly that. The '
         'demonstration server is configured with an Ed25519 key (V1); a deployment needs QR_SIGNING_KEYS set.'],
        ['No invitation email arrived',
         'Nothing is sent in the demo: MAIL_MAILER=log writes every message to the log. Show the transfer review page '
         'instead; the invitation and its history are recorded there.'],
        ['The print dialog shrinks the sticker',
         'Set paper to 50 × 25 mm, margins None, scale 100%, headers and footers off. The printer driver’s media size '
         'must be the same custom size, or Chrome scales the sticker and the code stops scanning.'],
        ['A click left the data in the wrong state',
         'Run the reseed command, reload the page, and carry on. The screenshots will match again.'],
        ['The session expired mid-demo',
         'Sign in again with the same account; the data is untouched by signing out.'],
    ]
    b.append(table([['Symptom', 'What to do']] + recovery, [2700, 6938], size=8.5))

    b.append(h('A timed rehearsal', 1))
    b.append(body('Eight minutes, in the order that keeps the story moving. Tick each block once it has run cleanly.'))
    rehearsal = [
        ['☐', '0:00', 'Sign in as the Asset Management Office; dashboard on screen', '—'],
        ['☐', '0:30', 'Requests: Period Last month, Sort Newest first, Apply; point at the count line; then approve a '
                      'pending row', '2, 13'],
        ['☐', '1:45', 'Assets → Registrar → View All: filters, page count, Print Labels', '1, 5, 19'],
        ['☐', '2:45', 'Label sheet: Printing settings, QR size, Save; close-up of the sticker', '6, 7'],
        ['☐', '3:45', 'Disposal → Forms: five stages, form #1, code legend; then Service-life review', '8, 12'],
        ['☐', '5:00', 'Reports: disposals this year, one more report, Export CSV and Print', '3, 4, 18'],
        ['☐', '5:45', 'Employee Management: roles and the CSV import panel; mention the /admin refusal', '10, 14'],
        ['☐', '6:30', 'Dashboard → Scanner on the printed sticker', '11'],
        ['☐', '7:00', 'Sign in as Nena: Submit Request without a photo, then the bell', '16, 17'],
        ['☐', '7:45', 'Close on performance: paging in SQL, indexes, lazy images', '15, 19'],
    ]
    b.append(table([['', 'Time', 'What happens', 'Recs']] + rehearsal, [400, 700, 7438, 1100], size=8.3))

    b.append(para([run('Closing line', bold=True, size=11, color='0F2143')], before=140, after=60))
    b.append(para([run('“All nineteen recommendations on the panel’s form are implemented; the guide in front of you shows '
                       'where each one lives, and the system is running now if the panel wants to choose the order.”',
                       italic=True, size=11)], border_left='BF9A3C', after=0))

    # ---------------------------------------------------------------- sectPr
    sect = ('<w:sectPr><w:footerReference w:type="default" r:id="rId2"/>'
            '<w:pgSz w:w="11906" w:h="16838"/>'
            '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" '
            'w:header="708" w:footer="708" w:gutter="0"/>'
            '<w:cols w:space="708"/><w:docGrid w:linePitch="360"/></w:sectPr>')

    body_xml = ''.join(b)
    document = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                f'<w:document {NS}><w:body>{body_xml}{sect}</w:body></w:document>')
    return document


STYLES = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="{W}">
<w:docDefaults>
  <w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
    <w:sz w:val="21"/><w:szCs w:val="21"/><w:color w:val="1F2937"/></w:rPr></w:rPrDefault>
  <w:pPrDefault><w:pPr><w:spacing w:after="100" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault>
</w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/>
  <w:pPr><w:spacing w:after="100" w:line="264" w:lineRule="auto"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/>
  <w:pPr><w:spacing w:before="0" w:after="100"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/>
  <w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="320" w:after="100"/>
    <w:outlineLvl w:val="0"/></w:pPr>
  <w:rPr><w:b/><w:color w:val="0F2143"/><w:sz w:val="34"/><w:szCs w:val="34"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/>
  <w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="260" w:after="60"/>
    <w:outlineLvl w:val="1"/></w:pPr>
  <w:rPr><w:b/><w:color w:val="0F2143"/><w:sz w:val="25"/><w:szCs w:val="25"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/>
  <w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="180" w:after="40"/>
    <w:outlineLvl w:val="2"/></w:pPr>
  <w:rPr><w:b/><w:color w:val="3F4C63"/><w:sz w:val="21"/><w:szCs w:val="21"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Meta"><w:name w:val="Meta"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:spacing w:before="40" w:after="60"/></w:pPr>
  <w:rPr><w:color w:val="1F2937"/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:pBdr><w:left w:val="single" w:sz="18" w:space="8" w:color="CDB488"/></w:pBdr>
    <w:spacing w:before="20" w:after="80"/><w:ind w:left="180"/></w:pPr>
  <w:rPr><w:i/><w:color w:val="5C6474"/><w:sz w:val="19"/><w:szCs w:val="19"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Say"><w:name w:val="Say"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:pBdr><w:left w:val="single" w:sz="18" w:space="8" w:color="BF9A3C"/></w:pBdr>
    <w:shd w:val="clear" w:color="auto" w:fill="FBF6EC"/>
    <w:spacing w:before="80" w:after="120"/><w:ind w:left="160"/></w:pPr>
  <w:rPr><w:i/><w:color w:val="3B2F1B"/><w:sz w:val="19"/><w:szCs w:val="19"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Mono"><w:name w:val="Mono"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:shd w:val="clear" w:color="auto" w:fill="F2F0EA"/><w:ind w:left="160"/></w:pPr>
  <w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>
    <w:sz w:val="17"/><w:szCs w:val="17"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>
  <w:rPr><w:sz w:val="17"/><w:szCs w:val="17"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Figure"><w:name w:val="Figure"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:keepNext/><w:spacing w:before="120" w:after="40"/></w:pPr></w:style>
<w:style w:type="paragraph" w:styleId="Footer"><w:name w:val="footer"/><w:basedOn w:val="Normal"/>
  <w:pPr><w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs>
    <w:spacing w:before="0" w:after="0"/><w:jc w:val="left"/></w:pPr>
  <w:rPr><w:color w:val="8A8F99"/><w:sz w:val="15"/><w:szCs w:val="15"/></w:rPr></w:style>
</w:styles>'''

FOOTER = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="{W}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<w:p><w:pPr><w:pStyle w:val="Footer"/></w:pPr>
<w:r><w:t xml:space="preserve">NU Trace · Panel Recommendations — Demonstration Trigger Guide</w:t></w:r>
<w:r><w:tab/></w:r>
<w:r><w:t xml:space="preserve">page </w:t></w:r>
<w:r><w:fldChar w:fldCharType="begin"/></w:r>
<w:r><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>
<w:r><w:fldChar w:fldCharType="separate"/></w:r>
<w:r><w:t>1</w:t></w:r>
<w:r><w:fldChar w:fldCharType="end"/></w:r>
<w:r><w:t xml:space="preserve"> of </w:t></w:r>
<w:r><w:fldChar w:fldCharType="begin"/></w:r>
<w:r><w:instrText xml:space="preserve"> NUMPAGES </w:instrText></w:r>
<w:r><w:fldChar w:fldCharType="separate"/></w:r>
<w:r><w:t>1</w:t></w:r>
<w:r><w:fldChar w:fldCharType="end"/></w:r>
</w:p></w:ftr>'''

CONTENT_TYPES = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Default Extension="png" ContentType="image/png"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>'''

ROOT_RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>'''

CORE = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
 xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:title>Panel Recommendations — Demonstration Trigger Guide</dc:title>
<dc:subject>NU Trace · Capstone Project 2 demonstration</dc:subject>
<dc:creator>NU Trace</dc:creator>
<cp:keywords>panel recommendations; demonstration; NU Trace</cp:keywords>
<dcterms:created xsi:type="dcterms:W3CDTF">%(stamp)s</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">%(stamp)s</dcterms:modified>
</cp:coreProperties>'''

APP = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<Application>NU Trace</Application>
</Properties>'''


def main():
    document = build()
    os.makedirs(OUT_DIR, exist_ok=True)

    rels = ['<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>']
    for rid, filename in FIGS.rels:
        rels.append(f'<Relationship Id="{rid}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                    f'Target="media/{filename}"/>')
    doc_rels = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                + ''.join(rels) + '</Relationships>')

    stamp = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')

    with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('[Content_Types].xml', CONTENT_TYPES)
        z.writestr('_rels/.rels', ROOT_RELS)
        z.writestr('word/document.xml', document)
        z.writestr('word/styles.xml', STYLES)
        z.writestr('word/footer1.xml', FOOTER)
        z.writestr('word/_rels/document.xml.rels', doc_rels)
        z.writestr('docProps/core.xml', CORE % {'stamp': stamp})
        z.writestr('docProps/app.xml', APP)
        for _, filename in FIGS.rels:
            z.write(os.path.join(SHOTS, filename), f'word/media/{filename}')

    print('written:', OUT, os.path.getsize(OUT), 'bytes;', len(FIGS.rels), 'figures')


if __name__ == '__main__':
    main()
