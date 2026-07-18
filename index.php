<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/pricing-cards.php';

$page_title = 'Microgifter — Customer Relationship Agent';
$page_section = 'public';
$header_mode = 'public';
$page_body_class = 'mg-homepage-exact-v2';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/public-dark-shell.css',
    '/assets/css/public-header-cleanup.css',
    '/assets/css/homepage-parallax-exact-v2.css?v=2.1.0',
    '/assets/css/pricing-local-business-v1.css?v=1.2.0',
];
$page_scripts = [
    '/assets/js/homepage-parallax-exact-v2.js?v=2.0.0',
];
$page_meta = [
    'description' => 'Microgifter personal social gifting and customer service agent.',
    'canonical' => 'https://microgifter.com/index.php',
    'og_title' => 'Microgifter — Customer Relationship Agent',
    'og_description' => 'One intelligent relationship system for social gifting, customer service, loyalty, and post-purchase commerce.',
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
<div class="homepage-exact-v2" data-homepage-exact-v2>
    <section class="hero-scroll" id="hero" aria-label="Microgifter introduction">
      <div class="hero-sticky">
        <div class="scene" id="scene">
          <img class="layer layer-mountains" id="mountains" src="/assets/images/mountains.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
          <img class="layer layer-foreground" id="foreground" src="/assets/images/foreground.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
          <img class="orb" id="orb" src="/assets/images/orb.png?v=2.0.0" alt="Glowing intelligent agent sphere" decoding="async" fetchpriority="high">

          <div class="hero-copy copy-one" id="heroCopy">
            <p class="eyebrow">Personal agent · active</p>
            <h1>The Future of Gifting Has Arrived.</h1>
            <p class="intro">One intelligent relationship system that understands, engages, gifts, and grows with every customer interaction.</p>
            <a class="primary-button" href="#relationship-system">Enter the system <span>→</span></a>
          </div>

          <div class="hero-copy copy-two" id="secondCopy" aria-hidden="true">
            <p class="eyebrow">Relationship intelligence · connected</p>
            <h2>One agent that remembers the relationship.</h2>
            <p class="intro">Microgifter carries customer context forward—across conversations, service moments, gifting, rewards, and every next action.</p>
            <a class="primary-button" href="#relationship-system">See how it works <span>↓</span></a>
          </div>

          <section class="growth-stage" id="growthStage" aria-hidden="true">
            <div class="growth-copy">
              <p class="eyebrow">Relationship growth · live</p>
              <h2>See every relationship create measurable momentum.</h2>
              <p class="intro">Five signals move together as your agent learns, responds, gifts, retains, and converts.</p>
            </div>
            <div class="growth-chart" role="img" aria-label="Animated sales growth chart showing five rising relationship signals">
              <div class="chart-header">
                <span>Sales growth</span>
                <strong>+38.4%</strong>
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
                <span>Sales</span><span>Retention</span><span>Gifting</span><span>Engagement</span><span>Referrals</span>
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

    <section class="story story-scroll" id="relationship-system">
      <div class="story-sticky">
        <div class="story-ambient" aria-hidden="true"></div>
        <div class="story-inner">
          <div class="story-copy">
            <p class="eyebrow">The relationship system</p>
            <h2>Turn every interaction into a lasting relationship.</h2>
            <p class="story-lede">Microgifter follows the customer journey across conversations, service, gifting, and loyalty—then helps your business take the next thoughtful action.</p>
          </div>
          <div class="steps" aria-label="Relationship system stages">
            <article><span>01</span><h3>Understand</h3><p>Learn preferences, intent, context, and the history behind every relationship.</p></article>
            <article><span>02</span><h3>Engage</h3><p>Begin relevant conversations at the right moment and through the right channel.</p></article>
            <article><span>03</span><h3>Gift</h3><p>Create personal gifting moments that feel human, useful, and memorable.</p></article>
            <article><span>04</span><h3>Grow</h3><p>Strengthen loyalty and convert better relationships into recurring value.</p></article>
          </div>
          <div class="story-progress" aria-hidden="true"><span></span></div>
        </div>
      </div>
    </section>

    <section class="agent-section agent-scroll" id="agent-in-action" aria-labelledby="agent-section-title">
      <div class="agent-pin">
        <div class="agent-section__ambient" aria-hidden="true"></div>
        <div class="agent-section__inner">
        <div class="agent-section__intro reveal-on-scroll">
          <p class="eyebrow">The agent in action</p>
          <h2 id="agent-section-title">One relationship. A thousand thoughtful next steps.</h2>
          <p>Microgifter listens across the customer journey, carries context forward, and turns each signal into a useful action—without making the relationship feel automated.</p>
          <a class="primary-button" href="#agent-workflow">Explore the workflow <span>↓</span></a>
        </div>

        <div class="agent-console reveal-on-scroll" id="agent-workflow">
          <div class="agent-console__bar">
            <div><span class="agent-status-dot"></span><span>Relationship agent online</span></div>
            <span>Customer memory · live</span>
          </div>

          <div class="agent-console__customer">
            <div class="customer-avatar" aria-hidden="true">AM</div>
            <div><p>Active relationship</p><h3>Alex Morgan</h3><span>12 interactions · 3 gifts · loyalty member</span></div>
            <div class="relationship-score"><strong>86</strong><span>relationship score</span></div>
          </div>

          <div class="agent-console__stream" aria-label="Customer relationship workflow">
            <article class="signal-card"><span class="signal-card__number">01</span><div><p>Signal recognized</p><h4>Birthday mentioned in conversation</h4><span>Intent, timing, and relationship context captured.</span></div></article>
            <div class="workflow-connector" aria-hidden="true"><span></span></div>
            <article class="signal-card"><span class="signal-card__number">02</span><div><p>Agent decides</p><h4>Recommend a personal local gift</h4><span>Matched to preference, budget, and merchant availability.</span></div></article>
            <div class="workflow-connector" aria-hidden="true"><span></span></div>
            <article class="signal-card signal-card--active"><span class="signal-card__number">03</span><div><p>Thoughtful action</p><h4>Send, follow up, and remember</h4><span>The relationship history updates for the next moment.</span></div></article>
          </div>

          <div class="agent-console__footer">
            <span>Next best action</span>
            <strong>Send a birthday gift recommendation tomorrow at 9:00 AM.</strong>
            <button type="button">Approve <span>→</span></button>
          </div>
        </div>
        </div>
      </div>
    </section>

    <section class="mountain-zoom-section" id="mountain-zoom" aria-labelledby="mountain-zoom-title">
      <div class="mountain-zoom-pin">
        <img class="mountain-zoom-bg" src="/assets/images/mountains.png?v=2.0.0" alt="" decoding="async">
        <img class="mountain-zoom-fg" src="/assets/images/foreground.png?v=2.0.0" alt="" decoding="async">
        <div class="mountain-zoom-copy">
          <p class="eyebrow">Built to keep growing</p>
          <h2 id="mountain-zoom-title">Relationships become the landscape of your business.</h2>
          <p>As the agent learns from every interaction, customer context compounds into stronger service, smarter gifting, and measurable long-term growth.</p>
        </div>
        <div class="how-presentation" id="how-it-works-presentation" aria-labelledby="how-title">
          <div class="how-presentation__intro">
            <p class="eyebrow">How it works</p>
            <h2 id="how-title">One continuous relationship loop.</h2>
            <p>Microgifter turns scattered customer moments into a simple, intelligent sequence that keeps learning as the relationship grows.</p>
          </div>
          <div class="how-presentation__flow" aria-label="How Microgifter works">
            <article class="how-step"><span class="how-step__number">01</span><div class="how-step__icon" aria-hidden="true">◎</div><h3>Listen</h3><p>Capture signals from conversations, purchases, service requests, and gifting activity.</p></article>
            <div class="how-connector" aria-hidden="true"><span></span></div>
            <article class="how-step"><span class="how-step__number">02</span><div class="how-step__icon" aria-hidden="true">◇</div><h3>Understand</h3><p>Build customer memory around intent, timing, preference, and relationship context.</p></article>
            <div class="how-connector" aria-hidden="true"><span></span></div>
            <article class="how-step"><span class="how-step__number">03</span><div class="how-step__icon" aria-hidden="true">→</div><h3>Act</h3><p>Recommend the next best message, gift, follow-up, reward, or service action.</p></article>
            <div class="how-connector" aria-hidden="true"><span></span></div>
            <article class="how-step"><span class="how-step__number">04</span><div class="how-step__icon" aria-hidden="true">↗</div><h3>Grow</h3><p>Measure the response, strengthen loyalty, and improve every future interaction.</p></article>
          </div>
        </div>
        <div class="mountain-zoom-progress" aria-hidden="true"><span></span></div>
      </div>
    </section>

    <section class="pppm-presentation" id="pppm-presentation" aria-labelledby="pppm-title">
      <div class="pppm-shell">
        <header class="pppm-intro">
          <p class="eyebrow">Platform features</p>
          <h2 id="pppm-title">Everything works inside one connected relationship system.</h2>
          <p>Social gifting, CRM, campaigns, messaging, claims, and automation all connect to the same customer record—so every action becomes more useful over time.</p>
        </header>

        <div class="pppm-timeline" aria-label="Microgifter platform features timeline">
          <div class="pppm-spine" aria-hidden="true"><span></span></div>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-gifting">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Social gifting</b></div>
                    <div class="feature-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main">
                        <div class="feature-header"><div><small>Send now, enjoy later</small><h4>Gifts and experiences</h4></div><button class="desktop-pill">Create gift</button></div>
                        <div class="gift-grid"><article class="gift-card"><strong>Coffee for two</strong><span>$18 · Send later</span></article><article class="gift-card"><strong>Massage voucher</strong><span>$90 · Schedule delivery</span></article><article class="gift-card"><strong>Dinner credit</strong><span>$65 · Add a note</span></article></div>
                        <div class="feature-banner"><b>Group gifting</b><span>Friends, family, coworkers, and community programs in one flow.</span></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>01</span></div>
              <div class="pppm-card"><p>Social Gifting</p><h3>Sell now. Send later.</h3><span>Sell products and experiences that customers can purchase now and send later to friends, family, coworkers, and communities.</span><a class="pppm-link" href="#hero">Explore local gifts <span>→</span></a></div>
            </div>
          </article>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-crm-feature">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Merchant CRM</b></div>
                    <div class="feature-shell crm-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main crm-main">
                        <div class="crm-top"><div class="crm-profile"><em>AJ</em><div><strong>Alex Johnson</strong><span>6 visits · 3 claims · loyalty active</span></div></div><div class="crm-score"><b>92</b><span>relationship score</span></div></div>
                        <div class="crm-grid"><div class="crm-panel"><small>Purchases</small><strong>18</strong></div><div class="crm-panel"><small>Messages</small><strong>34</strong></div><div class="crm-panel"><small>Referrals</small><strong>7</strong></div><div class="crm-panel"><small>Reward events</small><strong>12</strong></div></div>
                        <div class="crm-timeline"><span></span><span></span><span></span><span></span></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>02</span></div>
              <div class="pppm-card"><p>Merchant CRM</p><h3>Every action becomes customer memory.</h3><span>Connect purchases, claims, visits, messages, referrals, and reward activity to usable customer records.</span><a class="pppm-link" href="#hero">See the CRM <span>→</span></a></div>
            </div>
          </article>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-campaigns-feature">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Campaigns &amp; offers</b></div>
                    <div class="feature-shell campaign-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main campaign-main">
                        <div class="campaign-metrics"><div><small>Active campaigns</small><strong>12</strong></div><div><small>QR claims</small><strong>1,842</strong></div><div><small>Conversions</small><strong>42.3%</strong></div></div>
                        <div class="campaign-chart"><span></span><span></span><span></span><span></span></div>
                        <div class="campaign-list"><article><b>Spring gifting rewards</b><span>Offers · Referrals · QR</span></article><article><b>Weekend comeback contest</b><span>Leaderboard · Prizes</span></article><article><b>Office lunch appreciation</b><span>Workplace rewards</span></article></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>03</span></div>
              <div class="pppm-card"><p>Campaigns &amp; Offers</p><h3>Launch measurable local growth.</h3><span>Launch offers, rewards, contests, referrals, QR campaigns, and local promotions with measurable outcomes.</span><a class="pppm-link" href="#hero">Build a campaign <span>→</span></a></div>
            </div>
          </article>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-messaging-feature">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Customer messaging</b></div>
                    <div class="feature-shell messaging-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main messaging-main">
                        <div class="message-thread"><div class="bubble bubble--left">Your gift was claimed yesterday—want to follow up?</div><div class="bubble bubble--right">Yes, send a thank-you and invite them back.</div><div class="bubble bubble--left">Draft ready with visit history and reward status attached.</div></div>
                        <div class="message-actions"><span>Gift history</span><span>Reward status</span><span>Claim record</span><button class="desktop-pill">Send follow-up</button></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>04</span></div>
              <div class="pppm-card"><p>Customer Messaging</p><h3>Keep communication tied to the relationship.</h3><span>Follow up after a gift, reward, claim, or visit without separating customer communication from the transaction history.</span><a class="pppm-link" href="#hero">Connect conversations <span>→</span></a></div>
            </div>
          </article>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-redemption-feature">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Claim &amp; redemption</b></div>
                    <div class="feature-shell redemption-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main redemption-main">
                        <div class="claim-stage-row"><span class="active">Inbox</span><span class="active">Sent</span><span class="active">Claimed</span><span>Redeemed</span></div>
                        <div class="claim-table"><div class="claim-head"><b>Gift</b><b>Status</b><b>Merchant</b><b>Verification</b></div><div class="claim-row"><span>MG-2041</span><span>Claimed</span><span>North Side Spa</span><span>QR verified</span></div><div class="claim-row"><span>MG-1988</span><span>Sent</span><span>Riverfront Café</span><span>Pending</span></div><div class="claim-row"><span>MG-1915</span><span>Redeemed</span><span>Studio Nine</span><span>Confirmed</span></div></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>05</span></div>
              <div class="pppm-card"><p>Claim &amp; Redemption</p><h3>Follow every Microgift through its lifecycle.</h3><span>Track every Microgift from purchase through inbox, sent, claimed, and merchant-verified redemption states.</span><a class="pppm-link" href="#hero">Follow the lifecycle <span>→</span></a></div>
            </div>
          </article>

          <article class="pppm-event" data-device="desktop">
            <div class="pppm-event__sticky">
              <div class="pppm-visual" aria-hidden="true">
                <div class="device-desktop device-desktop--feature">
                  <div class="device-desktop__screen screen-automation-feature">
                    <div class="desktop-toolbar"><span></span><span></span><span></span><b>Automated commerce</b></div>
                    <div class="feature-shell automation-shell">
                      <aside class="feature-sidebar"><i></i><i></i><i></i><i></i><i></i></aside>
                      <main class="feature-main automation-main">
                        <div class="automation-stats"><div><small>Recurring programs</small><strong>24</strong></div><div><small>Agent assists</small><strong>1,206</strong></div><div><small>Repeat revenue</small><strong>31%</strong></div></div>
                        <div class="automation-flow"><span>Trigger</span><i></i><span>Recommend</span><i></i><span>Send</span><i></i><span>Measure</span></div>
                        <div class="automation-cards"><article>Workplace rewards</article><article>Recurring gifting</article><article>Campaign automation</article></div>
                      </main>
                    </div>
                  </div>
                  <div class="device-desktop__stand"></div>
                </div>
              </div>
              <div class="pppm-marker"><span>06</span></div>
              <div class="pppm-card"><p>Automated Commerce</p><h3>Create ongoing demand automatically.</h3><span>Use recurring programs, agent-assisted gifting, workplace rewards, and campaign automation to create ongoing demand.</span><a class="pppm-link" href="#hero">Automate demand <span>→</span></a></div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="final-cta" id="get-started" aria-labelledby="final-cta-title">
      <div class="final-cta__stage">
        <div class="final-cta__landscape" aria-hidden="true">
          <img class="final-cta__mountains" src="/assets/images/mountains.png?v=2.0.0" alt="">
          <img class="final-cta__foreground" src="/assets/images/foreground.png?v=2.0.0" alt="">
        </div>
        <img class="final-cta__orb" src="/assets/images/orb.png?v=2.0.0" alt="" aria-hidden="true">
        <div class="final-cta__inner">
          <p class="eyebrow">Your relationship system starts here</p>
          <h2 id="final-cta-title">Build stronger customer relationships with one intelligent agent.</h2>
          <p>Connect gifting, service, follow-up, loyalty, and post-purchase activity in one continuous customer relationship system.</p>
          <div class="final-cta__meta" aria-label="Microgifter platform benefits">
            <span>Social gifting</span><span>Customer service</span><span>Loyalty automation</span><span>Post-purchase management</span>
          </div>
        </div>

        <section class="pricing-reveal mg-pricing-v1" aria-labelledby="pricing-title">
          <div class="pricing-reveal__intro">
            <p class="eyebrow">Simple plans for relationship growth</p>
            <h2 id="pricing-title">Choose the system that fits your business.</h2>
            <p>Start with the essentials, then add automation and intelligence as your customer relationships grow.</p>
          </div>
          <?php mg_render_public_pricing_cards(['grid_class' => 'mg-price-grid homepage-public-pricing-grid']); ?>
        </section>
      </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
