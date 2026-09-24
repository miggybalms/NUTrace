{{--
    Shared NU Trace brand block for the auth screens.
    Styling lives in auth.partials.theme (.auth-brand*).

    Usage:
        @include('auth.partials.brand')
--}}
<div class="auth-brand">
    <div class="auth-brand-mark">
        @include('partials.logo', ['logoAlt' => 'NU Trace logo', 'logoAutoSize' => true, 'logoClass' => null])
    </div>
    <div>
        <h1 class="auth-brand-name">NU Trace</h1>
        <p class="auth-brand-tag">NU Lipa &middot; University Asset Management</p>
    </div>
</div>
