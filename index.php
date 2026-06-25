<?php
declare(strict_types=1);

/*
 * Microgifter Homepage - consolidated content with shared full-width header.
 * This file uses the same public universal header/footer stack as the rest of the site.
 * The homepage content itself is consolidated into this file.
 */

$page_title = 'Microgifter | Invest Local, Discover Value & Support Humans';
$page_section = 'public';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_manifest = [
    'id' => 'home-consolidated',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
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
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>
<script>document.documentElement.classList.add('mg-js');</script>

<script>
(() => {
  const addHeaderPhone = () => {
    const headerLeft = document.querySelector('[data-public-header] .mg-header-left');
    const brand = headerLeft ? headerLeft.querySelector('.mg-brand') : null;
    if (!headerLeft || !brand || headerLeft.querySelector('.mg-header-phone')) return;

    const phone = document.createElement('a');
    phone.className = 'mg-header-phone';
    phone.href = 'tel:18002697433';
    phone.textContent = '1-800-269-7433';
    phone.setAttribute('aria-label', 'Call Microgifter at 1-800-269-7433');
    brand.insertAdjacentElement('afterend', phone);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addHeaderPhone, { once:true });
  } else {
    addHeaderPhone();
  }
})();
</script>

<style>

  /* Page-only phone number beside logo in universal header */
  body[data-page-id="home-consolidated"] .mg-header-phone{
    display:inline-flex;
    align-items:center;
    margin-left:18px;
    padding-left:18px;
    border-left:1px solid rgba(0,0,0,.14);
    color:#050505;
    text-decoration:none;
    font-size:13px;
    font-weight:850;
    letter-spacing:.02em;
    white-space:nowrap;
  }
  body[data-page-id="home-consolidated"] .mg-header-phone:hover{
    color:#8a650c;
  }

  :root{
    --mg-ink:#050505;
    --mg-text:#171717;
    --mg-muted:#5f5f5a;
    --mg-paper:#f4f4f1;
    --mg-paper-2:#fbfbf8;
    --mg-line:rgba(10,10,10,.10);
    --mg-black:#030303;
    --mg-dark:#050505;
    --mg-dark-soft:#0c0c0b;
    --mg-white:#f7f7f2;
    --mg-soft:#c9c4ba;
    --mg-gold:#d9a735;
    --mg-gold-2:#f1c15a;
    --mg-green:#20d475;
    --mg-red:#ff5f57;
    --mg-max:1180px;
    --mg-radius:24px;
  }

  *{box-sizing:border-box}
  html{scroll-behavior:smooth;min-height:100%;overflow-x:hidden}
  body{
    min-height:100%;
    margin:0;
    overflow-x:hidden;
    background:var(--mg-paper);
    color:var(--mg-text);
    font-family:"Inter","Helvetica Neue",Arial,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  }

  .mg-home-page{min-height:100vh;background:#020202}
  .mg-container{width:min(var(--mg-max),calc(100% - 72px));margin:0 auto}
  .mg-progress{position:fixed;z-index:120;left:0;top:0;width:100%;height:3px;background:rgba(0,0,0,.04)}
  .mg-progress-bar{display:block;height:100%;width:0;background:linear-gradient(90deg,#050505,var(--mg-gold));box-shadow:0 0 20px rgba(217,167,53,.35)}


  /* Real public market ticker is provided by the universal public header. */
  .mg-hero{
    position:relative;
    z-index:4;
    min-height:108svh;
    overflow:visible;
    padding:132px 0 72px;
    isolation:isolate;
    background:#f2f2ef;
  }
  .mg-hero::before{
    content:"";
    position:absolute;
    inset:0;
    z-index:0;
    background:url('/images/header_gradient_bg.png') center top/cover no-repeat;
  }
  .mg-hero::after{
    content:"";
    position:absolute;
    inset:0;
    z-index:1;
    pointer-events:none;
    background:
      linear-gradient(90deg,rgba(245,245,242,.98) 0%,rgba(245,245,242,.90) 32%,rgba(245,245,242,.28) 60%,rgba(245,245,242,.02) 100%),
      linear-gradient(180deg,rgba(255,255,255,.30) 0%,rgba(255,255,255,0) 52%,rgba(0,0,0,.88) 76%,#020202 100%);
  }
  .mg-hero-grid{
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
  .mg-hero-copy{max-width:760px;padding-top:32px}
  .mg-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:10px;
    margin:0 0 16px;
    color:#050505;
    font-size:11px;
    font-weight:900;
    letter-spacing:.24em;
    text-transform:uppercase;
  }
  .mg-eyebrow::before{
    content:"";
    width:9px;
    height:9px;
    border-radius:999px;
    background:#050505;
  }
  .mg-title{
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
  .mg-lede{
    margin:30px 0 0;
    max-width:590px;
    color:#1c1c1c;
    font-size:clamp(20px,1.9vw,27px);
    line-height:1.03;
    letter-spacing:-.046em;
    font-weight:560;
  }
  .mg-note{
    margin:20px 0 0;
    max-width:590px;
    color:#40403c;
    font-size:13px;
    line-height:1.55;
  }
  .mg-note strong{color:#050505}
  .mg-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
  .mg-btn{
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
  .mg-btn-primary,.mg-btn-primary:visited{
    border:1px solid rgba(0,0,0,.08);
    background:#fff;
    color:#050505;
    box-shadow:0 14px 34px rgba(0,0,0,.055);
  }
  .mg-hero .mg-btn-secondary,
  .mg-hero .mg-btn-secondary:visited,
  .mg-hero .mg-btn-secondary span{
    color:#fff!important;
  }
  .mg-btn-secondary,.mg-btn-secondary:visited{
    border:1px solid #050505;
    background:#050505;
    color:#fff;
    box-shadow:0 16px 38px rgba(0,0,0,.18);
  }
  .mg-hero-visual{
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
    margin-top:200px;
  }
  .mg-desktop{
    position:relative;
    z-index:2;
    width:min(850px,78vw);
    margin:0 0 -36px 132px;
    border:12px solid #111;
    border-bottom-width:22px;
    border-radius:18px 18px 8px 8px;
    background:#fff;
    box-shadow:0 34px 80px rgba(0,0,0,.36);
    opacity:.78;
    filter:blur(.22px);
    display:block;
  }
  .mg-phone-img{
    position:absolute;
    left:56px;
    bottom:-142px;
    z-index:8;
    width:min(330px,31vw);
    height:auto;
    display:block;
    filter:drop-shadow(0 34px 52px rgba(0,0,0,.50));
    transform:rotate(-2deg);
  }

  .mg-section{
    position:relative;
    overflow:hidden;
    padding:118px 0;
    background:#050505;
    color:var(--mg-white);
  }
  .mg-section + .mg-section{border-top:1px solid rgba(217,167,53,.16)}
  .mg-bg-mesh{
    position:absolute;
    inset:0;
    pointer-events:none;
    opacity:.7;
    background:
      radial-gradient(circle at 15% 12%,rgba(217,167,53,.14),transparent 34%),
      radial-gradient(circle at 84% 48%,rgba(255,255,255,.055),transparent 30%),
      linear-gradient(rgba(217,167,53,.055) 1px,transparent 1px),
      linear-gradient(90deg,rgba(217,167,53,.05) 1px,transparent 1px);
    background-size:auto,auto,72px 72px,72px 72px;
    mask-image:linear-gradient(180deg,transparent 0%,#000 18%,#000 82%,transparent 100%);
  }
  .mg-section .mg-container{position:relative;z-index:2}
  .mg-section-head{max-width:1040px;margin:0 0 38px}
  .mg-section-title{
    margin:0;
    color:#fff;
    font-size:clamp(36px,4.4vw,68px);
    line-height:.98;
    letter-spacing:-.06em;
    font-weight:850;
  }
  .mg-section-copy{
    margin:20px 0 0;
    max-width:740px;
    color:#c9c4ba;
    font-size:clamp(15px,1.05vw,18px);
    line-height:1.58;
  }
  .mg-story-kicker{
    display:inline-flex;
    align-items:center;
    gap:10px;
    color:#d9a735;
    font-size:10px;
    font-weight:850;
    letter-spacing:.3em;
    text-transform:uppercase;
    margin-bottom:18px;
  }
  .mg-story-kicker::before{
    content:"";
    width:10px;
    height:10px;
    border-radius:999px;
    background:#d9a735;
    box-shadow:0 0 20px rgba(217,167,53,.55);
  }


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

  .mg-feature-panels{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:24px;
    margin-top:54px;
  }
  .mg-panel{
    border:1px solid rgba(217,167,53,.32);
    border-radius:20px;
    background:linear-gradient(145deg,rgba(10,10,10,.96),rgba(3,3,3,.82));
    padding:28px;
    min-height:430px;
    box-shadow:0 24px 80px rgba(0,0,0,.42);
    overflow:hidden;
  }
  .mg-panel-head{
    display:grid;
    grid-template-columns:56px 1fr;
    gap:18px;
    align-items:start;
    margin-bottom:24px;
  }
  .mg-panel-icon{
    width:56px;
    height:56px;
    border:1px solid rgba(217,167,53,.5);
    border-radius:12px;
    display:grid;
    place-items:center;
    color:var(--mg-gold);
  }
  .mg-panel-icon svg{
    width:28px;
    height:28px;
    stroke:currentColor;
    fill:none;
    stroke-width:2;
    stroke-linecap:round;
    stroke-linejoin:round;
  }
  .mg-panel h3{margin:0;color:#fff;font-size:28px;letter-spacing:-.04em;line-height:1}
  .mg-panel p{margin:10px 0 0;color:var(--mg-soft);font-size:15px;line-height:1.48}
  .mg-codebox,.mg-code-panel{
    padding:22px;
    border-radius:14px;
    background:#050505;
    border:1px solid rgba(217,167,53,.22);
    margin-top:24px;
    font-family:"SFMono-Regular",Consolas,monospace;
    font-size:13px;
    line-height:1.72;
    color:#d7d2c7;
    overflow:auto;
    white-space:pre-wrap;
  }
  .gold{color:var(--mg-gold)}
  .green{color:#7fd18a}
  .muted{color:#858076}
  .mg-mini-ui{
    margin-top:22px;
    min-height:300px;
    border:1px solid rgba(217,167,53,.22);
    border-radius:14px;
    overflow:hidden;
    background:
      radial-gradient(circle at 30% 20%,rgba(217,167,53,.20),transparent 28%),
      linear-gradient(145deg,#050505,#10100f);
    display:grid;
    place-items:center;
    padding:18px;
  }
  .mg-mini-ui img{display:block;width:100%;height:300px;object-fit:cover;object-position:top center}
  .mg-mini-ui-fallback{
    width:100%;
    min-height:240px;
    display:grid;
    gap:12px;
    align-content:center;
  }
  .mg-ui-line{height:12px;border-radius:999px;background:rgba(255,255,255,.10)}
  .mg-ui-line:nth-child(1){width:64%}
  .mg-ui-line:nth-child(2){width:86%}
  .mg-ui-line:nth-child(3){width:52%}
  .mg-ui-card-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}
  .mg-ui-card{min-height:72px;border:1px solid rgba(217,167,53,.22);border-radius:12px;background:rgba(255,255,255,.035)}
  .mg-panel-tags{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
    margin-top:22px;
    padding-top:20px;
    border-top:1px solid rgba(217,167,53,.24);
  }
  .mg-panel-tags span{text-align:center;color:#ece7dc;font-size:13px}

  .mg-story-grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:54px;align-items:start}
  .mg-story-copy{position:sticky;top:92px}
  .mg-story-copy h2{margin:0;color:#fff;font-size:clamp(34px,4vw,62px);line-height:.98;letter-spacing:-.06em}
  .mg-story-copy p{margin:20px 0 0;color:#c4bfb6;font-size:16px;line-height:1.62;max-width:560px}
  .mg-agentic-panel{display:grid;grid-template-columns:1.05fr .95fr;gap:24px;align-items:stretch;margin-top:8px}
  .mg-agentic-card{
    padding:28px;
    border:1px solid rgba(217,167,53,.25);
    border-radius:22px;
    background:linear-gradient(145deg,rgba(12,12,11,.9),rgba(0,0,0,.68));
  }
  .mg-agentic-card h3{margin:0;color:#fff;font-size:26px;letter-spacing:-.05em}
  .mg-agentic-card p{margin:16px 0 0;color:#c3beb4;font-size:15px;line-height:1.62}
  .mg-agent-flow{display:grid;gap:12px}
  .mg-flow-item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    padding:16px 18px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:15px;
    background:rgba(255,255,255,.025);
    font-size:13px;
    color:#f4f1e8;
    font-weight:750;
  }
  .mg-flow-item span:last-child{color:#d9a735}
  .mg-social-proof{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:34px}
  .mg-proof-pill{
    min-height:78px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    padding:14px;
    border:1px solid rgba(217,167,53,.18);
    border-radius:16px;
    background:rgba(255,255,255,.025);
    color:#e9e4d8;
    font-size:12px;
    font-weight:850;
    letter-spacing:.08em;
    text-transform:uppercase;
  }

  .mg-story-list{display:grid;gap:14px}
  .mg-story-step{
    display:grid;
    grid-template-columns:52px 1fr;
    gap:18px;
    padding:24px;
    border:1px solid rgba(217,167,53,.24);
    border-radius:18px;
    background:linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.012));
    box-shadow:0 0 0 1px rgba(255,255,255,.025) inset;
  }
  .mg-story-step b{
    width:42px;
    height:42px;
    display:grid;
    place-items:center;
    border:1px solid rgba(217,167,53,.45);
    border-radius:13px;
    color:#d9a735;
    font-size:12px;
  }
  .mg-story-step h3{margin:0;color:#fff;font-size:20px;letter-spacing:-.04em}
  .mg-story-step p{margin:9px 0 0;color:#bbb5aa;font-size:14px;line-height:1.55}

  .mg-examples-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:40px}
  .mg-example-card{
    position:relative;
    overflow:hidden;
    min-height:230px;
    padding:22px;
    border:1px solid rgba(217,167,53,.23);
    border-radius:18px;
    background:linear-gradient(160deg,rgba(255,255,255,.04),rgba(255,255,255,.012));
  }
  .mg-example-card::after{
    content:"";
    position:absolute;
    right:-36px;
    bottom:-36px;
    width:120px;
    height:120px;
    border-radius:999px;
    border:1px solid rgba(217,167,53,.15);
  }
  .mg-example-card small{display:block;color:#d9a735;font-size:10px;font-weight:850;letter-spacing:.22em;text-transform:uppercase}
  .mg-example-card h3{margin:18px 0 0;color:#fff;font-size:20px;letter-spacing:-.04em}
  .mg-example-card p{margin:10px 0 18px;color:#bdb7ad;font-size:13px;line-height:1.45}
  .mg-example-meta{display:grid;gap:8px;margin-top:auto}
  .mg-example-meta div{display:flex;justify-content:space-between;gap:14px;padding-top:8px;border-top:1px solid rgba(255,255,255,.07);font-size:12px;color:#aaa49a}
  .mg-example-meta strong{color:#fff}

  .mg-api-story{display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;align-items:stretch;margin-top:38px}
  .mg-api-flow{padding:28px;border:1px solid rgba(217,167,53,.25);border-radius:22px;background:rgba(255,255,255,.025)}
  .mg-api-flow h3{margin:0 0 18px;color:#fff;font-size:24px;letter-spacing:-.045em}
  .mg-api-flow-row{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;margin-top:12px}
  .mg-api-flow-dot{width:34px;height:34px;display:grid;place-items:center;border-radius:12px;background:rgba(217,167,53,.12);border:1px solid rgba(217,167,53,.32);color:#d9a735;font-size:11px;font-weight:900}
  .mg-api-flow-row p{margin:0;color:#c3beb4;font-size:14px;line-height:1.45}

  .mg-public-bottom-demo{
    position:relative;
    isolation:isolate;
    overflow:hidden;
    padding:104px 20px;
    background:#050505;
    color:#fff;
    border-top:1px solid rgba(217,167,53,.24);
  }
  .mg-public-bottom-demo::before{
    content:"";
    position:absolute;
    inset:0;
    pointer-events:none;
    background:radial-gradient(circle at 18% 18%,rgba(217,167,53,.16),transparent 34%),radial-gradient(circle at 82% 60%,rgba(217,167,53,.11),transparent 36%);
  }
  .mg-public-bottom-demo-inner{position:relative;z-index:1;width:min(920px,100%);margin:0 auto;text-align:center}
  .mg-public-bottom-demo-eyebrow{display:inline-flex;align-items:center;gap:10px;margin-bottom:18px;color:#d9a735;font-size:10px;font-weight:850;letter-spacing:.28em;text-transform:uppercase}
  .mg-public-bottom-demo h2{margin:0;color:#fff;font-size:clamp(36px,5vw,68px);line-height:.98;letter-spacing:-.06em;font-weight:850}
  .mg-public-bottom-demo p{margin:22px auto 0;max-width:660px;color:#c9c4ba;font-size:17px;line-height:1.6}
  .mg-public-bottom-demo-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:14px;margin-top:32px}
  .mg-public-bottom-demo-actions a{min-height:52px;display:inline-flex;align-items:center;justify-content:center;padding:0 22px;border-radius:12px;text-decoration:none;font-size:13px;font-weight:850}
  .mg-public-bottom-demo-primary{background:#fff;color:#050505}
  .mg-public-bottom-demo-secondary{border:1px solid rgba(217,167,53,.46);color:#fff;background:rgba(255,255,255,.03)}

  .mg-footer{
    position:relative;
    padding:98px 0 66px;
    background:#020202;
    color:var(--mg-white);
    overflow:hidden;
  }
  .mg-footer-grid{display:grid;grid-template-columns:1.45fr repeat(3,1fr);gap:74px;align-items:start}
  .mg-footer-brand .mg-logo{margin-bottom:34px;color:#fff}
  .mg-footer-brand .mg-logo-mark{display:block;color:#fff}
  .mg-footer-brand .mg-logo-text{display:block;color:#fff}
  .mg-footer-tag{max-width:360px;color:var(--mg-soft);font-size:17px;line-height:1.45;margin:0}
  .mg-socials{display:flex;gap:18px;margin-top:38px}
  .mg-socials a{width:54px;height:54px;display:grid;place-items:center;border:1px solid rgba(217,167,53,.4);border-radius:10px;color:#fff;text-decoration:none;transition:.2s ease;background:rgba(255,255,255,.02)}
  .mg-socials a:hover{transform:translateY(-3px);border-color:rgba(241,193,90,.8);background:rgba(217,167,53,.08)}
  .mg-socials svg{width:24px;height:24px;fill:currentColor;stroke:currentColor}
  .mg-contact{display:inline-flex;align-items:center;gap:14px;margin-top:36px;color:#e7e1d5;text-decoration:none;font-size:17px}
  .mg-contact svg{width:24px;height:24px;stroke:var(--mg-gold);fill:none;stroke-width:2}
  .mg-footer-col h3{margin:8px 0 34px;color:var(--mg-gold);font-size:13px;letter-spacing:.42em;text-transform:uppercase;font-weight:800}
  .mg-footer-col h3::after{content:"";display:block;width:40px;height:2px;margin-top:20px;background:var(--mg-gold)}
  .mg-footer-col nav{display:grid;gap:0}
  .mg-footer-col a{display:block;padding:18px 0;border-bottom:1px solid rgba(255,255,255,.07);color:#f2eee6;text-decoration:none;font-size:18px;transition:color .2s ease,transform .2s ease}
  .mg-footer-col a:hover{color:var(--mg-gold);transform:translateX(4px)}
  .mg-footer-bottom{display:flex;justify-content:space-between;gap:30px;align-items:center;margin-top:92px;padding-top:46px;border-top:1px solid rgba(217,167,53,.45);color:#bbb4a7;font-size:15px}
  .mg-footer-links{display:flex;gap:24px;align-items:center;flex-wrap:wrap}
  .mg-footer-links a{color:#f5efe5;text-decoration:none}
  .mg-dot{color:var(--mg-gold)}

  [data-reveal]{opacity:1;transform:none;filter:none}
  .mg-js [data-reveal]{opacity:0;transform:translateY(42px);filter:blur(8px);transition:opacity .85s cubic-bezier(.16,1,.3,1),transform .85s cubic-bezier(.16,1,.3,1),filter .85s cubic-bezier(.16,1,.3,1);transition-delay:var(--delay,0ms)}
  .mg-js [data-reveal].is-visible{opacity:1;transform:none;filter:blur(0)}
  .mg-js [data-reveal="left"]{transform:translateX(-42px)}
  .mg-js [data-reveal="right"]{transform:translateX(42px)}
  .mg-js [data-reveal="scale"]{transform:scale(.94)}
  .mg-js [data-reveal="left"].is-visible,
  .mg-js [data-reveal="right"].is-visible,
  .mg-js [data-reveal="scale"].is-visible{transform:none}
  .mg-image-missing{display:none!important}

  @media(max-width:1180px){
    .mg-feature-panels{grid-template-columns:1fr}
    .mg-story-grid,.mg-agentic-panel,.mg-api-story{grid-template-columns:1fr}
    .mg-story-copy{position:relative;top:auto}
    .mg-examples-grid{grid-template-columns:repeat(2,1fr)}
    .mg-social-proof{grid-template-columns:repeat(3,1fr)}
    .mg-footer-grid{grid-template-columns:1fr 1fr}
    .mg-footer-brand{grid-column:1/-1}
  }
  @media(max-width:1040px){
    .mg-hero-grid{grid-template-columns:1fr}
    .mg-hero-copy{max-width:610px}
    .mg-desktop{margin-left:70px;width:min(760px,82vw)}
    .mg-phone-img{left:28px;bottom:-132px;width:min(285px,36vw)}
  }
  @media(max-width:760px){
    .mg-hero{min-height:1280px;padding:82px 0 64px;overflow:hidden}
    .mg-hero::before{background-position:center top;background-size:cover}
    .mg-hero::after{
      background:linear-gradient(180deg,rgba(245,245,242,.02) 0%,rgba(245,245,242,.04) 38%,rgba(245,245,242,.90) 58%,rgba(245,245,242,.98) 76%,rgba(0,0,0,.78) 94%,#020202 100%);
    }
    .mg-hero-grid{
      width:calc(100% - 22px);
      min-height:calc(100svh - 86px);
      display:flex;
      flex-direction:column;
      gap:0;
    }
    .mg-hero-visual{
      order:1;
      width:100%;
      min-height:335px;
      align-items:flex-end;
      justify-content:center;
      margin-top:0;
      overflow:visible;
      isolation:isolate;
    }
    .mg-desktop{
      position:relative;
      z-index:5;
      width:min(92vw,390px);
      margin:0 auto 16px;
      border-width:8px;
      border-bottom-width:15px;
      border-radius:13px 13px 7px 7px;
      opacity:1;
      transform:translateX(0);
    }
    .mg-phone-img{
      left:8px;
      right:auto;
      top:auto;
      bottom:-22px;
      z-index:8;
      width:min(120px,32vw);
      max-width:none;
      opacity:1;
      filter:drop-shadow(0 34px 46px rgba(0,0,0,.48));
      transform:rotate(-3deg);
      transform-origin:center center;
    }
    .mg-hero-copy{
      position:relative;
      z-index:6;
      order:2;
      width:100%;
      max-width:100%;
      padding-top:104px;
      padding-bottom:58px;
    }
    .mg-title{max-width:100%;font-size:clamp(34px,10vw,47px);line-height:1;letter-spacing:-.058em}
    .mg-lede{max-width:100%;margin-top:22px;font-size:18px;line-height:1.12;letter-spacing:-.035em}
    .mg-actions{gap:10px;margin-top:24px}
    .mg-btn{min-height:48px;padding:0 18px;font-size:13px}
    .mg-container{width:min(100% - 32px,680px)}
    .mg-section{padding:84px 0}

    .mg-feature-panels.mg-growth-feature-grid{grid-template-columns:1fr}
    .mg-feature-panels.mg-growth-feature-grid .mg-panel{min-height:auto}
    .mg-mini-path{grid-template-columns:repeat(2,1fr)}
    .mg-story-step{grid-template-columns:1fr;gap:14px}
    .mg-examples-grid,.mg-social-proof{grid-template-columns:1fr}
    .mg-agentic-card,.mg-api-flow{padding:22px}
    .mg-footer{padding:90px 0 48px}
    .mg-footer-grid{grid-template-columns:1fr;gap:44px}
    .mg-footer-bottom{display:grid;margin-top:56px}
    .mg-footer-col a{font-size:17px}
  }
  @media(max-width:440px){
    .mg-desktop{width:min(92vw,370px);margin:0 auto 12px;opacity:1}
    .mg-phone-img{left:8px;bottom:-18px;width:min(112px,31vw);opacity:1}
    .mg-hero-copy{padding-top:96px}
  }
  @media(prefers-reduced-motion:reduce){
    *,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}
    [data-reveal]{opacity:1!important;transform:none!important;filter:none!important}
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

<div class="mg-home-page" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>


  <section class="mg-hero" aria-labelledby="mgHeroTitle">
    <div class="mg-hero-grid">
      <div class="mg-hero-copy" data-reveal="left">

        <span class="mg-hero-pretitle">Data Resource Management Solutions (DRM)</span>
        <h1 class="mg-title" id="mgHeroTitle"><span class="mg-title-line">INVEST LOCAL,</span><span class="mg-title-line">DISCOVER VALUE &amp;</span><span class="mg-title-line">SUPPORT HUMANS</span></h1>
        <p class="mg-note"><strong>Business data is the value layer:</strong> Microgifter helps local businesses turn support into structured data, use that data to discover what customers value, and earn more from the relationships they already create.</p>
        <div class="mg-actions">
          <a class="mg-btn mg-btn-primary" href="/signup.php">Create Account <span>→</span></a>
          <a class="mg-btn mg-btn-secondary" href="/developer-docs.php">View API Docs <span>→</span></a>
        </div>
      </div>

      <div class="mg-hero-visual" data-reveal="right" aria-label="Microgifter product preview">
        <img class="mg-desktop" src="/images/desktop_bg_main_v10.png" alt="Microgifter desktop product preview">
        <img class="mg-phone-img" src="/images/mobile_bg_main.png" alt="Microgifter mobile product preview">
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchants" aria-labelledby="merchantTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">For growth</span>
        <h2 class="mg-section-title" id="merchantTitle" data-reveal="left" style="--delay:80ms">Your business already creates value. Microgifter helps you capture it, understand it, and turn it into repeatable growth.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:160ms">Every offer, reward, gift certificate, campaign, claim, redemption, and customer interaction creates a signal. Microgifter organizes those signals into a practical data layer that helps local businesses make smarter offers, support better customers, and earn more from future demand.</p>
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

  <section class="mg-section" id="future-demand" aria-labelledby="agenticTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">Promotional CRM</span>
          <h2 id="agenticTitle">The value is not only the transaction. It is the business intelligence created around it.</h2>
          <p>Local businesses generate valuable signals every day, but most of that value disappears across social posts, paper coupons, disconnected payment systems, and untracked customer behavior. Microgifter captures those signals and turns them into a usable operating layer for growth.</p>
        </div>
        <div>
          <div class="mg-agentic-panel">
            <article class="mg-agentic-card" data-reveal="up">
              <h3>What business data value means</h3>
              <p>Every offer should teach the business something: what customers value, which channel moved them, what they claimed, whether they redeemed, and what made them return.</p>
              <p>Microgifter makes that trail operational, so businesses can stop guessing and start using their own data to build stronger customer relationships and more efficient revenue loops.</p>
            </article>
            <article class="mg-agentic-card" data-reveal="up" style="--delay:120ms">
              <h3>The efficient value loop</h3>
              <div class="mg-agent-flow" aria-label="Promotional CRM revenue flow">
                <div class="mg-flow-item"><span>Create a local value action</span><span>→</span></div>
                <div class="mg-flow-item"><span>Capture the customer signal</span><span>→</span></div>
                <div class="mg-flow-item"><span>Connect actions to business intelligence</span><span>→</span></div>
                <div class="mg-flow-item"><span>Use insight to improve the next offer</span><span>→</span></div>
                <div class="mg-flow-item"><span>Earn more from owned relationships</span><span>✓</span></div>
              </div>
            </article>
          </div>
          <div class="mg-social-proof" data-reveal="up" style="--delay:220ms">
            <div class="mg-proof-pill">Customer data</div>
            <div class="mg-proof-pill">Offer signals</div>
            <div class="mg-proof-pill">Redemption history</div>
            <div class="mg-proof-pill">Demand patterns</div>
            <div class="mg-proof-pill">Repeat revenue</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section" id="how-it-works" aria-labelledby="howTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">Revenue loop</span>
          <h2 id="howTitle">A simple loop for creating, capturing, and utilizing local value.</h2>
          <p>The goal is to make local commerce smarter without making it complicated: create a valuable customer action, capture the signal it creates, connect it to a customer record, and use the result to make the next action more profitable.</p>
        </div>
        <div class="mg-story-list">
          <article class="mg-story-step" data-reveal="up"><b>01</b><div><h3>Create value</h3><p>Build an offer, reward, gift certificate, contest entry, landing page, or pre-sale product with a clear customer benefit and a measurable business goal.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:100ms"><b>02</b><div><h3>Capture the data</h3><p>Distribute through QR codes, feeds, newsletters, social posts, table tents, landing pages, partner apps, or the API while capturing where each action came from.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:200ms"><b>03</b><div><h3>Discover what matters</h3><p>Connect claims, redemptions, purchases, visits, profiles, and campaign sources to discover what customers actually value and what drives them back.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:300ms"><b>04</b><div><h3>Utilize the insight</h3><p>Use the data to improve targeting, personalize follow-up, forecast demand, increase repeat visits, and earn more from the relationships already created.</p></div></article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchant-examples" aria-labelledby="examplesTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Merchant examples</span>
        <h2 class="mg-section-title" id="examplesTitle" data-reveal="left">Every local action can become a data asset.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">A coffee reward, lunch special, event pass, or trial class is more than a sale. It shows what customers value, when they act, how they support, and what brings them back.</p>
      </div>
      <div class="mg-examples-grid">
        <article class="mg-example-card" data-reveal="up"><small>Coffee shop</small><h3>Coffee for Two</h3><p>Captures gift behavior, visit timing, redemption activity, and the first signal of a repeat customer.</p><div class="mg-example-meta"><div><span>Price</span><strong>$18</strong></div><div><span>Signal</span><strong>Visit intent</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:90ms"><small>Restaurant</small><h3>Lunch Special</h3><p>Shows which offers move demand into slower windows and which customers respond to value-based timing.</p><div class="mg-example-meta"><div><span>Price</span><strong>$24</strong></div><div><span>Signal</span><strong>Demand shift</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:180ms"><small>Venue</small><h3>Two Drink Reward</h3><p>Connects pre-event intent, in-person redemption, and post-event follow-up into one measurable loop.</p><div class="mg-example-meta"><div><span>Price</span><strong>$22</strong></div><div><span>Signal</span><strong>Event lift</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:270ms"><small>Fitness</small><h3>Trial Class Pass</h3><p>Turns a first visit into a profile, a conversion path, and a clear follow-up opportunity.</p><div class="mg-example-meta"><div><span>Price</span><strong>$15</strong></div><div><span>Signal</span><strong>Lead value</strong></div></div></article>
      </div>
    </div>
  </section>

  <section class="mg-section" id="api-preview" aria-labelledby="apiPreviewTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Distribution API</span>
        <h2 class="mg-section-title" id="apiPreviewTitle" data-reveal="left">A data-value layer developers can wire into local commerce.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Use Microgifter to create reward actions, capture customer signals, verify redemption, and send structured value data back into CRMs, apps, loyalty workflows, dashboards, and AI-powered commerce systems.</p>
      </div>
      <div class="mg-api-story">
        <pre class="mg-code-panel" data-reveal="up"><span class="gold">POST</span> /v1/distribution/send
{
  "merchant_id": <span class="green">"m_local_123"</span>,
  "reward": <span class="green">"coffee_for_two"</span>,
  "recipient": {
    "type": <span class="green">"email"</span>,
    "value": <span class="green">"customer@example.com"</span>
  },
  "metadata": {
    "source": <span class="green">"promotional-crm"</span>
  }
}</pre>
        <article class="mg-api-flow" data-reveal="up" style="--delay:120ms">
          <h3>From support to intelligence</h3>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">01</span><p>A merchant or partner app creates a local value action: offer, reward, gift, contest, or pre-sale product.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">02</span><p>Microgifter captures source, claim, customer, campaign, and redemption context as structured data.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">03</span><p>The customer clicks, claims, shares, saves, buys, visits, or redeems.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">04</span><p>The business uses that data to improve campaigns, increase customer value, and earn more from future demand.</p></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-public-bottom-demo" aria-labelledby="mgPublicDemoTitle">
    <div class="mg-public-bottom-demo-inner">
      <span class="mg-public-bottom-demo-eyebrow">Book a demo</span>
      <h2 id="mgPublicDemoTitle">See how Microgifter turns local support into usable business data.</h2>
      <p>Walk through the full value loop: create the offer, capture the signal, verify the redemption, understand the customer, and use the insight to make the next action more valuable.</p>
      <div class="mg-public-bottom-demo-actions">
        <a class="mg-public-bottom-demo-primary" href="/learn-more.php">Book a Demo</a>
        <a class="mg-public-bottom-demo-secondary" href="/pricing.php">View Pricing</a>
        <a class="mg-public-bottom-demo-secondary" href="/developer-docs.php">Explore the API</a>
      </div>
    </div>
  </section>
</div>

<script>
(() => {
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
  const progressBar = document.getElementById('mgProgressBar');


  const showAllRevealItems = () => {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  };

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px 12% 0px' });

    revealItems.forEach((item) => revealObserver.observe(item));
    setTimeout(showAllRevealItems, 1800);
  } else {
    showAllRevealItems();
  }

  document.querySelectorAll('img').forEach((img) => {
    img.addEventListener('error', () => {
      img.classList.add('mg-image-missing');
      const fallback = img.parentElement && img.parentElement.querySelector('.mg-mini-ui-fallback');
      if (fallback) fallback.style.display = 'grid';
    }, { once: true });

    img.addEventListener('load', () => {
      const fallback = img.parentElement && img.parentElement.querySelector('.mg-mini-ui-fallback');
      if (fallback) fallback.style.display = 'none';
    }, { once: true });
  });

  const updateProgress = () => {
    if (!progressBar) return;
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? Math.min(1, scrollTop / docHeight) : 0;
    progressBar.style.width = `${pct * 100}%`;
  };

  window.addEventListener('scroll', updateProgress, { passive: true });
  window.addEventListener('resize', updateProgress);
  updateProgress();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
