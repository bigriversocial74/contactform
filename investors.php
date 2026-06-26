<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Investor Portal | Microgifter';
$page_section = 'investors';
$header_mode = 'public';
$page_body_class = 'mg-investors-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_meta = [
    'description' => 'Microgifter investor portal for reviewing the Promotional CRM thesis, share allocation model, product proof, use of funds, and investor materials.',
    'canonical' => 'https://microgifter.com/investors.php',
    'og_title' => 'Microgifter Investor Portal | Promotional CRM for Local Commerce',
    'og_description' => 'Review Microgifter’s share allocation model, investor thesis, product proof, business model, roadmap, and data room links.',
];
$page_manifest = [
    'id' => 'investors',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Market Model', 'href' => '/investor-tam.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'investors', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
:root{
  --mgi-bg:#f7f4ee;
  --mgi-surface:#fffdf9;
  --mgi-card:#ffffff;
  --mgi-ink:#10100e;
  --mgi-muted:#69655e;
  --mgi-line:#e8e1d6;
  --mgi-gold:#d9a83e;
  --mgi-gold-dark:#8d6412;
  --mgi-gold-soft:#fbf1d9;
  --mgi-green:#0d9255;
  --mgi-green-soft:#e8f8ef;
  --mgi-shadow:0 24px 70px rgba(53,43,24,.10);
  --mgi-max:1240px;
}
.mg-investors-page{background:var(--mgi-bg)!important;color:var(--mgi-ink)}
.mg-investors-page .mg-main{background:var(--mgi-bg);overflow:hidden}
.mgi-page,.mgi-page *{box-sizing:border-box}
.mgi-page{position:relative;background:radial-gradient(circle at 12% 6%,rgba(217,168,62,.18),transparent 24%),radial-gradient(circle at 84% 10%,rgba(20,150,90,.10),transparent 22%),linear-gradient(180deg,#fbf9f4 0,#f7f4ee 58%,#fbf9f4 100%);color:var(--mgi-ink);font-family:Inter,"Helvetica Neue",Arial,sans-serif}
.mgi-full-hero{position:relative;min-height:calc(100vh - 74px);width:100%;display:flex;align-items:stretch;overflow:hidden;background:linear-gradient(90deg,rgba(247,244,238,.99) 0%,rgba(247,244,238,.76) 48%,rgba(247,244,238,.42) 100%),radial-gradient(circle at 6% 10%,rgba(126,178,209,.28),transparent 24%),radial-gradient(circle at 85% 8%,rgba(217,168,62,.30),transparent 35%),url('/images/header_gradient_bg.png') center/cover no-repeat}
.mgi-full-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.20),rgba(255,255,255,.05) 42%,rgba(247,244,238,.70) 100%),repeating-linear-gradient(90deg,rgba(255,255,255,.18) 0 1px,transparent 1px 96px);pointer-events:none}
.mgi-hero-grid{position:relative;z-index:1;width:100%;display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,400px);gap:28px;align-items:center;padding:clamp(28px,3.4vw,54px)}
.mgi-chart-stage{min-width:0}
.mgi-live{display:inline-flex;align-items:center;gap:7px;min-height:26px;padding:0 10px;border-radius:999px;background:var(--mgi-green-soft);color:#0b7a46;font-size:9px;font-weight:950;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}
.mgi-live:before{content:"";width:7px;height:7px;border-radius:50%;background:#20b56c;box-shadow:0 0 0 4px rgba(32,181,108,.13)}
.mgi-hero-title{max-width:910px;margin:22px 0 0;color:#111;font-size:clamp(34px,4.4vw,76px);line-height:.91;letter-spacing:-.07em;font-weight:950;text-wrap:balance}
.mgi-hero-copy{max-width:735px;margin:18px 0 0;color:#504c45;font-size:clamp(15px,1.28vw,19px);line-height:1.48;font-weight:620}
.mgi-hero-value{margin-top:22px}
.mgi-value-label{display:block;color:#5e5a53;font-size:10px;font-weight:950;letter-spacing:.09em;text-transform:uppercase}
.mgi-value{display:block;margin-top:8px;color:#050505;font-size:clamp(44px,5.2vw,82px);line-height:.86;letter-spacing:-.065em;font-weight:950}
.mgi-change{display:block;margin-top:9px;color:var(--mgi-green);font-size:12px;font-weight:950}
.mgi-hero-chart-wrap{position:relative;margin-top:24px;min-height:390px}
.mgi-periods{position:absolute;right:1%;top:-42px;display:flex;gap:7px;align-items:center;z-index:2}
.mgi-periods button{min-width:36px;height:30px;border:1px solid transparent;border-radius:999px;background:rgba(255,255,255,.54);color:#5d584f;font-size:9px;font-weight:950;cursor:pointer}
.mgi-periods button.is-active{border-color:rgba(217,168,62,.40);background:var(--mgi-gold-soft);color:#6e500e}
.mgi-main-chart{display:block;width:100%;height:auto;min-height:380px;overflow:visible}
.mgi-chart-grid line{stroke:#ded7cc;stroke-width:1}
.mgi-chart-axis{fill:#6c675e;font-size:10px;font-weight:800}
.mgi-area{fill:url(#mgiHeroArea)}
.mgi-line{fill:none;stroke:var(--mgi-gold);stroke-width:4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 5px 10px rgba(217,168,62,.20))}
.mgi-chart-dot{fill:#fff;stroke:var(--mgi-gold);stroke-width:4}
.mgi-chart-pill{position:absolute;right:.6%;top:16%;padding:7px 10px;border-radius:10px;background:var(--mgi-gold);color:#fff;font-size:11px;font-weight:950;box-shadow:0 12px 26px rgba(217,168,62,.28)}
.mgi-hero-stat-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));margin-top:18px;border:1px solid rgba(232,225,214,.86);border-radius:18px;background:rgba(255,255,255,.72);box-shadow:0 20px 54px rgba(53,43,24,.08);backdrop-filter:blur(14px);overflow:hidden}
.mgi-hero-stat{min-height:126px;padding:24px 20px;border-right:1px solid rgba(232,225,214,.86)}
.mgi-hero-stat:last-child{border-right:0}
.mgi-hero-stat span{display:block;min-height:26px;color:#6d685f;font-size:9px;line-height:1.25;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.mgi-hero-stat strong{display:block;margin-top:13px;color:#111;font-size:25px;line-height:.96;font-weight:950;letter-spacing:-.04em}
.mgi-hero-stat em{display:block;margin-top:9px;color:var(--mgi-green);font-size:10px;line-height:1.25;font-style:normal;font-weight:900}
.mgi-invest-card{padding:22px;border:1px solid rgba(232,225,214,.95);border-radius:22px;background:rgba(255,255,255,.88);box-shadow:0 24px 70px rgba(53,43,24,.13);backdrop-filter:blur(18px)}
.mgi-form-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.mgi-form-top h2{max-width:260px;margin:0;font-size:16px;line-height:1.1;letter-spacing:-.035em;font-weight:950}
.mgi-label{display:block;margin:18px 0 8px;color:#4f4b44;font-size:9px;font-weight:950;letter-spacing:.11em;text-transform:uppercase}
.mgi-input-row{display:grid;grid-template-columns:94px minmax(0,1fr);gap:8px}
.mgi-select,.mgi-input{height:48px;border:1px solid var(--mgi-line);border-radius:11px;background:#fff;color:var(--mgi-ink);font:inherit;font-size:13px;font-weight:850;outline:none}
.mgi-select{padding:0 10px}.mgi-input{width:100%;padding:0 13px}
.mgi-input:focus,.mgi-select:focus{border-color:rgba(217,168,62,.70);box-shadow:0 0 0 4px rgba(217,168,62,.12)}
.mgi-rate{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:8px;color:var(--mgi-muted);font-size:9px;font-weight:780}.mgi-rate strong{color:var(--mgi-green);font-weight:950}
.mgi-allocation{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}
.mgi-allocation button{min-height:39px;border:1px solid var(--mgi-line);border-radius:11px;background:#fff;color:#2b2925;font-size:10px;font-weight:950;cursor:pointer;transition:.18s ease}
.mgi-allocation button:hover,.mgi-allocation button.is-active{border-color:rgba(217,168,62,.62);background:var(--mgi-gold-soft);color:#6e500e;transform:translateY(-1px)}
.mgi-summary{display:grid;gap:8px;margin-top:16px;padding:14px;border-radius:14px;background:#f7f3ec}
.mgi-summary-row{display:flex;align-items:center;justify-content:space-between;gap:18px;color:#5b564e;font-size:10px;font-weight:780}
.mgi-summary-row strong{color:#151411;font-size:11px;font-weight:950;text-align:right}
.mgi-benefits{display:grid;gap:10px;margin-top:16px}.mgi-benefit{display:grid;grid-template-columns:30px 1fr;gap:10px;align-items:start}
.mgi-benefit-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--mgi-gold-soft);color:var(--mgi-gold-dark);font-size:12px;font-weight:950}
.mgi-benefit strong{display:block;color:#211f1c;font-size:11px;font-weight:950}.mgi-benefit span{display:block;margin-top:3px;color:var(--mgi-muted);font-size:9.5px;line-height:1.38;font-weight:650}
.mgi-primary-btn,.mgi-secondary-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;padding:0 18px;border-radius:12px;text-decoration:none!important;font-size:12px;font-weight:950;transition:.18s ease}
.mgi-primary-btn{border:1px solid #111;background:#111;color:#fff!important;box-shadow:0 14px 30px rgba(17,17,17,.15)}
.mgi-primary-btn:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(17,17,17,.20)}
.mgi-secondary-btn{border:1px solid var(--mgi-line);background:#fff;color:#161512!important}
.mgi-form-submit{width:100%;margin-top:18px}.mgi-secure{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:13px;color:#77736b;font-size:9px;font-weight:800}
.mgi-shell{width:min(var(--mgi-max),calc(100% - 48px));margin:0 auto;padding:72px 0 92px}
.mgi-trust{display:grid;grid-template-columns:auto repeat(4,minmax(0,1fr));gap:12px;align-items:center;padding:14px;border:1px solid var(--mgi-line);border-radius:18px;background:rgba(255,255,255,.72);box-shadow:0 14px 48px rgba(53,43,24,.045)}
.mgi-trust-label{padding:0 9px;color:#77736b;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.08em}
.mgi-trust-item{display:flex;align-items:center;gap:10px;min-height:48px;padding:0 12px;border-left:1px solid var(--mgi-line);color:#37342f;font-size:10px;font-weight:850}
.mgi-trust-icon{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border-radius:9px;background:#f4efe6;color:#7c5a13;font-size:12px}
.mgi-anchor-nav{position:sticky;top:82px;z-index:5;display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;padding:10px;border:1px solid var(--mgi-line);border-radius:18px;background:rgba(255,255,255,.76);box-shadow:0 14px 48px rgba(53,43,24,.055);backdrop-filter:blur(16px)}
.mgi-anchor-nav a{display:inline-flex;align-items:center;min-height:34px;padding:0 12px;border-radius:999px;color:#2e2b26!important;text-decoration:none!important;background:#fff;border:1px solid rgba(232,225,214,.95);font-size:10px;font-weight:950}.mgi-anchor-nav a:hover{background:var(--mgi-gold-soft);border-color:rgba(217,168,62,.38)}
.mgi-section{margin-top:72px;scroll-margin-top:150px}.mgi-section-head{display:flex;align-items:end;justify-content:space-between;gap:28px;margin-bottom:26px}.mgi-section-head>div{max-width:760px}
.mgi-kicker{display:block;color:var(--mgi-gold-dark);font-size:10px;font-weight:950;letter-spacing:.13em;text-transform:uppercase}
.mgi-section-title{margin:10px 0 0;color:var(--mgi-ink);font-size:clamp(34px,4vw,54px);line-height:.96;letter-spacing:-.06em;font-weight:950;text-wrap:balance}
.mgi-section-copy{max-width:680px;margin:14px 0 0;color:#5b5750;font-size:15px;line-height:1.5;font-weight:560}
.mgi-overview-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
.mgi-metric-card{min-height:224px;padding:20px;border:1px solid var(--mgi-line);border-radius:18px;background:rgba(255,255,255,.82);box-shadow:0 12px 38px rgba(53,43,24,.045)}
.mgi-metric-card span{display:block;min-height:30px;color:var(--mgi-muted);font-size:9px;line-height:1.35;font-weight:950;letter-spacing:.06em;text-transform:uppercase}.mgi-metric-card strong{display:block;margin-top:11px;color:#171612;font-size:26px;line-height:.95;letter-spacing:-.045em;font-weight:950}.mgi-metric-card em{display:block;margin-top:8px;color:var(--mgi-green);font-style:normal;font-size:9px;font-weight:950}
.mgi-spark{display:block;width:100%;height:76px;margin-top:22px}.mgi-spark-grid{stroke:#eee9e1;stroke-width:1}.mgi-spark-line{fill:none;stroke:var(--mgi-green);stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round}.mgi-spark-line.gold{stroke:var(--mgi-gold)}.mgi-spark-line.gray{stroke:#8b8984}
.mgi-reasons{padding:30px;border:1px solid var(--mgi-line);border-radius:22px;background:rgba(255,255,255,.74)}
.mgi-reason-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;margin-top:28px}.mgi-reason{min-height:198px;padding:4px 26px 8px;border-left:1px solid var(--mgi-line)}.mgi-reason:first-child{border-left:0;padding-left:0}.mgi-reason:last-child{padding-right:0}
.mgi-reason-icon{display:grid;place-items:center;width:46px;height:46px;border:1px solid var(--mgi-line);border-radius:50%;background:#fff;color:#151411;font-size:17px;font-weight:950}.mgi-reason h3{margin:22px 0 0;color:#1b1a17;font-size:17px;line-height:1.05;letter-spacing:-.035em;font-weight:950}.mgi-reason p{margin:12px 0 0;color:#68645d;font-size:12px;line-height:1.5;font-weight:600}
.mgi-performance-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(290px,.55fr);gap:14px;align-items:stretch}.mgi-performance-card,.mgi-side-card{border:1px solid var(--mgi-line);border-radius:22px;background:rgba(255,255,255,.84);box-shadow:0 14px 46px rgba(53,43,24,.045)}.mgi-performance-card{padding:26px}
.mgi-performance-top{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}.mgi-performance-top h3{margin:0;color:#171612;font-size:25px;line-height:1;letter-spacing:-.05em;font-weight:950}.mgi-performance-top p{max-width:520px;margin:11px 0 0;color:#68645d;font-size:12px;line-height:1.48;font-weight:600}.mgi-proof-score{text-align:right}.mgi-proof-score strong{display:block;font-size:31px;line-height:.9;letter-spacing:-.045em;font-weight:950}.mgi-proof-score span{display:block;margin-top:8px;color:var(--mgi-green);font-size:10px;font-weight:950}
.mgi-token-chart{margin-top:20px;padding:16px 0 4px;border-top:1px solid var(--mgi-line);border-bottom:1px solid var(--mgi-line)}.mgi-token-chart svg{display:block;width:100%;height:auto}.mgi-token-grid line{stroke:#ebe6de;stroke-width:1}.mgi-token-area{fill:url(#mgiTokenArea)}.mgi-token-line{fill:none;stroke:var(--mgi-gold);stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.mgi-token-dot{fill:#fff;stroke:var(--mgi-gold);stroke-width:3}
.mgi-token-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}.mgi-token-stat span{display:block;color:var(--mgi-muted);font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.06em}.mgi-token-stat strong{display:block;margin-top:8px;color:#1b1a17;font-size:17px;line-height:1;font-weight:950}.mgi-performance-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:22px}
.mgi-side-stack{display:grid;grid-template-rows:auto auto;gap:14px}.mgi-side-card{padding:22px}.mgi-side-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--mgi-line)}.mgi-side-head h3{margin:0;font-size:16px;letter-spacing:-.035em;font-weight:950}.mgi-side-head a{color:#77736b;font-size:9px;font-weight:950;text-decoration:none}
.mgi-stat-list-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:10px;align-items:center;min-height:49px;border-bottom:1px solid #eee9e1}.mgi-stat-list-row:last-child{border-bottom:0}.mgi-stat-list-row b{display:grid;place-items:center;width:26px;height:26px;border-radius:9px;background:var(--mgi-gold-soft);color:var(--mgi-gold-dark);font-size:11px}.mgi-stat-list-row span{color:#5e5a53;font-size:9px;line-height:1.25;font-weight:800}.mgi-stat-list-row strong{color:#1a1916;font-size:10px;font-weight:950;text-align:right}
.mgi-network-info{display:grid;gap:11px;margin-top:15px}.mgi-network-info-row{display:flex;align-items:center;justify-content:space-between;gap:16px;color:#68645d;font-size:10px;font-weight:760}.mgi-network-info-row strong{color:#1b1a17;font-weight:950;text-align:right}
.mgi-distribution{overflow:hidden;border:1px solid var(--mgi-line);border-radius:22px;background:rgba(255,255,255,.82)}.mgi-distribution-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 24px;border-bottom:1px solid var(--mgi-line)}.mgi-distribution-head h3{margin:0;font-size:20px;letter-spacing:-.045em;font-weight:950}.mgi-distribution-head span{color:#77736b;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.08em}
.mgi-table{width:100%;border-collapse:collapse}.mgi-table th,.mgi-table td{padding:15px 20px;border-bottom:1px solid #eee9e1;text-align:left}.mgi-table th{color:#77736b;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.07em}.mgi-table td{color:#44413b;font-size:11px;font-weight:730}.mgi-table td strong{display:block;color:#1b1a17;font-size:12px;font-weight:950}.mgi-table tbody tr:last-child td{border-bottom:0}.mgi-table .positive{color:var(--mgi-green);font-weight:950}.mgi-row-meter{width:100%;height:7px;border-radius:999px;background:#eee9e1;overflow:hidden}.mgi-row-meter span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--mgi-gold),#e7c66e)}
.mgi-data-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mgi-data-card{min-height:158px;padding:20px;border:1px solid var(--mgi-line);border-radius:18px;background:rgba(255,255,255,.82);box-shadow:0 12px 38px rgba(53,43,24,.045)}.mgi-data-card span{display:block;color:#6d685f;font-size:9px;line-height:1.35;font-weight:950;letter-spacing:.07em;text-transform:uppercase}.mgi-data-card strong{display:block;margin-top:12px;color:#171612;font-size:22px;line-height:.95;letter-spacing:-.04em;font-weight:950}.mgi-data-card p{margin:10px 0 0;color:#66625b;font-size:11px;line-height:1.44;font-weight:650}.mgi-data-card a{display:inline-flex;margin-top:13px;color:#111!important;font-size:10px;font-weight:950;text-decoration:none}
.mgi-cta{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.65fr);gap:24px;align-items:center;margin-top:72px;padding:38px;border:1px solid var(--mgi-line);border-radius:26px;background:radial-gradient(circle at 85% 18%,rgba(217,168,62,.17),transparent 30%),linear-gradient(135deg,#fffdfa,#f6f0e6);box-shadow:var(--mgi-shadow)}.mgi-cta h2{max-width:700px;margin:0;color:#141310;font-size:clamp(34px,4vw,56px);line-height:.95;letter-spacing:-.065em;font-weight:950;text-wrap:balance}.mgi-cta p{max-width:650px;margin:18px 0 0;color:#5c5851;font-size:14px;line-height:1.5;font-weight:580}.mgi-cta-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.mgi-cta-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px}.mgi-cta-card{min-height:188px;padding:22px;border:1px solid rgba(0,0,0,.07);border-radius:18px;background:rgba(255,255,255,.78)}.mgi-cta-card b{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:#111;color:#fff;font-size:14px}.mgi-cta-card h3{margin:22px 0 0;font-size:15px;font-weight:950;letter-spacing:-.03em}.mgi-cta-card p{margin:9px 0 0;color:#6a665f;font-size:10px;line-height:1.45;font-weight:650}.mgi-cta-card a{display:inline-flex;margin-top:15px;color:#151411;font-size:10px;font-weight:950;text-decoration:none}
@media(max-width:1120px){.mgi-hero-grid{grid-template-columns:1fr;padding:30px 22px}.mgi-full-hero{min-height:auto}.mgi-invest-card{max-width:520px;margin-left:auto;margin-right:auto}.mgi-hero-stat-strip{grid-template-columns:repeat(3,minmax(0,1fr))}.mgi-hero-stat:nth-child(3n){border-right:0}.mgi-hero-stat:nth-child(n+4){border-top:1px solid rgba(232,225,214,.86)}.mgi-overview-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.mgi-performance-layout{grid-template-columns:1fr}.mgi-side-stack{grid-template-columns:1fr 1fr;grid-template-rows:auto}.mgi-trust{grid-template-columns:1fr 1fr}.mgi-trust-label{grid-column:1/-1}.mgi-trust-item{border-left:0}.mgi-data-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:820px){.mgi-shell{width:min(var(--mgi-max),calc(100% - 28px));padding-top:54px}.mgi-hero-chart-wrap{min-height:auto;margin-top:70px}.mgi-periods{left:0;right:auto;top:-50px}.mgi-hero-stat-strip,.mgi-overview-grid{grid-template-columns:1fr 1fr}.mgi-reason-grid{grid-template-columns:1fr 1fr;gap:22px}.mgi-reason{border-left:0;border-top:1px solid var(--mgi-line);padding:22px 0 0}.mgi-reason:nth-child(-n+2){border-top:0;padding-top:0}.mgi-token-stats{grid-template-columns:1fr 1fr}.mgi-cta{grid-template-columns:1fr;padding:28px}.mgi-table{min-width:760px}.mgi-distribution{overflow-x:auto}.mgi-anchor-nav{position:relative;top:auto}}
@media(max-width:560px){.mgi-hero-grid{padding:22px 14px 30px}.mgi-hero-title{font-size:35px}.mgi-value{font-size:46px}.mgi-input-row{grid-template-columns:96px minmax(0,1fr)}.mgi-allocation{grid-template-columns:1fr 1fr}.mgi-hero-stat-strip,.mgi-overview-grid,.mgi-side-stack,.mgi-cta-cards,.mgi-data-grid{grid-template-columns:1fr}.mgi-hero-stat{border-right:0!important;border-top:1px solid rgba(232,225,214,.86)}.mgi-hero-stat:first-child{border-top:0}.mgi-reason-grid{grid-template-columns:1fr}.mgi-reason:nth-child(2){border-top:1px solid var(--mgi-line);padding-top:22px}.mgi-performance-top{display:grid}.mgi-proof-score{text-align:left}.mgi-performance-actions{grid-template-columns:1fr}.mgi-trust{grid-template-columns:1fr}.mgi-cta{padding:24px 20px}}
</style>

<main class="mgi-page">
  <section class="mgi-full-hero" id="portal" aria-labelledby="mgi-investor-title">
    <div class="mgi-hero-grid">
      <div class="mgi-chart-stage">
        <span class="mgi-live">Investor portal</span>
        <h1 class="mgi-hero-title" id="mgi-investor-title">Microgifter Share Market</h1>
        <p class="mgi-hero-copy">A private-style investor portal for reviewing Microgifter’s available share pool, pre-seed allocation model, Promotional CRM thesis, product proof, use of funds, and data-room links.</p>

        <div class="mgi-hero-value">
          <span class="mgi-value-label">Total shares available</span>
          <strong class="mgi-value" id="mgiSystemValue">1,000,000</strong>
          <span class="mgi-change" id="mgiSystemChange">Common share pool available for investor allocation</span>
        </div>

        <div class="mgi-hero-chart-wrap" aria-label="Microgifter investor allocation chart">
          <div class="mgi-periods" aria-label="Allocation views">
            <button type="button" data-period="25K">$25K</button>
            <button class="is-active" type="button" data-period="50K">$50K</button>
            <button type="button" data-period="100K">$100K</button>
            <button type="button" data-period="250K">$250K</button>
            <button type="button" data-period="500K">$500K</button>
          </div>
          <svg class="mgi-main-chart" viewBox="0 0 900 430" role="img" aria-labelledby="mgiChartTitle mgiChartDesc">
            <title id="mgiChartTitle">Microgifter share allocation model</title>
            <desc id="mgiChartDesc">A light-background line chart showing investor shares increasing by check size while staying within the one million available share pool.</desc>
            <defs>
              <linearGradient id="mgiHeroArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#d9a83e" stop-opacity=".30"/><stop offset="100%" stop-color="#d9a83e" stop-opacity="0"/></linearGradient>
            </defs>
            <g class="mgi-chart-grid"><line x1="74" y1="52" x2="872" y2="52"/><line x1="74" y1="120" x2="872" y2="120"/><line x1="74" y1="188" x2="872" y2="188"/><line x1="74" y1="256" x2="872" y2="256"/><line x1="74" y1="324" x2="872" y2="324"/></g>
            <g class="mgi-chart-axis"><text x="14" y="56">100K</text><text x="14" y="124">75K</text><text x="14" y="192">50K</text><text x="14" y="260">25K</text><text x="14" y="328">0</text><text x="74" y="385">$25K</text><text x="230" y="385">$50K</text><text x="386" y="385">$100K</text><text x="542" y="385">$250K</text><text x="698" y="385">$500K</text><text x="824" y="385">Pool</text></g>
            <path class="mgi-area" id="mgiMainArea" d="M74 306 L230 288 L386 252 L542 162 L698 72 L872 72 L872 342 L74 342 Z"/>
            <path class="mgi-line" id="mgiMainLine" d="M74 306 L230 288 L386 252 L542 162 L698 72 L872 72"/>
            <circle class="mgi-chart-dot" id="mgiMainDot" cx="230" cy="288" r="6"/>
          </svg>
          <span class="mgi-chart-pill" id="mgiChartPill">10,000 shares</span>
        </div>

        <div class="mgi-hero-stat-strip">
          <div class="mgi-hero-stat"><span>Total shares available</span><strong>1,000,000</strong><em>Common share pool</em></div>
          <div class="mgi-hero-stat"><span>Illustrative share price</span><strong>$5.00</strong><em>$5M / 1M shares</em></div>
          <div class="mgi-hero-stat"><span>Target raise</span><strong>$250K–$500K</strong><em>Pre-seed validation</em></div>
          <div class="mgi-hero-stat"><span>Target shares</span><strong>50K–100K</strong><em>At $5.00 per share</em></div>
          <div class="mgi-hero-stat"><span>Target allocation</span><strong>5%–10%</strong><em>Of available shares</em></div>
          <div class="mgi-hero-stat"><span>Remaining after max</span><strong>900K</strong><em>After $500K round</em></div>
        </div>
      </div>

      <form class="mgi-invest-card" id="mgiInvestorForm" action="/learn-more.php#learn-more-form" method="get">
        <input type="hidden" name="source" value="investors">
        <div class="mgi-form-top"><h2>Investor allocation calculator</h2><span class="mgi-live">1M shares</span></div>

        <label class="mgi-label" for="mgiCommitAmount">Potential investment</label>
        <div class="mgi-input-row">
          <select class="mgi-select" id="mgiInstrument" name="instrument" aria-label="Investment instrument"><option value="Common Shares">Shares</option><option value="SAFE">SAFE</option><option value="Strategic">Strategic</option></select>
          <input class="mgi-input" id="mgiCommitAmount" name="amount" type="number" min="1000" step="1000" value="50000" inputmode="decimal">
        </div>

        <label class="mgi-label" for="mgiSharePrice">Share price</label>
        <div class="mgi-input-row">
          <select class="mgi-select" aria-label="Currency" disabled><option>USD</option></select>
          <input class="mgi-input" id="mgiSharePrice" name="share_price" type="number" min="0.01" step="0.01" value="5.00" inputmode="decimal">
        </div>
        <div class="mgi-rate"><span>1,000,000 available shares</span><strong id="mgiValuationLabel">$5.00M implied value</strong></div>

        <span class="mgi-label">Choose check size</span>
        <div class="mgi-allocation" aria-label="Quick investor check sizes"><button type="button" data-amount="25000">$25K</button><button class="is-active" type="button" data-amount="50000">$50K</button><button type="button" data-amount="100000">$100K</button><button type="button" data-amount="250000">$250K</button></div>

        <div class="mgi-summary" aria-live="polite">
          <div class="mgi-summary-row"><span>Estimated shares</span><strong id="mgiSummaryShares">10,000</strong></div>
          <div class="mgi-summary-row"><span>% of available shares</span><strong id="mgiSummaryShare">1.00%</strong></div>
          <div class="mgi-summary-row"><span>Shares remaining after buy</span><strong id="mgiSummaryRemaining">990,000</strong></div>
        </div>

        <div class="mgi-benefits">
          <div class="mgi-benefit"><span class="mgi-benefit-icon">✓</span><div><strong>Investor portal model</strong><span>Uses the confirmed 1,000,000 available shares as the base allocation pool.</span></div></div>
          <div class="mgi-benefit"><span class="mgi-benefit-icon">✓</span><div><strong>Capital-efficient round</strong><span>$250K–$500K maps to 50,000–100,000 shares at the current $5.00 example price.</span></div></div>
          <div class="mgi-benefit"><span class="mgi-benefit-icon">✓</span><div><strong>Product proof attached</strong><span>Connects investor allocation to the Promotional CRM proof loop and milestone roadmap.</span></div></div>
        </div>

        <button class="mgi-primary-btn mgi-form-submit" type="submit">Request Investor Access <span>→</span></button>
        <div class="mgi-secure"><span>Share allocation</span><span>•</span><span>Market model</span><span>•</span><span>Private deck</span></div>
      </form>
    </div>
  </section>

  <div class="mgi-shell">
    <section class="mgi-trust" aria-label="Microgifter investor foundations"><span class="mgi-trust-label">Investor portal foundations</span><div class="mgi-trust-item"><span class="mgi-trust-icon">◎</span><span>1M available shares</span></div><div class="mgi-trust-item"><span class="mgi-trust-icon">↗</span><span>Promotional CRM</span></div><div class="mgi-trust-item"><span class="mgi-trust-icon">✓</span><span>Tracked redemption</span></div><div class="mgi-trust-item"><span class="mgi-trust-icon">◇</span><span>Future demand data</span></div></section>

    <nav class="mgi-anchor-nav" aria-label="Investor page sections"><a href="#portal">Portal</a><a href="#product-proof">Product Proof</a><a href="#share-model">Share Model</a><a href="#business-model">Business Model</a><a href="#use-of-funds">Use of Funds</a><a href="#roadmap">Roadmap</a><a href="#data-room">Data Room</a></nav>

    <section class="mgi-section" id="product-proof" aria-labelledby="product-proof-title">
      <div class="mgi-section-head"><div><span class="mgi-kicker">Microgifter ecosystem overview</span><h2 class="mgi-section-title" id="product-proof-title">One portal for shares, proof, and the local commerce thesis.</h2><p class="mgi-section-copy">The investor page now behaves like an investor portal instead of a generic landing page. It shows allocation math, product proof, use of funds, roadmap, and data-room links in the same visual language as the Buy-In page.</p></div></div>
      <div class="mgi-overview-grid">
        <article class="mgi-metric-card"><span>Share pool</span><strong>1,000,000</strong><em>Available shares</em><svg class="mgi-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgi-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgi-spark-line gold" d="M2 60 L18 54 L34 58 L50 43 L66 47 L82 35 L98 39 L114 25 L130 31 L146 15 L162 20 L178 5"/></svg></article>
        <article class="mgi-metric-card"><span>Round target</span><strong>$250K–$500K</strong><em>Pre-seed validation</em><svg class="mgi-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgi-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgi-spark-line" d="M2 62 L18 52 L34 55 L50 46 L66 50 L82 38 L98 33 L114 36 L130 24 L146 28 L162 16 L178 8"/></svg></article>
        <article class="mgi-metric-card"><span>Allocation range</span><strong>50K–100K</strong><em>Shares at $5.00</em><svg class="mgi-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgi-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgi-spark-line" d="M2 61 L18 58 L34 49 L50 53 L66 43 L82 46 L98 34 L114 38 L130 27 L146 22 L162 13 L178 6"/></svg></article>
        <article class="mgi-metric-card"><span>Product category</span><strong>Promotional CRM</strong><em>Local commerce system</em><svg class="mgi-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgi-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgi-spark-line gray" d="M2 63 L18 56 L34 58 L50 48 L66 50 L82 40 L98 43 L114 32 L130 35 L146 22 L162 18 L178 8"/></svg></article>
        <article class="mgi-metric-card"><span>Milestone target</span><strong>$750K ARR</strong><em>At 2,500 businesses</em><svg class="mgi-spark" viewBox="0 0 180 76" aria-hidden="true"><line class="mgi-spark-grid" x1="0" y1="64" x2="180" y2="64"/><path class="mgi-spark-line gold" d="M2 64 L18 62 L34 53 L50 56 L66 44 L82 47 L98 36 L114 31 L130 34 L146 20 L162 14 L178 4"/></svg></article>
      </div>
    </section>

    <section class="mgi-section mgi-reasons" id="share-model" aria-labelledby="share-model-title"><span class="mgi-kicker">Share model</span><h2 class="mgi-section-title" id="share-model-title">The math is based on 1,000,000 available shares.</h2><div class="mgi-reason-grid"><article class="mgi-reason"><span class="mgi-reason-icon">◎</span><h3>$25K check</h3><p>At $5.00/share, this equals 5,000 shares, or 0.50% of the available share pool.</p></article><article class="mgi-reason"><span class="mgi-reason-icon">↗</span><h3>$50K check</h3><p>At $5.00/share, this equals 10,000 shares, or 1.00% of the available share pool.</p></article><article class="mgi-reason"><span class="mgi-reason-icon">✓</span><h3>$250K target</h3><p>At $5.00/share, this equals 50,000 shares, or 5.00% of the available share pool.</p></article><article class="mgi-reason"><span class="mgi-reason-icon">◇</span><h3>$500K max</h3><p>At $5.00/share, this equals 100,000 shares, or 10.00% of the available share pool.</p></article></div></section>

    <section class="mgi-section" id="business-model" aria-labelledby="business-model-title">
      <div class="mgi-performance-layout"><article class="mgi-performance-card"><div class="mgi-performance-top"><div><span class="mgi-kicker">System performance</span><h3 id="business-model-title">Microgifter investor proof-loop</h3><p>The investor model is connected to the platform lifecycle: offer creation, campaign distribution, claim ownership, redemption proof, and automated follow-up.</p></div><div class="mgi-proof-score"><strong>10%</strong><span>Max target allocation</span></div></div>
      <div class="mgi-token-chart" aria-label="Microgifter proof loop readiness chart"><svg viewBox="0 0 760 280" role="img" aria-labelledby="mgiTokenTitle mgiTokenDesc"><title id="mgiTokenTitle">Microgifter proof-loop readiness trend</title><desc id="mgiTokenDesc">A light line chart showing proof-loop readiness increasing as product, model, and investor data room come together.</desc><defs><linearGradient id="mgiTokenArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#d9a83e" stop-opacity=".24"/><stop offset="100%" stop-color="#d9a83e" stop-opacity="0"/></linearGradient></defs><g class="mgi-token-grid"><line x1="58" y1="28" x2="742" y2="28"/><line x1="58" y1="86" x2="742" y2="86"/><line x1="58" y1="144" x2="742" y2="144"/><line x1="58" y1="202" x2="742" y2="202"/></g><g class="mgi-chart-axis"><text x="7" y="32">100</text><text x="7" y="90">75</text><text x="7" y="148">50</text><text x="7" y="206">25</text><text x="62" y="258">Docs</text><text x="222" y="258">CRM</text><text x="382" y="258">Claims</text><text x="542" y="258">TAM</text><text x="684" y="258">Deck</text></g><path class="mgi-token-area" d="M58 188 L92 176 L126 181 L160 166 L194 170 L228 152 L262 157 L296 139 L330 144 L364 126 L398 120 L432 124 L466 109 L500 114 L534 92 L568 99 L602 76 L636 83 L670 57 L704 42 L738 46 L738 218 L58 218 Z"/><path class="mgi-token-line" d="M58 188 L92 176 L126 181 L160 166 L194 170 L228 152 L262 157 L296 139 L330 144 L364 126 L398 120 L432 124 L466 109 L500 114 L534 92 L568 99 L602 76 L636 83 L670 57 L704 42 L738 46"/><circle class="mgi-token-dot" cx="738" cy="46" r="5"/></svg></div>
      <div class="mgi-token-stats"><div class="mgi-token-stat"><span>Available shares</span><strong>1,000,000</strong></div><div class="mgi-token-stat"><span>Example price</span><strong>$5.00</strong></div><div class="mgi-token-stat"><span>Target raise</span><strong>$250K–$500K</strong></div><div class="mgi-token-stat"><span>Target shares</span><strong>50K–100K</strong></div></div><div class="mgi-performance-actions"><a class="mgi-secondary-btn" href="/investor-tam.php">View Bottom-Up TAM</a><a class="mgi-primary-btn" href="/learn-more.php">Request Deck <span>→</span></a></div></article>
      <div class="mgi-side-stack"><aside class="mgi-side-card"><div class="mgi-side-head"><h3>Investor stats</h3><a href="#share-model">View share model</a></div><div class="mgi-stat-list"><div class="mgi-stat-list-row"><b>◎</b><span>Stage</span><strong>Pre-seed</strong></div><div class="mgi-stat-list-row"><b>↗</b><span>Available shares</span><strong>1,000,000</strong></div><div class="mgi-stat-list-row"><b>✓</b><span>Example price</span><strong>$5.00</strong></div><div class="mgi-stat-list-row"><b>◇</b><span>Target raise</span><strong>$250K–$500K</strong></div><div class="mgi-stat-list-row"><b>$</b><span>Max allocation</span><strong>100K shares</strong></div><div class="mgi-stat-list-row"><b>+</b><span>Remaining after max</span><strong>900K shares</strong></div></div></aside><aside class="mgi-side-card"><div class="mgi-side-head"><h3>Business model</h3><span></span></div><div class="mgi-network-info"><div class="mgi-network-info-row"><span>SaaS</span><strong>Promotional CRM</strong></div><div class="mgi-network-info-row"><span>Transactions</span><strong>Gift + reward sales</strong></div><div class="mgi-network-info-row"><span>Usage</span><strong>Distribution credits</strong></div><div class="mgi-network-info-row"><span>Enterprise</span><strong>Workplace rewards</strong></div><div class="mgi-network-info-row"><span>Data layer</span><strong>PPPM + PSR signals</strong></div></div></aside></div></div>
    </section>

    <section class="mgi-section mgi-distribution" id="use-of-funds" aria-labelledby="use-of-funds-title"><div class="mgi-distribution-head"><h3 id="use-of-funds-title">Microgifter pre-seed use of funds</h3><span>Capital tied to proof-loop validation</span></div><table class="mgi-table"><thead><tr><th>Category</th><th>Purpose</th><th>Investor proof point</th><th>Priority</th><th>Allocation</th></tr></thead><tbody><tr><td><strong>Product completion</strong>Finish the Promotional CRM loop</td><td>Campaigns, offers, claims, redemption, dashboards, and automation.</td><td>End-to-end merchant workflow</td><td class="positive">High</td><td><div class="mgi-row-meter"><span style="width:82%"></span></div></td></tr><tr><td><strong>Merchant acquisition</strong>Launch focused local cohorts</td><td>Hospitality, cafés, salons, fitness, events, local services, and multi-location tests.</td><td>Active merchants and repeat campaigns</td><td class="positive">High</td><td><div class="mgi-row-meter"><span style="width:68%"></span></div></td></tr><tr><td><strong>Distribution partners</strong>Expand demand channels</td><td>Affiliates, community channels, enterprise reward buyers, QR programs, and API trials.</td><td>Issued, claimed, and redeemed value</td><td class="positive">Medium</td><td><div class="mgi-row-meter"><span style="width:52%"></span></div></td></tr><tr><td><strong>Investor-grade metrics</strong>Turn operations into data room proof</td><td>MRR, retention, redemption rates, cohort health, PSR signals, and reporting snapshots.</td><td>Next-round readiness</td><td class="positive">Core</td><td><div class="mgi-row-meter"><span style="width:74%"></span></div></td></tr></tbody></table></section>

    <section class="mgi-section" id="roadmap" aria-labelledby="roadmap-title"><div class="mgi-section-head"><div><span class="mgi-kicker">Milestone roadmap</span><h2 class="mgi-section-title" id="roadmap-title">From product completion to repeatable revenue.</h2><p class="mgi-section-copy">The next stage is designed to prove that Microgifter can turn local engagement into measurable promotional commerce and future demand intelligence.</p></div></div><div class="mgi-data-grid"><article class="mgi-data-card"><span>0–90 days</span><strong>Finish proof loop</strong><p>Complete the offer, distribution, claim, redemption, and follow-up workflow.</p></article><article class="mgi-data-card"><span>90–180 days</span><strong>Merchant cohort</strong><p>Launch focused local merchants and measure campaign usage, claims, and redemptions.</p></article><article class="mgi-data-card"><span>6–12 months</span><strong>MRR validation</strong><p>Prove paid plans, repeat campaign behavior, and revenue per merchant.</p></article><article class="mgi-data-card"><span>12–24 months</span><strong>Expansion proof</strong><p>Move into multi-location, enterprise rewards, and distribution partner pilots.</p></article></div></section>

    <section class="mgi-section" id="data-room" aria-labelledby="data-room-title"><div class="mgi-section-head"><div><span class="mgi-kicker">Data room</span><h2 class="mgi-section-title" id="data-room-title">Everything investors should review from one portal.</h2><p class="mgi-section-copy">The investor page acts as a guided hub: market model, pricing, demo request, distribution proof, share model, and product-readiness context.</p></div></div><div class="mgi-data-grid"><article class="mgi-data-card"><span>Market model</span><strong>Bottom-up TAM</strong><p>Subscription ARPU, transaction volume, distribution usage, and penetration ladder.</p><a href="/investor-tam.php">Open model →</a></article><article class="mgi-data-card"><span>Product demo</span><strong>Merchant workflow</strong><p>Walk through campaign creation, reward issuing, claiming, redemption, and reporting.</p><a href="/learn-more.php">Request demo →</a></article><article class="mgi-data-card"><span>Pricing</span><strong>Package model</strong><p>Promotional CRM plans, merchant tiers, multi-location potential, and packaging.</p><a href="/pricing.php">View pricing →</a></article><article class="mgi-data-card"><span>Developer proof</span><strong>API distribution</strong><p>External apps can distribute Microgifter rewards into their own flows.</p><a href="/developer-docs.php#quickstart">View docs →</a></article></div></section>

    <section class="mgi-cta" aria-labelledby="mgi-cta-title"><div><span class="mgi-kicker">Build the future of local value</span><h2 id="mgi-cta-title">Invest. Validate. Grow together.</h2><p>Microgifter is building a Promotional CRM for local commerce: rewards, referrals, contests, direct distribution, value-added sales, redemption proof, and future demand data in one system.</p><div class="mgi-cta-actions"><a class="mgi-primary-btn" href="/learn-more.php">Request Investor Access <span>→</span></a><a class="mgi-secondary-btn" href="/investor-tam.php">View Market Model</a></div></div><div class="mgi-cta-cards"><article class="mgi-cta-card"><b>◎</b><h3>For investors</h3><p>Review the share model, market model, proof loop, use of funds, and staged roadmap.</p><a href="/learn-more.php">Request deck →</a></article><article class="mgi-cta-card"><b>↗</b><h3>For merchants</h3><p>Launch campaigns that create measurable customer action and redemption data.</p><a href="/pricing.php">View plans →</a></article></div></section>
  </div>
</main>

<script>
(function(){
  'use strict';
  const totalShares = 1000000;
  const amountInput = document.getElementById('mgiCommitAmount');
  const sharePriceInput = document.getElementById('mgiSharePrice');
  const valuationLabel = document.getElementById('mgiValuationLabel');
  const summaryShares = document.getElementById('mgiSummaryShares');
  const summaryShare = document.getElementById('mgiSummaryShare');
  const summaryRemaining = document.getElementById('mgiSummaryRemaining');
  const allocationButtons = Array.from(document.querySelectorAll('.mgi-allocation button'));

  function money(value){return '$' + Number(value).toLocaleString('en-US',{maximumFractionDigits:0});}
  function number(value,digits){return Number(value).toLocaleString('en-US',{minimumFractionDigits:digits || 0,maximumFractionDigits:digits || 0});}
  function percent(value){return Number(value).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + '%';}

  function updateInvestorEstimate(){
    if(!amountInput || !sharePriceInput || !summaryShares || !summaryShare || !summaryRemaining){return;}
    const amount = Math.max(0, Number(amountInput.value) || 0);
    const price = Math.max(0.01, Number(sharePriceInput.value) || 5);
    const shares = Math.floor(amount / price);
    const sharePercent = totalShares ? (shares / totalShares) * 100 : 0;
    const remaining = Math.max(0, totalShares - shares);
    summaryShares.textContent = number(shares);
    summaryShare.textContent = percent(sharePercent);
    summaryRemaining.textContent = number(remaining);
    if(valuationLabel){valuationLabel.textContent = money(totalShares * price) + ' implied value';}
    allocationButtons.forEach(function(button){button.classList.toggle('is-active', Number(button.dataset.amount) === amount);});
  }

  allocationButtons.forEach(function(button){button.addEventListener('click', function(){amountInput.value = button.dataset.amount || '50000';updateInvestorEstimate();});});
  if(amountInput){amountInput.addEventListener('input', updateInvestorEstimate);}
  if(sharePriceInput){sharePriceInput.addEventListener('input', updateInvestorEstimate);}
  updateInvestorEstimate();

  const chartData = {
    '25K': {value:'5,000',change:'$25K at $5.00/share = 0.50% of available shares',pill:'5,000 shares',cx:'74',cy:'306'},
    '50K': {value:'10,000',change:'$50K at $5.00/share = 1.00% of available shares',pill:'10,000 shares',cx:'230',cy:'288'},
    '100K': {value:'20,000',change:'$100K at $5.00/share = 2.00% of available shares',pill:'20,000 shares',cx:'386',cy:'252'},
    '250K': {value:'50,000',change:'$250K at $5.00/share = 5.00% of available shares',pill:'50,000 shares',cx:'542',cy:'162'},
    '500K': {value:'100,000',change:'$500K at $5.00/share = 10.00% of available shares',pill:'100,000 shares',cx:'698',cy:'72'}
  };
  const periodButtons = Array.from(document.querySelectorAll('.mgi-periods button'));
  const mainDot = document.getElementById('mgiMainDot');
  const systemValue = document.getElementById('mgiSystemValue');
  const systemChange = document.getElementById('mgiSystemChange');
  const chartPill = document.getElementById('mgiChartPill');
  periodButtons.forEach(function(button){button.addEventListener('click', function(){const data = chartData[button.dataset.period || '50K'];if(!data){return;}periodButtons.forEach(function(item){item.classList.toggle('is-active', item === button);});if(mainDot){mainDot.setAttribute('cx', data.cx);mainDot.setAttribute('cy', data.cy);}if(systemValue){systemValue.textContent = data.value;}if(systemChange){systemChange.textContent = data.change;}if(chartPill){chartPill.textContent = data.pill;}});});
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
