<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

// Public pages render through /includes/header-components/public-header.php via includes/header.php.
$page_title = 'Microgifter | Pre-Purchase Gifts';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/index-minimal-header.css',
];
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
}
