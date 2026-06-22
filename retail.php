<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Retail Subscriptions | Microgifter';
$page_section = 'retail-subscriptions';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];

$page_manifest = [
    'id' => 'retail-subscriptions',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Book Demo', 'href' => '/learn-more.php'],
            
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'retail-subscriptions',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
:root{
  --rs-dark:#071225;
  --rs-text:#172033;
  --rs-muted:#64748b;
  --rs-border:#dbe5f1;
  --rs-purple:#7c3aed;
  --rs-purple-2:#6d5dfc;
  --rs-teal:#20bfd2;
  --rs-green:#16a34a;
  --rs-soft:#f8fafc;
}

.rs-page{
  color:var(--rs-text);
  background:#fff;
}

.rs-section{
  position:relative;
  overflow:hidden;
  padding:140px 0;
  border-bottom:1px solid var(--rs-border);
}

.rs-section::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.46;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.rs-container{
  position:relative;
  z-index:1;
  width:min(1180px,92%);
  margin:0 auto;
}

.rs-badge{
  display:inline-flex;
  align-items:center;
  gap:9px;
  min-height:36px;
  padding:0 14px;
  border:1px solid #d8e4f4;
  border-radius:999px;
  background:rgba(255,255,255,.88);
  color:var(--rs-dark);
  font-size:13px;
  font-weight:950;
  box-shadow:0 12px 28px rgba(15,23,42,.06);
}

.rs-badge::before{
  content:"";
  width:9px;
  height:9px;
  border-radius:999px;
  background:var(--rs-purple);
  box-shadow:0 0 0 6px rgba(124,58,237,.1);
}

.rs-hero{
  min-height:calc(100svh - 64px);
  padding-top:84px;
  padding-bottom:110px;
  display:flex;
  align-items:center;
  background:
    radial-gradient(circle at 16% 18%,rgba(255,255,255,.98),transparent 30%),
    radial-gradient(circle at 82% 22%,rgba(237,233,254,.88),transparent 34%),
    radial-gradient(circle at 74% 72%,rgba(204,251,241,.44),transparent 30%),
    linear-gradient(180deg,#fff 0%,#f8fafc 62%,#eef2f7 100%);
}

.rs-hero-grid{
  display:grid;
  grid-template-columns:.92fr 1.08fr;
  gap:56px;
  align-items:center;
}

.rs-hero-copy h1{
  margin:20px 0 0;
  max-width:650px;
  color:var(--rs-dark);
  font-size:clamp(48px,6vw,82px);
  line-height:.93;
  letter-spacing:-.078em;
}

.rs-hero-copy p{
  margin:24px 0 0;
  max-width:620px;
  color:var(--rs-muted);
  font-size:20px;
  line-height:1.58;
}

.rs-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:30px;
}

.rs-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:52px;
  padding:0 22px;
  border-radius:14px;
  text-decoration:none;
  font-weight:950;
  transition:.2s ease;
}

.rs-btn:hover{ transform:translateY(-2px); }

.rs-btn-primary{
  color:#fff;
  background:linear-gradient(135deg,var(--rs-purple-2),var(--rs-purple));
  box-shadow:0 16px 34px rgba(109,93,252,.22);
}

.rs-btn-secondary{
  color:var(--rs-dark);
  background:#fff;
  border:1px solid var(--rs-border);
}

.rs-hero-points{
  display:grid;
  gap:10px;
  margin-top:26px;
}

.rs-hero-points span{
  display:flex;
  align-items:center;
  gap:10px;
  color:#475569;
  font-size:14px;
  font-weight:850;
}

.rs-hero-points span::before{
  content:"✓";
  width:24px;
  height:24px;
  display:grid;
  place-items:center;
  border-radius:999px;
  color:#fff;
  font-size:12px;
  background:linear-gradient(135deg,var(--rs-teal),var(--rs-green));
}

.rs-hero-visual{
  position:relative;
  min-height:680px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.rs-hero-visual::before{
  content:"";
  position:absolute;
  width:86%;
  aspect-ratio:1/1;
  border-radius:999px;
  background:radial-gradient(circle,rgba(124,58,237,.18),rgba(32,191,210,.08) 50%,transparent 72%);
  filter:blur(12px);
}

.rs-flow-line{
  position:absolute;
  top:50%;
  left:50%;
  width:420px;
  height:420px;
  border-radius:999px;
  transform:translate(-50%,-50%);
  border:1px dashed rgba(124,58,237,.22);
  opacity:.7;
}

.rs-phone{
  position:relative;
  z-index:2;
  width:320px;
  height:620px;
  border-radius:44px;
  padding:14px;
  background:linear-gradient(145deg,#111827,#0f172a 58%,#243047);
  box-shadow:0 38px 90px rgba(15,23,42,.28), inset 0 1px 0 rgba(255,255,255,.08);
  transform:rotate(-12deg) perspective(1400px) rotateY(-16deg);
}

.rs-phone-screen{
  position:relative;
  width:100%;
  height:100%;
  overflow:hidden;
  border-radius:32px;
  background:
    radial-gradient(circle at 82% 10%,rgba(196,181,253,.35),transparent 20%),
    linear-gradient(180deg,#ffffff 0%,#f8fafc 72%,#eef2f7 100%);
}

.rs-status-bar{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:16px 18px 10px;
  color:#64748b;
  font-size:12px;
  font-weight:900;
}

.rs-feed-header{ padding:0 18px 16px; }

.rs-feed-header strong{
  display:block;
  color:var(--rs-dark);
  font-size:24px;
  letter-spacing:-.05em;
}

.rs-feed-header span{
  display:block;
  margin-top:6px;
  color:var(--rs-muted);
  font-size:13px;
}

.rs-phone-card{
  margin:0 16px 14px;
  padding:15px;
  border:1px solid #e2e8f0;
  border-radius:22px;
  background:#fff;
  box-shadow:0 12px 28px rgba(15,23,42,.06);
}

.rs-phone-tag{
  display:inline-flex;
  align-items:center;
  min-height:26px;
  padding:0 9px;
  border-radius:999px;
  background:rgba(124,58,237,.09);
  color:var(--rs-purple);
  font-size:11px;
  font-weight:950;
  letter-spacing:.04em;
  text-transform:uppercase;
}

.rs-phone-card h3{
  margin:12px 0 8px;
  color:var(--rs-dark);
  font-size:18px;
  line-height:1.12;
  letter-spacing:-.04em;
}

.rs-phone-card p{
  margin:0;
  color:var(--rs-muted);
  font-size:13px;
  line-height:1.5;
}

.rs-phone-meta{
  display:flex;
  justify-content:space-between;
  gap:12px;
  margin-top:12px;
  color:#475569;
  font-size:12px;
  font-weight:800;
}

.rs-phone-card.is-accent{
  background:linear-gradient(135deg,#f7f3ff,#ffffff 65%);
}

.rs-phone-card.is-ticket{
  background:linear-gradient(135deg,#ecfeff,#ffffff 65%);
}

.rs-feed-float{
  position:absolute;
  z-index:1;
  width:240px;
  padding:16px;
  border:1px solid rgba(219,229,241,.95);
  border-radius:24px;
  background:rgba(255,255,255,.96);
  box-shadow:0 28px 70px rgba(15,23,42,.14);
  backdrop-filter:blur(18px);
}

.rs-feed-float::after{
  content:"";
  position:absolute;
  width:32px;
  height:2px;
  background:linear-gradient(90deg,rgba(124,58,237,.7),rgba(32,191,210,.3));
}

.rs-feed-float h3{
  margin:12px 0 8px;
  color:var(--rs-dark);
  font-size:19px;
  line-height:1.08;
  letter-spacing:-.04em;
}

.rs-feed-float p{
  margin:0;
  color:var(--rs-muted);
  font-size:13px;
  line-height:1.55;
}

.rs-feed-float .rs-feed-mini{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  margin-top:14px;
  color:#475569;
  font-size:12px;
  font-weight:850;
}

.rs-feed-float-top-left{
  top:68px;
  left:18px;
  transform:rotate(-7deg);
}
.rs-feed-float-top-left::after{
  right:-44px;
  bottom:26px;
  transform:rotate(18deg);
}

.rs-feed-float-mid-left{
  top:290px;
  left:-6px;
  transform:rotate(5deg);
}
.rs-feed-float-mid-left::after{
  right:-48px;
  top:50%;
  transform:rotate(-8deg);
}

.rs-feed-float-bottom-right{
  right:-4px;
  bottom:118px;
  transform:rotate(8deg);
}
.rs-feed-float-bottom-right::after{
  left:-45px;
  top:40%;
  transform:rotate(12deg);
}

.rs-feed-float-top-right{
  top:110px;
  right:26px;
  transform:rotate(6deg);
}
.rs-feed-float-top-right::after{
  left:-48px;
  bottom:30px;
  transform:rotate(-15deg);
}

.rs-heading{
  max-width:820px;
  margin:0 auto 60px;
  text-align:center;
}

.rs-heading h2{
  margin:18px 0 16px;
  color:var(--rs-dark);
  font-size:clamp(40px,5vw,66px);
  line-height:.96;
  letter-spacing:-.065em;
}

.rs-heading p{
  margin:0 auto;
  max-width:690px;
  color:var(--rs-muted);
  font-size:18px;
  line-height:1.58;
}

.rs-benefits{
  background:
    radial-gradient(circle at 50% 10%,rgba(237,233,254,.72),transparent 36%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.rs-grid-3{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:18px;
}

.rs-card{
  padding:28px;
  border:1px solid var(--rs-border);
  border-radius:24px;
  background:rgba(255,255,255,.96);
  box-shadow:0 20px 54px rgba(15,23,42,.08);
}

.rs-card-icon{
  width:54px;
  height:54px;
  display:grid;
  place-items:center;
  border-radius:17px;
  color:var(--rs-purple);
  background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(32,191,210,.1));
  border:1px solid rgba(124,58,237,.12);
  font-size:25px;
}

.rs-card h3{
  margin:20px 0 9px;
  color:var(--rs-dark);
  font-size:23px;
  line-height:1.08;
  letter-spacing:-.045em;
}

.rs-card p{
  margin:0;
  color:var(--rs-muted);
  line-height:1.55;
  font-size:15px;
}

.rs-use-cases{
  background:
    radial-gradient(circle at 16% 50%,rgba(204,251,241,.46),transparent 30%),
    linear-gradient(180deg,#f8fafc,#fff);
}

.rs-use-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:18px;
}

.rs-use{
  min-height:250px;
  padding:26px;
  border:1px solid var(--rs-border);
  border-radius:24px;
  background:#fff;
  box-shadow:0 18px 50px rgba(15,23,42,.07);
}

.rs-use-number{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border-radius:999px;
  color:#fff;
  background:linear-gradient(135deg,var(--rs-purple),var(--rs-teal));
  font-weight:950;
}

.rs-use h3{
  margin:22px 0 10px;
  color:var(--rs-dark);
  font-size:21px;
  letter-spacing:-.04em;
}

.rs-use p{
  margin:0;
  color:var(--rs-muted);
  line-height:1.55;
  font-size:14px;
}

.rs-cta{
  background:
    radial-gradient(circle at 18% 24%,rgba(196,181,253,.34),transparent 30%),
    radial-gradient(circle at 84% 74%,rgba(165,243,252,.26),transparent 32%),
    linear-gradient(135deg,#071225 0%,#111b35 56%,#25124e 100%);
  color:#fff;
}

.rs-cta-card{
  width:min(980px,100%);
  margin:0 auto;
  padding:64px;
  border:1px solid rgba(255,255,255,.14);
  border-radius:34px;
  background:rgba(255,255,255,.07);
  box-shadow:0 34px 100px rgba(0,0,0,.28);
  backdrop-filter:blur(18px);
  text-align:center;
}

.rs-cta-card h2{
  margin:20px auto 16px;
  max-width:760px;
  color:#fff;
  font-size:clamp(42px,5vw,68px);
  line-height:.96;
  letter-spacing:-.068em;
}

.rs-cta-card p{
  max-width:680px;
  margin:0 auto;
  color:#cbd5e1;
  font-size:18px;
  line-height:1.6;
}

.rs-cta-card .rs-actions{ justify-content:center; }

#retail-subscriptions-footer{
  position:relative;
  z-index:2;
  width:100%;
  padding:84px 0 34px;
  border-top:1px solid #e2e8f0;
  background:#fff;
  color:#071225;
  box-sizing:border-box;
}

#retail-subscriptions-footer *{ box-sizing:border-box; }

.rs-footer-inner{
  width:min(1180px,92%);
  margin:0 auto;
}

.rs-footer-grid{
  display:grid;
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:48px;
  align-items:start;
}

.rs-footer-logo{
  display:inline-flex;
  align-items:center;
  gap:11px;
  color:#071225;
  text-decoration:none;
  font-size:24px;
  font-weight:950;
  letter-spacing:-.045em;
}

.rs-footer-mark{
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:14px;
  color:#fff;
  background:linear-gradient(135deg,#7c3aed,#20bfd2);
  box-shadow:0 12px 26px rgba(124,58,237,.18);
}

.rs-footer-brand p{
  margin:18px 0 0;
  color:#64748b;
  font-size:14px;
  line-height:1.6;
}

.rs-footer-socials{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-top:24px;
}

.rs-footer-socials a{
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
}

.rs-footer-column h3{
  margin:7px 0 18px;
  color:#071225;
  font-size:14px;
  font-weight:950;
  letter-spacing:.065em;
  text-transform:uppercase;
}

.rs-footer-column nav{
  display:grid;
  gap:13px;
}

.rs-footer-column a{
  color:#64748b;
  text-decoration:none;
  font-size:14px;
  font-weight:720;
}

.rs-footer-bottom{
  display:flex;
  justify-content:space-between;
  gap:24px;
  margin-top:66px;
  padding-top:24px;
  border-top:1px solid #e2e8f0;
  color:#94a3b8;
  font-size:12px;
}

.rs-footer-bottom-links{
  display:flex;
  flex-wrap:wrap;
  gap:18px;
}

.rs-footer-bottom a{
  color:#64748b;
  text-decoration:none;
}

@media(max-width:1040px){
  .rs-hero-grid,
  .rs-grid-3{
    grid-template-columns:1fr;
  }

  .rs-hero-copy{
    text-align:center;
  }

  .rs-hero-copy p{
    margin-left:auto;
    margin-right:auto;
  }

  .rs-actions,
  .rs-hero-points{
    justify-content:center;
  }

  .rs-hero-points{
    justify-items:center;
  }

  .rs-use-grid{
    grid-template-columns:repeat(2,1fr);
  }
}

@media(max-width:860px){
  .rs-footer-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }

  .rs-hero-visual{
    min-height:760px;
  }
}

@media(max-width:680px){
  .rs-section{
    padding:88px 0;
  }

  .rs-hero{
    min-height:auto;
    padding-top:56px;
    padding-bottom:80px;
  }

  .rs-hero-copy{
    text-align:left;
  }

  .rs-actions{
    display:grid;
  }

  .rs-btn{
    width:100%;
  }

  .rs-hero-points{
    justify-items:start;
  }

  .rs-hero-visual{
    min-height:680px;
    transform:scale(.88);
    transform-origin:center top;
  }

  .rs-feed-float{
    width:190px;
    padding:14px;
  }

  .rs-feed-float-top-left{
    top:24px;
    left:-6px;
  }

  .rs-feed-float-top-right{
    top:90px;
    right:-6px;
  }

  .rs-feed-float-mid-left{
    top:286px;
    left:-8px;
  }

  .rs-feed-float-bottom-right{
    right:-10px;
    bottom:80px;
  }

  .rs-use-grid{
    grid-template-columns:1fr;
  }

  .rs-cta-card{
    padding:40px 22px;
    border-radius:24px;
  }

  .rs-footer-grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .rs-footer-bottom{
    display:grid;
  }
}
</style>

<main class="rs-page">
  <section class="rs-section rs-hero mg-retail-hero">
    <div class="rs-container">
      <div class="rs-hero-grid">
        <div class="rs-hero-copy">
          <span class="rs-badge">Retail subscriptions</span>
          <h1>Sell monthly retail subscriptions.</h1>
          <p>
            Let customers subscribe to a favorite bar, restaurant, or retailer for a monthly fee,
            then give them access to a private feed of ongoing offers, discounted products,
            exclusive events, early releases, and subscriber-only collaborations.
          </p>

          <div class="rs-actions">
            <a class="rs-btn rs-btn-primary" href="/learn-more.php">Book A Demo</a>
            <a class="rs-btn rs-btn-secondary" href="/signup.php">Create Account</a>
          </div>

          <div class="rs-hero-points">
            <span>Create predictable monthly revenue from loyal customers</span>
            <span>Post deals, discounted products, exclusive events, and early access</span>
            <span>Give subscribers a reason to return, engage, and spend more often</span>
          </div>
        </div>

        <div class="rs-hero-visual" aria-hidden="true">
          <div class="rs-flow-line"></div>

          <div class="rs-feed-float rs-feed-float-top-left">
            <span class="rs-phone-tag">Subscriber deal</span>
            <h3>Members-only happy hour</h3>
            <p>Reward paying subscribers with a private offer that drives repeat visits.</p>
            <div class="rs-feed-mini">
              <span>This week only</span>
              <span>Included</span>
            </div>
          </div>

          <div class="rs-feed-float rs-feed-float-top-right">
            <span class="rs-phone-tag">Exclusive</span>
            <h3>Exclusive product release</h3>
            <p>Give monthly subscribers first access to limited products before the public launch.</p>
            <div class="rs-feed-mini">
              <span>Subscriber feed</span>
              <span>VIP early access</span>
            </div>
          </div>

          <div class="rs-feed-float rs-feed-float-mid-left">
            <span class="rs-phone-tag">Tickets</span>
            <h3>Early access event tickets</h3>
            <p>Give subscribers first access to tastings, classes, launch nights, pop-ups, and private experiences.</p>
            <div class="rs-feed-mini">
              <span>26 claimed</span>
              <span>Launch Friday</span>
            </div>
          </div>

          <div class="rs-feed-float rs-feed-float-bottom-right">
            <span class="rs-phone-tag">New arrival</span>
            <h3>Collaborative subscriber drop</h3>
            <p>Publish collaborative offers with nearby merchants exclusively for subscribers.</p>
            <div class="rs-feed-mini">
              <span>In stock now</span>
              <span>Only 12 left</span>
            </div>
          </div>

          <div class="rs-phone">
            <div class="rs-phone-screen">
              <div class="rs-status-bar">
                <span>9:41</span>
                <span>Retail Feed</span>
              </div>

              <div class="rs-feed-header">
                <strong>Your subscriptions</strong>
                <span>Paid access to private merchant feeds.</span>
              </div>

              <div class="rs-phone-card is-accent">
                <span class="rs-phone-tag">Subscriber exclusive</span>
                <h3>Free appetizer with any entrée</h3>
                <p>Available only to active monthly subscribers through the private feed.</p>
                <div class="rs-phone-meta">
                  <span>Member benefit</span>
                  <span>$9.99/mo</span>
                </div>
              </div>

              <div class="rs-phone-card">
                <span class="rs-phone-tag">Weekly offer</span>
                <h3>2-for-1 cocktail flight this Thursday</h3>
                <p>Keep the subscription valuable with fresh, time-sensitive offers posted every week.</p>
                <div class="rs-phone-meta">
                  <span>Ends tonight</span>
                  <span>Claimable now</span>
                </div>
              </div>

              <div class="rs-phone-card is-ticket">
                <span class="rs-phone-tag">Tickets</span>
                <h3>Subscriber-only tasting event</h3>
                <p>Offer private access to events, special menus, and limited-capacity experiences.</p>
                <div class="rs-phone-meta">
                  <span>24 seats</span>
                  <span>Subscribers first</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="rs-section rs-benefits">
    <div class="rs-container">
      <div class="rs-heading">
        <span class="rs-badge">How it works</span>
        <h2>Turn loyal customers into recurring monthly revenue.</h2>
        <p>
          Merchants set a monthly subscription price, publish subscriber-only content,
          and create an ongoing reason for customers to stay connected, visit more often,
          and spend locally.
        </p>
      </div>

      <div class="rs-grid-3">
        <article class="rs-card">
          <div class="rs-card-icon">◎</div>
          <h3>Predictable subscription revenue</h3>
          <p>
            Build recurring monthly income from customers who want continued access
            to exclusive merchant offers and experiences.
          </p>
        </article>

        <article class="rs-card">
          <div class="rs-card-icon">✦</div>
          <h3>A private paid feed</h3>
          <p>
            Publish deals, discounted products, private events, early releases,
            and collaborative offers in one subscriber-only channel.
          </p>
        </article>

        <article class="rs-card">
          <div class="rs-card-icon">↗</div>
          <h3>More repeat visits and spend</h3>
          <p>
            Give subscribers fresh reasons to return throughout the month,
            creating stronger loyalty and more frequent purchasing behavior.
          </p>
        </article>
      </div>
    </div>
  </section>

  <section class="rs-section rs-use-cases">
    <div class="rs-container">
      <div class="rs-heading">
        <span class="rs-badge">What retailers can sell</span>
        <h2>Give subscribers something new every month.</h2>
        <p>
          Keep the subscription valuable with a steady stream of private benefits
          that can change by day, week, season, product launch, or local collaboration.
        </p>
      </div>

      <div class="rs-use-grid">
        <article class="rs-use">
          <span class="rs-use-number">1</span>
          <h3>Subscriber-only deals</h3>
          <p>Post weekly specials, limited-time discounts, and private offers only active subscribers can claim.</p>
        </article>

        <article class="rs-use">
          <span class="rs-use-number">2</span>
          <h3>Discounted and exclusive products</h3>
          <p>Offer special pricing, limited bundles, private menus, and products unavailable to the general public.</p>
        </article>

        <article class="rs-use">
          <span class="rs-use-number">3</span>
          <h3>Events and early access</h3>
          <p>Give subscribers first access to tastings, classes, launch nights, pop-ups, and private experiences.</p>
        </article>

        <article class="rs-use">
          <span class="rs-use-number">4</span>
          <h3>Collaborative local offers</h3>
          <p>Partner with nearby merchants to create bundled deals, shared events, and cross-promotional subscriber perks.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="rs-section rs-cta">
    <div class="rs-container">
      <div class="rs-cta-card">
        <span class="rs-badge">Local revenue infrastructure</span>
        <h2>Sell a $9.99 monthly relationship, not just a one-time deal.</h2>
        <p>
          Create a paid merchant subscription, post private offers in minutes,
          and give customers an ongoing reason to stay subscribed, visit more often,
          and support local businesses every month.
        </p>

        <div class="rs-actions">
          <a class="rs-btn rs-btn-primary" href="/signup.php">Create Account</a>
          <a class="rs-btn rs-btn-secondary" href="/learn-more.php">Book A Demo</a>
        </div>
      </div>
    </div>
  </section>
</main>

<footer id="retail-subscriptions-footer">
  <div class="rs-footer-inner">
    <div class="rs-footer-grid">
      <div class="rs-footer-brand">
        <a class="rs-footer-logo" href="/">
          <span class="rs-footer-mark">M</span>
          <span>Microgifter</span>
        </a>

        <p>
          Pre-purchase gifts, local rewards, and simple digital redemption
          for businesses, customers, teams, and communities.
        </p>

        <div class="rs-footer-socials" aria-label="Social links">
           <a href="https://instagram.com/microgifter" aria-label="Instagram">ig</a>
          <a href="https://linkedin.com/microgifter" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="rs-footer-column">
        <h3>Product</h3>
        <nav aria-label="Product links">
          <a href="/retail.php">Retail Subscriptions</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/discover.php">Discover</a>
        </nav>
      </div>

      <div class="rs-footer-column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="rs-footer-column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
          <a href="/support.php">Support</a>
        </nav>
      </div>
    </div>

    <div class="rs-footer-bottom">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>

      <div class="rs-footer-bottom-links">
        <a href="/privacy.php">Privacy</a>
        <a href="/terms.php">Terms</a>
        <a href="/signin.php">Sign In</a>
      </div>
    </div>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const customFooter = document.getElementById('retail-subscriptions-footer');

  if (!customFooter) {
    return;
  }

  document.body.appendChild(customFooter);

  document.querySelectorAll('body > footer').forEach(function (footer) {
    if (footer !== customFooter) {
      footer.remove();
    }
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
