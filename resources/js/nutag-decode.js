/**
 * NUTAG-1 reader - PROTOTYPE.
 *
 * The sticker is not a QR code: it is a black frame, a 3x3 orientation marker
 * and a grid of cells carrying a token and a short MAC (see
 * App\Support\NutTag). A QR app or a scanning website therefore finds nothing
 * at all, which is the point of the format.
 *
 * This reader takes raw RGBA pixels - a canvas `getImageData()` in the browser,
 * or a synthetic frame in the test harness - finds the frame, corrects for
 * rotation and perspective, samples the 25 x 25 cells, and returns the token
 * and MAC of both copies. It does not check the MAC: that needs the key, and
 * the server (POST /qr/resolve) is where the decision belongs.
 *
 * Kept dependency-free on purpose: the phone and the browser can both include
 * it as it is, and the prototype can be exercised under Node against generated
 * images before anything in production is switched over.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.NutTag = factory();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var GRID = 25;
    var QUIET = 2;
    var PAYLOAD_BITS = 192;
    var TOKEN_BYTES = 16;
    var MAC_BYTES = 8;

    var MARKER_ZONE_START = 1;
    var MARKER_ZONE_SIZE = 5;
    var MARKER_CORE_START = 2;
    var MARKER_CORE_SIZE = 3;

    function inMarkerZone(r, c) {
        return r >= MARKER_ZONE_START && r < MARKER_ZONE_START + MARKER_ZONE_SIZE
            && c >= MARKER_ZONE_START && c < MARKER_ZONE_START + MARKER_ZONE_SIZE;
    }

    /** The same cell order the encoder writes, so the bits line up. */
    var POSITIONS = (function () {
        var positions = [];

        for (var r = 1; r < GRID - 1; r++) {
            for (var c = 1; c < GRID - 1; c++) {
                if (inMarkerZone(r, c)) continue;
                positions.push([r, c]);
            }
        }

        return positions;
    })();

    /* ── sampling ─────────────────────────────────────────────────────────── */

    function grayscale(image) {
        var data = image.data;
        var width = image.width;
        var height = image.height;
        var gray = new Uint8Array(width * height);

        for (var i = 0, p = 0; i < gray.length; i++, p += 4) {
            gray[i] = (data[p] * 0.299 + data[p + 1] * 0.587 + data[p + 2] * 0.114) | 0;
        }

        return gray;
    }

    /** Otsu: the threshold that best splits the histogram in two. */
    function otsu(gray) {
        var histogram = new Array(256).fill(0);

        for (var i = 0; i < gray.length; i++) histogram[gray[i]]++;

        var total = gray.length;
        var sum = 0;

        for (var t = 0; t < 256; t++) sum += t * histogram[t];

        var sumB = 0;
        var weightB = 0;
        var best = 0;
        var threshold = 128;

        for (var level = 0; level < 256; level++) {
            weightB += histogram[level];
            if (weightB === 0) continue;

            var weightF = total - weightB;
            if (weightF === 0) break;

            sumB += level * histogram[level];

            var meanB = sumB / weightB;
            var meanF = (sum - sumB) / weightF;
            var between = weightB * weightF * (meanB - meanF) * (meanB - meanF);

            if (between > best) {
                best = between;
                threshold = level;
            }
        }

        return threshold;
    }

    /**
     * The largest connected dark blob. The frame ring is one piece and much
     * bigger than any speck of noise, so its extremes are the tag's corners.
     */
    function largestBlob(dark, width, height) {
        var seen = new Uint8Array(width * height);
        var best = null;
        var stack = [];

        for (var start = 0; start < dark.length; start++) {
            if (!dark[start] || seen[start]) continue;

            var count = 0;
            var extremes = {
                tl: [0, 0, Infinity],   // smallest x + y
                tr: [0, 0, -Infinity],  // largest x - y
                br: [0, 0, -Infinity],  // largest x + y
                bl: [0, 0, Infinity],   // smallest x - y
            };

            stack.length = 0;
            stack.push(start);
            seen[start] = 1;

            while (stack.length) {
                var index = stack.pop();
                var x = index % width;
                var y = (index / width) | 0;

                count++;

                var sum = x + y;
                var diff = x - y;

                if (sum < extremes.tl[2]) extremes.tl = [x, y, sum];
                if (sum > extremes.br[2]) extremes.br = [x, y, sum];
                if (diff > extremes.tr[2]) extremes.tr = [x, y, diff];
                if (diff < extremes.bl[2]) extremes.bl = [x, y, diff];

                var neighbours = [
                    x > 0 ? index - 1 : -1,
                    x < width - 1 ? index + 1 : -1,
                    y > 0 ? index - width : -1,
                    y < height - 1 ? index + width : -1,
                ];

                for (var n = 0; n < 4; n++) {
                    var next = neighbours[n];

                    if (next >= 0 && dark[next] && !seen[next]) {
                        seen[next] = 1;
                        stack.push(next);
                    }
                }
            }

            if (!best || count > best.count) {
                best = { count: count, extremes: extremes };
            }
        }

        return best;
    }

    /**
     * Projective mapping from the unit square to a quad, then back for each
     * sample. Order: top-left, top-right, bottom-right, bottom-left.
     */
    function squareToQuad(quad) {
        var x0 = quad[0][0], y0 = quad[0][1];
        var x1 = quad[1][0], y1 = quad[1][1];
        var x2 = quad[2][0], y2 = quad[2][1];
        var x3 = quad[3][0], y3 = quad[3][1];

        var dx1 = x1 - x2, dx2 = x3 - x2, dx3 = x0 - x1 + x2 - x3;
        var dy1 = y1 - y2, dy2 = y3 - y2, dy3 = y0 - y1 + y2 - y3;

        var g, h;

        if (Math.abs(dx3) < 1e-9 && Math.abs(dy3) < 1e-9) {
            g = 0;
            h = 0;
        } else {
            var denominator = dx1 * dy2 - dx2 * dy1;

            if (Math.abs(denominator) < 1e-9) return null;

            g = (dx3 * dy2 - dx2 * dy3) / denominator;
            h = (dx1 * dy3 - dx3 * dy1) / denominator;
        }

        return {
            a: x1 - x0 + g * x1,
            b: x3 - x0 + h * x3,
            c: x0,
            d: y1 - y0 + g * y1,
            e: y3 - y0 + h * y3,
            f: y0,
            g: g,
            h: h,
        };
    }

    function apply(transform, u, v) {
        var denominator = transform.g * u + transform.h * v + 1;

        return [
            (transform.a * u + transform.b * v + transform.c) / denominator,
            (transform.d * u + transform.e * v + transform.f) / denominator,
        ];
    }

    /**
     * Sample the 25 x 25 cells. Each cell is read from a 3 x 3 vote of
     * sub-pixels, so one smeared dot does not flip a bit.
     */
    function sampleCells(dark, width, height, quad) {
        var transform = squareToQuad(quad);

        if (!transform) return null;

        var cells = [];
        var offsets = [-0.28, 0, 0.28];

        for (var r = 0; r < GRID; r++) {
            var row = [];

            for (var c = 0; c < GRID; c++) {
                var votes = 0;
                var samples = 0;

                for (var i = 0; i < offsets.length; i++) {
                    for (var j = 0; j < offsets.length; j++) {
                        var u = (c + 0.5 + offsets[j]) / GRID;
                        var v = (r + 0.5 + offsets[i]) / GRID;
                        var point = apply(transform, u, v);
                        var x = Math.round(point[0]);
                        var y = Math.round(point[1]);

                        if (x < 0 || y < 0 || x >= width || y >= height) continue;

                        samples++;
                        if (dark[y * width + x]) votes++;
                    }
                }

                row.push(samples > 0 && votes * 2 > samples);
            }

            cells.push(row);
        }

        return cells;
    }

    /* ── reading a sampled matrix ─────────────────────────────────────────── */

    function frameMatches(cells) {
        var last = GRID - 1;

        for (var i = 0; i < GRID; i++) {
            if (!cells[0][i] || !cells[last][i] || !cells[i][0] || !cells[i][last]) return false;
        }

        return true;
    }

    function orientationMatches(cells) {
        for (var r = MARKER_ZONE_START; r < MARKER_ZONE_START + MARKER_ZONE_SIZE; r++) {
            for (var c = MARKER_ZONE_START; c < MARKER_ZONE_START + MARKER_ZONE_SIZE; c++) {
                var core = r >= MARKER_CORE_START && r < MARKER_CORE_START + MARKER_CORE_SIZE
                    && c >= MARKER_CORE_START && c < MARKER_CORE_START + MARKER_CORE_SIZE;

                if (cells[r][c] !== core) return false;
            }
        }

        return true;
    }

    function rotate(cells, turns) {
        var result = cells;

        for (var t = 0; t < ((turns % 4) + 4) % 4; t++) {
            var size = result.length;
            var next = [];

            for (var r = 0; r < size; r++) {
                next.push(new Array(size));
                for (var c = 0; c < size; c++) {
                    next[r][c] = result[size - 1 - c][r];
                }
            }

            result = next;
        }

        return result;
    }

    function bytesOf(cells, offset) {
        var bytes = new Uint8Array(TOKEN_BYTES + MAC_BYTES);
        var bit = 0;
        var value = 0;

        for (var i = 0; i < PAYLOAD_BITS; i++) {
            var position = POSITIONS[offset + i];

            if (!position) return null;

            value = (value << 1) | (cells[position[0]][position[1]] ? 1 : 0);

            if (++bit === 8) {
                bytes[i >> 3] = value;
                value = 0;
                bit = 0;
            }
        }

        return bytes;
    }

    /**
     * Read an already-sampled 25 x 25 matrix (true = dark).
     *
     * @returns {{ok: true, token: Uint8Array, mac: Uint8Array, copies: Array}|{ok: false, reason: string}}
     */
    function decodeMatrix(input) {
        if (!input || input.length !== GRID) return { ok: false, reason: 'wrong-size' };

        for (var i = 0; i < GRID; i++) {
            if (!input[i] || input[i].length !== GRID) return { ok: false, reason: 'wrong-size' };
        }

        for (var turns = 0; turns < 4; turns++) {
            var cells = rotate(input, turns);

            if (!frameMatches(cells) || !orientationMatches(cells)) continue;

            var first = bytesOf(cells, 0);
            var second = bytesOf(cells, PAYLOAD_BITS);

            if (!first || !second) return { ok: false, reason: 'truncated' };

            return {
                ok: true,
                token: first.slice(0, TOKEN_BYTES),
                mac: first.slice(TOKEN_BYTES),
                copies: [first, second],
                matrix: cells,
            };
        }

        return { ok: false, reason: 'no-frame' };
    }

    /**
     * Read a frame of RGBA pixels.
     *
     * @param {{data: Uint8ClampedArray|Array, width: number, height: number}} image
     * @param {{threshold?: number}} [options]
     */
    function decodeImage(image, options) {
        var width = image.width;
        var height = image.height;

        if (!width || !height || !image.data) return { ok: false, reason: 'no-image' };

        var gray = grayscale(image);
        var threshold = (options && typeof options.threshold === 'number') ? options.threshold : otsu(gray);
        var dark = new Uint8Array(gray.length);

        var darkCount = 0;

        for (var i = 0; i < gray.length; i++) {
            // `<=`, not `<`: on a clean black-and-white frame Otsu's best split
            // is 0, and a strict comparison would then find no ink at all.
            if (gray[i] <= threshold) {
                dark[i] = 1;
                darkCount++;
            }
        }

        if (darkCount < 200) return { ok: false, reason: 'too-little-ink' };

        var blob = largestBlob(dark, width, height);

        if (!blob) return { ok: false, reason: 'no-frame' };

        // Frame corners, in the order the projective mapping expects. Each is
        // the ring pixel furthest along one diagonal, which still finds the
        // corners when the tag is rotated (a bounding box would not).
        var quad = [
            [blob.extremes.tl[0], blob.extremes.tl[1]],
            [blob.extremes.tr[0], blob.extremes.tr[1]],
            [blob.extremes.br[0], blob.extremes.br[1]],
            [blob.extremes.bl[0], blob.extremes.bl[1]],
        ];

        var cells = sampleCells(dark, width, height, quad);

        if (!cells) return { ok: false, reason: 'no-frame' };

        return decodeMatrix(cells);
    }

    return {
        version: 1,
        grid: GRID,
        quiet: QUIET,
        tokenBytes: TOKEN_BYTES,
        macBytes: MAC_BYTES,
        positions: POSITIONS,
        decodeMatrix: decodeMatrix,
        decodeImage: decodeImage,
        _internal: { otsu: otsu, squareToQuad: squareToQuad, largestBlob: largestBlob },
    };
});
