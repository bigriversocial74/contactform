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

/* V10 light/gray logged-out header and hero. */
:root{
  --mg-v10-ink:#050505;
  --mg-v10-text:#171717;
  --mg-v10-muted:#262626;
  --mg-v10-paper:#f4f4f1;
  --mg-v10-line:rgba(10,10,10,.10);
}
.mg-v4{
  min-height:100vh;
  background:#f4f4f1;
  color:var(--mg-v10-text);
  font-family:"Inter","Helvetica Neue",Arial,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
.mg-v4 .mg-bg-mesh,
.mg-v4 .mg-bg-mesh::after,
.mg-v4 .mg-mesh-ribbon,
.mg-v4 .mg-agent-orbit,
.mg-v4 .mg-location-marker,
.mg-v4 .mg-device-base,
.mg-v4-plinth{display:none!important;}
.mg-v4-header,
.mg-v4 .mg-site-header{
  position:fixed!important;
  top:0!important;
  left:0!important;
  right:0!important;
  z-index:90!important;
  background:transparent!important;
  border-bottom:0!important;
  animation:none!important;
  opacity:1!important;
  transform:none!important;
  pointer-events:none!important;
}
.mg-v4-nav{
  width:min(1180px,calc(100% - 40px))!important;
  min-height:66px!important;
  margin:10px auto 0!important;
  padding:0 16px!important;
  display:flex!important;
  align-items:center!important;
  justify-content:space-between!important;
  gap:26px!important;
  border:1px solid rgba(20,20,20,.055)!important;
  border-radius:9px!important;
  background:rgba(247,247,244,.78)!important;
  box-shadow:0 18px 50px rgba(0,0,0,.055)!important;
  backdrop-filter:blur(18px)!important;
  -webkit-backdrop-filter:blur(18px)!important;
  pointer-events:auto!important;
}
.mg-v4-logo{
  display:inline-flex!important;
  align-items:center!important;
  text-decoration:none!important;
  min-width:0!important;
}
.mg-v4-logo img{
  width:142px!important;
  max-width:100%!important;
  height:auto!important;
  display:block!important;
}
.mg-v4-actions{
  display:flex!important;
  justify-content:flex-end!important;
  align-items:center!important;
  gap:26px!important;
  min-width:0!important;
}
.mg-v4-links{
  display:flex!important;
  align-items:center!important;
  justify-content:flex-end!important;
  gap:28px!important;
}
.mg-v4-links a{
  color:#0d0d0d!important;
  text-decoration:none!important;
  font-size:13px!important;
  font-weight:680!important;
  letter-spacing:-.02em!important;
  white-space:nowrap!important;
}
.mg-v4-links a::after{
  content:"+";
  display:inline-block;
  margin-left:10px;
  font-size:12px;
  font-weight:800;
  opacity:.62;
}
.mg-v4-links a:last-child::after{display:none;}
.mg-v4-links a:hover{color:#000!important;}
.mg-v4-cta{
  min-height:40px!important;
  display:inline-flex!important;
  align-items:center!important;
  justify-content:center!important;
  padding:0 20px!important;
  border:1px solid rgba(0,0,0,.06)!important;
  border-radius:7px!important;
  background:#fff!important;
  color:#0b0b0b!important;
  box-shadow:0 12px 30px rgba(0,0,0,.06)!important;
  text-decoration:none!important;
  font-size:13px!important;
  font-weight:800!important;
  letter-spacing:-.02em!important;
  white-space:nowrap!important;
}
.mg-v4-cta span{display:none!important;}
.mg-v4-cta:hover{background:#f8f8f6!important;color:#000!important;}
.mg-v4-hero{
  position:relative;
  z-index:4;
  min-height:108svh;
  overflow:visible;
  padding:132px 0 0;
  isolation:isolate;
  background:#f2f2ef;
}
.mg-v4-hero + .mg-section{
  position:relative;
  z-index:1;
}
.mg-v4-hero::before{
  content:"";
  position:absolute;
  inset:0;
  z-index:0;
  background:url('/images/header_gradient_bg.png') center top/cover no-repeat;
}
.mg-v4-hero::after{
  content:"";
  position:absolute;
  inset:0;
  z-index:1;
  pointer-events:none;
  background:linear-gradient(90deg,rgba(245,245,242,.98) 0%,rgba(245,245,242,.90) 32%,rgba(245,245,242,.28) 60%,rgba(245,245,242,.02) 100%),linear-gradient(180deg,rgba(255,255,255,.30) 0%,rgba(255,255,255,0) 52%,rgba(0,0,0,.88) 76%,#020202 100%);
}
.mg-v4-hero-grid{
  position:relative;
  z-index:2;
  width:min(1180px,calc(100% - 40px));
  min-height:calc(108svh - 132px);
  margin:0 auto;
  display:grid;
  grid-template-columns:minmax(0,650px) minmax(360px,1fr);
  grid-template-rows:auto 1fr;
  gap:28px 44px;
  align-items:start;
}
.mg-v4-copy{
  max-width:650px;
  padding-top:32px;
}
.mg-v4-eyebrow{display:none!important;}
.mg-v4-title{
  margin:0;
  max-width:650px;
  color:#050505;
  font-family:"Helvetica Neue","Inter",Arial,ui-sans-serif,system-ui,sans-serif;
  font-size:clamp(42px,4.2vw,64px);
  line-height:.96;
  letter-spacing:-.067em;
  font-weight:900;
  text-wrap:balance;
}
.mg-v4-lede{
  margin:30px 0 0;
  max-width:590px;
  color:#1c1c1c;
  font-size:clamp(20px,1.9vw,27px);
  line-height:1.03;
  letter-spacing:-.046em;
  font-weight:560;
}
.mg-v4-note{display:none!important;}
.mg-v4-buttons{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:28px;
}
.mg-v4-btn{
  min-height:50px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:16px;
  padding:0 24px;
  border-radius:7px;
  text-decoration:none;
  font-size:14px;
  font-weight:830;
  letter-spacing:-.02em;
}
.mg-v4-btn-primary,
.mg-v4-btn-primary *,
.mg-v4-btn-primary:visited{
  border:1px solid rgba(0,0,0,.08)!important;
  background:#fff!important;
  color:#050505!important;
  box-shadow:0 14px 34px rgba(0,0,0,.055)!important;
}
.mg-v4-btn-secondary,
.mg-v4-btn-secondary *,
.mg-v4-btn-secondary:visited{
  border:1px solid #050505!important;
  background:#050505!important;
  color:#fff!important;
  box-shadow:0 16px 38px rgba(0,0,0,.18)!important;
}
.mg-v4-visual{
  position:relative;
  z-index:3;
  grid-column:1 / -1;
  grid-row:2;
  align-self:end;
  width:100%;
  min-height:430px;
  display:flex;
  align-items:flex-end;
  justify-content:center;
  pointer-events:none;
  overflow:visible;
}
.mg-v10-desktop{
  position:relative;
  z-index:2;
  width:min(850px,78vw);
  margin:0 0 -74px 132px;
  border:12px solid #111;
  border-bottom-width:22px;
  border-radius:18px 18px 8px 8px;
  background:#fff;
  box-shadow:0 34px 80px rgba(0,0,0,.36);
  opacity:.78;
  filter:blur(.22px);
  display:block;
}
.mg-v4-phone{
  position:absolute;
  left:56px;
  bottom:-142px;
  z-index:8;
  width:min(330px,31vw);
  height:auto;
  display:block!important;
  filter:drop-shadow(0 34px 52px rgba(0,0,0,.50));
  transform:rotate(-2deg);
}
@media(max-width:1040px){
  .mg-v4-nav{width:min(100% - 28px,920px)!important;gap:18px!important;}
  .mg-v4-links{gap:18px!important;}
  .mg-v4-links a{font-size:12px!important;}
  .mg-v4-logo img{width:132px!important;}
  .mg-v4-hero-grid{grid-template-columns:1fr;}
  .mg-v4-copy{max-width:610px;}
  .mg-v10-desktop{margin-left:70px;width:min(760px,82vw);}
  .mg-v4-phone{left:28px;bottom:-132px;width:min(285px,36vw);}
}
@media(max-width:760px){
  .mg-v4-header,.mg-v4 .mg-site-header{position:absolute!important;}
  .mg-v4-nav{
    width:calc(100% - 24px)!important;
    min-height:60px!important;
    margin-top:8px!important;
    padding:0 12px!important;
    border-radius:8px!important;
  }
  .mg-v4-logo img{width:128px!important;}
  .mg-v4-links{display:none!important;}
  .mg-v4-cta{min-height:38px!important;padding:0 14px!important;font-size:12px!important;}
  .mg-v4-hero{min-height:1260px;padding:82px 0 0;overflow:hidden;}
  .mg-v4-hero::before{background-position:center top;background-size:cover;}
  .mg-v4-hero::after{
    background:linear-gradient(180deg,rgba(245,245,242,.02) 0%,rgba(245,245,242,.04) 42%,rgba(245,245,242,.88) 62%,rgba(245,245,242,.98) 80%,rgba(0,0,0,.84) 93%,#020202 100%);
  }
  .mg-v4-hero-grid{
    width:calc(100% - 28px);
    min-height:auto;
    display:flex;
    flex-direction:column;
    gap:0;
  }
  .mg-v4-visual{
    order:1;
    width:100%;
    min-height:560px;
    align-items:flex-end;
    justify-content:center;
    margin-top:0;
    overflow:visible;
    isolation:isolate;
  }
  .mg-v10-desktop{
    position:relative;
    z-index:5;
    width:96vw;
    margin:70px 0 -88px 28px;
    border-width:8px;
    border-bottom-width:15px;
    border-radius:13px 13px 7px 7px;
    opacity:1!important;
  }
  .mg-v4-phone{
    left:50%;
    right:auto;
    top:18px;
    bottom:auto;
    z-index:1;
    width:min(900px,240vw);
    max-width:none!important;
    opacity:1!important;
    filter:drop-shadow(0 34px 46px rgba(0,0,0,.48));
    transform:translateX(-38%) rotate(-5deg);
    transform-origin:center center;
  }
  .mg-v4-copy{
    position:relative;
    z-index:6;
    order:2;
    width:100%;
    max-width:100%;
    padding-top:184px;
    padding-bottom:82px;
  }
  .mg-v4-title{
    max-width:100%;
    font-size:clamp(34px,10vw,47px);
    line-height:1;
    letter-spacing:-.058em;
  }
  .mg-v4-lede{
    max-width:100%;
    margin-top:22px;
    font-size:18px;
    line-height:1.12;
    letter-spacing:-.035em;
  }
  .mg-v4-buttons{gap:10px;margin-top:24px;}
  .mg-v4-btn{min-height:48px;padding:0 18px;font-size:13px;}
}
@media(max-width:440px){
  .mg-v4-logo img{width:116px!important;}
  .mg-v4-cta{padding:0 12px!important;}
  .mg-v10-desktop{width:100vw;margin-left:20px;opacity:1!important;}
  .mg-v4-phone{left:50%;top:28px;bottom:auto;width:min(820px,232vw);opacity:1!important;transform:translateX(-36%) rotate(-5deg);}
}
</style>

<main class="mg-page mg-v4" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>

  <header class="mg-site-header mg-v4-header" aria-label="Microgifter site navigation">
    <nav class="mg-v4-nav">
      <a class="mg-v4-logo" href="/" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"></a>

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
    <div class="mg-v4-hero-grid">
      <div class="mg-v4-copy" data-reveal="left">
        <div class="mg-v4-eyebrow">The rewards layer for local commerce</div>
        <h1 class="mg-v4-title" id="mgHeroTitle">Instant gifting, Social Rewards &amp;  Automated Commerce Solutions</h1>
        <p class="mg-v4-lede">Microgifter helps creators, local businesses, and musicians pre-sell products, launch reward campaigns, and manage customer demand from one simple platform.</p>
        <p class="mg-v4-note"><strong>Rewards layer for local commerce:</strong> sell prepaid offers, distribute them anywhere, and track every claim, redemption, customer, and campaign through a connected rewards CRM.</p>
        <div class="mg-v4-buttons">
          <a class="mg-v4-btn mg-v4-btn-primary" href="/signup.php">Create Account <span>→</span></a>
          <a class="mg-v4-btn mg-v4-btn-secondary" href="/developer-docs.php">View API Docs <span>→</span></a>
        </div>
      </div>
      <div class="mg-v4-visual" data-reveal="right" aria-label="Microgifter product preview">
        <img class="mg-v10-desktop" src="/images/desktop_bg_main_v10.png" alt="Microgifter desktop product preview">
        <img class="mg-v4-phone" src="/images/mobile_bg_main.png" alt="Microgifter mobile product preview">
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchants" aria-labelledby="merchantTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
<?php
require __DIR__ . '/includes/landing/index-v3/part11.php';
require __DIR__ . '/includes/landing/index-v3/part12.php';