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

        return classify(imageData, box);
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

                if ((result.verdict === 'dark' || result.verdict === 'unreadable') && attempts < 4) {
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
        verifyFrame: verifyFrame,
        checkScan: checkScan
    };
});
