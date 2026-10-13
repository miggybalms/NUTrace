"""Report a PNG's size, real transparency and dominant opaque colours.

Pure-stdlib (no PIL / no GD): decodes the PNG, unfilters every scanline and
samples pixels. Used to answer one question for the printed-asset-sticker work:
is the bundled brand mark actually transparent, and what colours does it use?
"""

from __future__ import annotations

import struct
import sys
import zlib
from collections import Counter


def read_png(path: str):
    with open(path, "rb") as fh:
        data = fh.read()

    if data[:8] != b"\x89PNG\r\n\x1a\n":
        raise SystemExit("not a PNG")

    pos = 8
    idat = bytearray()
    width = height = depth = color_type = None

    while pos < len(data):
        (length,) = struct.unpack(">I", data[pos : pos + 4])
        ctype = data[pos + 4 : pos + 8]
        chunk = data[pos + 8 : pos + 8 + length]
        pos += 12 + length

        if ctype == b"IHDR":
            width, height, depth, color_type = struct.unpack(">IIBB", chunk[:10])
        elif ctype == b"IDAT":
            idat += chunk
        elif ctype == b"IEND":
            break

    if depth != 8 or color_type not in (2, 6):
        raise SystemExit(f"unsupported PNG: depth={depth} color_type={color_type}")

    channels = 3 if color_type == 2 else 4
    raw = zlib.decompress(bytes(idat))
    stride = width * channels
    rows = []
    prev = bytearray(stride)
    ptr = 0

    for _ in range(height):
        ftype = raw[ptr]
        ptr += 1
        line = bytearray(raw[ptr : ptr + stride])
        ptr += stride

        for i in range(stride):
            a = line[i - channels] if i >= channels else 0
            b = prev[i]
            c = prev[i - channels] if i >= channels else 0
            x = line[i]

            if ftype == 1:
                x = (x + a) & 0xFF
            elif ftype == 2:
                x = (x + b) & 0xFF
            elif ftype == 3:
                x = (x + ((a + b) >> 1)) & 0xFF
            elif ftype == 4:
                p = a + b - c
                pa, pb, pc = abs(p - a), abs(p - b), abs(p - c)
                pr = a if (pa <= pb and pa <= pc) else (b if pb <= pc else c)
                x = (x + pr) & 0xFF

            line[i] = x

        rows.append(line)
        prev = line

    return width, height, channels, rows


def main() -> None:
    path = sys.argv[1] if len(sys.argv) > 1 else "public/images/logo-mark.png"
    width, height, channels, rows = read_png(path)

    transparent = opaque = 0
    colors: Counter[tuple[int, int, int]] = Counter()

    for y in range(0, height, 2):
        row = rows[y]
        for x in range(0, width, 2):
            i = x * channels
            r, g, b = row[i], row[i + 1], row[i + 2]
            alpha = row[i + 3] if channels == 4 else 255

            if alpha < 40:
                transparent += 1
                continue

            opaque += 1
            if alpha > 200:
                colors[(r, g, b)] += 1

    print(f"size: {width}x{height}  channels: {channels} (alpha channel: {channels == 4})")
    print(f"sampled fully-transparent: {transparent}  visible: {opaque}")
    print("top opaque colours:", [(f"#{r:02X}{g:02X}{b:02X}", n) for (r, g, b), n in colors.most_common(6)])


if __name__ == "__main__":
    main()
