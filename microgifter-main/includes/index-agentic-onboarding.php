<section class="agentic-onboarding" id="agentic-onboarding" data-agentic-onboarding>
  <div class="agentic-onboarding-shell">
    <header class="agentic-onboarding-head">
      <div>
        <span class="badge"><span class="pulse"></span> Agentic onboarding presentation</span>
        <h2>Build the first Microgifter product with a guided agent.</h2>
        <p>The presentation continues from the landing page, pauses when your input is required, and hands your choices into the next build step.</p>
      </div>
      <div class="agentic-session-status" data-agentic-status>Presentation starting</div>
    </header>

    <div class="agentic-progress" aria-label="Onboarding progress">
      <strong data-agentic-step-label>Meet the agent</strong>
      <div class="agentic-progress-track"><i data-agentic-progress-bar></i></div>
      <span data-agentic-progress-text>0%</span>
    </div>

    <div class="agentic-stage-stack">
      <article class="agentic-stage" data-agentic-stage data-step="0" data-requires-input="false">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 1 · Meet the agent</span><h3>Your agent connects the product, buyer, recipient, claim, and merchant.</h3><p>It guides setup, organizes the delivery path, and keeps the next action visible without replacing your control.</p></div>
          <div class="agentic-visual" data-presentation-image><div class="agentic-orbit"></div><div class="agentic-agent-core">AI</div><div class="agentic-visual-list"><span>Product activation</span><span>Gift delivery</span><span>Claims and fulfillment</span><span>Customer follow-up</span></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="1" data-requires-input="true">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 2 · Objective</span><h3>What should the agent help you launch first?</h3><p>Choose the closest starting point. Every option can be edited later in the Product Builder.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><div class="agentic-choice-grid" data-interest-choices><button class="agentic-choice" type="button" data-choice-value="gift-product"><strong>Create a gift product</strong><span>Build a prepaid local product or experience.</span></button><button class="agentic-choice" type="button" data-choice-value="local-offer"><strong>Launch a local offer</strong><span>Turn future demand into present revenue.</span></button><button class="agentic-choice" type="button" data-choice-value="workplace"><strong>Workplace reward</strong><span>Create a repeatable employee reward.</span></button><button class="agentic-choice" type="button" data-choice-value="contest"><strong>Contest reward</strong><span>Build a claimable promotional prize.</span></button></div><div class="agentic-pause-banner">Presentation paused for your selection</div><div class="agentic-actions"><span class="agentic-helper">Choose one or skip this step.</span><button class="agentic-btn" type="button" data-agentic-skip>Skip</button></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="2" data-requires-input="true">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 3 · Business</span><h3>Tell the agent who it is building for.</h3><p>Add a business or project name so the remaining ideas feel specific instead of generic.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><label for="agenticBusinessName">Business or project name</label><input class="agentic-input" id="agenticBusinessName" data-agentic-field="businessName" type="text" maxlength="160" autocomplete="organization" placeholder="Example: Local Coffee House"><div class="agentic-pause-banner">Presentation paused for your response</div><div class="agentic-actions"><span class="agentic-helper">Saved in this browser until account creation.</span><div class="agentic-action-group"><button class="agentic-btn" type="button" data-agentic-skip>Skip</button><button class="agentic-btn agentic-btn-primary" type="button" data-agentic-next disabled>Continue</button></div></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="3" data-requires-input="true">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 4 · Website scan</span><h3>Give the agent a public website to review.</h3><p>It can use visible public content to suggest giftable products, offers, services, and experiences.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><label for="agenticBusinessWebsite">Business website</label><input class="agentic-input" id="agenticBusinessWebsite" data-agentic-field="businessWebsite" type="url" inputmode="url" autocomplete="url" placeholder="https://yourbusiness.com"><div class="agentic-scan-state" data-agentic-scan-state>Preparing website scan…</div><div class="agentic-pause-banner">Presentation paused for your response</div><div class="agentic-actions"><span class="agentic-helper">Only publicly accessible content is reviewed.</span><div class="agentic-action-group"><button class="agentic-btn" type="button" data-agentic-skip>Skip</button><button class="agentic-btn agentic-btn-primary" type="button" data-agentic-scan disabled>Scan website</button></div></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="4" data-requires-input="true">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 5 · Product direction</span><h3>Choose the first product concept.</h3><p>Select a recommendation or continue with a custom product direction.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><div class="agentic-product-grid" data-agentic-product-grid></div><div class="agentic-pause-banner">Presentation paused for your selection</div><div class="agentic-actions"><button class="agentic-btn agentic-btn-soft" type="button" data-agentic-custom>Create my own</button><div class="agentic-action-group"><button class="agentic-btn" type="button" data-agentic-skip>Skip</button><button class="agentic-btn agentic-btn-primary" type="button" data-agentic-next disabled>Use selected idea</button></div></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="5" data-requires-input="true">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 6 · Agent preview</span><h3>Review what the agent will manage.</h3><p>Confirm or rewrite the product direction before the authenticated builder takes over.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><label for="agenticCustomProduct">Product or offer</label><textarea class="agentic-textarea" id="agenticCustomProduct" data-agentic-field="customProduct" maxlength="1000" placeholder="Example: Sell a $25 coffee-for-two voucher, redeemable during slower weekday hours."></textarea><div class="agentic-visual-list agentic-visual-list-compact"><span>Activation checklist</span><span>Customer questions</span><span>Gift delivery</span><span>Claim confirmation</span></div><div class="agentic-pause-banner">Presentation paused for your response</div><div class="agentic-actions"><span class="agentic-helper">Everything remains editable later.</span><div class="agentic-action-group"><button class="agentic-btn" type="button" data-agentic-skip>Skip</button><button class="agentic-btn agentic-btn-primary" type="button" data-agentic-next disabled>Build summary</button></div></div></div>
        </div>
      </article>

      <article class="agentic-stage" data-agentic-stage data-step="6" data-requires-input="false">
        <div class="agentic-stage-pin">
          <div class="agentic-stage-copy"><span class="agentic-step-label">Step 7 · Build handoff</span><h3>Your starting point is ready for the Product Builder.</h3><p>Create an account or sign in to continue with pricing, media, publishing, delivery, and claims.</p></div>
          <div class="agentic-prompt-card" data-presentation-image><div class="agentic-summary" data-agentic-summary></div><div class="agentic-actions"><button class="agentic-btn" type="button" data-agentic-restart>Restart presentation</button><div class="agentic-action-group"><a class="agentic-btn agentic-btn-soft" href="/signin.php">Sign in</a><a class="agentic-btn agentic-btn-primary" data-agentic-signup href="/signup.php">Start building</a></div></div></div>
        </div>
      </article>
    </div>
  </div>
  <button class="agentic-resume-control" type="button" data-agentic-resume>Resume presentation</button>
</section>
