{{--
    The one reader for a scanned sticker, shared by every screen that scans.

    The server is the only thing that can verify a signature (the private keys
    never leave it), so this file does two jobs and no more:

      · read the payload locally - which asset code is in it, and whether it is
        signed at all - so a screen that matches codes in the page (the pullout
        picker, the request forms) still finds the asset behind a signed sticker;
      · ask /qr/verify about the signature, and draw a sticker's code in the
        browser when there is no stored image to show.

    Nothing here decides that a sticker is fake: a failed fetch is reported as
    "verification unavailable", never as a forged code.
--}}
<script src="{{ url('/js/nutag-decode.js') }}"></script>
<script>
window.NUTraceQr = (function () {
    var VERIFY_URL = @json(url('/qr/verify'));
    var TAG_VERIFY_URL = @json(url('/tag/verify'));
    var PAYLOAD_URL = @json(url('/admin/assets/qr-payload'));
    var FAMILY = /^NUT([0-9]{1,2})[-:]/i;
    var BLOCKING = ['invalid_signature', 'unknown_key', 'asset_revoked', 'malformed'];
    var payloadCache = {};

    var OFFLINE = {
        status: 'unavailable',
        label: 'Verification unavailable',
        tone: 'neutral',
        detail: 'The app could not reach the server to check this sticker. It is neither confirmed nor rejected.',
        signed: true,
        key_id: null,
        permitted: false,
        asset: null
    };

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function escape(value) {
        return text(value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    /**
     * Read scanned text. Mirrors App\Support\QrPayload::parse: signed payload,
     * plain (legacy) code, or something that is not a NU Trace sticker / is
     * damaged.
     *
     * A version 2 sticker carries its code sealed, so the browser cannot read
     * it - `code` is null and `sealed` is true. The code comes back from
     * /qr/verify, which is where the key for the seal lives; screens that need
     * it must await verify() instead of reading it off the text.
     */
    function isTag(value) {
        return /^NUTAG1-/i.test(text(value));
    }

    function parse(value) {
        var raw = text(value);
        var blank = { code: null, keyId: null, signed: false, version: null, sealed: false };

        if (raw === '') {
            return Object.assign({}, blank, { type: 'malformed' });
        }

        // A NUTAG is not a QR payload: its identifier is sealed in cells and
        // only the server can turn it into an asset code.
        if (isTag(raw)) {
            return Object.assign({}, blank, {
                type: 'tag',
                keyId: 'NUTAG1',
                signed: true,
                version: 1,
                sealed: true
            });
        }

        if (!FAMILY.test(raw)) {
            return Object.assign({}, blank, { type: 'legacy', code: raw });
        }

        var upper = raw.toUpperCase();
        var match = /^NUT([0-9]{1,2})[-:]/i.exec(upper);
        var version = match ? parseInt(match[1], 10) : 0;
        var parts = upper.split('-');

        if (version === 2) {
            // NUT2, key id, sealed body, signature - none of them hold a dash.
            if (parts.length !== 4 || !parts[1] || !parts[2] || !parts[3]) {
                return Object.assign({}, blank, { type: 'malformed', version: 2 });
            }

            return {
                type: 'signed',
                code: null,
                keyId: parts[1],
                body: parts[2],
                signature: parts[3],
                signed: true,
                version: 2,
                sealed: true
            };
        }

        if (version !== 1) {
            return Object.assign({}, blank, { type: 'unsupported', version: version });
        }

        if (parts.length < 4) {
            return Object.assign({}, blank, { type: 'malformed', version: 1 });
        }

        var signature = parts.pop();
        var keyId = parts.pop();
        var code = parts.slice(1).join('-');

        if (!code || !keyId || !signature) {
            return Object.assign({}, blank, { type: 'malformed', version: 1 });
        }

        return { type: 'signed', code: code, keyId: keyId, signed: true, version: 1, sealed: false };
    }

    /** The asset code inside any scanned text, signed or not. */
    function codeOf(value) {
        return parse(value).code || '';
    }

    function isSigned(value) {
        var type = parse(value).type;

        return type === 'signed' || type === 'tag';
    }

    /** Statuses that must not be acted on. */
    function blocking(status) {
        return BLOCKING.indexOf(status) !== -1;
    }

    /** Ask the server about one scanned sticker. Never rejects. */
    function verify(value) {
        var scanned = text(value);

        if (scanned === '') {
            return Promise.resolve(OFFLINE);
        }

        // A tag is judged by the server that holds its key, exactly as a QR
        // payload is: the browser only carried the text here.
        if (isTag(scanned)) {
            return fetch(TAG_VERIFY_URL + '?tag=' + encodeURIComponent(scanned), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (result) { return result || OFFLINE; })
                .catch(function () { return OFFLINE; });
        }

        return fetch(VERIFY_URL + '?payload=' + encodeURIComponent(scanned), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (result) { return result || OFFLINE; })
            .catch(function () { return OFFLINE; });
    }

    /**
     * The signed payload for an asset code (admin screens only), falling back
     * to the plain code when this install cannot sign - exactly what the stored
     * sticker would then carry.
     */
    function payloadFor(code, options) {
        var normalized = text(code).toUpperCase();

        if (normalized === '') {
            return Promise.resolve('');
        }

        if (payloadCache[normalized]) {
            return Promise.resolve(payloadCache[normalized]);
        }

        var query = (options && options.single ? 'code=' : 'codes=') + encodeURIComponent(normalized);

        return fetch(PAYLOAD_URL + '?' + query, { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                var payload = data && data.payloads ? data.payloads[normalized] : null;
                payloadCache[normalized] = payload || normalized;

                return payloadCache[normalized];
            })
            .catch(function () { return normalized; });
    }

    /**
     * Draw a QR into an element with the same library every screen already uses.
     * A signed payload is a long string, so it is drawn at a lower error
     * correction level than a short legacy code: the same 20mm square stays
     * readable instead of turning into a wall of modules.
     */
    function draw(target, value, options) {
        var holder = typeof target === 'string' ? document.getElementById(target) : target;
        var drawn = text(value);

        if (!holder || drawn === '') {
            return null;
        }

        holder.innerHTML = '';

        // A NUTAG is drawn from its own matrix: not a QR, and deliberately not
        // something a QR library could produce. The same server-side geometry
        // as App\Support\NutTag, quiet zone included.
        if (window.NutTag && typeof window.NutTag.svgFromText === 'function') {
            var tagSvg = window.NutTag.svgFromText(drawn, 'NU Trace asset tag');

            if (tagSvg) {
                holder.innerHTML = tagSvg;
                return holder.firstChild;
            }
        }

        // A NUTAG with no decoder loaded is never handed to a QR library or a
        // QR service: either would publish the sticker's text as a readable
        // code, which is the one thing the symbol exists to prevent.
        if (isTag(drawn)) {
            holder.innerHTML = '<p style="font-size:.75rem;color:#5B6678;text-align:center;">'
                + 'The NU Trace symbol could not be drawn. Reload the page.</p>';

            return null;
        }

        if (typeof QRCode === 'undefined') {
            var img = document.createElement('img');
            img.alt = 'QR code';
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&qzone=1&ecc='
                + (isSigned(drawn) ? 'M' : 'L') + '&data=' + encodeURIComponent(drawn);
            holder.appendChild(img);

            return img;
        }

        var settings = options || {};

        return new QRCode(holder, {
            text: drawn,
            width: settings.width || 220,
            height: settings.height || 220,
            colorDark: settings.colorDark || '#0A1830',
            colorLight: settings.colorLight || '#ffffff',
            correctLevel: isSigned(drawn) ? QRCode.CorrectLevel.M : QRCode.CorrectLevel.H
        });
    }

    /** Colours and icon for one verification tone. */
    function tone(name) {
        switch (name) {
            case 'ok':
                return { bg: '#ECF6EE', border: '#1E7A3C', color: '#14532D', icon: 'ri-verified-badge-line' };
            case 'warning':
                return { bg: '#FDF6E3', border: '#C9A227', color: '#7A5B00', icon: 'ri-alert-line' };
            case 'danger':
                return { bg: '#FBECEA', border: '#A23B32', color: '#7A241C', icon: 'ri-close-circle-line' };
            default:
                return { bg: '#F2F4F7', border: '#A8AFBC', color: '#46536B', icon: 'ri-question-line' };
        }
    }

    /** The verification banner, as markup, for a scanner result panel. */
    function banner(verification) {
        if (!verification || !verification.label) {
            return '';
        }

        var palette = tone(verification.tone);

        return '<div style="display:flex;gap:.6rem;align-items:flex-start;background:' + palette.bg
            + ';border:1px solid ' + palette.border + ';border-radius:.6rem;padding:.65rem .8rem;">'
            + '<i class="' + palette.icon + '" style="color:' + palette.color + ';font-size:1.15rem;line-height:1.3;"></i>'
            + '<div style="min-width:0;">'
            + '<div style="font-weight:600;color:' + palette.color + ';font-size:.875rem;">' + escape(verification.label) + '</div>'
            + '<div style="color:#5B6678;font-size:.8rem;margin-top:.15rem;">' + escape(verification.detail) + '</div>'
            + '</div></div>'
            + duplicateBanner(verification.duplicate);
    }

    /**
     * The "same sticker, two places" warning.
     *
     * A copy carries the original's bytes, so nothing in the scan can prove it
     * is a copy; what the ledger can say is that this one sticker has been
     * presented from another account or network recently. The scan still works
     * - this asks for a physical check, which is the only thing that settles
     * it - and revocation is how the office makes every copy dead at once.
     */
    function duplicateBanner(duplicate) {
        if (!duplicate || !duplicate.suspected) {
            return '';
        }

        var palette = tone('warning');
        var others = duplicate.others > 1 ? duplicate.others + ' other sources' : 'another account or network';

        return '<div style="display:flex;gap:.6rem;align-items:flex-start;background:' + palette.bg
            + ';border:1px solid ' + palette.border + ';border-radius:.6rem;padding:.65rem .8rem;margin-top:.5rem;">'
            + '<i class="ri-alert-line" style="color:' + palette.color + ';font-size:1.15rem;line-height:1.3;"></i>'
            + '<div style="min-width:0;">'
            + '<div style="font-weight:600;color:' + palette.color + ';font-size:.875rem;">Possible duplicate sticker</div>'
            + '<div style="color:#5B6678;font-size:.8rem;margin-top:.15rem;">'
            + 'This exact sticker was also presented from ' + escape(others) + ' in the last '
            + escape(duplicate.window_minutes) + ' minutes. A reprinted code works like the original — '
            + 'check the object physically, and revoke the sticker if it is a copy.'
            + '</div></div></div>';
    }

    return {
        parse: parse,
        codeOf: codeOf,
        isSigned: isSigned,
        blocking: blocking,
        verify: verify,
        payloadFor: payloadFor,
        draw: draw,
        tone: tone,
        banner: banner,
        duplicateBanner: duplicateBanner,
        escape: escape,
        OFFLINE: OFFLINE
    };
})();
</script>
