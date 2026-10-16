{{--
    Extra styling for the Executive Director review pages.

    They build on auth.partials.theme (the same navy-and-gold card the sign-in
    and verification screens use), so there is one place for the app's look and
    this file only adds what those screens never needed: a wider page, a
    details list, an asset table and the decision options.

    Include after auth.partials.theme:
        @include('executive_review.partials.theme')
--}}
<style>
    /* The review needs more room than a sign-in card: it carries a whole
       transfer, a table of assets and the decision itself. */
    .wrapper--wide { max-width: 880px; }

    .review-head { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-start; justify-content: space-between; }

    .pill {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .6rem; border-radius: 999px;
        border: 1px solid var(--line); background: var(--paper-2); color: var(--navy-900);
        font-size: .68rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
        white-space: nowrap;
    }
    .pill--gold { background: var(--gold-100); border-color: #EAD9B4; color: var(--gold-ink); }
    .pill--green { background: var(--forest-tint); border-color: #BFDEC7; color: #245C3B; }
    .pill--red { background: var(--brick-tint); border-color: #E7C9C1; color: #7E2E27; }

    /* Recorded details, in the order a person reads them. */
    .meta {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .1rem 1.4rem;
        margin: 1.15rem 0 1.5rem; padding: .9rem 0;
        border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);
    }
    .meta-item { padding: .4rem 0; }
    .meta-item dt {
        font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        color: var(--ink-400);
    }
    .meta-item dd { margin-top: .15rem; font-size: .92rem; font-weight: 600; color: var(--navy-900); word-break: break-word; }
    .meta-item dd span { display: block; font-weight: 500; font-size: .8rem; color: var(--ink-600); margin-top: .1rem; }

    .section-title {
        font-family: 'Fraunces', Georgia, serif; font-size: 1.05rem; font-weight: 600;
        color: var(--navy-900); margin-bottom: .7rem;
    }

    .table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 12px; }
    table.assets { width: 100%; border-collapse: collapse; font-size: .86rem; }
    table.assets th {
        text-align: left; background: #FBF7EA; color: var(--ink-600);
        font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        padding: .7rem .8rem; border-bottom: 1px solid var(--line); white-space: nowrap;
    }
    table.assets td { padding: .7rem .8rem; border-bottom: 1px solid #EFEADB; vertical-align: top; color: var(--navy-900); }
    table.assets tr:last-child td { border-bottom: none; }
    table.assets td.pick { width: 3.2rem; text-align: center; }
    table.assets input[type="checkbox"] { width: auto; accent-color: var(--gold-600); }

    .tag { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; letter-spacing: .02em; }
    .muted { color: var(--ink-600); font-size: .8rem; font-weight: 500; }

    .note {
        margin: .9rem 0 1.2rem; padding: .7rem .85rem;
        background: var(--paper-2); border-left: 3px solid var(--gold-500); border-radius: 8px;
        font-size: .82rem; line-height: 1.55; color: var(--navy-900);
    }
    .note strong { font-weight: 700; }

    /* Decision options: one card per choice, so the consequence is written next
       to the button that causes it. */
    .option {
        display: block; position: relative;
        border: 1px solid var(--line); border-radius: 12px;
        padding: .85rem .95rem; margin-bottom: .6rem; cursor: pointer;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
    }
    .option:hover { border-color: #CFC4A4; }
    .option.is-active { border-color: var(--gold-500); background: #FDFAF1; box-shadow: 0 0 0 3px rgba(201, 162, 39, .14); }
    .option.is-muted { opacity: .6; }
    .option input[type="radio"] { width: auto; margin-right: .5rem; accent-color: var(--gold-600); }
    .option .option-title { font-size: .92rem; font-weight: 700; color: var(--navy-900); }
    .option .option-desc { font-size: .8rem; line-height: 1.5; color: var(--ink-600); margin: .3rem 0 0 1.5rem; }

    .counter { font-size: .78rem; font-weight: 700; color: var(--gold-ink); margin: .6rem 0 1.2rem; }

    textarea {
        width: 100%; min-height: 96px; resize: vertical;
        padding: .8rem .95rem; background: #fff;
        border: 1px solid var(--line); border-radius: 10px;
        font-family: 'Inter', sans-serif; font-size: .92rem; color: var(--navy-900);
        outline: none; transition: border-color .15s ease, box-shadow .15s ease;
    }
    textarea::placeholder { color: #A8AFBC; }
    textarea:hover { border-color: #CFC4A4; }
    textarea:focus { border-color: var(--gold-500); box-shadow: 0 0 0 3px rgba(201, 162, 39, .18); }
    textarea:disabled { background: #F6F3EA; color: var(--ink-400); cursor: not-allowed; }

    .field-note { display: block; font-size: .78rem; color: var(--ink-600); margin-top: .45rem; line-height: 1.5; }

    /* What the recorded decision looked like, once there is one. */
    .outcome { border: 1px solid var(--line); border-radius: 12px; padding: .9rem 1rem; margin-bottom: 1.2rem; background: #FCFAF4; }
    .outcome--green { border-color: #BFDEC7; background: var(--forest-tint); }
    .outcome--red { border-color: #E7C9C1; background: var(--brick-tint); }
    .outcome h3 { font-family: 'Fraunces', Georgia, serif; font-size: 1rem; font-weight: 600; color: var(--navy-900); }
    .outcome p { font-size: .85rem; line-height: 1.55; color: var(--navy-900); margin-top: .35rem; }
    .outcome dl { margin-top: .6rem; font-size: .82rem; color: var(--navy-900); }
    .outcome dl div { display: flex; gap: .6rem; padding: .2rem 0; }
    .outcome dt { min-width: 8.5rem; color: var(--ink-600); }

    .footer-note { font-size: .78rem; line-height: 1.55; color: var(--ink-600); margin-top: 1.5rem; text-align: center; }

    @media (max-width: 720px) {
        .meta { grid-template-columns: 1fr; }
        .outcome dl div { display: block; }
        .outcome dt { min-width: 0; }
    }
</style>
