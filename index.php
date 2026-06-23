<?php
declare(strict_types=1);

require __DIR__ . '/includes/landing/index-v3/part01.php';
require __DIR__ . '/includes/landing/index-v3/part02.php';
require __DIR__ . '/includes/landing/index-v3/part03.php';
require __DIR__ . '/includes/landing/index-v3/part04.php';
require __DIR__ . '/includes/landing/index-v3/part05.php';
require __DIR__ . '/includes/landing/index-v3/part06.php';
require __DIR__ . '/includes/landing/index-v3/part07.php';
require __DIR__ . '/includes/landing/index-v3/part08.php';
require __DIR__ . '/includes/landing/index-v3/part09.php';
?>
    max-width:220px!important;
  }
  .mg-market-ticker-center .mg-ticker-item{
    padding-left:8px!important;
    padding-right:8px!important;
  }
}

/* Stage 12N: rebuilt header and hero to match the new black/white reward-layer direction. */
:root{
  --mg-line:rgba(255,255,255,.16);
  --mg-gold:#ffffff;
  --mg-gold-2:#f4f4f4;
}
.mg-v4{
  min-height:100vh;
  background:#020304;
  color:#fff;
  font-family:"Inter","Helvetica Neue",Arial,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
.mg-v4 .mg-bg-mesh{
  background:linear-gradient(90deg,rgba(0,0,0,.84),rgba(0,0,0,.2) 48%,rgba(0,0,0,.86)),url('/images/microgifter-hero-mesh.svg') center/cover no-repeat!important;
  opacity:.74!important;
  mix-blend-mode:normal!important;
}
.mg-v4 .mg-bg-mesh::after{
  background:linear-gradient(180deg,rgba(0,0,0,.16),rgba(0,0,0,.23) 48%,#020304 98%)!important;
}
.mg-v4-header,
.mg-v4 .mg-site-header{
  position:fixed!important;
  top:0!important;
  left:0!important;
  right:0!important;
  z-index:80!important;
  background:rgba(2,3,4,.74)!important;
  border-bottom:1px solid rgba(255,255,255,.12)!important;
  backdrop-filter:blur(18px)!important;
  animation:none!important;
  opacity:1!important;
  transform:none!important;
}
.mg-v4-nav{
  width:min(1640px,calc(100% - 96px))!important;
  height:82px!important;
  margin:0 auto!important;
  display:grid!important;
  grid-template-columns:minmax(250px,.88fr) minmax(360px,1.25fr) minmax(340px,.82fr)!important;
  align-items:center!important;
  gap:26px!important;
}
.mg-v4-logo{
  display:inline-flex!important;
  align-items:center!important;
  gap:18px!important;
  text-decoration:none!important;
  min-width:0!important;
}
.mg-v4-logo img{
  width:280px!important;
  max-width:100%!important;
  height:auto!important;
  display:block!important;
}
.mg-v4-ticker{
  min-width:0;
  height:40px;
  display:flex;
  align-items:center;
  overflow:hidden;
  border:1px solid rgba(255,255,255,.18);
  border-radius:999px;
  background:rgba(255,255,255,.035);
  box-shadow:0 16px 36px rgba(0,0,0,.24),0 0 0 1px rgba(255,255,255,.025) inset;
}
.mg-v4-ticker-label{
  flex:0 0 auto;
  display:flex;
  align-items:center;
  height:100%;
  padding:0 16px;
  border-right:1px solid rgba(255,255,255,.1);
  color:#fff;
  font-size:10px;
  font-weight:900;
  letter-spacing:.18em;
  text-transform:uppercase;
}
.mg-v4-ticker-track{
  min-width:0;
  overflow:hidden;
}
.mg-v4-ticker-row{
  display:flex;
  align-items:center;
  width:max-content;
  animation:mgV4Ticker 36s linear infinite;
}
.mg-v4-tick{
  display:inline-flex;
  align-items:center;
  gap:8px;
  min-height:40px;
  padding:0 18px;
  border-left:1px solid rgba(255,255,255,.07);
  color:#e9e9e9;
  white-space:nowrap;
  font-size:11px;
  font-weight:760;
  letter-spacing:.03em;
}
.mg-v4-tick strong{color:#fff;letter-spacing:.1em;font-weight:900;}
.mg-v4-tick em{font-style:normal;color:#27d17f;font-weight:850;}
@keyframes mgV4Ticker{from{transform:translateX(0);}to{transform:translateX(-50%);}}
.mg-v4-actions{
  display:flex!important;
  justify-content:flex-end!important;
  align-items:center!important;
  gap:28px!important;
  min-width:0!important;
}
.mg-v4-links{
  display:flex!important;
  align-items:center!important;
  justify-content:flex-end!important;
  gap:30px!important;
}
.mg-v4-links a{
  color:#e9e9e9!important;
  text-decoration:none!important;
  font-size:15px!important;
  font-weight:520!important;
  letter-spacing:-.02em!important;
}
.mg-v4-links a:hover{color:#fff!important;}
.mg-v4-cta{
  min-height:52px!important;
  display:inline-flex!important;
  align-items:center!important;
  gap:14px!important;
  padding:0 22px!important;
  border:1px solid rgba(255,255,255,.72)!important;
  border-radius:12px!important;
  color:#fff!important;
  text-decoration:none!important;
  font-size:15px!important;
  font-weight:760!important;
  background:rgba(255,255,255,.035)!important;
}
.mg-v4-cta:hover{background:#fff!important;color:#030304!important;}
.mg-v4-hero{
  position:relative;
  overflow:hidden;
  min-height:100svh;
  padding:156px 0 92px;
  isolation:isolate;
}
.mg-v4-hero::after{
  content:"";
  position:absolute;
  inset:0;
  z-index:1;
  pointer-events:none;
  background:radial-gradient(circle at 72% 48%,rgba(255,255,255,.1),transparent 26%),linear-gradient(90deg,rgba(2,3,4,.94) 0%,rgba(2,3,4,.68) 38%,rgba(2,3,4,.28) 62%,rgba(2,3,4,.82) 100%);
}
.mg-v4-hero-grid{
  position:relative;
  z-index:2;
  width:min(1640px,calc(100% - 96px));
  margin:0 auto;
  display:grid;
  grid-template-columns:minmax(0,.98fr) minmax(440px,.82fr);
  gap:56px;
  align-items:center;
}
.mg-v4-copy{max-width:900px;}
.mg-v4-eyebrow{
  display:inline-flex;
  align-items:center;
  gap:14px;
  margin-bottom:38px;
  color:#fff;
  font-size:13px;
  font-weight:820;
  letter-spacing:.26em;
  text-transform:uppercase;
}
.mg-v4-eyebrow::before{
  content:"";
  width:14px;
  height:14px;
  border:1px solid rgba(255,255,255,.9);
  border-radius:999px;
  box-shadow:0 0 20px rgba(255,255,255,.28);
}
.mg-v4-title{
  margin:0;
  max-width:930px;
  color:#fff;
  font-family:"Helvetica Neue","Inter",Arial,ui-sans-serif,system-ui,sans-serif;
  font-size:clamp(58px,5.85vw,108px);
  line-height:1.025;
  letter-spacing:-.052em;
  font-weight:780;
  text-wrap:balance;
}
.mg-v4-lede{
  margin:34px 0 0;
  max-width:720px;
  color:#d1d1d1;
  font-size:clamp(19px,1.45vw,27px);
  line-height:1.55;
  letter-spacing:-.025em;
  font-weight:420;
}
.mg-v4-note{
  margin:22px 0 0;
  max-width:720px;
  color:#b7b7b7;
  font-size:clamp(16px,1.05vw,19px);
  line-height:1.65;
  font-weight:420;
}
.mg-v4-note strong{color:#fff;}
.mg-v4-buttons{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
  margin-top:44px;
}
.mg-v4-btn{
  min-height:64px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:18px;
  padding:0 34px;
  border-radius:13px;
  text-decoration:none;
  font-size:17px;
  font-weight:820;
}
.mg-v4-btn-primary{background:#fff;color:#030304;}
.mg-v4-btn-secondary{border:1px solid rgba(255,255,255,.38);background:rgba(255,255,255,.035);color:#fff;}
.mg-v4-visual{
  position:relative;
  min-height:720px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.mg-v4-phone{
  position:relative;
  z-index:2;
  width:min(520px,100%);
  height:auto;
  filter:drop-shadow(0 42px 80px rgba(0,0,0,.72));
  transform:translateX(-12px) rotate(-1deg);
}
.mg-v4-plinth{
  position:absolute;
  z-index:1;
  right:20px;
  bottom:26px;
  width:460px;
  height:120px;
  border-radius:24px;
  background:linear-gradient(135deg,#0e0e0e,#020202);
  box-shadow:0 35px 90px rgba(0,0,0,.75);
  transform:perspective(540px) rotateX(64deg) rotateZ(-3deg);
  opacity:.86;
}
.mg-v4 .mg-mesh-ribbon,
.mg-v4 .mg-agent-orbit,
.mg-v4 .mg-location-marker,
.mg-v4 .mg-device-base{display:none!important;}
@media(max-width:1260px){
  .mg-v4-nav{grid-template-columns:auto minmax(260px,1fr) auto;width:min(100% - 44px,1180px)!important;gap:18px!important;}
  .mg-v4-logo img{width:230px!important;}
  .mg-v4-links{display:none!important;}
  .mg-v4-hero-grid{width:min(100% - 44px,980px);grid-template-columns:1fr;gap:34px;}
  .mg-v4-visual{min-height:560px;}
  .mg-v4-phone{width:min(390px,82vw);}
  .mg-v4-plinth{right:50%;transform:translateX(50%) perspective(540px) rotateX(64deg) rotateZ(-3deg);width:360px;}
}
@media(max-width:760px){
  .mg-v4-nav{height:72px!important;grid-template-columns:auto 1fr auto!important;width:calc(100% - 28px)!important;}
  .mg-v4-logo img{width:190px!important;}
  .mg-v4-ticker{display:none!important;}
  .mg-v4-cta{min-height:44px!important;padding:0 14px!important;font-size:13px!important;}
  .mg-v4-hero{padding:118px 0 68px;}
  .mg-v4-hero-grid{width:calc(100% - 32px);}
  .mg-v4-title{font-size:clamp(44px,12vw,66px);letter-spacing:-.045em;}
  .mg-v4-lede{font-size:18px;}
  .mg-v4-buttons{gap:12px;margin-top:30px;}
  .mg-v4-btn{width:100%;min-height:56px;font-size:15px;}
  .mg-v4-visual{min-height:470px;}
}
</style>

<main class="mg-page mg-v4" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>

  <header class="mg-site-header mg-v4-header" aria-label="Microgifter site navigation">
    <nav class="mg-v4-nav">
      <a class="mg-v4-logo" href="/" aria-label="Microgifter home"><img src="/images/microgifter-logo-white.svg" alt="Microgifter"></a>

      <div class="mg-v4-ticker" aria-label="Experience market ticker">
        <div class="mg-v4-ticker-label">Experience Market</div>
        <div class="mg-v4-ticker-track">
          <div class="mg-v4-ticker-row">
            <span class="mg-v4-tick"><strong>MGFTR</strong> Microgifter <b>$0.842</b> <em>▲ 3.21%</em></span>
            <span class="mg-v4-tick"><strong>COF2</strong> Coffee for Two <b>$18.00</b> <em>▲ 4.2%</em></span>
            <span class="mg-v4-tick"><strong>BRNCH</strong> Weekend Brunch <b>$42.00</b> <em>▲ 8.7%</em></span>
            <span class="mg-v4-tick"><strong>VIPX</strong> VIP Experience <b>$225</b> <em>▲ 15.9%</em></span>
            <span class="mg-v4-tick"><strong>MGFTR</strong> Microgifter <b>$0.842</b> <em>▲ 3.21%</em></span>
            <span class="mg-v4-tick"><strong>COF2</strong> Coffee for Two <b>$18.00</b> <em>▲ 4.2%</em></span>
            <span class="mg-v4-tick"><strong>BRNCH</strong> Weekend Brunch <b>$42.00</b> <em>▲ 8.7%</em></span>
            <span class="mg-v4-tick"><strong>VIPX</strong> VIP Experience <b>$225</b> <em>▲ 15.9%</em></span>
          </div>
        </div>
      </div>

      <div class="mg-v4-actions">
        <div class="mg-v4-links" aria-label="Primary navigation">
          <a href="#platform">Platform</a>
          <a href="#growth">API</a>
          <a href="/developer-docs.php">Docs</a>
        </div>
        <a class="mg-v4-cta" href="/signup.php">Create Account <span>→</span></a>
      </div>
    </nav>
  </header>

  <section class="mg-v4-hero" aria-labelledby="mgHeroTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-v4-hero-grid">
      <div class="mg-v4-copy" data-reveal="left">
        <div class="mg-v4-eyebrow">The easiest way to</div>
        <h1 class="mg-v4-title" id="mgHeroTitle">Pre-purchase products and invest in your local community.</h1>
        <p class="mg-v4-lede">Microgifter helps local businesses pre-sell products, launch reward campaigns, and manage customer demand from one simple platform.</p>
        <p class="mg-v4-note"><strong>Rewards layer for local commerce:</strong> sell prepaid offers, distribute them anywhere, and track every claim, redemption, customer, and campaign through a connected rewards CRM.</p>
        <div class="mg-v4-buttons">
          <a class="mg-v4-btn mg-v4-btn-primary" href="/signup.php">Create Account <span>→</span></a>
          <a class="mg-v4-btn mg-v4-btn-secondary" href="/developer-docs.php">Build with the API <span>&lt;/&gt;</span></a>
        </div>
      </div>
      <div class="mg-v4-visual" data-reveal="right" aria-label="Microgifter reward phone preview">
        <img class="mg-v4-phone" src="/images/microgifter-hero-phone.svg" alt="Microgifter reward phone interface">
        <div class="mg-v4-plinth" aria-hidden="true"></div>
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchants" aria-labelledby="merchantTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
<?php
require __DIR__ . '/includes/landing/index-v3/part11.php';
require __DIR__ . '/includes/landing/index-v3/part12.php';
