<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OEE Monitoring System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --m900: #0d0404;
    --m800: #1a0707;
    --m700: #2e0e0e;
    --m600: #4d1515;
    --m500: #6b1a1a;
    --m400: #8b2020;
    --m300: #a82626;
    --m200: #c43030;
    --m100: #d45050;
    --mlight: #e8847a;
    --cream:  #fff0ee;
    --text:   #f0e8e7;
    --muted:  rgba(240,232,231,.45);
    --line:   rgba(240,232,231,.1);
}

html { scroll-behavior: smooth; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--m900);
    color: var(--text);
    overflow-x: hidden;
    line-height: 1.6;
}

/* ══ UTILS ══════════════════════════════════════════════════ */
.container { max-width: 1120px; margin: 0 auto; padding: 0 24px; }

.tag {
    display: inline-block;
    font-family: 'Sora', sans-serif;
    font-size: .68rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    color: var(--m100); border: 1px solid var(--m400);
    padding: 4px 12px; border-radius: 4px;
    background: rgba(107,26,26,.3);
}

h1, h2, h3 { font-family: 'Sora', sans-serif; font-weight: 800; line-height: 1.15; }

.reveal { opacity: 0; transform: translateY(24px); transition: opacity .65s ease, transform .65s ease; }
.reveal.in { opacity: 1; transform: none; }

/* ══ NAVBAR ═════════════════════════════════════════════════ */
#nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    padding: 18px 32px;
    display: flex; align-items: center; justify-content: space-between;
    transition: background .3s, border-color .3s;
    border-bottom: 1px solid transparent;
}
#nav.stuck {
    background: rgba(13,4,4,.92);
    backdrop-filter: blur(12px);
    border-color: var(--line);
}
.nav-brand {
    font-family: 'Sora', sans-serif;
    font-weight: 800; font-size: .95rem;
    display: flex; align-items: center; gap: 9px;
    letter-spacing: .3px;
}
.nav-brand-icon {
    width: 30px; height: 30px;
    background: var(--m300);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
}
.nav-links {
    display: flex; gap: 32px; list-style: none;
}
.nav-links a {
    color: var(--muted);
    text-decoration: none;
    font-size: .85rem; font-weight: 500;
    transition: color .2s;
}
.nav-links a:hover { color: var(--text); }
.btn-nav {
    font-family: 'Sora', sans-serif;
    background: var(--m300);
    color: #fff; font-weight: 700; font-size: .82rem;
    padding: 9px 22px; border-radius: 6px;
    text-decoration: none;
    transition: background .2s;
    letter-spacing: .3px;
}
.btn-nav:hover { background: var(--m200); color: #fff; text-decoration: none; }

/* ══ HERO ════════════════════════════════════════════════════ */
#hero {
    min-height: 100vh;
    background: var(--m900);
    display: flex; align-items: center;
    padding: 120px 0 80px;
    position: relative;
    border-bottom: 1px solid var(--line);
}

/* Subtle texture overlay */
#hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.015'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

/* Diagonal accent block */
#hero::after {
    content: '';
    position: absolute;
    right: 0; top: 0; bottom: 0;
    width: 45%;
    background: linear-gradient(160deg, var(--m800) 0%, var(--m700) 100%);
    clip-path: polygon(12% 0, 100% 0, 100% 100%, 0% 100%);
    z-index: 0;
}

.hero-inner {
    position: relative; z-index: 1;
    display: flex; align-items: center;
    gap: 0;
}
.hero-left { flex: 1 1 500px; padding-right: 60px; }
.hero-right {
    flex: 0 0 380px;
    display: flex; align-items: center; justify-content: center;
}

.hero-tag { margin-bottom: 24px; }

.hero-h1 {
    font-size: clamp(2.4rem, 4.5vw, 3.8rem);
    margin-bottom: 20px;
    color: var(--text);
}
.hero-h1 em {
    font-style: normal;
    color: var(--m100);
    /* underline accent */
    background: linear-gradient(to right, var(--m300), var(--m100));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-p {
    font-size: 1rem;
    color: var(--muted);
    max-width: 440px;
    margin-bottom: 36px;
    line-height: 1.75;
}

.btn-primary {
    font-family: 'Sora', sans-serif;
    display: inline-flex; align-items: center; gap: 10px;
    background: var(--m300);
    color: #fff; font-weight: 700; font-size: .95rem;
    padding: 14px 32px; border-radius: 6px;
    text-decoration: none;
    transition: background .2s, transform .15s;
    border: none; cursor: pointer;
}
.btn-primary:hover { background: var(--m200); transform: translateY(-2px); color: #fff; text-decoration: none; }
.btn-primary i { font-size: .9rem; }

.btn-tv {
    font-family: 'Sora', sans-serif;
    display: inline-flex; align-items: center; gap: 10px;
    background: transparent;
    color: var(--mlight); font-weight: 700; font-size: .95rem;
    padding: 14px 32px; border-radius: 6px;
    text-decoration: none;
    border: 1px solid var(--m400);
    transition: background .2s, border-color .2s, transform .15s;
}
.btn-tv:hover {
    background: var(--m700);
    border-color: var(--mlight);
    color: var(--text);
    transform: translateY(-2px);
    text-decoration: none;
}
.btn-tv i { font-size: .9rem; }

.hero-meta {
    margin-top: 48px;
    display: flex; gap: 0;
    border-top: 1px solid var(--line);
    padding-top: 32px;
}
.hero-meta-item {
    flex: 1;
    padding-right: 24px;
    border-right: 1px solid var(--line);
    margin-right: 24px;
}
.hero-meta-item:last-child { border: none; margin: 0; }
.hero-meta-num {
    font-family: 'Sora', sans-serif;
    font-size: 1.8rem; font-weight: 800;
    line-height: 1;
}
.hero-meta-lbl {
    font-size: .72rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 4px;
}

/* Gauge */
.gauge-box {
    background: var(--m800);
    border: 1px solid var(--m600);
    border-radius: 16px;
    padding: 36px 32px;
    text-align: center;
    width: 100%;
}
.gauge-box-title {
    font-size: .72rem; text-transform: uppercase;
    letter-spacing: 2px; color: var(--muted); margin-bottom: 24px;
}
.gauge-svg-wrap { position: relative; width: 200px; height: 200px; margin: 0 auto 20px; }
.gauge-svg-wrap svg { transform: rotate(-90deg); }
.gauge-center-lbl {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
}
.gauge-big-num {
    font-family: 'Sora', sans-serif;
    font-size: 1.8rem; font-weight: 800;
    color: var(--text); line-height: 1;
}
.gauge-big-sub {
    font-size: .62rem; color: var(--muted);
    letter-spacing: 1.5px; text-transform: uppercase;
    margin-top: 3px;
}

.gauge-rows { display: flex; flex-direction: column; gap: 10px; }
.gauge-row { display: flex; align-items: center; gap: 10px; }
.gauge-row-lbl { font-size: .78rem; color: var(--muted); width: 88px; text-align: left; }
.gauge-bar-t { flex: 1; height: 5px; background: var(--m700); border-radius: 3px; overflow: hidden; }
.gauge-bar-f { height: 100%; border-radius: 3px; width: 0; transition: width 1.8s cubic-bezier(.4,0,.2,1) .5s; }
.gauge-row-val { font-family: 'Sora', sans-serif; font-size: .8rem; font-weight: 700; width: 38px; text-align: right; }

/* ══ SECTION BASE ════════════════════════════════════════════ */
section { padding: 96px 0; }
.sec-alt { background: var(--m800); }

.sec-head { margin-bottom: 56px; }
.sec-head .tag { margin-bottom: 14px; }
.sec-head h2 { font-size: clamp(1.8rem, 3vw, 2.6rem); margin-bottom: 14px; }
.sec-head p { color: var(--muted); max-width: 520px; font-size: .95rem; line-height: 1.75; }

/* ══ COMPONENTS ══════════════════════════════════════════════ */
.comp-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: var(--line);
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
}
.comp-cell {
    background: var(--m900);
    padding: 40px 32px;
    transition: background .25s;
}
.comp-cell:hover { background: var(--m800); }
.comp-num {
    font-family: 'Sora', sans-serif;
    font-size: 2.8rem; font-weight: 800; line-height: 1;
    margin-bottom: 6px;
    opacity: .12;
}
.comp-icon {
    font-size: 1.4rem; margin-bottom: 18px;
}
.comp-title { font-family: 'Sora', sans-serif; font-size: 1.05rem; font-weight: 700; margin-bottom: 10px; }
.comp-desc { color: var(--muted); font-size: .86rem; line-height: 1.7; margin-bottom: 20px; }
.comp-formula {
    font-family: 'DM Mono', 'Courier New', monospace;
    font-size: .75rem;
    color: var(--muted);
    border-top: 1px solid var(--line);
    padding-top: 16px;
    line-height: 1.6;
}
.comp-pct {
    font-family: 'Sora', sans-serif;
    font-size: 1.5rem; font-weight: 800;
    margin-top: 8px;
}
.comp-bar-wrap { margin-top: 12px; }
.comp-bar-track { height: 3px; background: var(--m600); border-radius: 2px; overflow: hidden; }
.comp-bar-fill { height: 100%; border-radius: 2px; width: 0; transition: width 1.8s ease .4s; }

/* ══ FORMULA ═════════════════════════════════════════════════ */
.formula-band {
    background: var(--m700);
    border-radius: 12px;
    padding: 40px 48px;
    border: 1px solid var(--m600);
    margin-bottom: 36px;
}
.formula-eq {
    display: flex; align-items: center; flex-wrap: wrap;
    gap: 12px; justify-content: center;
    font-family: 'Sora', sans-serif;
    font-size: 1.05rem; font-weight: 700;
}
.feq-block {
    padding: 10px 22px; border-radius: 6px;
    border: 1px solid;
}
.feq-oee  { border-color: var(--m200); color: var(--mlight); background: rgba(168,38,38,.2); }
.feq-sym  { color: var(--muted); border: none; background: none; padding: 10px 4px; font-size: 1.4rem; }
.feq-a    { border-color: rgba(78,115,223,.5); color: #7c9ef5; background: rgba(78,115,223,.1); }
.feq-p    { border-color: rgba(28,200,138,.5); color: #5ce0ab; background: rgba(28,200,138,.1); }
.feq-q    { border-color: rgba(246,194,62,.5); color: #f5d278; background: rgba(246,194,62,.1); }

.example-table { width: 100%; border-collapse: collapse; }
.example-table tr { border-bottom: 1px solid var(--line); }
.example-table tr:last-child { border: none; }
.example-table td { padding: 14px 0; vertical-align: middle; font-size: .88rem; }
.ex-name { color: var(--muted); width: 120px; }
.ex-pct  { font-family: 'Sora', sans-serif; font-weight: 700; width: 60px; text-align: right; }
.ex-bar  { padding: 0 20px; }
.ex-bar-t { height: 5px; background: var(--m700); border-radius: 3px; overflow: hidden; }
.ex-bar-f { height: 100%; border-radius: 3px; width: 0; transition: width 1.8s ease .6s; }

.oee-classes {
    display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px;
}
.oee-class {
    flex: 1 1 160px;
    border: 1px solid;
    border-radius: 8px;
    padding: 16px 18px;
    font-size: .84rem;
}
.oee-class strong { display: block; font-family: 'Sora', sans-serif; font-size: 1rem; margin-bottom: 4px; }
.oee-class span { color: var(--muted); font-size: .78rem; }
.cls-wc  { border-color: rgba(28,200,138,.4);  background: rgba(28,200,138,.07); }
.cls-wc strong { color: #5ce0ab; }
.cls-ok  { border-color: rgba(246,194,62,.4);  background: rgba(246,194,62,.07); }
.cls-ok strong { color: #f5d278; }
.cls-low { border-color: rgba(168,38,38,.4);   background: rgba(168,38,38,.07); }
.cls-low strong { color: var(--mlight); }

/* ══ FEATURES ════════════════════════════════════════════════ */
.feat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 16px;
}
.feat-card {
    background: var(--m800);
    border: 1px solid var(--m700);
    border-radius: 10px;
    padding: 26px 22px;
    transition: border-color .25s, transform .25s;
}
.feat-card:hover { border-color: var(--m400); transform: translateY(-3px); }
.feat-ic {
    width: 40px; height: 40px; border-radius: 8px;
    background: var(--m700); border: 1px solid var(--m600);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; color: var(--mlight);
    margin-bottom: 14px;
}
.feat-ttl { font-family: 'Sora', sans-serif; font-weight: 700; font-size: .9rem; margin-bottom: 6px; }
.feat-dsc { font-size: .8rem; color: var(--muted); line-height: 1.65; }

/* ══ CTA ═════════════════════════════════════════════════════ */
#cta {
    padding: 100px 0;
    background: var(--m700);
    border-top: 1px solid var(--m600);
    border-bottom: 1px solid var(--m600);
    text-align: center;
}
#cta h2 { font-size: clamp(1.8rem, 3.5vw, 2.8rem); margin-bottom: 14px; }
#cta p  { color: var(--muted); max-width: 440px; margin: 0 auto 36px; font-size: .95rem; line-height: 1.75; }

/* ══ FOOTER ══════════════════════════════════════════════════ */
footer {
    background: var(--m900);
    border-top: 1px solid var(--line);
    padding: 28px 32px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    font-size: .75rem; color: var(--muted);
}

/* ══ RESPONSIVE ══════════════════════════════════════════════ */
@media (max-width: 900px) {
    #hero::after { display: none; }
    .hero-right { display: none; }
    .hero-left { padding-right: 0; }
    .comp-grid { grid-template-columns: 1fr; }
    .nav-links { display: none; }
}
@media (max-width: 600px) {
    #nav { padding: 14px 20px; }
    .hero-meta { flex-direction: column; gap: 20px; }
    .hero-meta-item { border: none; margin: 0; padding: 0; }
    .formula-band { padding: 24px 20px; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav id="nav">
    <div class="nav-brand">
        <div class="nav-brand-icon"><i class="fas fa-cog"></i></div>
        OEE Monitoring
    </div>
    <ul class="nav-links">
        <li><a href="#components">Components</a></li>
        <li><a href="#formula">Formula</a></li>
        <li><a href="#features">Features</a></li>
    </ul>
    <div style="display:flex;align-items:center;gap:10px;">
        <a href="tv_dashboard.php" target="_blank" class="btn-nav"
           style="background:transparent;border:1px solid var(--m500);color:var(--mlight);">
            <i class="fas fa-tv" style="margin-right:6px;"></i>TV
        </a>
        <a href="login.php" class="btn-nav">
            <i class="fas fa-sign-in-alt" style="margin-right:7px;"></i>Login
        </a>
    </div>
</nav>

<!-- HERO -->
<section id="hero">
    <div class="container">
        <div class="hero-inner">
            <div class="hero-left">
                <div class="hero-tag reveal">
                    <span class="tag"><i class="fas fa-industry" style="margin-right:6px;"></i>Production Monitoring System</span>
                </div>
                <h1 class="hero-h1 reveal">
                    Measure Equipment<br>
                    Effectiveness with <em>Precision</em>
                </h1>
                <p class="hero-p reveal">
                    An integrated OEE platform — from ESP32 sensors on the factory floor
                    to management reports. Monitor Availability, Performance,
                    and Quality in real-time.
                </p>
                <div class="reveal" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                    <a href="login.php" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Dashboard
                    </a>
                    <a href="tv_dashboard.php" target="_blank" class="btn-tv">
                        <i class="fas fa-tv"></i>
                        View TV Dashboard
                    </a>
                </div>

                <div class="hero-meta reveal">
                    <div class="hero-meta-item">
                        <div class="hero-meta-num" style="color:var(--mlight);">
                            <span class="cnt" data-t="85">0</span>%
                        </div>
                        <div class="hero-meta-lbl">World Class OEE</div>
                    </div>
                    <div class="hero-meta-item">
                        <div class="hero-meta-num" style="color:#7c9ef5;">
                            <span class="cnt" data-t="3">0</span> Shifts
                        </div>
                        <div class="hero-meta-lbl">Per Day</div>
                    </div>
                    <div class="hero-meta-item">
                        <div class="hero-meta-num" style="color:#5ce0ab;">
                            <span class="cnt" data-t="4">0</span> Sensors
                        </div>
                        <div class="hero-meta-lbl">Vibration Axes</div>
                    </div>
                </div>
            </div>

            <!-- Gauge Card -->
            <div class="hero-right reveal">
                <div class="gauge-box">
                    <div class="gauge-box-title">OEE Score — Real-time</div>
                    <div class="gauge-svg-wrap">
                        <svg width="200" height="200" viewBox="0 0 200 200">
                            <!-- tracks (r: 82, 64, 47, 31) -->
                            <circle cx="100" cy="100" r="82" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="14"/>
                            <circle cx="100" cy="100" r="64" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="10"/>
                            <circle cx="100" cy="100" r="47" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="10"/>
                            <circle cx="100" cy="100" r="31" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="7"/>
                            <!-- fills -->
                            <!-- OEE  r=82  c=515 -->
                            <circle id="gOEE" cx="100" cy="100" r="82" fill="none"
                                    stroke="#c43030" stroke-width="14" stroke-linecap="round"
                                    stroke-dasharray="515" stroke-dashoffset="515"
                                    style="transition:stroke-dashoffset 2s cubic-bezier(.4,0,.2,1) .3s"/>
                            <!-- A    r=64  c=402 -->
                            <circle id="gA" cx="100" cy="100" r="64" fill="none"
                                    stroke="#7c9ef5" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="402" stroke-dashoffset="402"
                                    style="transition:stroke-dashoffset 2.2s cubic-bezier(.4,0,.2,1) .5s"/>
                            <!-- P    r=47  c=295 -->
                            <circle id="gP" cx="100" cy="100" r="47" fill="none"
                                    stroke="#5ce0ab" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="295" stroke-dashoffset="295"
                                    style="transition:stroke-dashoffset 2.4s cubic-bezier(.4,0,.2,1) .7s"/>
                            <!-- Q    r=31  c=195 -->
                            <circle id="gQ" cx="100" cy="100" r="31" fill="none"
                                    stroke="#f5d278" stroke-width="7" stroke-linecap="round"
                                    stroke-dasharray="195" stroke-dashoffset="195"
                                    style="transition:stroke-dashoffset 2.6s cubic-bezier(.4,0,.2,1) .9s"/>
                        </svg>
                        <div class="gauge-center-lbl">
                            <div class="gauge-big-num"><span id="gNum">0</span>%</div>
                            <div class="gauge-big-sub">OEE</div>
                        </div>
                    </div>
                    <div class="gauge-rows">
                        <div class="gauge-row">
                            <div class="gauge-row-lbl">Availability</div>
                            <div class="gauge-bar-t">
                                <div class="gauge-bar-f" id="gbA" style="background:#7c9ef5;"></div>
                            </div>
                            <div class="gauge-row-val" style="color:#7c9ef5;"><span id="gvA">0</span>%</div>
                        </div>
                        <div class="gauge-row">
                            <div class="gauge-row-lbl">Performance</div>
                            <div class="gauge-bar-t">
                                <div class="gauge-bar-f" id="gbP" style="background:#5ce0ab;"></div>
                            </div>
                            <div class="gauge-row-val" style="color:#5ce0ab;"><span id="gvP">0</span>%</div>
                        </div>
                        <div class="gauge-row">
                            <div class="gauge-row-lbl">Quality</div>
                            <div class="gauge-bar-t">
                                <div class="gauge-bar-f" id="gbQ" style="background:#f5d278;"></div>
                            </div>
                            <div class="gauge-row-val" style="color:#f5d278;"><span id="gvQ">0</span>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- OEE COMPONENTS -->
<section id="components">
    <div class="container">
        <div class="sec-head reveal">
            <span class="tag">Three Components</span>
            <h2>What Does OEE Measure?</h2>
            <p>OEE identifies hidden production losses across three interconnected
               measurement dimensions — revealing exactly where efficiency is lost.</p>
        </div>
        <div class="comp-grid reveal">
            <!-- Availability -->
            <div class="comp-cell">
                <div class="comp-num">01</div>
                <div class="comp-icon" style="color:#7c9ef5;"><i class="fas fa-clock"></i></div>
                <div class="comp-title">Availability</div>
                <div class="comp-desc">
                    The proportion of scheduled time that equipment is available to operate.
                    Unplanned downtime and changeovers are the primary losses here.
                    Scheduled breaks are automatically excluded from the calculation.
                </div>
                <div class="comp-formula">
                    Run Time ÷ Planned Production Time × 100
                    <div class="comp-pct" style="color:#7c9ef5;" id="cpA">0%</div>
                    <div class="comp-bar-wrap">
                        <div class="comp-bar-track">
                            <div class="comp-bar-fill" id="cbA" style="background:#7c9ef5;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Performance -->
            <div class="comp-cell" style="border-left:1px solid var(--line);">
                <div class="comp-num">02</div>
                <div class="comp-icon" style="color:#5ce0ab;"><i class="fas fa-tachometer-alt"></i></div>
                <div class="comp-title">Performance</div>
                <div class="comp-desc">
                    How fast equipment runs compared to its maximum designed speed.
                    Slow cycles and micro-stops are hidden causes that often go
                    unnoticed by operators on the floor.
                </div>
                <div class="comp-formula">
                    Actual Output ÷ Target Output × 100
                    <div class="comp-pct" style="color:#5ce0ab;" id="cpP">0%</div>
                    <div class="comp-bar-wrap">
                        <div class="comp-bar-track">
                            <div class="comp-bar-fill" id="cbP" style="background:#5ce0ab;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Quality -->
            <div class="comp-cell" style="border-left:1px solid var(--line);">
                <div class="comp-num">03</div>
                <div class="comp-icon" style="color:#f5d278;"><i class="fas fa-check-double"></i></div>
                <div class="comp-title">Quality</div>
                <div class="comp-desc">
                    The percentage of parts that meet quality standards on the first pass.
                    Defects, rejects, and rework directly reduce this value
                    and represent wasted time and materials.
                </div>
                <div class="comp-formula">
                    Good Parts ÷ Total Parts × 100
                    <div class="comp-pct" style="color:#f5d278;" id="cpQ">0%</div>
                    <div class="comp-bar-wrap">
                        <div class="comp-bar-track">
                            <div class="comp-bar-fill" id="cbQ" style="background:#f5d278;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FORMULA -->
<section id="formula" class="sec-alt">
    <div class="container">
        <div class="sec-head reveal">
            <span class="tag">Calculation</span>
            <h2>The OEE Formula</h2>
            <p>OEE is the product of all three components multiplied together.
               The ISO 22400 industry standard sets 85% as the world-class benchmark.</p>
        </div>

        <div class="reveal">
            <div class="formula-band">
                <div class="formula-eq">
                    <div class="feq-block feq-oee">OEE</div>
                    <div class="feq-block feq-sym">=</div>
                    <div class="feq-block feq-a">Availability</div>
                    <div class="feq-block feq-sym">×</div>
                    <div class="feq-block feq-p">Performance</div>
                    <div class="feq-block feq-sym">×</div>
                    <div class="feq-block feq-q">Quality</div>
                </div>
            </div>

            <table class="example-table">
                <tr>
                    <td class="ex-name">Availability</td>
                    <td class="ex-pct" style="color:#7c9ef5;">90%</td>
                    <td class="ex-bar">
                        <div class="ex-bar-t"><div class="ex-bar-f" style="background:#7c9ef5;" data-w="90"></div></div>
                    </td>
                </tr>
                <tr>
                    <td class="ex-name">Performance</td>
                    <td class="ex-pct" style="color:#5ce0ab;">95%</td>
                    <td class="ex-bar">
                        <div class="ex-bar-t"><div class="ex-bar-f" style="background:#5ce0ab;" data-w="95"></div></div>
                    </td>
                </tr>
                <tr>
                    <td class="ex-name">Quality</td>
                    <td class="ex-pct" style="color:#f5d278;">99%</td>
                    <td class="ex-bar">
                        <div class="ex-bar-t"><div class="ex-bar-f" style="background:#f5d278;" data-w="99"></div></div>
                    </td>
                </tr>
                <tr>
                    <td class="ex-name" style="color:var(--text);font-family:'Sora',sans-serif;font-weight:700;">OEE</td>
                    <td class="ex-pct" style="color:var(--mlight);font-size:1.1rem;">84.6%</td>
                    <td class="ex-bar">
                        <div class="ex-bar-t" style="height:7px;">
                            <div class="ex-bar-f" style="background:var(--m200);height:100%;" data-w="84.6"></div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="oee-classes">
                <div class="oee-class cls-wc">
                    <strong>≥ 85%</strong>
                    <span>World Class — global industry benchmark</span>
                </div>
                <div class="oee-class cls-ok">
                    <strong>60 – 85%</strong>
                    <span>Acceptable — room for improvement exists</span>
                </div>
                <div class="oee-class cls-low">
                    <strong>&lt; 60%</strong>
                    <span>Needs attention — identify and eliminate losses</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section id="features">
    <div class="container">
        <div class="sec-head reveal">
            <span class="tag">Platform</span>
            <h2>System Features</h2>
            <p>Everything you need to monitor production — from hardware sensors to management reports.</p>
        </div>
        <div class="feat-grid">
            <?php
            $feats = [
                ['fa-broadcast-tower', 'Live from ESP32',      'Tower lamp and sensor data pushed to the system every second via API.'],
                ['fa-wave-square',     'Vibration Analysis',   '4 WitMotion B02-485 sensors monitoring motor vibration on X, Y, Z, and B axes.'],
                ['fa-clipboard-list',  'OEE Input',            'Operators can enter working hours, targets, and rejects directly in the browser.'],
                ['fa-coffee',          'Break Scheduling',     'Scheduled breaks are automatically excluded from the Availability calculation.'],
                ['fa-chart-bar',       'Trend Reports',        'Daily, weekly, and monthly OEE trend charts with data export support.'],
                ['fa-bell',            'Automatic Alerts',     'Instant notifications when a machine enters emergency or stop status.'],
                ['fa-bolt',            'Energy Monitoring',    'Track power consumption and identify energy waste across all equipment.'],
                ['fa-tv',              'TV Dashboard',         'Large-screen live display designed for production floor visibility.'],
            ];
            foreach ($feats as $f): ?>
            <div class="feat-card reveal">
                <div class="feat-ic"><i class="fas <?= $f[0] ?>"></i></div>
                <div class="feat-ttl"><?= $f[1] ?></div>
                <div class="feat-dsc"><?= $f[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section id="cta">
    <div class="container">
        <h2 class="reveal">Start Monitoring Now</h2>
        <p class="reveal">
            Log in and access all production, vibration, and OEE data
            from a single integrated platform.
        </p>
        <div class="reveal" style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;align-items:center;">
            <a href="login.php" class="btn-primary" style="font-size:1rem;padding:15px 36px;">
                <i class="fas fa-sign-in-alt"></i>
                Go to Dashboard
            </a>
            <a href="tv_dashboard.php" target="_blank" class="btn-tv" style="font-size:1rem;padding:15px 32px;">
                <i class="fas fa-tv"></i>
                TV Dashboard
            </a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div>© <?= date('Y') ?> OEE Monitoring System &nbsp;·&nbsp; PT Yanmar Indonesia</div>
    <div>ISO 22400 &nbsp;·&nbsp; Overall Equipment Effectiveness</div>
</footer>

<script>
// Navbar scroll
window.addEventListener('scroll', function(){
    document.getElementById('nav').classList.toggle('stuck', scrollY > 50);
}, {passive:true});

// Scroll reveal
var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
        if (!e.isIntersecting) return;
        e.target.classList.add('in');
        // Trigger bar fills in component cells
        e.target.querySelectorAll('.comp-bar-fill').forEach(function(b){
            var pct = b.id==='cbA'?90:b.id==='cbP'?95:99;
            setTimeout(function(){ b.style.width=pct+'%'; },400);
        });
        e.target.querySelectorAll('.ex-bar-f').forEach(function(b){
            setTimeout(function(){ b.style.width=b.dataset.w+'%'; },400);
        });
        io.unobserve(e.target);
    });
}, {threshold: 0.12});
document.querySelectorAll('.reveal').forEach(function(el){ io.observe(el); });

// Component % counters (triggered when visible)
var compDone = false;
var io2 = new IntersectionObserver(function(entries){
    if (compDone || !entries[0].isIntersecting) return;
    compDone = true;
    animNum('cpA',90,'%'); animNum('cpP',95,'%'); animNum('cpQ',99,'%');
}, {threshold:0.3});
var compEl = document.querySelector('.comp-grid');
if (compEl) io2.observe(compEl);

// Gauge animation
function setArc(id, c, pct) {
    var el = document.getElementById(id);
    if (!el) return;
    var offset = c * (1 - pct/100);
    el.style.strokeDashoffset = offset;
}
function animNum(id, target, suffix) {
    var el = document.getElementById(id);
    if (!el) return;
    var n=0, step=target/60;
    var t = setInterval(function(){
        n = Math.min(n+step, target);
        el.textContent = Math.round(n)+(suffix||'');
        if (n>=target) clearInterval(t);
    }, 18);
}

// Counter for gauge value text
function animSpan(id, target) {
    var el = document.getElementById(id);
    if (!el) return;
    var n=0, step=target/80;
    var t = setInterval(function(){
        n = Math.min(n+step, target);
        el.textContent = (Math.round(n*10)/10).toFixed(1);
        if (n>=target) clearInterval(t);
    }, 20);
}

// Hero counters
window.addEventListener('load', function(){
    // Fire gauge after short delay
    setTimeout(function(){
        // Circumferences: r=82→515, r=64→402, r=47→295, r=31→195
        setArc('gOEE', 515, 84.6);
        setArc('gA',   402, 90);
        setArc('gP',   295, 95);
        setArc('gQ',   195, 99);

        animSpan('gNum', 84.6);

        // Bar fills + value counters
        var bars = [{b:'gbA',v:'gvA',p:90},{b:'gbP',v:'gvP',p:95},{b:'gbQ',v:'gvQ',p:99}];
        bars.forEach(function(x){
            var bel=document.getElementById(x.b);
            if(bel) setTimeout(function(){ bel.style.width=x.p+'%'; },600);
            var vel=document.getElementById(x.v);
            if(vel){
                var n=0,step=x.p/70;
                var t=setInterval(function(){ n=Math.min(n+step,x.p); vel.textContent=Math.round(n); if(n>=x.p)clearInterval(t); },20);
            }
        });

        // Hero meta counters
        document.querySelectorAll('.cnt').forEach(function(el){
            var target=parseInt(el.dataset.t), n=0, step=target/50;
            var t=setInterval(function(){ n=Math.min(n+step,target); el.textContent=Math.round(n); if(n>=target)clearInterval(t); },20);
        });
    }, 500);
});
</script>
</body>
</html>
