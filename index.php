<?php
declare(strict_types=1);

/*
 * Microgifter public homepage.
 * The universal header/footer owns the logged-out phone number and market ticker.
 */

$page_title = 'Microgifter | Invest Local, Discover Value & Support Humans';
$page_section = 'public';
$header_mode = 'public';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/homepage-drm.css',
    '/assets/css/homepage-hero-search.css',
];
$page_scripts = [
    '/assets/js/homepage-drm.js',
];
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
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Merchant', 'href' => '/merchant.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
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

<div class="mg-home-page" id="top">
  <div class="mg-progress" aria-hidden="true"><span class="mg-progress-bar" id="mgProgressBar"></span></div>

  <section class="mg-hero" aria-labelledby="mgHeroTitle">
    <div class="mg-hero-grid">
      <div class="mg-hero-copy" data-reveal="left">
        <form class="mg-hero-search" data-hero-search action="/discover.php" method="get" role="search" autocomplete="off">
          <label class="mg-hero-search-label" for="mgHeroSearch">Search businesses or creators</label>
          <div class="mg-hero-search-control">
            <input id="mgHeroSearch" name="q" type="search" data-hero-search-input placeholder="Search business name or user name" aria-label="Search business name or user name">
            <button type="submit">Search</button>
          </div>
          <div class="mg-hero-search-results" data-hero-search-results hidden></div>
        </form>
        <h1 class="mg-title" id="mgHeroTitle"><span class="mg-title-line">THE ALL-IN-ONE</span><span class="mg-title-line">SOCIAL CRM FOR</span><span class="mg-title-line">BUSINESSES &amp; CREATORS</span></h1>
        <p class="mg-note"><strong>Save time. Add customers. Increase sales.</strong> One dashboard gives you social tools, customer acquisition resources, and customer messaging designed to help you grow.</p>
        <div class="mg-actions">
          <a class="mg-btn mg-btn-primary" href="/signup.php">Create Your Wallet <span aria-hidden="true">→</span></a>
          <a class="mg-btn mg-btn-secondary" href="/learn-more.php">Create Merchant Account <span aria-hidden="true">→</span></a>
        </div>
      </div>

      <div class="mg-hero-visual" data-reveal="right" aria-label="Microgifter product preview">
        <img class="mg-desktop" src="/images/desktop_bg_main_v10.png" alt="Microgifter desktop product preview" decoding="async" fetchpriority="high">
        <img class="mg-phone-img" src="/images/mobile_bg_main.png" alt="Microgifter mobile product preview" decoding="async" fetchpriority="high">
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchants" aria-labelledby="merchantTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">For growth</span>
        <h2 class="mg-section-title" id="merchantTitle" data-reveal="left" style="--delay:80ms">Your business already creates value. Microgifter helps you capture it, understand it, and turn it into repeatable growth.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:160ms">Every offer, reward, gift certificate, campaign, claim, redemption, and customer interaction creates a signal. Microgifter organizes those signals into a practical data layer that helps local businesses make smarter offers, support better customers, and earn more from future demand.</p>
      </div>

      <div class="mg-feature-panels mg-growth-feature-grid">
        <article class="mg-panel" data-reveal="scale">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 5l-5 7 5 7M16 5l5 7-5 7M14 4l-4 16"/></svg></div>
            <div><h3>Create Value</h3><p>Build offers, rewards, gift certificates, pre-sale products, and local experiences that give customers a clear reason to act.</p></div>
          </div>
          <div class="mg-codebox" aria-label="Create value data example"><span class="gold">VALUE OBJECT</span><br>{<br>&nbsp;&nbsp;<span class="muted">"offer"</span>: <span class="green">"coffee_for_two"</span>,<br>&nbsp;&nbsp;<span class="muted">"price"</span>: <span class="green">"$18.00"</span>,<br>&nbsp;&nbsp;<span class="muted">"goal"</span>: <span class="green">"bring two humans in"</span>,<br>&nbsp;&nbsp;<span class="muted">"signal"</span>: <span class="green">"visit_intent"</span><br>}</div>
          <div class="mg-panel-tags"><span>Offer</span><span>Support</span><span>Action</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:80ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 13h4l11-6v10L8 11H4v2Z"/><path d="M8 11v7a2 2 0 0 0 2 2h1M21 9c1 2 1 4 0 6"/></svg></div>
            <div><h3>Capture Data</h3><p>Turn claims, purchases, redemptions, QR scans, shares, saves, and visits into clean customer and campaign data.</p></div>
          </div>
          <div class="mg-signal-list" aria-label="Captured data signals">
            <div class="mg-signal-row"><span>Claim source</span><span>QR table tent</span></div>
            <div class="mg-signal-row"><span>Customer action</span><span>Saved reward</span></div>
            <div class="mg-signal-row"><span>Redemption</span><span>Verified</span></div>
            <div class="mg-signal-row"><span>Follow-up path</span><span>Ready</span></div>
          </div>
          <div class="mg-panel-tags"><span>Signal</span><span>Profile</span><span>History</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:160ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/><path d="M8 11h6M11 8v6"/></svg></div>
            <div><h3>Discover Value</h3><p>See which offers, prices, channels, timing, and experiences customers actually respond to — not what you guessed they wanted.</p></div>
          </div>
          <div class="mg-value-score" aria-label="Value discovery score">
            <strong>87</strong>
            <span>Value confidence score based on claims, redemption, repeat activity, and customer engagement.</span>
          </div>
          <div class="mg-panel-tags"><span>Pattern</span><span>Demand</span><span>Timing</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:240ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21s-7-4.2-9-9.5C1.8 8.4 3.7 5 7.1 5c2 0 3.5 1.1 4.9 3 1.4-1.9 2.9-3 4.9-3 3.4 0 5.3 3.4 4.1 6.5C19 16.8 12 21 12 21Z"/></svg></div>
            <div><h3>Activate Support</h3><p>Give customers a direct way to support real local businesses and real humans while creating measurable demand for the merchant.</p></div>
          </div>
          <div class="mg-mini-path" aria-label="Support activation path"><span>Find</span><span>Support</span><span>Gift</span><span>Redeem</span></div>
          <div class="mg-panel-tags"><span>Human</span><span>Local</span><span>Direct</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:320ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-5 4.2-8 8-8s6.5 3 8 8"/></svg></div>
            <div><h3>Utilize Insight</h3><p>Use captured data to personalize follow-up, improve the next campaign, identify valuable customers, and reduce wasted marketing effort.</p></div>
          </div>
          <div class="mg-signal-list" aria-label="Utilized data actions">
            <div class="mg-signal-row"><span>Best audience</span><span>Repeat guests</span></div>
            <div class="mg-signal-row"><span>Best offer</span><span>Two-person gifts</span></div>
            <div class="mg-signal-row"><span>Best channel</span><span>In-store QR</span></div>
            <div class="mg-signal-row"><span>Next action</span><span>Send follow-up</span></div>
          </div>
          <div class="mg-panel-tags"><span>Learn</span><span>Target</span><span>Grow</span></div>
        </article>

        <article class="mg-panel" data-reveal="scale" style="--delay:400ms">
          <div class="mg-panel-head">
            <div class="mg-panel-icon"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 19V5"/><path d="M4 19h16"/><path d="M7 16l4-5 3 3 5-8"/></svg></div>
            <div><h3>Earn on Value</h3><p>Turn support, customer data, and future demand into repeat revenue — earning more from the relationships the business already owns.</p></div>
          </div>
          <div class="mg-value-score" aria-label="Earned value example">
            <strong>3.4x</strong>
            <span>Example repeat-value lift when claims, redemptions, and follow-up campaigns work as one connected loop.</span>
          </div>
          <div class="mg-panel-tags"><span>Return</span><span>Repeat</span><span>Revenue</span></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-section" id="future-demand" aria-labelledby="agenticTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">Promotional CRM</span>
          <h2 id="agenticTitle">The value is not only the transaction. It is the business intelligence created around it.</h2>
          <p>Local businesses generate valuable signals every day, but most of that value disappears across social posts, paper coupons, disconnected payment systems, and untracked customer behavior. Microgifter captures those signals and turns them into a usable operating layer for growth.</p>
        </div>
        <div>
          <div class="mg-agentic-panel">
            <article class="mg-agentic-card" data-reveal="up">
              <h3>What business data value means</h3>
              <p>Every offer should teach the business something: what customers value, which channel moved them, what they claimed, whether they redeemed, and what made them return.</p>
              <p>Microgifter makes that trail operational, so businesses can stop guessing and start using their own data to build stronger customer relationships and more efficient revenue loops.</p>
            </article>
            <article class="mg-agentic-card" data-reveal="up" style="--delay:120ms">
              <h3>The efficient value loop</h3>
              <div class="mg-agent-flow" aria-label="Promotional CRM revenue flow">
                <div class="mg-flow-item"><span>Create a local value action</span><span aria-hidden="true">→</span></div>
                <div class="mg-flow-item"><span>Capture the customer signal</span><span aria-hidden="true">→</span></div>
                <div class="mg-flow-item"><span>Connect actions to business intelligence</span><span aria-hidden="true">→</span></div>
                <div class="mg-flow-item"><span>Use insight to improve the next offer</span><span aria-hidden="true">→</span></div>
                <div class="mg-flow-item"><span>Earn more from owned relationships</span><span aria-hidden="true">✓</span></div>
              </div>
            </article>
          </div>
          <div class="mg-social-proof" data-reveal="up" style="--delay:220ms">
            <div class="mg-proof-pill">Customer data</div>
            <div class="mg-proof-pill">Offer signals</div>
            <div class="mg-proof-pill">Redemption history</div>
            <div class="mg-proof-pill">Demand patterns</div>
            <div class="mg-proof-pill">Repeat revenue</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section" id="how-it-works" aria-labelledby="howTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">Revenue loop</span>
          <h2 id="howTitle">A simple loop for creating, capturing, and utilizing local value.</h2>
          <p>The goal is to make local commerce smarter without making it complicated: create a valuable customer action, capture the signal it creates, connect it to a customer record, and use the result to make the next action more profitable.</p>
        </div>
        <div class="mg-story-list">
          <article class="mg-story-step" data-reveal="up"><b>01</b><div><h3>Create value</h3><p>Build an offer, reward, gift certificate, contest entry, landing page, or pre-sale product with a clear customer benefit and a measurable business goal.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:100ms"><b>02</b><div><h3>Capture the data</h3><p>Distribute through QR codes, feeds, newsletters, social posts, table tents, landing pages, partner apps, or the API while capturing where each action came from.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:200ms"><b>03</b><div><h3>Discover what matters</h3><p>Connect claims, redemptions, purchases, visits, profiles, and campaign sources to discover what customers actually value and what drives them back.</p></div></article>
          <article class="mg-story-step" data-reveal="up" style="--delay:300ms"><b>04</b><div><h3>Utilize the insight</h3><p>Use the data to improve targeting, personalize follow-up, forecast demand, increase repeat visits, and earn more from the relationships already created.</p></div></article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section" id="merchant-examples" aria-labelledby="examplesTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Merchant examples</span>
        <h2 class="mg-section-title" id="examplesTitle" data-reveal="left">Every local action can become a data asset.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">A coffee reward, lunch special, event pass, or trial class is more than a sale. It shows what customers value, when they act, how they support, and what brings them back.</p>
      </div>
      <div class="mg-examples-grid">
        <article class="mg-example-card" data-reveal="up"><small>Coffee shop</small><h3>Coffee for Two</h3><p>Captures gift behavior, visit timing, redemption activity, and the first signal of a repeat customer.</p><div class="mg-example-meta"><div><span>Price</span><strong>$18</strong></div><div><span>Signal</span><strong>Visit intent</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:90ms"><small>Restaurant</small><h3>Lunch Special</h3><p>Shows which offers move demand into slower windows and which customers respond to value-based timing.</p><div class="mg-example-meta"><div><span>Price</span><strong>$24</strong></div><div><span>Signal</span><strong>Demand shift</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:180ms"><small>Venue</small><h3>Two Drink Reward</h3><p>Connects pre-event intent, in-person redemption, and post-event follow-up into one measurable loop.</p><div class="mg-example-meta"><div><span>Price</span><strong>$22</strong></div><div><span>Signal</span><strong>Event lift</strong></div></div></article>
        <article class="mg-example-card" data-reveal="up" style="--delay:270ms"><small>Fitness</small><h3>Trial Class Pass</h3><p>Turns a first visit into a profile, a conversion path, and a clear follow-up opportunity.</p><div class="mg-example-meta"><div><span>Price</span><strong>$15</strong></div><div><span>Signal</span><strong>Lead value</strong></div></div></article>
      </div>
    </div>
  </section>

  <section class="mg-section" id="api-preview" aria-labelledby="apiPreviewTitle">
    <div class="mg-bg-mesh" aria-hidden="true"></div>
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Distribution API</span>
        <h2 class="mg-section-title" id="apiPreviewTitle" data-reveal="left">A data-value layer developers can wire into local commerce.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Use Microgifter to create reward actions, capture customer signals, verify redemption, and send structured value data back into CRMs, apps, loyalty workflows, dashboards, and AI-powered commerce systems.</p>
      </div>
      <div class="mg-api-story">
        <pre class="mg-code-panel" data-reveal="up"><span class="gold">POST</span> /v1/distribution/send
{
  "merchant_id": <span class="green">"m_local_123"</span>,
  "reward": <span class="green">"coffee_for_two"</span>,
  "recipient": {
    "type": <span class="green">"email"</span>,
    "value": <span class="green">"customer@example.com"</span>
  },
  "metadata": {
    "source": <span class="green">"promotional-crm"</span>
  }
}</pre>
        <article class="mg-api-flow" data-reveal="up" style="--delay:120ms">
          <h3>From support to intelligence</h3>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">01</span><p>A merchant or partner app creates a local value action: offer, reward, gift, contest, or pre-sale product.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">02</span><p>Microgifter captures source, claim, customer, campaign, and redemption context as structured data.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">03</span><p>The customer clicks, claims, shares, saves, buys, visits, or redeems.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">04</span><p>The business uses that data to improve campaigns, increase customer value, and earn more from future demand.</p></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-public-bottom-demo" aria-labelledby="mgPublicDemoTitle">
    <div class="mg-public-bottom-demo-inner">
      <span class="mg-public-bottom-demo-eyebrow">Book a demo</span>
      <h2 id="mgPublicDemoTitle">See how Microgifter turns local support into usable business data.</h2>
      <p>Walk through the full value loop: create the offer, capture the signal, verify the redemption, understand the customer, and use the insight to make the next action more valuable.</p>
      <div class="mg-public-bottom-demo-actions">
        <a class="mg-public-bottom-demo-primary" href="/learn-more.php">Book a Demo</a>
        <a class="mg-public-bottom-demo-secondary" href="/pricing.php">View Pricing</a>
        <a class="mg-public-bottom-demo-secondary" href="/developer-docs.php">Explore the API</a>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
