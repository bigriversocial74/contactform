<?php
// Microgifter investor market model page.
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Microgifter Market Opportunity | Investor Portal';
$page_section = 'investors';
$header_mode = 'public';
$page_body_class = 'mg-investors-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_meta = [
    'description' => 'Microgifter investor market opportunity model with bottom-up ARR, unit economics, focused SOM, SAM, and TAM expansion path.',
    'canonical' => 'https://microgifter.com/investors.php',
    'og_title' => 'Microgifter Market Opportunity | Investor Portal',
    'og_description' => 'Review Microgifter’s market opportunity, merchant unit economics, focused SOM, SAM, TAM, and 5-year share model.',
];
$page_manifest = [
    'id' => 'investors',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'investors', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --inv-bg:#f8f4ed;
  --inv-paper:#fffdf8;
  --inv-card:#fff;
  --inv-ink:#090908;
  --inv-muted:#655f57;
  --inv-soft:#8a8174;
  --inv-line:#eadfce;
  --inv-gold:#d88c05;
  --inv-gold-2:#f6bd34;
  --inv-green:#07955b;
  --inv-shadow:0 24px 70px rgba(58,43,17,.095);
  --inv-max:1360px;
}
.mg-investors-page{background:var(--inv-bg)!important;color:var(--inv-ink)}
.mg-investors-page .mg-main{background:var(--inv-bg);overflow:hidden}
.inv-page,.inv-page *{box-sizing:border-box}
.inv-page{position:relative;isolation:isolate;font-family:Inter,"Helvetica Neue",Arial,sans-serif;background:linear-gradient(180deg,#fffdf8 0,#f9f4eb 48%,#fffdf9 100%);color:var(--inv-ink);overflow:hidden}
.inv-page:before{content:"";position:absolute;top:72px;right:-4%;width:58%;height:650px;background:url('/images/header_gradient_bg.png') center/cover no-repeat;opacity:.23;filter:grayscale(1);z-index:-1;pointer-events:none}
.inv-page:after{content:"";position:absolute;top:80px;left:0;right:0;height:620px;background:radial-gradient(circle at 15% 10%,rgba(255,255,255,.96),rgba(255,255,255,.3) 36%,transparent 68%),radial-gradient(circle at 78% 18%,rgba(216,140,5,.16),transparent 33%);z-index:-2;pointer-events:none}
.inv-wrap{width:min(var(--inv-max),calc(100% - 58px));margin:0 auto;padding:70px 0 72px}
.inv-badge{display:inline-flex;align-items:center;gap:9px;min-height:30px;padding:0 14px;border:1px solid rgba(216,140,5,.6);border-radius:999px;background:rgba(255,255,255,.74);color:#b86f00;font-size:11px;font-weight:950;letter-spacing:.12em;text-transform:uppercase;box-shadow:0 10px 30px rgba(216,140,5,.08)}
.inv-badge:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--inv-gold);box-shadow:0 0 0 4px rgba(216,140,5,.15)}
.inv-card{border:1px solid var(--inv-line);border-radius:22px;background:rgba(255,255,255,.9);box-shadow:var(--inv-shadow)}
.inv-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,430px);gap:42px;align-items:start;min-height:705px}
.inv-hero-main{padding-top:18px}
.inv-kicker{margin:32px 0 0;color:#1d1a16;font-size:13px;font-weight:950;letter-spacing:.07em;text-transform:uppercase}
.inv-title{max-width:780px;margin:18px 0 0;font-family:Georgia,"Times New Roman",serif;font-size:clamp(42px,5.4vw,76px);line-height:.98;letter-spacing:-.055em;font-weight:800;text-wrap:balance}
.inv-total-label{margin:30px 0 0;color:#211e1a;font-size:13px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.inv-total{margin:4px 0 0;font-size:clamp(70px,9vw,122px);line-height:.86;letter-spacing:-.09em;font-weight:950}
.inv-green{display:block;margin-top:12px;color:var(--inv-green);font-size:15px;font-weight:900}
.inv-hero-chart{margin-top:44px;max-width:850px}
.inv-hero-svg,.inv-step-svg{display:block;width:100%;height:auto;overflow:visible}
.inv-axis{fill:#211e1a;font-size:14px;font-weight:900}
.inv-axis-soft{fill:#5f584f;font-size:13px;font-weight:780}
.inv-grid{stroke:#dfd3c1;stroke-dasharray:4 7}
.inv-chart-line{fill:none;stroke:var(--inv-gold);stroke-width:5;stroke-linecap:round;stroke-linejoin:round}
.inv-chart-area{fill:url(#invHeroFill)}
.inv-dot{fill:#fff;stroke:var(--inv-gold);stroke-width:5}
.inv-tip-label{fill:#fff;font-size:13px;font-weight:950}
.inv-assumptions{padding:30px 30px 20px;border-radius:24px;background:rgba(255,255,255,.92);backdrop-filter:blur(16px)}
.inv-assumptions h2{margin:0 0 18px;font-family:Georgia,"Times New Roman",serif;font-size:28px;letter-spacing:-.04em}
.inv-assumption{display:grid;grid-template-columns:44px 1fr;gap:17px;align-items:center;min-height:82px;border-top:1px solid var(--inv-line)}
.inv-assumption:first-of-type{border-top:2px solid rgba(216,140,5,.45)}
.inv-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;color:var(--inv-gold);background:rgba(216,140,5,.08);font-size:22px;font-weight:900}
.inv-assumption strong{display:block;color:#15130f;font-size:14px;font-weight:950;line-height:1.25}
.inv-assumption span:not(.inv-icon){display:block;margin-top:2px;color:#4f4940;font-size:14px;line-height:1.28}
.inv-metric-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));margin-top:-98px;padding:0;overflow:hidden;position:relative;z-index:2}
.inv-strip-item{min-height:118px;padding:22px 24px;border-left:1px solid var(--inv-line);background:rgba(255,255,255,.82)}
.inv-strip-item:first-child{border-left:0}
.inv-strip-top{display:flex;align-items:center;gap:9px;color:#1a1713;font-size:12px;font-weight:950}
.inv-strip-top .inv-mini{color:var(--inv-gold);font-size:20px}
.inv-strip-value{display:flex;align-items:baseline;gap:6px;margin-top:18px;font-size:31px;font-weight:950;letter-spacing:-.055em}
.inv-strip-value small{font-size:15px;letter-spacing:0}.inv-strip-copy{margin-top:8px;color:#4f4942;font-size:13px;line-height:1.25;font-weight:760}
.inv-section{margin-top:36px}.inv-section-title{margin:0;font-family:Georgia,"Times New Roman",serif;font-size:32px;letter-spacing:-.045em}.inv-section-copy{margin:10px 0 0;color:#5b554d;font-size:15px;line-height:1.55}
.inv-formula{padding:28px 34px}.inv-formula h2{margin:0 0 26px;font-family:Georgia,"Times New Roman",serif;font-size:26px;letter-spacing:-.04em}.inv-formula-grid{display:grid;grid-template-columns:1fr 26px 1fr 26px 1fr 26px 1fr 26px 1.2fr 1.2fr;gap:12px;align-items:center}.inv-formula-item{text-align:center}.inv-formula-item b{display:grid;place-items:center;width:52px;height:52px;margin:0 auto 12px;border-radius:50%;background:rgba(216,140,5,.1);color:var(--inv-gold);font-size:25px}.inv-formula-item strong{display:block;font-size:31px;letter-spacing:-.05em}.inv-formula-item span{display:block;margin-top:6px;color:#4e4941;font-size:13px;line-height:1.25}.inv-op{text-align:center;font-size:27px;font-weight:950;color:#15120f}.inv-result{padding-left:22px;border-left:1px solid var(--inv-line);text-align:center}.inv-result strong{display:block;color:var(--inv-gold);font-size:44px;line-height:1;letter-spacing:-.06em}.inv-result span{display:block;margin-top:8px;color:#3e3932;font-size:14px;font-weight:760}
.inv-market-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px}.inv-market-card{padding:26px;border-radius:20px}.inv-market-head{display:flex;align-items:center;gap:12px;font-size:14px;font-weight:950}.inv-market-head .inv-mini{color:var(--inv-gold);font-size:25px}.inv-market-value{display:flex;align-items:baseline;gap:9px;margin-top:28px;font-size:43px;line-height:.94;font-weight:950;letter-spacing:-.06em}.inv-market-value small{font-size:18px;letter-spacing:0}.inv-market-card p{min-height:74px;margin:20px 0 0;color:#3f3933;font-size:15px;line-height:1.38}.inv-market-foot{display:flex;align-items:center;gap:9px;margin:24px -26px -26px;padding:18px 26px;border-top:1px solid var(--inv-line);border-radius:0 0 20px 20px;background:linear-gradient(90deg,rgba(216,140,5,.09),rgba(255,255,255,.75));font-weight:950}.inv-market-foot .inv-mini{color:var(--inv-gold)}
.inv-path{display:grid;grid-template-columns:310px 1fr;gap:30px;align-items:center;padding:32px 34px}.inv-legend{display:flex;align-items:center;gap:10px;margin-top:30px;color:#2b261f;font-size:13px;font-weight:850}.inv-legend:before{content:"";width:12px;height:12px;border-radius:50%;background:var(--inv-gold)}.inv-step{fill:none;stroke:var(--inv-gold);stroke-width:4;stroke-dasharray:6 8}.inv-bar{fill:url(#invBar)}.inv-chart-label{fill:#100f0d;font-size:17px;font-weight:950}.inv-chart-small{fill:#4c463f;font-size:14px;font-weight:780}.inv-chart-base{stroke:#c8bbaa;stroke-width:2}.inv-chart-dash{stroke:#dfd3c1;stroke-dasharray:4 7}
.inv-why{margin-top:38px}.inv-why-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin-top:20px}.inv-why-card{display:grid;grid-template-columns:54px 1fr;gap:16px;align-items:start;padding:24px;border:1px solid var(--inv-line);border-radius:18px;background:#fff;box-shadow:0 14px 40px rgba(58,43,17,.045)}.inv-why-card b{display:grid;place-items:center;width:54px;height:54px;border-radius:50%;background:rgba(216,140,5,.1);color:var(--inv-gold);font-size:25px}.inv-why-card strong{display:block;font-size:15px;font-weight:950}.inv-why-card span{display:block;margin-top:7px;color:#5c554d;font-size:13px;line-height:1.4}
.inv-cta{display:grid;grid-template-columns:100px 1fr 380px;gap:28px;align-items:center;margin-top:40px;padding:34px 38px;border:1px solid var(--inv-line);border-radius:24px;background:radial-gradient(circle at 7% 50%,rgba(216,140,5,.18),transparent 22%),linear-gradient(135deg,#fff4df,#fffdf8);box-shadow:var(--inv-shadow);position:relative;overflow:hidden}.inv-cta:after{content:"";position:absolute;right:-20px;bottom:-90px;width:380px;height:240px;background:url('/images/header_gradient_bg.png') center/cover no-repeat;opacity:.18;filter:grayscale(1);pointer-events:none}.inv-cta-icon{display:grid;place-items:center;width:86px;height:86px;border-radius:50%;background:#fff;color:var(--inv-gold);font-size:42px;position:relative;z-index:1}.inv-cta h2{margin:0;font-family:Georgia,"Times New Roman",serif;font-size:39px;line-height:1.05;letter-spacing:-.052em;position:relative;z-index:1}.inv-cta p{margin:12px 0 0;color:#5c554b;font-size:15px;line-height:1.45;position:relative;z-index:1}.inv-actions{display:grid;gap:14px;position:relative;z-index:1}.inv-btn{display:flex;align-items:center;justify-content:center;gap:12px;min-height:56px;border-radius:13px;text-decoration:none!important;font-size:14px;font-weight:950}.inv-btn-dark{background:#111;color:#fff!important;border:1px solid #111}.inv-btn-light{background:#fff;color:#111!important;border:1px solid rgba(17,17,17,.58)}
.inv-note{margin-top:14px;color:#80786d;font-size:12px;font-style:italic}.mg-investors-page .mg-site-footer{margin-top:0;background:#fffdf9;border-top:1px solid var(--inv-line)}
@media(max-width:1100px){.inv-hero{grid-template-columns:1fr}.inv-metric-strip{margin-top:28px;grid-template-columns:repeat(3,1fr)}.inv-formula-grid{grid-template-columns:1fr}.inv-op{font-size:21px}.inv-result{border-left:0;border-top:1px solid var(--inv-line);padding:22px 0 0}.inv-market-grid,.inv-why-grid{grid-template-columns:repeat(2,1fr)}.inv-path,.inv-cta{grid-template-columns:1fr}}
@media(max-width:700px){.inv-wrap{width:min(100% - 28px,var(--inv-max));padding-top:42px}.inv-title{font-size:43px}.inv-total{font-size:72px}.inv-metric-strip,.inv-market-grid,.inv-why-grid{grid-template-columns:1fr}.inv-strip-item{border-left:0;border-top:1px solid var(--inv-line)}.inv-strip-item:first-child{border-top:0}.inv-formula{padding:24px 18px}.inv-path{padding:24px 18px}.inv-cta{padding:26px 22px}.inv-cta h2{font-size:31px}}
</style>
<section class="inv-page" aria-labelledby="inv-title">
  <div class="inv-wrap">
    <section class="inv-hero" aria-label="Microgifter market opportunity overview">
      <div class="inv-hero-main">
        <span class="inv-badge">Investor model</span>
        <h1 class="inv-title" id="inv-title">Microgifter Market Opportunity</h1>
        <p class="inv-total-label">Total addressable ARR</p>
        <div class="inv-total">$7.04B</div>
        <span class="inv-green">+ projected from our 5-year market model</span>

        <div class="inv-hero-chart" aria-label="Market opportunity chart">
          <svg class="inv-hero-svg" viewBox="0 0 860 310" role="img" aria-label="Path from near-term milestone to SOM, SAM, and TAM">
            <defs>
              <linearGradient id="invHeroFill" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0" stop-color="#d88c05" stop-opacity=".22"/>
                <stop offset="1" stop-color="#d88c05" stop-opacity=".02"/>
              </linearGradient>
            </defs>
            <g class="inv-grid">
              <line x1="80" y1="36" x2="780" y2="36"/>
              <line x1="80" y1="90" x2="780" y2="90"/>
              <line x1="80" y1="144" x2="780" y2="144"/>
              <line x1="80" y1="198" x2="780" y2="198"/>
              <line x1="80" y1="252" x2="780" y2="252"/>
            </g>
            <g class="inv-axis">
              <text x="18" y="41">$8B</text><text x="18" y="95">$6B</text><text x="18" y="149">$4B</text><text x="18" y="203">$2B</text><text x="31" y="257">$0</text>
            </g>
            <polygon class="inv-chart-area" points="110,246 300,218 510,164 742,58 742,252 110,252"/>
            <polyline class="inv-chart-line" points="110,246 300,218 510,164 742,58"/>
            <g>
              <circle class="inv-dot" cx="110" cy="246" r="9"/><circle class="inv-dot" cx="300" cy="218" r="9"/><circle class="inv-dot" cx="510" cy="164" r="9"/><circle class="inv-dot" cx="742" cy="58" r="9"/>
              <rect x="704" y="18" width="72" height="30" rx="8" fill="#d88c05"/><text class="inv-tip-label" x="719" y="38">$7.04B</text>
            </g>
            <g class="inv-axis-soft" text-anchor="middle">
              <text x="110" y="286">Near-Term</text><text x="110" y="304">Milestone</text>
              <text x="300" y="286">SOM</text><text x="300" y="304">(7.5K)</text>
              <text x="510" y="286">SAM</text><text x="510" y="304">(50K)</text>
              <text x="742" y="286">TAM</text><text x="742" y="304">(500K)</text>
            </g>
          </svg>
        </div>
      </div>

      <aside class="inv-card inv-assumptions" aria-label="Core assumptions">
        <h2>Core Assumptions</h2>
        <div class="inv-assumption"><span class="inv-icon">♨</span><div><strong>Launch verticals: bars, restaurants, fast commerce</strong></div></div>
        <div class="inv-assumption"><span class="inv-icon">▤</span><div><strong>Subscription: $49/month</strong><span>per merchant</span></div></div>
        <div class="inv-assumption"><span class="inv-icon">%</span><div><strong>Commission rate: 15%</strong><span>per transaction</span></div></div>
        <div class="inv-assumption"><span class="inv-icon">▥</span><div><strong>Merchant activity: 10 sales</strong><span>per day</span></div></div>
        <div class="inv-assumption"><span class="inv-icon">◇</span><div><strong>Average ticket: $25</strong><span>per sale</span></div></div>
        <div class="inv-assumption"><span class="inv-icon">◎</span><div><strong>5-year share goal: 15%</strong><span>of focused SAM</span></div></div>
      </aside>
    </section>

    <section class="inv-card inv-metric-strip" aria-label="Key market values">
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">⚑</span>Near-Term Milestone</div><div class="inv-strip-value">$35.2M <small>ARR</small></div><div class="inv-strip-copy">2,500 merchants</div></article>
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">◎</span>SOM</div><div class="inv-strip-value">$105.7M <small>ARR</small></div><div class="inv-strip-copy">7,500 merchants</div></article>
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">▥</span>SAM</div><div class="inv-strip-value">$704.4M <small>ARR</small></div><div class="inv-strip-copy">50,000 merchants</div></article>
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">◎</span>TAM</div><div class="inv-strip-value">$7.04B <small>ARR</small></div><div class="inv-strip-copy">500,000 merchants</div></article>
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">$</span>Per Merchant ARR</div><div class="inv-strip-value">$14,088</div><div class="inv-strip-copy">$1,174 MRR per merchant</div></article>
      <article class="inv-strip-item"><div class="inv-strip-top"><span class="inv-mini">%</span>5-Year Share Goal</div><div class="inv-strip-value">15%</div><div class="inv-strip-copy">of focused SAM</div></article>
    </section>

    <section class="inv-section inv-card inv-formula" aria-label="Unit economics per merchant">
      <h2>Unit Economics per Merchant</h2>
      <div class="inv-formula-grid">
        <div class="inv-formula-item"><b>▥</b><strong>10</strong><span>sales / day</span></div>
        <div class="inv-op">×</div>
        <div class="inv-formula-item"><b>◇</b><strong>$25</strong><span>average sale</span></div>
        <div class="inv-op">×</div>
        <div class="inv-formula-item"><b>%</b><strong>15%</strong><span>commission</span></div>
        <div class="inv-op">+</div>
        <div class="inv-formula-item"><b>▤</b><strong>$49</strong><span>monthly subscription</span></div>
        <div class="inv-op">=</div>
        <div class="inv-result"><strong>$1,174</strong><span>MRR per merchant</span></div>
        <div class="inv-result"><strong>$14,088</strong><span>ARR per merchant</span></div>
      </div>
    </section>

    <section class="inv-section inv-market-grid" aria-label="Bottom-up market ladder">
      <article class="inv-card inv-market-card"><div class="inv-market-head"><span class="inv-mini">⚑</span>Near-Term Milestone</div><div class="inv-market-value">$35.2M <small>ARR</small></div><p>2,500 merchants × $14,088 ARR per merchant</p><div class="inv-market-foot"><span class="inv-mini">◎</span>$2.94M MRR</div></article>
      <article class="inv-card inv-market-card"><div class="inv-market-head"><span class="inv-mini">◎</span>SOM</div><div class="inv-market-value">$105.7M <small>ARR</small></div><p>7,500 merchants<br>15% of 50,000 focused launch merchants</p><div class="inv-market-foot"><span class="inv-mini">◎</span>$8.8M MRR</div></article>
      <article class="inv-card inv-market-card"><div class="inv-market-head"><span class="inv-mini">▥</span>SAM</div><div class="inv-market-value">$704.4M <small>ARR</small></div><p>50,000 bars, restaurants & fast-commerce merchants</p><div class="inv-market-foot"><span class="inv-mini">◎</span>$58.7M MRR</div></article>
      <article class="inv-card inv-market-card"><div class="inv-market-head"><span class="inv-mini">◎</span>TAM</div><div class="inv-market-value">$7.04B <small>ARR</small></div><p>500,000 local-commerce merchants across adjacent categories</p><div class="inv-market-foot"><span class="inv-mini">◎</span>$587M MRR</div></article>
    </section>
    <p class="inv-note">Internal Microgifter investor model. Figures are illustrative and based on current platform assumptions.</p>

    <section class="inv-section inv-card inv-path" aria-labelledby="path-title">
      <div>
        <h2 class="inv-section-title" id="path-title">Bottom-Up Path to TAM</h2>
        <p class="inv-section-copy">A clear path from our initial wedge into bars, restaurants, and fast commerce to a multi-billion dollar market opportunity.</p>
        <div class="inv-legend">ARR (Annual Recurring Revenue)</div>
      </div>
      <svg class="inv-step-svg" viewBox="0 0 900 350" role="img" aria-label="Bottom-up path to TAM">
        <defs><linearGradient id="invBar" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#f7bb34"/><stop offset="1" stop-color="#d88c05"/></linearGradient></defs>
        <g class="inv-chart-dash"><line x1="40" y1="64" x2="870" y2="64"/><line x1="40" y1="130" x2="870" y2="130"/><line x1="40" y1="196" x2="870" y2="196"/></g>
        <line class="inv-chart-base" x1="40" y1="274" x2="870" y2="274"/>
        <path class="inv-step" d="M130 242 H245 V222 H315 V187 H445 V148 H525 V107 H655 V64 H720"/>
        <rect class="inv-bar" x="86" y="242" width="92" height="32" rx="7"/><text class="inv-chart-label" x="72" y="228">$35.2M ARR</text><text class="inv-chart-small" text-anchor="middle" x="132" y="304">2.5K merchants</text><text class="inv-chart-small" text-anchor="middle" x="132" y="326">Near-Term</text>
        <rect class="inv-bar" x="300" y="214" width="92" height="60" rx="8"/><text class="inv-chart-label" x="288" y="200">$105.7M ARR</text><text class="inv-chart-small" text-anchor="middle" x="346" y="304">7.5K merchants</text><text class="inv-chart-small" text-anchor="middle" x="346" y="326">SOM</text>
        <rect class="inv-bar" x="514" y="146" width="106" height="128" rx="9"/><text class="inv-chart-label" x="508" y="132">$704.4M ARR</text><text class="inv-chart-small" text-anchor="middle" x="567" y="304">50K merchants</text><text class="inv-chart-small" text-anchor="middle" x="567" y="326">SAM</text>
        <rect class="inv-bar" x="724" y="50" width="116" height="224" rx="10"/><text class="inv-chart-label" x="726" y="34">$7.04B ARR</text><text class="inv-chart-small" text-anchor="middle" x="782" y="304">500K merchants</text><text class="inv-chart-small" text-anchor="middle" x="782" y="326">TAM</text>
      </svg>
    </section>

    <section class="inv-why" aria-labelledby="why-title">
      <h2 class="inv-section-title" id="why-title">Why this model works</h2>
      <div class="inv-why-grid">
        <article class="inv-why-card"><b>↻</b><div><strong>Recurring SaaS revenue</strong><span>$49 subscription creates stable base revenue.</span></div></article>
        <article class="inv-why-card"><b>%</b><div><strong>Transaction upside</strong><span>10 daily sales at 15% commission drives meaningful merchant ARPU.</span></div></article>
        <article class="inv-why-card"><b>◎</b><div><strong>Focused launch wedge</strong><span>Bars, restaurants, and fast commerce create a tight initial beachhead.</span></div></article>
        <article class="inv-why-card"><b>↗</b><div><strong>Expansion path</strong><span>The same model extends into broader local-commerce categories.</span></div></article>
      </div>
    </section>

    <section class="inv-cta" aria-label="Investor call to action">
      <div class="inv-cta-icon">🚀</div>
      <div><h2>Start with a focused SOM.<br>Scale into a much larger TAM.</h2><p>Microgifter’s bottom-up model compounds through merchant count, transaction volume, and adjacent market expansion.</p></div>
      <div class="inv-actions"><a class="inv-btn inv-btn-dark" href="/learn-more.php">▤ Request Deck</a><a class="inv-btn inv-btn-light" href="/investor-tam.php">▥ View Market Model</a></div>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
