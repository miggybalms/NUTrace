#!/usr/bin/env node
/**
 * What the scanner decides about the middle of a QR code.
 *
 *   node scripts/qr-mark-check.cjs
 *
 * The classifier in resources/js/nu-trace-mark.js is the only place a scan is
 * refused for a missing mark, so it is exercised here against synthetic frames:
 * the centre of a genuine sticker, the centre of an ordinary black-and-white
 * code, a code with some other picture pasted in it, and a frame too dark to
 * judge. No browser or camera is involved; the real files are put through the
 * same function the scanners call.
 */

'use strict';

const fs = require('fs');
const path = require('path');

// The module is a browser script in a package that declares itself ESM, so it
// is loaded the way a browser would: evaluated with no DOM around it. The
// classifier is pure and works on plain pixel buffers.
const source = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'nu-trace-mark.js'), 'utf8');
const loaded = { exports: {} };

new Function('module', 'exports', source).call(loaded.exports, loaded, loaded.exports);

const mark = loaded.exports;

const NAVY = [10, 24, 48];
const GOLD = [232, 163, 61];
const WHITE = [255, 255, 255];
const BLACK = [12, 12, 12];

/**
 * Build a frame: `size` x `size`, painted by `paint(x, y)` which returns a
 * colour for the pixel. `centre` is where a QR code is supposed to be.
 */
function frame(size, paint) {
    const data = new Uint8ClampedArray(size * size * 4);

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const [r, g, b] = paint(x, y);
            const i = (y * size + x) * 4;

            data[i] = r;
            data[i + 1] = g;
            data[i + 2] = b;
            data[i + 3] = 255;
        }
    }

    return { data, width: size, height: size };
}

/** Every pixel the same colour: the region a decoder would point at. */
function flat(size, colour) {
    return frame(size, () => colour);
}

/** A QR-ish field: black and white squares, no colour anywhere. */
function plainQr(size) {
    return frame(size, (x, y) => (((x >> 3) + (y >> 3)) % 2 === 0 ? BLACK : WHITE));
}

/**
 * The sticker's centre: a navy plate, the white letter A on it and the gold
 * orbit around it - the proportions of the supplied artwork, not a guess at
 * them (roughly 60% navy, 25% white, 12% gold).
 */
function sealedCentre(size) {
    const module = size / 25;

    return frame(size, (x, y) => {
        const dx = x - size / 2;
        const dy = y - size / 2;

        // The white letter: a stroke down the middle with a crossbar.
        if (Math.abs(dx) < module * 1.2 && Math.abs(dy) < module * 4) {
            return WHITE;
        }

        if (Math.abs(dy + module * 2.5) < module * 0.8 && Math.abs(dx) < module * 2.5) {
            return WHITE;
        }

        // The gold orbit around it.
        if (Math.abs(dx) > module * 3 && Math.abs(dx) < module * 4.5 && Math.abs(dy) < module * 4) {
            return GOLD;
        }

        return NAVY;
    });
}

/** Something else entirely pasted in the middle: a red disc on green. */
function wrongLogo(size) {
    const module = size / 25;

    return frame(size, (x, y) => {
        const dx = x - size / 2;
        const dy = y - size / 2;

        return Math.sqrt(dx * dx + dy * dy) < module * 6 ? [200, 40, 40] : [30, 140, 70];
    });
}

const results = [];
let failures = 0;

function check(name, verdict, expected) {
    const ok = verdict === expected;

    if (!ok) {
        failures++;
    }

    results.push(`${ok ? 'ok  ' : 'FAIL'} ${name}: ${verdict}${ok ? '' : ' (expected ' + expected + ')'}`);
}

const size = 200;
const box = { x: 60, y: 60, width: 80, height: 80 };

check('a genuine sticker centre is sealed', mark.classify(sealedCentre(size), box).verdict, 'sealed');
check('an ordinary black-and-white code is missing the mark', mark.classify(plainQr(size), box).verdict, 'missing');
check('a code with another picture in it is a mismatch', mark.classify(wrongLogo(size), box).verdict, 'mismatch');
check('a frame too dark to judge is not an accusation', mark.classify(flat(size, [6, 8, 12]), box).verdict, 'dark');
check('a blank white frame has no mark', mark.classify(flat(size, WHITE), box).verdict, 'missing');

// The ratios the decision is made from, printed so a threshold change is
// visible in the check's output.
const sealed = mark.classify(sealedCentre(size), box);
const plain = mark.classify(plainQr(size), box);

// The region is located from the corners a decoder returns; a code in the
// middle of a frame must produce a box over its middle.
const centred = mark.boxFor({
    topLeftCorner: { x: 20, y: 20 },
    topRightCorner: { x: 180, y: 20 },
    bottomRightCorner: { x: 180, y: 180 },
    bottomLeftCorner: { x: 20, y: 180 },
}, 200, 200);

check(
    'the box covers the centre of the decoded code',
    Math.round(centred.x + centred.width / 2) === 100 && Math.round(centred.y + centred.height / 2) === 100 ? 'centred' : 'off-centre',
    'centred'
);

console.log(results.join('\n'));
console.log(
    `\ncoloured: genuine ${(sealed.coloured * 100).toFixed(1)}% / plain ${(plain.coloured * 100).toFixed(1)}%` +
    `  ·  gold: genuine ${(sealed.gold * 100).toFixed(1)}% / plain ${(plain.gold * 100).toFixed(1)}%` +
    `  ·  light: genuine ${(sealed.light * 100).toFixed(1)}% / plain ${(plain.light * 100).toFixed(1)}%`
);

process.exit(failures === 0 ? 0 : 1);
