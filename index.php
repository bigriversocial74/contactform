<?php
declare(strict_types=1);

/*
 * Microgifter public homepage.
 * The universal header/footer owns authentication and shared navigation.
 */

$page_title = 'Microgifter | Social Gifting, Loyalty CRM & Local Commerce';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/homepage-local-business-v1.css?v=1.0.0',
    '/assets/css/homepage-crm-integrations-v1.css?v=1.0.0',
];
$page_scripts = [];
$page_manifest = [
    'id' => 'index',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'How It Works', 'href' => '/index.php#how-it-works'],
            ['label' => 'Find Gifts', 'href' => '/discover.php'],
            ['label' => 'Rewards', 'href' => '/index.php#rewards'],
            ['label' => 'For Businesses', 'href' => '/index.php#businesses'],
            ['label' => 'About', 'href' => '/about.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
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

<main class="mg-lb-home" id="top">
  <section class="mg-lb-hero" aria-labelledby="mgLbHeroTitle">
    <div class="mg-lb-hero-main">
      <div class="mg-lb-hero-copy">
        <span class="mg-lb-eyebrow">For local businesses</span>
        <h1 id="mgLbHeroTitle">Drive Traffic.<br>Build Loyalty.<br><span>Reward Customers.</span></h1>
        <p>Microgifter helps local businesses turn everyday customer interactions into social gifts, repeat visits, measurable rewards, and stronger owned relationships.</p>

        <ul class="mg-lb-checklist" aria-label="Local business benefits">
          <li>Attract new customers in your community</li>
          <li>Pre-sell products, gifts, and experiences</li>
          <li>Reward loyalty, referrals, and repeat visits</li>
          <li>Track claims, redemptions, and customer engagement</li>
        </ul>

        <div class="mg-lb-hero-actions">
          <a class="mg-lb-button is-primary" href="/signup.php">Create Account</a>
          <a class="mg-lb-button is-secondary" href="/learn-more.php">Contact Sales</a>
        </div>
      </div>

      <figure class="mg-lb-hero-visual">
        <img src="/assets/images/public-home-merchant-hero.jpg?v=2.0.0" alt="Local coffee shop owner using Microgifter to reward customers" width="640" height="427" fetchpriority="high" decoding="async">
      </figure>
    </div>

    <div class="mg-lb-benefit-rail" aria-label="Microgifter business outcomes">
      <article>
        <span class="mg-lb-rail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12A7 7 0 1 0 5 9c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg></span>
        <div><h2>Drive More Traffic</h2><p>Get discovered by nearby customers already looking for something worth sharing.</p></div>
      </article>
      <article>
        <span class="mg-lb-rail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg></span>
        <div><h2>Build Loyalty</h2><p>Reward the customer actions that create lasting local relationships.</p></div>
      </article>
      <article>
        <span class="mg-lb-rail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/><path d="m4 8 6-4 6 6 6-5"/></svg></span>
        <div><h2>Increase Revenue</h2><p>Turn gifts, campaigns, and repeat visits into measurable commerce.</p></div>
      </article>
      <article>
        <span class="mg-lb-rail-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10h18M5 10v9h14v-9M4 10l2-5h12l2 5"/><path d="M9 19v-5h6v5"/></svg></span>
        <div><h2>Simple to Operate</h2><p>Run gifting, rewards, CRM, campaigns, and redemption from one platform.</p></div>
      </article>
    </div>
  </section>

  <section class="mg-lb-section mg-lb-platform" id="businesses" aria-labelledby="mgLbPlatformTitle">
    <div class="mg-lb-container">
      <div class="mg-lb-section-head is-centered">
        <span class="mg-lb-eyebrow">One connected growth platform</span>
        <h2 id="mgLbPlatformTitle">Everything your business needs to turn attention into repeat customers.</h2>
        <p>Replace scattered promotions, gift certificates, loyalty tools, messages, and redemption records with one campaign-based merchant CRM.</p>
      </div>

      <div class="mg-lb-feature-grid">
        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 7v13M12 7H7.5A2.5 2.5 0 1 1 10 4.5L12 7Zm0 0h4.5A2.5 2.5 0 1 0 14 4.5L12 7Z"/></svg></span>
          <h3>Social Gifting</h3>
          <p>Sell products and experiences that customers can purchase now and send later to friends, family, coworkers, and communities.</p>
          <a href="/discover.php">Explore local gifts <span aria-hidden="true">→</span></a>
        </article>

        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c.9-4 3-6 6-6s5.1 2 6 6M16 8h5M16 12h5M17 16h4"/></svg></span>
          <h3>Merchant CRM</h3>
          <p>Connect purchases, claims, visits, messages, referrals, and reward activity to usable customer records.</p>
          <a href="/learn-more.php">See the CRM <span aria-hidden="true">→</span></a>
        </article>

        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 14h3l10 4V6L7 10H4v4Z"/><path d="M7 14v4h3l1-3M19 8l2-2M20 12h3M19 16l2 2"/></svg></span>
          <h3>Campaigns & Offers</h3>
          <p>Launch offers, rewards, contests, referrals, QR campaigns, and local promotions with measurable outcomes.</p>
          <a href="/learn-more.php">Build a campaign <span aria-hidden="true">→</span></a>
        </article>

        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 3V5Z"/><path d="M8 9h8M8 13h5"/></svg></span>
          <h3>Customer Messaging</h3>
          <p>Follow up after a gift, reward, claim, or visit without separating customer communication from the transaction history.</p>
          <a href="/learn-more.php">Connect conversations <span aria-hidden="true">→</span></a>
        </article>

        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 8h8v8H8z"/><path d="M2 8h2M2 16h2M20 8h2M20 16h2"/></svg></span>
          <h3>Claim & Redemption</h3>
          <p>Track every Microgift from purchase through inbox, sent, claimed, and merchant-verified redemption states.</p>
          <a href="/learn-more.php">Follow the lifecycle <span aria-hidden="true">→</span></a>
        </article>

        <article class="mg-lb-feature-card">
          <span class="mg-lb-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/></svg></span>
          <h3>Automated Commerce</h3>
          <p>Use recurring programs, agent-assisted gifting, workplace rewards, and campaign automation to create ongoing demand.</p>
          <a href="/learn-more.php">Automate growth <span aria-hidden="true">→</span></a>
        </article>
      </div>
    </div>
  </section>

  <?php require __DIR__ . '/includes/landing/homepage-crm-integrations.php'; ?>

  <section class="mg-lb-section mg-lb-workflow" id="how-it-works" aria-labelledby="mgLbWorkflowTitle">
    <div class="mg-lb-container mg-lb-workflow-grid">
      <div class="mg-lb-workflow-copy">
        <span class="mg-lb-eyebrow is-light">How it works</span>
        <h2 id="mgLbWorkflowTitle">A simple customer loop from campaign to claim.</h2>
        <p>Create a valuable reason to act, deliver it through the channels your customers already use, and keep the resulting relationship inside one connected system.</p>

        <ol class="mg-lb-steps">
          <li><b>01</b><div><h3>Create the offer</h3><p>Choose a product, reward, gift, referral, contest, or customer action.</p></div></li>
          <li><b>02</b><div><h3>Share it everywhere</h3><p>Publish through QR, social, email, landing pages, embeds, feeds, or direct delivery.</p></div></li>
          <li><b>03</b><div><h3>Track the response</h3><p>Capture source, recipient, claim, redemption, visit, and follow-up activity.</p></div></li>
          <li><b>04</b><div><h3>Improve the next campaign</h3><p>Use customer and demand signals to create more relevant offers and repeat revenue.</p></div></li>
        </ol>
      </div>

      <div class="mg-lb-product-stage" aria-label="Microgifter platform preview">
        <div class="mg-lb-screen-card">
          <img src="/images/desktop_bg_main_v10.png" alt="Microgifter merchant dashboard" loading="lazy" decoding="async">
        </div>
        <div class="mg-lb-phone-card">
          <img src="/images/mobile_bg_main.png" alt="Microgifter mobile experience" loading="lazy" decoding="async">
        </div>
        <div class="mg-lb-stage-note">
          <strong>Campaign → Claim</strong>
          <span>One connected customer record</span>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-lb-section mg-lb-rewards" id="rewards" aria-labelledby="mgLbRewardsTitle">
    <div class="mg-lb-container">
      <div class="mg-lb-section-head">
        <span class="mg-lb-eyebrow">Reward the actions that matter</span>
        <h2 id="mgLbRewardsTitle">Turn ordinary customer moments into reasons to return.</h2>
        <p>Build reward paths around purchases, referrals, visits, recognition, media engagement, contests, and community participation.</p>
      </div>

      <div class="mg-lb-reward-layout">
        <article class="mg-lb-reward-feature">
          <div class="mg-lb-reward-copy">
            <span>Campaign-based loyalty</span>
            <h3>Reward behavior, not just transactions.</h3>
            <p>Microgifter helps merchants recognize the full customer journey—from the first campaign click to the next visit.</p>
            <ul>
              <li>Purchase and visit rewards</li>
              <li>Referral and social gifting incentives</li>
              <li>Watch-and-listen engagement campaigns</li>
              <li>Workplace and community recognition</li>
            </ul>
          </div>
          <div class="mg-lb-reward-meter" aria-label="Connected customer journey">
            <span style="--level:92%"><b>Discover</b></span>
            <span style="--level:78%"><b>Engage</b></span>
            <span style="--level:86%"><b>Claim</b></span>
            <span style="--level:70%"><b>Return</b></span>
          </div>
        </article>

        <div class="mg-lb-reward-stack">
          <article><span>01</span><div><h3>Bring customers in</h3><p>Use local offers, gifts, and rewards to create a clear reason to visit.</p></div></article>
          <article><span>02</span><div><h3>Recognize loyalty</h3><p>Reward repeat behavior, referrals, recognition, and community support.</p></div></article>
          <article><span>03</span><div><h3>Keep the relationship</h3><p>Use the connected CRM to follow up with the right customer at the right time.</p></div></article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-lb-section mg-lb-use-cases" aria-labelledby="mgLbUseCasesTitle">
    <div class="mg-lb-container">
      <div class="mg-lb-section-head is-centered">
        <span class="mg-lb-eyebrow">Built for everyday local commerce</span>
        <h2 id="mgLbUseCasesTitle">One platform. Many ways to create local value.</h2>
      </div>

      <div class="mg-lb-use-grid">
        <article><small>Restaurants & bars</small><h3>Fill slower hours and bring regulars back.</h3><p>Promote menu items, happy-hour offers, event nights, gift experiences, and repeat-visit rewards.</p></article>
        <article><small>Retail & services</small><h3>Turn products and appointments into shareable gifts.</h3><p>Pre-sell local products, service credits, appointments, memberships, and customer referrals.</p></article>
        <article><small>Fitness & wellness</small><h3>Move trial customers into lasting routines.</h3><p>Reward first visits, class streaks, referrals, memberships, and wellness milestones.</p></article>
        <article><small>Events & hospitality</small><h3>Connect attendance, rewards, and follow-up.</h3><p>Issue passes, upgrades, contest prizes, drink rewards, guest experiences, and post-event offers.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-lb-proof" aria-labelledby="mgLbProofTitle">
    <div class="mg-lb-container">
      <div class="mg-lb-proof-copy">
        <span class="mg-lb-eyebrow is-light">A connected operating layer</span>
        <h2 id="mgLbProofTitle">See the complete value of every customer action.</h2>
        <p>Microgifter connects the offer, the recipient, the campaign source, the claim, the redemption, and the next opportunity—without forcing merchants to rebuild the customer story across disconnected tools.</p>
        <a class="mg-lb-button is-light" href="/learn-more.php">See Microgifter in action</a>
      </div>

      <div class="mg-lb-proof-grid" aria-label="Microgifter platform capabilities">
        <article><strong>4</strong><span>Tracked ownership states</span><small>Inbox, Sent, Claimed, Redeemed</small></article>
        <article><strong>360°</strong><span>Customer activity view</span><small>Commerce, campaigns, messages, claims</small></article>
        <article><strong>1</strong><span>Connected merchant CRM</span><small>Gifting, loyalty, rewards, automation</small></article>
        <article><strong>24/7</strong><span>Commerce-ready programs</span><small>Recurring, scheduled, and agent-assisted</small></article>
      </div>
    </div>
  </section>

  <section class="mg-lb-final" aria-labelledby="mgLbFinalTitle">
    <div class="mg-lb-final-inner">
      <span class="mg-lb-eyebrow">Grow local relationships</span>
      <h2 id="mgLbFinalTitle">Give customers a reason to visit, share, claim, and come back.</h2>
      <p>Start with one product, one campaign, or one reward. Microgifter connects the rest of the customer journey.</p>
      <div class="mg-lb-final-actions">
        <a class="mg-lb-button is-primary" href="/signup.php">Create Account</a>
        <a class="mg-lb-button is-secondary" href="/learn-more.php">Book a Demo</a>
        <a class="mg-lb-text-link" href="/discover.php">Explore local gifts <span aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
