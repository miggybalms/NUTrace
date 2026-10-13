import os

BASE = '/mnt/c/capstone/assetapp_mobile/assetapp'

TARGETS = [
    ('components/asset-scanner.tsx', 33),   # extractAssetKey
    ('components/asset-scanner.tsx', 52),   # findAssetByScan
    ('components/asset-scanner.tsx', 120),  # handleScanned
    ('components/QRViewModal.tsx', 26),     # QRViewModal (component)
    ('lib/assetCode.ts', 35),               # buildAssetCode
    ('lib/assetCode.ts', 68),               # parseAssetCodeList
    ('lib/assetCode.ts', 81),               # buildSequentialCodes
    ('lib/assetCode.ts', 96),               # findDuplicateCodes
    ('lib/assetService.ts', 527),           # registerAsset
    ('lib/assetService.ts', 637),           # findExistingAssetCodes
    ('lib/assetService.ts', 478),           # fetchAuditLogsPage
    ('lib/lifecycle.ts', 29),               # matchUserVisibleStatus
    ('lib/lifecycle.ts', 59),               # isRequestableAsset
    ('lib/lifecycle.ts', 88),               # canCompleteMaintenanceStatus
    ('lib/maintenanceService.ts', 295),     # recordMaintenance
    ('lib/maintenanceService.ts', 436),     # runAssetEvaluation
    ('lib/maintenanceService.ts', 666),     # extendAssetLifespan
    ('lib/repairService.ts', 612),          # submitRepairRequest
    ('lib/repairService.ts', 785),          # updateRepairStatus
    ('lib/repairService.ts', 1128),         # sendRepairToReplacement
    ('lib/assetService.ts', 1068),          # createAndLinkReplacementAsset
    ('lib/disposalService.ts', 391),        # archiveDisposal
    ('lib/disposalService.ts', 566),        # initiateDisposal
    ('lib/pulloutService.ts', 77),          # submitPulloutRequest
    ('lib/transferService.ts', 318),        # executeEmployeeTransfer
    ('lib/auditService.ts', 48),            # stripNetworkIdentifiers
    ('lib/auditService.ts', 95),            # writeSessionAudit
    ('lib/auditService.ts', 145),           # writeAudit
    ('lib/userService.ts', 937),            # isEmployeeEligible
    ('lib/userService.ts', 970),            # lookupEmployeeByNumber
    ('lib/userService.ts', 1008),           # isEmployeeNumberRegistered
    ('lib/userService.ts', 1103),           # registerUser
    ('lib/passwordHash.ts', 20),            # hashPasswordForDatabase
]


def find_body_range(lines, start_index):
    """Return the line index of the function body's closing brace.

    The body opens at the first `{` reached while every paren / bracket / brace
    opened so far is already closed — that skips destructured parameters and
    inline type literals, which sit inside parentheses.
    """
    paren = bracket = depth = 0
    body_open_line = None
    in_block_comment = False
    for i in range(start_index, len(lines)):
        line = lines[i]
        j = 0
        while j < len(line):
            ch = line[j]
            nxt = line[j + 1] if j + 1 < len(line) else ''
            if in_block_comment:
                end = line.find('*/', j)
                if end == -1:
                    break
                in_block_comment = False
                j = end + 2
                continue
            if ch == '/' and nxt == '/':
                break
            if ch == '/' and nxt == '*':
                in_block_comment = True
                j += 2
                continue
            if ch in "'\"`":
                quote = ch
                j += 1
                while j < len(line):
                    if line[j] == '\\':
                        j += 2
                        continue
                    if line[j] == quote:
                        break
                    j += 1
                j += 1
                continue
            if ch == '(':
                paren += 1
            elif ch == ')':
                paren -= 1
            elif ch == '[':
                bracket += 1
            elif ch == ']':
                bracket -= 1
            elif ch == '{':
                if (
                    body_open_line is None
                    and paren == 0
                    and bracket == 0
                    and depth == 0
                    and line[j + 1:].strip() == ''
                    and not line[:j].rstrip().endswith('<')
                ):
                    body_open_line = i
                depth += 1
            elif ch == '}':
                depth -= 1
                if body_open_line is not None and depth == 0:
                    return i
            j += 1
    return len(lines) - 1


seen = {}
for path, start in TARGETS:
    lines = open(os.path.join(BASE, path), encoding='utf-8', errors='replace').read().replace('\r\n', '\n').split('\n')
    begin = start
    for i in range(start - 2, max(0, start - 40), -1):
        s = lines[i].strip()
        if s.startswith('/**'):
            begin = i + 1
            break
        if s.startswith('export') or s.startswith('function') or s.startswith('const ') or s.startswith('interface'):
            break
    end = find_body_range(lines, start - 1)
    body = lines[begin - 1:end + 1]
    while body and body[-1].strip() == '':
        body.pop()
    key = (path, begin, begin + len(body) - 1)
    print('== %-46s %4d-%-4d (%3d lines)' % (path, key[1], key[2], len(body)))
    print('   S| ' + body[0][:96])
    print('   E| ' + body[-1][:96])
    seen.setdefault(path, []).append(key[1:])
print()
print('--- python list ---')
for path, ranges in seen.items():
    print(path, ranges)
