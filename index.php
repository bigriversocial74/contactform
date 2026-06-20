<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

// Public pages render through /includes/header-components/public-header.php via includes/header.php.
$page_title = 'Microgifter | Pre-Purchase Gifts';
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

require __DIR__ . '/includes/header.php';
?>
<style>
.mg-index-hero{
  position:relative;
  min-height:calc(100svh - 64px);
  display:flex;
  align-items:center;
  overflow:hidden;
  border-bottom:1px solid rgba(219,229,241,.9);
  background:
    radial-gradient(circle at 18% 16%,rgba(255,255,255,.98),transparent 30%),
    radial-gradient(circle at 78% 24%,rgba(237,233,254,.88),transparent 35%),
    linear-gradient(180deg,#fff 0%,#f8fafc 58%,#eef2f7 100%);
}

.mg-index-hero::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.72;
  background:
    linear-gradient(90deg,rgba(15,23,42,.04) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.04) 1px,transparent 1px);
  background-size:72px 72px;
}

.mg-index-hero-inner{
  position:relative;
  z-index:1;
  width:min(1180px,92%);
  margin:0 auto;
  display:grid;
  grid-template-columns:.9fr 1.1fr;
  gap:56px;
  align-items:center;
  padding:10px 0;
}

.mg-index-hero-copy h1{
  margin:20px 0 0;
  max-width:650px;
  color:#071225;
  font-size:clamp(44px,5.8vw,78px);
  line-height:.94;
  letter-spacing:-.075em;
}

.mg-index-hero-copy p{
  margin:24px 0 0;
  max-width:590px;
  color:#64748b;
  font-size:20px;
  line-height:1.48;
  font-weight:650;
}

.mg-index-badge{
  display:inline-flex;
  align-items:center;
  gap:9px;
  min-height:36px;
  padding:0 14px;
  border:1px solid #d8e4f4;
  border-radius:999px;
  background:rgba(255,255,255,.86);
  color:#071225;
  font-size:14px;
  font-weight:950;
  box-shadow:0 12px 28px rgba(15,23,42,.06);
}

.mg-index-badge-dot{
  width:10px;
  height:10px;
  border-radius:999px;
  background:#7c3aed;
  box-shadow:0 0 0 6px rgba(124,58,237,.1);
}

.mg-index-hero-visual{
  position:relative;
  min-height:610px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.mg-index-hero-visual::before{
  content:"";
  position:absolute;
  width:82%;
  aspect-ratio:1/1;
  border-radius:999px;
  background:radial-gradient(circle,rgba(124,58,237,.16),rgba(32,191,210,.06) 48%,transparent 72%);
  filter:blur(8px);
  transform:translate(4%,2%);
}

.mg-index-hero-image{
  position:relative;
  z-index:1;
  width:min(680px,100%);
  height:auto;
  display:block;
  object-fit:contain;
  filter:drop-shadow(0 34px 44px rgba(15,23,42,.18));
  animation:mgHeroImageIn .9s cubic-bezier(.16,1,.3,1) both;
}

@keyframes mgHeroImageIn{
  from{
    opacity:0;
    transform:translateY(24px) scale(.96);
    filter:blur(5px) drop-shadow(0 20px 30px rgba(15,23,42,.12));
  }
  to{
    opacity:1;
    transform:none;
    filter:drop-shadow(0 34px 44px rgba(15,23,42,.18));
  }
}

@media (prefers-reduced-motion:reduce){
  .mg-index-hero-image{
    animation:none;
  }
}

@media(max-width:900px){
  .mg-index-hero{
    min-height:auto;
    align-items:flex-start;
  }

  .mg-index-hero-inner{
    display:block;
    width:min(680px,92%);
    padding:74px 0 82px;
  }

  .mg-index-hero-copy{
    text-align:center;
  }

  .mg-index-hero-copy h1,
  .mg-index-hero-copy p{
    margin-left:auto;
    margin-right:auto;
  }

  .mg-index-hero-visual{
    min-height:auto;
    margin-top:38px;
  }

  .mg-index-hero-image{
    width:min(610px,100%);
  }
}

@media(max-width:680px){
  .mg-index-hero-inner{
    padding:54px 0 68px;
  }

  .mg-index-hero-copy{
    text-align:left;
  }

  .mg-index-hero-copy h1{
    font-size:clamp(44px,13vw,64px);
  }

  .mg-index-hero-copy p{
    font-size:18px;
  }

  .mg-index-hero-visual{
    margin-top:28px;
  }

  .mg-index-hero-image{
    width:112%;
    max-width:none;
    margin-left:-6%;
  }
}

/* Shared landing-page sections */
.mg-index-section{
  position:relative;
  overflow:hidden;
  padding:200px 0;
  border-bottom:1px solid rgba(219,229,241,.9);
  color:#071225;
}

.mg-index-section::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.52;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.mg-index-container{
  position:relative;
  z-index:1;
  width:min(1180px,92%);
  margin:0 auto;
}

.mg-section-grid{
  display:grid;
  grid-template-columns:1.08fr .92fr;
  gap:76px;
  align-items:center;
}

.mg-section-copy h2,
.mg-section-heading h2{
  margin:18px 0 18px;
  color:#071225;
  font-size:clamp(40px,5vw,68px);
  line-height:.96;
  letter-spacing:-.068em;
}

.mg-section-copy > p,
.mg-section-heading > p{
  margin:0;
  max-width:610px;
  color:#64748b;
  font-size:19px;
  line-height:1.58;
  font-weight:620;
}

.mg-section-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:30px;
}

.mg-section-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:50px;
  padding:0 20px;
  border-radius:14px;
  border:1px solid transparent;
  font-weight:900;
  text-decoration:none;
  transition:.2s ease;
}

.mg-section-btn:hover{
  transform:translateY(-2px);
}

.mg-section-btn-primary{
  color:#fff;
  background:linear-gradient(135deg,#6d5dfc,#7c3aed);
  box-shadow:0 16px 34px rgba(109,93,252,.22);
}

.mg-section-btn-secondary{
  color:#334155;
  background:#fff;
  border-color:#dbe5f1;
}

/* Revenue section */
.mg-revenue-section{
  background:
    radial-gradient(circle at 16% 50%,rgba(237,233,254,.86),transparent 34%),
    radial-gradient(circle at 88% 18%,rgba(220,252,231,.54),transparent 30%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.mg-revenue-card{
  padding:24px;
  border:1px solid #dbe5f1;
  border-radius:30px;
  background:rgba(255,255,255,.96);
  box-shadow:0 34px 90px rgba(15,23,42,.13);
}

.mg-revenue-card-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  margin-bottom:18px;
}

.mg-revenue-card-head h3{
  margin:0;
  font-size:22px;
  letter-spacing:-.04em;
}

.mg-revenue-filter{
  padding:9px 12px;
  border:1px solid #dbe5f1;
  border-radius:11px;
  background:#f8fafc;
  color:#64748b;
  font-size:12px;
  font-weight:850;
}

.mg-revenue-stats{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:10px;
  margin-bottom:18px;
}

.mg-revenue-stat{
  padding:14px;
  border:1px solid #e2e8f0;
  border-radius:15px;
  background:#f8fafc;
}

.mg-revenue-stat span{
  display:block;
  color:#64748b;
  font-size:10px;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.055em;
}

.mg-revenue-stat strong{
  display:block;
  margin-top:7px;
  font-size:20px;
  letter-spacing:-.045em;
}

.mg-revenue-stat small{
  display:block;
  margin-top:5px;
  color:#16a34a;
  font-size:10px;
  font-weight:850;
}

.mg-revenue-chart{
  width:100%;
  height:auto;
  display:block;
  overflow:visible;
}

.mg-revenue-chart .grid{
  stroke:#e2e8f0;
  stroke-width:1;
}

.mg-revenue-chart .axis-label{
  fill:#64748b;
  font-size:11px;
  font-weight:800;
}

.mg-revenue-chart .bars{
  fill:url(#mgRevenueBarGradient);
}

.mg-revenue-chart .line{
  fill:none;
  stroke:#16a34a;
  stroke-width:4;
  stroke-linecap:round;
  stroke-linejoin:round;
  filter:drop-shadow(0 8px 12px rgba(22,163,74,.2));
}

.mg-revenue-chart .point{
  fill:#fff;
  stroke:#16a34a;
  stroke-width:3;
}

.mg-benefit-list{
  display:grid;
  gap:14px;
  margin-top:28px;
}

.mg-benefit-item{
  display:grid;
  grid-template-columns:42px 1fr;
  gap:13px;
  align-items:start;
}

.mg-benefit-icon{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 24px rgba(124,58,237,.18);
  font-weight:950;
}

.mg-benefit-item strong{
  display:block;
  margin-top:2px;
  font-size:15px;
}

.mg-benefit-item span{
  display:block;
  margin-top:3px;
  color:#64748b;
  font-size:13px;
  line-height:1.45;
}

/* Simplicity section */
.mg-simple-section{
  background:
    radial-gradient(circle at 50% 10%,rgba(237,233,254,.8),transparent 38%),
    linear-gradient(180deg,#f8fafc,#f5f3ff 48%,#f8fafc);
}

.mg-section-heading{
  max-width:820px;
  margin:0 auto 62px;
  text-align:center;
}

.mg-section-heading > p{
  margin-left:auto;
  margin-right:auto;
}

.mg-feature-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:18px;
}

.mg-feature-card{
  min-height:230px;
  padding:28px;
  border:1px solid #dbe5f1;
  border-radius:24px;
  background:rgba(255,255,255,.92);
  box-shadow:0 20px 54px rgba(15,23,42,.08);
  transition:.2s ease;
}

.mg-feature-card:hover{
  transform:translateY(-4px);
  border-color:rgba(124,58,237,.28);
  box-shadow:0 26px 64px rgba(15,23,42,.12);
}

.mg-feature-card-icon{
  width:54px;
  height:54px;
  display:grid;
  place-items:center;
  border-radius:17px;
  color:#7c3aed;
  background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(32,191,210,.1));
  border:1px solid rgba(124,58,237,.12);
  font-size:25px;
}

.mg-feature-card h3{
  margin:20px 0 9px;
  font-size:23px;
  line-height:1.08;
  letter-spacing:-.045em;
}

.mg-feature-card p{
  margin:0;
  color:#64748b;
  line-height:1.55;
  font-size:15px;
}

/* Calculator section */
.mg-calculator-section{
  background:
    radial-gradient(circle at 82% 26%,rgba(237,233,254,.82),transparent 34%),
    radial-gradient(circle at 18% 80%,rgba(220,252,231,.48),transparent 28%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.mg-calculator-grid{
  display:grid;
  grid-template-columns:.9fr 1.1fr;
  gap:28px;
  align-items:stretch;
  margin-top:54px;
}

.mg-calculator-card{
  padding:30px;
  border:1px solid #dbe5f1;
  border-radius:28px;
  background:rgba(255,255,255,.95);
  box-shadow:0 28px 76px rgba(15,23,42,.1);
}

.mg-calculator-card h3{
  margin:0 0 26px;
  font-size:24px;
  letter-spacing:-.04em;
}

.mg-slider-group + .mg-slider-group{
  margin-top:28px;
}

.mg-slider-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  margin-bottom:12px;
}

.mg-slider-head label{
  color:#334155;
  font-size:14px;
  font-weight:900;
}

.mg-slider-value{
  min-width:84px;
  padding:8px 11px;
  border:1px solid #dbe5f1;
  border-radius:11px;
  background:#f8fafc;
  color:#071225;
  text-align:center;
  font-weight:950;
}

.mg-slider{
  width:100%;
  accent-color:#7c3aed;
  cursor:pointer;
}

.mg-slider-scale{
  display:flex;
  justify-content:space-between;
  margin-top:7px;
  color:#94a3b8;
  font-size:11px;
  font-weight:800;
}

.mg-calculator-note{
  margin:28px 0 0;
  padding-top:18px;
  border-top:1px solid #e2e8f0;
  color:#64748b;
  font-size:12px;
  line-height:1.5;
}

.mg-preview-card{
  padding:34px;
  border:1px solid rgba(124,58,237,.16);
  border-radius:28px;
  background:
    radial-gradient(circle at 50% 0%,rgba(255,255,255,.95),transparent 42%),
    linear-gradient(145deg,#f5f3ff,#fff 72%);
  box-shadow:0 30px 84px rgba(124,58,237,.13);
}

.mg-preview-kicker{
  color:#7c3aed;
  font-size:12px;
  font-weight:950;
  text-transform:uppercase;
  letter-spacing:.08em;
  text-align:center;
}

.mg-preview-total{
  margin:10px 0 6px;
  color:#7c3aed;
  text-align:center;
  font-size:clamp(52px,7vw,84px);
  line-height:1;
  letter-spacing:-.07em;
}

.mg-preview-caption{
  margin:0;
  color:#64748b;
  text-align:center;
  font-size:14px;
  font-weight:750;
}

.mg-preview-stats{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:10px;
  margin-top:30px;
}

.mg-preview-stat{
  padding:16px 12px;
  border:1px solid rgba(124,58,237,.12);
  border-radius:16px;
  background:rgba(255,255,255,.78);
  text-align:center;
}

.mg-preview-stat strong{
  display:block;
  font-size:20px;
}

.mg-preview-stat span{
  display:block;
  margin-top:5px;
  color:#64748b;
  font-size:10px;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.05em;
}

.mg-demand-total{
  display:grid;
  grid-template-columns:1.2fr .8fr;
  gap:12px;
  margin-top:16px;
}

.mg-demand-total-card{
  padding:18px;
  border:1px solid rgba(124,58,237,.14);
  border-radius:18px;
  background:rgba(255,255,255,.82);
}

.mg-demand-total-card.primary{
  background:linear-gradient(135deg,rgba(124,58,237,.11),rgba(32,191,210,.08));
  border-color:rgba(124,58,237,.2);
}

.mg-demand-total-card span{
  display:block;
  color:#64748b;
  font-size:10px;
  font-weight:950;
  text-transform:uppercase;
  letter-spacing:.055em;
}

.mg-demand-total-card strong{
  display:block;
  margin-top:7px;
  color:#071225;
  font-size:28px;
  line-height:1;
  letter-spacing:-.05em;
}

.mg-demand-total-card small{
  display:block;
  margin-top:7px;
  color:#64748b;
  font-size:11px;
  line-height:1.4;
}

.mg-preview-chart{
  margin-top:28px;
  padding:18px;
  border:1px solid rgba(124,58,237,.12);
  border-radius:18px;
  background:rgba(255,255,255,.76);
}

.mg-preview-chart-head{
  display:flex;
  justify-content:space-between;
  gap:15px;
  margin-bottom:12px;
  color:#475569;
  font-size:12px;
  font-weight:900;
}

.mg-preview-svg{
  width:100%;
  height:190px;
  display:block;
  overflow:visible;
}

.mg-preview-svg .grid{
  stroke:#e9e5ff;
  stroke-width:1;
}

.mg-preview-svg .area{
  fill:url(#mgPreviewAreaGradient);
}

.mg-preview-svg .line{
  fill:none;
  stroke:#7c3aed;
  stroke-width:4;
  stroke-linecap:round;
  stroke-linejoin:round;
}

.mg-preview-svg .dot{
  fill:#fff;
  stroke:#7c3aed;
  stroke-width:3;
}

@media(max-width:980px){
  .mg-section-grid,
  .mg-calculator-grid{
    grid-template-columns:1fr;
  }

  .mg-revenue-card{
    order:2;
  }

  .mg-section-copy{
    order:1;
  }

  .mg-feature-grid{
    grid-template-columns:repeat(2,1fr);
  }
}

@media(max-width:680px){
  .mg-index-section{
    padding:96px 0;
  }

  .mg-revenue-stats{
    grid-template-columns:repeat(2,1fr);
  }

  .mg-feature-grid{
    grid-template-columns:1fr;
  }

  .mg-feature-card{
    min-height:0;
  }

  .mg-preview-stats{
    grid-template-columns:1fr;
  }

  .mg-demand-total{
    grid-template-columns:1fr;
  }

  .mg-calculator-card,
  .mg-preview-card,
  .mg-revenue-card{
    padding:22px;
    border-radius:22px;
  }

  .mg-section-copy h2,
  .mg-section-heading h2{
    font-size:clamp(38px,12vw,54px);
  }
}


/* Final account CTA */
.mg-account-cta-section{
  position:relative;
  overflow:hidden;
  padding:180px 0;
  border-bottom:1px solid rgba(219,229,241,.9);
  background:
    radial-gradient(circle at 18% 24%,rgba(196,181,253,.34),transparent 30%),
    radial-gradient(circle at 84% 74%,rgba(165,243,252,.26),transparent 32%),
    linear-gradient(135deg,#071225 0%,#111b35 56%,#25124e 100%);
  color:#fff;
}

.mg-account-cta-section::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.13;
  background:
    linear-gradient(90deg,rgba(255,255,255,.32) 1px,transparent 1px),
    linear-gradient(0deg,rgba(255,255,255,.32) 1px,transparent 1px);
  background-size:72px 72px;
}

.mg-account-cta-card{
  position:relative;
  z-index:1;
  width:min(1000px,92%);
  margin:0 auto;
  padding:64px;
  border:1px solid rgba(255,255,255,.14);
  border-radius:34px;
  background:rgba(255,255,255,.07);
  box-shadow:0 34px 100px rgba(0,0,0,.28);
  backdrop-filter:blur(18px);
  text-align:center;
}

.mg-account-cta-card .mg-index-badge{
  color:#fff;
  border-color:rgba(255,255,255,.18);
  background:rgba(255,255,255,.08);
  box-shadow:none;
}

.mg-account-cta-card .mg-index-badge-dot{
  background:#a78bfa;
  box-shadow:0 0 0 6px rgba(167,139,250,.15);
}

.mg-account-cta-card h2{
  margin:22px auto 18px;
  max-width:760px;
  color:#fff;
  font-size:clamp(42px,5vw,70px);
  line-height:.96;
  letter-spacing:-.068em;
}

.mg-account-cta-card p{
  max-width:680px;
  margin:0 auto;
  color:#cbd5e1;
  font-size:19px;
  line-height:1.6;
}

.mg-account-cta-actions{
  display:flex;
  justify-content:center;
  flex-wrap:wrap;
  gap:12px;
  margin-top:32px;
}

.mg-account-cta-primary,
.mg-account-cta-secondary{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:52px;
  padding:0 22px;
  border-radius:14px;
  font-weight:950;
  text-decoration:none;
  transition:.2s ease;
}

.mg-account-cta-primary:hover,
.mg-account-cta-secondary:hover{
  transform:translateY(-2px);
}

.mg-account-cta-primary{
  color:#071225;
  background:linear-gradient(135deg,#fff,#ddd6fe);
  box-shadow:0 16px 38px rgba(167,139,250,.22);
}

.mg-account-cta-secondary{
  color:#fff;
  border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.06);
}

/* Home-page four-column footer */
body > footer:not(.mg-home-footer){
  display:none;
}

.mg-home-footer{
  padding:84px 0 34px;
  background:#fff;
  color:#071225;
}

.mg-home-footer-inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.mg-home-footer-grid{
  display:grid;
  grid-template-columns:1.45fr repeat(3,1fr);
  gap:54px;
  align-items:start;
}

.mg-home-footer-brand{
  max-width:330px;
}

.mg-home-footer-logo{
  display:inline-flex;
  align-items:center;
  gap:11px;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.mg-home-footer-mark{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 26px rgba(124,58,237,.18);
}

.mg-home-footer-brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.mg-home-socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.mg-home-socials a{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border:1px solid #dbe5f1;
  border-radius:12px;
  background:#f8fafc;
  color:#475569;
  text-decoration:none;
  font-size:13px;
  font-weight:950;
  transition:.2s ease;
}

.mg-home-socials a:hover{
  color:#7c3aed;
  border-color:rgba(124,58,237,.25);
  transform:translateY(-2px);
}

.mg-home-footer-column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.mg-home-footer-column nav{
  display:grid;
  gap:13px;
}

.mg-home-footer-column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
  transition:.18s ease;
}

.mg-home-footer-column a:hover{
  color:#7c3aed;
  transform:translateX(2px);
}

.mg-home-footer-bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.mg-home-footer-bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.mg-home-footer-bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:900px){
  .mg-home-footer-grid{
    grid-template-columns:1fr 1fr;
  }

  .mg-home-footer-brand{
    grid-column:1/-1;
  }
}

@media(max-width:680px){
  .mg-account-cta-section{
    padding:96px 0;
  }

  .mg-account-cta-card{
    padding:38px 22px;
    border-radius:24px;
  }

  .mg-account-cta-actions{
    display:grid;
  }

  .mg-account-cta-primary,
  .mg-account-cta-secondary{
    width:100%;
  }

  .mg-home-footer{
    padding-top:64px;
  }

  .mg-home-footer-grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .mg-home-footer-brand{
    grid-column:auto;
  }

  .mg-home-footer-bottom{
    display:grid;
  }
}

</style>
<section class="mg-index-hero" aria-labelledby="mg-index-title">
  <div class="mg-index-hero-inner">
    <div class="mg-index-hero-copy">
      <div class="mg-index-badge">
        <span class="mg-index-badge-dot"></span>
        Local gifting made simple
      </div>

      <h1 id="mg-index-title">
        Sell, Purchase, Send &amp; Claim Local Gifts.
      </h1>

      <p>
        Microgifter gives local businesses and their customers one simple way to create,
        purchase, send, and claim meaningful local gifts—from the first scan to the final redemption.
      </p>
    </div>

    <div class="mg-index-hero-visual">
      <img
        class="mg-index-hero-image"
        src="/images/microgifter-table-tent-phone-removebg-preview.png"
        alt="Microgifter table tent with QR code beside a mobile gift inbox"
        width="1122"
        height="1402"
      >
    </div>
  </div>
</section>


<section class="mg-index-section mg-revenue-section" id="revenue">
  <div class="mg-index-container">
    <div class="mg-section-grid">
      <div class="mg-revenue-card" aria-label="Illustrative Microgifter sales overview">
        <div class="mg-revenue-card-head">
          <h3>Sales overview</h3>
          <span class="mg-revenue-filter">This month</span>
        </div>

        <div class="mg-revenue-stats">
          <div class="mg-revenue-stat">
            <span>Total sales</span>
            <strong>$24,680</strong>
            <small>+18.6% this month</small>
          </div>
          <div class="mg-revenue-stat">
            <span>Gifts sent</span>
            <strong>1,248</strong>
            <small>+22.4% this month</small>
          </div>
          <div class="mg-revenue-stat">
            <span>Gifts claimed</span>
            <strong>918</strong>
            <small>+19.8% this month</small>
          </div>
        </div>

        <svg class="mg-revenue-chart" viewBox="0 0 720 360" role="img" aria-labelledby="mg-revenue-chart-title mg-revenue-chart-desc">
          <title id="mg-revenue-chart-title">Illustrative monthly gift sales and claims</title>
          <desc id="mg-revenue-chart-desc">Purple bars and a green line trend upward during the month.</desc>
          <defs>
            <linearGradient id="mgRevenueBarGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#7c3aed"/>
              <stop offset="100%" stop-color="#c4b5fd"/>
            </linearGradient>
          </defs>
          <line class="grid" x1="60" y1="60" x2="690" y2="60"/>
          <line class="grid" x1="60" y1="130" x2="690" y2="130"/>
          <line class="grid" x1="60" y1="200" x2="690" y2="200"/>
          <line class="grid" x1="60" y1="270" x2="690" y2="270"/>
          <text class="axis-label" x="18" y="64">$30k</text>
          <text class="axis-label" x="18" y="134">$20k</text>
          <text class="axis-label" x="18" y="204">$10k</text>
          <text class="axis-label" x="32" y="274">$0</text>
          <g class="bars">
            <rect x="84" y="222" width="34" height="48" rx="6"/>
            <rect x="130" y="208" width="34" height="62" rx="6"/>
            <rect x="176" y="190" width="34" height="80" rx="6"/>
            <rect x="222" y="201" width="34" height="69" rx="6"/>
            <rect x="268" y="171" width="34" height="99" rx="6"/>
            <rect x="314" y="154" width="34" height="116" rx="6"/>
            <rect x="360" y="164" width="34" height="106" rx="6"/>
            <rect x="406" y="137" width="34" height="133" rx="6"/>
            <rect x="452" y="118" width="34" height="152" rx="6"/>
            <rect x="498" y="128" width="34" height="142" rx="6"/>
            <rect x="544" y="96" width="34" height="174" rx="6"/>
            <rect x="590" y="76" width="34" height="194" rx="6"/>
            <rect x="636" y="88" width="34" height="182" rx="6"/>
          </g>
          <path class="line" d="M101 236 C142 214 158 210 193 217 S248 188 285 196 S339 167 377 174 S432 148 469 154 S522 126 561 135 S616 109 653 117"/>
          <g>
            <circle class="point" cx="101" cy="236" r="6"/>
            <circle class="point" cx="193" cy="217" r="6"/>
            <circle class="point" cx="285" cy="196" r="6"/>
            <circle class="point" cx="377" cy="174" r="6"/>
            <circle class="point" cx="469" cy="154" r="6"/>
            <circle class="point" cx="561" cy="135" r="6"/>
            <circle class="point" cx="653" cy="117" r="6"/>
          </g>
          <text class="axis-label" x="82" y="314">May 1</text>
          <text class="axis-label" x="222" y="314">May 8</text>
          <text class="axis-label" x="355" y="314">May 15</text>
          <text class="axis-label" x="494" y="314">May 22</text>
          <text class="axis-label" x="626" y="314">May 29</text>
        </svg>
      </div>

      <div class="mg-section-copy">
        <div class="mg-index-badge">
          <span class="mg-index-badge-dot"></span>
          Built for local businesses
        </div>

        <h2>Turn future revenue into present demand.</h2>

        <p>
          Pre-purchased local gifts give merchants revenue today and a committed customer visit tomorrow.
          Microgifter makes those purchases easy to sell, deliver, track, and claim.
        </p>

        <div class="mg-benefit-list">
          <div class="mg-benefit-item">
            <span class="mg-benefit-icon">↗</span>
            <div><strong>Immediate cash flow</strong><span>Receive revenue before the customer arrives.</span></div>
          </div>
          <div class="mg-benefit-item">
            <span class="mg-benefit-icon">$</span>
            <div><strong>Higher customer spend</strong><span>Turn a simple gift into a larger in-person purchase.</span></div>
          </div>
          <div class="mg-benefit-item">
            <span class="mg-benefit-icon">◎</span>
            <div><strong>Predictable local demand</strong><span>See committed visits before products are redeemed.</span></div>
          </div>
        </div>

        <div class="mg-section-actions">
          <a class="mg-section-btn mg-section-btn-primary" href="#earnings">Model revenue</a>
          <a class="mg-section-btn mg-section-btn-secondary" href="/learn-more.php">Book a demo</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mg-index-section mg-simple-section" id="simple">
  <div class="mg-index-container">
    <div class="mg-section-heading">
      <div class="mg-index-badge">
        <span class="mg-index-badge-dot"></span>
        Simple by design
      </div>

      <h2>Local gifting made effortless.</h2>

      <p>
        Microgifter removes the hardware, setup delays, and complicated redemption steps.
        Create products quickly, deliver them instantly, and manage every location from one account.
      </p>
    </div>

    <div class="mg-feature-grid">
      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">⏱</div>
        <h3>Products in under five minutes</h3>
        <p>Create a gift product, set its value, and start promoting it without a technical setup process.</p>
      </article>

      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">⌖</div>
        <h3>Works across multiple locations</h3>
        <p>Manage one storefront or an entire group of locations from the same simple merchant account.</p>
      </article>

      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">╱</div>
        <h3>No hardware required</h3>
        <p>No terminals, scanners, or extra equipment. Merchants can verify and claim gifts from the web.</p>
      </article>

      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">➤</div>
        <h3>Instant delivery</h3>
        <p>Send gifts immediately through digital delivery so recipients can receive them wherever they are.</p>
      </article>

      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">◇</div>
        <h3>Fraud-proof verification</h3>
        <p>Unique claim codes and tracked redemption events help prevent duplicate or unauthorized claims.</p>
      </article>

      <article class="mg-feature-card">
        <div class="mg-feature-card-icon">$</div>
        <h3>Claim payments</h3>
        <p>Connect redemption activity with merchant payment records and a clear claim history.</p>
      </article>
    </div>
  </div>
</section>

<section class="mg-index-section mg-calculator-section" id="earnings">
  <div class="mg-index-container">
    <div class="mg-section-heading">
      <div class="mg-index-badge">
        <span class="mg-index-badge-dot"></span>
        Hypothetical earnings model
      </div>

      <h2>Estimate your local gifting impact.</h2>

      <p>
        Adjust the assumptions to preview hypothetical gross gift sales.
        These figures are illustrative and are not a promise of future results.
      </p>
    </div>

    <div class="mg-calculator-grid">
      <div class="mg-calculator-card">
        <h3>Adjust your assumptions</h3>

        <div class="mg-slider-group">
          <div class="mg-slider-head">
            <label for="mgGiftValue">Average gift value</label>
            <output class="mg-slider-value" id="mgGiftValueOutput" for="mgGiftValue">$25</output>
          </div>
          <input class="mg-slider" id="mgGiftValue" type="range" min="5" max="100" step="1" value="25">
          <div class="mg-slider-scale"><span>$5</span><span>$100</span></div>
        </div>

        <div class="mg-slider-group">
          <div class="mg-slider-head">
            <label for="mgGiftSales">Gifts sold per month</label>
            <output class="mg-slider-value" id="mgGiftSalesOutput" for="mgGiftSales">250</output>
          </div>
          <input class="mg-slider" id="mgGiftSales" type="range" min="25" max="2000" step="25" value="250">
          <div class="mg-slider-scale"><span>25</span><span>2,000</span></div>
        </div>

        <div class="mg-slider-group">
          <div class="mg-slider-head">
            <label for="mgDemandMultiplier">Committed demand multiplier</label>
            <output class="mg-slider-value" id="mgDemandMultiplierOutput" for="mgDemandMultiplier">1.5×</output>
          </div>
          <input class="mg-slider" id="mgDemandMultiplier" type="range" min="0.5" max="5" step="0.1" value="1.5">
          <div class="mg-slider-scale"><span>0.5×</span><span>5×</span></div>
        </div>

        <p class="mg-calculator-note">
          Committed demand estimates the additional in-store spend generated by each pre-sold gift customer who visits the business. It is similar to an upsell multiplier and is illustrative only. Actual sales, fees, and merchant results will vary.
        </p>
      </div>

      <div class="mg-preview-card" aria-live="polite">
        <div class="mg-preview-kicker">Estimated monthly gross gift sales</div>
        <div class="mg-preview-total" id="mgMonthlyRevenue">$6,250</div>
        <p class="mg-preview-caption">Based on the selected gift value and monthly sales volume.</p>

        <div class="mg-preview-stats">
          <div class="mg-preview-stat">
            <strong id="mgPreviewSales">250</strong>
            <span>Pre-sold gifts</span>
          </div>
          <div class="mg-preview-stat">
            <strong id="mgPreviewUpsell">1.5×</strong>
            <span>Demand multiplier</span>
          </div>
          <div class="mg-preview-stat">
            <strong id="mgPreviewAnnual">$75,000</strong>
            <span>Annualized gift sales</span>
          </div>
        </div>

        <div class="mg-demand-total">
          <div class="mg-demand-total-card primary">
            <span>Total monthly revenue with committed demand</span>
            <strong id="mgCommittedTotal">$12,813</strong>
            <small>Gross gift sales plus estimated additional in-store spend.</small>
          </div>
          <div class="mg-demand-total-card">
            <span>Additional committed demand</span>
            <strong id="mgCommittedAdditional">$6,563</strong>
            <small id="mgCommittedFormula">250 pre-sold gifts × $25 × 1.5</small>
          </div>
        </div>

        <div class="mg-preview-chart">
          <div class="mg-preview-chart-head">
            <span>12-month hypothetical run rate</span>
            <span id="mgPreviewRate">$75,000 annualized</span>
          </div>
          <svg class="mg-preview-svg" id="mgPreviewChart" viewBox="0 0 620 190" role="img" aria-label="Hypothetical monthly sales projection">
            <defs>
              <linearGradient id="mgPreviewAreaGradient" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#7c3aed" stop-opacity=".22"/>
                <stop offset="100%" stop-color="#7c3aed" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <line class="grid" x1="24" y1="35" x2="596" y2="35"/>
            <line class="grid" x1="24" y1="88" x2="596" y2="88"/>
            <line class="grid" x1="24" y1="141" x2="596" y2="141"/>
            <polygon class="area" id="mgPreviewArea" points=""></polygon>
            <polyline class="line" id="mgPreviewLine" points=""></polyline>
            <g id="mgPreviewDots"></g>
          </svg>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(() => {
  const giftValue = document.getElementById('mgGiftValue');
  const giftSales = document.getElementById('mgGiftSales');
  const demandMultiplier = document.getElementById('mgDemandMultiplier');

  if (!giftValue || !giftSales || !demandMultiplier) {
    return;
  }

  const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0
  });

  const integer = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0
  });

  const updateCalculator = () => {
    const value = Number(giftValue.value);
    const sales = Number(giftSales.value);
    const multiplier = Number(demandMultiplier.value);

    const monthly = value * sales;
    const annual = monthly * 12;
    const committedAdditional = sales * value * multiplier;
    const committedTotal = monthly + committedAdditional;

    document.getElementById('mgGiftValueOutput').textContent = currency.format(value);
    document.getElementById('mgGiftSalesOutput').textContent = integer.format(sales);
    document.getElementById('mgDemandMultiplierOutput').textContent = `${multiplier.toFixed(1)}×`;

    document.getElementById('mgMonthlyRevenue').textContent = currency.format(monthly);
    document.getElementById('mgPreviewSales').textContent = integer.format(sales);
    document.getElementById('mgPreviewUpsell').textContent = `${multiplier.toFixed(1)}×`;
    document.getElementById('mgPreviewAnnual').textContent = currency.format(annual);
    document.getElementById('mgPreviewRate').textContent = `${currency.format(annual)} annualized`;
    document.getElementById('mgCommittedAdditional').textContent = currency.format(committedAdditional);
    document.getElementById('mgCommittedTotal').textContent = currency.format(committedTotal);
    document.getElementById('mgCommittedFormula').textContent =
      `${integer.format(sales)} pre-sold gifts × ${currency.format(value)} × ${multiplier.toFixed(1)}`;

    const weights = [.72,.77,.81,.86,.9,.94,.98,1.02,1.06,1.1,1.14,1.18];
    const values = weights.map(weight => monthly * weight);
    const max = Math.max(...values, 1);
    const left = 24;
    const right = 596;
    const top = 24;
    const bottom = 154;
    const width = right - left;
    const height = bottom - top;

    const points = values.map((entry, index) => {
      const x = left + (width * index / (values.length - 1));
      const y = bottom - (entry / max * height);
      return [x, y];
    });

    const pointString = points.map(point => point.join(',')).join(' ');
    const areaString = `${left},${bottom} ${pointString} ${right},${bottom}`;

    document.getElementById('mgPreviewLine').setAttribute('points', pointString);
    document.getElementById('mgPreviewArea').setAttribute('points', areaString);
    document.getElementById('mgPreviewDots').innerHTML = points
      .filter((_, index) => index % 2 === 0 || index === points.length - 1)
      .map(([x, y]) => `<circle class="dot" cx="${x}" cy="${y}" r="5"></circle>`)
      .join('');
  };

  [giftValue, giftSales, demandMultiplier].forEach(input => {
    input.addEventListener('input', updateCalculator);
  });

  updateCalculator();
})();
</script>


<section class="mg-account-cta-section" id="create-account">
  <div class="mg-account-cta-card">
    <div class="mg-index-badge">
      <span class="mg-index-badge-dot"></span>
      Start with your first local gift
    </div>

    <h2>Create your account and launch in minutes.</h2>

    <p>
      Build your first product, manage your locations, deliver gifts instantly,
      and track every claim from one simple Microgifter account.
    </p>

    <div class="mg-account-cta-actions">
      <a class="mg-account-cta-primary" href="/signup.php">Create Account</a>
      <a class="mg-account-cta-secondary" href="/learn-more.php">Book A Demo</a>
    </div>
  </div>
</section>

<footer class="mg-home-footer">
  <div class="mg-home-footer-inner">
    <div class="mg-home-footer-grid">
      <div class="mg-home-footer-brand">
        <a class="mg-home-footer-logo" href="/">
          <span class="mg-home-footer-mark">M</span>
          <span>Microgifter</span>
        </a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div class="mg-home-socials" aria-label="Social links">
           <a href="https://instagram.com/microgifter" aria-label="Instagram">ig</a>
          <a href="https://linkedin.com/microgifter" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="mg-home-footer-column">
        <h3>Product</h3>
        <nav aria-label="Product links">
                    <a href="/retail.php">Retail Subscriptions</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/discover.php">Discover</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Company</h3>
        <nav aria-label="Company links">
         <a href="/about.php">About</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="mg-home-footer-bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="mg-home-footer-bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>

<?php require __DIR__ . '/includes/footer.php'; ?>

