<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
if (mg_current_user() !== null) {
    header('Cache-Control: no-store, private');
    header('Location: /inbox.php', true, 302);
    exit;
}

$page_title = 'Microgifter — Gift Certificates, Workplace Rewards & CRM';
$page_section = 'public';
$header_mode = 'public';
$page_body_class = 'mg-home-saas-v1';
$page_disable_legacy_home_assets = true;
$page_styles = [
    '/assets/css/homepage-saas-v1.css?v=1.2.0',
];
$page_scripts = [];
$page_meta = [
    'description' => 'Microgifter brings gift certificates, workplace rewards, loyalty, campaigns, and customer engagement into one connected platform for local businesses and organizations.',
    'canonical' => 'https://microgifter.com/index.php',
    'og_title' => 'Microgifter — Microgifting. Big Impact.',
    'og_description' => 'Sell gift certificates, reward teams, manage customer engagement, and grow future demand with Microgifter.',
    'og_image' => 'https://microgifter.com/assets/images/home/microgifter-home-desktop-dashboard.svg',
];
$page_manifest = [
    'id' => 'homepage-saas',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'onboarding' => [
        'enabled' => false,
        'page' => 'home',
        'sections' => [],
    ],
];

header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
require __DIR__ . '/includes/header.php';
?>
<div class="mg-home" data-mg-home-saas-v1>
  <section class="mg-home-hero" id="hero" aria-labelledby="home-hero-title">
    <div class="mg-home-container mg-home-hero__inner">
      <div class="mg-home-hero__copy">
        <p class="mg-home-eyebrow">Gift certificates, rewards, loyalty and CRM</p>
        <h1 id="home-hero-title">Microgifting. Big Impact.</h1>
        <p class="mg-home-lead">Give local businesses and organizations one connected platform to sell gift certificates, reward people, engage customers, and turn future demand into present-day revenue.</p>
        <div class="mg-home-actions" aria-label="Homepage actions">
          <a class="mg-home-button mg-home-button--primary" href="/signup.php">Get started free</a>
          <a class="mg-home-button mg-home-button--secondary" href="/learn-more.php">Book a demo</a>
        </div>
        <div class="mg-home-trust" aria-label="Platform benefits">
          <span>Digital-first</span>
          <span>Merchant controlled</span>
          <span>Built for local commerce</span>
        </div>
      </div>

      <figure class="mg-home-hero__visual">
        <div class="mg-home-hero__glow" aria-hidden="true"></div>
        <img
          src="/assets/images/home/microgifter-home-desktop-dashboard.svg?v=1.2.0"
          alt="Microgifter dashboard showing pre-sale revenue, gift certificate sales, and top-performing products"
          width="1448"
          height="1086"
          fetchpriority="high"
          decoding="async"
        >
      </figure>
    </div>
  </section>

  <section class="mg-home-section mg-home-features" id="solutions" aria-labelledby="home-features-title">
    <div class="mg-home-container mg-home-split mg-home-split--phone">
      <div class="mg-home-copy-block">
        <p class="mg-home-eyebrow">One connected platform</p>
        <h2 id="home-features-title">Everything you need to grow with gifting.</h2>
        <p class="mg-home-section-lead">Start with digital gift certificates, then expand into rewards, campaigns, loyalty, workplace programs, and customer engagement.</p>

        <div class="mg-home-feature-list">
          <a class="mg-home-feature-row" href="/signup.php">
            <span class="mg-home-feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M3 3h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.6L20 7H6"/><circle cx="10" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
            </span>
            <span><strong>Sell gift certificates online</strong><small>Publish local products, services, experiences, and offers customers can purchase and send.</small></span>
            <i aria-hidden="true">→</i>
          </a>
          <a class="mg-home-feature-row" href="/learn-more.php">
            <span class="mg-home-feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span><strong>Reward your team</strong><small>Run workplace recognition, milestones, incentives, group gifting, and recurring reward programs.</small></span>
            <i aria-hidden="true">→</i>
          </a>
          <a class="mg-home-feature-row" href="/pricing.php">
            <span class="mg-home-feature-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            </span>
            <span><strong>Manage customer relationships</strong><small>Connect purchases, claims, campaign activity, referrals, visits, and rewards to customer records.</small></span>
            <i aria-hidden="true">→</i>
          </a>
        </div>
      </div>

      <figure class="mg-home-phone-card">
        <div class="mg-home-phone-card__halo" aria-hidden="true"></div>
        <img
          src="/assets/images/home/microgifter-home-phone.svg?v=1.2.0"
          alt="Microgifter mobile inbox displaying claimable local gifts"
          width="768"
          height="1152"
          loading="lazy"
          decoding="async"
        >
      </figure>
    </div>
  </section>

  <section class="mg-home-section mg-home-sales" id="business" aria-labelledby="home-sales-title">
    <div class="mg-home-container mg-home-split mg-home-split--reverse">
      <div class="mg-home-analytics" aria-label="Illustrative gift certificate sales analytics">
        <article class="mg-home-chart-card mg-home-chart-card--large">
          <div class="mg-home-card-heading"><span>Pre-Sale Revenue</span><strong>$125K</strong><em>+23.7%</em></div>
          <svg viewBox="0 0 640 260" role="img" aria-label="Upward pre-sale revenue trend">
            <g class="mg-home-chart-grid"><line x1="30" y1="40" x2="610" y2="40"/><line x1="30" y1="95" x2="610" y2="95"/><line x1="30" y1="150" x2="610" y2="150"/><line x1="30" y1="205" x2="610" y2="205"/></g>
            <path class="mg-home-chart-line mg-home-chart-line--previous" d="M30 210C90 190 110 200 160 168S245 165 290 142S390 135 430 115S520 110 610 58"/>
            <path class="mg-home-chart-line" d="M30 190C90 175 110 150 160 160S245 130 290 120S390 108 430 82S520 86 610 35"/>
          </svg>
        </article>
        <div class="mg-home-analytics__small">
          <article class="mg-home-chart-card"><span>Gift Certificates Sold</span><strong>2,450</strong><em>+18.6%</em><div class="mg-home-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div></article>
          <article class="mg-home-chart-card"><span>Average Order Value</span><strong>$58</strong><em>+12.4%</em><div class="mg-home-mini-line" aria-hidden="true"><i></i></div></article>
        </div>
        <div class="mg-home-metric-strip">
          <span><b>Total Revenue</b><strong>$60K</strong></span>
          <span><b>Gross Profit</b><strong>$22K</strong></span>
          <span><b>Average Order Value</b><strong>$58</strong></span>
        </div>
      </div>

      <div class="mg-home-copy-block">
        <p class="mg-home-eyebrow">For businesses</p>
        <h2 id="home-sales-title">Drive more sales with gift certificates.</h2>
        <p class="mg-home-section-lead">Increase conversion opportunities, create future visits, and strengthen customer loyalty with digital gifts that are simple to sell, send, claim, and track.</p>
        <ul class="mg-home-check-list">
          <li>Receive revenue when a gift is purchased.</li>
          <li>Track sent, claimed, redeemed, refunded, and regifted activity.</li>
          <li>Connect gift activity to campaigns, rewards, and customer records.</li>
        </ul>
        <a class="mg-home-text-link" href="/learn-more.php">Learn more about business solutions <span>→</span></a>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-rewards" id="workplace-rewards" aria-labelledby="home-rewards-title">
    <div class="mg-home-container mg-home-split">
      <div class="mg-home-copy-block">
        <p class="mg-home-eyebrow">For organizations</p>
        <h2 id="home-rewards-title">Reward, recognize, and retain top talent.</h2>
        <p class="mg-home-section-lead">Create meaningful employee recognition experiences using gift certificates, custom rewards, milestones, recurring programs, and peer participation.</p>
        <ul class="mg-home-check-list">
          <li>Employee appreciation and milestone rewards.</li>
          <li>Peer recognition and team incentives.</li>
          <li>Enterprise-sponsored local commerce programs.</li>
        </ul>
        <a class="mg-home-text-link" href="/learn-more.php">Explore workplace rewards <span>→</span></a>
      </div>

      <div class="mg-home-reward-visual" aria-label="Illustrative workplace rewards dashboard">
        <article class="mg-home-float-card mg-home-float-card--total"><span>Team Rewards</span><strong>3,450</strong><small>+12% from last month</small></article>
        <article class="mg-home-float-card mg-home-float-card--performer"><i>★</i><span>Top Performer</span><strong>Alex Johnson</strong></article>
        <article class="mg-home-float-card mg-home-float-card--peer"><i>✓</i><span>Peer Recognition</span><strong>+18 received</strong></article>
        <article class="mg-home-float-card mg-home-float-card--milestone"><i>◫</i><span>Milestone</span><strong>Work Anniversary</strong></article>
        <div class="mg-home-gift-box" aria-hidden="true"><span></span><i></i><b></b></div>
        <div class="mg-home-progress-card"><span>Your progress</span><strong>Level 4</strong><i><b></b></i><small>2,150 points toward Level 5</small></div>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-platform" id="platform" aria-labelledby="home-platform-title">
    <div class="mg-home-container">
      <header class="mg-home-section-heading">
        <p class="mg-home-eyebrow">Platform overview</p>
        <h2 id="home-platform-title">One platform. Endless possibilities.</h2>
        <p>Launch, manage, and grow gifting, rewards, customer engagement, and campaign programs from one connected system.</p>
      </header>

      <div class="mg-home-platform-grid">
        <article><span>🎁</span><h3>Gift Certificates</h3><p>Create, customize, sell, send, claim, and track digital gift certificates.</p></article>
        <article><span>🏆</span><h3>Workplace Rewards</h3><p>Recognize teams with milestones, recurring programs, and custom incentives.</p></article>
        <article><span>◎</span><h3>Loyalty & CRM</h3><p>Connect customer interactions to useful relationship and lifecycle records.</p></article>
        <article><span>✦</span><h3>Digital Incentives</h3><p>Use offers, referrals, contests, and rewards to encourage meaningful action.</p></article>
        <article><span>☆</span><h3>Events & Community</h3><p>Reward participation before, during, and after events and local programs.</p></article>
        <article><span>⌂</span><h3>In Your Store</h3><p>Connect digital promotions to future visits, claims, and redemption activity.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-integrations" id="integrations" aria-labelledby="home-integrations-title">
    <div class="mg-home-container">
      <div class="mg-home-coming-soon">
        <div class="mg-home-coming-soon__copy">
          <p class="mg-home-eyebrow"><span>Coming soon</span> POS and operations integrations</p>
          <h2 id="home-integrations-title">Connect Microgifter with the systems businesses already use.</h2>
          <p>Planned integrations will help merchants connect gifting, rewards, customer activity, and redemption workflows with leading payroll and point-of-sale platforms.</p>
        </div>
        <div class="mg-home-integration-grid" aria-label="Coming soon integrations">
          <article><strong>Gusto</strong><small>Payroll and workplace programs</small></article>
          <article><strong>Square</strong><small>Commerce and point of sale</small></article>
          <article><strong>Toast</strong><small>Restaurant point of sale</small></article>
          <article><strong>Other POS Systems</strong><small>Additional providers planned</small></article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-outcomes" id="outcomes" aria-labelledby="home-outcomes-title">
    <div class="mg-home-container">
      <header class="mg-home-section-heading mg-home-section-heading--left">
        <p class="mg-home-eyebrow">Real impact. Measurable results.</p>
        <h2 id="home-outcomes-title">Turn customer activity into a connected growth system.</h2>
        <p>Microgifter is designed to help businesses understand what was purchased, what is waiting to be claimed, which programs create engagement, and where future demand already exists.</p>
      </header>
      <div class="mg-home-outcome-grid">
        <article><span>01</span><h3>Generate pre-sale revenue</h3><p>Sell products and experiences before the recipient visits or redeems.</p></article>
        <article><span>02</span><h3>Create future visits</h3><p>Every outstanding gift or reward represents a future customer interaction.</p></article>
        <article><span>03</span><h3>Build lasting loyalty</h3><p>Use claims, referrals, campaigns, and repeat activity to deepen relationships.</p></article>
        <article><span>04</span><h3>Measure demand</h3><p>See which products, offers, and programs are producing committed activity.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-setup" id="how-it-works" aria-labelledby="home-setup-title">
    <div class="mg-home-container">
      <header class="mg-home-section-heading">
        <p class="mg-home-eyebrow">Easy setup</p>
        <h2 id="home-setup-title">Get started in minutes.</h2>
        <p>Begin with the tools you need today and expand as your programs grow.</p>
      </header>
      <div class="mg-home-steps">
        <article><span>1</span><h3>Create your account</h3><p>Set up your business or organization profile.</p></article>
        <article><span>2</span><h3>Add products and programs</h3><p>Create gift certificates, rewards, and campaigns.</p></article>
        <article><span>3</span><h3>Invite or import people</h3><p>Reach customers, employees, groups, and communities.</p></article>
        <article><span>4</span><h3>Launch and grow</h3><p>Track activity, claims, engagement, and future demand.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-home-section mg-home-faq" id="faq" aria-labelledby="home-faq-title">
    <div class="mg-home-container mg-home-faq__grid">
      <div>
        <p class="mg-home-eyebrow">FAQ</p>
        <h2 id="home-faq-title">Frequently asked questions.</h2>
        <div class="mg-home-faq-list">
          <details><summary>What is Microgifter?</summary><p>Microgifter is a connected platform for digital gifting, gift certificates, workplace rewards, campaigns, customer engagement, and pre-sale commerce.</p></details>
          <details><summary>How do gift certificates work?</summary><p>Businesses publish products, services, experiences, or custom values. A customer purchases and sends the gift, and the recipient securely claims and redeems it.</p></details>
          <details><summary>How do workplace rewards work?</summary><p>Organizations can create one-time or recurring recognition programs for employees, teams, milestones, contests, and community participation.</p></details>
          <details><summary>Can Microgifter support customer engagement?</summary><p>Yes. Purchases, claims, visits, campaigns, referrals, rewards, and redemption activity can contribute to a connected customer history.</p></details>
          <details><summary>Which integrations are available?</summary><p>Gusto, Square, Toast, and additional POS integrations are planned and currently labeled coming soon.</p></details>
          <details><summary>Can I customize gift certificates and rewards?</summary><p>Yes. Merchants and organizations can configure products, values, messages, delivery timing, campaigns, and reward programs.</p></details>
        </div>
      </div>
      <aside class="mg-home-support-card">
        <span aria-hidden="true">◉</span>
        <h3>Still have questions?</h3>
        <p>See how Microgifter can support your business, workplace, event, or local program.</p>
        <a class="mg-home-button mg-home-button--primary" href="/learn-more.php">Contact our team</a>
        <small>Book a guided product conversation.</small>
      </aside>
    </div>
  </section>

  <section class="mg-home-section mg-home-final" aria-labelledby="home-final-title">
    <div class="mg-home-container">
      <div class="mg-home-final__panel">
        <div>
          <p class="mg-home-eyebrow">Ready to grow?</p>
          <h2 id="home-final-title">Turn future demand into present-day revenue.</h2>
          <p>Start with digital gifting and expand into rewards, campaigns, loyalty, CRM, and automated local commerce.</p>
        </div>
        <div class="mg-home-actions">
          <a class="mg-home-button mg-home-button--primary" href="/signup.php">Get started free</a>
          <a class="mg-home-button mg-home-button--secondary" href="/learn-more.php">Book a demo</a>
        </div>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
