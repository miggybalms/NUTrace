#!/usr/bin/env node
/**
 * NUTAG-1 verification harness.
 *
 * Renders a NUTAG symbol from its text form and checks the two things the
 * format exists for:
 *
 *   1. the NU Trace decoder (resources/js/nutag-decode.js) reads it back;
 *   2. jsQR - the decoder behind html5-qrcode and many online scanner sites -
 *      finds no QR code in exactly the same pixels.
 *
 * jsQR and pngjs are only needed for this check, not by the app, so they are
 * installed outside the project:
 *
 *     mkdir -p /tmp/nutagcheck && cd /tmp/nutagcheck && npm init -y && npm i jsqr pngjs
 *     NODE_PATH=/tmp/nutagcheck/node_modules node scripts/nutag-check.cjs
 *
 * A photograph of a real printed sticker can be checked too:
 *
 *     NODE_PATH=/tmp/nutagcheck/node_modules node scripts/nutag-check.cjs photo.png
 *
 * The result is evidence, not a guarantee: a genuine tag can still be
 * photographed and reprinted. The MAC proves this system minted the token, and
 * the server's duplicate-scan record is what notices one tag used twice.
 */
'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');

// The app's package.json declares `type: module`, which makes Node load
// resources/js/nutag-decode.js as an ES module, where its UMD branch finds no
// global object. Copy it to a .cjs file so the very same source can be loaded
// as CommonJS here, without touching how the browser loads it.
const copied = path.join(os.tmpdir(), 'nutag-decode-' + process.pid + '.cjs');
fs.copyFileSync(path.join(__dirname, '..', 'resources', 'js', 'nutag-decode.js'), copied);

const NutTag = require(copied);
const jsQR = require('jsqr');

fs.unlinkSync(copied);

const GRID = NutTag.grid;
const QUIET = NutTag.quiet;

/** A fixed tag, so the check is reproducible. */
const TOKEN_HEX = '0123456789ABCDEF0123456789ABCDEF';
const MAC_HEX = 'FEDCBA9876543210';
const TAG_TEXT = 'NUTAG1-' + TOKEN_HEX + '-' + MAC_HEX;

function rgbaFromCells(cells, modulePx) {
    const side = (GRID + QUIET * 2) * modulePx;
    const data = new Uint8ClampedArray(side * side * 4);

    for (let y = 0; y < side; y++) {
        const r = Math.floor(y / modulePx) - QUIET;

        for (let x = 0; x < side; x++) {
            const c = Math.floor(x / modulePx) - QUIET;
            const dark = r >= 0 && r < GRID && c >= 0 && c < GRID && cells[r][c];
            const value = dark ? 0 : 255;
            const p = (y * side + x) * 4;

            data[p] = value;
            data[p + 1] = value;
            data[p + 2] = value;
            data[p + 3] = 255;
        }
    }

    return { data, width: side, height: side };
}

function hex(bytes) {
    return Array.from(bytes).map((b) => ('0' + b.toString(16)).slice(-2)).join('').toUpperCase();
}

function checkImage(image, label) {
    const ourRead = NutTag.decodeImage(image);
    const qrRead = jsQR(image.data, image.width, image.height);

    const oursOk = !!ourRead && ourRead.ok
        && hex(ourRead.token) === TOKEN_HEX
        && hex(ourRead.mac) === MAC_HEX;

    console.log(label);
    console.log('  NU Trace decoder: ' + (oursOk
        ? 'read the tag (' + hex(ourRead.token).slice(0, 8) + '… / ' + hex(ourRead.mac).slice(0, 4) + '…)'
        : 'FAILED' + (ourRead && ourRead.reason ? ' (' + ourRead.reason + ')' : '')));
    console.log('  jsQR (standard QR): ' + (qrRead ? 'FOUND A QR CODE: ' + qrRead.data : 'nothing'));

    return oursOk && !qrRead;
}

function main() {
    const file = process.argv[2];
    let ok;

    if (file) {
        const { PNG } = require('pngjs');
        const png = PNG.sync.read(fs.readFileSync(path.resolve(file)));

        ok = checkImage({ data: png.data, width: png.width, height: png.height }, path.basename(file));
    } else {
        for (const modulePx of [4, 8, 12]) {
            const cells = NutTag.matrixFromText(TAG_TEXT);
            const image = rgbaFromCells(cells, modulePx);

            ok = checkImage(image, 'synthetic tag, ' + modulePx + ' px per cell (' + image.width + '×' + image.height + ')');
        }
    }

    console.log('');
    console.log(ok ? 'PASS: NU Trace reads the symbol; a standard QR decoder does not.' : 'FAIL');

    process.exit(ok ? 0 : 1);
}

main();
