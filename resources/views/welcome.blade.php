<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Asset Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --blue: #1a3a6b;
            --blue-mid: #1e4d8c;
            --blue-light: #2563b0;
            --gold: #c9a227;
            --gold-light: #e2b93b;
            --gold-pale: #fdf3d0;
            --dark: #0d1f3c;
            --white: #ffffff;
            --gray-bg: #f4f7fb;
            --muted: #5a6a85;
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
            justify-content: flex-end;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 16px rgba(13,31,60,0.25);
            border-bottom: 3px solid var(--gold);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-signup {
            color: var(--white);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            transition: background 0.2s;
            letter-spacing: 0.02em;
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
            letter-spacing: 0.02em;
        }

        .btn-login:hover {
            background: var(--gold-light);
            transform: translateY(-1px);
        }

        /* HERO */
        .hero {
            min-height: calc(100vh - 68px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            background: linear-gradient(135deg, #eef3fb 0%, #f4f7fb 50%, #fdf8e8 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -100px; right: -100px;
            width: 480px; height: 480px;
            background: radial-gradient(circle, rgba(26,58,107,0.07) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -80px;
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(201,162,39,0.10) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-deco {
            position: absolute;
            top: 40px; left: 40px;
            width: 120px; height: 120px;
            border-radius: 50%;
            border: 2px dashed rgba(201,162,39,0.25);
            pointer-events: none;
        }

        .hero-card {
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 8px 48px rgba(26,58,107,0.12), 0 2px 12px rgba(0,0,0,0.05);
            padding: 4rem 3.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4rem;
            max-width: 960px;
            width: 100%;
            position: relative;
            z-index: 1;
            border-top: 5px solid var(--gold);
            animation: fadeUp 0.7s cubic-bezier(.22,.68,0,1.2) both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-left { flex: 1; }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--gold-pale);
            color: var(--gold);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            margin-bottom: 1.2rem;
            border: 1px solid rgba(201,162,39,0.3);
        }

        .hero-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.8rem, 5vw, 4.2rem);
            line-height: 1.05;
            letter-spacing: 2px;
            color: var(--blue);
            margin-bottom: 1rem;
        }

        .hero-title span {
            color: var(--gold);
            -webkit-text-stroke: 1px #a07d10;
        }

        .hero-desc {
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.75;
            max-width: 400px;
            margin-bottom: 2rem;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--blue);
            color: var(--white);
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 12px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(26,58,107,0.25);
        }

        .btn-primary:hover {
            background: var(--blue-mid);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26,58,107,0.30);
        }

        .btn-secondary {
            background: transparent;
            color: var(--gold);
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 12px;
            border: 2px solid var(--gold);
            transition: background 0.2s, color 0.2s;
        }

        .btn-secondary:hover {
            background: var(--gold-pale);
            color: var(--dark);
        }

        /* LOGO BOX */
        .hero-right {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .logo-box {
            width: 180px; height: 180px;
            border-radius: 20px;
            border: 2px dashed rgba(201,162,39,0.4);
            background: var(--gold-pale);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.5rem;
            transition: border-color 0.2s, background 0.2s;
        }

        .logo-box:hover {
            border-color: var(--gold);
            background: #faefc0;
        }

        .logo-box svg { opacity: 0.4; }

        .logo-box-label {
            font-size: 0.78rem;
            color: var(--gold);
            font-weight: 600;
            text-align: center;
        }

        .logo-caption {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 500;
        }

        /* FEATURES */
        .features {
            background: var(--blue);
            padding: 3rem 2.5rem;
            border-top: 4px solid var(--gold);
        }

        .features-inner {
            max-width: 960px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .feature-icon {
            width: 44px; height: 44px;
            background: rgba(201,162,39,0.12);
            border: 1px solid rgba(201,162,39,0.3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-icon svg { color: var(--gold); }

        .feature-text h4 {
            color: var(--gold-light);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .feature-text p {
            color: #a8bcd4;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        /* FOOTER */
        footer {
            background: var(--dark);
            text-align: center;
            padding: 1.5rem;
            font-size: 0.82rem;
            color: #6b85a8;
            border-top: 1px solid rgba(201,162,39,0.2);
        }

        footer span { color: var(--gold); }

        @media (max-width: 768px) {
            .hero-card { flex-direction: column; padding: 2.5rem 1.5rem; gap: 2rem; text-align: center; }
            .hero-desc { max-width: 100%; }
            .hero-cta { justify-content: center; }
            .features-inner { grid-template-columns: 1fr; }
            .hero-right { display: none; }
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-actions">
            <a href="/register" class="btn-signup">Sign Up</a>
            <a href="/login" class="btn-login">Login</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-deco"></div>
        <div class="hero-card">
            <div class="hero-left">
                <div class="hero-badge">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor"><circle cx="5" cy="5" r="5"/></svg>
                    University Asset Management
                </div>
                <h1 class="hero-title">
                    MANAGE YOUR<br>
                    <span>ASSETS</span><br>
                    SMARTER.
                </h1>
                <p class="hero-desc">
                    A centralized system for tracking, requesting, and managing institutional assets across all departments — from acquisition to disposal.
                </p>
                <div class="hero-cta">
                    <a href="/login" class="btn-primary">Get Started</a>
                    <a href="#features" class="btn-secondary">Learn More</a>
                </div>
            </div>
            <div class="hero-right">
                <div class="logo-box">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#c9a227" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="3"/>
                        <path d="M3 9h18M9 21V9"/>
                    </svg>
                    <span class="logo-box-label">Your Logo Here</span>
                </div>
                <span class="logo-caption">Logo of System</span>
            </div>
        </div>
    </section>

    <section class="features" id="features">
        <div class="features-inner">
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                        <path d="M9 12h6M9 16h4"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>Request Management</h4>
                    <p>Submit and track repair, disposal, transfer, pullout, and replacement requests seamlessly.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <path d="M14 17h7M17 14v7"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>QR Code Scanning</h4>
                    <p>Instantly fetch asset details by scanning the auto-generated QR code with your phone.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h4>Audit & Security</h4>
                    <p>Full audit logs track every action across the system — who did what, when, and to which asset.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        &copy; {{ date('Y') }} <span>University Asset Management System</span>. All rights reserved.
    </footer>

</body>
</html>