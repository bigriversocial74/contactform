      <div class="mg-eyebrow" data-reveal="left">For growth</div>
      <h2 class="mg-section-title" id="growthTitle" data-reveal="left" style="--delay:80ms">Distribute rewards, run campaigns, and manage customer relationships.</h2>
      <p class="mg-lede" data-reveal="left" style="--delay:160ms">Microgifter gives merchants the tools to send rewards through the Distribution API, launch targeted campaigns, and organize customer activity in one connected CRM.</p>

      <div class="mg-feature-panels">
        <article class="mg-panel" data-reveal="scale">
          <div class="mg-panel-head"><div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M8 5l-5 7 5 7M16 5l5 7-5 7M14 4l-4 16"/></svg></div><div><h3>Distribution API</h3><p>Send agent-ready rewards through your app, website, partners, or automation workflows.</p></div></div>
          <div class="mg-codebox" aria-label="Distribution API code example"><span class="gold">POST</span> <span>/v1/distribution/send</span> <span class="green">● 200 OK</span><br>{<br>&nbsp;&nbsp;<span class="muted">"merchant_id"</span>: <span class="green">"m_123456"</span>,<br>&nbsp;&nbsp;<span class="muted">"reward"</span>: <span class="green">"limited_coffee_for_two"</span>,<br>&nbsp;&nbsp;<span class="muted">"recipient"</span>: {<br>&nbsp;&nbsp;&nbsp;&nbsp;<span class="muted">"type"</span>: <span class="green">"email"</span>,<br>&nbsp;&nbsp;&nbsp;&nbsp;<span class="muted">"value"</span>: <span class="green">"alex@example.com"</span><br>&nbsp;&nbsp;},<br>&nbsp;&nbsp;<span class="muted">"campaign"</span>: <span class="green">"spring_launch_2026"</span><br>}</div>
          <div class="mg-panel-tags"><span>Send</span><span>Track</span><span>Redeem</span></div>
        </article>
        <article class="mg-panel" data-reveal="scale" style="--delay:100ms">
          <div class="mg-panel-head"><div class="mg-panel-icon"><svg viewBox="0 0 24 24"><path d="M4 13h4l11-6v10L8 11H4v2Z"/><path d="M8 11v7a2 2 0 0 0 2 2h1M21 9c1 2 1 4 0 6"/></svg></div><div><h3>Campaigns</h3><p>Launch promotions, audience segments, and scheduled reward drops with measurable performance.</p></div></div>
          <div class="mg-mini-ui"><img src="/images/campaing_v1.png" alt="Campaign performance dashboard preview"></div>
          <div class="mg-panel-tags"><span>Audience</span><span>Offer</span><span>Performance</span></div>
        </article>
        <article class="mg-panel" data-reveal="scale" style="--delay:200ms">
          <div class="mg-panel-head"><div class="mg-panel-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-5 4.2-8 8-8s6.5 3 8 8"/></svg></div><div><h3>CRM</h3><p>See customer activity, reward history, and visit behavior in one simple relationship layer.</p></div></div>
          <div class="mg-mini-ui"><img src="/images/crm_v1.png" alt="CRM reward history preview"></div>
          <div class="mg-panel-tags"><span>Profile</span><span>History</span><span>Engagement</span></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-section mg-story-band" id="future-demand" aria-labelledby="agenticTitle">
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">Tokenized experiences</span>
          <h2 id="agenticTitle">Local experiences can become scarce, ownable, and tradable.</h2>
          <p>Microgifter turns limited local experiences into programmable demand objects. Customers can buy early access, hold it, gift it, redeem it, or transfer it through a controlled resale market.</p>
        </div>
        <div>
          <div class="mg-agentic-panel">
            <article class="mg-agentic-card" data-reveal="up">
              <h3>What tokenized experience means</h3>
              <p>A real-world experience becomes a wallet-ready claim with limited supply, ownership, redemption rules, transferability, and performance data.</p>
              <p>The blockchain layer is not the headline. It supports proof, scarcity, ownership, and transfer while the customer still buys a real local experience.</p>
            </article>
            <article class="mg-agentic-card" data-reveal="up" style="--delay:120ms">
              <h3>The future demand object</h3>
              <div class="mg-agent-flow" aria-label="Tokenized experiences flow">
                <div class="mg-flow-item"><span>Limited experience</span><span>→</span></div>
                <div class="mg-flow-item"><span>Tokenized claim</span><span>→</span></div>
                <div class="mg-flow-item"><span>Marketplace demand</span><span>→</span></div>
                <div class="mg-flow-item"><span>Customer wallet</span><span>→</span></div>
                <div class="mg-flow-item"><span>Redeem or resell</span><span>✓</span></div>
              </div>
            </article>
          </div>
          <div class="mg-social-proof" data-reveal="up" style="--delay:220ms">
            <div class="mg-proof-pill">Restaurants</div>
            <div class="mg-proof-pill">Venues</div>
            <div class="mg-proof-pill">Fitness</div>
            <div class="mg-proof-pill">Local guides</div>
            <div class="mg-proof-pill">Developers</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section mg-story-band" id="how-it-works" aria-labelledby="howTitle">
    <div class="mg-container">
      <div class="mg-story-grid">
        <div class="mg-story-copy" data-reveal="left">
          <span class="mg-story-kicker">How it works</span>
          <h2 id="howTitle">A simple loop from limited access to verified future demand.</h2>
          <p>The page story should feel like a market system: create limited access, distribute demand, let customers hold or transfer the claim, then verify redemption when the visit happens.</p>
        </div>
        <div class="mg-story-list">
          <article class="mg-story-step" data-reveal="up">
            <b>01</b>
            <div><h3>Create a limited experience</h3><p>Build a scarce local experience with supply, price, rules, transfer settings, expiration, and redemption logic.</p></div>
          </article>
          <article class="mg-story-step" data-reveal="up" style="--delay:100ms">
            <b>02</b>
            <div><h3>Release the drop</h3><p>Launch through your website, QR codes, campaigns, partner apps, social channels, or the Microgifter Distribution API.</p></div>
          </article>
          <article class="mg-story-step" data-reveal="up" style="--delay:200ms">
            <b>03</b>
            <div><h3>Hold, gift, or transfer</h3><p>The customer keeps the experience in a wallet and can redeem, gift, transfer, or resell it when demand exists.</p></div>
          </article>
          <article class="mg-story-step" data-reveal="up" style="--delay:300ms">
            <b>04</b>
            <div><h3>Verify demand</h3><p>Measure primary sales, transfers, claims, redemptions, resale activity, revenue, and merchant demand signals.</p></div>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="mg-section mg-story-band" id="merchant-examples" aria-labelledby="examplesTitle">
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Merchant examples</span>
        <h2 class="mg-section-title" id="examplesTitle" data-reveal="left">Make future demand concrete.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Microgifter starts with simple local experiences that can be limited, purchased early, held, gifted, transferred, and redeemed later.</p>
      </div>
      <div class="mg-examples-grid">
        <article class="mg-example-card" data-reveal="up">
          <small>Coffee shop</small>
          <h3>Coffee for Two</h3>
          <p>A lightweight gift that brings two people into a local cafe.</p>
          <div class="mg-example-meta"><div><span>Price</span><strong>$18</strong></div><div><span>Use case</span><strong>Wallet gift</strong></div></div>
        </article>
        <article class="mg-example-card" data-reveal="up" style="--delay:90ms">
          <small>Restaurant</small>
          <h3>Lunch Special</h3>
          <p>Prepaid lunch demand during slower weekday windows.</p>
          <div class="mg-example-meta"><div><span>Price</span><strong>$24</strong></div><div><span>Use case</span><strong>Campaign</strong></div></div>
        </article>
        <article class="mg-example-card" data-reveal="up" style="--delay:180ms">
          <small>Venue</small>
          <h3>Two Drink Reward</h3>
          <p>A reward object that can be sent before an event or show.</p>
          <div class="mg-example-meta"><div><span>Price</span><strong>$22</strong></div><div><span>Use case</span><strong>Event drop</strong></div></div>
        </article>
        <article class="mg-example-card" data-reveal="up" style="--delay:270ms">
          <small>Fitness</small>
          <h3>Trial Class Pass</h3>