<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learn More - NU TRACE Asset Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --blue: #1a3a6b;
            --blue-mid: #1e4d8c;
            --blue-light: #2563b0;
            --navy-dark: #0d1f3c;
            --gold: #c9a227;
            --gold-light: #e2b93b;
            --gold-pale: #fdf3d0;
            --dark: #0d1f3c;
            --white: #ffffff;
            --gray-bg: #f4f7fb;
            --cream: #fdf9ee;
            --muted: #5a6a85;
            --line: #e7dfc8;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--white);
            color: var(--dark);
            overflow-x: hidden;
        }

        /* NAVBAR */
        nav {
            background: var(--blue);
            padding: 0 2.5rem;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 16px rgba(13,31,60,0.25);
            border-bottom: 3px solid var(--gold);
        }

        .brand-left {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
        }

        /* Theme-recoloured mark with its background keyed out, so it sits on
           this nav navy with no rectangle behind it. */
        .brand-mark { width: 38px; height: auto; display: block; flex: 0 0 auto; }

        .brand-text { display: flex; align-items: baseline; gap: 0.6rem; }

        .brand-name {
            font-family: 'Bebas Neue', sans-serif;
            color: var(--gold);
            font-size: 1.5rem;
            letter-spacing: 2px;
            -webkit-text-stroke: 0.5px #a07d10;
        }

        .brand-tag {
            color: rgba(255,255,255,0.65);
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.04em;
        }

        .nav-actions { display: flex; align-items: center; gap: 1rem; }

        .btn-signup {
            color: var(--white);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-signup:hover { background: rgba(255,255,255,0.12); }

        .btn-login {
            background: var(--gold);
            color: var(--dark);
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.55rem 1.6rem;
            border-radius: 999px;
            transition: background 0.2s, transform 0.15s;
            box-shadow: 0 2px 10px rgba(201,162,39,0.35);
        }

        .btn-login:hover { background: var(--gold-light); transform: translateY(-1px); }

        /* HERO */
        .page-hero {
            padding: 3.5rem 2.5rem 3rem;
            text-align: center;
            background: linear-gradient(135deg, #eef3fb 0%, #fdf8e8 100%);
            border-bottom: 1px solid var(--line);
        }

        .breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 1.1rem;
        }

        .breadcrumb a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .breadcrumb a:hover { text-decoration: underline; }

        .page-hero h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.6rem, 5vw, 4rem);
            letter-spacing: 2px;
            color: var(--blue);
            line-height: 1.05;
            margin-bottom: 0.9rem;
        }

        .page-hero h1 span { color: var(--gold); -webkit-text-stroke: 1px #a07d10; }

        .page-hero p {
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 640px;
            margin: 0 auto;
        }

        /* SECTION SHELL */
        .section {
            padding: 3.5rem 2.5rem;
        }

        .section-head {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 2.75rem;
        }

        .section-head .kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--gold-pale);
            color: var(--gold);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            border: 1px solid rgba(201,162,39,0.3);
            margin-bottom: 0.9rem;
        }

        .section-head h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2rem, 4vw, 2.9rem);
            letter-spacing: 1.5px;
            color: var(--blue);
            margin-bottom: 0.7rem;
        }

        .section-head h2 em { color: var(--gold); font-style: normal; }

        .section-head p { color: var(--muted); font-size: 0.98rem; line-height: 1.7; }

        .section.alt { background: var(--cream); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .section.navy { background: var(--blue); }

        .section.navy .section-head h2 { color: var(--white); }
        .section.navy .section-head .kicker { background: rgba(201,162,39,0.15); border-color: rgba(201,162,39,0.45); color: var(--gold-light); }
        .section.navy .section-head p { color: #b7c7de; }

        /* LIFECYCLE */
        .lifecycle-flow {
            display: flex;
            align-items: stretch;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.6rem;
            max-width: 1080px;
            margin: 0 auto 2.2rem;
        }

        .lc-step {
            background: var(--white);
            border: 1px solid var(--line);
            border-top: 4px solid var(--gold);
            border-radius: 14px;
            padding: 1.1rem 1.4rem;
            text-align: center;
            min-width: 128px;
            flex: 1 1 128px;
            box-shadow: 0 4px 14px rgba(26,58,107,0.06);
        }

        .lc-step .num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--gold-pale);
            color: var(--gold);
            font-size: 0.78rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .lc-step h4 { font-size: 0.95rem; font-weight: 700; color: var(--blue); letter-spacing: 0.02em; }
        .lc-step p { font-size: 0.76rem; color: var(--muted); margin-top: 0.25rem; line-height: 1.45; }

        .lc-arrow {
            display: flex;
            align-items: center;
            color: var(--gold);
            font-size: 1.25rem;
            align-self: center;
        }

        .lc-note {
            max-width: 1080px;
            margin: 0 auto;
            display: flex;
            gap: 0.9rem;
            background: var(--gold-pale);
            border: 1px solid rgba(201,162,39,0.35);
            border-radius: 14px;
            padding: 1.1rem 1.4rem;
        }

        .lc-note i { color: var(--gold); font-size: 1.4rem; flex-shrink: 0; margin-top: 0.1rem; }
        .lc-note strong { color: var(--blue); }
        .lc-note p { color: var(--muted); font-size: 0.92rem; line-height: 1.6; }

        /* FEATURE CARDS */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 1080px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.8rem 1.6rem;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 34px rgba(26,58,107,0.12);
            border-color: var(--gold);
        }

        .feature-card .icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: var(--gold-pale);
            border: 1px solid rgba(201,162,39,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--gold);
            margin-bottom: 1rem;
        }

        .feature-card h3 { font-size: 1.05rem; font-weight: 700; color: var(--blue); margin-bottom: 0.5rem; }
        .feature-card p { font-size: 0.9rem; color: var(--muted); line-height: 1.65; }

        /* ACCOUNTABILITY TILES */
        .acc-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.3rem;
            max-width: 1080px;
            margin: 0 auto;
        }

        .acc-tile {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--line);
            border-bottom: 4px solid var(--gold);
            padding: 1.6rem 1.4rem;
            text-align: center;
            box-shadow: 0 4px 14px rgba(26,58,107,0.05);
        }

        .acc-tile i { font-size: 1.9rem; color: var(--gold); display: block; margin-bottom: 0.7rem; }
        .acc-tile h4 { font-size: 1rem; font-weight: 700; color: var(--blue); margin-bottom: 0.35rem; }
        .acc-tile p { font-size: 0.85rem; color: var(--muted); line-height: 1.55; }

        /* TWO-COLUMN BLOCKS (audit + benefits band) */
        .duo {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1080px;
            margin: 0 auto;
            align-items: center;
        }

        .duo-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 2.2rem;
            box-shadow: 0 8px 26px rgba(26,58,107,0.07);
            border-top: 4px solid var(--gold);
        }

        .duo-card h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 1.7rem;
            letter-spacing: 1px;
            color: var(--blue);
            margin-bottom: 0.9rem;
        }

        .duo-card h3 em { color: var(--gold); font-style: normal; }

        .duo-card p { color: var(--muted); font-size: 0.95rem; line-height: 1.75; margin-bottom: 1rem; }

        .check-list { list-style: none; }
        .check-list li {
            display: flex;
            gap: 0.6rem;
            align-items: flex-start;
            padding: 0.42rem 0;
            color: var(--muted);
            font-size: 0.93rem;
            line-height: 1.55;
        }
        .check-list li i { color: var(--gold); margin-top: 0.2rem; flex-shrink: 0; }
        .check-list li strong { color: var(--blue); font-weight: 600; }

        /* BULK OPS */
        .bulk-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.6rem;
            max-width: 1080px;
            margin: 0 auto;
        }

        .bulk-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(26,58,107,0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .bulk-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px rgba(26,58,107,0.12); }

        .bulk-top { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.6rem; border-bottom: 1px solid var(--line); }

        .bulk-top .icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: var(--blue);
            color: var(--gold-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .bulk-top h3 { font-size: 1.05rem; font-weight: 700; color: var(--blue); }
        .bulk-top span { display: block; font-size: 0.78rem; color: var(--muted); font-weight: 500; }

        .bulk-body { padding: 1.3rem 1.6rem 1.6rem; }
        .bulk-body p { color: var(--muted); font-size: 0.92rem; line-height: 1.7; }

        /* WHO IS IT FOR */
        .audience-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1080px;
            margin: 0 auto;
        }

        .audience-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 2rem;
            border-top: 5px solid var(--gold);
            box-shadow: 0 8px 26px rgba(26,58,107,0.07);
        }

        .audience-card .icon { font-size: 2.1rem; color: var(--gold); margin-bottom: 0.8rem; }
        .audience-card h3 { font-size: 1.15rem; font-weight: 700; color: var(--blue); margin-bottom: 0.25rem; }
        .audience-card .role-sub { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--gold); margin-bottom: 1.1rem; }
        .audience-card ul { list-style: none; }
        .audience-card ul li {
            display: flex; gap: 0.6rem; align-items: flex-start;
            padding: 0.45rem 0;
            color: var(--muted);
            font-size: 0.93rem;
            line-height: 1.55;
        }
        .audience-card ul li i { color: var(--gold); margin-top: 0.2rem; flex-shrink: 0; }
        .audience-card ul li strong { color: var(--blue); font-weight: 600; }

        /* WHY BAND */
        .benefit-band { max-width: 1080px; margin: 0 auto; text-align: center; }

        .benefit-chips {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 0 auto 1.8rem;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(201,162,39,0.5);
            color: var(--gold-light);
            padding: 0.75rem 1.6rem;
            border-radius: 999px;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .chip i { font-size: 1.1rem; }

        .benefit-band p {
            color: #b7c7de;
            font-size: 1rem;
            line-height: 1.75;
            max-width: 620px;
            margin: 0 auto;
        }

        /* CTA */
        .cta-wrap {
            text-align: center;
            padding: 3rem 2rem 3.5rem;
            background: var(--navy-dark);
            border-top: 4px solid var(--gold);
        }

        .cta-wrap h2 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.2rem, 4.5vw, 3.2rem);
            letter-spacing: 2px;
            color: var(--white);
            margin-bottom: 0.7rem;
        }

        .cta-wrap h2 span { color: var(--gold); -webkit-text-stroke: 1px #a07d10; }

        .cta-wrap p { color: #a8bcd4; font-size: 1rem; margin-bottom: 1.9rem; }

        .cta-buttons { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }

        .btn-gold {
            background: var(--gold);
            color: var(--dark);
            font-weight: 700;
            font-size: 1.05rem;
            text-decoration: none;
            padding: 0.95rem 2.6rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s, transform 0.15s;
            box-shadow: 0 4px 18px rgba(201,162,39,0.4);
        }

        .btn-gold:hover { background: var(--gold-light); transform: translateY(-2px); }

        .btn-outline-light {
            background: transparent;
            border: 2px solid rgba(201,162,39,0.7);
            color: var(--gold-light);
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            padding: 0.9rem 2rem;
            border-radius: 999px;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-outline-light:hover { background: rgba(201,162,39,0.15); }

        /* FOOTER */
        footer {
            background: var(--navy-dark);
            text-align: center;
            padding: 1.5rem;
            font-size: 0.82rem;
            color: #6b85a8;
            border-top: 1px solid rgba(201,162,39,0.2);
        }

        footer span { color: var(--gold); }

        @media (max-width: 860px) {
            .card-grid, .acc-grid, .duo, .bulk-grid, .audience-grid { grid-template-columns: 1fr; }
            .lc-arrow { transform: rotate(90deg); padding: 0.2rem 0; }
            nav { padding: 0 1.25rem; }
            .section { padding: 2.5rem 1.25rem; }
            .page-hero { padding: 2.5rem 1.25rem 2rem; }
            .brand-tag { display: none; }
        }
    </style>
</head>
<body>

    <nav>
        <a href="/" class="brand-left">
            <img src="/images/logo-mark.png" alt="NU Trace logo" class="brand-mark">
            <span class="brand-text">
                <span class="brand-name">NU TRACE</span>
                <span class="brand-tag">Asset Management System</span>
            </span>
        </a>
        <div class="nav-actions">
            <a href="/register" class="btn-signup">Activate Account</a>
            <a href="/login" class="btn-login">Login</a>
        </div>
    </nav>

    <section class="page-hero">
        <div class="breadcrumb">
            <a href="/">Home</a>
            <i class="ri-arrow-right-s-line"></i>
            <span>Learn More</span>
        </div>
        <h1>EVERY ASSET.<br><span>TRACKED, ACCOUNTED, PROTECTED.</span></h1>
        <p>NU TRACE is NU Lipa's centralized asset management system — built to guide each institutional asset from the moment it is acquired until the day it is responsibly retired.</p>
    </section>

    <!-- 1. Manage the Asset Lifecycle -->
    <section class="section" id="lifecycle">
        <div class="section-head">
            <span class="kicker">The Journey</span>
            <h2>MANAGE THE <em>ASSET LIFECYCLE</em></h2>
            <p>Every asset moves through a clear, visible lifecycle. NU TRACE shows you exactly which stage each asset is in — and what needs to happen next.</p>
        </div>

        <div class="lifecycle-flow">
            <div class="lc-step"><span class="num">1</span><h4>Acquired</h4><p>The asset arrives and is registered with its own unique code.</p></div>
            <div class="lc-arrow"><i class="ri-arrow-right-line"></i></div>
            <div class="lc-step"><span class="num">2</span><h4>Active</h4><p>In use and accountable to an employee, department, and location.</p></div>
            <div class="lc-arrow"><i class="ri-arrow-right-line"></i></div>
            <div class="lc-step"><span class="num">3</span><h4>Checking</h4><p>Scheduled maintenance or lifespan review puts the asset up for evaluation.</p></div>
            <div class="lc-arrow"><i class="ri-arrow-right-line"></i></div>
            <div class="lc-step"><span class="num">4</span><h4>Repair</h4><p>Damaged assets are sent for repair and tracked until they return to service.</p></div>
            <div class="lc-arrow"><i class="ri-arrow-right-line"></i></div>
            <div class="lc-step"><span class="num">5</span><h4>Replace</h4><p>When repair no longer makes sense, a replacement request is raised.</p></div>
            <div class="lc-arrow"><i class="ri-arrow-right-line"></i></div>
            <div class="lc-step"><span class="num">6</span><h4>Disposal</h4><p>Retired assets are formally disposed — removed from service for good.</p></div>
        </div>

        <div class="lc-note">
            <i class="ri-archive-drawer-line"></i>
            <p>
                <strong>Pullout is another lifecycle stage.</strong> Assets can be pulled out — often in bulk —
                for storage, relocation, or reassignment while they are still usable. A pulled-out asset stays
                visible in the system and can later be reassigned to a new employee or moved toward disposal.
            </p>
        </div>
    </section>

    <!-- 2. Key Features -->
    <section class="section alt" id="features">
        <div class="section-head">
            <span class="kicker">What You Get</span>
            <h2>KEY <em>FEATURES</em></h2>
            <p>Six capabilities working together so nothing slips through the cracks.</p>
        </div>

        <div class="card-grid">
            <div class="feature-card">
                <div class="icon"><i class="ri-box-3-line"></i></div>
                <h3>Asset Management</h3>
                <p>Register every asset with a unique code and QR sticker, then track it through assignment, transfer, and review — all from one place.</p>
            </div>
            <div class="feature-card">
                <div class="icon"><i class="ri-tools-line"></i></div>
                <h3>Preventive Maintenance</h3>
                <p>Set maintenance intervals and the system schedules the next due date automatically, alerting administrators before a problem happens.</p>
            </div>
            <div class="feature-card">
                <div class="icon"><i class="ri-timer-flash-line"></i></div>
                <h3>Lifespan Monitoring</h3>
                <p>Know when an asset reaches the end of its expected lifespan so you can evaluate, extend, repair, or replace it on time.</p>
            </div>
            <div class="feature-card">
                <div class="icon"><i class="ri-refresh-line"></i></div>
                <h3>Repair &amp; Replacement</h3>
                <p>Raise and process repair and replacement requests with a clear trail — every decision about an asset is recorded.</p>
            </div>
            <div class="feature-card">
                <div class="icon"><i class="ri-user-star-line"></i></div>
                <h3>Asset Accountability</h3>
                <p>Every asset is tied to a specific accountable employee, department, and location, so ownership is never in question.</p>
            </div>
            <div class="feature-card">
                <div class="icon"><i class="ri-bar-chart-box-line"></i></div>
                <h3>Reports &amp; Dashboard</h3>
                <p>Dashboards and exportable reports turn raw asset data into decisions you can act on at a glance.</p>
            </div>
        </div>
    </section>

    <!-- 3. Asset Accountability -->
    <section class="section" id="accountability">
        <div class="section-head">
            <span class="kicker">Who Has It</span>
            <h2>ASSET <em>ACCOUNTABILITY</em></h2>
            <p>An asset is only as protected as the person responsible for it. Each record keeps the full chain of custody visible.</p>
        </div>

        <div class="acc-grid">
            <div class="acc-tile">
                <i class="ri-user-line"></i>
                <h4>Assigned Employee</h4>
                <p>The person accountable for the asset day-to-day, tracked by name and employee record.</p>
            </div>
            <div class="acc-tile">
                <i class="ri-building-2-line"></i>
                <h4>Department</h4>
                <p>The department that owns the asset, from IT to Laboratories and every office in between.</p>
            </div>
            <div class="acc-tile">
                <i class="ri-map-pin-line"></i>
                <h4>Location</h4>
                <p>The current physical location — room, faculty office, storage, or lab — so assets are easy to find.</p>
            </div>
            <div class="acc-tile">
                <i class="ri-pulse-line"></i>
                <h4>Current Status</h4>
                <p>The live lifecycle stage: Active, For Repair, Pulled Out, Disposed, and more — always current.</p>
            </div>
        </div>
    </section>

    <!-- 4. Audit Trail -->
    <section class="section alt" id="audit">
        <div class="section-head">
            <span class="kicker">Proof &amp; History</span>
            <h2>AUDIT <em>TRAIL</em></h2>
            <p>Trust, but verify.</p>
        </div>

        <div class="duo">
            <div class="duo-card">
                <h3>EVERY ACTION <em>RECORDED</em></h3>
                <p>
                    Important activities across the system are captured automatically — who performed the action,
                    what changed, which asset it affected, and exactly when it happened.
                </p>
                <ul class="check-list">
                    <li><i class="ri-check-line"></i><span>Asset registration, transfer, and lifecycle changes</span></li>
                    <li><i class="ri-check-line"></i><span>Request approvals, rejections, and processing</span></li>
                    <li><i class="ri-check-line"></i><span>Maintenance, repair, replacement, and disposal events</span></li>
                    <li><i class="ri-check-line"></i><span>Account and sign-in activity</span></li>
                </ul>
            </div>
            <div class="duo-card">
                <h3>REVIEWABLE &amp; <em>EXPORTABLE</em></h3>
                <p>
                    Authorized administrators can review the full activity log and export it as a report —
                    making compliance checks, investigations, and end-of-year reviews straightforward.
                </p>
                <ul class="check-list">
                    <li><i class="ri-check-line"></i><span>Filter logs by asset, request, or account activity</span></li>
                    <li><i class="ri-check-line"></i><span>Search and date-range filtering built in</span></li>
                    <li><i class="ri-check-line"></i><span>One-click export for official records</span></li>
                </ul>
            </div>
        </div>
    </section>

    <!-- 5. Bulk Operations -->
    <section class="section" id="bulk">
        <div class="section-head">
            <span class="kicker">Do More, Faster</span>
            <h2>BULK <em>OPERATIONS</em></h2>
            <p>Register and move assets in groups — without losing the individual record of each one.</p>
        </div>

        <div class="bulk-grid">
            <div class="bulk-card">
                <div class="bulk-top">
                    <div class="icon"><i class="ri-stack-line"></i></div>
                    <div>
                        <h3>Bulk Asset Registration</h3>
                        <span>Register a whole delivery at once</span>
                    </div>
                </div>
                <div class="bulk-body">
                    <p>
                        When a shipment of new equipment arrives, register all of it in one go. Each asset still
                        receives its own unique code, QR sticker, and full record — so bulk entry never means
                        blurred accountability.
                    </p>
                </div>
            </div>
            <div class="bulk-card">
                <div class="bulk-top">
                    <div class="icon"><i class="ri-archive-drawer-line"></i></div>
                    <div>
                        <h3>Bulk Asset Pullout</h3>
                        <span>Pull out a group of assets together</span>
                    </div>
                </div>
                <div class="bulk-body">
                    <p>
                        Pull out multiple assets in a single group when they need to be stored or relocated.
                        Administrators can review the whole group, search any asset inside it, and reassign each
                        one to a new user — or send it on to disposal — with a few clicks.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Who Is It For -->
    <section class="section alt" id="audience">
        <div class="section-head">
            <span class="kicker">Who Uses It</span>
            <h2>WHO IS IT <em>FOR?</em></h2>
            <p>One system, two clear roles — each side sees exactly what it needs.</p>
        </div>

        <div class="audience-grid">
            <div class="audience-card">
                <div class="icon"><i class="ri-team-line"></i></div>
                <h3>Employees &amp; Department Personnel</h3>
                <div class="role-sub">Day-to-day users</div>
                <ul>
                    <li><i class="ri-check-line"></i><span><strong>View assets</strong> assigned to them and across their department</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Submit requests</strong> for repair, replacement, and transfer</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Request pullouts</strong> for assets that need to be collected</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Track requests</strong> from submission to final decision</span></li>
                </ul>
            </div>
            <div class="audience-card">
                <div class="icon"><i class="ri-shield-user-line"></i></div>
                <h3>Asset Management Administrators</h3>
                <div class="role-sub">System overseers</div>
                <ul>
                    <li><i class="ri-check-line"></i><span><strong>Manage assets</strong> — register, assign, transfer, and retire them</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Manage requests</strong> — review, approve, and process them</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Handle maintenance</strong> schedules and completions</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Process repairs and replacements</strong> from start to finish</span></li>
                    <li><i class="ri-check-line"></i><span><strong>Manage disposal and pullouts</strong> with full group control</span></li>
                    <li><i class="ri-check-line"></i><span><strong>View reports and audit logs</strong> to stay accountable</span></li>
                </ul>
            </div>
        </div>
    </section>

    <!-- 7. Why Smarter Asset Management -->
    <section class="section navy" id="why">
        <div class="section-head">
            <span class="kicker">The Bottom Line</span>
            <h2>WHY SMARTER <em>ASSET MANAGEMENT?</em></h2>
            <p>Because when you know exactly where everything is and what it needs, the whole university runs smoother.</p>
        </div>

        <div class="benefit-band">
            <div class="benefit-chips">
                <span class="chip"><i class="ri-dashboard-3-line"></i> Centralized</span>
                <span class="chip"><i class="ri-user-star-line"></i> Accountable</span>
                <span class="chip"><i class="ri-flashlight-line"></i> Proactive</span>
                <span class="chip"><i class="ri-focus-3-line"></i> Traceable</span>
            </div>
            <p>
                One source of truth for every asset · clear ownership at all times · maintenance and lifespan
                issues caught before they become problems · and a complete record of every action — from the
                first day an asset arrives to the day it is responsibly retired.
            </p>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="cta-wrap">
        <h2>READY TO TAKE CONTROL OF YOUR <span>ASSETS?</span></h2>
        <p>Activate your account or sign in to start tracking smarter today.</p>
        <div class="cta-buttons">
            <a href="/login" class="btn-gold"><i class="ri-rocket-2-line"></i> Get Started</a>
            <a href="/register" class="btn-outline-light"><i class="ri-user-follow-line"></i> Activate Your Account</a>
        </div>
    </section>

    <footer>
        &copy; {{ date('Y') }} <span>NU Lipa University Asset Management System</span>. All rights reserved.
    </footer>

</body>
</html>
