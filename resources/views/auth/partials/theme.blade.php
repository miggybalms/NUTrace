{{--
    Shared NU Trace stylesheet for the auth screens (login, register, forgot
    password, verify code, reset password).

    Replaces what used to be five separately maintained <style> blocks, so every
    screen in the flow stays visually identical and only needs updating here.

    Include it inside <head>:
        @include('auth.partials.theme')
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
<style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        /* NU Trace palette - same navy + warm gold as the rest of the system */
        --navy-950: #0A1830;
        --navy-900: #0F2143;
        --navy-800: #142442;
        --navy-700: #15305B;
        --gold-500: #C9A227;
        --gold-400: #E0BC44;
        --gold-600: #A8841E;
        --gold-100: #F3E7C4;
        --gold-text: #E9C766;
        --ink-900: #24334F;
        --ink-600: #5B6678;
        --ink-400: #8991A0;
        --line: #DED2AE;
        --paper-2: #EFE9D8;
        --forest: #2F7A4D;
        --forest-tint: #EAF4EE;
        --brick: #A23B32;
        --brick-tint: #F7E9E6;

        /* Aliases kept so existing markup and partials keep working */
        --blue: var(--navy-900);
        --blue-mid: var(--navy-800);
        --blue-light: var(--navy-700);
        --gold: var(--gold-500);
        --gold-light: var(--gold-400);
        --dark: var(--navy-900);
        --white: #ffffff;
        --muted: var(--ink-600);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background:
            radial-gradient(900px 480px at 50% -8%, rgba(201, 162, 39, .18), transparent 70%),
            linear-gradient(160deg, var(--navy-950) 0%, var(--navy-800) 55%, var(--navy-700) 100%);
        background-attachment: fixed;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1.25rem;
        color: var(--navy-900);
        -webkit-font-smoothing: antialiased;
    }

    .wrapper {
        width: 100%;
        max-width: 460px;
        display: flex;
        flex-direction: column;
    }

    /* ---------- Brand (rendered by auth.partials.brand) ---------- */
    .auth-brand { display: flex; align-items: center; gap: .85rem; margin-bottom: 1.35rem; }
    .auth-brand-mark {
        width: 46px; height: 46px; border-radius: 13px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, var(--gold-500), #8f7015);
        color: var(--navy-950); font-size: 1.35rem;
        box-shadow: 0 10px 22px -12px rgba(201, 162, 39, .9);
    }
    .auth-brand-name {
        font-family: 'Fraunces', Georgia, serif; font-size: 1.45rem; font-weight: 600;
        color: #F3EFE3; line-height: 1.1; letter-spacing: .01em;
    }
    .auth-brand-tag { font-size: .78rem; color: var(--ink-400); letter-spacing: .045em; margin-top: .2rem; }

    /* ---------- Card ---------- */
    .card {
        position: relative;
        background: #ffffff;
        border: 1px solid rgba(201, 162, 39, .32);
        border-radius: 18px;
        padding: 2rem 1.75rem;
        width: 100%;
        overflow: hidden;
        box-shadow: 0 26px 60px -34px rgba(0, 0, 0, .7), 0 2px 6px rgba(10, 24, 48, .18);
    }
    .card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, transparent, var(--gold-500) 18%, var(--gold-500) 82%, transparent);
    }

    .step-title {
        font-family: 'Fraunces', Georgia, serif; font-size: 1.5rem; font-weight: 600;
        color: var(--navy-900); line-height: 1.2; margin-bottom: .4rem;
    }
    .step-desc { color: var(--ink-600); font-size: .9rem; line-height: 1.55; margin-bottom: 1.5rem; }
    /* The email address shown back to the user (verify / forgot screens) */
    .step-desc strong { color: var(--gold-text); font-weight: 600; word-break: break-all; }

    /* ---------- Form ---------- */
    .form-group { margin-bottom: 1.15rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    label {
        display: block;
        color: var(--navy-900);
        font-size: .875rem;
        font-weight: 600;
        letter-spacing: .01em;
        margin-bottom: .45rem;
    }

    .required { color: var(--brick); }

    input {
        width: 100%;
        padding: .8rem .95rem;
        background: #ffffff;
        border: 1px solid var(--line);
        border-radius: 10px;
        font-size: .95rem;
        font-family: 'Inter', sans-serif;
        color: var(--navy-900);
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    input::placeholder { color: #A8AFBC; }
    input:hover { border-color: #CFC4A4; }
    input:focus { border-color: var(--gold-500); box-shadow: 0 0 0 3px rgba(201, 162, 39, .18); }
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 1000px #FBF7EA inset;
        -webkit-text-fill-color: var(--navy-900);
    }

    input[type="file"] { padding: .55rem .7rem; font-size: .85rem; color: var(--ink-600); background: #FCFAF4; cursor: pointer; }
    input[type="file"]::file-selector-button {
        margin-right: .7rem; padding: .45rem .8rem; border: none; border-radius: 8px;
        background: var(--gold-100); color: var(--navy-900); font-weight: 600; font-size: .8rem; cursor: pointer;
    }

    input.code-input {
        text-align: center;
        font-size: 1.6rem;
        font-weight: 700;
        letter-spacing: .6rem;
        text-indent: .6rem;
        font-family: 'Inter', monospace;
    }

    .hint { display: block; color: var(--ink-600); font-size: .8rem; line-height: 1.5; margin-top: .5rem; }
    .field-error { color: var(--brick); font-size: .8rem; line-height: 1.45; margin-top: .45rem; }

    /* ---------- Password field ---------- */
    .password-input-wrapper { position: relative; }
    .password-input-wrapper input { padding-right: 2.9rem; }
    .password-toggle {
        position: absolute;
        right: .45rem;
        top: 50%;
        transform: translateY(-50%);
        display: flex; align-items: center; justify-content: center;
        background: none; border: none; border-radius: 8px;
        color: var(--ink-400); font-size: 1.15rem;
        padding: .4rem; cursor: pointer;
        transition: color .15s ease, background .15s ease;
    }
    .password-toggle:hover { color: var(--gold-600); background: var(--paper-2); }

    /* ---------- Buttons ---------- */
    .btn-row { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 1.5rem; }
    /* Grow to fill the row, but never squeeze a label onto a second line:
       the wider label ("Send Verification Code") simply takes more width. */
    .btn-row > * { flex: 1 1 auto; min-width: 8.5rem; white-space: nowrap; }

    /* Secondary way back, stacked under the primary action and full width so a
       screen never offers two rival "back" controls side by side. */
    .back-row { display: flex; flex-wrap: wrap; margin-top: .7rem; }
    .back-row > * { flex: 1 1 auto; min-width: 8.5rem; white-space: nowrap; }

    .btn-login,
    .btn-primary,
    .btn-signup {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        background: var(--gold-500); color: var(--navy-950);
        font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 600;
        padding: .85rem 1.25rem; border: none; border-radius: 10px;
        text-decoration: none; cursor: pointer;
        transition: background .15s ease, transform .08s ease;
    }
    .btn-login:hover,
    .btn-primary:hover,
    .btn-signup:hover { background: var(--gold-400); }
    .btn-login:active,
    .btn-primary:active,
    .btn-signup:active { transform: translateY(1px); }

    .btn-back,
    .back-link {
        display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
        background: #ffffff; color: var(--ink-600);
        font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 600;
        padding: .85rem 1.25rem; border: 1px solid var(--gold-500); border-radius: 10px;
        text-decoration: none; cursor: pointer;
        transition: background .15s ease;
    }
    .btn-back:hover,
    .back-link:hover { background: var(--paper-2); }

    /* ---------- Links ---------- */
    .forgot,
    .login-link,
    .register-link {
        color: var(--gold-600);
        font-size: .875rem;
        font-weight: 600;
        text-decoration: none;
    }
    .forgot { display: inline-block; margin-top: .55rem; }
    .register-link,
    .login-link { display: block; text-align: center; margin-top: 1.35rem; }
    .forgot:hover,
    .login-link:hover,
    .register-link:hover { text-decoration: underline; color: var(--navy-900); }

    /* ---------- Alerts ---------- */
    .success-msg,
    .error-msg {
        border-radius: 10px;
        padding: .75rem .9rem;
        font-size: .85rem;
        line-height: 1.45;
        margin-bottom: 1.15rem;
    }
    .success-msg { background: var(--forest-tint); color: #245C3B; border: 1px solid #BFDEC7; }
    .error-msg { background: var(--brick-tint); color: #7E2E27; border: 1px solid #E7C9C1; }
    .error-msg span { display: block; }
    .error-msg span + span { margin-top: .3rem; }
    .resend-note { color: var(--ink-600); font-size: .85rem; line-height: 1.5; margin-top: 1.1rem; text-align: center; }
    .resend-note form { display: inline; }
    .resend-note button {
        background: none;
        border: none;
        padding: 0;
        color: var(--gold-text);
        font-family: inherit;
        font-size: .85rem;
        font-weight: 600;
        cursor: pointer;
    }
    .resend-note button:hover { text-decoration: underline; }

    /* ---------- Legacy brand classes (kept so nothing breaks) ---------- */
    .logo-caption { display: none; }
    .site-name { display: none; }

    /* ---------- Accessibility + responsive ---------- */
    a:focus-visible,
    button:focus-visible,
    input:focus-visible { outline: 2px solid var(--gold-500); outline-offset: 2px; }

    @media (max-width: 480px) {
        body { padding: 1.5rem 1rem; }
        .card { padding: 1.6rem 1.2rem; border-radius: 14px; }
        .form-row { grid-template-columns: 1fr; gap: 0; }
        .btn-row { flex-direction: column; }
        .back-row { flex-direction: column; }
        .auth-brand-mark { width: 40px; height: 40px; border-radius: 11px; font-size: 1.15rem; }
        .auth-brand-name { font-size: 1.25rem; }
        .auth-brand { gap: .7rem; margin-bottom: 1.1rem; }
        .step-title { font-size: 1.3rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        * { transition: none !important; animation: none !important; }
    }
</style>
