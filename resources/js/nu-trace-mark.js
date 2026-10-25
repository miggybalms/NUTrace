/**
 * The NU Trace seal: the official logo in the middle of every asset QR code,
 * and the reader's check that it is actually there.
 *
 * Two jobs, and no decision about whether a sticker is genuine:
 *
 *   · draw - paint a sticker's QR with the mark in its centre, so the code on
 *     screen, in a download and on a printed label are the same artwork;
 *   · check - confirm that a camera frame whose QR has just been decoded has
 *     the mark in the middle, and say so in words the office can act on.
 *
 * The mark is a visual marker, not proof of authenticity: a photograph or a
 * photocopy of a genuine sticker carries it too. What makes a code trustworthy
 * is still the server: the payload must be signed with a key that never leaves
 * the backend, its asset must be registered, and its sticker must not be
 * revoked. This file only refuses codes that were not made by the app.
 *
 * Plain ES5 on purpose: the scanners also run on the office's older tablets.
 */
(function (root, factory) {
    'use strict';

    var api = factory();

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.NUTraceQrMark = api;
    }
})(typeof self !== 'undefined' ? self : (typeof window !== 'undefined' ? window : this), function () {
    'use strict';

    var INVALID = 'Invalid or Unrecognized QR Code. The required NU Trace logo could not be verified.';
    var RETRY = 'Unable to read QR code. Please try scanning again.';

    var DEFAULTS = {
        // Served from public/images: same origin, so a canvas that has the logo
        // painted onto it can still be read back (a download) and is never
        // tainted by a cross-origin image.
        logo: '/images/nu-trace-mark.jpg',

        // The logo plate, as a fraction of the code's edge. 0.22 leaves the
        // three finder patterns untouched and covers roughly 5% of the symbol -
        // inside the ~15% a level-M code can lose and still decode.
        ratio: 0.22,

        // The plate's own colour, matching the logo artwork's background.
        plate: '#0A1830',

        // Refuse a QR code whose mark cannot be confirmed. Turned off by
        // QR_REQUIRE_SEAL=false on the server while stickers printed before the
        // mark are still in circulation - never silently, see config/qr.php.
        requireSeal: true
    };

    var logoImage = null;
    var waitingForLogo = [];

    function config() {
        var given = (typeof window !== 'undefined' && window.NUTraceQrMarkConfig) || {};
        var out = {};
        var key;

        for (key in DEFAULTS) {
            if (Object.prototype.hasOwnProperty.call(DEFAULTS, key)) {
                out[key] = DEFAULTS[key];
            }
        }

        for (key in given) {
            if (Object.prototype.hasOwnProperty.call(given, key) && given[key] !== null && given[key] !== undefined) {
                out[key] = given[key];
            }
        }

        return out;
    }

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function isTag(value) {
        return /^NUTAG1-/i.test(text(value));
    }

    function isSigned(value) {
        return /^NUT[0-9]{1,2}[-:]/i.test(text(value));
    }

    /** The official logo, loaded once per page. */
    function mark() {
        if (logoImage) {
            return logoImage;
        }

        if (typeof Image === 'undefined') {
            return null;
        }

        logoImage = new Image();
        logoImage.decoding = 'async';
        logoImage.src = config().logo;
        logoImage.addEventListener('load', function () {
            var queued = waitingForLogo;
            waitingForLogo = [];
            queued.forEach(function (paint) {
                try { paint(); } catch (error) { /* a stale canvas is not worth a page error */ }
            });
        });

        return logoImage;
    }

    function loaded(image) {
        return !!(image && image.complete && image.naturalWidth > 0);
    }

    function roundedRect(ctx, x, y, width, height, radius) {
        var r = Math.max(0, Math.min(radius, Math.min(width, height) / 2));
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + width, y, x + width, y + height, r);
        ctx.arcTo(x + width, y + height, x, y + height, r);
        ctx.arcTo(x, y + height, x, y, r);
        ctx.arcTo(x, y, x + width, y, r);
        ctx.closePath();
    }

    function contain(ctx, image, x, y, width, height) {
        var scale = Math.min(width / image.naturalWidth, height / image.naturalHeight);
        var w = image.naturalWidth * scale;
        var h = image.naturalHeight * scale;

        ctx.drawImage(image, x + (width - w) / 2, y + (height - h) / 2, w, h);
    }

    /**
     * Paint the mark over a QR canvas: a white ring, then the logo's own navy
     * plate with the artwork letterboxed inside it - never stretched, never
     * cropped.
     *
     * A QR library draws its modules across the whole canvas, so the mark is
     * painted on top of them. It is repeated here rather than built into the
     * module loop, so a canvas that was drawn before the logo finished loading
     * is completed the moment it arrives.
     */
    function paintSeal(canvas) {
        var image = mark();

        if (!canvas || !canvas.getContext || !canvas.width || !canvas.height) {
            return false;
        }

        var ctx = canvas.getContext('2d');
        var side = Math.min(canvas.width, canvas.height);
        var plate = Math.round(side * Number(config().ratio || DEFAULTS.ratio));
        var ring = Math.max(2, Math.round(plate * 0.11));
        var x = Math.round((canvas.width - plate) / 2);
        var y = Math.round((canvas.height - plate) / 2);

        ctx.save();

        // The ring keeps modules a module's width away from the artwork, so a
        // camera never sees navy touching black.
        ctx.fillStyle = '#ffffff';
        roundedRect(ctx, x - ring, y - ring, plate + ring * 2, plate + ring * 2, Math.round(plate * 0.14));
        ctx.fill();

        ctx.fillStyle = config().plate || DEFAULTS.plate;
        roundedRect(ctx, x, y, plate, plate, Math.round(plate * 0.10));
        ctx.fill();

        if (loaded(image)) {
            contain(ctx, image, x, y, plate, plate);
        }

        ctx.restore();
        canvas.dataset.nutraceSeal = '1';

        return true;
    }

    /** Paint the seal, and again once the artwork has arrived. */
    function seal(canvas) {
        var target = canvas;

        if (target && target.nodeType === 1 && !target.getContext) {
            target = target.querySelector('canvas');
        }

        if (!target || !target.getContext) {
            return false;
        }

        paintSeal(target);

        if (!loaded(mark())) {
            waitingForLogo.push(function () { paintSeal(target); });
        }

        return true;
    }

    /** A canvas on which a QR reader can see the finished sticker. */
    function sealHolder(holder) {
        if (!holder) {
            return false;
        }

        var sealed = seal(holder);

        // qrcodejs paints the canvas a tick after the constructor on some
        // builds; a second pass costs nothing and covers that.
        setTimeout(function () { seal(holder); }, 0);

        return sealed;
    }

    /** Resolves when the artwork is on screen, so a download is never half a mark. */
    function whenMarkReady() {
        var image = mark();

        if (!image || loaded(image)) {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    }

    /**
     * Draw a sticker on a detached canvas, seal included - for the screens that
     * keep an <img> rather than a container (a downloadable preview, the QR on
     * an asset page).
     *
     * Uses the QR library the page already loaded; without one there is nothing
     * to draw and the caller keeps its stored image.
     */
    function render(value, options) {
        var scanned = text(value);
        var settings = options || {};

        if (scanned === '' || typeof window === 'undefined' || typeof window.QRCode === 'undefined') {
            return null;
        }

        var size = settings.size || 220;
        var holder = document.createElement('div');
        var instance;

        try {
            instance = new window.QRCode(holder, {
                text: scanned,
                width: size,
                height: size,
                colorDark: settings.colorDark || '#0A1830',
                colorLight: '#ffffff',
                correctLevel: isSigned(scanned)
                    ? window.QRCode.CorrectLevel.M
                    : window.QRCode.CorrectLevel.H
            });
        } catch (error) {
            return null;
        }

        var canvas = instance && instance._el ? instance._el.querySelector('canvas') : holder.querySelector('canvas');

        if (!canvas) {
            return null;
        }

        seal(canvas);

        return canvas;
    }

    /** A sealed sticker as a PNG data URL, ready to put on an <img> or download. */
    function dataUrl(value, options) {
        return whenMarkReady().then(function () {
            var canvas = render(value, options);

            return canvas ? canvas.toDataURL('image/png') : '';
        });
    }

    /* ------------------------------------------------------------------ *
     * Deciding whether a frame carries the mark
     * ------------------------------------------------------------------ */

    /*
     * A printed sticker may have no colour on it at all.
     *
     * The office's label printer is a Zebra ZD230 with a black ribbon, so a
     * printed sticker has no navy and no gold: the plate comes out black, the
     * letter white, and the orbit a mid-grey dither. Judging a print by hue
     * would therefore refuse the office's own labels. What a black-only print
     * keeps is the *shape*: a dark square with the artwork's bright mass in the
     * middle. So the centre is also compared, in grey, against the same artwork
     * reduced to a small grid of cells - the same test a photocopy or a
     * greyscale scan of a sticker passes, and an ordinary QR code fails.
     */
    var GRID = 16;

    // How much of the code's edge the plate covers.
    //
    // The sticker draws the mark at 20% of its QR *square*, and that square
    // includes the four-module quiet zone, so against the code's own edge - the
    // edge a decoder reports - the plate is 0.23 to 0.25 depending on how many
    // modules the payload needs. A sweep, not a guess: the whole 0.16-0.32 band
    // is searched a hundredth at a time, so a mark printed slightly small or a
    // code whose quiet zone the print has shaved still lands on its own size.
    var PLATE = (function () {
        var factors = [];
        var factor;

        for (factor = 16; factor <= 32; factor++) {
            factors.push(factor / 100);
        }

        return factors;
    })();

    // Where that plate may sit inside its crop, as a fraction of the code's
    // edge: a print is never perfectly centred in its own square. A test of
    // five offsets gave the same scores as these three, so this is the cheaper
    // of the two - the size sweep is what the accuracy comes from.
    var DRIFT = [-0.02, 0, 0.02];

    // Correlation needed before a grey centre is accepted as the mark.
    //
    // Measured against the real artwork, printed to one ink, blurred and noisy,
    // at 0-180 degrees round, with the code 180-480 px across: a genuine
    // sticker scores 0.71-0.91 (0.79-0.91 in normal light), an ordinary code
    // reproduced on a QR website scores 0.15-0.22, and a plain dark square with
    // a bright blob in it about 0.54-0.62. This sits in the middle of that gap,
    // so a soft print still passes and a re-created code still does not.
    var PRINT_MATCH = 0.66;

    function isGold(r, g, b, saturation) {
        return r > 100 && g > 55 && b < r * 0.75 && saturation > 0.4;
    }

    /**
     * Colour census of the area where the mark has to be.
     *
     * The artwork is a navy plate with white and gold on it, so the centre of a
     * genuine sticker is strongly *coloured* - while the centre of an ordinary
     * QR code is pure black and white. That difference survives printing,
     * camera compression and a phone held at an angle; it does not survive
     * someone photographing the whole sticker, which is a limit of any static
     * code.
     *
     * `region` is {data, width, height} (an ImageData) plus the box to read.
     */
    function classify(region, box) {
        var data = region.data;
        var width = region.width;
        var height = region.height;
        var left = Math.max(0, Math.round(box.x));
        var top = Math.max(0, Math.round(box.y));
        var right = Math.min(width, Math.round(box.x + box.width));
        var bottom = Math.min(height, Math.round(box.y + box.height));

        if (!data || right <= left || bottom <= top) {
            return { verdict: 'unreadable', total: 0 };
        }

        var span = (right - left) * (bottom - top);
        var step = Math.max(1, Math.floor(Math.sqrt(span / 6000)));
        var total = 0;
        var coloured = 0;
        var gold = 0;
        var light = 0;
        var luminance = 0;
        var x;
        var y;

        for (y = top; y < bottom; y += step) {
            for (x = left; x < right; x += step) {
                var index = (y * width + x) * 4;
                var r = data[index];
                var g = data[index + 1];
                var b = data[index + 2];
                var max = Math.max(r, g, b);
                var min = Math.min(r, g, b);
                var saturation = max === 0 ? 0 : (max - min) / max;

                total++;
                luminance += (r * 0.299 + g * 0.587 + b * 0.114);

                if (saturation > 0.2 && max > 30) {
                    coloured++;
                }

                if (isGold(r, g, b, saturation)) {
                    gold++;
                }

                if (min > 155) {
                    light++;
                }
            }
        }

        if (total === 0) {
            return { verdict: 'unreadable', total: 0 };
        }

        var ratios = {
            total: total,
            coloured: coloured / total,
            gold: gold / total,
            light: light / total,
            luminance: luminance / total
        };

        // Too dark to judge: the scanner keeps trying rather than accusing a
        // genuine sticker of being fake.
        if (ratios.luminance < 45) {
            return withVerdict('dark', ratios);
        }

        // Nothing coloured in the middle: an ordinary QR code, or one drawn by
        // a QR-generator website.
        if (ratios.coloured < 0.15) {
            return withVerdict('missing', ratios);
        }

        // Coloured, and the two colours the artwork is made of are both there.
        // The thresholds come from measured stickers, not from taste: a drawn
        // code reads about half coloured with the gold arc and the white letter
        // clearly present, while a black-and-white code reads none at all.
        if (ratios.gold >= 0.02 && ratios.coloured >= 0.30 && ratios.light >= 0.05) {
            return withVerdict('sealed', ratios);
        }

        // Coloured, but not with these colours: some other picture was pasted
        // into the centre.
        return withVerdict('mismatch', ratios);
    }

    function withVerdict(verdict, ratios) {
        ratios.verdict = verdict;

        return ratios;
    }

    /* ------------------------------------------------------------------ *
     * The mark read back from a print
     * ------------------------------------------------------------------ */

    function luminanceAt(data, width, height, x, y) {
        var px = Math.round(x);
        var py = Math.round(y);

        if (px < 0) px = 0; else if (px >= width) px = width - 1;
        if (py < 0) py = 0; else if (py >= height) py = height - 1;

        var i = (py * width + px) * 4;

        return data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114;
    }

    /** A point in a quad, in its own terms: 0..1 across and down it. */
    function pointIn(quad, u, v) {
        var topX = quad.tl.x + (quad.tr.x - quad.tl.x) * u;
        var topY = quad.tl.y + (quad.tr.y - quad.tl.y) * u;
        var bottomX = quad.bl.x + (quad.br.x - quad.bl.x) * u;
        var bottomY = quad.bl.y + (quad.br.y - quad.bl.y) * u;

        return { x: topX + (bottomX - topX) * v, y: topY + (bottomY - topY) * v };
    }

    /**
     * One cell of the grid: a few samples around its middle.
     *
     * A black-only print is dithered, so one pixel is either ink or paper;
     * averaging a patch inside each cell is what turns that back into the grey
     * the artwork actually has there.
     */
    function cell(region, quad, u, v) {
        var step = 0.22 / GRID;
        var total = 0;
        var count = 0;
        var i;
        var j;

        for (i = -1; i <= 1; i++) {
            for (j = -1; j <= 1; j++) {
                var point = pointIn(quad, u + i * step, v + j * step);

                total += luminanceAt(region.data, region.width, region.height, point.x, point.y);
                count++;
            }
        }

        return total / count;
    }

    function gridOf(region, quad) {
        var values = [];
        var row;
        var col;

        for (row = 0; row < GRID; row++) {
            for (col = 0; col < GRID; col++) {
                values.push(cell(region, quad, (col + 0.5) / GRID, (row + 0.5) / GRID));
            }
        }

        return values;
    }

    /**
     * Centre and scale a grid, so a print's brightness and contrast do not
     * matter: only the pattern is compared. A grid with no pattern in it (a
     * blank patch, an even grey) cannot be recognised and returns null.
     */
    function standardise(values) {
        var n = values.length;
        var mean = 0;
        var variance = 0;
        var i;

        for (i = 0; i < n; i++) mean += values[i];
        mean /= n;

        for (i = 0; i < n; i++) variance += (values[i] - mean) * (values[i] - mean);
        variance /= n;

        // Under ~8 grey levels of spread there is nothing to recognise.
        if (variance < 64) {
            return null;
        }

        var sd = Math.sqrt(variance);

        for (i = 0; i < n; i++) values[i] = (values[i] - mean) / sd;

        return values;
    }

    function correlation(a, b) {
        var total = 0;
        var i;

        for (i = 0; i < a.length; i++) total += a[i] * b[i];

        return total / a.length;
    }

    /**
     * A grid turned by a quarter, half or three quarters.
     *
     * A sticker can be on a shelf upside down, and a decoder reports the code's
     * corners as it finds them in the picture, so the artwork has to be
     * recognisable in all four orientations.
     */
    function turn(values, quarterTurns) {
        var size = Math.round(Math.sqrt(values.length));
        var out = [];
        var row;
        var col;

        for (row = 0; row < size; row++) {
            for (col = 0; col < size; col++) {
                var sourceRow;
                var sourceCol;

                if (quarterTurns === 1) {
                    sourceRow = size - 1 - col;
                    sourceCol = row;
                } else if (quarterTurns === 2) {
                    sourceRow = size - 1 - row;
                    sourceCol = size - 1 - col;
                } else if (quarterTurns === 3) {
                    sourceRow = col;
                    sourceCol = size - 1 - row;
                } else {
                    sourceRow = row;
                    sourceCol = col;
                }

                out.push(values[sourceRow * size + sourceCol]);
            }
        }

        return out;
    }

    var printedGrid = null;

    /**
     * The artwork as the sticker prints it - navy plate, artwork letterboxed in
     * it - reduced to the same grid. Built once, from the same file the sticker
     * prints, so the reference can never drift from what is on the paper.
     */
    function artworkGrid() {
        if (printedGrid) {
            return printedGrid;
        }

        var image = mark();

        if (typeof document === 'undefined' || !loaded(image)) {
            return null;
        }

        var canvas = document.createElement('canvas');
        canvas.width = GRID;
        canvas.height = GRID;

        var ctx = canvas.getContext('2d');
        ctx.fillStyle = config().plate || DEFAULTS.plate;
        ctx.fillRect(0, 0, GRID, GRID);
        contain(ctx, image, 0, 0, GRID, GRID);

        var pixels = ctx.getImageData(0, 0, GRID, GRID).data;
        var values = [];
        var i;

        for (i = 0; i < GRID * GRID; i++) {
            values.push(pixels[i * 4] * 0.299 + pixels[i * 4 + 1] * 0.587 + pixels[i * 4 + 2] * 0.114);
        }

        printedGrid = standardise(values);

        return printedGrid;
    }

    function distance(a, b) {
        return Math.sqrt(Math.pow(b.x - a.x, 2) + Math.pow(b.y - a.y, 2));
    }

    /** The code's four corners, or a square over the middle of the frame. */
    function quadFor(location, width, height) {
        var corners = location && [
            location.topLeftCorner,
            location.topRightCorner,
            location.bottomRightCorner,
            location.bottomLeftCorner
        ];

        var usable = corners && corners.filter(function (corner) {
            return corner && typeof corner.x === 'number' && typeof corner.y === 'number';
        });

        if (!usable || usable.length !== 4) {
            var side = Math.min(width, height) * 0.6;
            var left = (width - side) / 2;
            var top = (height - side) / 2;

            return {
                tl: { x: left, y: top },
                tr: { x: left + side, y: top },
                br: { x: left + side, y: top + side },
                bl: { x: left, y: top + side }
            };
        }

        return { tl: usable[0], tr: usable[1], br: usable[2], bl: usable[3] };
    }

    /** A smaller quad inside the code's own corners, nudged by a pixel offset. */
    function shrink(quad, factor, shiftX, shiftY) {
        var cx = (quad.tl.x + quad.tr.x + quad.br.x + quad.bl.x) / 4;
        var cy = (quad.tl.y + quad.tr.y + quad.br.y + quad.bl.y) / 4;

        function corner(point) {
            return {
                x: cx + (point.x - cx) * factor + shiftX,
                y: cy + (point.y - cy) * factor + shiftY
            };
        }

        return { tl: corner(quad.tl), tr: corner(quad.tr), br: corner(quad.br), bl: corner(quad.bl) };
    }

    /**
     * How much the middle of a frame looks like the mark, in grey.
     *
     * `template` is the artwork's own grid; it is passed in by the check script
     * (which has no image to load) and omitted everywhere else.
     */
    function matchPrint(region, location, template) {
        var reference = template || artworkGrid();

        if (!reference || !region || !region.data) {
            return null;
        }

        var quad = quadFor(location, region.width, region.height);
        var edge = (distance(quad.tl, quad.tr) + distance(quad.tr, quad.br)
            + distance(quad.br, quad.bl) + distance(quad.bl, quad.tl)) / 4;
        var best = { score: -1 };
        var turned = [reference, turn(reference, 1), turn(reference, 2), turn(reference, 3)];

        PLATE.forEach(function (factor) {
            DRIFT.forEach(function (shiftX) {
                DRIFT.forEach(function (shiftY) {
                    var grid = standardise(gridOf(
                        region,
                        shrink(quad, factor, edge * shiftX, edge * shiftY)
                    ));

                    if (!grid) {
                        return;
                    }

                    for (var rotation = 0; rotation < turned.length; rotation++) {
                        var score = correlation(grid, turned[rotation]);

                        if (score > best.score) {
                            best = { score: score, factor: factor, shiftX: shiftX, shiftY: shiftY, rotation: rotation };
                        }
                    }
                });
            });
        });

        return best.score < 0 ? null : best;
    }

    /** The box a decoded QR's mark must occupy, from the corners a decoder gives. */
    function boxFor(location, width, height) {
        var corners = location && [
            location.topLeftCorner,
            location.topRightCorner,
            location.bottomRightCorner,
            location.bottomLeftCorner
        ];

        var usable = corners && corners.filter(function (corner) {
            return corner && typeof corner.x === 'number' && typeof corner.y === 'number';
        });

        if (!usable || usable.length !== 4) {
            var fallback = Math.min(width, height) * 0.55;

            return { x: (width - fallback) / 2, y: (height - fallback) / 2, width: fallback, height: fallback };
        }

        var sumX = 0;
        var sumY = 0;

        usable.forEach(function (corner) {
            sumX += corner.x;
            sumY += corner.y;
        });

        var centreX = sumX / 4;
        var centreY = sumY / 4;

        // Average edge length of the quad; the mark sits in the middle ~22% of
        // it. The box read here is a little larger (0.30) so a mark printed
        // slightly off-centre still lands inside it.
        var edges = [
            Math.sqrt(Math.pow(usable[1].x - usable[0].x, 2) + Math.pow(usable[1].y - usable[0].y, 2)),
            Math.sqrt(Math.pow(usable[2].x - usable[1].x, 2) + Math.pow(usable[2].y - usable[1].y, 2)),
            Math.sqrt(Math.pow(usable[3].x - usable[2].x, 2) + Math.pow(usable[3].y - usable[2].y, 2)),
            Math.sqrt(Math.pow(usable[0].x - usable[3].x, 2) + Math.pow(usable[0].y - usable[3].y, 2))
        ];
        var edge = edges.reduce(function (a, b) { return a + b; }, 0) / 4;
        var side = edge * 0.30;

        return { x: centreX - side / 2, y: centreY - side / 2, width: side, height: side };
    }

    /** Judge one decoded frame. `location` is jsQR's, when it is available. */
    function verifyFrame(imageData, location) {
        if (!imageData) {
            return { verdict: 'unreadable' };
        }

        var box = boxFor(location, imageData.width, imageData.height);
        var result = classify(imageData, box);

        // A colour judgement is the end of it: sealed, too dark to tell, or no
        // frame to read. Only a centre with no colour in it - the print on a
        // black-only ribbon - is put to the grey comparison.
        if (result.verdict === 'sealed' || result.verdict === 'dark' || result.verdict === 'unreadable') {
            return result;
        }

        var print = matchPrint(imageData, location);

        if (!print) {
            return result;
        }

        result.print = print;

        if (print.score >= PRINT_MATCH) {
            result.verdict = 'sealed';
            result.via = 'print';
        }

        return result;
    }

    /* ------------------------------------------------------------------ *
     * The scanner's check
     * ------------------------------------------------------------------ */

    function camera() {
        if (typeof document === 'undefined') {
            return null;
        }

        var videos = document.querySelectorAll('video');
        var visible = null;
        var i;

        for (i = 0; i < videos.length; i++) {
            var video = videos[i];

            if (!video.videoWidth || video.readyState < 2) {
                continue;
            }

            if (video.offsetWidth || video.offsetHeight || video.getClientRects().length) {
                visible = video;
            } else if (!visible) {
                visible = video;
            }
        }

        return visible;
    }

    /**
     * Confirm the mark on the sticker that was just decoded.
     *
     * Resolves `{ok: true}` or `{ok: false, message}`; the caller shows the
     * message and does nothing else. A NUTAG has no centre to check and a
     * sticker whose camera frame cannot be judged after a few tries is refused
     * - an unverified scan never turns into asset information.
     */
    function checkScan(scanned) {
        var settings = config();

        if (!settings.requireSeal || isTag(scanned)) {
            return Promise.resolve({ ok: true, skipped: true });
        }

        if (typeof window === 'undefined' || typeof window.jsQR !== 'function') {
            return Promise.resolve({ ok: false, reason: 'no-decoder', message: INVALID });
        }

        if (typeof document === 'undefined') {
            return Promise.resolve({ ok: false, reason: 'no-frame', message: RETRY });
        }

        // The grey comparison needs the artwork's own grid, so wait for the
        // file: a printed sticker must not be refused because the logo was
        // still in flight on a slow tablet.
        return whenMarkReady().then(readFrame);
    }

    function readFrame() {
        var attempts = 0;
        var canvas = document.createElement('canvas');

        return new Promise(function (resolve) {
            function attempt() {
                var video = camera();

                if (!video) {
                    resolve({ ok: false, reason: 'no-frame', message: RETRY });
                    return;
                }

                attempts++;

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                var ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                var imageData;
                var decoded;
                var result;

                try {
                    imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    decoded = window.jsQR(imageData.data, imageData.width, imageData.height);
                } catch (error) {
                    resolve({ ok: false, reason: 'unreadable', message: RETRY });
                    return;
                }

                if (!decoded) {
                    if (attempts < 4) {
                        setTimeout(attempt, 150);
                        return;
                    }

                    resolve({ ok: false, reason: 'unreadable', message: RETRY });
                    return;
                }

                result = verifyFrame(imageData, decoded.location);

                if (result.verdict === 'sealed') {
                    resolve({ ok: true, verdict: result });
                    return;
                }

                // A centre that is close to the mark - a print held at a
                // glancing angle, caught mid-focus - is given the same second
                // chances as a dark frame rather than being called fake on one
                // unlucky frame.
                var nearMiss = result.print && result.print.score >= PRINT_MATCH * 0.8;

                if ((result.verdict === 'dark' || result.verdict === 'unreadable' || nearMiss) && attempts < 4) {
                    setTimeout(attempt, 150);
                    return;
                }

                resolve({ ok: false, reason: result.verdict, verdict: result, message: INVALID });
            }

            attempt();
        });
    }

    return {
        INVALID: INVALID,
        RETRY: RETRY,
        config: config,
        isTag: isTag,
        isSigned: isSigned,
        mark: mark,
        whenMarkReady: whenMarkReady,
        render: render,
        dataUrl: dataUrl,
        seal: seal,
        sealHolder: sealHolder,
        classify: classify,
        boxFor: boxFor,
        quadFor: quadFor,
        verifyFrame: verifyFrame,
        matchPrint: matchPrint,
        artworkGrid: artworkGrid,
        PRINT_MATCH: PRINT_MATCH,
        GRID: GRID,
        checkScan: checkScan
    };
});
