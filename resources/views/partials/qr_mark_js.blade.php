{{--
    The NU Trace seal, on every screen that draws or scans a sticker.

    window.NUTraceQrMarkConfig is what the module reads: where the official logo
    lives, and whether a scan whose centre mark cannot be confirmed has to be
    refused. That second value is a server setting (QR_REQUIRE_SEAL) so turning
    it off while old stickers are still in circulation is a deployment decision,
    not something a browser can choose.

    The module itself is served from resources/js through /js/nu-trace-mark.js:
    one copy for the app and for the test harness, no build step.
--}}
<script>
    window.NUTraceQrMarkConfig = {
        logo: @json(url('/images/nu-trace-mark.jpg')),
        requireSeal: @json((bool) config('qr.require_seal', true))
    };
</script>
<script src="{{ url('/js/nu-trace-mark.js') }}"></script>
