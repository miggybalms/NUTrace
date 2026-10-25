{{--
    The NUTAG reader, shared by every screen with a camera.

    A NUTAG is not a QR code, so html5-qrcode (and every other QR library) sees
    nothing on a new sticker. This partial watches the same camera frames,
    samples the 25 x 25 cells with resources/js/nutag-decode.js, and hands the
    tag's text - `NUTAG1-<token>-<MAC>` - to whatever the page already does
    with a scan.

    The page's own verification then does the rest: App\Support\QrVerification
    recognises a tag and asks App\Support\AssetTag, so the MAC, the token, the
    asset and its revocation are judged on the server. No key ever reaches this
    file.
--}}
<script src="{{ url('/js/nutag-decode.js') }}"></script>
<script>
window.NUTraceTag = (function () {
    var MAX_WIDTH = 720;
    var INTERVAL = 400;
    var REPEAT_MS = 4000;
    var pageTimer = null;
    var pageCanvas = null;
    var lastText = '';
    var lastAt = 0;

    function hex(bytes) {
        var out = '';

        for (var i = 0; i < bytes.length; i++) {
            out += ('0' + bytes[i].toString(16)).slice(-2);
        }

        return out.toUpperCase();
    }

    /** Ask the server about one tag: the MAC and the token are judged there. */
    function verify(token, mac) {
        return fetch(@json(url('/tag/verify')) + '?token=' + encodeURIComponent(token) + '&mac=' + encodeURIComponent(mac), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) { return response.ok ? response.json() : null; })
            .catch(function () { return null; });
    }

    /** Read one canvas; the decoder finds the frame, corrects rotation, samples cells. */
    function readCanvas(canvas) {
        if (!window.NutTag || !canvas || !canvas.width) return null;

        try {
            var data = canvas.getContext('2d', { willReadFrequently: true })
                .getImageData(0, 0, canvas.width, canvas.height).data;
            var result = window.NutTag.decodeImage({ data: data, width: canvas.width, height: canvas.height });

            if (!result || !result.ok) return null;

            return 'NUTAG1-' + hex(result.token) + '-' + hex(result.mac);
        } catch (e) {
            return null;
        }
    }

    /**
     * Watch the page's camera, if one is running, and call onFound with the
     * tag's text. One watcher per page: the scanner modals open and close, and
     * this simply stops seeing a video when they do.
     */
    function watchPage(onFound) {
        if (!window.NutTag) return null;

        if (pageTimer) clearInterval(pageTimer);

        pageCanvas = document.createElement('canvas');
        pageCanvas.style.display = 'none';

        pageTimer = setInterval(function () {
            var video = document.querySelector('video');

            if (!video || !video.videoWidth || video.paused || video.offsetWidth === 0) {
                return;
            }

            var scale = Math.min(1, MAX_WIDTH / video.videoWidth);
            var width = Math.round(video.videoWidth * scale);
            var height = Math.round(video.videoHeight * scale);

            if (pageCanvas.width !== width || pageCanvas.height !== height) {
                pageCanvas.width = width;
                pageCanvas.height = height;
            }

            try {
                pageCanvas.getContext('2d').drawImage(video, 0, 0, width, height);
            } catch (e) {
                return;
            }

            var text = readCanvas(pageCanvas);

            if (!text) return;

            var now = Date.now();

            // The camera stays open in some screens; the same tag must not fire
            // on every frame it is still in front of the lens.
            if (text === lastText && now - lastAt < REPEAT_MS) return;

            lastText = text;
            lastAt = now;
            onFound(text);
        }, INTERVAL);

        return { stop: function () { clearInterval(pageTimer); pageTimer = null; } };
    }

    /** Decode an uploaded photo or screenshot of a tag. */
    function fromFile(file) {
        return new Promise(function (resolve) {
            if (!file || !window.NutTag) return resolve(null);

            var url = URL.createObjectURL(file);
            var image = new Image();

            image.onload = function () {
                try {
                    var canvas = document.createElement('canvas');
                    var scale = Math.min(1, 1600 / image.naturalWidth);

                    canvas.width = Math.round(image.naturalWidth * scale);
                    canvas.height = Math.round(image.naturalHeight * scale);

                    var context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0, canvas.width, canvas.height);

                    resolve(readCanvas(canvas));
                } catch (e) {
                    resolve(null);
                } finally {
                    URL.revokeObjectURL(url);
                }
            };

            image.onerror = function () {
                URL.revokeObjectURL(url);
                resolve(null);
            };

            image.src = url;
        });
    }

    return {
        verify: verify,
        watchPage: watchPage,
        fromFile: fromFile,
        svgFromText: function (text, label) {
            return window.NutTag && window.NutTag.svgFromText
                ? window.NutTag.svgFromText(text, label)
                : null;
        }
    };
})();
</script>
