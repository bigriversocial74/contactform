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

/* Stage 12N: reference-matched header and hero. */
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
  background:linear-gradient(90deg,rgba(0,0,0,.84),rgba(0,0,0,.22) 48%,rgba(0,0,0,.86)),url('/images/microgifter-hero-mesh.svg') center/cover no-repeat!important;
  opacity:.76!important;
  mix-blend-mode:normal!important;
}
.mg-v4 .mg-bg-mesh::after{
  background:linear-gradient(180deg,rgba(0,0,0,.12),rgba(0,0,0,.22) 48%,#020304 98%)!important;
}
.mg-v4-header,
.mg-v4 .mg-site-header{
  position:fixed!important;
  top:0!important;
  left:0!important;
  right:0!important;
  z-index:80!important;
  background:rgba(2,3,4,.74)!important;
  border-bottom:0!important;
  backdrop-filter:blur(18px)!important;
  animation:none!important;
  opacity:1!important;
  transform:none!important;
}
.mg-v4-nav{
  width:min(1640px,calc(100% - 96px))!important;
  height:96px!important;
  margin:0 auto!important;
  display:flex!important;
  align-items:center!important;
  justify-content:space-between!important;
  gap:34px!important;
  border-bottom:1px solid rgba(255,255,255,.13)!important;
}
.mg-v4-logo{
  display:inline-flex!important;
  align-items:center!important;
  text-decoration:none!important;
  min-width:0!important;
}
.mg-v4-logo img{
  width:286px!important;
  max-width:100%!important;
  height:auto!important;
  display:block!important;
}
.mg-v4-actions{
  display:flex!important;
  justify-content:flex-end!important;
  align-items:center!important;
  gap:34px!important;
  min-width:0!important;
}
.mg-v4-links{
  display:flex!important;
  align-items:center!important;
  justify-content:flex-end!important;
  gap:40px!important;
}
.mg-v4-links a{
  color:#e9e9e9!important;
  text-decoration:none!important;
  font-size:17px!important;
  font-weight:470!important;
  letter-spacing:-.02em!important;
}
.mg-v4-links a:hover{color:#fff!important;}
.mg-v4-cta{
  min-height:52px!important;
  display:inline-flex!important;
  align-items:center!important;
  gap:14px!important;
  padding:0 24px!important;
  border:1px solid rgba(255,255,255,.72)!important;
  border-radius:12px!important;
  color:#fff!important;
  text-decoration:none!important;
  font-size:16px!important;
  font-weight:740!important;
  background:rgba(255,255,255,.035)!important;
}
.mg-v4-cta:hover{background:#fff!important;color:#030304!important;}
.mg-v4-cta:hover *{color:#030304!important;}
.mg-v4-hero{
  position:relative;
  overflow:hidden;
  min-height:100svh;
  padding:158px 0 92px;
  isolation:isolate;
}
.mg-v4-hero::after{
  content:"";
  position:absolute;
  inset:0;
  z-index:1;
  pointer-events:none;
  background:radial-gradient(circle at 72% 48%,rgba(255,255,255,.08),transparent 26%),linear-gradient(90deg,rgba(2,3,4,.95) 0%,rgba(2,3,4,.72) 37%,rgba(2,3,4,.32) 62%,rgba(2,3,4,.82) 100%);
}
.mg-v4-hero-grid{
  position:relative;
  z-index:2;
  width:min(1640px,calc(100% - 96px));
  margin:0 auto;
  display:grid;
  grid-template-columns:minmax(0,1fr) minmax(420px,.78fr);
  gap:56px;
  align-items:center;
}
.mg-v4-copy{max-width:900px;}
.mg-v4-eyebrow{
  display:inline-flex;
  align-items:center;
  gap:14px;
  margin-bottom:34px;
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
  border:1px solid #f0a72e;
  border-radius:999px;
  box-shadow:0 0 20px rgba(240,167,46,.36);
}
.mg-v4-title{
  margin:0;
  max-width:940px;
  color:#fff;
  font-family:"Helvetica Neue","Inter",Arial,ui-sans-serif,system-ui,sans-serif;
  font-size:clamp(46px,4.6vw,84px);
  line-height:1.05;
  letter-spacing:-.052em;
  font-weight:780;
  text-wrap:balance;
}
.mg-v4-lede{
  margin:32px 0 0;
  max-width:720px;
  color:#d1d1d1;
  font-size:clamp(19px,1.42vw,26px);
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
  gap:22px;
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
.mg-v4-btn-primary,
.mg-v4-btn-primary *,
.mg-v4-btn-primary:visited{
  background:#fff!important;
  color:#030304!important;
}
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
  .mg-v4-nav{width:min(100% - 44px,1180px)!important;gap:24px!important;}
  .mg-v4-logo img{width:230px!important;}
  .mg-v4-links{gap:24px!important;}
  .mg-v4-links a{font-size:15px!important;}
  .mg-v4-hero-grid{width:min(100% - 44px,980px);grid-template-columns:1fr;gap:34px;}
  .mg-v4-visual{min-height:560px;}
  .mg-v4-phone{width:min(390px,82vw);}
  .mg-v4-plinth{right:50%;transform:translateX(50%) perspective(540px) rotateX(64deg) rotateZ(-3deg);width:360px;}
}
@media(max-width:860px){
  .mg-v4-links{display:none!important;}
}
@media(max-width:760px){
  .mg-v4-nav{height:72px!important;width:calc(100% - 28px)!important;}
  .mg-v4-logo img{width:190px!important;}
  .mg-v4-cta{min-height:44px!important;padding:0 14px!important;font-size:13px!important;}
  .mg-v4-hero{padding:92px 0 68px;}
  .mg-v4-hero-grid{width:calc(100% - 32px);display:flex;flex-direction:column;gap:18px;}
  .mg-v4-visual{order:1;min-height:360px;width:100%;}
  .mg-v4-copy{order:2;width:100%;}
  .mg-v4-phone{width:min(292px,78vw);transform:translateX(0) rotate(-1deg);}
  .mg-v4-plinth{width:280px;height:78px;bottom:6px;}
  .mg-v4-title{font-size:clamp(36px,9.8vw,52px);letter-spacing:-.042em;line-height:1.08;}
  .mg-v4-eyebrow{font-size:10px;letter-spacing:.22em;margin-bottom:20px;}
  .mg-v4-lede{font-size:17px;margin-top:22px;}
  .mg-v4-note{font-size:15px;}
  .mg-v4-buttons{gap:12px;margin-top:28px;}
  .mg-v4-btn{width:100%;min-height:56px;font-size:15px;}
}
</style>

<main class="mg-page mg-v4" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>

  <header class="mg-site-header mg-v4-header" aria-label="Microgifter site navigation">
    <nav class="mg-v4-nav">
      <a class="mg-v4-logo" href="/" aria-label="Microgifter home"><img src="/images/microgifter-logo-white.svg" alt="Microgifter"></a>

      <div class="mg-v4-actions">
        <div class="mg-v4-links" aria-label="Primary navigation">
          <a href="#platform">Platform</a>
          <a href="#growth">API</a>
          <a href="#merchants">Merchants</a>
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
        <div class="mg-v4-eyebrow">The rewards layer for local commerce</div>
        <h1 class="mg-v4-title" id="mgHeroTitle">Pre-purchase products and support your favorite creators, local businesses, and musicians.</h1>
        <p class="mg-v4-lede">Microgifter helps creators, local businesses, and musicians pre-sell products, launch reward campaigns, and manage customer demand from one simple platform.</p>
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
