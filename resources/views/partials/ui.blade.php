{{--
    NU Trace design layer.

    One stylesheet that gives every screen the same visual language. The palette
    is unchanged (navy #0A1830 / #142442, gold #C9A227, warm ivory #F3EEE0); what
    changes is how it is applied:

      · Surfaces are separated by crisp ink-tinted hairlines and a layered,
        navy-tinted elevation scale rather than flat beige outlines, so cards
        read as objects sitting on the page instead of drawn boxes.
      · One radius scale and one control height replace the mix of rounded-lg /
        rounded-xl / rounded-2xl and ad-hoc paddings.
      · Gold is an accent again — hairlines, focus rings, small rails, icon
        glyphs — instead of large saturated fills.
      · Metrics use tabular figures so numbers stop jittering between rows.
      · Motion is a calm 150ms ease-out; the old translate-on-hover tricks that
        made lists feel unsteady are gone.

    Two scopes, deliberately:

      · Rules prefixed with `html body` re-tune Tailwind's own primitives. They
        outrank a single utility class, which is what lets 140+ hard-coded
        `border-[#DED2AE]` and the whole `shadow-*` scale be corrected from one
        place with no markup change.
      · Rules prefixed with `.nt-ui` are the in-app component language. The
        marker only sits on the signed-in shells (see the <body> tags), so the
        auth screens and the marketing pages — which draw `.card` and buttons on
        navy and need different treatment — are never touched by it.

    Included last in <head> of every document root:
        @include('partials.ui')
--}}
<style>
    /* ================================================================
       1. Palette tokens — the existing NU Trace palette, nothing new
       ================================================================ */
    :root {
        /* Navy ramp */
        --nt-navy-950: #0A1830;
        --nt-navy-900: #0F2143;
        --nt-navy-850: #142442;
        --nt-navy-800: #15305B;
        --nt-navy-750: #1D3F73;
        --nt-navy-700: #111B2E;
        --nt-navy-600: #1C2740;

        /* Gold ramp */
        --nt-gold-600: #A8841E;
        --nt-gold-500: #C9A227;
        --nt-gold-400: #E0BC44;
        --nt-gold-300: #E9C766;
        --nt-gold-100: #F3E7C4;
        --nt-gold-tint: #FBF1DE;

        /* Warm neutrals */
        --nt-paper: #F3EEE0;
        --nt-paper-2: #EFE9D8;
        --nt-paper-3: #F5F0E2;
        --nt-cream: #F3EFE3;

        /* Ink ramp */
        --nt-ink-900: #1A2233;
        --nt-ink-800: #24334F;
        --nt-ink-700: #46536B;
        --nt-ink-600: #5B6678;
        --nt-ink-500: #7C86A0;
        --nt-ink-400: #8991A0;
        --nt-ink-300: #B7BFD4;

        /* Status */
        --nt-forest: #2F7A4D;
        --nt-forest-dark: #245C3B;
        --nt-forest-tint: #EAF4EE;
        --nt-bronze: #B4791E;
        --nt-bronze-dark: #8F5F16;
        --nt-bronze-tint: #FBF1DE;
        --nt-brick: #A23B32;
        --nt-brick-dark: #7E2E27;
        --nt-brick-tint: #F7E9E6;
        --nt-steel: #2E5C8A;
        --nt-steel-tint: #E9F0F7;

        /* --- Derived: hairlines, elevation, geometry, focus --- */
        --nt-hair: rgba(16, 34, 64, .10);
        --nt-hair-soft: rgba(16, 34, 64, .065);
        --nt-hair-strong: rgba(16, 34, 64, .16);

        --nt-e1: 0 1px 2px rgba(10, 24, 48, .05);
        --nt-e2: 0 1px 2px rgba(10, 24, 48, .04), 0 8px 20px -12px rgba(10, 24, 48, .18);
        --nt-e3: 0 2px 4px rgba(10, 24, 48, .05), 0 16px 32px -16px rgba(10, 24, 48, .22);
        --nt-e4: 0 8px 16px -8px rgba(10, 24, 48, .14), 0 32px 64px -24px rgba(10, 24, 48, .30);

        --nt-r-card: 14px;
        --nt-r-control: 10px;
        --nt-ring: 0 0 0 3px rgba(201, 162, 39, .30);

        /* The lifecycle accents the dashboard legends already use */
        --nt-accent-acquired: #2E5C8A;
        --nt-accent-active: #2F7A4D;
        --nt-accent-checking: #6B4C82;
        --nt-accent-repair: #B4791E;
        --nt-accent-replace: #A23B32;
        --nt-accent-pullout: #46536B;
        --nt-accent-disposed: #7E2E27;
    }

    /* ================================================================
       2. Base refinements — additive only, safe on every page
       ================================================================ */
    html { -webkit-text-size-adjust: 100%; }

    body {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    ::selection { background: rgba(201, 162, 39, .28); color: var(--nt-navy-950); }

    /* Keyboard affordance: a gold ring, never on mouse clicks */
    :focus-visible {
        outline: 2px solid var(--nt-gold-500);
        outline-offset: 2px;
        border-radius: 4px;
    }

    /* Slim, warm scrollbars so they stop fighting the ivory canvas */
    * { scrollbar-width: thin; scrollbar-color: rgba(16, 34, 64, .20) transparent; }
    *::-webkit-scrollbar { width: 10px; height: 10px; }
    *::-webkit-scrollbar-track { background: transparent; }
    *::-webkit-scrollbar-thumb {
        background: rgba(16, 34, 64, .18);
        border: 3px solid transparent;
        border-radius: 999px;
        background-clip: content-box;
    }
    *::-webkit-scrollbar-thumb:hover { background: rgba(16, 34, 64, .30); background-clip: content-box; }
    *::-webkit-scrollbar-corner { background: transparent; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
        }
    }

    /* ================================================================
       3. Re-tune Tailwind primitives (scoped to beat a single utility)
       ================================================================ */

    /* Hairlines — the heavy beige outlines become crisp ink-tinted rules */
    html body .border-\[\#DED2AE\] { border-color: var(--nt-hair); }
    html body .border-\[\#EFE9D8\] { border-color: var(--nt-hair-soft); }
    html body .border-\[\#CFC4A4\] { border-color: var(--nt-hair); }
    html body .border-\[\#EAD9B4\] { border-color: rgba(201, 162, 39, .30); }
    html body .border-\[\#E3D6B0\] { border-color: rgba(201, 162, 39, .26); }
    html body .border-\[\#EADFC0\] { border-color: rgba(201, 162, 39, .26); }

    html body .divide-\[\#EFE9D8\] > :not([hidden]) ~ :not([hidden]) { border-color: var(--nt-hair-soft); }
    html body .divide-\[\#F0EADA\] > :not([hidden]) ~ :not([hidden]) { border-color: var(--nt-hair-soft); }

    /* Elevation — override the shadow variable so Tailwind's own ring
       composition keeps working, and the shadows turn navy instead of grey */
    html body .shadow-sm { --tw-shadow: var(--nt-e2); --tw-shadow-colored: var(--nt-e2); }
    html body .shadow { --tw-shadow: var(--nt-e2); --tw-shadow-colored: var(--nt-e2); }
    html body .shadow-md { --tw-shadow: var(--nt-e3); --tw-shadow-colored: var(--nt-e3); }
    html body .shadow-lg { --tw-shadow: var(--nt-e3); --tw-shadow-colored: var(--nt-e3); }
    html body .shadow-xl { --tw-shadow: var(--nt-e4); --tw-shadow-colored: var(--nt-e4); }
    html body .shadow-2xl { --tw-shadow: var(--nt-e4); --tw-shadow-colored: var(--nt-e4); }

    /* Figures stop jittering between rows */
    html body .tabular-nums,
    .nt-ui .font-mono,
    .nt-ui .text-2xl,
    .nt-ui .text-3xl,
    .nt-ui .text-4xl,
    .nt-ui .text-5xl { font-variant-numeric: tabular-nums; }

    /* ================================================================
       4. In-app component language
       ================================================================ */

    /* ---- Surfaces ------------------------------------------------- */
    .nt-ui .card,
    .nt-ui .stat-card,
    .nt-ui .asset-card,
    .nt-ui .quicklink-tile,
    .nt-ui .acc-tile,
    .nt-ui .duo-card,
    .nt-ui .section-card,
    .nt-ui .panel,
    .nt-ui .table-container {
        border-color: var(--nt-hair);
        border-radius: var(--nt-r-card);
        box-shadow: var(--nt-e2);
        background-color: #fff;
    }

    /* A gold hairline across the top marks the primary/hero surface without
       flooding the card with gold the way a solid fill does. */
    .nt-ui .card-accent,
    .nt-ui .stat-card-accent { box-shadow: var(--nt-e2), inset 0 2px 0 0 var(--nt-gold-500); }

    /* Cards you can act on lift slightly instead of sliding sideways */
    .nt-ui .stat-card,
    .nt-ui .asset-card,
    .nt-ui .quicklink-tile,
    .nt-ui .acc-tile {
        transition: box-shadow .15s ease-out, border-color .15s ease-out, transform .15s ease-out;
    }
    .nt-ui .stat-card:hover,
    .nt-ui .asset-card:hover,
    .nt-ui .quicklink-tile:hover,
    .nt-ui .acc-tile:hover {
        box-shadow: var(--nt-e3);
        border-color: rgba(16, 34, 64, .16);
        transform: translateY(-1px);
    }

    /* Rows: a quiet tint on hover, no lateral movement */
    .nt-ui .request-item,
    .nt-ui .request-row,
    .nt-ui .asset-row {
        transition: background-color .15s ease-out, border-color .15s ease-out;
    }
    .nt-ui .request-item:hover,
    .nt-ui .request-row:hover,
    .nt-ui .asset-row:hover {
        background-color: var(--nt-paper-3);
        border-color: var(--nt-hair);
        transform: none;
    }

    /* ---- Type ----------------------------------------------------- */
    .nt-ui .font-display { letter-spacing: -.015em; }
    .nt-ui .eyebrow {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--nt-ink-500);
    }
    .nt-ui .section-head {
        font-size: 15px;
        font-weight: 600;
        letter-spacing: -.01em;
        color: var(--nt-navy-950);
    }
    .nt-ui .meta { color: var(--nt-ink-600); }
    .nt-ui .detail-field .detail-label,
    .nt-ui .detail-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--nt-ink-500);
    }

    /* ---- Badges --------------------------------------------------- */
    .nt-ui .status-badge,
    .nt-ui .tag {
        border-radius: 999px;
        font-weight: 600;
        letter-spacing: .01em;
        border-width: 1px;
        border-style: solid;
    }

    /* ---- Controls ------------------------------------------------- */
    .nt-ui .btn-gold,
    .nt-ui .btn-ghost,
    .nt-ui .btn-primary,
    .nt-ui .submit-btn,
    .nt-ui .filter-btn {
        border-radius: var(--nt-r-control);
        font-weight: 600;
        letter-spacing: .005em;
        transition: background-color .15s ease-out, border-color .15s ease-out,
                    box-shadow .15s ease-out, color .15s ease-out;
    }

    /* Gold stays the primary action, minus the heavy drop shadow */
    .nt-ui .btn-gold,
    .nt-ui .btn-primary,
    .nt-ui .submit-btn {
        box-shadow: var(--nt-e1);
    }
    .nt-ui .btn-gold:hover,
    .nt-ui .btn-primary:hover,
    .nt-ui .submit-btn:hover {
        box-shadow: var(--nt-e2);
        transform: none;
    }

    .nt-ui .filter-tab,
    .nt-ui .filter-chip,
    .nt-ui .tab-btn {
        border-radius: 999px;
        font-weight: 500;
        transition: background-color .15s ease-out, color .15s ease-out, border-color .15s ease-out;
    }

    /* Fields: one height, one radius, a gold ring on focus */
    .nt-ui .form-input,
    .nt-ui .search-input,
    .nt-ui input[type="text"],
    .nt-ui input[type="email"],
    .nt-ui input[type="password"],
    .nt-ui input[type="number"],
    .nt-ui input[type="search"],
    .nt-ui input[type="tel"],
    .nt-ui input[type="date"],
    .nt-ui select,
    .nt-ui textarea {
        border-radius: var(--nt-r-control);
        border-color: var(--nt-hair-strong);
        transition: border-color .15s ease-out, box-shadow .15s ease-out;
    }
    .nt-ui .form-input:focus,
    .nt-ui .search-input:focus,
    .nt-ui input[type="text"]:focus,
    .nt-ui input[type="email"]:focus,
    .nt-ui input[type="password"]:focus,
    .nt-ui input[type="number"]:focus,
    .nt-ui input[type="search"]:focus,
    .nt-ui input[type="tel"]:focus,
    .nt-ui input[type="date"]:focus,
    .nt-ui select:focus,
    .nt-ui textarea:focus {
        outline: none;
        border-color: var(--nt-gold-500);
        box-shadow: var(--nt-ring);
    }
    .nt-ui ::placeholder { color: var(--nt-ink-400); }

    /* ---- Chrome: top bars ----------------------------------------- */
    .nt-ui .topbar,
    .nt-ui .admin-topbar,
    .nt-ui .top {
        background-color: rgba(255, 255, 255, .88);
        -webkit-backdrop-filter: saturate(160%) blur(10px);
        backdrop-filter: saturate(160%) blur(10px);
        border-bottom: 1px solid var(--nt-hair);
        box-shadow: none;
    }

    /* ---- Chrome: sidebar ------------------------------------------ */
    .nt-ui .sidebar-item {
        border-left-width: 0;
        border-radius: 10px;
        transition: background-color .15s ease-out, color .15s ease-out;
        position: relative;
    }
    .nt-ui .sidebar-item::before {
        content: "";
        position: absolute;
        left: -10px;
        top: 50%;
        width: 3px;
        height: 0;
        border-radius: 0 3px 3px 0;
        background: var(--nt-gold-500);
        transform: translateY(-50%);
        transition: height .15s ease-out;
    }
    .nt-ui .sidebar-item:hover { background-color: rgba(255, 255, 255, .06); }
    .nt-ui .sidebar-item.active {
        background-color: rgba(201, 162, 39, .14);
        color: var(--nt-gold-300);
    }
    .nt-ui .sidebar-item.active::before { height: 60%; }
    .nt-ui .sidebar-item.active i { color: var(--nt-gold-300); }

    /* ---- Tables --------------------------------------------------- */
    .nt-ui .table-container thead th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--nt-ink-500);
        background-color: var(--nt-paper-2);
        border-bottom: 1px solid var(--nt-hair);
    }
    .nt-ui .table-container tbody tr { border-bottom: 1px solid var(--nt-hair-soft); }
    .nt-ui .table-container tbody tr:last-child { border-bottom: 0; }
    .nt-ui .table-container tbody tr:hover { background-color: var(--nt-paper-3); }

    /* ---- Modals --------------------------------------------------- */
    .nt-ui .modal {
        -webkit-backdrop-filter: blur(3px);
        backdrop-filter: blur(3px);
    }
    .nt-ui .modal-panel {
        border-radius: var(--nt-r-card);
        border: 1px solid var(--nt-hair);
        box-shadow: var(--nt-e4);
    }
    .nt-ui .modal-head {
        border-bottom: 1px solid var(--nt-hair);
        letter-spacing: -.01em;
    }

    /* ---- Empty states --------------------------------------------- */
    .nt-ui .empty-state i,
    .nt-ui .empty i { color: var(--nt-gold-500); }

    /* ---- Upload / drop areas -------------------------------------- */
    .nt-ui .upload-area {
        border-radius: var(--nt-r-card);
        border-color: var(--nt-hair-strong);
        background-color: var(--nt-paper-3);
        transition: border-color .15s ease-out, background-color .15s ease-out;
    }
    .nt-ui .upload-area:hover {
        border-color: var(--nt-gold-500);
        background-color: var(--nt-gold-tint);
    }
</style>
