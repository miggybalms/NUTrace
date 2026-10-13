"""Key the outer background out of the supplied NU seal and crop it.

The artwork the school handed over is RGB with no alpha channel: the shield is
flattened onto a solid white card. This makes the outer background transparent
by flood-filling inwards from the border only — never by deleting white
everywhere — so the shield's own white inner ring and the white "1900" stay
exactly as they are. It then crops to the artwork and writes an RGBA PNG.

Pure standard library: no PIL, no GD, no ImageMagick in this environment.
"""

from __future__ import annotations

import struct
import sys
import zlib

# A border pixel counts as background when every channel is this light or
# lighter. The supplied card is pure #FFFFFF, so this is generous but safe.
BACKGROUND_MIN = 240

# Pixels left over from anti-aliasing between the card and the shield are
# softened, so no white fringe is left around the artwork. Only pixels touching
# the background are touched, and they must be light to qualify, which is why
# the navy outline (min channel ~50) is never affected.
FEATHER_MIN = 205

# Transparent margin kept around the cropped artwork, in pixels.
MARGIN = 6


def decode(path: str):
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
    pixels = bytearray(width * height * 4)
    prev = bytearray(stride)
    ptr = 0

    for y in range(height):
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

        for x in range(width):
            src = x * channels
            dst = (y * width + x) * 4
            pixels[dst] = line[src]
            pixels[dst + 1] = line[src + 1]
            pixels[dst + 2] = line[src + 2]
            pixels[dst + 3] = line[src + 3] if channels == 4 else 255

        prev = line

    return width, height, pixels


def encode(path: str, width: int, height: int, pixels: bytes) -> None:
    raw = bytearray()

    for y in range(height):
        raw.append(0)  # filter type: none
        raw += pixels[y * width * 4 : (y + 1) * width * 4]

    def chunk(tag: bytes, payload: bytes) -> bytes:
        return (
            struct.pack(">I", len(payload))
            + tag
            + payload
            + struct.pack(">I", zlib.crc32(tag + payload) & 0xFFFFFFFF)
        )

    with open(path, "wb") as fh:
        fh.write(b"\x89PNG\r\n\x1a\n")
        fh.write(chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0)))
        fh.write(chunk(b"IDAT", zlib.compress(bytes(raw), 9)))
        fh.write(chunk(b"IEND", b""))


def main() -> None:
    src = sys.argv[1]
    dst = sys.argv[2]

    width, height, px = decode(src)

    def is_light(x: int, y: int, minimum: int) -> bool:
        i = (y * width + x) * 4
        return px[i] >= minimum and px[i + 1] >= minimum and px[i + 2] >= minimum

    # Flood fill the background, starting only from the border.
    transparent = bytearray(width * height)
    stack = []

    for x in range(width):
        for y in (0, height - 1):
            if not transparent[y * width + x] and is_light(x, y, BACKGROUND_MIN):
                transparent[y * width + x] = 1
                stack.append((x, y))

    for y in range(height):
        for x in (0, width - 1):
            if not transparent[y * width + x] and is_light(x, y, BACKGROUND_MIN):
                transparent[y * width + x] = 1
                stack.append((x, y))

    while stack:
        x, y = stack.pop()
        for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
            if 0 <= nx < width and 0 <= ny < height and not transparent[ny * width + nx]:
                if is_light(nx, ny, BACKGROUND_MIN):
                    transparent[ny * width + nx] = 1
                    stack.append((nx, ny))

    for y in range(height):
        for x in range(width):
            if transparent[y * width + x]:
                px[(y * width + x) * 4 + 3] = 0

    # Soften the anti-aliased edge the card left behind.
    fringe = []
    for y in range(height):
        for x in range(width):
            if transparent[y * width + x]:
                continue
            touching = (
                (x and transparent[y * width + x - 1])
                or (x < width - 1 and transparent[y * width + x + 1])
                or (y and transparent[(y - 1) * width + x])
                or (y < height - 1 and transparent[(y + 1) * width + x])
            )
            if not touching:
                continue
            i = (y * width + x) * 4
            lightest = min(px[i], px[i + 1], px[i + 2])
            if lightest >= FEATHER_MIN:
                px[i + 3] = max(0, min(255, int((255 - lightest) * 255 / (255 - FEATHER_MIN))))

    # Crop to the artwork (plus a transparent margin) so it fills its slot.
    xs = [x for x in range(width) for y in range(height) if px[(y * width + x) * 4 + 3] > 8]
    ys = [y for y in range(height) for x in range(width) if px[(y * width + x) * 4 + 3] > 8]

    x0, x1 = max(0, min(xs) - MARGIN), min(width - 1, max(xs) + MARGIN)
    y0, y1 = max(0, min(ys) - MARGIN), min(height - 1, max(ys) + MARGIN)
    crop_w, crop_h = x1 - x0 + 1, y1 - y0 + 1

    cropped = bytearray(crop_w * crop_h * 4)
    for y in range(crop_h):
        src_row = ((y0 + y) * width + x0) * 4
        cropped[y * crop_w * 4 : (y + 1) * crop_w * 4] = px[src_row : src_row + crop_w * 4]

    encode(dst, crop_w, crop_h, bytes(cropped))

    # Report what survived: interior white (the ring and the "1900") must still
    # be there, and the corners must not be.
    interior_white = 0
    for y in range(crop_h):
        for x in range(crop_w):
            i = (y * crop_w + x) * 4
            if cropped[i + 3] > 200 and min(cropped[i], cropped[i + 1], cropped[i + 2]) >= 230:
                interior_white += 1

    corners = [cropped[3], cropped[(crop_w - 1) * 4 + 3], cropped[(crop_h - 1) * crop_w * 4 + 3], cropped[-1]]

    print(f"source: {width}x{height} RGB -> {crop_w}x{crop_h} RGBA ({crop_w / crop_h:.3f} w/h)")
    print(f"background pixels keyed out: {sum(transparent)}")
    print(f"interior white kept (ring + 1900): {interior_white}")
    print(f"corner alphas (want 0): {corners}")


if __name__ == "__main__":
    main()
