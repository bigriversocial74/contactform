<?php
declare(strict_types=1);

$page_title = 'Microgifter | Customer Relationship Agent for Social Gifting';
$page_section = 'public';
$header_mode = 'public';
$page_body_class = 'mg-parallax-home';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/homepage-parallax-agent-v1.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/homepage-parallax-agent-v1.js?v=1.0.0',
];
$page_meta = [
    'description' => 'Create a personal social gifting and customer service agent that connects gifting, loyalty, service, follow-up, and post-purchase commerce.',
    'canonical' => 'https://microgifter.com/index.php',
    'og_title' => 'Microgifter — Customer Relationship Agent',
    'og_description' => 'One intelligent relationship system for social gifting, customer service, loyalty, and post-purchase commerce.',
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
            ['label' => 'How It Works', 'href' => '/index.php#relationship-system'],
            ['label' => 'Solutions', 'href' => '/index.php#agent-in-action'],
            ['label' => 'Features', 'href' => '/index.php#pppm-presentation'],
            ['label' => 'For Businesses', 'href' => '/merchant-landing.php'],
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

<main class="mg-ph-main" id="top" aria-label="Microgifter customer relationship agent homepage">
  <section class="mg-ph-hero-scroll" id="hero" aria-label="Microgifter introduction">
    <div class="mg-ph-hero-sticky">
      <div class="mg-ph-scene" data-ph-scene>
        <div class="mg-ph-sky" aria-hidden="true"></div>
        <div class="mg-ph-mountains mg-ph-mountains-back" aria-hidden="true"></div>
        <div class="mg-ph-mountains mg-ph-mountains-front" aria-hidden="true"></div>
        <div class="mg-ph-foreground" aria-hidden="true"></div>
        <div class="mg-ph-orb" data-ph-orb aria-hidden="true"><span></span></div>

        <div class="mg-ph-hero-copy mg-ph-copy-one" data-ph-copy-one>
          <p class="mg-ph-eyebrow">Personal agent · active</p>
          <h1>Create your personal social gifting and customer service agent.</h1>
          <p class="mg-ph-intro">One intelligent relationship system that understands, engages, gifts, and grows with every customer interaction.</p>
          <a class="mg-ph-button" href="#relationship-system">Enter the system <span aria-hidden="true">→</span></a>
        </div>

        <div class="mg-ph-hero-copy mg-ph-copy-two" data-ph-copy-two aria-hidden="true">
          <p class="mg-ph-eyebrow">Relationship intelligence · connected</p>
          <h2>One agent that remembers the relationship.</h2>
          <p class="mg-ph-intro">Microgifter carries customer context forward—across conversations, service moments, gifting, rewards, and every next action.</p>
          <a class="mg-ph-button" href="#relationship-system">See how it works <span aria-hidden="true">↓</span></a>
        </div>

        <section class="mg-ph-growth" data-ph-growth aria-hidden="true" aria-labelledby="mgPhGrowthTitle">
          <div class="mg-ph-growth-copy">
            <p class="mg-ph-eyebrow">Relationship growth · live</p>
            <h2 id="mgPhGrowthTitle">See every relationship create measurable momentum.</h2>
            <p class="mg-ph-intro">Five signals move together as your agent learns, responds, gifts, retains, and converts.</p>
          </div>
          <div class="mg-ph-chart" role="img" aria-label="Animated sales growth chart showing five rising relationship signals">
            <div class="mg-ph-chart-head"><span>Sales growth</span><strong>+38.4%</strong></div>
            <svg viewBox="0 0 900 420" preserveAspectRatio="none" aria-hidden="true">
              <defs><linearGradient id="mgPhChartFade" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f1b99d" stop-opacity=".2"/><stop offset="100%" stop-color="#f1b99d" stop-opacity="0"/></linearGradient></defs>
              <g class="mg-ph-grid"><line x1="40" y1="80" x2="860" y2="80"/><line x1="40" y1="155" x2="860" y2="155"/><line x1="40" y1="230" x2="860" y2="230"/><line x1="40" y1="305" x2="860" y2="305"/><line x1="40" y1="380" x2="860" y2="380"/></g>
              <path class="mg-ph-chart-area" d="M40 350 C120 334 145 320 205 301 S315 270 360 250 S455 224 510 190 S620 160 680 118 S790 88 860 52 L860 380 L40 380 Z"/>
              <path class="mg-ph-line mg-ph-line-1" pathLength="1" d="M40 350 C120 334 145 320 205 301 S315 270 360 250 S455 224 510 190 S620 160 680 118 S790 88 860 52"/>
              <path class="mg-ph-line mg-ph-line-2" pathLength="1" d="M40 366 C115 350 160 345 215 312 S325 302 380 259 S475 238 530 210 S635 190 700 145 S805 121 860 87"/>
              <path class="mg-ph-line mg-ph-line-3" pathLength="1" d="M40 335 C105 327 155 285 220 291 S320 255 385 240 S485 199 545 205 S655 148 715 132 S805 93 860 76"/>
              <path class="mg-ph-line mg-ph-line-4" pathLength="1" d="M40 375 C115 360 175 335 230 342 S340 302 400 290 S505 254 565 221 S670 215 735 160 S820 140 860 118"/>
              <path class="mg-ph-line mg-ph-line-5" pathLength="1" d="M40 355 C105 340 170 326 225 306 S335 282 395 246 S500 226 560 181 S675 166 735 111 S820 92 860 64"/>
            </svg>
            <div class="mg-ph-chart-legend" aria-hidden="true"><span>Sales</span><span>Retention</span><span>Gifting</span><span>Engagement</span><span>Referrals</span></div>
          </div>
        </section>

        <div class="mg-ph-phase" aria-hidden="true"><span class="is-active"></span><span></span><span></span></div>
        <div class="mg-ph-scroll-note" aria-hidden="true"><span></span> Scroll to activate</div>
      </div>
    </div>
  </section>

  <section class="mg-ph-story" id="relationship-system" aria-labelledby="mgPhStoryTitle">
    <div class="mg-ph-ambient" aria-hidden="true"></div>
    <div class="mg-ph-container">
      <div class="mg-ph-section-copy mg-ph-reveal">
        <p class="mg-ph-eyebrow">The relationship system</p>
        <h2 id="mgPhStoryTitle">Turn every interaction into a lasting relationship.</h2>
        <p>Microgifter follows the customer journey across conversations, service, gifting, and loyalty—then helps your business take the next thoughtful action.</p>
      </div>
      <div class="mg-ph-steps" aria-label="Relationship system stages">
        <article class="mg-ph-reveal"><span>01</span><h3>Understand</h3><p>Learn preferences, intent, context, and the history behind every relationship.</p></article>
        <article class="mg-ph-reveal"><span>02</span><h3>Engage</h3><p>Begin relevant conversations at the right moment and through the right channel.</p></article>
        <article class="mg-ph-reveal"><span>03</span><h3>Gift</h3><p>Create personal gifting moments that feel human, useful, and memorable.</p></article>
        <article class="mg-ph-reveal"><span>04</span><h3>Grow</h3><p>Strengthen loyalty and convert better relationships into recurring value.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-ph-agent" id="agent-in-action" aria-labelledby="mgPhAgentTitle">
    <div class="mg-ph-container mg-ph-agent-grid">
      <div class="mg-ph-section-copy mg-ph-reveal">
        <p class="mg-ph-eyebrow">The agent in action</p>
        <h2 id="mgPhAgentTitle">One relationship. A thousand thoughtful next steps.</h2>
        <p>Microgifter listens across the customer journey, carries context forward, and turns each signal into a useful action—without making the relationship feel automated.</p>
        <a class="mg-ph-button" href="#agent-workflow">Explore the workflow <span aria-hidden="true">↓</span></a>
      </div>

      <div class="mg-ph-console mg-ph-reveal" id="agent-workflow">
        <div class="mg-ph-console-bar"><span><i></i> Relationship agent online</span><span>Customer memory · live</span></div>
        <div class="mg-ph-customer">
          <div class="mg-ph-avatar" aria-hidden="true">AM</div>
          <div><p>Active relationship</p><h3>Alex Morgan</h3><span>12 interactions · 3 gifts · loyalty member</span></div>
          <div class="mg-ph-score"><strong>86</strong><span>relationship score</span></div>
        </div>
        <div class="mg-ph-stream" aria-label="Customer relationship workflow">
          <article><span>01</span><div><p>Signal recognized</p><h4>Birthday mentioned in conversation</h4><small>Intent, timing, and relationship context captured.</small></div></article>
          <i aria-hidden="true"></i>
          <article><span>02</span><div><p>Agent decides</p><h4>Recommend a personal local gift</h4><small>Matched to preference, budget, and merchant availability.</small></div></article>
          <i aria-hidden="true"></i>
          <article class="is-active"><span>03</span><div><p>Next action ready</p><h4>Schedule gift and thoughtful follow-up</h4><small>The relationship continues after purchase and delivery.</small></div></article>
        </div>
        <div class="mg-ph-console-foot"><span>Context retained across service, gifting, loyalty, and redemption.</span><strong>Agent confidence 94%</strong></div>
      </div>
    </div>
  </section>

  <section class="mg-ph-how" id="mountain-zoom" aria-labelledby="mgPhHowTitle">
    <div class="mg-ph-how-landscape" aria-hidden="true"><div></div><div></div><div></div></div>
    <div class="mg-ph-container">
      <div class="mg-ph-section-copy mg-ph-reveal">
        <p class="mg-ph-eyebrow">How Microgifter works</p>
        <h2 id="mgPhHowTitle">Relationship context moves forward instead of starting over.</h2>
        <p>Customer signals become connected actions across one continuous relationship system.</p>
      </div>
      <div class="mg-ph-how-grid">
        <article class="mg-ph-reveal"><span>01</span><h3>Connect</h3><p>Bring together customer identity, merchant activity, gifting, rewards, and conversations.</p></article>
        <article class="mg-ph-reveal"><span>02</span><h3>Remember</h3><p>Carry preferences, timing, ownership, and transaction history into every next interaction.</p></article>
        <article class="mg-ph-reveal"><span>03</span><h3>Act</h3><p>Recommend the next useful gift, message, reward, campaign, or service response.</p></article>
        <article class="mg-ph-reveal"><span>04</span><h3>Measure</h3><p>Track claims, redemption, retention, referrals, and relationship growth.</p></article>
      </div>
    </div>
  </section>

  <section class="mg-ph-pppm" id="pppm-presentation" aria-labelledby="mgPhPppmTitle">
    <div class="mg-ph-container">
      <header class="mg-ph-section-copy mg-ph-reveal">
        <p class="mg-ph-eyebrow">Post-purchase product management</p>
        <h2 id="mgPhPppmTitle">One continuous lifecycle from discovery through redemption.</h2>
        <p>Microgifter keeps products, gifts, customers, conversations, and merchant actions connected after checkout.</p>
      </header>

      <div class="mg-ph-timeline">
        <article class="mg-ph-feature mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-phone"><span></span><div class="mg-ph-device-head">Local gifting</div><div class="mg-ph-product-card"><i></i><b>Birthday experience</b><small>Local · ready to send</small></div><button type="button" tabindex="-1">Send gift</button></div></div>
          <div class="mg-ph-feature-copy"><span>01</span><p>Social gifting</p><h3>Make local the easy gift choice.</h3><small>Help customers discover, purchase, send, and support local products, services, experiences, and creative work.</small><a href="/discover.php">Explore local gifts →</a></div>
        </article>

        <article class="mg-ph-feature is-reverse mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-desktop"><div class="mg-ph-device-head">Merchant CRM</div><div class="mg-ph-metric-row"><i></i><i></i><i></i></div><div class="mg-ph-data-list"><span></span><span></span><span></span><span></span></div></div></div>
          <div class="mg-ph-feature-copy"><span>02</span><p>Merchant CRM</p><h3>Every action becomes customer memory.</h3><small>Connect purchases, claims, visits, messages, referrals, and reward activity to usable customer records.</small><a href="/learn-more.php">See the CRM →</a></div>
        </article>

        <article class="mg-ph-feature mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-desktop"><div class="mg-ph-device-head">Campaigns & offers</div><div class="mg-ph-bars"><i></i><i></i><i></i><i></i><i></i></div><div class="mg-ph-campaign-list"><span></span><span></span><span></span></div></div></div>
          <div class="mg-ph-feature-copy"><span>03</span><p>Campaigns & offers</p><h3>Launch measurable local growth.</h3><small>Launch offers, rewards, contests, referrals, QR campaigns, and local promotions with measurable outcomes.</small><a href="/learn-more.php">Build a campaign →</a></div>
        </article>

        <article class="mg-ph-feature is-reverse mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-desktop"><div class="mg-ph-device-head">Customer messaging</div><div class="mg-ph-chat"><span>Gift claimed—follow up?</span><span>Send a thank-you.</span><span>Draft ready with history attached.</span></div></div></div>
          <div class="mg-ph-feature-copy"><span>04</span><p>Customer messaging</p><h3>Keep communication tied to the relationship.</h3><small>Follow up after a gift, reward, claim, or visit without separating communication from transaction history.</small><a href="/learn-more.php">Connect conversations →</a></div>
        </article>

        <article class="mg-ph-feature mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-desktop"><div class="mg-ph-device-head">Claim & redemption</div><div class="mg-ph-status-row"><span>Inbox</span><span>Sent</span><span>Claimed</span><span>Redeemed</span></div><div class="mg-ph-data-list"><span></span><span></span><span></span></div></div></div>
          <div class="mg-ph-feature-copy"><span>05</span><p>Claim & redemption</p><h3>Follow every Microgift through its lifecycle.</h3><small>Track every Microgift from purchase through Inbox, Sent, Claimed, and merchant-verified redemption states.</small><a href="/learn-more.php">Follow the lifecycle →</a></div>
        </article>

        <article class="mg-ph-feature is-reverse mg-ph-reveal">
          <div class="mg-ph-device"><div class="mg-ph-desktop"><div class="mg-ph-device-head">Automated commerce</div><div class="mg-ph-flow"><span>Trigger</span><i></i><span>Recommend</span><i></i><span>Send</span><i></i><span>Measure</span></div><div class="mg-ph-metric-row"><i></i><i></i><i></i></div></div></div>
          <div class="mg-ph-feature-copy"><span>06</span><p>Automated commerce</p><h3>Create ongoing demand automatically.</h3><small>Use recurring programs, agent-assisted gifting, workplace rewards, and campaign automation to create ongoing demand.</small><a href="/learn-more.php">Automate demand →</a></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-ph-final" id="get-started" aria-labelledby="mgPhFinalTitle">
    <div class="mg-ph-final-landscape" aria-hidden="true"></div>
    <div class="mg-ph-orb mg-ph-final-orb" aria-hidden="true"><span></span></div>
    <div class="mg-ph-container">
      <div class="mg-ph-final-copy mg-ph-reveal">
        <p class="mg-ph-eyebrow">Your relationship system starts here</p>
        <h2 id="mgPhFinalTitle">Build stronger customer relationships with one intelligent agent.</h2>
        <p>Connect gifting, service, follow-up, loyalty, and post-purchase activity in one continuous customer relationship system.</p>
        <div class="mg-ph-tags"><span>Social gifting</span><span>Customer service</span><span>Loyalty automation</span><span>Post-purchase management</span></div>
      </div>

      <section class="mg-ph-pricing" aria-labelledby="mgPhPricingTitle">
        <div class="mg-ph-section-copy mg-ph-reveal">
          <p class="mg-ph-eyebrow">Simple plans for relationship growth</p>
          <h2 id="mgPhPricingTitle">Choose the system that fits your business.</h2>
          <p>Start with the essentials, then add automation and intelligence as your customer relationships grow.</p>
        </div>
        <div class="mg-ph-pricing-grid">
          <article class="mg-ph-reveal"><p>Starter</p><h3>$25<span>/month</span></h3><small>For independent businesses organizing gifting, customer activity, and follow-up.</small><ul><li>Customer relationship profiles</li><li>Social gifting tools</li><li>Basic loyalty automation</li></ul><a href="/signup.php">Create Account →</a></article>
          <article class="is-featured mg-ph-reveal"><p>Growth</p><h3>$79<span>/month</span></h3><small>For growing merchants that want an active customer relationship agent.</small><ul><li>Everything in Starter</li><li>Agentic recommendations</li><li>Campaign automation</li></ul><a href="/signup.php">Start Growing →</a></article>
          <article class="mg-ph-reveal"><p>Professional</p><h3>$149<span>/month</span></h3><small>For teams coordinating campaigns, service, loyalty, and post-purchase activity.</small><ul><li>Advanced workflows</li><li>Team permissions</li><li>Lifecycle reporting</li></ul><a href="/signup.php">Choose Professional →</a></article>
          <article class="mg-ph-reveal"><p>Enterprise</p><h3>Custom</h3><small>For organizations managing locations, group gifting, and customer programs.</small><ul><li>Multi-location management</li><li>Enterprise gifting</li><li>Custom integrations</li></ul><a href="/learn-more.php">Book Demo ↗</a></article>
        </div>
      </section>
    </div>
  </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
