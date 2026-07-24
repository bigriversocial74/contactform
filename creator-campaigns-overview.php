<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Merchant & Creator Campaigns | Microgifter';
$page_section = 'campaigns';
$header_mode = 'public';
$page_body_class = 'mg-creator-campaigns-overview-page';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/creator-campaigns-overview-v1.css?v=1.0.0',
];
$page_meta = [
    'description' => 'Create structured merchant and creator campaigns that connect promotion, attribution, customer activity, claims, and CRM follow-up in one Microgifter workflow.',
    'canonical' => 'https://microgifter.com/creator-campaigns-overview.php',
    'og_title' => 'Merchant & Creator Campaigns | Microgifter',
    'og_description' => 'Turn creator reach into measurable local commerce with governed campaign rules, attribution, customer lifecycle tracking, and Merchant CRM follow-up.',
];
$page_manifest = [
    'id' => 'creator-campaigns-overview',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'How It Works', 'href' => '#how-it-works'],
            ['label' => 'For Merchants', 'href' => '#for-merchants'],
            ['label' => 'For Creators', 'href' => '#for-creators'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'creator-campaigns-overview', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<main class="mcc-page">
  <section class="mcc-hero">
    <div class="mcc-wrap mcc-hero__grid">
      <div class="mcc-hero__copy">
        <p class="mcc-kicker">Merchant + Creator Growth</p>
        <h1>Turn creator reach into <em>measurable local commerce.</em></h1>
        <p class="mcc-lede">Microgifter gives merchants and creators one governed campaign workflow—from the offer and audience to attribution, customer activity, claims, and CRM follow-up.</p>
        <div class="mcc-actions">
          <a class="mcc-button mcc-button--dark" href="/learn-more.php">Book a campaign demo</a>
          <a class="mcc-button mcc-button--light" href="#how-it-works">See how it works</a>
        </div>
        <div class="mcc-proof-row" aria-label="Campaign capabilities">
          <span>Structured briefs</span>
          <span>Creator attribution</span>
          <span>Merchant CRM lifecycle</span>
        </div>
      </div>

      <div class="mcc-console" aria-label="Merchant and creator campaign workflow preview">
        <div class="mcc-console__top">
          <div><span class="mcc-live-dot"></span><strong>Creator Campaign</strong></div>
          <small>Governed workflow</small>
        </div>
        <section class="mcc-console__campaign">
          <div class="mcc-console__label"><span>Campaign brief</span><b>Active</b></div>
          <h2>Local Experience Spotlight</h2>
          <p>Promote a merchant offer with approved messaging, audience rules, attribution, and customer follow-up.</p>
          <div class="mcc-console__tags"><span>Product linked</span><span>Offer rules</span><span>Creator approved</span></div>
        </section>
        <div class="mcc-console__flow">
          <article><small>01</small><strong>Creator partner</strong><span>Joins the campaign</span></article>
          <article><small>02</small><strong>Attributed customer</strong><span>Visits, purchases, or claims</span></article>
          <article><small>03</small><strong>Merchant CRM</strong><span>Lifecycle and follow-up</span></article>
        </div>
        <div class="mcc-console__footer">
          <span><b>CRM</b> Creator partner connected</span>
          <span><b>Track</b> Campaign activity retained</span>
        </div>
      </div>
    </div>
  </section>

  <section class="mcc-system" id="how-it-works">
    <div class="mcc-wrap">
      <header class="mcc-heading">
        <p>One connected campaign system</p>
        <h2>Promotion should create a customer relationship—not disappear into a report.</h2>
        <span>Merchant campaigns, creator participation, customer attribution, claims, and follow-up stay connected to the same commerce lifecycle.</span>
      </header>
      <div class="mcc-system__grid">
        <article><b>01</b><h3>Build the campaign</h3><p>The merchant defines the product or experience, approved offer, campaign window, creator terms, audience, and measurable actions.</p></article>
        <article><b>02</b><h3>Activate creators</h3><p>Approved creators participate through a structured workspace instead of relying on disconnected messages, spreadsheets, and links.</p></article>
        <article><b>03</b><h3>Attribute activity</h3><p>Campaign participation and attributed customer actions remain connected to the merchant, creator partner, offer, and customer lifecycle.</p></article>
        <article><b>04</b><h3>Grow the relationship</h3><p>Leads, customers, and claimants can move into the Merchant CRM for governed follow-up, rewards, messaging, and future campaigns.</p></article>
      </div>
    </div>
  </section>

  <section class="mcc-audiences">
    <div class="mcc-wrap mcc-audiences__grid">
      <article class="mcc-audience" id="for-merchants">
        <p class="mcc-section-label">For merchants</p>
        <h2>Run creator partnerships like a real customer-growth channel.</h2>
        <ul>
          <li><strong>Campaign control</strong><span>Define the offer, approved content, products, dates, rules, and intended outcomes.</span></li>
          <li><strong>Partner visibility</strong><span>See creator participation without separating it from merchant operations.</span></li>
          <li><strong>CRM continuity</strong><span>Connect creator partners, customer leads, customers, and claimants to canonical Merchant CRM records.</span></li>
          <li><strong>Lifecycle evidence</strong><span>Keep campaign activity connected to purchase, claim, redemption, and follow-up context where available.</span></li>
        </ul>
      </article>

      <article class="mcc-audience mcc-audience--dark" id="for-creators">
        <p class="mcc-section-label">For creators</p>
        <h2>Promote local products and experiences with clearer rules and attribution.</h2>
        <ul>
          <li><strong>Structured opportunities</strong><span>Review the campaign, merchant, offer, timing, requirements, and participation status in one place.</span></li>
          <li><strong>Approved promotion</strong><span>Work from merchant-approved products, messaging, and campaign terms.</span></li>
          <li><strong>Connected attribution</strong><span>Keep creator participation tied to the campaign and resulting customer lifecycle.</span></li>
          <li><strong>Long-term partnerships</strong><span>Build a reusable merchant relationship rather than a one-time promotional handoff.</span></li>
        </ul>
      </article>
    </div>
  </section>

  <section class="mcc-capabilities">
    <div class="mcc-wrap">
      <header class="mcc-heading mcc-heading--compact">
        <p>Connected capabilities</p>
        <h2>Everything needed to move from campaign idea to customer memory.</h2>
      </header>
      <div class="mcc-card-grid">
        <article><span>Brief</span><h3>Merchant campaign builder</h3><p>Products, offers, objectives, campaign windows, terms, audiences, and creator participation.</p></article>
        <article><span>Partners</span><h3>Creator participation</h3><p>Structured creator relationships, status, campaign context, and approved promotional direction.</p></article>
        <article><span>Attribution</span><h3>Campaign identity</h3><p>Keep creator and customer activity associated with the originating merchant campaign.</p></article>
        <article><span>CRM</span><h3>Customer lifecycle sync</h3><p>Connect creator partners and attributed customers to usable Merchant CRM relationship types.</p></article>
        <article><span>Commerce</span><h3>Offer-to-claim context</h3><p>Retain purchase, claim, redemption, and campaign context when those lifecycle events occur.</p></article>
        <article><span>Governance</span><h3>Rules stay enforced</h3><p>Campaign permissions, approved content, reward rules, and merchant controls remain part of the workflow.</p></article>
      </div>
    </div>
  </section>

  <section class="mcc-guardrails">
    <div class="mcc-wrap mcc-guardrails__grid">
      <div><p class="mcc-section-label">Built for accountable collaboration</p><h2>Creators extend the campaign. They do not bypass the merchant.</h2></div>
      <div class="mcc-rule-list">
        <article><b>01</b><div><strong>Merchant-owned rules</strong><span>The merchant controls the campaign offer, approved content, eligibility, timing, and operational requirements.</span></div></article>
        <article><b>02</b><div><strong>Scoped access</strong><span>Creators receive only the campaign context and actions intended for their participation.</span></div></article>
        <article><b>03</b><div><strong>Auditable lifecycle</strong><span>Campaign relationships and attributed customer states remain attached to canonical platform records.</span></div></article>
      </div>
    </div>
  </section>

  <section class="mcc-cta">
    <div class="mcc-wrap mcc-cta__panel">
      <div><p>Merchant & Creator Campaigns</p><h2>Build partnerships that create measurable customer growth.</h2><span>Connect creator reach, local offers, social gifting, attribution, and Merchant CRM follow-up.</span></div>
      <a class="mcc-button mcc-button--dark" href="/learn-more.php">Book a demo</a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
