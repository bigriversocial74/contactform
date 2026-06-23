<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Microgifter | Rewards Layer for Local Commerce';
$page_section = 'public';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_manifest = [
    'id' => 'home',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{
  --mg-black:#030303;
  --mg-black-2:#070706;
  --mg-panel:#0a0a09;
  --mg-panel-soft:#0d0d0c;
  --mg-white:#f7f7f2;
  --mg-muted:#a7a39a;
  --mg-soft:#d9d4c8;
  --mg-line:rgba(217,167,53,.28);
  --mg-line-soft:rgba(255,255,255,.08);
  --mg-gold:#d9a735;
  --mg-gold-2:#f1c15a;
  --mg-radius:28px;
  --mg-max:1380px;
}
html{scroll-behavior:smooth;min-height:100%;overflow-y:auto;overflow-x:hidden;}
body{margin:0;min-height:100%;overflow-y:auto!important;overflow-x:hidden;background:var(--mg-black);}
.mg-page,.mg-page *{box-sizing:border-box;}
.mg-page{min-height:100vh;overflow-x:hidden;overflow-y:visible;background:var(--mg-black);color:var(--mg-white);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;letter-spacing:-.01em;}
.mg-page a{color:inherit;}
.mg-bg-mesh{position:absolute;inset:0;pointer-events:none;overflow:hidden;opacity:.58;background:linear-gradient(90deg,rgba(0,0,0,.72),rgba(0,0,0,.14) 47%,rgba(0,0,0,.72)),url('/images/cosmic_golden_network_on_black.png') center/cover no-repeat;mix-blend-mode:screen;}
.mg-bg-mesh::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 70% 35%,rgba(217,167,53,.12),transparent 35%),linear-gradient(180deg,rgba(0,0,0,.2),#030303 95%);}
.mg-container{width:min(var(--mg-max),calc(100% - 80px));margin:0 auto;position:relative;z-index:2;}
.mg-site-header{position:fixed;top:0;left:0;right:0;z-index:50;background:linear-gradient(180deg,rgba(3,3,3,.94),rgba(3,3,3,.74));border-bottom:1px solid rgba(255,255,255,.07);backdrop-filter:blur(18px);transform:translateY(-14px);opacity:0;animation:mgHeaderIn .75s cubic-bezier(.16,1,.3,1) .08s forwards;}
@keyframes mgHeaderIn{to{transform:none;opacity:1;}}
.mg-nav{width:min(var(--mg-max),calc(100% - 80px));height:58px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:24px;}
.mg-logo{display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:var(--mg-white);}
.mg-logo-mark{width:26px;height:26px;display:block;}
.mg-logo-text{font-size:13px;font-weight:780;letter-spacing:.2em;text-transform:uppercase;}
.mg-nav-links{display:flex;align-items:center;justify-content:flex-end;gap:30px;margin-left:auto;}
.mg-nav-links a{color:#e9e7df;text-decoration:none;font-size:12px;font-weight:650;opacity:.82;transition:opacity .2s ease,color .2s ease;}
.mg-nav-links a:hover{opacity:1;color:#fff;}
.mg-header-cta{display:inline-flex;align-items:center;justify-content:center;gap:11px;min-height:36px;padding:0 16px;border:1px solid rgba(241,193,90,.46);border-radius:10px;color:#fff;text-decoration:none;font-size:12px;font-weight:780;background:rgba(255,255,255,.025);box-shadow:0 0 0 1px rgba(255,255,255,.03) inset;transition:transform .2s ease,border-color .2s ease,background .2s ease;}
.mg-header-cta:hover{transform:translateY(-2px);border-color:rgba(241,193,90,.82);background:rgba(217,167,53,.08);}
.mg-section{position:relative;min-height:100svh;padding:150px 0;background:var(--mg-black);border-bottom:1px solid rgba(255,255,255,.055);}
.mg-eyebrow{display:inline-flex;align-items:center;gap:13px;color:#f5efe1;font-size:12px;font-weight:800;letter-spacing:.34em;text-transform:uppercase;margin-bottom:24px;}
.mg-eyebrow::before{content:"";width:13px;height:13px;border:2px solid var(--mg-gold);border-radius:999px;box-shadow:0 0 22px rgba(217,167,53,.35);}
.mg-title,.mg-section-title{margin:0;max-width:980px;color:var(--mg-white);font-size:clamp(48px,6.2vw,104px);line-height:.94;letter-spacing:-.07em;font-weight:820;}
.mg-section-title{font-size:clamp(42px,5.2vw,82px);max-width:1180px;}
.mg-lede,.mg-section-copy{margin:26px 0 0;max-width:720px;color:#c5c0b6;font-size:clamp(17px,1.3vw,20px);line-height:1.58;font-weight:450;}
.mg-actions{display:flex;flex-wrap:wrap;gap:18px;margin-top:36px;}
.mg-btn{display:inline-flex;align-items:center;justify-content:center;gap:18px;min-height:58px;padding:0 28px;border-radius:13px;border:1px solid transparent;text-decoration:none;font-weight:780;font-size:15px;transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease,background .22s ease;position:relative;isolation:isolate;}
.mg-btn:hover{transform:translateY(-3px);}
.mg-btn-primary{background:linear-gradient(180deg,#fff,#e8e5dc);color:#050505!important;box-shadow:0 22px 60px rgba(255,255,255,.1);}
.mg-btn-primary span,.mg-btn-primary .mg-arrow{color:#050505!important;}
.mg-btn-secondary{background:rgba(255,255,255,.018);color:#fff!important;border-color:rgba(241,193,90,.42);}
.mg-btn-secondary span,.mg-btn-secondary .mg-arrow{color:#fff!important;}
.mg-btn-primary:hover{box-shadow:0 28px 80px rgba(255,255,255,.17);}
.mg-btn-secondary:hover{border-color:rgba(241,193,90,.78);background:rgba(217,167,53,.065);}
.mg-arrow{font-size:20px;line-height:1;}
.mg-hero{min-height:100svh;display:flex;align-items:center;padding:106px 0 80px;}
.mg-hero-grid{display:grid;grid-template-columns:minmax(0,.92fr) minmax(430px,1.08fr);gap:58px;align-items:center;}
.mg-hero-copy{position:relative;z-index:3;max-width:750px;}
.mg-hero-visual{position:relative;min-height:720px;display:grid;place-items:center;perspective:1400px;}
.mg-visual-stage{position:relative;width:min(680px,100%);height:720px;transform-style:preserve-3d;}
.mg-phone-orbit{position:absolute;inset:38px 170px 58px 138px;transform:rotateY(-16deg) rotateZ(3deg);transform-style:preserve-3d;filter:drop-shadow(0 38px 70px rgba(0,0,0,.7));animation:mgPhoneFloat 7s ease-in-out infinite;z-index:5;}
@keyframes mgPhoneFloat{0%,100%{transform:rotateY(-16deg) rotateZ(3deg) translateY(0);}50%{transform:rotateY(-10deg) rotateZ(2deg) translateY(-16px);}}
.mg-phone{position:relative;width:100%;height:100%;border-radius:42px;padding:12px;background:linear-gradient(92deg,#161616,#050505 35%,#24211a 64%,#080808);border:1px solid rgba(255,255,255,.15);box-shadow:0 0 0 2px rgba(255,255,255,.05) inset,0 0 0 4px rgba(0,0,0,.9) inset,20px 14px 42px rgba(0,0,0,.5);}
.mg-phone::before{content:"";position:absolute;right:-13px;top:112px;width:9px;height:88px;border-radius:0 8px 8px 0;background:linear-gradient(180deg,#2b2a26,#050505);border:1px solid rgba(255,255,255,.14);}
.mg-phone::after{content:"";position:absolute;inset:0;border-radius:42px;pointer-events:none;background:linear-gradient(120deg,rgba(255,255,255,.14),transparent 24%,transparent 74%,rgba(217,167,53,.12));mix-blend-mode:screen;opacity:.55;}
.mg-phone-screen{position:relative;width:100%;height:100%;overflow:hidden;border-radius:34px;background:#000;}
.mg-phone-notch{position:absolute;z-index:6;top:18px;left:50%;width:112px;height:34px;transform:translateX(-50%);border-radius:999px;background:#030303;box-shadow:0 0 0 1px rgba(255,255,255,.05),0 8px 18px rgba(0,0,0,.5);}
.mg-phone-shine{position:absolute;z-index:5;inset:-40px;background:linear-gradient(112deg,rgba(255,255,255,.18),transparent 28%,transparent 62%,rgba(255,255,255,.04));transform:translateX(-36%);pointer-events:none;mix-blend-mode:screen;animation:mgShine 10s ease-in-out infinite;}
@keyframes mgShine{0%,70%,100%{transform:translateX(-42%);opacity:.22;}35%{transform:translateX(40%);opacity:.38;}}
.mg-carousel-screen{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.018);transition:opacity 1.2s ease,transform 1.2s ease,filter 1.2s ease;filter:blur(8px);}
.mg-carousel-screen.is-active{opacity:1;transform:scale(1);filter:blur(0);}
.mg-device-base{position:absolute;left:50%;bottom:28px;width:430px;height:90px;transform:translateX(-30%) rotateX(62deg) rotateZ(-4deg);border-radius:40px;background:linear-gradient(180deg,rgba(92,78,53,.32),rgba(0,0,0,.98));border:1px solid rgba(241,193,90,.16);box-shadow:0 40px 80px rgba(0,0,0,.75),0 0 80px rgba(217,167,53,.08) inset;z-index:2;}
.mg-location-marker{position:absolute;z-index:2;width:34px;height:44px;color:var(--mg-gold);opacity:.72;filter:drop-shadow(0 0 18px rgba(217,167,53,.38));animation:mgMarkerFloat 7s ease-in-out infinite;}
.mg-location-marker::before{content:"";position:absolute;left:50%;top:50%;width:76px;height:76px;border-radius:999px;border:1px solid rgba(217,167,53,.16);transform:translate(-50%,-35%) scale(.7);opacity:.55;}
.mg-location-marker svg{display:block;width:100%;height:100%;}
.mg-location-marker.marker-a{left:72px;top:188px;animation-delay:-1.2s;}
.mg-location-marker.marker-b{right:48px;top:144px;transform:scale(.78);animation-delay:-3.1s;}
.mg-location-marker.marker-c{left:118px;bottom:128px;transform:scale(.66);animation-delay:-4.4s;}
.mg-location-marker.marker-d{right:112px;bottom:180px;transform:scale(.58);animation-delay:-2.2s;}
@keyframes mgMarkerFloat{0%,100%{translate:0 0;opacity:.54;}50%{translate:0 -14px;opacity:.9;}}
.mg-mesh-ribbon{position:absolute;z-index:0;inset:60px -30px 20px 20px;opacity:.54;background:url('/images/cosmic_golden_network_on_black.png') center/cover no-repeat;mix-blend-mode:screen;mask-image:radial-gradient(circle at 62% 48%,#000 0 44%,transparent 76%);pointer-events:none;}
.mg-carousel-dots{position:absolute;z-index:8;left:50%;bottom:14px;transform:translateX(-50%);display:flex;gap:9px;padding:9px 11px;border-radius:999px;background:rgba(0,0,0,.42);border:1px solid rgba(255,255,255,.08);}
.mg-carousel-dot{width:8px;height:8px;border-radius:999px;background:rgba(255,255,255,.28);transition:width .3s ease,background .3s ease;}
.mg-carousel-dot.is-active{width:28px;background:var(--mg-gold);}
.mg-hero-microcopy{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;margin-top:54px;max-width:720px;}
.mg-metric{padding-top:18px;border-top:1px solid rgba(217,167,53,.24);}
.mg-metric strong{display:block;color:#fff;font-size:15px;font-weight:800;}
.mg-metric span{display:block;margin-top:7px;color:#9f9b92;font-size:12px;line-height:1.45;}
.mg-section-head{margin-bottom:54px;}
.mg-card-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;}
.mg-feature-card,.mg-system-card{position:relative;overflow:hidden;min-height:250px;padding:34px;border-radius:18px;border:1px solid rgba(217,167,53,.34);background:linear-gradient(145deg,rgba(10,10,9,.92),rgba(3,3,3,.7));box-shadow:0 0 0 1px rgba(255,255,255,.03) inset;}
.mg-feature-card::after,.mg-system-card::after{content:"";position:absolute;left:50%;bottom:28px;width:72px;height:2px;background:linear-gradient(90deg,transparent,var(--mg-gold),transparent);transform:translateX(-50%);opacity:.76;}
.mg-icon{width:52px;height:52px;display:grid;place-items:center;margin:0 auto 30px;color:var(--mg-gold);}
.mg-icon svg{width:100%;height:100%;stroke:currentColor;}
.mg-feature-card h3,.mg-system-card h3{margin:0;color:#fff;text-align:center;font-size:21px;line-height:1.1;letter-spacing:-.04em;}
.mg-feature-card p,.mg-system-card p{margin:15px auto 0;max-width:250px;color:#c1bcb2;text-align:center;font-size:14px;line-height:1.55;}
.mg-system-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
.mg-system-card{min-height:520px;padding:30px;}
.mg-system-card .mg-icon{margin:0 0 18px;width:42px;height:42px;}
.mg-system-card h3{text-align:left;font-size:27px;}
.mg-system-card p{text-align:left;margin-left:0;max-width:360px;}
.mg-ui-shell{margin-top:28px;overflow:hidden;border-radius:15px;border:1px solid rgba(217,167,53,.22);background:rgba(0,0,0,.38);}
.mg-ui-shell img{display:block;width:100%;height:305px;object-fit:cover;object-position:top center;}
.mg-code-panel{margin-top:28px;padding:22px;min-height:305px;border-radius:15px;border:1px solid rgba(217,167,53,.2);background:#050505;color:#e9e4d8;font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace;font-size:13px;line-height:1.72;overflow:auto;}
.mg-code-panel .gold{color:var(--mg-gold-2);}.mg-code-panel .green{color:#7de6aa;}
.mg-card-foot{display:flex;justify-content:space-around;gap:16px;margin-top:24px;padding-top:18px;border-top:1px solid rgba(217,167,53,.18);color:#ddd8ca;font-size:13px;}
.mg-footer{position:relative;overflow:hidden;padding:140px 0 52px;background:#030303;color:#fff;}
.mg-footer-grid{display:grid;grid-template-columns:1.45fr repeat(3,1fr);gap:86px;align-items:start;}
.mg-footer-brand .mg-logo{margin-bottom:30px;}.mg-footer-brand p{margin:0;color:#c6c1b7;font-size:20px;line-height:1.45;}
.mg-socials{display:flex;gap:16px;margin-top:34px;}.mg-socials a{width:46px;height:46px;display:grid;place-items:center;border:1px solid rgba(217,167,53,.44);border-radius:10px;color:#fff;text-decoration:none;font-weight:800;background:rgba(255,255,255,.02);}
.mg-email{display:inline-flex;align-items:center;gap:12px;margin-top:34px;color:#eee;text-decoration:none;font-size:16px;}.mg-email svg{width:22px;height:22px;color:var(--mg-gold);}
.mg-footer-column h3{margin:0 0 34px;color:var(--mg-gold);font-size:13px;letter-spacing:.38em;text-transform:uppercase;}.mg-footer-column h3::after{content:"";display:block;width:42px;height:2px;margin-top:20px;background:var(--mg-gold);}
.mg-footer-column nav{display:grid;gap:0;}.mg-footer-column a{padding:17px 0;border-bottom:1px solid rgba(255,255,255,.07);color:#eee;text-decoration:none;font-size:18px;}
.mg-footer-bottom{display:flex;justify-content:space-between;gap:30px;margin-top:92px;padding-top:50px;border-top:1px solid rgba(217,167,53,.54);color:#bbb5aa;font-size:14px;}
.mg-footer-links{display:flex;flex-wrap:wrap;gap:20px;align-items:center;}.mg-footer-links a{color:#f2eee5;text-decoration:none;}.mg-footer-links span{color:var(--mg-gold);}
.mg-progress{position:fixed;top:0;left:0;right:0;height:3px;z-index:80;background:transparent;}.mg-progress-bar{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--mg-gold),#fff0bc);box-shadow:0 0 18px rgba(217,167,53,.55);}
[data-reveal]{opacity:1;transform:none;transition:opacity .9s cubic-bezier(.16,1,.3,1),transform .9s cubic-bezier(.16,1,.3,1);transition-delay:var(--delay,0ms);} .js [data-reveal]{opacity:0;} .js [data-reveal="left"]{transform:translateX(-42px);} .js [data-reveal="right"]{transform:translateX(42px);} .js [data-reveal="up"]{transform:translateY(42px);} .js [data-reveal].is-visible{opacity:1;transform:none;}
@media(max-width:1180px){.mg-hero-grid{grid-template-columns:1fr;gap:40px;}.mg-hero-visual{min-height:650px;}.mg-card-grid{grid-template-columns:repeat(2,1fr);}.mg-system-grid{grid-template-columns:1fr;}.mg-system-card{min-height:0;}.mg-footer-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:780px){.mg-site-header{position:sticky;}.mg-nav{width:min(100% - 32px,680px);height:64px;}.mg-logo-text{letter-spacing:.16em;font-size:12px;}.mg-logo-mark{width:26px;height:26px;}.mg-nav-links{display:none;}.mg-header-cta{min-height:40px;padding:0 13px;font-size:12px;}.mg-container{width:min(100% - 32px,680px);}.mg-hero{padding:54px 0 80px;}.mg-section{min-height:auto;padding:96px 0;}.mg-title{font-size:clamp(48px,14vw,76px);}.mg-section-title{font-size:clamp(38px,11vw,62px);}.mg-actions{display:grid;grid-template-columns:1fr;}.mg-btn{width:100%;min-height:54px;}.mg-hero-microcopy{grid-template-columns:1fr;}.mg-hero-visual{min-height:560px;margin-top:20px;}.mg-visual-stage{height:560px;}.mg-phone-orbit{inset:20px 78px 64px 72px;}.mg-location-marker.marker-a{left:8px;top:118px;}.mg-location-marker.marker-b{right:4px;top:96px;}.mg-location-marker.marker-c{left:36px;bottom:120px;}.mg-location-marker.marker-d{right:34px;bottom:152px;}.mg-card-grid{grid-template-columns:1fr;}.mg-feature-card{min-height:220px;}.mg-system-card{padding:22px;}.mg-ui-shell img{height:250px;}.mg-footer{padding:92px 0 38px;}.mg-footer-grid{grid-template-columns:1fr;gap:42px;}.mg-footer-bottom{display:grid;}}
@media(max-width:480px){.mg-hero-visual{min-height:520px;}.mg-visual-stage{height:520px;}.mg-phone-orbit{inset:20px 54px 72px 48px;}.mg-device-base{width:310px;}.mg-carousel-dots{bottom:38px;}.mg-footer-brand p{font-size:17px;}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important;}}
</style>
</head>
<body>
<main class="mg-page" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>
  <header class="mg-site-header" aria-label="Microgifter site navigation"><nav class="mg-nav"><a class="mg-logo" href="/" aria-label="Microgifter home"><svg class="mg-logo-mark" viewBox="0 0 256 256" aria-hidden="true"><path d="M38 54H86L128 96L170 54H218V202H170V118L128 160L86 118V202H38V54Z" fill="currentColor"/><path d="M96 108L128 140L160 108L128 76L96 108Z" fill="currentColor" opacity=".72"/></svg><span class="mg-logo-text">Microgifter</span></a><div class="mg-nav-links" aria-label="Primary navigation"><a href="#platform">Platform</a><a href="#growth">API</a><a href="#merchants">Merchants</a><a href="/developer-docs.php">Docs</a></div><a class="mg-header-cta" href="/signup.php">Create Account <span>→</span></a></nav></header>
  <section class="mg-section mg-hero" aria-labelledby="mgHeroTitle"><div class="mg-bg-mesh" aria-hidden="true"></div><div class="mg-container mg-hero-grid"><div class="mg-hero-copy"><div class="mg-eyebrow" data-reveal="left">The rewards layer for local commerce</div><h1 class="mg-title" id="mgHeroTitle" data-reveal="left" style="--delay:100ms">Connect your business to the future of commerce.</h1><p class="mg-lede" data-reveal="left" style="--delay:200ms">Microgifter helps merchants launch agent-ready products, deliver wallet-ready rewards, and track claims, visits, and revenue from one system. Turn simple local offers into measurable demand.</p><div class="mg-actions" data-reveal="left" style="--delay:300ms"><a class="mg-btn mg-btn-primary" href="/signup.php">Create Account <span class="mg-arrow">→</span></a><a class="mg-btn mg-btn-secondary" href="/learn-more.php">Book a Demo <span class="mg-arrow">→</span></a></div><div class="mg-hero-microcopy" data-reveal="left" style="--delay:410ms" aria-label="Platform highlights"><div class="mg-metric"><strong>Agent-ready</strong><span>Structured rewards apps and assistants can understand.</span></div><div class="mg-metric"><strong>Wallet-ready</strong><span>Save, claim, redeem, and revisit from one experience.</span></div><div class="mg-metric"><strong>Measurable</strong><span>Claims, visits, revenue, and campaign performance.</span></div></div></div><div class="mg-hero-visual" data-reveal="right" aria-label="Animated Microgifter wallet and analytics carousel"><div class="mg-visual-stage"><div class="mg-mesh-ribbon" aria-hidden="true"></div><span class="mg-location-marker marker-a" aria-hidden="true"><svg viewBox="0 0 64 84"><path d="M32 78S10 50 10 30C10 17 20 8 32 8s22 9 22 22c0 20-22 48-22 48Z" fill="none" stroke="currentColor" stroke-width="5"/><circle cx="32" cy="30" r="8" fill="currentColor"/></svg></span><span class="mg-location-marker marker-b" aria-hidden="true"><svg viewBox="0 0 64 84"><path d="M32 78S10 50 10 30C10 17 20 8 32 8s22 9 22 22c0 20-22 48-22 48Z" fill="none" stroke="currentColor" stroke-width="5"/><circle cx="32" cy="30" r="8" fill="currentColor"/></svg></span><span class="mg-location-marker marker-c" aria-hidden="true"><svg viewBox="0 0 64 84"><path d="M32 78S10 50 10 30C10 17 20 8 32 8s22 9 22 22c0 20-22 48-22 48Z" fill="none" stroke="currentColor" stroke-width="5"/><circle cx="32" cy="30" r="8" fill="currentColor"/></svg></span><span class="mg-location-marker marker-d" aria-hidden="true"><svg viewBox="0 0 64 84"><path d="M32 78S10 50 10 30C10 17 20 8 32 8s22 9 22 22c0 20-22 48-22 48Z" fill="none" stroke="currentColor" stroke-width="5"/><circle cx="32" cy="30" r="8" fill="currentColor"/></svg></span><div class="mg-phone-orbit"><div class="mg-phone"><div class="mg-phone-screen"><span class="mg-phone-notch" aria-hidden="true"></span><span class="mg-phone-shine" aria-hidden="true"></span><img class="mg-carousel-screen is-active" src="/images/reward_saved_coffee_for_two.png" alt="Wallet-ready reward screen"><img class="mg-carousel-screen" src="/images/merchant_analytics_dashboard_design.png" alt="Merchant analytics mobile screen"><img class="mg-carousel-screen" src="/images/coffee_for_two_reward_screen.png" alt="Agent-ready reward mobile screen"><img class="mg-carousel-screen" src="/images/nearby_offers_in_belmont_ca.png" alt="Local discovery mobile screen"><div class="mg-carousel-dots" aria-hidden="true"><span class="mg-carousel-dot is-active"></span><span class="mg-carousel-dot"></span><span class="mg-carousel-dot"></span><span class="mg-carousel-dot"></span></div></div></div></div><div class="mg-device-base" aria-hidden="true"></div></div></div></div></section>
  <section class="mg-section" id="merchants" aria-labelledby="merchantTitle"><div class="mg-bg-mesh" aria-hidden="true"></div><div class="mg-container"><div class="mg-eyebrow" data-reveal="left">For merchants</div><div class="mg-section-head"><h2 class="mg-section-title" id="merchantTitle" data-reveal="left" style="--delay:100ms">What it does for your business.</h2><p class="mg-section-copy" data-reveal="left" style="--delay:190ms">From prepaid demand to wallet-ready rewards, Microgifter helps local businesses sell smarter, track performance, and connect with the next generation of commerce.</p></div><div class="mg-card-grid" id="platform"><article class="mg-feature-card" data-reveal="up"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M12 25h40v27H12V25Z" stroke-width="3"/><path d="M32 25v27M12 34h40" stroke-width="3"/><path d="M32 25c-6-8-15-9-16-3-1 5 8 6 16 3Zm0 0c6-8 15-9 16-3 1 5-8 6-16 3Z" stroke-width="3"/></svg></div><h3>Prepaid demand</h3><p>Get paid before the customer arrives and turn local offers into future visits.</p></article><article class="mg-feature-card" data-reveal="up" style="--delay:90ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M32 7 54 19v26L32 57 10 45V19L32 7Z" stroke-width="3"/><path d="M10 19l22 13 22-13M32 32v25" stroke-width="3"/></svg></div><h3>Agent-ready products</h3><p>Create structured offers that AI agents and apps can discover, recommend, and send.</p></article><article class="mg-feature-card" data-reveal="up" style="--delay:180ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><rect x="9" y="15" width="46" height="34" rx="7" stroke-width="3"/><path d="M15 15v-5h34c4 0 6 2 6 6v5" stroke-width="3"/><rect x="34" y="27" width="21" height="14" rx="4" stroke-width="3"/></svg></div><h3>Wallet system</h3><p>Deliver rewards customers can save, claim, and redeem from a simple wallet experience.</p></article><article class="mg-feature-card" data-reveal="up" style="--delay:270ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M12 50V34M25 50V25M38 50V30M51 50V14" stroke-width="3"/><path d="M12 22l13-9 13 5 15-14" stroke-width="3"/></svg></div><h3>Merchant analytics</h3><p>Track claims, redemption, visits, and revenue with clear performance visibility.</p></article></div><div class="mg-actions" data-reveal="up" style="--delay:240ms"><a class="mg-btn mg-btn-secondary" href="/developer-docs.php">Build with the API <span class="mg-arrow">→</span></a><a class="mg-btn mg-btn-primary" href="/signup.php">Create Account <span class="mg-arrow">→</span></a></div></div></section>
  <section class="mg-section" id="growth" aria-labelledby="growthTitle"><div class="mg-bg-mesh" aria-hidden="true"></div><div class="mg-container"><div class="mg-eyebrow" data-reveal="left">For growth</div><div class="mg-section-head"><h2 class="mg-section-title" id="growthTitle" data-reveal="left" style="--delay:100ms">Distribute rewards, run campaigns, and manage customer relationships.</h2><p class="mg-section-copy" data-reveal="left" style="--delay:190ms">Microgifter gives merchants the tools to send rewards through the Distribution API, launch targeted campaigns, and organize customer activity in one connected CRM.</p></div><div class="mg-system-grid"><article class="mg-system-card" data-reveal="up"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M25 17 10 32l15 15M39 17l15 15-15 15M35 12 29 52" stroke-width="3"/></svg></div><h3>Distribution API</h3><p>Send agent-ready rewards through your app, website, partners, or automation workflows.</p><pre class="mg-code-panel"><span class="gold">POST</span> /v1/distribution/send
{
  "merchant_id": <span class="green">"m_123456"</span>,
  "reward": <span class="green">"coffee_for_two"</span>,
  "recipient": { "type": <span class="green">"email"</span> },
  "campaign": <span class="green">"spring_launch_2025"</span>
}</pre><div class="mg-card-foot"><span>Send</span><span>Track</span><span>Redeem</span></div></article><article class="mg-system-card" data-reveal="up" style="--delay:120ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M11 36h12l28-18v28L23 28H11v8Z" stroke-width="3"/><path d="M23 28v19c0 4 3 7 7 7h2" stroke-width="3"/></svg></div><h3>Campaigns</h3><p>Launch promotions, audience segments, and scheduled reward drops with measurable performance.</p><div class="mg-ui-shell"><img src="/images/sleek_saas_ui_with_gold_accents.png" alt="Campaign performance dashboard"></div><div class="mg-card-foot"><span>Audience</span><span>Offer</span><span>Performance</span></div></article><article class="mg-system-card" data-reveal="up" style="--delay:240ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="21" r="12" stroke-width="3"/><path d="M13 56c3-16 11-24 19-24s16 8 19 24" stroke-width="3"/></svg></div><h3>CRM</h3><p>See customer activity, reward history, and visit behavior in one simple relationship layer.</p><div class="mg-ui-shell"><img src="/images/sleek_saas_ui_with_gold_accents.png" alt="CRM relationship dashboard"></div><div class="mg-card-foot"><span>Profile</span><span>History</span><span>Engagement</span></div></article></div></div></section>
  <section class="mg-section" id="discovery" aria-labelledby="discoveryTitle"><div class="mg-bg-mesh" aria-hidden="true"></div><div class="mg-container"><div class="mg-eyebrow" data-reveal="left">For discovery</div><div class="mg-section-head"><h2 class="mg-section-title" id="discoveryTitle" data-reveal="left" style="--delay:100ms">Wallet-ready rewards for agent gifting and discovery.</h2><p class="mg-section-copy" data-reveal="left" style="--delay:190ms">Microgifter helps merchants deliver rewards customers can save to a wallet, share through agents, and discover across the next generation of local commerce. Make every offer ready for gifting, discovery, and redemption.</p></div><div class="mg-system-grid"><article class="mg-system-card" data-reveal="up"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><rect x="9" y="15" width="46" height="34" rx="7" stroke-width="3"/><rect x="34" y="27" width="21" height="14" rx="4" stroke-width="3"/></svg></div><h3>Wallet system</h3><p>Let customers save rewards, claim them later, and redeem from a simple wallet experience.</p><div class="mg-ui-shell"><img src="/images/reward_saved_coffee_for_two.png" alt="Wallet reward screen"></div></article><article class="mg-system-card" data-reveal="up" style="--delay:120ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><path d="M12 25h40v27H12V25Z" stroke-width="3"/><path d="M32 25v27M12 34h40" stroke-width="3"/></svg></div><h3>Agent gifting</h3><p>Create structured rewards that AI agents, apps, and partners can send on behalf of customers.</p><div class="mg-ui-shell"><img src="/images/coffee_for_two_reward_screen.png" alt="Agent-ready reward screen"></div></article><article class="mg-system-card" data-reveal="up" style="--delay:240ms"><div class="mg-icon"><svg viewBox="0 0 64 64" fill="none"><circle cx="28" cy="28" r="18" stroke-width="3"/><path d="M42 42 55 55" stroke-width="3"/></svg></div><h3>Discovery layer</h3><p>Make local offers discoverable across apps, assistant experiences, and future commerce channels.</p><div class="mg-ui-shell"><img src="/images/nearby_offers_in_belmont_ca.png" alt="Nearby offers discovery screen"></div></article></div><div class="mg-actions" data-reveal="up" style="--delay:260ms"><a class="mg-btn mg-btn-primary" href="/signup.php">Create Account <span class="mg-arrow">→</span></a><a class="mg-btn mg-btn-secondary" href="/learn-more.php">Book a Demo <span class="mg-arrow">→</span></a></div></div></section>
  <footer class="mg-footer"><div class="mg-bg-mesh" aria-hidden="true"></div><div class="mg-container"><div class="mg-footer-grid"><div class="mg-footer-brand" data-reveal="up"><a class="mg-logo" href="/" aria-label="Microgifter home"><svg class="mg-logo-mark" viewBox="0 0 256 256" aria-hidden="true"><path d="M38 54H86L128 96L170 54H218V202H170V118L128 160L86 118V202H38V54Z" fill="currentColor"/><path d="M96 108L128 140L160 108L128 76L96 108Z" fill="currentColor" opacity=".72"/></svg><span class="mg-logo-text">Microgifter</span></a><p>Local rewards. Real results.<br>Powering agent-ready commerce.</p><div class="mg-socials" aria-label="Social links"><a href="https://linkedin.com" aria-label="LinkedIn">in</a><a href="https://x.com" aria-label="X">X</a><a href="https://github.com" aria-label="GitHub">●</a><a href="https://youtube.com" aria-label="YouTube">▶</a></div><a class="mg-email" href="mailto:hello@microgifter.com"><svg viewBox="0 0 64 64" fill="none"><rect x="8" y="14" width="48" height="36" rx="6" stroke="currentColor" stroke-width="4"/><path d="M10 18 32 34 54 18" stroke="currentColor" stroke-width="4"/></svg>hello@microgifter.com</a></div><div class="mg-footer-column" data-reveal="up" style="--delay:90ms"><h3>Product</h3><nav><a href="#merchants">Rewards</a><a href="#discovery">Wallet</a><a href="#growth">Campaigns</a><a href="#merchants">Merchant Analytics</a></nav></div><div class="mg-footer-column" data-reveal="up" style="--delay:180ms"><h3>Platform</h3><nav><a href="#growth">Distribution API</a><a href="#discovery">Agent Toolkit</a><a href="/developer-docs.php">Docs</a><a href="/learn-more.php">Book a Demo</a></nav></div><div class="mg-footer-column" data-reveal="up" style="--delay:270ms"><h3>Company</h3><nav><a href="/about.php">About Us</a><a href="/careers.php">Careers</a><a href="/contact.php">Contact</a><a href="/press.php">Press Kit</a></nav></div></div><div class="mg-footer-bottom"><span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span><div class="mg-footer-links"><a href="/privacy.php">Privacy Policy</a><span>•</span><a href="/terms.php">Terms of Service</a><span>•</span><a href="/security.php">Security</a><span>•</span><a href="/status.php">Status</a></div></div></div></footer>
</main>
<script>
document.documentElement.classList.add('js');
(() => {
  const screens = Array.from(document.querySelectorAll('.mg-carousel-screen'));
  const dots = Array.from(document.querySelectorAll('.mg-carousel-dot'));
  let index = 0;
  const setSlide = next => { if (!screens.length) return; screens[index]?.classList.remove('is-active'); dots[index]?.classList.remove('is-active'); index = next % screens.length; screens[index]?.classList.add('is-active'); dots[index]?.classList.add('is-active'); };
  if (screens.length > 1) setInterval(() => setSlide(index + 1), 4200);
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
  const showAll = () => revealItems.forEach(el => el.classList.add('is-visible'));
  if ('IntersectionObserver' in window) { const observer = new IntersectionObserver(entries => { entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } }); }, {threshold:.12, rootMargin:'0px 0px -8% 0px'}); revealItems.forEach(el => observer.observe(el)); setTimeout(showAll, 1800); } else { showAll(); }
  const progress = document.getElementById('mgProgressBar');
  const updateProgress = () => { if (!progress) return; const scrollTop = window.pageYOffset || document.documentElement.scrollTop; const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight); progress.style.width = `${Math.min(100, Math.max(0, scrollTop / max * 100))}%`; };
  updateProgress(); window.addEventListener('scroll', updateProgress, {passive:true});
})();
</script>
</body>
</html>
