<?php
declare(strict_types=1);

$page_title = 'Microgifter Investor Pitch Deck';
$page_section = 'investors';
$header_mode = 'public';
$page_body_class = 'mg-pitch-deck-page';
$page_styles = [
    '/assets/css/public-header-footer-fixes.css',
    '/assets/css/public-dark-shell.css',
    '/assets/css/public-header-cleanup.css',
    '/assets/css/pitch-deck-scroll-v1.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/pitch-deck-scroll-v1.js?v=1.0.0',
];
$page_meta = [
    'description' => 'Microgifter investor pitch deck: the connected social gifting, merchant CRM, campaign, loyalty, and automated commerce platform built to make local the easy choice.',
    'canonical' => 'https://microgifter.com/pitch-deck.php',
    'og_title' => 'Microgifter Investor Pitch Deck',
    'og_description' => 'The future of gifting starts local. Explore the Microgifter opportunity, platform, business model, market path, and vision.',
];
$page_manifest = [
    'id' => 'pitch-deck',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'body_class' => $page_body_class,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'Explore', 'href' => '/discover.php'],
            ['label' => 'Market Model', 'href' => '/investors.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => [
        'enabled' => false,
        'page' => 'pitch-deck',
        'sections' => [],
    ],
];

header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
require __DIR__ . '/includes/header.php';
?>
<div class="pitch-deck" data-pitch-deck data-slide-count="10">
  <section class="pitch-scroll" aria-label="Microgifter investor pitch deck">
    <div class="pitch-sticky">
      <div class="pitch-scene" data-pitch-scene>
        <div class="pitch-landscape" aria-hidden="true">
          <img class="pitch-landscape__mountains" src="/assets/images/mountains.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
          <img class="pitch-landscape__foreground" src="/assets/images/foreground.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
          <img class="pitch-landscape__orb" src="/assets/images/orb.png?v=2.0.0" alt="" decoding="async" fetchpriority="high">
          <div class="pitch-landscape__glow"></div>
          <div class="pitch-landscape__grid"></div>
        </div>

        <div class="pitch-chrome" aria-hidden="true">
          <span class="pitch-chrome__brand">Microgifter</span>
          <span class="pitch-chrome__label">Investor presentation</span>
          <span class="pitch-chrome__counter"><b data-pitch-current>01</b><i></i><span>10</span></span>
        </div>

        <div class="pitch-slides" aria-live="polite">
          <article class="pitch-slide pitch-slide--cover is-active" id="pitch-slide-1" data-pitch-slide data-slide-label="Vision">
            <div class="pitch-slide__inner pitch-slide__inner--split">
              <div class="pitch-copy pitch-copy--hero">
                <p class="pitch-eyebrow">Investor presentation · 2026</p>
                <h1>The Future of Gifting <em>Starts Local.</em></h1>
                <p class="pitch-lede">Microgifter connects customer intent with independent businesses through social gifting, AI-assisted shopping, merchant CRM, and automated commerce.</p>
                <div class="pitch-actions">
                  <button type="button" class="pitch-button" data-pitch-next>Begin the story <span>↓</span></button>
                  <a class="pitch-text-link" href="/investors.php">View market model <span>→</span></a>
                </div>
                <div class="pitch-founder"><span>Presented by</span><strong>David Evans</strong><small>Founder, Microgifter</small></div>
              </div>
              <div class="pitch-cover-visual" aria-label="Connected local commerce visualization">
                <div class="pitch-cover-ring pitch-cover-ring--one"></div>
                <div class="pitch-cover-ring pitch-cover-ring--two"></div>
                <div class="pitch-cover-ring pitch-cover-ring--three"></div>
                <div class="pitch-cover-core"><span>Local</span><strong>Commerce</strong><small>Connected</small></div>
                <span class="pitch-cover-signal pitch-cover-signal--one">Gift</span>
                <span class="pitch-cover-signal pitch-cover-signal--two">Claim</span>
                <span class="pitch-cover-signal pitch-cover-signal--three">Visit</span>
                <span class="pitch-cover-signal pitch-cover-signal--four">Reward</span>
                <span class="pitch-cover-signal pitch-cover-signal--five">Return</span>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--problem" id="pitch-slide-2" data-pitch-slide data-slide-label="Problem">
            <div class="pitch-slide__inner">
              <header class="pitch-copy pitch-copy--wide">
                <p class="pitch-eyebrow">01 · The problem</p>
                <h2>Local Commerce Is Valuable.<br><em>The Experience Is Fragmented.</em></h2>
                <p class="pitch-lede">Customers want better local gift choices. Independent businesses want easier digital commerce. Today, the experience is split across disconnected tools, national marketplaces, and traditional gift certificates.</p>
              </header>
              <div class="pitch-problem-grid">
                <article data-pitch-reveal><span class="pitch-card-number">01</span><h3>Customers default to national brands.</h3><p>Local products, services, experiences, and creative work are harder to discover, purchase, and send as gifts.</p><div class="pitch-card-line"></div></article>
                <article data-pitch-reveal><span class="pitch-card-number">02</span><h3>Merchants inherit more complexity.</h3><p>Small and mid-sized businesses do not want more hardware, disconnected software, or technical maintenance.</p><div class="pitch-card-line"></div></article>
                <article data-pitch-reveal><span class="pitch-card-number">03</span><h3>Customer activity loses its value.</h3><p>Purchases, claims, visits, referrals, rewards, and messages rarely become one usable customer relationship.</p><div class="pitch-card-line"></div></article>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--solution" id="pitch-slide-3" data-pitch-slide data-slide-label="Solution">
            <div class="pitch-slide__inner pitch-slide__inner--split pitch-slide__inner--reverse">
              <div class="pitch-system-map" aria-label="Microgifter connected commerce system">
                <svg viewBox="0 0 700 600" role="img" aria-label="Customer intent flows through gifting, claims, visits, CRM, and repeat commerce">
                  <defs>
                    <linearGradient id="pitchFlow" x1="0" x2="1"><stop offset="0" stop-color="#111820"/><stop offset="1" stop-color="#e9976e"/></linearGradient>
                    <filter id="pitchSoft"><feGaussianBlur stdDeviation="8"/></filter>
                  </defs>
                  <circle cx="350" cy="300" r="215" class="pitch-map-ring pitch-map-ring--outer"/>
                  <circle cx="350" cy="300" r="145" class="pitch-map-ring"/>
                  <path class="pitch-map-path" pathLength="1" d="M350 72 C530 80 620 190 588 330 C558 470 430 548 285 514 C138 480 77 347 122 208 C160 92 252 64 350 72Z"/>
                  <circle cx="350" cy="300" r="82" class="pitch-map-core-glow" filter="url(#pitchSoft)"/>
                </svg>
                <div class="pitch-map-core"><span>Microgifter</span><strong>Connected Customer Growth</strong></div>
                <div class="pitch-map-node pitch-map-node--one"><b>Intent</b><small>Discover local</small></div>
                <div class="pitch-map-node pitch-map-node--two"><b>Gift</b><small>Purchase now</small></div>
                <div class="pitch-map-node pitch-map-node--three"><b>Claim</b><small>Receive securely</small></div>
                <div class="pitch-map-node pitch-map-node--four"><b>Visit</b><small>Redeem locally</small></div>
                <div class="pitch-map-node pitch-map-node--five"><b>CRM</b><small>Remember activity</small></div>
                <div class="pitch-map-node pitch-map-node--six"><b>Return</b><small>Reward and repeat</small></div>
              </div>
              <div class="pitch-copy">
                <p class="pitch-eyebrow">02 · The solution</p>
                <h2>One Connected System From <em>Intent to Loyalty.</em></h2>
                <p class="pitch-lede">Microgifter brings social gifting, pre-sale commerce, claims, merchant CRM, campaigns, rewards, messaging, and automation into one customer-growth platform.</p>
                <div class="pitch-proof-list">
                  <span data-pitch-reveal><b>01</b>Make local products and experiences easier to gift.</span>
                  <span data-pitch-reveal><b>02</b>Generate revenue before the customer arrives.</span>
                  <span data-pitch-reveal><b>03</b>Turn every interaction into usable customer memory.</span>
                </div>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--product" id="pitch-slide-4" data-pitch-slide data-slide-label="Product">
            <div class="pitch-slide__inner">
              <header class="pitch-copy pitch-copy--wide">
                <p class="pitch-eyebrow">03 · The product</p>
                <h2>Sell. Send. Claim.<br><em>Engage. Repeat.</em></h2>
                <p class="pitch-lede">A complete digital commerce lifecycle without requiring merchants to install new hardware or manage disconnected technology.</p>
              </header>
              <div class="pitch-lifecycle" aria-label="Microgifter product lifecycle">
                <div class="pitch-lifecycle__line"><span></span></div>
                <article data-pitch-reveal><b>01</b><div><small>Create</small><h3>Publish what makes the business valuable.</h3><p>Products, services, experiences, rewards, and offers.</p></div></article>
                <article data-pitch-reveal><b>02</b><div><small>Sell</small><h3>Capture customer intent now.</h3><p>Personal, group, workplace, community, and recurring gifting.</p></div></article>
                <article data-pitch-reveal><b>03</b><div><small>Send</small><h3>Deliver now or schedule later.</h3><p>Flexible digital delivery and recipient ownership.</p></div></article>
                <article data-pitch-reveal><b>04</b><div><small>Claim</small><h3>Connect the recipient.</h3><p>Secure claim, ownership, and customer identity.</p></div></article>
                <article data-pitch-reveal><b>05</b><div><small>Redeem</small><h3>Verify the local transaction.</h3><p>Merchant confirmation without replacing existing operations.</p></div></article>
                <article data-pitch-reveal><b>06</b><div><small>Grow</small><h3>Create the next interaction.</h3><p>CRM, rewards, referrals, campaigns, and automated follow-up.</p></div></article>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--platform" id="pitch-slide-5" data-pitch-slide data-slide-label="Platform">
            <div class="pitch-slide__inner pitch-slide__inner--split">
              <div class="pitch-copy">
                <p class="pitch-eyebrow">04 · The platform</p>
                <h2>More Than a Gift Certificate.<br><em>A Commerce Operating System.</em></h2>
                <p class="pitch-lede">Each module strengthens the same relationship graph, giving merchants one place to sell, engage, measure, and grow.</p>
                <strong class="pitch-statement">One platform. One customer history. More reasons to return.</strong>
              </div>
              <div class="pitch-platform-grid">
                <article data-pitch-reveal><span>01</span><h3>Social Gifting</h3><p>Local products and experiences sent now or later.</p></article>
                <article data-pitch-reveal><span>02</span><h3>Pre-Sale Commerce</h3><p>Future demand converted into present-day revenue.</p></article>
                <article data-pitch-reveal><span>03</span><h3>Merchant CRM</h3><p>Purchases, claims, visits, messages, and rewards connected.</p></article>
                <article data-pitch-reveal><span>04</span><h3>Campaigns</h3><p>Offers, contests, referrals, QR, creator, and local promotions.</p></article>
                <article data-pitch-reveal><span>05</span><h3>Loyalty & Rewards</h3><p>Recognition for actions that drive customer growth.</p></article>
                <article data-pitch-reveal><span>06</span><h3>Automated Commerce</h3><p>Recurring gifts, workplace programs, and agent-assisted activity.</p></article>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--business" id="pitch-slide-6" data-pitch-slide data-slide-label="Business model">
            <div class="pitch-slide__inner">
              <header class="pitch-copy pitch-copy--wide">
                <p class="pitch-eyebrow">05 · The business model</p>
                <h2>Recurring Software Revenue.<br><em>Transaction Upside.</em></h2>
                <p class="pitch-lede">Microgifter combines merchant SaaS, transaction participation, and larger recurring or sponsored commerce programs.</p>
              </header>
              <div class="pitch-business-grid">
                <article class="pitch-business-card" data-pitch-reveal><span>Merchant SaaS</span><strong>$49<small>/month</small></strong><p>Access to the connected gifting, CRM, campaign, loyalty, and commerce platform.</p></article>
                <article class="pitch-business-card pitch-business-card--featured" data-pitch-reveal><span>Transaction commission</span><strong>15<small>%</small></strong><p>Revenue participation across product sales and recurring gifting activity.</p></article>
                <article class="pitch-business-card" data-pitch-reveal><span>Expansion revenue</span><strong>Enterprise</strong><p>Workplace rewards, group gifting, sponsored commerce, creator programs, and community campaigns.</p></article>
                <aside class="pitch-unit-card" data-pitch-reveal>
                  <div><span>Illustrative active merchant</span><strong>$1,174<small>MRR</small></strong></div>
                  <div class="pitch-unit-formula"><span>10 daily sales</span><i>×</i><span>$25 average ticket</span><i>×</i><span>15% commission</span><i>+</i><span>$49 subscription</span></div>
                  <small>Current internal investor-model assumption. Actual performance will vary by merchant and category.</small>
                </aside>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--market" id="pitch-slide-7" data-pitch-slide data-slide-label="Market">
            <div class="pitch-slide__inner pitch-slide__inner--split">
              <div class="pitch-copy">
                <p class="pitch-eyebrow">06 · The market path</p>
                <h2>Start Focused.<br><em>Expand Across Local Commerce.</em></h2>
                <p class="pitch-lede">The initial wedge targets bars, restaurants, and fast-commerce merchants, then expands across hospitality, entertainment, travel, fitness, services, creators, and community programs.</p>
                <a class="pitch-text-link" href="/investors.php">Open the full market model <span>→</span></a>
              </div>
              <div class="pitch-market-ladder" aria-label="Illustrative annual recurring revenue market ladder">
                <div class="pitch-market-axis"><span>$0</span><span>$2B</span><span>$4B</span><span>$6B</span><span>$8B</span></div>
                <div class="pitch-market-bars">
                  <article data-pitch-reveal style="--bar:.08"><strong>$35.2M</strong><div><i></i></div><span>2,500</span><small>Near-term</small></article>
                  <article data-pitch-reveal style="--bar:.14"><strong>$105.7M</strong><div><i></i></div><span>7,500</span><small>SOM</small></article>
                  <article data-pitch-reveal style="--bar:.34"><strong>$704.4M</strong><div><i></i></div><span>50,000</span><small>SAM</small></article>
                  <article data-pitch-reveal style="--bar:1"><strong>$7.04B</strong><div><i></i></div><span>500,000</span><small>TAM</small></article>
                </div>
                <p>Illustrative ARR based on the current internal model of $14,088 annual revenue per active merchant.</p>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--gtm" id="pitch-slide-8" data-pitch-slide data-slide-label="Go to market">
            <div class="pitch-slide__inner">
              <header class="pitch-copy pitch-copy--wide">
                <p class="pitch-eyebrow">07 · Go-to-market</p>
                <h2>Build Local Density.<br><em>Then Compound the Network.</em></h2>
                <p class="pitch-lede">The strategy begins with focused merchant categories, partner-led acquisition, and local customer demand—then grows through recipients, referrals, campaigns, and enterprise-sponsored commerce.</p>
              </header>
              <div class="pitch-gtm-roadmap">
                <article data-pitch-reveal><b>01</b><div><span>Launch wedge</span><h3>Hospitality and fast commerce</h3><p>Bars, restaurants, venues, local experiences, and high-frequency gifting categories.</p></div></article>
                <article data-pitch-reveal><b>02</b><div><span>Distribution</span><h3>Affiliates and marketing partners</h3><p>Use local business relationships and reseller channels to reduce merchant acquisition friction.</p></div></article>
                <article data-pitch-reveal><b>03</b><div><span>Network growth</span><h3>Every gift creates a new participant</h3><p>Recipients become customers, claims become visits, and campaigns create repeat activity.</p></div></article>
                <article data-pitch-reveal><b>04</b><div><span>Expansion</span><h3>Enterprise and community programs</h3><p>Workplace recognition, group gifting, sponsored purchases, creators, and local organizations.</p></div></article>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--moat" id="pitch-slide-9" data-pitch-slide data-slide-label="Defensibility">
            <div class="pitch-slide__inner pitch-slide__inner--split pitch-slide__inner--reverse">
              <div class="pitch-moat-visual" aria-label="Microgifter data and relationship flywheel">
                <div class="pitch-moat-rings"><i></i><i></i><i></i><i></i></div>
                <div class="pitch-moat-core"><span>Commerce</span><strong>Relationship Graph</strong><small>Gets stronger with every interaction</small></div>
                <article class="pitch-moat-node pitch-moat-node--one">Product intent</article>
                <article class="pitch-moat-node pitch-moat-node--two">Gift ownership</article>
                <article class="pitch-moat-node pitch-moat-node--three">Claim history</article>
                <article class="pitch-moat-node pitch-moat-node--four">Merchant activity</article>
                <article class="pitch-moat-node pitch-moat-node--five">Campaign response</article>
                <article class="pitch-moat-node pitch-moat-node--six">Future demand</article>
              </div>
              <div class="pitch-copy">
                <p class="pitch-eyebrow">08 · Defensibility</p>
                <h2>The Transaction Ends.<br><em>The Relationship Keeps Growing.</em></h2>
                <p class="pitch-lede">Microgifter is designed around the lifecycle after purchase: ownership, delivery, claim, redemption, engagement, and the next predicted opportunity.</p>
                <div class="pitch-proof-list">
                  <span data-pitch-reveal><b>01</b>Connected purchase-to-claim lifecycle data.</span>
                  <span data-pitch-reveal><b>02</b>Merchant CRM memory across every customer action.</span>
                  <span data-pitch-reveal><b>03</b>Campaign and reward rules that shape agent-assisted commerce.</span>
                  <span data-pitch-reveal><b>04</b>Local network value that increases with merchant and customer density.</span>
                </div>
              </div>
            </div>
          </article>

          <article class="pitch-slide pitch-slide--vision" id="pitch-slide-10" data-pitch-slide data-slide-label="The ask">
            <div class="pitch-slide__inner pitch-slide__inner--split">
              <div class="pitch-copy pitch-copy--hero">
                <p class="pitch-eyebrow">09 · The vision and the ask</p>
                <h2>Turn Future Demand Into <em>Present-Day Revenue.</em></h2>
                <p class="pitch-lede">Microgifter can help independent businesses sell earlier, understand future customer activity, and compete for the moments that currently default to national brands.</p>
                <div class="pitch-ask-grid">
                  <article data-pitch-reveal><span>Capital</span><p>Accelerate merchant acquisition, platform operations, integrations, and product intelligence.</p></article>
                  <article data-pitch-reveal><span>Partners</span><p>Develop channel relationships, enterprise programs, and focused local-market launches.</p></article>
                  <article data-pitch-reveal><span>Pilots</span><p>Prove recurring, sponsored, workplace, creator, and community commerce programs.</p></article>
                </div>
                <div class="pitch-actions">
                  <a class="pitch-button" href="/learn-more.php">Start an investor conversation <span>→</span></a>
                  <a class="pitch-text-link" href="https://www.linkedin.com/in/david-evans-15005530/" target="_blank" rel="noopener">Connect with David <span>↗</span></a>
                </div>
              </div>
              <div class="pitch-vision-visual" aria-label="Future demand becoming present-day revenue">
                <div class="pitch-vision-label pitch-vision-label--future"><span>Future demand</span><strong>Intent</strong><small>Gifts · rewards · recurring programs</small></div>
                <div class="pitch-vision-transfer"><i></i><i></i><i></i><b>→</b></div>
                <div class="pitch-vision-label pitch-vision-label--present"><span>Present day</span><strong>Revenue</strong><small>Measurable local commerce</small></div>
                <div class="pitch-vision-signature"><span>Make local</span><strong>the easy choice.</strong></div>
              </div>
            </div>
          </article>
        </div>

        <nav class="pitch-progress" aria-label="Pitch deck sections">
          <button type="button" class="is-active" data-pitch-jump="0"><span>Vision</span></button>
          <button type="button" data-pitch-jump="1"><span>Problem</span></button>
          <button type="button" data-pitch-jump="2"><span>Solution</span></button>
          <button type="button" data-pitch-jump="3"><span>Product</span></button>
          <button type="button" data-pitch-jump="4"><span>Platform</span></button>
          <button type="button" data-pitch-jump="5"><span>Business model</span></button>
          <button type="button" data-pitch-jump="6"><span>Market</span></button>
          <button type="button" data-pitch-jump="7"><span>Go to market</span></button>
          <button type="button" data-pitch-jump="8"><span>Defensibility</span></button>
          <button type="button" data-pitch-jump="9"><span>The ask</span></button>
        </nav>

        <div class="pitch-scroll-cue" aria-hidden="true"><span></span> Scroll to advance</div>
      </div>
    </div>
  </section>

  <section class="pitch-after" aria-label="Microgifter investor next steps">
    <div class="pitch-after__inner">
      <p class="pitch-eyebrow">Continue the conversation</p>
      <h2>Explore the numbers.<br>See the platform in action.</h2>
      <p>Review the detailed market model or schedule a conversation about Microgifter’s product, launch strategy, and investment opportunity.</p>
      <div class="pitch-actions">
        <a class="pitch-button" href="/investors.php">View market model <span>→</span></a>
        <a class="pitch-button pitch-button--light" href="/learn-more.php">Book a demo</a>
      </div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
