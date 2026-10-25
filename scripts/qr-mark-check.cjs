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

/**
 * The grid the grey comparison works on, built from a region by averaging each
 * cell - the same thing the module does to the artwork file and to a frame.
 */
function downsample(region, grid) {
    const values = [];

    for (let row = 0; row < grid; row++) {
        for (let col = 0; col < grid; col++) {
            const x0 = Math.floor((col * region.width) / grid);
            const x1 = Math.floor(((col + 1) * region.width) / grid);
            const y0 = Math.floor((row * region.height) / grid);
            const y1 = Math.floor(((row + 1) * region.height) / grid);
            let sum = 0;
            let count = 0;

            for (let y = y0; y < y1; y++) {
                for (let x = x0; x < x1; x++) {
                    const i = (y * region.width + x) * 4;
                    sum += 0.299 * region.data[i] + 0.587 * region.data[i + 1] + 0.114 * region.data[i + 2];
                    count++;
                }
            }

            values.push(sum / count);
        }
    }

    return values;
}

/** Centre and scale a grid, exactly as the module does before correlating. */
function standardise(values) {
    const n = values.length;
    let mean = 0;

    for (const value of values) mean += value;
    mean /= n;

    let variance = 0;

    for (const value of values) variance += (value - mean) * (value - mean);
    variance /= n;

    if (variance < 64) return null;

    const sd = Math.sqrt(variance);

    return values.map((value) => (value - mean) / sd);
}

/**
 * The reference grid for the grey comparison.
 *
 * In a browser the module builds this from the artwork file itself. There is no
 * image to load here, so the same shape the frames are painted from is used:
 * what is being tested is the matching, not the artwork, and the browser check
 * covers the artwork.
 */
function referenceGrid() {
    return standardise(downsample(sealedCentre(mark.GRID * 20), mark.GRID));
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

const location = {
    topLeftCorner: { x: 10, y: 10 },
    topRightCorner: { x: size - 10, y: 10 },
    bottomRightCorner: { x: size - 10, y: size - 10 },
    bottomLeftCorner: { x: 10, y: size - 10 },
};

const reference = referenceGrid();

function colourAt(region, x, y) {
    const i = (y * region.width + x) * 4;

    return [region.data[i], region.data[i + 1], region.data[i + 2]];
}

/** A colour as a black-only print would carry it: one grey level. */
function monochrome(colour) {
    const level = Math.round(0.299 * colour[0] + 0.587 * colour[1] + 0.114 * colour[2]);

    return [level, level, level];
}

/** The artwork, painted inside a plate of `plate` pixels. */
function markCentre(x, y, plate) {
    return colourAt(sealedCentre(plate), x, y);
}

/** The same artwork through a black ribbon: no navy, no gold, only grey. */
function printedCentre(x, y, plate) {
    return monochrome(markCentre(x, y, plate));
}

/**
 * A sticker: a black-and-white code with a plate in its middle, drawn the way
 * the label does - the artwork at 20% of the code's edge, inside a white ring.
 */
function sticker(size, centre) {
    const base = plainQr(size);
    const plate = Math.round(size * 0.2);
    const ring = Math.round(plate * 0.135);
    const left = Math.round((size - plate) / 2);
    const top = Math.round((size - plate) / 2);

    return frame(size, (x, y) => {
        const inRing = x >= left - ring && x < left + plate + ring && y >= top - ring && y < top + plate + ring;

        if (!inRing) {
            return colourAt(base, x, y);
        }

        const inPlate = x >= left && x < left + plate && y >= top && y < top + plate;

        return inPlate ? centre(x - left, y - top, plate) : WHITE;
    });
}

/** How much a frame's middle looks like the mark in grey. */
function printScore(frame) {
    const result = mark.matchPrint(frame, location, reference);

    return result ? result.score : NaN;
}

function passed(score) {
    return score >= mark.PRINT_MATCH;
}

check('a genuine sticker centre is sealed', mark.classify(sealedCentre(size), box).verdict, 'sealed');
check('an ordinary black-and-white code is missing the mark', mark.classify(plainQr(size), box).verdict, 'missing');
check('a code with another picture in it is a mismatch', mark.classify(wrongLogo(size), box).verdict, 'mismatch');
check('a frame too dark to judge is not an accusation', mark.classify(flat(size, [6, 8, 12]), box).verdict, 'dark');
check('a blank white frame has no mark', mark.classify(flat(size, WHITE), box).verdict, 'missing');

// A label printed on a black-only ribbon has no navy and no gold, so colour
// alone reports 'missing' - the grey comparison is what has to recognise it.
check(
    'a black-only print is not judged by its colour',
    mark.classify(sticker(size, printedCentre), box).verdict,
    'missing'
);
check(
    'the grey comparison still recognises the printed mark',
    passed(printScore(sticker(size, printedCentre))) ? 'sealed' : 'missing',
    'sealed'
);
check('the coloured sticker passes both ways', passed(printScore(sticker(size, markCentre))) ? 'sealed' : 'missing', 'sealed');
check('an ordinary code fails the grey comparison', passed(printScore(plainQr(size))) ? 'sealed' : 'missing', 'missing');
check('another picture fails the grey comparison', passed(printScore(wrongLogo(size))) ? 'sealed' : 'missing', 'missing');
check('a blank frame fails the grey comparison', passed(printScore(flat(size, WHITE))) ? 'sealed' : 'missing', 'missing');

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
console.log(
    `grey match (threshold ${mark.PRINT_MATCH}): printed mark ${printScore(sticker(size, printedCentre)).toFixed(3)}` +
    `  ·  ordinary code ${printScore(plainQr(size)).toFixed(3)}` +
    `  ·  another picture ${printScore(wrongLogo(size)).toFixed(3)}`
);

process.exit(failures === 0 ? 0 : 1);
