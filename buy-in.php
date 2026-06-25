<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Buy-In Market | Microgifter';
$page_section = 'buy-in';
$header_mode = 'public';
$page_body_class = 'mg-buy-in-page';
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
  --bi-ink:#050505;
  --bi-text:#151515;
  --bi-muted:#63615d;
  --bi-bg:#f4f1ea;
  --bi-card:rgba(255,255,255,.76);
  --bi-line:rgba(0,0,0,.09);
  --bi-gold:#d9a735;
  --bi-green:#13b45f;
  --bi-red:#dc2626;
  --bi-blue:#315fce;
  --bi-max:1180px;
}
.mg-buy-in-page{background:var(--bi-bg)!important;color:var(--bi-text)}
.mg-buy-in-page .mg-main{background:var(--bi-bg);overflow:hidden}
.bi-wrap,.bi-wrap *{box-sizing:border-box}
.bi-wrap{position:relative;isolation:isolate;min-height:100vh;background:var(--bi-bg);font-family:Inter,"Helvetica Neue",Arial,sans-serif;color:var(--bi-text)}
.bi-wrap:before{content:"";position:absolute;inset:0 0 auto;height:860px;z-index:0;background:
  radial-gradient(circle at 18% 6%,rgba(217,167,53,.34),transparent 28%),
  radial-gradient(circle at 76% 18%,rgba(49,95,206,.18),transparent 30%),
  url('/images/header_gradient_bg.png') center top/cover no-repeat;opacity:.92}
.bi-wrap:after{content:"";position:absolute;inset:0 0 auto;height:940px;z-index:1;pointer-events:none;background:
  linear-gradient(90deg,rgba(244,241,234,.98),rgba(244,241,234,.82) 38%,rgba(244,241,234,.22) 72%,rgba(244,241,234,.1)),
  linear-gradient(180deg,rgba(255,255,255,.34),rgba(255,255,255,.06) 58%,var(--bi-bg))}
.bi-shell{position:relative;z-index:2;width:min(var(--bi-max),calc(100% - 48px));margin:auto;padding:122px 0 88px}
.bi-hero{display:grid;grid-template-columns:minmax(0,.92fr) minmax(420px,1.08fr);gap:42px;align-items:center;padding-top:18px}
.bi-kicker{display:inline-flex;min-height:28px;align-items:center;padding:0 13px;border:1px solid var(--bi-line);border-radius:999px;background:rgba(255,255,255,.7);font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}
.bi-title{margin:28px 0 0;max-width:760px;color:var(--bi-ink);font-family:"Helvetica Neue",Inter,Arial,sans-serif;font-size:clamp(48px,5.6vw,86px);line-height:.9;letter-spacing:-.08em;font-weight:930;text-wrap:balance}
.bi-lede{margin:28px 0 0;max-width:640px;color:#121212;font-size:clamp(18px,1.8vw,25px);line-height:1.12;letter-spacing:-.046em;font-weight:620}
.bi-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:34px}
.bi-btn{display:inline-flex;align-items:center;justify-content:center;gap:12px;min-height:46px;padding:0 20px;border-radius:10px;text-decoration:none!important;font-size:13px;font-weight:930;letter-spacing:-.015em}
.bi-btn.primary{background:#050505;color:#fff!important;box-shadow:0 18px 42px rgba(0,0,0,.17)}
.bi-btn.secondary{border:1px solid rgba(0,0,0,.12);background:rgba(255,255,255,.68);color:#050505!important}
.bi-proof{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}
.bi-proof span{display:inline-flex;align-items:center;min-height:30px;padding:0 12px;border:1px solid rgba(0,0,0,.08);border-radius:999px;background:rgba(255,255,255,.64);font-size:11px;font-weight:850;color:#242424}
.bi-market-card{position:relative;min-height:520px;padding:20px;border:1px solid rgba(0,0,0,.09);border-radius:28px;background:rgba(255,255,255,.72);box-shadow:0 28px 90px rgba(0,0,0,.12);backdrop-filter:blur(18px);overflow:hidden}
.bi-market-card:before{content:"";position:absolute;inset:-120px -130px auto auto;width:310px;height:310px;border-radius:50%;background:rgba(217,167,53,.22);filter:blur(6px)}
.bi-market-head{position:relative;display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:18px;border-radius:20px;background:#050505;color:#fff}
.bi-market-head small{display:block;color:rgba(255,255,255,.58);font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
.bi-market-head h2{margin:7px 0 0;color:#fff;font-size:28px;line-height:.94;letter-spacing:-.06em;font-weight:930}
.bi-live{display:inline-flex;align-items:center;gap:7px;min-height:28px;padding:0 11px;border-radius:999px;background:rgba(19,180,95,.14);color:#86efac;font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}
.bi-live:before{content:"";width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.14)}
.bi-price-grid{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
.bi-stat{min-height:112px;padding:16px;border:1px solid rgba(0,0,0,.075);border-radius:18px;background:rgba(255,255,255,.82)}
.bi-stat span{display:block;color:#68645f;font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.bi-stat strong{display:block;margin-top:15px;color:#050505;font-size:31px;line-height:.9;letter-spacing:-.06em;font-weight:930}
.bi-stat em{display:block;margin-top:8px;color:var(--bi-green);font-style:normal;font-size:11px;font-weight:900}
.bi-position{position:relative;margin-top:14px;padding:18px;border:1px solid rgba(0,0,0,.075);border-radius:20px;background:rgba(255,255,255,.86)}
.bi-position-top{display:flex;align-items:center;justify-content:space-between;gap:16px}
.bi-position h3{margin:0;color:#050505;font-size:20px;line-height:1;font-weight:930;letter-spacing:-.045em}
.bi-position-pill{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:999px;background:#fbf0d1;color:#8a650c;font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase}
.bi-bars{display:grid;gap:9px;margin-top:20px}
.bi-bar{display:grid;grid-template-columns:92px minmax(0,1fr) 72px;gap:11px;align-items:center;color:#202020;font-size:11px;font-weight:820}
.bi-bar-track{height:9px;border-radius:999px;background:#e8e1d6;overflow:hidden}
.bi-bar-fill{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#050505,#d9a735)}
.bi-actions-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:18px}
.bi-mini-action{display:flex;align-items:center;justify-content:center;min-height:36px;border:1px solid rgba(0,0,0,.08);border-radius:10px;background:#fff;color:#111;font-size:11px;font-weight:930;text-decoration:none}
.bi-section{margin-top:72px}
.bi-section-head{max-width:720px}
.bi-section-kicker{display:inline-flex;min-height:26px;align-items:center;padding:0 12px;border-radius:999px;background:#050505;color:#fff;font-size:10px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}
.bi-section-title{margin:22px 0 0;color:#050505;font-size:clamp(34px,3.8vw,56px);line-height:.94;letter-spacing:-.07em;font-weight:930;text-wrap:balance}
.bi-section-copy{max-width:660px;margin:20px 0 0;color:#34312e;font-size:17px;line-height:1.38;letter-spacing:-.035em;font-weight:560}
.bi-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:34px}
.bi-feature-card{min-height:262px;padding:28px;border:1px solid rgba(0,0,0,.08);border-radius:20px;background:var(--bi-card);box-shadow:0 18px 60px rgba(0,0,0,.055);backdrop-filter:blur(12px)}
.bi-feature-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:16px;background:#050505;color:#fff;font-size:18px;font-weight:900}
.bi-feature-card h3{margin:25px 0 0;color:#070707;font-size:23px;line-height:.98;font-weight:930;letter-spacing:-.055em}
.bi-feature-card p{margin:15px 0 0;color:#4c4740;font-size:14px;line-height:1.43;font-weight:560}
.bi-trade{display:grid;grid-template-columns:minmax(0,.84fr) minmax(420px,1.16fr);gap:24px;align-items:stretch;margin-top:34px}
.bi-trade-panel{padding:34px;border-radius:24px;background:#050505;color:#fff;box-shadow:0 24px 80px rgba(0,0,0,.16)}
.bi-trade-panel h3{margin:0;color:#fff;font-size:36px;line-height:.94;letter-spacing:-.065em;font-weight:930}
.bi-trade-panel p{margin:18px 0 0;color:rgba(255,255,255,.72);font-size:15px;line-height:1.45;font-weight:560}
.bi-redemption-list{display:grid;gap:10px;margin-top:28px}
.bi-redemption-item{display:grid;grid-template-columns:42px 1fr auto;gap:12px;align-items:center;min-height:58px;padding:10px 12px;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(255,255,255,.08)}
.bi-redemption-item b{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.14);font-size:18px}
.bi-redemption-item strong{display:block;color:#fff;font-size:13px;font-weight:900}
.bi-redemption-item span{display:block;margin-top:3px;color:rgba(255,255,255,.56);font-size:11px;line-height:1.24;font-weight:620}
.bi-redemption-item em{font-style:normal;color:#facc15;font-size:11px;font-weight:950;white-space:nowrap}
.bi-table-card{padding:22px;border:1px solid rgba(0,0,0,.08);border-radius:24px;background:rgba(255,255,255,.78);box-shadow:0 18px 64px rgba(0,0,0,.055)}
.bi-table-card h3{margin:0 0 16px;color:#050505;font-size:24px;line-height:1;font-weight:930;letter-spacing:-.055em}
.bi-phase-row{display:grid;grid-template-columns:1fr 1fr 98px;gap:12px;align-items:center;min-height:64px;padding:12px 0;border-top:1px solid rgba(0,0,0,.075)}
.bi-phase-row:first-of-type{border-top:0}
.bi-phase-row span{display:block;color:#68615a;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}
.bi-phase-row strong{display:block;margin-top:4px;color:#111;font-size:13px;font-weight:900;line-height:1.2}
.bi-phase-row em{display:inline-flex;align-items:center;justify-content:center;min-height:30px;border-radius:999px;background:#f8f1df;color:#8a650c;font-style:normal;font-size:11px;font-weight:950}
.bi-flow-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:34px;counter-reset:flow}
.bi-flow-card{position:relative;min-height:208px;padding:26px 22px 22px;border:1px solid rgba(0,0,0,.08);border-radius:18px;background:rgba(255,255,255,.74)}
.bi-flow-card:before{counter-increment:flow;content:"0" counter(flow);display:inline-flex;align-items:center;justify-content:center;width:36px;height:30px;border-radius:999px;background:#050505;color:#fff;font-size:11px;font-weight:950}
.bi-flow-card h3{margin:22px 0 0;color:#060606;font-size:21px;line-height:.98;font-weight:930;letter-spacing:-.05em}
.bi-flow-card p{margin:14px 0 0;color:#504b44;font-size:13px;line-height:1.4;font-weight:560}
.bi-example{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.72fr);gap:18px;align-items:stretch;margin-top:34px}
.bi-example-main{padding:36px;border-radius:24px;background:linear-gradient(135deg,#050505,#171717 58%,#050505);color:#fff;box-shadow:0 24px 90px rgba(0,0,0,.18)}
.bi-example-main small{display:inline-flex;min-height:26px;align-items:center;padding:0 11px;border-radius:999px;background:rgba(255,255,255,.1);color:#f7d77b;font-size:10px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}
.bi-example-main h3{margin:24px 0 0;max-width:560px;color:#fff;font-size:clamp(34px,3.6vw,54px);line-height:.94;letter-spacing:-.07em;font-weight:930}
.bi-example-main p{max-width:620px;margin:20px 0 0;color:rgba(255,255,255,.74);font-size:16px;line-height:1.42;font-weight:560}
.bi-example-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:26px}
.bi-example-tags span{display:inline-flex;align-items:center;min-height:31px;padding:0 12px;border:1px solid rgba(255,255,255,.13);border-radius:999px;background:rgba(255,255,255,.08);color:#fff;font-size:11px;font-weight:850}
.bi-example-side{padding:26px;border:1px solid rgba(0,0,0,.08);border-radius:24px;background:rgba(255,255,255,.78)}
.bi-buy-box{display:grid;gap:10px}
.bi-buy-row{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;min-height:54px;padding:0 14px;border:1px solid rgba(0,0,0,.08);border-radius:14px;background:#fff}
.bi-buy-row strong{color:#111;font-size:14px;font-weight:930}
.bi-buy-row span{color:#6a625c;font-size:11px;font-weight:740}
.bi-buy-row b{font-size:14px;font-weight:930}
.bi-note{margin-top:16px;padding:16px;border-radius:16px;background:#f8f1df;color:#5f4611;font-size:12px;line-height:1.42;font-weight:680}
.bi-final{margin-top:72px;padding:46px;border-radius:26px;background:#fff;border:1px solid rgba(0,0,0,.08);box-shadow:0 28px 90px rgba(0,0,0,.08);text-align:center}
.bi-final h2{max-width:780px;margin:0 auto;color:#050505;font-size:clamp(36px,4vw,60px);line-height:.94;letter-spacing:-.075em;font-weight:930;text-wrap:balance}
.bi-final p{max-width:720px;margin:20px auto 0;color:#3b3835;font-size:16px;line-height:1.45;font-weight:560}
.bi-final .bi-actions{justify-content:center}
@media(max-width:1080px){
  .bi-hero,.bi-trade,.bi-example{grid-template-columns:1fr}
  .bi-market-card{min-height:auto}
  .bi-card-grid{grid-template-columns:1fr 1fr}
  .bi-flow-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:760px){
  .bi-shell{width:min(100% - 28px,var(--bi-max));padding-top:104px}
  .bi-hero{gap:28px}
  .bi-title{font-size:clamp(44px,15vw,64px)}
  .bi-lede{font-size:18px}
  .bi-market-head,.bi-position-top{display:grid}
  .bi-price-grid,.bi-card-grid,.bi-flow-grid{grid-template-columns:1fr}
  .bi-actions-row{grid-template-columns:1fr 1fr}
  .bi-trade-panel,.bi-example-main,.bi-final{padding:30px 24px}
  .bi-phase-row{grid-template-columns:1fr}
  .bi-bar{grid-template-columns:78px minmax(0,1fr) 56px}
}
</style>

<section class="bi-wrap" aria-labelledby="buy-in-title">
  <div class="bi-shell">
    <section class="bi-hero">
      <div>
        <span class="bi-kicker">Buy-In Market</span>
        <h1 class="bi-title" id="buy-in-title">A market for early belief.</h1>
        <p class="bi-lede">Microgifter turns bands, artists, creators, events, and local brands into limited digital buy-in markets where supporters can buy early, hold, gift, resell, or redeem shares for real digital products.</p>
        <div class="bi-actions">
          <a class="bi-btn primary" href="/signup.php">Create Your Market <span>→</span></a>
          <a class="bi-btn secondary" href="/learn-more.php">Book A Demo</a>
        </div>
        <div class="bi-proof" aria-label="Buy-in market highlights">
          <span>Digital shares</span>
          <span>Gift cards and event tickets</span>
          <span>Merch booth redemption</span>
          <span>Milestone reissues</span>
        </div>
      </div>

      <aside class="bi-market-card" aria-label="Example buy-in market">
        <div class="bi-market-head">
          <div>
            <small>Example Market</small>
            <h2>The Band First 10,000</h2>
          </div>
          <span class="bi-live">Live Market</span>
        </div>
        <div class="bi-price-grid">
          <div class="bi-stat"><span>Launch Price</span><strong>$10</strong><em>Original issue</em></div>
          <div class="bi-stat"><span>Floor Price</span><strong>$24</strong><em>+140%</em></div>
          <div class="bi-stat"><span>Supply</span><strong>10K</strong><em>Series 1</em></div>
        </div>
        <div class="bi-position">
          <div class="bi-position-top">
            <h3>Your position</h3>
            <span class="bi-position-pill">250 shares owned</span>
          </div>
          <div class="bi-bars" aria-label="Example position breakdown">
            <div class="bi-bar"><span>Buy cost</span><div class="bi-bar-track"><span class="bi-bar-fill" style="width:42%"></span></div><strong>$2,500</strong></div>
            <div class="bi-bar"><span>Market</span><div class="bi-bar-track"><span class="bi-bar-fill" style="width:78%"></span></div><strong>$6,000</strong></div>
            <div class="bi-bar"><span>Redeem</span><div class="bi-bar-track"><span class="bi-bar-fill" style="width:61%"></span></div><strong>$4,250</strong></div>
          </div>
          <div class="bi-actions-row">
            <a class="bi-mini-action" href="/signup.php">Buy</a>
            <a class="bi-mini-action" href="/signup.php">Sell</a>
            <a class="bi-mini-action" href="/signup.php">Redeem</a>
            <a class="bi-mini-action" href="/signup.php">Gift</a>
          </div>
        </div>
      </aside>
    </section>

    <section class="bi-section" aria-labelledby="what-is-buy-in">
      <div class="bi-section-head">
        <span class="bi-section-kicker">The concept</span>
        <h2 class="bi-section-title" id="what-is-buy-in">Supporters buy into momentum before the crowd arrives.</h2>
        <p class="bi-section-copy">A creator or merchant issues a limited series of buy-in shares. Supporters can buy more than one share, build a position, and later decide whether to hold, resell, gift, or trade that value back into the creator economy.</p>
      </div>
      <div class="bi-card-grid">
        <article class="bi-feature-card">
          <span class="bi-feature-icon">1</span>
          <h3>Buy early</h3>
          <p>Fans buy shares in a person, band, event, restaurant, product drop, or local brand before wider demand shows up.</p>
        </article>
        <article class="bi-feature-card">
          <span class="bi-feature-icon">↔</span>
          <h3>Trade value</h3>
          <p>Owners can list shares on the open market, gift them to friends, or keep their position as the creator grows.</p>
        </article>
        <article class="bi-feature-card">
          <span class="bi-feature-icon">✓</span>
          <h3>Redeem utility</h3>
          <p>Shares can be traded in for Microgift cards, event tickets, merch, VIP access, digital products, or show-day experiences.</p>
        </article>
      </div>
    </section>

    <section class="bi-section" aria-labelledby="redeem-title">
      <div class="bi-section-head">
        <span class="bi-section-kicker">Redemption layer</span>
        <h2 class="bi-section-title" id="redeem-title">Shares become digital products when fans want to cash in with the artist.</h2>
        <p class="bi-section-copy">Because Microgifter already handles digital gifts, tickets, claim codes, and redemption workflows, a share can convert into a Microgift card, show ticket, merch credit, or QR-backed offer without building a separate redemption system.</p>
      </div>

      <div class="bi-trade">
        <div class="bi-trade-panel">
          <h3>Resell for market value or redeem for creator value.</h3>
          <p>If a share trades at $24 on the market, the owner can list it for resale. Or they can redeem shares directly with the creator for approved rewards, tickets, merch, or experiences.</p>
          <div class="bi-redemption-list">
            <div class="bi-redemption-item"><b>🎟</b><div><strong>Event tickets</strong><span>Trade shares for digital ticket access.</span></div><em>QR READY</em></div>
            <div class="bi-redemption-item"><b>🎁</b><div><strong>Microgift cards</strong><span>Convert share value into giftable credit.</span></div><em>SENDABLE</em></div>
            <div class="bi-redemption-item"><b>👕</b><div><strong>Merch at shows</strong><span>Redeem shares at a booth and support the band.</span></div><em>LIVE USE</em></div>
            <div class="bi-redemption-item"><b>⭐</b><div><strong>VIP experiences</strong><span>Meetups, soundcheck, backstage, private drops.</span></div><em>LIMITED</em></div>
          </div>
        </div>

        <aside class="bi-table-card">
          <h3>Example trade-in menu</h3>
          <div class="bi-phase-row"><div><span>Reward</span><strong>Digital album drop</strong></div><div><span>Share Cost</span><strong>3 shares</strong></div><em>Instant</em></div>
          <div class="bi-phase-row"><div><span>Reward</span><strong>Show ticket credit</strong></div><div><span>Share Cost</span><strong>8 shares</strong></div><em>Ticket</em></div>
          <div class="bi-phase-row"><div><span>Reward</span><strong>Limited tour hoodie</strong></div><div><span>Share Cost</span><strong>15 shares</strong></div><em>Merch</em></div>
          <div class="bi-phase-row"><div><span>Reward</span><strong>Meet the band pass</strong></div><div><span>Share Cost</span><strong>40 shares</strong></div><em>VIP</em></div>
          <p class="bi-note">The creator controls the redemption value and catalog. Fans decide whether they would rather redeem with the artist or resell into the market.</p>
        </aside>
      </div>
    </section>

    <section class="bi-section" aria-labelledby="phases-title">
      <div class="bi-section-head">
        <span class="bi-section-kicker">Reissue phases</span>
        <h2 class="bi-section-title" id="phases-title">Creators can issue more shares when they hit public milestones.</h2>
        <p class="bi-section-copy">Instead of dumping unlimited supply, each release is a separate series. The original First 10,000 keeps its early identity, while new followers get a later chance to buy in.</p>
      </div>

      <div class="bi-table-card">
        <h3>Sample issue schedule</h3>
        <div class="bi-phase-row"><div><span>Phase</span><strong>Founder Drop</strong></div><div><span>Trigger</span><strong>Launch the market</strong></div><em>10,000</em></div>
        <div class="bi-phase-row"><div><span>Phase</span><strong>Growth Drop</strong></div><div><span>Trigger</span><strong>100,000 followers</strong></div><em>5,000</em></div>
        <div class="bi-phase-row"><div><span>Phase</span><strong>Breakout Drop</strong></div><div><span>Trigger</span><strong>500,000 followers</strong></div><em>10,000</em></div>
        <div class="bi-phase-row"><div><span>Phase</span><strong>Mass Market Drop</strong></div><div><span>Trigger</span><strong>1,000,000 followers</strong></div><em>25,000</em></div>
      </div>
    </section>

    <section class="bi-section" aria-labelledby="flow-title">
      <div class="bi-section-head">
        <span class="bi-section-kicker">How it works</span>
        <h2 class="bi-section-title" id="flow-title">Simple enough for fans. Deep enough to create a real market.</h2>
      </div>
      <div class="bi-flow-grid">
        <article class="bi-flow-card"><h3>Create a market</h3><p>The artist defines a name, story, original supply, starting price, redemption rules, and future milestone drops.</p></article>
        <article class="bi-flow-card"><h3>Fans buy shares</h3><p>Supporters buy one share or a larger position. Their account tracks quantity, average price, and owned series.</p></article>
        <article class="bi-flow-card"><h3>Shares move</h3><p>Owners can gift, hold, list shares for resale, or trade them back to the creator for digital products.</p></article>
        <article class="bi-flow-card"><h3>Momentum grows</h3><p>Follower, event, sales, and release milestones can trigger new share phases without erasing early holders.</p></article>
      </div>
    </section>

    <section class="bi-section" aria-labelledby="example-title">
      <div class="bi-example">
        <div class="bi-example-main">
          <small>Example launch</small>
          <h3 id="example-title">The Band First 10,000</h3>
          <p>A band issues 10,000 launch shares at $10 each. Early fans buy positions, trade them, or redeem shares for digital tickets, merch, signed products, and show-day experiences.</p>
          <div class="bi-example-tags">
            <span>Original shares stay scarce</span>
            <span>Merch booth redemption</span>
            <span>Digital ticket conversion</span>
            <span>Open resale market</span>
            <span>Follower-based reissues</span>
          </div>
        </div>
        <aside class="bi-example-side">
          <div class="bi-buy-box" aria-label="Example share bundles">
            <div class="bi-buy-row"><div><strong>Starter fan</strong><br><span>1 share</span></div><b>$10</b></div>
            <div class="bi-buy-row"><div><strong>Early holder</strong><br><span>10 shares</span></div><b>$100</b></div>
            <div class="bi-buy-row"><div><strong>Core supporter</strong><br><span>100 shares</span></div><b>$1,000</b></div>
            <div class="bi-buy-row"><div><strong>Anchor holder</strong><br><span>500 shares</span></div><b>$5,000</b></div>
          </div>
          <p class="bi-note">Fans do not have to pick one path. They can sell part of their position, redeem part for merch, and keep the rest.</p>
        </aside>
      </div>
    </section>

    <section class="bi-final" aria-labelledby="final-title">
      <h2 id="final-title">Microgifter turns support into something fans can hold, trade, gift, or redeem.</h2>
      <p>Buy-In Markets give artists, creators, founders, events, and local brands a new way to convert early belief into revenue, demand, and long-term community value.</p>
      <div class="bi-actions">
        <a class="bi-btn primary" href="/signup.php">Start a Buy-In Market <span>→</span></a>
        <a class="bi-btn secondary" href="/learn-more.php">Talk Through the Concept</a>
      </div>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>