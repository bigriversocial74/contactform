<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/app.php';

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
        'links' => [
            [
                'label' => 'Book A Demo',
                'href' => '/learn-more.php',
            ],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];

require dirname(__DIR__, 3) . '/includes/header.php';
?>
<script>document.documentElement.classList.add('mg-js');</script>
<style>
:root{
  --mg-black:#030303;
  --mg-panel:#080808;
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
body{min-height:100%;overflow-y:auto!important;overflow-x:hidden;background:var(--mg-black);} 

/* Index page owns its header. Hide the shared logged-out/public header on this page only. */
body > header:not(.mg-site-header),
body > footer:not(.mg-footer),
body > .public-header,
body > .site-header,
body > .sg-header,
body > .sg-public-header,
body > .universal-header,
body > .navbar,
body > .navbar-wrap,
body > .topbar,
body > .app-header,
#public-header,
#site-header,
.public-header,
.sg-public-header,
.universal-header:not(.mg-site-header),
.header-components-public-header,
.header-shell,
.logged-out-header{display:none!important;}


.mg-page,
.mg-page *{box-sizing:border-box;}
.mg-page{
  min-height:100vh;
  overflow-x:hidden;
  overflow-y:visible;
  background:var(--mg-black);
  color:var(--mg-white);
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  letter-spacing:-.01em;
}
.mg-page a{color:inherit;}
.mg-bg-mesh{
  position:absolute;
  inset:0;
  pointer-events:none;
  overflow:hidden;
  opacity:.58;
  background:
    linear-gradient(90deg,rgba(0,0,0,.72),rgba(0,0,0,.14) 47%,rgba(0,0,0,.72)),
    url('/images/cosmic_golden_network_on_black.png') center/cover no-repeat;
  mix-blend-mode:screen;
}
.mg-bg-mesh::after{
  content:"";
  position:absolute;
  inset:0;
  background:radial-gradient(circle at 70% 35%,rgba(217,167,53,.12),transparent 35%),linear-gradient(180deg,rgba(0,0,0,.2),#030303 95%);
}
.mg-container{
  width:min(var(--mg-max),calc(100% - 80px));
  margin:0 auto;
  position:relative;
  z-index:2;
}
.mg-site-header{
  position:fixed;
  top:0;
  left:0;
  right:0;
  z-index:50;
  background:linear-gradient(180deg,rgba(3,3,3,.92),rgba(3,3,3,.66));
  border-bottom:1px solid rgba(255,255,255,.07);
  backdrop-filter:blur(18px);
  transform:translateY(-14px);
  opacity:0;
  animation:mgHeaderIn .75s cubic-bezier(.16,1,.3,1) .08s forwards;
}
@keyframes mgHeaderIn{to{transform:none;opacity:1;}}
.mg-nav{
  width:min(var(--mg-max),calc(100% - 80px));
  height:58px;
  margin:0 auto;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:24px;
}
.mg-logo{
  display:inline-flex;
  align-items:center;
  gap:14px;
  text-decoration:none;
  color:var(--mg-white);
}
.mg-logo-mark{width:26px;height:26px;display:block;}
.mg-logo-text{
  font-size:13px;
  font-weight:780;
  letter-spacing:.26em;
  text-transform:uppercase;
}
.mg-nav-links{
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:30px;
  margin-left:auto;
}
.mg-nav-links a{
  color:#e9e7df;
  text-decoration:none;
  font-size:12px;
  font-weight:650;
  opacity:.82;
  transition:opacity .2s ease,color .2s ease;
}
.mg-nav-links a:hover{opacity:1;color:#fff;}
.mg-header-cta{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:11px;
  min-height:36px;
  padding:0 16px;
  border:1px solid rgba(241,193,90,.46);
  border-radius:12px;
  color:#fff;
  text-decoration:none;
  font-size:12px;
  font-weight:780;
  background:rgba(255,255,255,.025);
  box-shadow:0 0 0 1px rgba(255,255,255,.03) inset;
  transition:transform .2s ease,border-color .2s ease,background .2s ease;
}
.mg-header-cta:hover{transform:translateY(-2px);border-color:rgba(241,193,90,.82);background:rgba(217,167,53,.08);}
.mg-mobile-menu{display:none;}

.mg-section{
  position:relative;
  min-height:100svh;
  padding:150px 0;
  background:var(--mg-black);
  border-bottom:1px solid rgba(255,255,255,.055);
}
.mg-eyebrow{
  display:inline-flex;
  align-items:center;
  gap:13px;
  color:#f5efe1;
  font-size:12px;
  font-weight:800;
  letter-spacing:.34em;
  text-transform:uppercase;
}
.mg-eyebrow::before{
  content:"";
  width:13px;
  height:13px;
  border:2px solid var(--mg-gold);
  border-radius:50%;
  box-shadow:0 0 18px rgba(217,167,53,.34);
}
.mg-title{
  margin:28px 0 0;
  color:var(--mg-white);
  font-size:clamp(50px,7vw,112px);
  line-height:.94;
  letter-spacing:-.072em;
  font-weight:760;
}
.mg-section-title{
  margin:26px 0 0;
  color:var(--mg-white);
  font-size:clamp(44px,5.7vw,88px);
  line-height:.96;
  letter-spacing:-.06em;
  font-weight:740;
}
.mg-lede{
  max-width:690px;
  margin:28px 0 0;
  color:var(--mg-soft);
  font-size:clamp(17px,1.55vw,22px);
  line-height:1.58;
  font-weight:430;
}
.mg-actions{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
  margin-top:42px;
}
.mg-btn{
  min-height:58px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:18px;
  padding:0 26px;
  border-radius:12px;
  text-decoration:none;
  font-size:16px;
  font-weight:780;
  border:1px solid transparent;
  transition:transform .22s ease,border-color .22s ease,background .22s ease,box-shadow .22s ease;
}
.mg-btn:hover{transform:translateY(-3px);}
.mg-btn-primary{background:linear-gradient(180deg,#fff,#e8e5dc);color:#050505!important;box-shadow:0 22px 60px rgba(255,255,255,.1);}
.mg-btn-primary span,.mg-btn-primary .mg-arrow{color:#050505!important;}
.mg-btn-secondary{color:#fff!important;}
.mg-btn-secondary span,.mg-btn-secondary .mg-arrow{color:#fff!important;}
.mg-btn-primary:hover{box-shadow:0 28px 80px rgba(255,255,255,.17);}
.mg-btn-secondary{background:rgba(255,255,255,.018);color:#fff;border-color:rgba(241,193,90,.42);}
.mg-btn-secondary:hover{border-color:rgba(241,193,90,.78);background:rgba(217,167,53,.065);}
.mg-arrow{font-size:24px;line-height:1;}

.mg-hero{
  min-height:100svh;
  display:flex;
  align-items:center;
  padding:106px 0 80px;
}
.mg-hero-grid{
  display:grid;
  grid-template-columns:minmax(0,.92fr) minmax(430px,1.08fr);
  gap:58px;
  align-items:center;
}
.mg-hero-copy{position:relative;z-index:3;max-width:750px;}
.mg-hero-visual{
  position:relative;
  min-height:720px;
  display:grid;
  place-items:center;
  perspective:1400px;
}
.mg-visual-stage{
  position:relative;
  width:min(680px,100%);
  height:720px;
  transform-style:preserve-3d;
}
.mg-phone-orbit{
  position:absolute;
  inset:38px 170px 58px 138px;
  transform:rotateY(-16deg) rotateZ(3deg);
  transform-style:preserve-3d;
  filter:drop-shadow(0 38px 70px rgba(0,0,0,.7));
  animation:mgPhoneFloat 7s ease-in-out infinite;
}
@keyframes mgPhoneFloat{
  0%,100%{transform:rotateY(-16deg) rotateZ(3deg) translateY(0);}
  50%{transform:rotateY(-10deg) rotateZ(2deg) translateY(-16px);}
}
.mg-phone{
  position:relative;
  width:100%;
  height:100%;
  border-radius:42px;
  padding:12px;
  background:linear-gradient(92deg,#161616,#050505 35%,#24211a 64%,#080808);
  border:1px solid rgba(255,255,255,.15);
  box-shadow:0 0 0 2px rgba(255,255,255,.05) inset,0 0 0 4px rgba(0,0,0,.9) inset,20px 14px 42px rgba(0,0,0,.5);
}
.mg-phone::before{
  content:"";
  position:absolute;
  right:-13px;
  top:112px;
  width:9px;
  height:88px;
  border-radius:0 8px 8px 0;
  background:linear-gradient(180deg,#2b2a26,#050505);
  border:1px solid rgba(255,255,255,.14);
}
.mg-phone::after{
  content:"";
  position:absolute;
  inset:0;
  border-radius:42px;
  pointer-events:none;
  background:linear-gradient(120deg,rgba(255,255,255,.14),transparent 24%,transparent 74%,rgba(217,167,53,.12));
  mix-blend-mode:screen;
  opacity:.55;
}
.mg-phone-screen{
  position:relative;
  width:100%;
  height:100%;
  overflow:hidden;
  border-radius:34px;
  background:#000;
}
.mg-phone-shine{
  position:absolute;
  z-index:5;
  inset:-40px;
  background:linear-gradient(112deg,rgba(255,255,255,.18),transparent 28%,transparent 62%,rgba(255,255,255,.04));
  transform:translateX(-36%);
  pointer-events:none;
  mix-blend-mode:screen;
  animation:mgShine 10s ease-in-out infinite;
}
@keyframes mgShine{
  0%,70%,100%{transform:translateX(-42%);opacity:.22;}
  35%{transform:translateX(40%);opacity:.38;}
}
.mg-carousel-screen{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  object-fit:cover;
  opacity:0;
  transform:scale(1.018);
  transition:opacity 1.2s ease,transform 1.2s ease,filter 1.2s ease;
  filter:blur(8px);
}
.mg-carousel-screen.is-active{opacity:1;transform:scale(1);filter:blur(0);}
.mg-device-base{
  position:absolute;
  left:50%;
  bottom:28px;
  width:430px;
  height:90px;
  transform:translateX(-30%) rotateX(62deg) rotateZ(-4deg);
  border-radius:40px;
  background:linear-gradient(180deg,rgba(92,78,53,.32),rgba(0,0,0,.98));
  border:1px solid rgba(241,193,90,.16);
  box-shadow:0 40px 80px rgba(0,0,0,.75),0 0 80px rgba(217,167,53,.08) inset;
}
.mg-float-card{
  position:absolute;
  z-index:1;
  overflow:hidden;
  border-radius:24px;
  border:1px solid rgba(217,167,53,.38);
  background:linear-gradient(145deg,rgba(9,9,8,.94),rgba(4,4,4,.76));
  box-shadow:0 30px 80px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.04) inset;
  transform-style:preserve-3d;
}
.mg-float-card img{display:block;width:100%;height:100%;object-fit:cover;}
.mg-float-card{display:none!important;}
.mg-float-analytics{left:18px;top:330px;width:210px;height:264px;transform:rotateY(18deg) translateZ(-40px);animation:mgPanelOne 8s ease-in-out infinite;}
.mg-float-product{right:10px;top:224px;width:234px;height:292px;transform:rotateY(-18deg) translateZ(-20px);animation:mgPanelTwo 8.7s ease-in-out infinite;}
@keyframes mgPanelOne{0%,100%{transform:rotateY(18deg) translateY(0) translateZ(-40px);}50%{transform:rotateY(10deg) translateY(-12px) translateZ(-18px);}}
@keyframes mgPanelTwo{0%,100%{transform:rotateY(-18deg) translateY(0) translateZ(-20px);}50%{transform:rotateY(-10deg) translateY(12px) translateZ(0);}}

.mg-location-marker{
  position:absolute;
  z-index:2;
  width:34px;
  height:44px;
