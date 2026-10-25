{{--
    The NU Trace seal, on every screen that draws or scans a sticker.

    window.NUTraceQrMarkConfig is what the module reads: where the official logo
    lives, and whether a scan whose centre mark cannot be confirmed has to be
    refused. That second value is a server setting (QR_REQUIRE_SEAL) so turning
    it off while old stickers are still in circulation is a deployment decision,
    not something a browser can choose.

    The module itself is served from resources/js through /js/nu-trace-mark.js:
    one copy for the app and for the test harness, no build step.

    The file's timestamp is part of its URL. Without it a browser keeps the
    copy it cached yesterday - the scanner would go on using an older check
    after a deployment and keep refusing stickers the new one accepts.
--}}
@php($markVersion = @filemtime(resource_path('js/nu-trace-mark.js')) ?: 1)
<script>
    window.NUTraceQrMarkConfig = {
        // Root-relative on purpose: the artwork is then fetched from whatever
        // host and scheme served the page, so it can never be blocked as mixed
        // content on the https site.
        logo: @json('/'.ltrim((string) config('qr.seal_logo', 'images/nu-trace-mark.jpg'), '/')),
        requireSeal: @json((bool) config('qr.require_seal', true))
    };
</script>
<script src="{{ url('/js/nu-trace-mark.js') }}?v={{ $markVersion }}"></script>
