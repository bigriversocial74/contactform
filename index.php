<?php
declare(strict_types=1);

$page_title = 'Microgifter — The Future of Social Gifting';
$page_section = 'public';
$header_mode = 'public';
$page_body_class = 'mg-homepage-exact-v2 mg-homepage-core-v1';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/public-dark-shell.css',
    '/assets/css/public-header-cleanup.css',
    '/assets/css/homepage-parallax-exact-v2.css?v=2.1.0',
    '/assets/css/homepage-core-positioning-v1.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/homepage-parallax-exact-v2.js?v=2.0.0',
    '/assets/js/homepage-core-positioning-v1.js?v=1.0.0',
];
$page_meta = [
    'description' => 'Microgifter is the future of social gifting—turning future demand into real-time revenue while helping local businesses increase engagement, grow sales, and build loyalty.',
    'canonical' => 'https://microgifter.com/index.php',
    'og_title' => 'Microgifter — The Future of Social Gifting',
    'og_description' => 'Social gifting, pre-sale commerce, campaigns, CRM, and loyalty tools built to increase engagement, grow sales, and strengthen local customer relationships.',
];
$page_manifest = [
    'id' => 'index',
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
<div class="homepage-exact-v2 mg-home-rework" data-homepage-exact-v2 data-homepage-core-v1>
  <section class="hero-scroll" id="hero" aria-label="Microgifter introduction">
    <div class="hero-sticky">
      <div class="scene" id="scene">
        <img class="layer layer-mountains" id="mountains" src="/assets/images/mountains.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
        <img class="layer layer-foreground" id="foreground" src="/assets/images/foreground.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
        <img class="orb" id="orb" src="/assets/images/orb.png?v=2.0.0" alt="Glowing Microgifter commerce sphere" decoding="async" fetchpriority="high">

        <div class="hero-copy copy-one" id="heroCopy">
          <p class="eyebrow">The future of gifting starts local</p>
          <h1><span class="hero-title-lead">Microgifter Is the Future</span><span class="hero-title-arrived">of Social Gifting.</span></h1>
          <p class="intro">Discover, purchase, and send local products, services, experiences, and creative work to friends, family, coworkers, and communities.</p>
          <a class="primary-button" href="#growth-system">Enter Microgifter <span>→</span></a>
        </div>

        <div class="hero-copy copy-two" id="secondCopy" aria-hidden="true">
          <p class="eyebrow">Turn future demand into present-day revenue</p>
          <h2>Turn Future Demand Into Real-Time Revenue.</h2>
          <p class="intro">Gifts, rewards, referrals, recurring programs, and sponsored purchases create measurable demand and immediate revenue for local businesses.</p>
          <a class="primary-button" href="#pre-sale-revenue">See pre-sale commerce <span>↓</span></a>
        </div>

        <section class="growth-stage" id="growthStage" aria-hidden="true">
          <div class="growth-copy">
            <p class="eyebrow">Measurable customer growth</p>
            <h2>Increase Engagement. Grow Sales. Build Loyalty.</h2>
            <p class="intro">Microgifter connects customer activity across gifting, campaigns, claims, rewards, referrals, and repeat visits.</p>
          </div>
          <div class="growth-chart" role="img" aria-label="Animated chart showing connected customer growth signals">
            <div class="chart-header">
              <span>Customer growth signals</span>
              <strong>Connected</strong>
            </div>
            <svg viewBox="0 0 900 420" preserveAspectRatio="none" aria-hidden="true">
              <defs>
                <linearGradient id="chartFade" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#f1b99d" stop-opacity=".18"/>
                  <stop offset="100%" stop-color="#f1b99d" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <g class="chart-grid">
                <line x1="40" y1="80" x2="860" y2="80"/>
                <line x1="40" y1="155" x2="860" y2="155"/>
                <line x1="40" y1="230" x2="860" y2="230"/>
                <line x1="40" y1="305" x2="860" y2="305"/>
                <line x1="40" y1="380" x2="860" y2="380"/>
              </g>
              <path class="chart-area" d="M40 350 C120 334 145 320 205 301 S315 270 360 250 S455 224 510 190 S620 160 680 118 S790 88 860 52 L860 380 L40 380 Z"/>
              <path class="tracking-line line-1" pathLength="1" d="M40 350 C120 334 145 320 205 301 S315 270 360 250 S455 224 510 190 S620 160 680 118 S790 88 860 52"/>
              <path class="tracking-line line-2" pathLength="1" d="M40 366 C115 350 160 345 215 312 S325 302 380 259 S475 238 530 210 S635 190 700 145 S805 121 860 87"/>
              <path class="tracking-line line-3" pathLength="1" d="M40 335 C105 327 155 285 220 291 S320 255 385 240 S485 199 545 205 S655 148 715 132 S805 93 860 76"/>
              <path class="tracking-line line-4" pathLength="1" d="M40 375 C115 360 175 335 230 342 S340 302 400 290 S505 254 565 221 S670 215 735 160 S820 140 860 118"/>
              <path class="tracking-line line-5" pathLength="1" d="M40 355 C105 340 170 326 225 306 S335 282 395 246 S500 226 560 181 S675 166 735 111 S820 92 860 64"/>
            </svg>
            <div class="chart-legend" aria-hidden="true">
              <span>Engagement</span><span>Sales</span><span>Loyalty</span><span>Referrals</span><span>Repeat visits</span>
            </div>
          </div>
        </section>

        <div class="phase-indicator" aria-hidden="true">
          <span class="phase-dot is-active"></span>
          <span class="phase-dot"></span>
          <span class="phase-dot"></span>
        </div>
        <div class="scroll-note" aria-hidden="true"><span></span> Scroll to activate</div>
      </div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-growth-system" id="growth-system" data-core-chapter>
    <div class="mg-core-pin">
      <div class="mg-core-ambient" aria-hidden="true"></div>
      <div class="mg-core-layout">
        <header class="mg-core-copy" data-core-reveal>
          <p class="eyebrow">One connected customer growth system</p>
          <h2>Turn Every Gift Into a Lasting Customer Relationship.</h2>
          <p>Microgifter connects gifting, purchases, claims, visits, rewards, referrals, and customer activity in one platform.</p>
          <p>Instead of treating every transaction as a separate event, businesses can use each interaction to increase engagement, encourage repeat purchases, and build long-term loyalty.</p>
        </header>
        <div class="mg-core-stage-list" aria-label="Microgifter customer growth stages">
          <article class="mg-core-stage" data-core-step>
            <span>01</span><div><p>Attract</p><h3>Reach new customers.</h3><small>Use social gifting, local discovery, campaigns, referrals, and community promotions to create the first interaction.</small></div>
          </article>
          <article class="mg-core-stage" data-core-step>
            <span>02</span><div><p>Engage</p><h3>Create reasons to participate.</h3><small>Use gifts, rewards, contests, messages, and personalized offers to keep customers involved.</small></div>
          </article>
          <article class="mg-core-stage" data-core-step>
            <span>03</span><div><p>Convert</p><h3>Turn interest into revenue.</h3><small>Transform future demand into purchases, claims, visits, and measurable sales.</small></div>
          </article>
          <article class="mg-core-stage" data-core-step>
            <span>04</span><div><p>Retain</p><h3>Give customers reasons to return.</h3><small>Reward repeat activity and build stronger, longer-lasting customer relationships.</small></div>
          </article>
        </div>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-revenue" id="pre-sale-revenue" data-core-chapter>
    <div class="mg-core-pin">
      <div class="mg-core-layout mg-core-layout--reverse">
        <header class="mg-core-copy" data-core-reveal>
          <p class="eyebrow">Turn future demand into present-day revenue</p>
          <h2>Generate Revenue Before the Customer Arrives.</h2>
          <p>Traditional commerce waits for the customer to visit, order, or redeem. Microgifter helps businesses sell products and experiences in advance through social gifts, rewards, referrals, recurring programs, workplace purchases, and sponsored campaigns.</p>
          <strong class="mg-core-closing">Future customer activity becomes measurable present-day revenue.</strong>
        </header>
        <div class="mg-core-metric-stack" aria-label="Pre-sale commerce benefits">
          <article data-core-step><span>01</span><div><h3>Get Paid Today</h3><p>Receive revenue when the Microgift is purchased rather than waiting for the future visit or redemption.</p></div></article>
          <article data-core-step><span>02</span><div><h3>Measure Future Demand</h3><p>See which products, services, experiences, and campaigns are creating committed customer demand.</p></div></article>
          <article data-core-step><span>03</span><div><h3>Create Future Visits</h3><p>Every unclaimed or unredeemed Microgift represents a future customer interaction.</p></div></article>
          <article data-core-step><span>04</span><div><h3>Increase Downstream Spending</h3><p>Gift recipients can spend beyond the original value, return later, or introduce additional customers.</p></div></article>
        </div>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-lifecycle" id="how-it-works" data-core-chapter>
    <div class="mg-core-pin">
      <header class="mg-core-copy mg-core-copy--center" data-core-reveal>
        <p class="eyebrow">A simple social commerce lifecycle</p>
        <h2>Sell. Send. Claim. Engage. Repeat.</h2>
        <p>Microgifter connects the complete social gifting and customer-growth journey without requiring businesses to install new hardware or manage disconnected technology.</p>
      </header>
      <div class="mg-lifecycle-track" aria-label="How Microgifter works">
        <article data-core-step><span>01</span><h3>Create</h3><p>Publish products, services, experiences, rewards, and promotional offers.</p></article>
        <article data-core-step><span>02</span><h3>Sell</h3><p>Customers purchase Microgifts for friends, family, coworkers, groups, and communities.</p></article>
        <article data-core-step><span>03</span><h3>Send</h3><p>Deliver gifts immediately, schedule them for later, or include them in recurring programs.</p></article>
        <article data-core-step><span>04</span><h3>Claim</h3><p>Recipients securely receive, accept, and claim their Microgifts.</p></article>
        <article data-core-step><span>05</span><h3>Redeem</h3><p>Businesses verify redemption while maintaining the transaction and customer history.</p></article>
        <article data-core-step><span>06</span><h3>Grow</h3><p>Campaigns, rewards, referrals, and loyalty programs create the next interaction.</p></article>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-section mg-platform-overview" id="platform" data-core-reveal>
    <div class="mg-core-section__inner">
      <header class="mg-core-copy mg-core-copy--wide">
        <p class="eyebrow">One platform for social gifting and customer growth</p>
        <h2>Everything You Need to Sell, Engage, and Grow.</h2>
        <p>Microgifter brings social gifting, pre-sale commerce, customer relationship management, campaigns, loyalty, rewards, and automated growth programs into one connected platform.</p>
      </header>
      <div class="mg-platform-grid" aria-label="Microgifter platform capabilities">
        <article><span>01</span><h3>Social Gifting</h3><p>Local products, services, experiences, and creative work sent now or later.</p></article>
        <article><span>02</span><h3>Pre-Sale Commerce</h3><p>Capture future demand and generate revenue before fulfillment.</p></article>
        <article><span>03</span><h3>Merchant CRM</h3><p>Connect customer activity to one usable relationship record.</p></article>
        <article><span>04</span><h3>Campaigns</h3><p>Create offers, contests, referrals, QR programs, and creator campaigns.</p></article>
        <article><span>05</span><h3>Loyalty & Rewards</h3><p>Reward the actions that lead to repeat visits, purchases, and referrals.</p></article>
        <article><span>06</span><h3>Recurring Commerce</h3><p>Support workplace, group, community, and scheduled gifting programs.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-gifting" id="social-gifting" data-core-chapter>
    <div class="mg-core-pin">
      <div class="mg-core-layout">
        <header class="mg-core-copy" data-core-reveal>
          <p class="eyebrow">The future of gifting starts local</p>
          <h2>Sell Today. Send Now or Later.</h2>
          <p>Sell local products, services, experiences, and creative work that customers can purchase now and send to friends, family, coworkers, groups, and communities.</p>
          <a class="primary-button" href="/discover.php">Explore social gifting <span>→</span></a>
        </header>
        <div class="mg-gifting-panels">
          <article data-core-step><span>Social Gifting</span><h3>Personal, flexible, and local.</h3><p>Customers can send immediately, schedule delivery, or create recurring gifting programs.</p></article>
          <article data-core-step><span>Pre-Sale Commerce</span><h3>Revenue now. Fulfillment later.</h3><p>Businesses receive revenue when the gift is purchased while claim and redemption activity remains connected.</p></article>
          <article data-core-step><span>Group & Community</span><h3>Gifting beyond one-to-one.</h3><p>Support workplace rewards, appreciation programs, contests, community prizes, and sponsored local commerce.</p></article>
        </div>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-crm" id="merchant-crm" data-core-chapter>
    <div class="mg-core-pin">
      <div class="mg-core-layout mg-core-layout--reverse">
        <header class="mg-core-copy" data-core-reveal>
          <p class="eyebrow">Customer activity becomes customer memory</p>
          <h2>Turn Every Interaction Into a Usable Customer Record.</h2>
          <p>Connect purchases, claims, visits, referrals, rewards, campaign activity, and redemption history to one customer record.</p>
          <p>Businesses gain a clearer view of who their customers are, how they engage, and what could encourage them to return.</p>
          <a class="primary-button" href="#merchant-crm">Explore the Merchant CRM <span>→</span></a>
        </header>
        <div class="mg-crm-console" data-core-step>
          <div class="mg-crm-console__head"><span>Customer relationship record</span><strong>Live</strong></div>
          <div class="mg-crm-profile"><i>AJ</i><div><small>Active customer</small><h3>Alex Johnson</h3><p>6 visits · 3 claims · loyalty active</p></div><b>92</b></div>
          <div class="mg-crm-signals">
            <article><span>Purchases</span><strong>18</strong></article>
            <article><span>Claims</span><strong>3</strong></article>
            <article><span>Referrals</span><strong>7</strong></article>
            <article><span>Reward events</span><strong>12</strong></article>
          </div>
          <div class="mg-crm-timeline" aria-label="Connected customer history">
            <span>Gift purchased</span><span>Recipient claimed</span><span>Store visit</span><span>Reward earned</span><span>Referral completed</span>
          </div>
        </div>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-campaigns" id="campaigns" data-core-chapter>
    <div class="mg-core-pin">
      <header class="mg-core-copy mg-core-copy--wide" data-core-reveal>
        <p class="eyebrow">Create more reasons to engage</p>
        <h2>Launch Campaigns That Drive Measurable Customer Action.</h2>
        <p>Create offers, rewards, contests, referrals, QR campaigns, creator campaigns, loyalty programs, and local promotions from one connected platform.</p>
      </header>
      <div class="mg-campaign-track" data-core-track aria-label="Microgifter campaign types">
        <article data-core-step><span>01</span><h3>Offers & Promotions</h3><p>Product offers, seasonal promotions, limited-time incentives, and customer reactivation campaigns.</p></article>
        <article data-core-step><span>02</span><h3>Rewards & Loyalty</h3><p>Recognize purchases, visits, referrals, participation, and customer milestones.</p></article>
        <article data-core-step><span>03</span><h3>Contests & Challenges</h3><p>Increase engagement through prizes, tasks, leaderboards, and community participation.</p></article>
        <article data-core-step><span>04</span><h3>Creator Campaigns</h3><p>Manage creators, deliverables, attribution, compensation, and connected CRM outcomes.</p></article>
        <article data-core-step><span>05</span><h3>QR & Location Campaigns</h3><p>Turn stores, events, packaging, and printed materials into measurable digital activity.</p></article>
      </div>
      <a class="primary-button mg-core-floating-action" href="#campaigns">Build a campaign <span>→</span></a>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-chapter mg-core-chapter--sticky mg-core-loyalty" id="loyalty" data-core-chapter>
    <div class="mg-core-pin">
      <div class="mg-core-layout">
        <header class="mg-core-copy" data-core-reveal>
          <p class="eyebrow">Reward the actions that help your business grow</p>
          <h2>Build Loyalty Through Meaningful Customer Participation.</h2>
          <p>Reward repeat purchases, referrals, visits, claims, engagement, milestones, and community activity.</p>
          <strong class="mg-core-closing">Loyalty grows when customers have meaningful reasons to stay connected.</strong>
          <a class="primary-button" href="#loyalty">Build customer loyalty <span>→</span></a>
        </header>
        <div class="mg-loyalty-orbit" aria-label="Rewardable customer actions">
          <div class="mg-loyalty-center"><span>Loyalty</span><strong>Connected</strong></div>
          <article data-core-step>Repeat purchases</article>
          <article data-core-step>Customer referrals</article>
          <article data-core-step>Product claims</article>
          <article data-core-step>Store visits</article>
          <article data-core-step>Campaign participation</article>
          <article data-core-step>Customer milestones</article>
          <article data-core-step>Group gifting</article>
          <article data-core-step>Workplace recognition</article>
        </div>
      </div>
      <div class="mg-core-progress" aria-hidden="true"><span></span></div>
    </div>
  </section>

  <section class="mg-core-section mg-local-business" id="local-business" data-core-reveal>
    <div class="mg-core-section__inner">
      <header class="mg-core-copy mg-core-copy--wide">
        <p class="eyebrow">Local commerce without the complexity</p>
        <h2>Powerful Commerce Tools Without More Hardware or Technical Overhead.</h2>
        <p>Microgifter is designed for independent businesses, artists, creators, restaurants, venues, hospitality companies, service providers, and local organizations that want better digital commerce without managing complicated systems.</p>
      </header>
      <div class="mg-local-grid">
        <article><span>01</span><h3>No New Hardware</h3><p>Run social gifting, campaigns, claims, rewards, and customer engagement through a digital platform.</p></article>
        <article><span>02</span><h3>One Connected System</h3><p>Stop separating customer activity, promotions, gifting, and transaction history across multiple tools.</p></article>
        <article><span>03</span><h3>Start Small and Scale</h3><p>Begin with a few products and campaigns, then expand into locations, recurring commerce, and enterprise programs.</p></article>
        <article><span>04</span><h3>Built for Local Economies</h3><p>Support hospitality, restaurants, bars, travel, fitness, events, entertainment, artists, services, and community programs.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-core-section mg-outcomes" id="outcomes" data-core-reveal>
    <div class="mg-core-section__inner">
      <header class="mg-core-copy mg-core-copy--center">
        <p class="eyebrow">Measurable customer growth</p>
        <h2>Increase Engagement. Grow Sales. Build Loyalty.</h2>
      </header>
      <div class="mg-outcome-grid">
        <article><span>01</span><h3>Increase Customer Engagement</h3><p>Create more meaningful interactions through gifting, rewards, campaigns, contests, referrals, and customer programs.</p></article>
        <article><span>02</span><h3>Increase Sales</h3><p>Generate revenue through pre-sale purchases, gift recipients, campaign activity, repeat visits, and recurring programs.</p></article>
        <article><span>03</span><h3>Build Customer Loyalty</h3><p>Connect each interaction to a relationship history that supports better rewards and future offers.</p></article>
        <article><span>04</span><h3>Create New Customers</h3><p>Every Microgift introduces a recipient to a product, service, experience, creator, or local business.</p></article>
        <article><span>05</span><h3>Encourage Repeat Visits</h3><p>Claims, rewards, referrals, and campaigns give customers more reasons to return.</p></article>
        <article><span>06</span><h3>Measure Future Demand</h3><p>Understand what has been purchased, what is waiting to be claimed, and what future activity already exists.</p></article>
      </div>

      <div class="mg-final-cta">
        <div class="mg-final-cta__landscape" aria-hidden="true">
          <img src="/assets/images/mountains.png?v=2.0.0" alt="">
          <img src="/assets/images/foreground.png?v=2.0.0" alt="">
          <img src="/assets/images/orb.png?v=2.0.0" alt="">
        </div>
        <div class="mg-final-cta__copy">
          <p class="eyebrow">The future of local commerce</p>
          <h2>Make Local the Easy Choice.</h2>
          <p>Microgifter connects customers with independent businesses through social gifting, pre-sale commerce, campaigns, rewards, loyalty, and customer-growth tools.</p>
          <p>Customers gain better local gifting opportunities. Businesses gain immediate revenue, stronger engagement, new customers, and lasting customer relationships.</p>
          <div class="mg-final-cta__actions">
            <a class="primary-button" href="/signup.php">Get Started <span>→</span></a>
            <a class="primary-button primary-button--secondary" href="/learn-more.php">Book a Demo</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
