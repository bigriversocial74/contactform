<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Microgifter Token Network | Microgifter';
$page_section = 'buy-in';
$header_mode = 'public';
$page_body_class = 'mg-token-network-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_manifest = [
    'id' => 'buy-in',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'buy-in', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --mgt-bg:#f7f4ee;
  --mgt-surface:#fffdf9;
  --mgt-card:#ffffff;
  --mgt-ink:#10100e;
  --mgt-muted:#69655e;
  --mgt-line:#e8e1d6;
  --mgt-gold:#d9a83e;
  --mgt-gold-dark:#8d6412;
  --mgt-gold-soft:#fbf1d9;
  --mgt-green:#0d9255;
  --mgt-green-soft:#e8f8ef;
  --mgt-shadow:0 24px 70px rgba(53,43,24,.10);
  --mgt-max:1240px;
}
.mg-token-network-page{background:var(--mgt-bg)!important;color:var(--mgt-ink)}
.mg-token-network-page .mg-main{background:var(--mgt-bg);overflow:hidden}
.mgt-page,.mgt-page *{box-sizing:border-box}
.mgt-page{position:relative;background:
  radial-gradient(circle at 12% 6%,rgba(217,168,62,.18),transparent 24%),
  radial-gradient(circle at 84% 10%,rgba(20,150,90,.10),transparent 22%),
  linear-gradient(180deg,#fbf9f4 0,#f7f4ee 58%,#fbf9f4 100%);
  color:var(--mgt-ink);font-family:Inter,"Helvetica Neue",Arial,sans-serif}
.mgt-full-hero{position:relative;min-height:calc(100vh - 74px);width:100%;display:flex;align-items:stretch;overflow:hidden;background:
  linear-gradient(90deg,rgba(247,244,238,.98) 0%,rgba(247,244,238,.72) 48%,rgba(247,244,238,.38) 100%),
  radial-gradient(circle at 6% 10%,rgba(126,178,209,.30),transparent 24%),
  radial-gradient(circle at 85% 8%,rgba(217,168,62,.30),transparent 35%),
  url('/images/header_gradient_bg.png') center/cover no-repeat}
.mgt-full-hero:before{content:"";position:absolute;inset:0;background:
  linear-gradient(180deg,rgba(255,255,255,.20),rgba(255,255,255,.05) 42%,rgba(247,244,238,.70) 100%),
  repeating-linear-gradient(90deg,rgba(255,255,255,.18) 0 1px,transparent 1px 96px);pointer-events:none}
.mgt-hero-grid{position:relative;z-index:1;width:100%;display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,390px);gap:28px;align-items:center;padding:clamp(28px,3.4vw,54px)}
.mgt-chart-stage{min-width:0}
.mgt-live{display:inline-flex;align-items:center;gap:7px;min-height:26px;padding:0 10px;border-radius:999px;background:var(--mgt-green-soft);color:#0b7a46;font-size:9px;font-weight:950;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}
.mgt-live:before{content:"";width:7px;height:7px;border-radius:50%;background:#20b56c;box-shadow:0 0 0 4px rgba(32,181,108,.13)}
.mgt-hero-title{margin:22px 0 0;color:#111;font-size:clamp(28px,3vw,46px);line-height:.95;letter-spacing:-.055em;font-weight:930}
.mgt-hero-value{margin-top:18px}
.mgt-value-label{display:block;color:#5e5a53;font-size:10px;font-weight:950;letter-spacing:.09em;text-transform:uppercase}
.mgt-value{display:block;margin-top:8px;color:#050505;font-size:clamp(42px,5vw,76px);line-height:.86;letter-spacing:-.065em;font-weight:950}
.mgt-change{display:block;margin-top:9px;color:var(--mgt-green);font-size:12px;font-weight:950}
.mgt-hero-chart-wrap{position:relative;margin-top:24px;min-height:410px}
.mgt-periods{position:absolute;right:1%;top:-42px;display:flex;gap:7px;align-items:center;z-index:2}
.mgt-periods button{min-width:36px;height:30px;border:1px solid transparent;border-radius:999px;background:rgba(255,255,255,.54);color:#5d584f;font-size:9px;font-weight:950;cursor:pointer}
.mgt-periods button.is-active{border-color:rgba(217,168,62,.40);background:var(--mgt-gold-soft);color:#6e500e}
.mgt-main-chart{display:block;width:100%;height:auto;min-height:390px;overflow:visible}
.mgt-chart-grid line{stroke:#ded7cc;stroke-width:1}
.mgt-chart-axis{fill:#6c675e;font-size:10px;font-weight:800}
.mgt-area{fill:url(#mgtHeroArea)}
.mgt-line{fill:none;stroke:var(--mgt-gold);stroke-width:4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 5px 10px rgba(217,168,62,.20))}
.mgt-chart-dot{fill:#fff;stroke:var(--mgt-gold);stroke-width:4}
.mgt-chart-pill{position:absolute;right:.6%;top:16%;padding:7px 10px;border-radius:10px;background:var(--mgt-gold);color:#fff;font-size:11px;font-weight:950;box-shadow:0 12px 26px rgba(217,168,62,.28)}
.mgt-hero-stat-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));margin-top:18px;border:1px solid rgba(232,225,214,.86);border-radius:18px;background:rgba(255,255,255,.72);box-shadow:0 20px 54px rgba(53,43,24,.08);backdrop-filter:blur(14px);overflow:hidden}
.mgt-hero-stat{min-height:126px;padding:24px 20px;border-right:1px solid rgba(232,225,214,.86)}
.mgt-hero-stat:last-child{border-right:0}
.mgt-hero-stat span{display:block;min-height:26px;color:#6d685f;font-size:9px;line-height:1.25;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.mgt-hero-stat strong{display:block;margin-top:13px;color:#111;font-size:26px;line-height:.96;font-weight:950;letter-spacing:-.04em}
.mgt-hero-stat em{display:block;margin-top:9px;color:var(--mgt-green);font-size:10px;line-height:1.25;font-style:normal;font-weight:900}
.mgt-participation{padding:22px;border:1px solid rgba(232,225,214,.95);border-radius:22px;background:rgba(255,255,255,.86);box-shadow:0 24px 70px rgba(53,43,24,.13);backdrop-filter:blur(18px)}
.mgt-form-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.mgt-form-top h2{max-width:230px;margin:0;font-size:16px;line-height:1.1;letter-spacing:-.035em;font-weight:950}
.mgt-label{display:block;margin:18px 0 8px;color:#4f4b44;font-size:9px;font-weight:950;letter-spacing:.11em;text-transform:uppercase}
.mgt-input-row{display:grid;grid-template-columns:82px minmax(0,1fr);gap:8px}
.mgt-select,.mgt-input{height:48px;border:1px solid var(--mgt-line);border-radius:11px;background:#fff;color:var(--mgt-ink);font:inherit;font-size:13px;font-weight:850;outline:none}
.mgt-select{padding:0 10px}
.mgt-input{width:100%;padding:0 13px}
.mgt-input:focus,.mgt-select:focus{border-color:rgba(217,168,62,.70);box-shadow:0 0 0 4px rgba(217,168,62,.12)}
.mgt-rate{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:8px;color:var(--mgt-muted);font-size:9px;font-weight:780}
.mgt-rate strong{color:var(--mgt-green);font-weight:950}
.mgt-allocation{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}
.mgt-allocation button{min-height:39px;border:1px solid var(--mgt-line);border-radius:11px;background:#fff;color:#2b2925;font-size:10px;font-weight:950;cursor:pointer;transition:.18s ease}
.mgt-allocation button:hover,.mgt-allocation button.is-active{border-color:rgba(217,168,62,.62);background:var(--mgt-gold-soft);color:#6e500e;transform:translateY(-1px)}
.mgt-summary{display:grid;gap:8px;margin-top:16px;padding:14px;border-radius:14px;background:#f7f3ec}
.mgt-summary-row{display:flex;align-items:center;justify-content:space-between;gap:18px;color:#5b564e;font-size:10px;font-weight:780}
.mgt-summary-row strong{color:#151411;font-size:11px;font-weight:950}
.mgt-benefits{display:grid;gap:10px;margin-top:16px}
.mgt-benefit{display:grid;grid-template-columns:30px 1fr;gap:10px;align-items:start}
.mgt-benefit-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--mgt-gold-soft);color:var(--mgt-gold-dark);font-size:12px;font-weight:950}
.mgt-benefit strong{display:block;color:#211f1c;font-size:11px;font-weight:950}
.mgt-benefit span{display:block;margin-top:3px;color:var(--mgt-muted);font-size:9.5px;line-height:1.38;font-weight:650}
.mgt-primary-btn,.mgt-secondary-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;padding:0 18px;border-radius:12px;text-decoration:none!important;font-size:12px;font-weight:950;transition:.18s ease}
.mgt-primary-btn{border:1px solid #111;background:#111;color:#fff!important;box-shadow:0 14px 30px rgba(17,17,17,.15)}
.mgt-primary-btn:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(17,17,17,.20)}
.mgt-secondary-btn{border:1px solid var(--mgt-line);background:#fff;color:#161512!important}
.mgt-form-submit{width:100%;margin-top:18px}
.mgt-secure{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:13px;color:#77736b;font-size:9px;font-weight:800}
.mgt-shell{width:min(var(--mgt-max),calc(100% - 48px));margin:0 auto;padding:72px 0 92px}
.mgt-trust{display:grid;grid-template-columns:auto repeat(4,minmax(0,1fr));gap:12px;align-items:center;padding:14px;border:1px solid var(--mgt-line);border-radius:18px;background:rgba(255,255,255,.72);box-shadow:0 14px 48px rgba(53,43,24,.045)}
.mgt-trust-label{padding:0 9px;color:#77736b;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}
.mgt-trust-item{display:flex;align-items:center;gap:10px;min-height:48px;padding:0 12px;border-left:1px solid var(--mgt-line);color:#37342f;font-size:10px;font-weight:850}
.mgt-trust-icon{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border-radius:9px;background:#f4efe6;color:#7c5a13;font-size:12px}
.mgt-section{margin-top:72px}
.mgt-section-head{display:flex;align-items:end;justify-content:space-between;gap:28px;margin-bottom:26px}
.mgt-section-head>div{max-width:720px}
.mgt-kicker{display:block;color:var(--mgt-gold-dark);font-size:10px;font-weight:950;letter-spacing:.13em;text-transform:uppercase}
.mgt-section-title{margin:10px 0 0;color:var(--mgt-ink);font-size:clamp(34px,4vw,54px);line-height:.96;letter-spacing:-.06em;font-weight:950;text-wrap:balance}
.mgt-section-copy{max-width:640px;margin:14px 0 0;color:#5b5750;font-size:15px;line-height:1.5;font-weight:560}
.mgt-overview-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.mgt-metric-card{min-height:224px;padding:20px;border:1px solid var(--mgt-line);border-radius:18px;background:rgba(255,255,255,.82);box-shadow:0 12px 38px rgba(53,43,24,.045)}
.mgt-metric-card span{display:block;min-height:30px;color:var(--mgt-muted);font-size:9px;line-height:1.35;font-weight:950;letter-spacing:.06em;text-transform:uppercase}
.mgt-metric-card strong{display:block;margin-top:11px;color:#171612;font-size:26px;line-height:.95;letter-spacing:-.045em;font-weight:950}
.mgt-metric-card em{display:block;margin-top:8px;color:var(--mgt-green);font-style:normal;font-size:9px;font-weight:950}
.mgt-spark{display:block;width:100%;height:76px;margin-top:22px}
.mgt-spark-grid{stroke:#eee9e1;stroke-width:1}
.mgt-spark-line{fill:none;stroke:var(--mgt-green);stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}
.mgt-spark-line.gold{stroke:var(--mgt-gold)}
.mgt-spark-line.gray{stroke:#8b8984}
.mgt-reasons{padding:30px;border:1px solid var(--mgt-line);border-radius:22px;background:rgba(255,255,255,.74)}
.mgt-reason-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin-top:28px}
.mgt-reason{min-height:198px;padding:4px 26px 8px;border-left:1px solid var(--mgt-line)}
.mgt-reason:first-child{border-left:0;padding-left:0}
.mgt-reason:last-child{padding-right:0}
.mgt-reason-icon{display:grid;place-items:center;width:46px;height:46px;border:1px solid var(--mgt-line);border-radius:50%;background:#fff;color:#151411;font-size:17px;font-weight:950}
.mgt-reason h3{margin:22px 0 0;color:#1b1a17;font-size:17px;line-height:1.05;letter-spacing:-.035em;font-weight:950}
.mgt-reason p{margin:12px 0 0;color:#68645d;font-size:12px;line-height:1.5;font-weight:600}
.mgt-performance-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.55fr);gap:14px;align-items:stretch}
.mgt-performance-card,.mgt-side-card{border:1px solid var(--mgt-line);border-radius:22px;background:rgba(255,255,255,.84);box-shadow:0 14px 46px rgba(53,43,24,.045)}
.mgt-performance-card{padding:26px}
.mgt-performance-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.mgt-performance-top h3{margin:0;color:#171612;font-size:25px;line-height:1;letter-spacing:-.05em;font-weight:950}
.mgt-performance-top p{max-width:500px;margin:11px 0 0;color:#68645d;font-size:12px;line-height:1.48;font-weight:600}
.mgt-token-price{text-align:right}
.mgt-token-price strong{display:block;font-size:31px;line-height:.9;letter-spacing:-.045em;font-weight:950}
.mgt-token-price span{display:block;margin-top:8px;color:var(--mgt-green);font-size:10px;font-weight:950}
.mgt-token-chart{margin-top:20px;padding:16px 0 4px;border-top:1px solid var(--mgt-line);border-bottom:1px solid var(--mgt-line)}
.mgt-token-chart svg{display:block;width:100%;height:auto}
.mgt-token-grid line{stroke:#ebe6de;stroke-width:1}
.mgt-token-area{fill:url(#mgtTokenArea)}
.mgt-token-line{fill:none;stroke:var(--mgt-gold);stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.mgt-token-dot{fill:#fff;stroke:var(--mgt-gold);stroke-width:3}
.mgt-token-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}
.mgt-token-stat span{display:block;color:var(--mgt-muted);font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.06em}
.mgt-token-stat strong{display:block;margin-top:8px;color:#1b1a17;font-size:17px;line-height:1;font-weight:950}
.mgt-performance-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:22px}
.mgt-side-stack{display:grid;grid-template-rows:auto auto;gap:14px}
.mgt-side-card{padding:22px}
.mgt-side-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--mgt-line)}
.mgt-side-head h3{margin:0;font-size:16px;letter-spacing:-.035em;font-weight:950}
.mgt-side-head a{color:#77736b;font-size:9px;font-weight:950;text-decoration:none}
.mgt-stat-list-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:10px;align-items:center;min-height:49px;border-bottom:1px solid #eee9e1}
.mgt-stat-list-row:last-child{border-bottom:0}
.mgt-stat-list-row b{display:grid;place-items:center;width:26px;height:26px;border-radius:9px;background:var(--mgt-gold-soft);color:var(--mgt-gold-dark);font-size:11px}
.mgt-stat-list-row span{color:#5e5a53;font-size:9px;line-height:1.25;font-weight:800}
.mgt-stat-list-row strong{color:#1a1916;font-size:10px;font-weight:950;text-align:right}
.mgt-network-info{display:grid;gap:11px;margin-top:15px}
.mgt-network-info-row{display:flex;align-items:center;justify-content:space-between;gap:16px;color:#68645d;font-size:10px;font-weight:760}
.mgt-network-info-row strong{color:#1b1a17;font-weight:950;text-align:right}
.mgt-distribution{overflow:hidden;border:1px solid var(--mgt-line);border-radius:22px;background:rgba(255,255,255,.82)}
.mgt-distribution-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 24px;border-bottom:1px solid var(--mgt-line)}
.mgt-distribution-head h3{margin:0;font-size:20px;letter-spacing:-.045em;font-weight:950}
.mgt-distribution-head span{color:#77736b;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}
.mgt-table{width:100%;border-collapse:collapse}
.mgt-table th,.mgt-table td{padding:15px 20px;border-bottom:1px solid #eee9e1;text-align:left}
.mgt-table th{color:#77736b;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.07em}
.mgt-table td{color:#44413b;font-size:11px;font-weight:730}
.mgt-table td strong{display:block;color:#1b1a17;font-size:12px;font-weight:950}
.mgt-table tbody tr:last-child td{border-bottom:0}
.mgt-table .positive{color:var(--mgt-green);font-weight:950}
.mgt-row-meter{width:100%;height:7px;border-radius:999px;background:#eee9e1;overflow:hidden}
.mgt-row-meter span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--mgt-gold),#e7c66e)}
.mgt-cta{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.65fr);gap:24px;align-items:center;margin-top:72px;padding:38px;border:1px solid var(--mgt-line);border-radius:26px;background:
  radial-gradient(circle at 85% 18%,rgba(217,168,62,.17),transparent 30%),
  linear-gradient(135deg,#fffdfa,#f6f0e6);box-shadow:var(--mgt-shadow)}
.mgt-cta h2{max-width:700px;margin:0;color:#141310;font-size:clamp(34px,4vw,56px);line-height:.95;letter-spacing:-.065em;font-weight:950;text-wrap:balance}
.mgt-cta p{max-width:650px;margin:18px 0 0;color:#5c5851;font-size:14px;line-height:1.5;font-weight:580}
.mgt-cta-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.mgt-cta-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mgt-cta-card{min-height:188px;padding:22px;border:1px solid rgba(0,0,0,.07);border-radius:18px;background:rgba(255,255,255,.78)}
.mgt-cta-card b{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:#111;color:#fff;font-size:14px}
.mgt-cta-card h3{margin:22px 0 0;font-size:15px;font-weight:950;letter-spacing:-.03em}
.mgt-cta-card p{margin:9px 0 0;color:#6a665f;font-size:10px;line-height:1.45;font-weight:650}
.mgt-cta-card a{display:inline-flex;margin-top:15px;color:#151411;font-size:10px;font-weight:950;text-decoration:none}
@media(max-width:1120px){
  .mgt-hero-grid{grid-template-columns:1fr;padding:30px 22px}
  .mgt-full-hero{min-height:auto}
  .mgt-participation{max-width:520px;margin-left:auto;margin-right:auto}
  .mgt-hero-stat-strip{grid-template-columns:repeat(3,minmax(0,1fr))}
  .mgt-hero-stat:nth-child(3n){border-right:0}
  .mgt-hero-stat:nth-child(n+4){border-top:1px solid rgba(232,225,214,.86)}
  .mgt-overview-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .mgt-performance-layout{grid-template-columns:1fr}
  .mgt-side-stack{grid-template-columns:1fr 1fr;grid-template-rows:auto}
  .mgt-trust{grid-template-columns:1fr 1fr}
  .mgt-trust-label{grid-column:1/-1}
  .mgt-trust-item{border-left:0}
}
@media(max-width:820px){
  .mgt-shell{width:min(var(--mgt-max),calc(100% - 28px));padding-top:54px}
  .mgt-hero-chart-wrap{min-height:auto;margin-top:70px}
  .mgt-periods{left:0;right:auto;top:-50px}
  .mgt-hero-stat-strip,.mgt-overview-grid{grid-template-columns:1fr 1fr}
  .mgt-reason-grid{grid-template-columns:1fr 1fr;gap:22px}
  .mgt-reason{border-left:0;border-top:1px solid var(--mgt-line);padding:22px 0 0}
  .mgt-reason:nth-child(-n+2){border-top:0;padding-top:0}
  .mgt-token-stats{grid-template-columns:1fr 1fr}
  .mgt-cta{grid-template-columns:1fr;padding:28px}
  .mgt-table{min-width:720px}
  .mgt-distribution{overflow-x:auto}
}
@media(max-width:560px){
  .mgt-hero-grid{padding:22px 14px 30px}
  .mgt-hero-title{font-size:30px}
  .mgt-value{font-size:46px}
  .mgt-input-row{grid-template-columns:86px minmax(0,1fr)}
  .mgt-allocation{grid-template-columns:1fr 1fr}
  .mgt-hero-stat-strip,.mgt-overview-grid,.mgt-side-stack,.mgt-cta-cards{grid-template-columns:1fr}
  .mgt-hero-stat{border-right:0!important;border-top:1px solid rgba(232,225,214,.86)}
  .mgt-hero-stat:first-child{border-top:0}
  .mgt-reason-grid{grid-template-columns:1fr}
  .mgt-reason:nth-child(2){border-top:1px solid var(--mgt-line);padding-top:22px}
  .mgt-performance-top{display:grid}
  .mgt-token-price{text-align:left}
  .mgt-performance-actions{grid-template-columns:1fr}
  .mgt-trust{grid-template-columns:1fr}
  .mgt-cta{padding:24px 20px}
}
</style>

<main class="mgt-page">
  <section class="mgt-full-hero" aria-labelledby="mgt-network-title">
    <div class="mgt-hero-grid">
      <div class="mgt-chart-stage">
        <span class="mgt-live">Live system</span>
        <h1 class="mgt-hero-title" id="mgt-network-title">Microgifter Token Network</h1>
        <div class="mgt-hero-value">
          <span class="mgt-value-label">Total system value</span>
          <strong class="mgt-value" id="mgtSystemValue">$23.04M</strong>
          <span class="mgt-change" id="mgtSystemChange">↑ 28.47% over 30 days</span>
        </div>

        <div class="mgt-hero-chart-wrap" aria-label="Microgifter token network chart">
          <div class="mgt-periods" aria-label="Chart period">
            <button type="button" data-period="7D">7D</button>
            <button class="is-active" type="button" data-period="30D">30D</button>
            <button type="button" data-period="90D">90D</button>
            <button type="button" data-period="1Y">1Y</button>
            <button type="button" data-period="ALL">All</button>
          </div>
          <svg class="mgt-main-chart" viewBox="0 0 900 430" role="img" aria-labelledby="mgtChartTitle mgtChartDesc">
            <title id="mgtChartTitle">Microgifter token network value</title>
            <desc id="mgtChartDesc">A light-background line chart showing the overall Microgifter system value increasing over time.</desc>
            <defs>
              <linearGradient id="mgtHeroArea" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#d9a83e" stop-opacity=".30"/>
                <stop offset="100%" stop-color="#d9a83e" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <g class="mgt-chart-grid">
              <line x1="74" y1="52" x2="872" y2="52"/>
              <line x1="74" y1="120" x2="872" y2="120"/>
              <line x1="74" y1="188" x2="872" y2="188"/>
              <line x1="74" y1="256" x2="872" y2="256"/>
              <line x1="74" y1="324" x2="872" y2="324"/>
            </g>
            <g class="mgt-chart-axis">
              <text x="14" y="56">$25M</text><text x="14" y="124">$20M</text><text x="14" y="192">$15M</text><text x="14" y="260">$10M</text><text x="14" y="328">$5M</text>
              <text x="74" y="385">May 3</text><text x="230" y="385">May 10</text><text x="386" y="385">May 17</text><text x="542" y="385">May 24</text><text x="698" y="385">May 31</text><text x="824" y="385">Jun 6</text>
            </g>
            <path class="mgt-area" id="mgtMainArea" d="M74 324 L110 304 L146 313 L182 278 L218 292 L254 258 L290 252 L326 218 L362 224 L398 188 L434 205 L470 222 L506 184 L542 174 L578 138 L614 142 L650 104 L686 112 L722 78 L758 89 L794 48 L830 96 L866 72 L872 75 L872 342 L74 342 Z"/>
            <path class="mgt-line" id="mgtMainLine" d="M74 324 L110 304 L146 313 L182 278 L218 292 L254 258 L290 252 L326 218 L362 224 L398 188 L434 205 L470 222 L506 184 L542 174 L578 138 L614 142 L650 104 L686 112 L722 78 L758 89 L794 48 L830 96 L866 72 L872 75"/>
            <circle class="mgt-chart-dot" id="mgtMainDot" cx="872" cy="75" r="6"/>
          </svg>
          <span class="mgt-chart-pill" id="mgtChartPill">$23.04M</span>
        </div>

        <div class="mgt-hero-stat-strip">
          <div class="mgt-hero-stat"><span>Total Microgifter system value</span><strong>$23.04M</strong><em>↑ 28.47% vs. 30D</em></div>
          <div class="mgt-hero-stat"><span>Circulating supply</span><strong>28.45M</strong><em>MGF across the system</em></div>
          <div class="mgt-hero-stat"><span>Distributed</span><strong>17.32M</strong><em>MGF sent to participants</em></div>
          <div class="mgt-hero-stat"><span>Redeemed</span><strong>6.84M</strong><em>MGF converted to utility</em></div>
          <div class="mgt-hero-stat"><span>Total token volume</span><strong>$1.92M</strong><em>30-day average activity</em></div>
          <div class="mgt-hero-stat"><span>Ecosystem growth</span><strong>+28.47%</strong><em>Compound rate prior 30D</em></div>
        </div>
      </div>

      <form class="mgt-participation" id="mgtParticipationForm" action="/signup.php" method="get">
        <div class="mgt-form-top">
          <h2>Participate in the Microgifter ecosystem</h2>
          <span class="mgt-live">Network active</span>
        </div>

        <label class="mgt-label" for="mgtSendAmount">You contribute</label>
        <div class="mgt-input-row">
          <select class="mgt-select" id="mgtSendAsset" aria-label="Contribution asset">
            <option value="USD">USD</option>
            <option value="USDC">USDC</option>
          </select>
          <input class="mgt-input" id="mgtSendAmount" name="amount" type="number" min="10" step="10" value="1000" inputmode="decimal">
        </div>

        <label class="mgt-label" for="mgtReceiveAmount">Estimated MGF allocation</label>
        <div class="mgt-input-row">
          <select class="mgt-select" aria-label="Microgifter token" disabled>
            <option>MGF</option>
          </select>
          <input class="mgt-input" id="mgtReceiveAmount" type="text" value="1,234.57" readonly>
        </div>
        <div class="mgt-rate"><span>Illustrative rate: 1 MGF = $0.810</span><strong>Early network access</strong></div>

        <span class="mgt-label">Choose an allocation</span>
        <div class="mgt-allocation" aria-label="Quick allocation amounts">
          <button type="button" data-amount="250">$250</button>
          <button class="is-active" type="button" data-amount="1000">$1,000</button>
          <button type="button" data-amount="5000">$5,000</button>
          <button type="button" data-amount="10000">$10,000</button>
        </div>

        <div class="mgt-summary" aria-live="polite">
          <div class="mgt-summary-row"><span>Estimated allocation</span><strong id="mgtSummaryTokens">1,234.57 MGF</strong></div>
          <div class="mgt-summary-row"><span>Share of circulating supply</span><strong id="mgtSummaryShare">0.0043%</strong></div>
          <div class="mgt-summary-row"><span>Current system value</span><strong>$23.04M</strong></div>
        </div>

        <div class="mgt-benefits">
          <div class="mgt-benefit"><span class="mgt-benefit-icon">✓</span><div><strong>Participate in ecosystem growth</strong><span>Support real local activity, distribution, redemption, and local commerce impact.</span></div></div>
          <div class="mgt-benefit"><span class="mgt-benefit-icon">✓</span><div><strong>Transparent system metrics</strong><span>See the full network balance sheet, value recirculation, and compute charts.</span></div></div>
          <div class="mgt-benefit"><span class="mgt-benefit-icon">✓</span><div><strong>Built for local impact</strong><span>Empowering creators, merchants, communities, and mission-driven projects.</span></div></div>
        </div>

        <button class="mgt-primary-btn mgt-form-submit" type="submit">Join the Microgifter Ecosystem <span>→</span></button>
        <div class="mgt-secure"><span>Secure access</span><span>•</span><span>Transparent metrics</span><span>•</span><span>Built for local impact</span></div>
      </form>
    </div>
  </section>

  <div class="mgt-shell">
    <section class="mgt-trust" aria-label="Microgifter network foundations">
      <span class="mgt-trust-label">Built around real platform activity</span>
      <div class="mgt-trust-item"><span class="mgt-trust-icon">◎</span><span>Local commerce</span></div>
      <div class="mgt-trust-item"><span class="mgt-trust-icon">↗</span><span>Creator distribution</span></div>
      <div class="mgt-trust-item"><span class="mgt-trust-icon">✓</span><span>Tracked redemption</span></div>
      <div class="mgt-trust-item"><span class="mgt-trust-icon">◇</span><span>Transparent circulation</span></div>
    </section>

    <section class="mgt-section" id="ecosystem" aria-labelledby="ecosystem-title">
      <div class="mgt-section-head">
        <div>
          <span class="mgt-kicker">Microgifter ecosystem overview</span>
          <h2 class="mgt-section-title" id="ecosystem-title">One chart for the value moving through the entire network.</h2>
          <p class="mgt-section-copy">The dashboard measures Microgifter circulation across every creator, merchant, reward, gift, and redemption—not the performance of one individual market.</p>
        </div>
      </div>

      <div class="mgt-overview-grid">
        <article class="mgt-metric-card">
          <span>Total system value</span><strong>$23.04M</strong><em>↑ 28.47% in 30D</em>
          <svg class="mgt-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgt-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgt-spark-line gold" d="M2 60 L18 54 L34 58 L50 43 L66 47 L82 35 L98 39 L114 25 L130 31 L146 15 L162 20 L178 5"/></svg>
        </article>
        <article class="mgt-metric-card">
          <span>Total Microgifter circulation</span><strong>28.45M</strong><em>MGF across all uses</em>
          <svg class="mgt-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgt-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgt-spark-line" d="M2 62 L18 52 L34 55 L50 46 L66 50 L82 38 L98 33 L114 36 L130 24 L146 28 L162 16 L178 8"/></svg>
        </article>
        <article class="mgt-metric-card">
          <span>Distributed to participants</span><strong>17.32M</strong><em>60.88% of circulation</em>
          <svg class="mgt-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgt-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgt-spark-line" d="M2 61 L18 58 L34 49 L50 53 L66 43 L82 46 L98 34 L114 38 L130 27 L146 22 L162 13 L178 6"/></svg>
        </article>
        <article class="mgt-metric-card">
          <span>Redeemed into utility</span><strong>6.84M</strong><em>24.05% of circulation</em>
          <svg class="mgt-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgt-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgt-spark-line gray" d="M2 63 L18 56 L34 58 L50 48 L66 50 L82 40 L98 43 L114 32 L130 35 L146 22 L162 18 L178 8"/></svg>
        </article>
        <article class="mgt-metric-card">
          <span>Creator and merchant payouts</span><strong>$1.25M</strong><em>Delivered through the network</em>
          <svg class="mgt-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgt-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgt-spark-line gold" d="M2 64 L18 62 L34 53 L50 56 L66 44 L82 47 L98 36 L114 31 L130 34 L146 20 L162 14 L178 4"/></svg>
        </article>
      </div>
    </section>

    <section class="mgt-section mgt-reasons" aria-labelledby="why-title">
      <span class="mgt-kicker">Why the network matters</span>
      <h2 class="mgt-section-title" id="why-title">A token system connected to measurable local activity.</h2>
      <div class="mgt-reason-grid">
        <article class="mgt-reason"><span class="mgt-reason-icon">◎</span><h3>Real local impact</h3><p>Distribution supports merchants, creators, events, hospitality businesses, and community programs.</p></article>
        <article class="mgt-reason"><span class="mgt-reason-icon">↗</span><h3>Network growth</h3><p>Circulation expands as more gifts, transactions, partners, and automated revenue programs launch.</p></article>
        <article class="mgt-reason"><span class="mgt-reason-icon">✓</span><h3>Redemption visibility</h3><p>Microgifter tracks when value moves from distribution into claimed products, offers, and experiences.</p></article>
        <article class="mgt-reason"><span class="mgt-reason-icon">◇</span><h3>System transparency</h3><p>Investors and participants see ecosystem token flow and performance in a single system dashboard.</p></article>
      </div>
    </section>

    <section class="mgt-section" id="performance" aria-labelledby="performance-title">
      <div class="mgt-performance-layout">
        <article class="mgt-performance-card">
          <div class="mgt-performance-top">
            <div>
              <span class="mgt-kicker">System performance</span>
              <h3 id="performance-title">Microgifter Token (MGF)</h3>
              <p>The illustrative token value reflects overall circulation, distribution, redemption, transaction volume, and network participation across Microgifter.</p>
            </div>
            <div class="mgt-token-price"><strong>$0.810</strong><span>↑ 28.47% in 30D</span></div>
          </div>

          <div class="mgt-token-chart" aria-label="Microgifter token value chart">
            <svg viewBox="0 0 760 280" role="img" aria-labelledby="mgtTokenTitle mgtTokenDesc">
              <title id="mgtTokenTitle">MGF token value trend</title>
              <desc id="mgtTokenDesc">A light line chart showing the illustrative MGF value increasing from forty-two cents to eighty-one cents.</desc>
              <defs><linearGradient id="mgtTokenArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#d9a83e" stop-opacity=".24"/><stop offset="100%" stop-color="#d9a83e" stop-opacity="0"/></linearGradient></defs>
              <g class="mgt-token-grid"><line x1="58" y1="28" x2="742" y2="28"/><line x1="58" y1="86" x2="742" y2="86"/><line x1="58" y1="144" x2="742" y2="144"/><line x1="58" y1="202" x2="742" y2="202"/></g>
              <g class="mgt-chart-axis"><text x="7" y="32">$1.00</text><text x="7" y="90">$0.75</text><text x="7" y="148">$0.50</text><text x="7" y="206">$0.25</text><text x="62" y="258">May 8</text><text x="222" y="258">May 15</text><text x="382" y="258">May 22</text><text x="542" y="258">May 29</text><text x="684" y="258">Jun 5</text></g>
              <path class="mgt-token-area" d="M58 188 L92 176 L126 181 L160 166 L194 170 L228 152 L262 157 L296 139 L330 144 L364 126 L398 120 L432 124 L466 109 L500 114 L534 92 L568 99 L602 76 L636 83 L670 57 L704 42 L738 46 L738 218 L58 218 Z"/>
              <path class="mgt-token-line" d="M58 188 L92 176 L126 181 L160 166 L194 170 L228 152 L262 157 L296 139 L330 144 L364 126 L398 120 L432 124 L466 109 L500 114 L534 92 L568 99 L602 76 L636 83 L670 57 L704 42 L738 46"/>
              <circle class="mgt-token-dot" cx="738" cy="46" r="5"/>
            </svg>
          </div>

          <div class="mgt-token-stats">
            <div class="mgt-token-stat"><span>Total system value</span><strong>$23.04M</strong></div>
            <div class="mgt-token-stat"><span>Circulating supply</span><strong>28.45M MGF</strong></div>
            <div class="mgt-token-stat"><span>Distributed supply</span><strong>17.32M MGF</strong></div>
            <div class="mgt-token-stat"><span>30-day volume</span><strong>$1.92M</strong></div>
          </div>
          <div class="mgt-performance-actions">
            <a class="mgt-secondary-btn" href="#distribution">View System Distribution</a>
            <a class="mgt-primary-btn" href="/signup.php">Participate in MGF <span>→</span></a>
          </div>
        </article>

        <div class="mgt-side-stack">
          <aside class="mgt-side-card">
            <div class="mgt-side-head"><h3>System stats</h3><a href="#ecosystem">View overview</a></div>
            <div class="mgt-stat-list">
              <div class="mgt-stat-list-row"><b>◎</b><span>Total circulation</span><strong>28.45M MGF</strong></div>
              <div class="mgt-stat-list-row"><b>↗</b><span>Distributed</span><strong>17.32M MGF</strong></div>
              <div class="mgt-stat-list-row"><b>✓</b><span>Redeemed</span><strong>6.84M MGF</strong></div>
              <div class="mgt-stat-list-row"><b>◇</b><span>Active holders</span><strong>8,921</strong></div>
              <div class="mgt-stat-list-row"><b>$</b><span>Creator payouts</span><strong>$1.25M</strong></div>
              <div class="mgt-stat-list-row"><b>+</b><span>30-day growth</span><strong>+28.47%</strong></div>
            </div>
          </aside>
          <aside class="mgt-side-card">
            <div class="mgt-side-head"><h3>Network scope</h3><span></span></div>
            <div class="mgt-network-info">
              <div class="mgt-network-info-row"><span>Token</span><strong>MGF</strong></div>
              <div class="mgt-network-info-row"><span>System</span><strong>Microgifter Network</strong></div>
              <div class="mgt-network-info-row"><span>Primary utility</span><strong>Gifts, rewards, redemption</strong></div>
              <div class="mgt-network-info-row"><span>Reporting scope</span><strong>All markets and campaigns</strong></div>
              <div class="mgt-network-info-row"><span>Data status</span><strong>Illustrative platform model</strong></div>
            </div>
          </aside>
        </div>
      </div>
    </section>

    <section class="mgt-section mgt-distribution" id="distribution" aria-labelledby="distribution-title">
      <div class="mgt-distribution-head"><h3 id="distribution-title">Microgifter system distribution</h3><span>Network-wide token activity</span></div>
      <table class="mgt-table">
        <thead><tr><th>System category</th><th>MGF volume</th><th>% of circulation</th><th>30D change</th><th>Distribution</th></tr></thead>
        <tbody>
          <tr><td><strong>Community and supporter holdings</strong>Tokens held across participant accounts</td><td>10.48M MGF</td><td>36.84%</td><td class="positive">+18.2%</td><td><div class="mgt-row-meter"><span style="width:76%"></span></div></td></tr>
          <tr><td><strong>Creator and merchant distribution</strong>Tokens issued through offers and campaigns</td><td>6.84M MGF</td><td>24.04%</td><td class="positive">+14.6%</td><td><div class="mgt-row-meter"><span style="width:61%"></span></div></td></tr>
          <tr><td><strong>Redeemed ecosystem utility</strong>Tokens converted into products and experiences</td><td>6.84M MGF</td><td>24.05%</td><td class="positive">+10.1%</td><td><div class="mgt-row-meter"><span style="width:53%"></span></div></td></tr>
          <tr><td><strong>Network reserves</strong>Available for future distribution programs</td><td>4.29M MGF</td><td>15.07%</td><td class="positive">+4.8%</td><td><div class="mgt-row-meter"><span style="width:38%"></span></div></td></tr>
        </tbody>
      </table>
    </section>

    <section class="mgt-cta" aria-labelledby="mgt-cta-title">
      <div>
        <span class="mgt-kicker">Build the future of local value</span>
        <h2 id="mgt-cta-title">Invest. Create. Grow together.</h2>
        <p>Join a network designed to turn local support, promotional demand, gifting, rewards, and redemption into measurable ecosystem value.</p>
        <div class="mgt-cta-actions"><a class="mgt-primary-btn" href="/signup.php">Join the Ecosystem <span>→</span></a><a class="mgt-secondary-btn" href="/learn-more.php">Book A Demo</a></div>
      </div>
      <div class="mgt-cta-cards">
        <article class="mgt-cta-card"><b>◎</b><h3>For communities</h3><p>Launch rewards, gifting, fundraising, and local engagement programs tied to visible network activity.</p><a href="/signup.php">Create a program →</a></article>
        <article class="mgt-cta-card"><b>↗</b><h3>For participants</h3><p>Track system circulation, distribution, redemption, and growth through one unified Microgifter dashboard.</p><a href="/signup.php">Participate now →</a></article>
      </div>
    </section>
  </div>
</main>

<script>
(function(){
  'use strict';
  const rate = 0.81;
  const circulation = 28450000;
  const amountInput = document.getElementById('mgtSendAmount');
  const receiveInput = document.getElementById('mgtReceiveAmount');
  const summaryTokens = document.getElementById('mgtSummaryTokens');
  const summaryShare = document.getElementById('mgtSummaryShare');
  const allocationButtons = Array.from(document.querySelectorAll('.mgt-allocation button'));

  function formatNumber(value, digits){
    return Number(value).toLocaleString('en-US',{minimumFractionDigits:digits,maximumFractionDigits:digits});
  }

  function updateAllocation(){
    const amount = Math.max(0, Number(amountInput.value) || 0);
    const tokens = amount / rate;
    const share = circulation ? (tokens / circulation) * 100 : 0;
    receiveInput.value = formatNumber(tokens, 2);
    summaryTokens.textContent = formatNumber(tokens, 2) + ' MGF';
    summaryShare.textContent = share.toFixed(4) + '%';
    allocationButtons.forEach(function(button){
      button.classList.toggle('is-active', Number(button.dataset.amount) === amount);
    });
  }

  allocationButtons.forEach(function(button){
    button.addEventListener('click', function(){
      amountInput.value = button.dataset.amount || '1000';
      updateAllocation();
    });
  });
  amountInput.addEventListener('input', updateAllocation);
  updateAllocation();

  const chartData = {
    '7D': {value:'$23.04M',change:'↑ 8.16% over 7 days',pill:'$23.04M',line:'M74 302 L154 280 L234 288 L314 245 L394 252 L474 200 L554 212 L634 156 L714 166 L794 96 L872 75',area:'M74 302 L154 280 L234 288 L314 245 L394 252 L474 200 L554 212 L634 156 L714 166 L794 96 L872 75 L872 342 L74 342 Z',dotY:'75'},
    '30D': {value:'$23.04M',change:'↑ 28.47% over 30 days',pill:'$23.04M',line:'M74 324 L110 304 L146 313 L182 278 L218 292 L254 258 L290 252 L326 218 L362 224 L398 188 L434 205 L470 222 L506 184 L542 174 L578 138 L614 142 L650 104 L686 112 L722 78 L758 89 L794 48 L830 96 L866 72 L872 75',area:'M74 324 L110 304 L146 313 L182 278 L218 292 L254 258 L290 252 L326 218 L362 224 L398 188 L434 205 L470 222 L506 184 L542 174 L578 138 L614 142 L650 104 L686 112 L722 78 L758 89 L794 48 L830 96 L866 72 L872 75 L872 342 L74 342 Z',dotY:'75'},
    '90D': {value:'$23.04M',change:'↑ 46.31% over 90 days',pill:'$23.04M',line:'M74 336 L136 327 L198 306 L260 315 L322 276 L384 282 L446 232 L508 240 L570 192 L632 200 L694 136 L756 151 L818 87 L872 75',area:'M74 336 L136 327 L198 306 L260 315 L322 276 L384 282 L446 232 L508 240 L570 192 L632 200 L694 136 L756 151 L818 87 L872 75 L872 342 L74 342 Z',dotY:'75'},
    '1Y': {value:'$23.04M',change:'↑ 112.80% over 1 year',pill:'$23.04M',line:'M74 340 L154 332 L234 315 L314 300 L394 286 L474 246 L554 214 L634 182 L714 137 L794 100 L872 75',area:'M74 340 L154 332 L234 315 L314 300 L394 286 L474 246 L554 214 L634 182 L714 137 L794 100 L872 75 L872 342 L74 342 Z',dotY:'75'},
    'ALL': {value:'$23.04M',change:'↑ 284.12% since network launch',pill:'$23.04M',line:'M74 342 L154 336 L234 326 L314 308 L394 284 L474 250 L554 212 L634 174 L714 132 L794 96 L872 75',area:'M74 342 L154 336 L234 326 L314 308 L394 284 L474 250 L554 212 L634 174 L714 132 L794 96 L872 75 L872 342 L74 342 Z',dotY:'75'}
  };

  const periodButtons = Array.from(document.querySelectorAll('.mgt-periods button'));
  const mainLine = document.getElementById('mgtMainLine');
  const mainArea = document.getElementById('mgtMainArea');
  const mainDot = document.getElementById('mgtMainDot');
  const systemValue = document.getElementById('mgtSystemValue');
  const systemChange = document.getElementById('mgtSystemChange');
  const chartPill = document.getElementById('mgtChartPill');

  periodButtons.forEach(function(button){
    button.addEventListener('click', function(){
      const period = button.dataset.period || '30D';
      const data = chartData[period];
      if(!data){ return; }
      periodButtons.forEach(function(item){ item.classList.toggle('is-active', item === button); });
      mainLine.setAttribute('d', data.line);
      mainArea.setAttribute('d', data.area);
      mainDot.setAttribute('cy', data.dotY);
      systemValue.textContent = data.value;
      systemChange.textContent = data.change;
      chartPill.textContent = data.pill;
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
