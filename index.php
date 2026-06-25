<?php
declare(strict_types=1);

/*
 * Microgifter Homepage - consolidated content with shared full-width header/footer.
 * This file intentionally replaces the old landing partial include stack for testing
 * while keeping the public universal header and footer components intact.
 */

$page_title = 'Microgifter | Invest Local, Discover Value & Support Humans';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
  '/assets/css/public-header-footer-fixes.css',
];
$page_scripts = [];
$page_manifest = [
  'id' => 'home-consolidated',
  'title' => $page_title,
  'section' => $page_section,
  'header_mode' => $header_mode,
  'assets' => ['universal-header'],
  'styles' => $page_styles,
  'scripts' => $page_scripts,
  'body_class' => 'mg-home-body',
  'public_header' => [
    'presentation' => false,
    'ticker' => true,
    'links' => [
      ['label' => 'Explore', 'href' => '/discover.php'],
      ['label' => 'Merchant', 'href' => '/merchant.php'],
      ['label' => 'Pricing', 'href' => '/pricing.php'],
      ['label' => 'Docs', 'href' => '/developer-docs.php'],
      ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
    ],
  ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
  :root{
    --mg-gold:#d9a735;
    --mg-gold-dark:#b98212;
    --mg-black:#050505;
    --mg-white:#f8f4ea;
    --mg-cream:#fbf6e8;
    --mg-muted:#8f8a80;
    --mg-card:#111111;
    --mg-green:#24d680;
    --mg-cyan:#37d7ff;
    --mg-red:#ff6464;
    --mg-line:rgba(217,167,53,.26);
    --mg-max:1180px;
  }

  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0;
    background:#050505;
    color:var(--mg-white);
    font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    overflow-x:hidden;
  }

  body.mg-home-body,
  body[data-page-id="home-consolidated"]{
    background:#050505;
  }

  a{color:inherit}
  .mg-home-page{position:relative;overflow:hidden;background:#050505;color:var(--mg-white)}
  .mg-container{width:min(var(--mg-max),calc(100% - 40px));margin:0 auto;position:relative;z-index:2}

  .mg-progress{
    position:fixed;left:0;top:0;height:3px;width:100%;z-index:9999;
    transform-origin:left center;transform:scaleX(0);
    background:linear-gradient(90deg,var(--mg-gold),#fff,var(--mg-gold));
    box-shadow:0 0 24px rgba(217,167,53,.7);
  }

  .mg-bg-grid{
    position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.5;
    background:
      linear-gradient(rgba(217,167,53,.05) 1px,transparent 1px),
      linear-gradient(90deg,rgba(217,167,53,.05) 1px,transparent 1px);
    background-size:72px 72px;
    mask-image:radial-gradient(circle at 50% 20%,#000 0,rgba(0,0,0,.9) 35%,transparent 76%);
  }
  .mg-orb{position:fixed;border-radius:999px;filter:blur(44px);opacity:.18;z-index:0;pointer-events:none;mix-blend-mode:screen}
  .mg-orb-a{width:360px;height:360px;background:var(--mg-gold);left:-140px;top:220px}
  .mg-orb-b{width:300px;height:300px;background:#fff;right:-150px;top:36%;opacity:.08}

  .mg-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;clip-path:inset(50%)}

  /* Homepage bottom-header ticker */
  .mg-bottom-stock-ticker{
    position:relative!important;
    top:auto!important;
    z-index:95!important;
    background:rgba(255,255,255,.96);
    border-top:1px solid rgba(0,0,0,.08);
    border-bottom:1px solid rgba(0,0,0,.10);
    box-shadow:0 10px 26px rgba(0,0,0,.08);
    overflow:hidden;
  }
  .mg-bottom-stock-ticker-inner{
    display:flex;
    align-items:center;
    min-height:42px;
    white-space:nowrap;
  }
  .mg-bottom-stock-label{
    flex:0 0 auto;
    display:inline-flex;
    align-items:center;
    gap:10px;
    min-height:42px;
    padding:0 28px;
    background:linear-gradient(90deg,rgba(255,255,255,.98) 0%,rgba(255,255,255,.98) 82%,rgba(255,255,255,0) 100%);
    color:#050505;
    font-size:13px;
    font-weight:950;
    letter-spacing:.14em;
    text-transform:uppercase;
    position:relative;
    z-index:2;
  }
  .mg-bottom-stock-label::before{
    content:"";
    width:9px;
    height:9px;
    border-radius:999px;
    background:var(--mg-green);
    box-shadow:0 0 18px rgba(36,214,128,.55);
  }
  .mg-bottom-stock-track{
    flex:1 1 auto;
    min-width:0;
    overflow:hidden;
  }
  .mg-bottom-stock-marquee{
    display:flex;
    width:max-content;
    animation:mgBottomTickerScroll 34s linear infinite;
  }
  .mg-bottom-stock-row{
    display:flex;
    align-items:center;
    min-width:max-content;
  }
  .mg-bottom-stock-item{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:42px;
    padding:0 24px;
    border-left:1px solid rgba(0,0,0,.08);
    color:#171717;
    text-decoration:none;
    font-size:13px;
    font-weight:850;
  }
  .mg-bottom-stock-item:hover{
    background:rgba(217,167,53,.08);
  }
  .mg-bottom-stock-item strong{
    color:#050505;
    font-size:13px;
    font-weight:950;
    letter-spacing:.12em;
  }
  .mg-bottom-stock-item span{
    color:#5f5f5a;
    font-weight:780;
  }
  .mg-bottom-stock-item b{
    color:#171717;
    font-weight:950;
  }
  .mg-bottom-stock-item em{
    color:var(--mg-green);
    font-style:normal;
    font-weight:950;
  }
  .mg-bottom-stock-item.is-down em{color:var(--mg-red)}
  .mg-bottom-stock-item.is-flat em{color:var(--mg-gold)}
  @keyframes mgBottomTickerScroll{
    from{transform:translateX(0)}
    to{transform:translateX(-50%)}
  }

  .mg-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 22px;border-radius:999px;border:1px solid transparent;text-decoration:none;font-weight:900;font-size:14px;letter-spacing:.01em;transition:transform .2s ease,border-color .2s ease,background .2s ease,color .2s ease,box-shadow .2s ease;cursor:pointer}
  .mg-btn:hover{transform:translateY(-2px)}
  .mg-btn-primary,.mg-btn-primary:visited{background:var(--mg-gold);color:#050505;box-shadow:0 18px 38px rgba(217,167,53,.22)}
  .mg-hero .mg-btn-secondary,
  .mg-hero .mg-btn-secondary:visited,
  .mg-hero .mg-btn-secondary span{
    color:#fff!important;
  }
  .mg-btn-secondary,.mg-btn-secondary:visited{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.16);color:#fff}
  .mg-btn-link{min-height:auto;padding:0;border:0;background:transparent;color:var(--mg-gold);font-weight:900;text-decoration:none}

  .mg-hero{
    min-height:1600px;
    padding:132px 0 72px;
    position:relative;
    isolation:isolate;
    background:
      radial-gradient(circle at 72% 12%,rgba(217,167,53,.14),transparent 30%),
      radial-gradient(circle at 24% 20%,rgba(255,255,255,.08),transparent 26%),
      linear-gradient(180deg,#050505 0%,#0a0a0a 46%,#050505 100%);
  }
  .mg-hero::before{
    content:"";position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.28;
    background:linear-gradient(120deg,transparent 0%,rgba(217,167,53,.16) 44%,transparent 60%);
    transform:translateX(-60%);
    animation:mgScan 8s ease-in-out infinite;
  }
  @keyframes mgScan{0%,100%{transform:translateX(-70%)}50%{transform:translateX(70%)}}
  .mg-hero-grid{display:grid;grid-template-columns:1fr;gap:54px;align-items:start;position:relative;z-index:2}
  .mg-hero-copy{max-width:760px}
  .mg-eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--mg-gold);font-size:12px;font-weight:950;letter-spacing:.14em;text-transform:uppercase;margin-bottom:18px}
  .mg-eyebrow::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--mg-gold);box-shadow:0 0 20px rgba(217,167,53,.55)}
  .mg-title{
    max-width:760px;
    margin:0;
    font-size:clamp(58px,9vw,128px);
    line-height:.82;
    letter-spacing:-.095em;
    text-transform:uppercase;
    color:#fff;
  }
  .mg-title .gold{color:var(--mg-gold)}
  .mg-lede{max-width:640px;margin:28px 0 0;color:#c7c1b5;font-size:clamp(18px,2vw,25px);line-height:1.46;font-weight:540}
  .mg-note{max-width:640px;margin:24px 0 0;color:#9f9a91;font-size:15px;line-height:1.72}
  .mg-note strong{color:#fff}
  .mg-hero-actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:32px}
  .mg-hero-visual{
    position:relative;
    min-height:980px;
    margin-top:200px;
  }
  .mg-desktop{
    width:min(1120px,92vw);
    margin:0 0 -36px 132px;
    border:1px solid rgba(217,167,53,.22);
    border-radius:30px;
    background:#111;
    box-shadow:0 42px 100px rgba(0,0,0,.52),0 0 0 1px rgba(255,255,255,.06) inset;
    overflow:hidden;
    transform:rotateX(3deg) rotateZ(-1deg);
    transform-origin:center;
  }
  .mg-browser-bar{height:42px;display:flex;align-items:center;gap:8px;padding:0 18px;background:#171717;border-bottom:1px solid rgba(255,255,255,.08)}
  .mg-dot{width:10px;height:10px;border-radius:999px;background:#484848}.mg-dot:nth-child(1){background:#ff5f57}.mg-dot:nth-child(2){background:#febc2e}.mg-dot:nth-child(3){background:#28c840}
  .mg-desktop-screen{aspect-ratio:16/9;background:#080808;position:relative;overflow:hidden}
  .mg-desktop-screen img{width:100%;height:100%;object-fit:cover;display:block;opacity:.92;filter:saturate(1.02) contrast(1.03)}
  .mg-screen-fallback{position:absolute;inset:0;display:grid;place-items:center;padding:32px;background:
    radial-gradient(circle at 20% 20%,rgba(217,167,53,.24),transparent 24%),
    radial-gradient(circle at 80% 30%,rgba(255,255,255,.1),transparent 22%),
    linear-gradient(135deg,#111,#050505)}
  .mg-ui-card{width:min(720px,82%);border:1px solid rgba(217,167,53,.3);border-radius:22px;background:rgba(5,5,5,.78);padding:28px;box-shadow:0 24px 90px rgba(0,0,0,.45)}
  .mg-ui-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:18px;margin-top:18px}.mg-ui-line{height:12px;border-radius:99px;background:rgba(255,255,255,.12);margin:10px 0}.mg-ui-line.gold{background:rgba(217,167,53,.55)}

  .mg-phone{
    position:absolute;
    right:2%;
    top:392px;
    width:min(300px,30vw);
    border:1px solid rgba(255,255,255,.12);
    border-radius:38px;
    background:#090909;
    padding:12px;
    box-shadow:0 36px 90px rgba(0,0,0,.62),0 0 0 1px rgba(217,167,53,.16) inset;
    z-index:4;
    transform:rotate(7deg);
  }
  .mg-phone-screen{aspect-ratio:9/19;border-radius:28px;background:#f5efe0;overflow:hidden;color:#050505;position:relative}
  .mg-phone-screen img{width:100%;height:100%;object-fit:cover;display:block}
  .mg-phone-fallback{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:space-between;padding:18px;background:linear-gradient(180deg,#fff7df,#e8ca72)}
  .mg-qr{width:92px;height:92px;background:
    linear-gradient(90deg,#050505 50%,transparent 50%) 0 0/18px 18px,
    linear-gradient(#050505 50%,transparent 50%) 0 0/18px 18px,#fff;margin-top:18px;border:10px solid #fff;box-shadow:0 0 0 2px #050505}
  .mg-phone-fallback h3{margin:0;font-size:26px;line-height:.9;letter-spacing:-.06em;text-transform:uppercase}.mg-phone-fallback p{margin:0;font-size:12px;font-weight:850}

  .mg-section{position:relative;padding:130px 0;background:#050505}
  .mg-section.alt{background:#0a0a0a}
  .mg-section.light{background:var(--mg-cream);color:#050505}
  .mg-section.light .mg-section-copy{color:#5d574e}
  .mg-section.light .mg-kicker{color:#8a650c}
  .mg-section-head{display:flex;align-items:end;justify-content:space-between;gap:30px;margin-bottom:56px}
  .mg-kicker{display:inline-flex;align-items:center;gap:9px;color:var(--mg-gold);font-size:12px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}
  .mg-kicker::before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}
  .mg-section-title{max-width:760px;margin:12px 0 0;font-size:clamp(38px,5vw,82px);line-height:.92;letter-spacing:-.07em;text-transform:uppercase;color:inherit}
  .mg-section-copy{max-width:520px;margin:18px 0 0;color:#aaa49a;font-size:16px;line-height:1.7}

  .mg-feature-panels.mg-growth-feature-grid{
    grid-template-columns:repeat(3,1fr);
    gap:20px;
  }
  .mg-feature-panels.mg-growth-feature-grid .mg-panel{
    min-height:390px;
  }
  .mg-signal-list{
    display:grid;
    gap:10px;
    margin-top:24px;
  }
  .mg-signal-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    min-height:44px;
    padding:0 14px;
    border:1px solid rgba(217,167,53,.20);
    border-radius:12px;
    background:rgba(255,255,255,.025);
    color:#e8e2d6;
    font-size:12px;
    font-weight:800;
  }
  .mg-signal-row span:last-child{
    color:var(--mg-gold);
    font-variant-numeric:tabular-nums;
  }
  .mg-value-score{
    margin-top:24px;
    padding:22px;
    border:1px solid rgba(217,167,53,.24);
    border-radius:16px;
    background:radial-gradient(circle at 20% 20%,rgba(217,167,53,.14),transparent 38%),rgba(255,255,255,.025);
  }
  .mg-value-score strong{
    display:block;
    color:#fff;
    font-size:46px;
    line-height:1;
    letter-spacing:-.06em;
  }
  .mg-value-score span{
    display:block;
    margin-top:8px;
    color:#c9c4ba;
    font-size:13px;
    line-height:1.45;
  }
  .mg-mini-path{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:8px;
    margin-top:24px;
  }
  .mg-mini-path span{
    min-height:58px;
    display:grid;
    place-items:center;
    text-align:center;
    padding:8px;
    border:1px solid rgba(217,167,53,.22);
    border-radius:12px;
    background:rgba(255,255,255,.025);
    color:#ede7dc;
    font-size:11px;
    font-weight:850;
  }
  .mg-feature-panels{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
  .mg-panel{position:relative;border:1px solid rgba(217,167,53,.22);border-radius:26px;background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.025));padding:28px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.25)}
  .mg-panel::before{content:"";position:absolute;inset:-1px;opacity:0;background:linear-gradient(135deg,rgba(217,167,53,.25),transparent 42%);transition:opacity .25s ease;pointer-events:none}.mg-panel:hover::before{opacity:1}
  .mg-panel-head{display:flex;gap:16px;align-items:flex-start;position:relative;z-index:1}
  .mg-panel-icon{width:52px;height:52px;flex:0 0 auto;border-radius:18px;background:rgba(217,167,53,.12);display:grid;place-items:center;color:var(--mg-gold)}
  .mg-panel-icon svg{width:26px;height:26px;stroke:currentColor;stroke-width:1.9;fill:none;stroke-linecap:round;stroke-linejoin:round}
  .mg-panel h3{margin:0;color:#fff;font-size:24px;line-height:1;letter-spacing:-.04em}.mg-panel p{margin:10px 0 0;color:#b8b1a4;font-size:14px;line-height:1.58}
  .mg-codebox{margin-top:28px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:#070707;padding:18px;color:#d9d1c2;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;line-height:1.7}.mg-codebox .gold{color:var(--mg-gold)}.mg-codebox .green{color:var(--mg-green)}.mg-codebox .muted{color:#8d8578}
  .mg-panel-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.mg-panel-tags span{font-size:11px;font-weight:900;color:#050505;background:var(--mg-gold);border-radius:999px;padding:7px 10px}

  .mg-split{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}
  .mg-large-card{border-radius:34px;border:1px solid rgba(217,167,53,.22);background:#101010;padding:34px;box-shadow:0 36px 100px rgba(0,0,0,.35);position:relative;overflow:hidden}.mg-large-card::before{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(217,167,53,.15);right:-120px;top:-120px;filter:blur(14px)}
  .mg-large-card h3{font-size:34px;margin:0;letter-spacing:-.055em}.mg-large-card p{color:#b8b1a4;line-height:1.7}.mg-flow{display:grid;gap:12px;margin-top:24px}.mg-flow-item{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px 16px;background:rgba(255,255,255,.04);color:#e7dfd0;font-weight:820}.mg-flow-item span:last-child{color:var(--mg-gold)}
  .mg-proof-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}.mg-proof-pill{border:1px solid rgba(217,167,53,.22);border-radius:18px;padding:20px;background:rgba(217,167,53,.08);font-weight:920;color:#fff}

  .mg-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;counter-reset:steps}.mg-step{counter-increment:steps;border-left:1px solid rgba(217,167,53,.32);padding:0 0 0 20px}.mg-step::before{content:"0" counter(steps);display:block;color:var(--mg-gold);font-weight:950;margin-bottom:18px}.mg-step h3{margin:0;font-size:22px;color:#050505;letter-spacing:-.04em}.mg-step p{margin:12px 0 0;color:#5f594f;line-height:1.65;font-size:14px}

  .mg-examples{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}.mg-example{border:1px solid rgba(5,5,5,.12);border-radius:24px;background:#fff;overflow:hidden;box-shadow:0 24px 60px rgba(5,5,5,.08)}.mg-example-art{height:170px;background:linear-gradient(135deg,#050505,var(--mg-gold));position:relative;overflow:hidden}.mg-example-art::before{content:"";position:absolute;inset:20px;border:1px solid rgba(255,255,255,.34);border-radius:18px}.mg-example-art::after{content:"";position:absolute;width:86px;height:86px;border-radius:50%;background:#fff;right:26px;top:36px;box-shadow:0 0 0 14px rgba(255,255,255,.16)}.mg-example-body{padding:22px}.mg-example h3{margin:0;font-size:22px;letter-spacing:-.04em}.mg-example p{margin:10px 0 16px;color:#625c52;line-height:1.55;font-size:14px}.mg-example-meta{display:grid;gap:8px}.mg-example-meta div{display:flex;justify-content:space-between;gap:12px;border-top:1px solid #eee;padding-top:8px;color:#6b645b;font-size:12px}.mg-example-meta strong{color:#050505}

  .mg-api-preview{display:grid;grid-template-columns:1fr 1fr;gap:34px;align-items:stretch}.mg-api-terminal{border:1px solid rgba(217,167,53,.26);border-radius:26px;background:#070707;overflow:hidden;box-shadow:0 36px 100px rgba(0,0,0,.36)}.mg-terminal-top{height:44px;background:#151515;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:8px;padding:0 16px}.mg-terminal-code{padding:26px;color:#d9d1c2;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;line-height:1.8}.mg-terminal-code .gold{color:var(--mg-gold)}.mg-terminal-code .green{color:var(--mg-green)}.mg-terminal-code .cyan{color:var(--mg-cyan)}.mg-api-card{border:1px solid rgba(217,167,53,.22);border-radius:26px;background:rgba(255,255,255,.045);padding:30px}.mg-api-card h3{margin:0;font-size:30px;letter-spacing:-.05em}.mg-api-flow-row{display:flex;gap:14px;align-items:flex-start;border-top:1px solid rgba(255,255,255,.1);padding:18px 0}.mg-api-flow-row:first-of-type{margin-top:18px}.mg-api-flow-dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:var(--mg-gold);color:#050505;font-size:12px;font-weight:950;flex:0 0 auto}.mg-api-flow-row p{margin:0;color:#b8b1a4;line-height:1.55}

  .mg-cta{padding:130px 0;background:#050505;text-align:center}.mg-cta-box{border:1px solid rgba(217,167,53,.28);border-radius:36px;background:
    radial-gradient(circle at 50% 0%,rgba(217,167,53,.20),transparent 42%),
    linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.025));padding:74px 34px;box-shadow:0 42px 120px rgba(0,0,0,.38)}.mg-cta h2{max-width:900px;margin:0 auto;font-size:clamp(42px,6vw,94px);line-height:.88;letter-spacing:-.075em;text-transform:uppercase}.mg-cta p{max-width:660px;margin:22px auto 0;color:#b8b1a4;font-size:17px;line-height:1.7}.mg-cta-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:14px;margin-top:32px}

  [data-reveal]{opacity:0;transform:translateY(30px);transition:opacity .8s ease,transform .8s cubic-bezier(.2,.8,.2,1);transition-delay:var(--delay,0ms)}[data-reveal].is-visible{opacity:1;transform:none}[data-reveal="left"]{transform:translateX(-34px)}[data-reveal="left"].is-visible{transform:none}[data-reveal="scale"]{transform:scale(.94);transform-origin:center}[data-reveal="scale"].is-visible{transform:scale(1)}

  @media(max-width:980px){
    .mg-container{width:min(100% - 28px,640px)}
    .mg-bottom-stock-label{padding:0 18px;font-size:11px}
    .mg-bottom-stock-item{padding:0 18px;font-size:12px}
    .mg-hero{min-height:1280px;padding:82px 0 64px;overflow:hidden}
    .mg-hero-copy{max-width:100%}
    .mg-title{max-width:100%;font-size:clamp(34px,10vw,47px);line-height:1;letter-spacing:-.058em}
    .mg-lede{font-size:17px;line-height:1.55}.mg-note{font-size:14px}
    .mg-hero-actions{display:grid;grid-template-columns:1fr;max-width:360px}.mg-btn{width:100%;min-height:48px}
    .mg-hero-visual{min-height:720px;margin-top:0}.mg-desktop{width:760px;max-width:none;margin:0 auto 16px;transform:translateX(-18%) rotateZ(-1deg);border-radius:22px}.mg-phone{width:210px;right:8px;top:286px;border-radius:30px;transform:rotate(5deg)}
    .mg-section{padding:84px 0}
    .mg-feature-panels.mg-growth-feature-grid{grid-template-columns:1fr}
    .mg-feature-panels.mg-growth-feature-grid .mg-panel{min-height:auto}
    .mg-mini-path{grid-template-columns:repeat(2,1fr)}
    .mg-section-head{display:grid;gap:12px;margin-bottom:34px}.mg-section-title{font-size:clamp(36px,11vw,58px);line-height:.95}.mg-feature-panels,.mg-split,.mg-steps,.mg-examples,.mg-api-preview{grid-template-columns:1fr}.mg-proof-grid{grid-template-columns:1fr}.mg-panel,.mg-large-card,.mg-api-card{padding:22px;border-radius:22px}.mg-examples{gap:16px}.mg-example-art{height:130px}.mg-cta{padding:84px 0}.mg-cta-box{padding:50px 20px;border-radius:26px}.mg-cta-actions{display:grid;max-width:360px;margin-left:auto;margin-right:auto}
  }

  @media(max-width:520px){
    .mg-desktop{width:680px;transform:translateX(-23%) rotateZ(-1deg);margin:0 auto 12px}.mg-phone{width:184px;right:-2px;top:258px}.mg-hero-visual{min-height:650px}.mg-ui-card{width:88%;padding:18px}.mg-ui-grid{grid-template-columns:1fr}.mg-phone-fallback h3{font-size:22px}.mg-qr{width:72px;height:72px}
  }

  /* Desktop hero title lock: exactly three lines */
  @media (min-width:981px){
    body[data-page-id="home-consolidated"] .mg-hero-copy{
      max-width:1040px!important;
    }
    body[data-page-id="home-consolidated"] .mg-title{
      max-width:1040px!important;
      width:1040px!important;
    }
    body[data-page-id="home-consolidated"] .mg-title-line{
      display:block;
      white-space:nowrap;
    }
  }
  @media (max-width:980px){
    body[data-page-id="home-consolidated"] .mg-title-line{
      display:block;
    }
  }

  .mg-hero-pretitle{
    display:inline-flex;
    align-items:center;
    gap:10px;
    margin:0 0 22px;
    color:#050505;
    font-size:13px;
    font-weight:950;
    letter-spacing:.14em;
    line-height:1.2;
    text-transform:uppercase;
  }
  .mg-hero-pretitle::before{
    content:"";
    width:9px;
    height:9px;
    border-radius:999px;
    background:var(--mg-gold);
    box-shadow:0 0 18px rgba(217,167,53,.42);
  }
  @media(max-width:680px){
    .mg-hero-pretitle{
      margin-bottom:16px;
      font-size:11px;
      letter-spacing:.11em;
    }
  }
</style>

<div class="mg-bottom-stock-ticker" aria-label="Sample local experience ticker">
  <div class="mg-bottom-stock-ticker-inner">
    <div class="mg-bottom-stock-label">Local market</div>
    <div class="mg-bottom-stock-track">
      <div class="mg-bottom-stock-marquee">
        <div class="mg-bottom-stock-row">
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>MGFTR</strong><span>Local market</span><b>$0.842</b><em>▲ 3.21%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>COF2</strong><span>Coffee for Two</span><b>$18.00</b><em>▲ 4.2%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>BRNCH</strong><span>Weekend Brunch</span><b>$42.00</b><em>▲ 8.7%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>CHEF</strong><span>Chef Table</span><b>$150.00</b><em>▲ 12.4%</em></a>
          <a class="mg-bottom-stock-item is-down" href="https://microgifter.com/tom"><strong>SPA</strong><span>Wellness Trial</span><b>$29.00</b><em>▼ 1.1%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>SHOW</strong><span>Venue Night</span><b>$36.00</b><em>▲ 6.1%</em></a>
          <a class="mg-bottom-stock-item is-flat" href="https://microgifter.com/tom"><strong>TACO</strong><span>Food Crawl</span><b>$55.00</b><em>● 0.0%</em></a>
        </div>
        <div class="mg-bottom-stock-row" aria-hidden="true">
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>MGFTR</strong><span>Local market</span><b>$0.842</b><em>▲ 3.21%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>COF2</strong><span>Coffee for Two</span><b>$18.00</b><em>▲ 4.2%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>BRNCH</strong><span>Weekend Brunch</span><b>$42.00</b><em>▲ 8.7%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>CHEF</strong><span>Chef Table</span><b>$150.00</b><em>▲ 12.4%</em></a>
          <a class="mg-bottom-stock-item is-down" href="https://microgifter.com/tom"><strong>SPA</strong><span>Wellness Trial</span><b>$29.00</b><em>▼ 1.1%</em></a>
          <a class="mg-bottom-stock-item" href="https://microgifter.com/tom"><strong>SHOW</strong><span>Venue Night</span><b>$36.00</b><em>▲ 6.1%</em></a>
          <a class="mg-bottom-stock-item is-flat" href="https://microgifter.com/tom"><strong>TACO</strong><span>Food Crawl</span><b>$55.00</b><em>● 0.0%</em></a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="mg-home-page" id="top">
  <div class="mg-progress" id="mgProgressBar" aria-hidden="true"></div>
  <div class="mg-bg-grid" aria-hidden="true"></div>
  <div class="mg-orb mg-orb-a" aria-hidden="true"></div>
  <div class="mg-orb mg-orb-b" aria-hidden="true"></div>

  <main>
    <section class="mg-hero" aria-labelledby="mgHeroTitle">
      <div class="mg-container">
        <div class="mg-hero-grid">
          <div class="mg-hero-copy" data-reveal="left">
            <span class="mg-hero-pretitle">Data Resource Management Solutions (DRM)</span>
        <h1 class="mg-title" id="mgHeroTitle"><span class="mg-title-line">INVEST LOCAL,</span><span class="mg-title-line">DISCOVER VALUE &amp;</span><span class="mg-title-line">SUPPORT HUMANS</span></h1>
            <p class="mg-note"><strong>Business data is the value layer:</strong> Microgifter helps local businesses turn support into structured data, use that data to discover what customers value, and earn more from the relationships they already create.</p>
            <div class="mg-hero-actions">
              <a class="mg-btn mg-btn-primary" href="/signup.php">Create Account</a>
              <a class="mg-btn mg-btn-secondary" href="/developer-docs.php">View API Docs</a>
            </div>
          </div>

          <div class="mg-hero-visual" data-reveal="scale" style="--delay:180ms">
            <div class="mg-desktop" aria-label="Microgifter desktop product view">
              <div class="mg-browser-bar"><span class="mg-dot"></span><span class="mg-dot"></span><span class="mg-dot"></span></div>
              <div class="mg-desktop-screen">
                <img src="/images/desktop_bg_main_v10.png" alt="Microgifter promotional CRM desktop dashboard" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                <div class="mg-screen-fallback" style="display:none">
                  <div class="mg-ui-card">
                    <div class="mg-kicker">Promotional CRM</div>
                    <h2 style="margin:10px 0 0;font-size:46px;line-height:.92;letter-spacing:-.06em">Launch rewards, capture demand.</h2>
                    <div class="mg-ui-grid"><div><div class="mg-ui-line gold"></div><div class="mg-ui-line"></div><div class="mg-ui-line"></div><div class="mg-ui-line" style="width:70%"></div></div><div><div class="mg-ui-line gold" style="height:70px;border-radius:16px"></div></div></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mg-phone" aria-label="Mobile table tent QR reward preview">
              <div class="mg-phone-screen">
                <img src="/images/mobile_bg_main.png" alt="Mobile phone with Microgifter QR table tent promotion" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="mg-phone-fallback" style="display:none">
                  <div><p>TABLE TENT REWARD</p><h3>Scan for coffee</h3></div>
                  <div class="mg-qr" aria-hidden="true"></div>
                  <p>Gift it. Save it. Redeem it locally.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="mg-section" id="merchants" aria-labelledby="merchantTitle">
      <div class="mg-container">
        <div class="mg-section-head">
          <div>
            <span class="mg-kicker" data-reveal="left">For Growth</span>
            <h2 class="mg-section-title" id="merchantTitle" data-reveal="left">Your business already creates value. Microgifter helps you capture it, understand it, and turn it into repeatable growth.</h2>
          </div>
          <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Every offer, reward, gift certificate, campaign, claim, redemption, and customer interaction creates a signal. Microgifter organizes those signals into a practical data layer that helps local businesses make smarter offers, support better customers, and earn more from future demand.</p>
        </div>

        <div class="mg-feature-panels mg-growth-feature-grid">
        <article class="mg-panel" data-reveal="scale">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M8 5l-5 7 5 7M16 5l5 7-5 7M14 4l-4 16"/></svg></div>
            <div><h3>Create Value</h3><p>Build offers, rewards, gift certificates, pre-sale products, and local experiences that give customers a clear reason to act.</p></div>
          </div>
          <div class="mg-codebox" aria-label="Create value data example"><span class="gold">VALUE OBJECT</span><br>{<br>&nbsp;&nbsp;<span class="muted">"offer"</span>: <span class="green">"coffee_for_two"</span>,<br>&nbsp;&nbsp;<span class="muted">"price"</span>: <span class="green">"$18.00"</span>,<br>&nbsp;&nbsp;<span class="muted">"goal"</span>: <span class="green">"bring two humans in"</span>,<br>&nbsp;&nbsp;<span class="muted">"signal"</span>: <span class="green">"visit_intent"</span><br>}</div>
          <div class="mg-panel-tags"><span>Offer</span><span>Support</span><span>Action</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:80ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M4 13h4l11-6v10L8 11H4v2Z"/><path d="M8 11v7a2 2 0 0 0 2 2h1M21 9c1 2 1 4 0 6"/></svg></div>
            <div><h3>Capture Data</h3><p>Turn claims, purchases, redemptions, QR scans, shares, saves, and visits into clean customer and campaign data.</p></div>
          </div>
          <div class="mg-signal-list" aria-label="Captured data signals">
            <div class="mg-signal-row"><span>Claim source</span><span>QR table tent</span></div>
            <div class="mg-signal-row"><span>Customer action</span><span>Saved reward</span></div>
            <div class="mg-signal-row"><span>Redemption</span><span>Verified</span></div>
            <div class="mg-signal-row"><span>Follow-up path</span><span>Ready</span></div>
          </div>
          <div class="mg-panel-tags"><span>Signal</span><span>Profile</span><span>History</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:160ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/><path d="M8 11h6M11 8v6"/></svg></div>
            <div><h3>Discover Value</h3><p>See which offers, prices, channels, timing, and experiences customers actually respond to — not what you guessed they wanted.</p></div>
          </div>
          <div class="mg-value-score" aria-label="Value discovery score">
            <strong>87</strong>
            <span>Value confidence score based on claims, redemption, repeat activity, and customer engagement.</span>
          </div>
          <div class="mg-panel-tags"><span>Pattern</span><span>Demand</span><span>Timing</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:240ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M12 21s-7-4.2-9-9.5C1.8 8.4 3.7 5 7.1 5c2 0 3.5 1.1 4.9 3 1.4-1.9 2.9-3 4.9-3 3.4 0 5.3 3.4 4.1 6.5C19 16.8 12 21 12 21Z"/></svg></div>
            <div><h3>Activate Support</h3><p>Give customers a direct way to support real local businesses and real humans while creating measurable demand for the merchant.</p></div>
          </div>
          <div class="mg-mini-path" aria-label="Support activation path">
            <span>Find</span><span>Support</span><span>Gift</span><span>Redeem</span>
          </div>
          <div class="mg-panel-tags"><span>Human</span><span>Local</span><span>Direct</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:320ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-5 4.2-8 8-8s6.5 3 8 8"/></svg></div>
            <div><h3>Utilize Insight</h3><p>Use captured data to personalize follow-up, improve the next campaign, identify valuable customers, and reduce wasted marketing effort.</p></div>
          </div>
          <div class="mg-signal-list" aria-label="Utilized data actions">
            <div class="mg-signal-row"><span>Best audience</span><span>Repeat guests</span></div>
            <div class="mg-signal-row"><span>Best offer</span><span>Two-person gifts</span></div>
            <div class="mg-signal-row"><span>Best channel</span><span>In-store QR</span></div>
            <div class="mg-signal-row"><span>Next action</span><span>Send follow-up</span></div>
          </div>
          <div class="mg-panel-tags"><span>Learn</span><span>Target</span><span>Grow</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:400ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M7 16l4-5 3 3 5-8"/></svg></div>
            <div><h3>Earn on Value</h3><p>Turn support, customer data, and future demand into repeat revenue — earning more from the relationships the business already owns.</p></div>
          </div>
          <div class="mg-value-score" aria-label="Earned value example">
            <strong>3.4x</strong>
            <span>Example repeat-value lift when claims, redemptions, and follow-up campaigns work as one connected loop.</span>
          </div>
          <div class="mg-panel-tags"><span>Return</span><span>Repeat</span><span>Revenue</span></div>
        </article>
      </div>
      </div>
    </section>

    <section class="mg-section alt" aria-labelledby="agenticTitle">
      <div class="mg-container mg-split">
        <div data-reveal="left">
          <span class="mg-kicker">Rewards layer</span>
          <h2 class="mg-section-title" id="agenticTitle">The value is not only the transaction. It is the business intelligence created around it.</h2>
          <p class="mg-section-copy">Local businesses generate valuable signals every day, but most of that value disappears across social posts, paper coupons, disconnected payment systems, and untracked customer behavior. Microgifter captures those signals and turns them into a usable operating layer for growth.</p>
        </div>
        <div class="mg-large-card" data-reveal="scale" style="--delay:140ms">
          <h3>What business data value means</h3>
          <p>Every offer should teach the business something: what customers value, which channel moved them, what they claimed, whether they redeemed, and what made them return.</p>
          <p>Microgifter makes that trail operational, so businesses can stop guessing and start using their own data to build stronger customer relationships and more efficient revenue loops.</p>
          <h3 style="margin-top:28px">The efficient value loop</h3>
          <div class="mg-flow">
            <div class="mg-flow-item"><span>Create a local value action</span><span>→</span></div>
            <div class="mg-flow-item"><span>Capture the customer signal</span><span>→</span></div>
            <div class="mg-flow-item"><span>Connect actions to business intelligence</span><span>→</span></div>
            <div class="mg-flow-item"><span>Use insight to improve the next offer</span><span>→</span></div>
            <div class="mg-flow-item"><span>Earn more from owned relationships</span><span>✓</span></div>
          </div>
        </div>
      </div>
    </section>

    <section class="mg-section light" id="simple" aria-labelledby="howTitle">
      <div class="mg-container">
        <div class="mg-section-head">
          <div>
            <span class="mg-kicker" data-reveal="left">How it works</span>
            <h2 class="mg-section-title" id="howTitle" data-reveal="left">A simple loop for creating, capturing, and utilizing local value.</h2>
          </div>
          <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">The goal is to make local commerce smarter without making it complicated: create a valuable customer action, capture the signal it creates, connect it to a customer record, and use the result to make the next action more profitable.</p>
        </div>
        <div class="mg-steps">
          <article class="mg-step" data-reveal="left"><h3>Create value</h3><p>Build an offer, reward, gift certificate, contest entry, landing page, or pre-sale product with a clear customer benefit and a measurable business goal.</p></article>
          <article class="mg-step" data-reveal="left" style="--delay:80ms"><h3>Capture the data</h3><p>Distribute through QR codes, feeds, newsletters, social posts, table tents, landing pages, partner apps, or the API while capturing where each action came from.</p></article>
          <article class="mg-step" data-reveal="left" style="--delay:160ms"><h3>Discover what matters</h3><p>Connect claims, redemptions, purchases, visits, profiles, and campaign sources to discover what customers actually value and what drives them back.</p></article>
          <article class="mg-step" data-reveal="left" style="--delay:240ms"><h3>Utilize the insight</h3><p>Use the data to improve targeting, personalize follow-up, forecast demand, increase repeat visits, and earn more from the relationships already created.</p></article>
        </div>
      </div>
    </section>

    <section class="mg-section light" aria-labelledby="examplesTitle">
      <div class="mg-container">
        <div class="mg-section-head">
          <div>
            <span class="mg-kicker" data-reveal="left">Offer examples</span>
            <h2 class="mg-section-title" id="examplesTitle" data-reveal="left">Every local action can become a data asset.</h2>
          </div>
          <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">A coffee reward, lunch special, event pass, or trial class is more than a sale. It shows what customers value, when they act, how they support, and what brings them back.</p>
        </div>
        <div class="mg-examples">
          <article class="mg-example" data-reveal="scale"><div class="mg-example-art"></div><div class="mg-example-body"><h3>Coffee for Two</h3><p>Captures gift behavior, visit timing, redemption activity, and the first signal of a repeat customer.</p><div class="mg-example-meta"><div><span>Avg value</span><strong>$18</strong></div><div><span>Signal</span><strong>Visit intent</strong></div></div></div></article>
          <article class="mg-example" data-reveal="scale" style="--delay:80ms"><div class="mg-example-art"></div><div class="mg-example-body"><h3>Lunch Special</h3><p>Shows which offers move demand into slower windows and which customers respond to value-based timing.</p><div class="mg-example-meta"><div><span>Avg value</span><strong>$24</strong></div><div><span>Signal</span><strong>Demand shift</strong></div></div></div></article>
          <article class="mg-example" data-reveal="scale" style="--delay:160ms"><div class="mg-example-art"></div><div class="mg-example-body"><h3>Two Drink Reward</h3><p>Connects pre-event intent, in-person redemption, and post-event follow-up into one measurable loop.</p><div class="mg-example-meta"><div><span>Avg value</span><strong>$16</strong></div><div><span>Signal</span><strong>Event lift</strong></div></div></div></article>
          <article class="mg-example" data-reveal="scale" style="--delay:240ms"><div class="mg-example-art"></div><div class="mg-example-body"><h3>Trial Class Pass</h3><p>Turns a first visit into a profile, a conversion path, and a clear follow-up opportunity.</p><div class="mg-example-meta"><div><span>Avg value</span><strong>$35</strong></div><div><span>Signal</span><strong>Lead value</strong></div></div></div></article>
        </div>
      </div>
    </section>

    <section class="mg-section" id="api" aria-labelledby="apiPreviewTitle">
      <div class="mg-container">
        <div class="mg-section-head">
          <div>
            <span class="mg-kicker" data-reveal="left">Distribution API</span>
            <h2 class="mg-section-title" id="apiPreviewTitle" data-reveal="left">A data-value layer developers can wire into local commerce.</h2>
          </div>
          <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Use Microgifter to create reward actions, capture customer signals, verify redemption, and send structured value data back into CRMs, apps, loyalty workflows, dashboards, and AI-powered commerce systems.</p>
        </div>
        <div class="mg-api-preview">
          <div class="mg-api-terminal" data-reveal="scale">
            <div class="mg-terminal-top"><span class="mg-dot"></span><span class="mg-dot"></span><span class="mg-dot"></span></div>
            <pre class="mg-terminal-code"><code><span class="gold">POST</span> /api/distribution/rewards
{
  <span class="cyan">"merchant_id"</span>: <span class="green">"local-cafe"</span>,
  <span class="cyan">"reward"</span>: <span class="green">"coffee_for_two"</span>,
  <span class="cyan">"channel"</span>: <span class="green">"agentic-shopping"</span>,
  <span class="cyan">"recipient"</span>: <span class="green">"customer_wallet"</span>
}</code></pre>
          </div>
          <div class="mg-api-card" data-reveal="left" style="--delay:120ms">
            <h3>From support to intelligence</h3>
            <div class="mg-api-flow-row"><span class="mg-api-flow-dot">01</span><p>A merchant or partner app creates a local value action: offer, reward, gift, contest, or pre-sale product.</p></div>
            <div class="mg-api-flow-row"><span class="mg-api-flow-dot">02</span><p>Microgifter captures source, claim, customer, campaign, and redemption context as structured data.</p></div>
            <div class="mg-api-flow-row"><span class="mg-api-flow-dot">03</span><p>The customer clicks, claims, shares, saves, buys, visits, or redeems.</p></div>
            <div class="mg-api-flow-row"><span class="mg-api-flow-dot">04</span><p>The business uses that data to improve campaigns, increase customer value, and earn more from future demand.</p></div>
            <a class="mg-btn mg-btn-primary" href="/developer-docs.php" style="margin-top:20px">Read API Docs</a>
          </div>
        </div>
      </div>
    </section>

    <section class="mg-cta" aria-labelledby="mgPublicDemoTitle">
      <div class="mg-container">
        <div class="mg-cta-box" data-reveal="scale">
          <h2 id="mgPublicDemoTitle">See how Microgifter turns local support into usable business data.</h2>
          <p>Walk through the full value loop: create the offer, capture the signal, verify the redemption, understand the customer, and use the insight to make the next action more valuable.</p>
          <div class="mg-cta-actions">
            <a class="mg-btn mg-btn-primary" href="/signup.php">Create Account</a>
            <a class="mg-btn mg-btn-secondary" href="/learn-more.php">Book A Demo</a>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<script>document.documentElement.classList.add('mg-js');</script>
<script>
(() => {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const progressBar = document.getElementById('mgProgressBar');

  const updateProgress = () => {
    if(!progressBar) return;
    const doc = document.documentElement;
    const max = Math.max(1, doc.scrollHeight - window.innerHeight);
    progressBar.style.transform = `scaleX(${Math.min(1, window.scrollY / max)})`;
  };

  const reveals = Array.from(document.querySelectorAll('[data-reveal]'));
  if('IntersectionObserver' in window){
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if(entry.isIntersecting){entry.target.classList.add('is-visible');io.unobserve(entry.target);}
      });
    }, {threshold:.16, rootMargin:'0px 0px -80px 0px'});
    reveals.forEach(el => io.observe(el));
  } else {
    reveals.forEach(el => el.classList.add('is-visible'));
  }

  if(!prefersReduced){
    let ticking = false;
    window.addEventListener('scroll', () => {
      if(!ticking){
        window.requestAnimationFrame(() => {updateProgress();ticking=false;});
        ticking = true;
      }
    }, {passive:true});
  }
  updateProgress();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
