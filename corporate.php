<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Corporate Gifting | Microgifter';
$page_section = 'corporate-gifting';
$header_mode = 'public';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];

$page_manifest = [
    'id' => 'corporate-gifting',
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
        'page' => 'corporate-gifting',
        'sections' => [],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<style>
:root{
  --cg-dark:#071225;
  --cg-text:#172033;
  --cg-muted:#64748b;
  --cg-border:#dbe5f1;
  --cg-purple:#7c3aed;
  --cg-purple-2:#6d5dfc;
  --cg-teal:#0f9f9a;
  --cg-green:#16a34a;
  --cg-soft:#f8fafc;
}

.cg-page{
  color:var(--cg-text);
  background:#fff;
}

.cg-section{
  position:relative;
  overflow:hidden;
  padding:150px 0;
  border-bottom:1px solid var(--cg-border);
}

.cg-section::before{
  content:"";
  position:absolute;
  inset:0;
  pointer-events:none;
  opacity:.48;
  background:
    linear-gradient(90deg,rgba(15,23,42,.035) 1px,transparent 1px),
    linear-gradient(0deg,rgba(15,23,42,.035) 1px,transparent 1px);
  background-size:72px 72px;
}

.cg-container{
  position:relative;
  z-index:1;
  width:min(1180px,92%);
  margin:0 auto;
}

.cg-badge{
  display:inline-flex;
  align-items:center;
  gap:9px;
  min-height:36px;
  padding:0 14px;
  border:1px solid #d8e4f4;
  border-radius:999px;
  background:rgba(255,255,255,.88);
  color:var(--cg-dark);
  font-size:13px;
  font-weight:950;
  box-shadow:0 12px 28px rgba(15,23,42,.06);
}

.cg-badge::before{
  content:"";
  width:9px;
  height:9px;
  border-radius:999px;
  background:var(--cg-purple);
  box-shadow:0 0 0 6px rgba(124,58,237,.1);
}

.cg-hero{
  min-height:calc(100svh - 64px);
  display:flex;
  align-items:flex-start;
  padding-top:72px;
  padding-bottom:120px;
  background:
    radial-gradient(circle at 16% 18%,rgba(255,255,255,.98),transparent 30%),
    radial-gradient(circle at 82% 22%,rgba(237,233,254,.88),transparent 34%),
    radial-gradient(circle at 74% 72%,rgba(204,251,241,.44),transparent 30%),
    linear-gradient(180deg,#fff 0%,#f8fafc 62%,#eef2f7 100%);
}

.cg-hero-grid{
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:28px;
}

.cg-hero-copy{
  width:min(900px,100%);
  margin:0 auto;
  text-align:center;
}

.cg-hero-copy h1{
  margin:0 auto;
  max-width:900px;
  color:var(--cg-dark);
  font-size:clamp(46px,5.8vw,78px);
  line-height:.94;
  letter-spacing:-.075em;
}

.cg-hero-copy p{
  margin:24px auto 0;
  max-width:760px;
  color:var(--cg-muted);
  font-size:20px;
  line-height:1.55;
  font-weight:620;
}

.cg-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:30px;
}


.cg-btn{
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

.cg-btn:hover{
  transform:translateY(-2px);
}

.cg-btn-primary{
  color:#fff;
  background:linear-gradient(135deg,var(--cg-purple-2),var(--cg-purple));
  box-shadow:0 16px 34px rgba(109,93,252,.22);
}

.cg-btn-secondary{
  color:var(--cg-dark);
  background:#fff;
  border:1px solid var(--cg-border);
}

.cg-trust{
  display:flex;
  align-items:center;
  gap:10px;
  margin-top:26px;
  color:#475569;
  font-size:14px;
  font-weight:850;
}

.cg-hero-copy .cg-trust{
  justify-content:center;
}

.cg-trust-icon{
  width:28px;
  height:28px;
  display:grid;
  place-items:center;
  border-radius:999px;
  color:#fff;
  background:linear-gradient(135deg,var(--cg-teal),#22c55e);
}

.cg-hero-visual{
  position:relative;
  width:100%;
  min-height:0;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
}

.cg-hero-image-actions{
  justify-content:center;
  margin-top:8px;
}

.cg-hero-image-trust{
  justify-content:center;
  margin-top:14px;
}

.cg-hero-visual::before{
  content:"";
  position:absolute;
  width:82%;
  aspect-ratio:1/1;
  border-radius:999px;
  background:radial-gradient(circle,rgba(124,58,237,.17),rgba(15,159,154,.07) 50%,transparent 72%);
  filter:blur(10px);
}

.cg-hero-image{
  position:relative;
  z-index:1;
  width:min(1050px,100%);
  max-width:none;
  height:auto;
  display:block;
  filter:drop-shadow(0 32px 48px rgba(15,23,42,.18));
}

/* Benefits */
.cg-benefits{
  background:
    radial-gradient(circle at 50% 10%,rgba(237,233,254,.72),transparent 36%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.cg-heading{
  max-width:820px;
  margin:0 auto 60px;
  text-align:center;
}

.cg-heading h2{
  margin:18px 0 16px;
  color:var(--cg-dark);
  font-size:clamp(40px,5vw,66px);
  line-height:.96;
  letter-spacing:-.065em;
}

.cg-heading p{
  margin:0 auto;
  max-width:690px;
  color:var(--cg-muted);
  font-size:18px;
  line-height:1.58;
}

.cg-benefit-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:18px;
}

.cg-card{
  padding:28px;
  border:1px solid var(--cg-border);
  border-radius:24px;
  background:rgba(255,255,255,.95);
  box-shadow:0 20px 54px rgba(15,23,42,.08);
}

.cg-card-icon{
  width:54px;
  height:54px;
  display:grid;
  place-items:center;
  border-radius:17px;
  color:var(--cg-purple);
  background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(15,159,154,.1));
  border:1px solid rgba(124,58,237,.12);
  font-size:25px;
}

.cg-card h3{
  margin:20px 0 9px;
  color:var(--cg-dark);
  font-size:23px;
  line-height:1.08;
  letter-spacing:-.045em;
}

.cg-card p{
  margin:0;
  color:var(--cg-muted);
  line-height:1.55;
  font-size:15px;
}

/* Workflow */
.cg-workflow{
  background:
    radial-gradient(circle at 16% 50%,rgba(204,251,241,.46),transparent 30%),
    linear-gradient(180deg,#f8fafc,#fff);
}

.cg-step-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:18px;
}

.cg-step{
  position:relative;
  min-height:260px;
  padding:28px;
  border:1px solid var(--cg-border);
  border-radius:24px;
  background:#fff;
  box-shadow:0 18px 50px rgba(15,23,42,.07);
}

.cg-step-number{
  width:38px;
  height:38px;
  display:grid;
  place-items:center;
  border-radius:999px;
  color:#fff;
  background:linear-gradient(135deg,var(--cg-purple),var(--cg-teal));
  font-weight:950;
}

.cg-step h3{
  margin:24px 0 9px;
  color:var(--cg-dark);
  font-size:21px;
  letter-spacing:-.04em;
}

.cg-step p{
  margin:0;
  color:var(--cg-muted);
  line-height:1.55;
  font-size:14px;
}

/* Impact */
.cg-impact{
  background:
    radial-gradient(circle at 82% 24%,rgba(237,233,254,.76),transparent 33%),
    linear-gradient(180deg,#fff,#f8fafc);
}

.cg-impact-grid{
  display:grid;
  grid-template-columns:.82fr 1.18fr;
  gap:48px;
  align-items:center;
}

.cg-impact-copy h2{
  margin:18px 0 16px;
  color:var(--cg-dark);
  font-size:clamp(40px,5vw,64px);
  line-height:.96;
  letter-spacing:-.065em;
}

.cg-impact-copy p{
  margin:0;
  color:var(--cg-muted);
  font-size:18px;
  line-height:1.58;
}

.cg-dashboard{
  padding:26px;
  border:1px solid var(--cg-border);
  border-radius:30px;
  background:#fff;
  box-shadow:0 28px 76px rgba(15,23,42,.11);
}

.cg-dashboard-head{
  display:flex;
  justify-content:space-between;
  gap:16px;
  margin-bottom:18px;
}

.cg-dashboard-head h3{
  margin:0;
  color:var(--cg-dark);
  font-size:22px;
}

.cg-dashboard-filter{
  padding:9px 12px;
  border:1px solid var(--cg-border);
  border-radius:11px;
  background:#f8fafc;
  color:var(--cg-muted);
  font-size:12px;
  font-weight:850;
}

.cg-stat-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:10px;
}

.cg-stat{
  padding:16px;
  border:1px solid #e2e8f0;
  border-radius:16px;
  background:#f8fafc;
}

.cg-stat span{
  display:block;
  color:var(--cg-muted);
  font-size:10px;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.055em;
}

.cg-stat strong{
  display:block;
  margin-top:7px;
  color:var(--cg-dark);
  font-size:22px;
  letter-spacing:-.045em;
}

.cg-stat small{
  display:block;
  margin-top:5px;
  color:var(--cg-green);
  font-size:10px;
  font-weight:850;
}

.cg-chart-wrap{
  margin-top:18px;
  padding:18px;
  border:1px solid #e2e8f0;
  border-radius:18px;
  background:#fbfdff;
}

.cg-chart{
  width:100%;
  height:auto;
  display:block;
}

.cg-chart .grid{
  stroke:#e2e8f0;
  stroke-width:1;
}

.cg-chart .line{
  fill:none;
  stroke:var(--cg-purple);
  stroke-width:4;
  stroke-linecap:round;
  stroke-linejoin:round;
}

.cg-chart .area{
  fill:url(#cgAreaGradient);
}

.cg-chart .dot{
  fill:#fff;
  stroke:var(--cg-purple);
  stroke-width:3;
}


.cg-impact-controls{
  display:grid;
  gap:22px;
  margin-top:30px;
  padding:24px;
  border:1px solid var(--cg-border);
  border-radius:22px;
  background:rgba(255,255,255,.9);
  box-shadow:0 18px 48px rgba(15,23,42,.08);
}

.cg-impact-control{
  display:grid;
  gap:10px;
}

.cg-impact-control-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
}

.cg-impact-control label{
  color:#334155;
  font-size:14px;
  font-weight:900;
}

.cg-impact-output{
  min-width:92px;
  padding:8px 11px;
  border:1px solid var(--cg-border);
  border-radius:11px;
  background:#f8fafc;
  color:var(--cg-dark);
  text-align:center;
  font-weight:950;
}

.cg-impact-slider{
  width:100%;
  accent-color:var(--cg-purple);
  cursor:pointer;
}

.cg-impact-scale{
  display:flex;
  justify-content:space-between;
  color:#94a3b8;
  font-size:11px;
  font-weight:800;
}

.cg-impact-note{
  margin:22px 0 0;
  color:#64748b;
  font-size:12px;
  line-height:1.55;
}

/* CTA */
.cg-cta{
  background:
    radial-gradient(circle at 18% 24%,rgba(196,181,253,.34),transparent 30%),
    radial-gradient(circle at 84% 74%,rgba(165,243,252,.26),transparent 32%),
    linear-gradient(135deg,#071225 0%,#111b35 56%,#25124e 100%);
  color:#fff;
}

.cg-cta-card{
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

.cg-cta-card h2{
  margin:20px auto 16px;
  max-width:760px;
  color:#fff;
  font-size:clamp(42px,5vw,68px);
  line-height:.96;
  letter-spacing:-.068em;
}

.cg-cta-card p{
  max-width:680px;
  margin:0 auto;
  color:#cbd5e1;
  font-size:18px;
  line-height:1.6;
}

.cg-cta-card .cg-actions{
  justify-content:center;
}

/* Matching four-column footer */
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
  grid-template-columns:repeat(4,minmax(0,1fr));
  gap:48px;
  align-items:start;
}

.mg-home-footer-brand{
  min-width:0;
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

@media(max-width:980px){
  .cg-impact-grid{
    grid-template-columns:1fr;
  }

  .cg-benefit-grid{
    grid-template-columns:repeat(2,1fr);
  }

  .cg-step-grid{
    grid-template-columns:repeat(2,1fr);
  }

  .cg-hero-visual{
    min-height:auto;
  }

  .cg-hero-image{
    width:min(760px,100%);
  }
}

@media(max-width:820px){
  .mg-home-footer-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
}

@media(max-width:680px){
  .cg-section{
    padding:88px 0;
  }

  .cg-hero{
    padding-top:48px;
    padding-bottom:88px;
  }

  .cg-hero-copy{
    text-align:center;
  }

  .cg-actions{
    display:grid;
  }

  .cg-btn{
    width:100%;
  }

  .cg-hero-copy .cg-trust{
    justify-content:center;
  }

  .cg-benefit-grid,
  .cg-step-grid,
  .cg-stat-grid{
    grid-template-columns:1fr;
  }

  .cg-cta-card{
    padding:40px 22px;
    border-radius:24px;
  }

  .mg-home-footer-grid{
    grid-template-columns:1fr;
    gap:34px;
  }

  .mg-home-footer-bottom{
    display:grid;
  }
}
</style>

<main class="cg-page">
  <section class="cg-section cg-hero">
    <div class="cg-container">
      <div class="cg-hero-grid">
        <div class="cg-hero-copy">
          <h1>Make Corporate Gifting Easy &amp; Support Local.</h1>

          <p>
            Simplify corporate group purchases, reward your teams with meaningful local gifts,
            and direct more company spending into the communities where your employees live and work.
          </p>

        </div>

        <div class="cg-hero-visual">
          <img
            class="cg-hero-image"
            src="/images/microgifter-corporate-gifting-hero-removebg-preview.png"
            alt="HR manager with a diverse team, local business, gift boxes, and digital gifting tools"
            width="1448"
            height="1086"
          >

          <div class="cg-actions cg-hero-image-actions">
            <a class="cg-btn cg-btn-primary" href="/learn-more.php">Book A Demo</a>
            <a class="cg-btn cg-btn-secondary" href="/signup.php">Create Account</a>
          </div>

          <div class="cg-trust cg-hero-image-trust">
            <span class="cg-trust-icon">✓</span>
            <span>Built for HR teams, managers, and distributed organizations.</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cg-section cg-benefits" id="benefits">
    <div class="cg-container">
      <div class="cg-heading">
        <span class="cg-badge">Better incentives, stronger communities</span>
        <h2>Corporate gifting that works for everyone.</h2>
        <p>
          Give teams more meaningful choices while making group purchasing, local business support,
          administration, and impact measurement easier for your organization.
        </p>
      </div>

      <div class="cg-benefit-grid">
        <article class="cg-card">
          <div class="cg-card-icon">👥</div>
          <h3>Corporate group purchases</h3>
          <p>Set a budget, organize recipients, and manage large gift purchases from one place.</p>
        </article>

        <article class="cg-card">
          <div class="cg-card-icon">🏪</div>
          <h3>Support local businesses</h3>
          <p>Direct company gifting spend toward curated local merchants and experiences.</p>
        </article>

        <article class="cg-card">
          <div class="cg-card-icon">♥</div>
          <h3>Better employee incentives</h3>
          <p>Offer rewards that feel personal, useful, and connected to each recipient’s community.</p>
        </article>

        <article class="cg-card">
          <div class="cg-card-icon">◎</div>
          <h3>Measurable community impact</h3>
          <p>Track participation, gift delivery, claims, and estimated local economic impact.</p>
        </article>

        <article class="cg-card">
          <div class="cg-card-icon">⚙</div>
          <h3>Easy administration</h3>
          <p>Centralize budgets, campaign details, recipients, and reporting for HR and team leaders.</p>
        </article>

        <article class="cg-card">
          <div class="cg-card-icon">➤</div>
          <h3>Simple digital delivery</h3>
          <p>Send gifts instantly without shipping delays, physical inventory, or extra hardware.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="cg-section cg-workflow" id="how-it-works">
    <div class="cg-container">
      <div class="cg-heading">
        <span class="cg-badge">How Microgifter works</span>
        <h2>A simple four-step process for teams.</h2>
        <p>
          Build the campaign, choose the local experience, deliver the gifts,
          and measure the results without adding another complicated HR workflow.
        </p>
      </div>

      <div class="cg-step-grid">
        <article class="cg-step">
          <span class="cg-step-number">1</span>
          <h3>Set the budget and campaign</h3>
          <p>Choose the audience, budget, timing, and reason for the corporate gift.</p>
        </article>

        <article class="cg-step">
          <span class="cg-step-number">2</span>
          <h3>Choose local gifts</h3>
          <p>Select local businesses, products, experiences, or curated recipient options.</p>
        </article>

        <article class="cg-step">
          <span class="cg-step-number">3</span>
          <h3>Send to your teams</h3>
          <p>Upload recipients and deliver gifts digitally with a personalized message.</p>
        </article>

        <article class="cg-step">
          <span class="cg-step-number">4</span>
          <h3>Track and measure impact</h3>
          <p>Monitor delivery, participation, redemption, and support for local merchants.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="cg-section cg-impact" id="impact">
    <div class="cg-container">
      <div class="cg-impact-grid">
        <div class="cg-impact-copy">
          <span class="cg-badge">Illustrative community-impact model</span>
          <h2>See the impact your gifting creates.</h2>
          <p>
            Adjust the assumptions to estimate how a corporate gifting program could direct
            more spending toward local businesses and create measurable community activity.
          </p>

          <div class="cg-impact-controls">
            <div class="cg-impact-control">
              <div class="cg-impact-control-head">
                <label for="cgRecipients">Employees or recipients</label>
                <output class="cg-impact-output" id="cgRecipientsOutput" for="cgRecipients">250</output>
              </div>
              <input class="cg-impact-slider" id="cgRecipients" type="range" min="25" max="5000" step="25" value="250">
              <div class="cg-impact-scale"><span>25</span><span>5,000</span></div>
            </div>

            <div class="cg-impact-control">
              <div class="cg-impact-control-head">
                <label for="cgGiftValue">Average gift value</label>
                <output class="cg-impact-output" id="cgGiftValueOutput" for="cgGiftValue">$50</output>
              </div>
              <input class="cg-impact-slider" id="cgGiftValue" type="range" min="10" max="250" step="5" value="50">
              <div class="cg-impact-scale"><span>$10</span><span>$250</span></div>
            </div>

            <div class="cg-impact-control">
              <div class="cg-impact-control-head">
                <label for="cgCampaigns">Campaigns per year</label>
                <output class="cg-impact-output" id="cgCampaignsOutput" for="cgCampaigns">4</output>
              </div>
              <input class="cg-impact-slider" id="cgCampaigns" type="range" min="1" max="12" step="1" value="4">
              <div class="cg-impact-scale"><span>1</span><span>12</span></div>
            </div>

            <div class="cg-impact-control">
              <div class="cg-impact-control-head">
                <label for="cgLocalMultiplier">Local-spend multiplier</label>
                <output class="cg-impact-output" id="cgLocalMultiplierOutput" for="cgLocalMultiplier">1.5×</output>
              </div>
              <input class="cg-impact-slider" id="cgLocalMultiplier" type="range" min="0.5" max="5" step="0.1" value="1.5">
              <div class="cg-impact-scale"><span>0.5×</span><span>5×</span></div>
            </div>
          </div>

          <p class="cg-impact-note">
            The local-spend multiplier estimates additional local purchasing associated with each gift recipient.
            Results are hypothetical and intended for planning only.
          </p>
        </div>

        <div class="cg-dashboard" aria-live="polite" aria-label="Corporate gifting community impact estimator">
          <div class="cg-dashboard-head">
            <h3>Projected annual impact</h3>
            <span class="cg-dashboard-filter">Live estimate</span>
          </div>

          <div class="cg-stat-grid">
            <div class="cg-stat">
              <span>Total gifts sent</span>
              <strong id="cgTotalGifts">1,000</strong>
              <small>Recipients × campaigns</small>
            </div>
            <div class="cg-stat">
              <span>Direct local gift spend</span>
              <strong id="cgDirectSpend">$50,000</strong>
              <small>Gift value × total gifts</small>
            </div>
            <div class="cg-stat">
              <span>Additional local demand</span>
              <strong id="cgAdditionalDemand">$75,000</strong>
              <small>Estimated multiplier effect</small>
            </div>
            <div class="cg-stat">
              <span>Total community impact</span>
              <strong id="cgCommunityImpact">$125,000</strong>
              <small>Direct spend + added demand</small>
            </div>
          </div>

          <div class="cg-chart-wrap">
            <svg class="cg-chart" viewBox="0 0 720 300" role="img" aria-labelledby="cg-chart-title cg-chart-desc">
              <title id="cg-chart-title">Projected corporate gifting community impact</title>
              <desc id="cg-chart-desc">A purple line illustrates cumulative community impact across twelve months.</desc>
              <defs>
                <linearGradient id="cgAreaGradient" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stop-color="#7c3aed" stop-opacity=".24"/>
                  <stop offset="100%" stop-color="#7c3aed" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <line class="grid" x1="48" y1="55" x2="680" y2="55"/>
              <line class="grid" x1="48" y1="120" x2="680" y2="120"/>
              <line class="grid" x1="48" y1="185" x2="680" y2="185"/>
              <line class="grid" x1="48" y1="250" x2="680" y2="250"/>
              <polygon class="area" id="cgImpactArea" points=""></polygon>
              <polyline class="line" id="cgImpactLine" points=""></polyline>
              <g id="cgImpactDots"></g>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script>
  (() => {
    const recipients = document.getElementById('cgRecipients');
    const giftValue = document.getElementById('cgGiftValue');
    const campaigns = document.getElementById('cgCampaigns');
    const multiplier = document.getElementById('cgLocalMultiplier');

    if (!recipients || !giftValue || !campaigns || !multiplier) {
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

    const updateImpact = () => {
      const recipientCount = Number(recipients.value);
      const averageGiftValue = Number(giftValue.value);
      const campaignCount = Number(campaigns.value);
      const localMultiplier = Number(multiplier.value);

      const totalGifts = recipientCount * campaignCount;
      const directSpend = totalGifts * averageGiftValue;
      const additionalDemand = directSpend * localMultiplier;
      const communityImpact = directSpend + additionalDemand;

      document.getElementById('cgRecipientsOutput').textContent = integer.format(recipientCount);
      document.getElementById('cgGiftValueOutput').textContent = currency.format(averageGiftValue);
      document.getElementById('cgCampaignsOutput').textContent = integer.format(campaignCount);
      document.getElementById('cgLocalMultiplierOutput').textContent = `${localMultiplier.toFixed(1)}×`;

      document.getElementById('cgTotalGifts').textContent = integer.format(totalGifts);
      document.getElementById('cgDirectSpend').textContent = currency.format(directSpend);
      document.getElementById('cgAdditionalDemand').textContent = currency.format(additionalDemand);
      document.getElementById('cgCommunityImpact').textContent = currency.format(communityImpact);

      const weights = [.08,.16,.24,.32,.4,.5,.6,.7,.78,.86,.93,1];
      const values = weights.map(weight => communityImpact * weight);
      const max = Math.max(...values, 1);
      const left = 48;
      const right = 680;
      const top = 34;
      const bottom = 250;
      const width = right - left;
      const height = bottom - top;

      const points = values.map((value, index) => {
        const x = left + (width * index / (values.length - 1));
        const y = bottom - (value / max * height);
        return [x, y];
      });

      const pointString = points.map(point => point.join(',')).join(' ');
      const areaString = `${left},${bottom} ${pointString} ${right},${bottom}`;

      document.getElementById('cgImpactLine').setAttribute('points', pointString);
      document.getElementById('cgImpactArea').setAttribute('points', areaString);
      document.getElementById('cgImpactDots').innerHTML = points
        .filter((_, index) => index % 2 === 0 || index === points.length - 1)
        .map(([x, y]) => `<circle class="dot" cx="${x}" cy="${y}" r="5"></circle>`)
        .join('');
    };

    [recipients, giftValue, campaigns, multiplier].forEach(input => {
      input.addEventListener('input', updateImpact);
    });

    updateImpact();
  })();
  </script>

  <section class="cg-section cg-cta">
    <div class="cg-container">
      <div class="cg-cta-card">
        <span class="cg-badge">Corporate gifting, built for local impact</span>
        <h2>Give better gifts without adding more work.</h2>
        <p>
          Create your first corporate gifting campaign, support local businesses,
          and give your teams a reward they will actually use.
        </p>

        <div class="cg-actions">
          <a class="cg-btn cg-btn-primary" href="/signup.php">Create Account</a>
          <a class="cg-btn cg-btn-secondary" href="/learn-more.php">Book A Demo</a>
        </div>
      </div>
    </div>
  </section>
</main>

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
          <a href="#" aria-label="Facebook">f</a>
          <a href="#" aria-label="Instagram">ig</a>
          <a href="#" aria-label="LinkedIn">in</a>
          <a href="mailto:hello@microgifter.com" aria-label="Email">✉</a>
        </div>
      </div>

      <div class="mg-home-footer-column">
        <h3>Product</h3>
        <nav aria-label="Product links">
          <a href="/feed.php">Gift Feed</a>
          <a href="/discover.php">Discover</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Businesses</h3>
        <nav aria-label="Business links">
          <a href="/#simple">How It Works</a>
          <a href="/learn-more.php">Book A Demo</a>
          <a href="/signup.php">Create Account</a>
        </nav>
      </div>

      <div class="mg-home-footer-column">
        <h3>Company</h3>
        <nav aria-label="Company links">
          <a href="/about.php">About</a>
          <a href="/corporate.php">Corporate Gifting</a>
          <a href="/pitch-deck.php">Pitch Deck</a>
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
