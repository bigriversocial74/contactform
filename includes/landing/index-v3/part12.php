          <p>A measurable intro offer for first-time customers.</p>
          <div class="mg-example-meta"><div><span>Price</span><strong>$15</strong></div><div><span>Use case</span><strong>Lead reward</strong></div></div>
        </article>
      </div>
    </div>
  </section>

  <style>
    @media(max-width:760px){
      .mg-v4 .mg-v4-hero{padding-top:86px!important;overflow:hidden!important;}
      .mg-v4 .mg-v4-hero::after{
        background:linear-gradient(180deg,rgba(245,245,242,.02) 0%,rgba(245,245,242,.04) 38%,rgba(245,245,242,.90) 58%,rgba(245,245,242,.98) 76%,rgba(0,0,0,.78) 94%,#020202 100%)!important;
      }
      .mg-v4 .mg-v4-hero-grid{width:calc(100% - 22px)!important;min-height:calc(100svh - 86px)!important;}
      .mg-v4 .mg-v4-visual{min-height:335px!important;align-items:flex-end!important;justify-content:center!important;margin-top:0!important;}
      .mg-v4 .mg-v10-desktop{width:min(92vw,390px)!important;margin:0 auto -8px!important;transform:translateX(0)!important;}
      .mg-v4 .mg-v4-phone{left:8px!important;bottom:-22px!important;width:min(120px,32vw)!important;}
      .mg-v4 .mg-v4-copy{padding-top:104px!important;padding-bottom:58px!important;}
    }
    @media(max-width:440px){
      .mg-v4 .mg-v4-visual{min-height:320px!important;}
      .mg-v4 .mg-v10-desktop{width:min(92vw,370px)!important;margin:0 auto -4px!important;}
      .mg-v4 .mg-v4-copy{padding-top:96px!important;}
    }
  </style>

  <section class="mg-section mg-story-band" id="api-preview" aria-labelledby="apiPreviewTitle">
    <div class="mg-container">
      <div class="mg-section-head">
        <span class="mg-story-kicker" data-reveal="left">Distribution API</span>
        <h2 class="mg-section-title" id="apiPreviewTitle" data-reveal="left">A future-demand layer developers can wire into their own products.</h2>
        <p class="mg-section-copy" data-reveal="left" style="--delay:120ms">Use Microgifter to issue, distribute, track, and verify tokenized local experiences from apps, campaigns, CRMs, loyalty workflows, and AI-powered commerce systems.</p>
      </div>
      <div class="mg-api-story">
        <pre class="mg-code-panel" data-reveal="up"><span class="gold">POST</span> /v1/distribution/send
{
  "merchant_id": <span class="green">"m_local_123"</span>,
  "reward": <span class="green">"limited_coffee_for_two"</span>,
  "recipient": {
    "type": <span class="green">"email"</span>,
    "value": <span class="green">"customer@example.com"</span>
  },
  "metadata": {
    "source": <span class="green">"future-demand"</span>
  }
}</pre>
        <article class="mg-api-flow" data-reveal="up" style="--delay:120ms">
          <h3>From experience drop to redemption</h3>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">01</span><p>A merchant or partner app releases a limited experience.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">02</span><p>Microgifter issues the claim and tracks ownership.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">03</span><p>Customer holds, gifts, transfers, resells, or redeems.</p></div>
          <div class="mg-api-flow-row"><span class="mg-api-flow-dot">04</span><p>Merchant verifies redemption and sees demand activity.</p></div>
        </article>
      </div>
    </div>
  </section>

  <section class="mg-public-bottom-demo" aria-labelledby="mgPublicDemoTitle">
    <div class="mg-public-bottom-demo-inner">
      <span class="mg-public-bottom-demo-eyebrow">Book a demo</span>
      <h2 id="mgPublicDemoTitle">See how Microgifter creates future local demand.</h2>
      <p>Walk through tokenized experience drops, wallet claims, resale controls, merchant tools, and the Distribution API in one focused demo.</p>
      <div class="mg-public-bottom-demo-actions">
        <a class="mg-public-bottom-demo-primary" href="/learn-more.php">Book a Demo</a>
        <a class="mg-public-bottom-demo-secondary" href="/pricing.php">View Pricing</a>
        <a class="mg-public-bottom-demo-secondary" href="/developer-docs.php">Explore the API</a>
      </div>
    </div>
  </section>

</main>

<footer class="mg-footer">
  <div class="mg-bg-mesh" aria-hidden="true"></div>
  <div class="mg-container">
    <div class="mg-footer-grid">
      <div class="mg-footer-brand" data-reveal="left">
        <a class="mg-logo" href="/" aria-label="Microgifter home">
          <svg class="mg-logo-mark" viewBox="0 0 256 256" aria-hidden="true"><path d="M38 54H86L128 96L170 54H218V202H170V118L128 160L86 118V202H38V54Z" fill="currentColor"/><path d="M96 108L128 140L160 108L128 76L96 108Z" fill="currentColor" opacity=".72"/></svg>
          <span class="mg-logo-text">Microgifter</span>
        </a>
        <p class="mg-footer-tag">Local rewards. Real results.<br>Powering agent-ready commerce.</p>
        <div class="mg-socials" aria-label="Social links">
          <a href="https://linkedin.com/company/microgifter" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M4 8h4v12H4V8Zm2-5a2.3 2.3 0 1 1 0 4.6A2.3 2.3 0 0 1 6 3Zm5 5h4v1.7c.7-1.1 2-2 4-2 3.4 0 5 2.2 5 6.1V20h-4v-5.5c0-2.1-.8-3.1-2.3-3.1-1.7 0-2.7 1.2-2.7 3.1V20h-4V8Z"/></svg></a>
          <a href="https://x.com/microgifter" aria-label="X"><svg viewBox="0 0 24 24"><path d="M4 4l16 16M20 4L4 20" stroke-width="2.2" fill="none" stroke-linecap="round"/></svg></a>
          <a href="https://github.com/bigriversocial74" aria-label="GitHub"><svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12c0 4.4 2.9 8.1 6.8 9.5.5.1.7-.2.7-.5v-1.8c-2.8.6-3.4-1.2-3.4-1.2-.5-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 .1 1.6 1.1 1.6 1.1.9 1.6 2.4 1.1 2.9.9.1-.7.4-1.1.7-1.4-2.2-.3-4.6-1.1-4.6-4.9 0-1.1.4-2 1.1-2.7-.1-.3-.5-1.3.1-2.7 0 0 .9-.3 2.8 1.1.8-.2 1.6-.3 2.4-.3.8 0 1.7.1 2.4.3 1.9-1.4 2.8-1.1 2.8-1.1.6 1.4.2 2.4.1 2.7.7.8 1.1 1.6 1.1 2.7 0 3.8-2.3 4.6-4.6 4.9.4.3.8 1 .8 2v3c0 .3.2.6.8.5C19.1 20.1 22 16.4 22 12 22 6.5 17.5 2 12 2Z"/></svg></a>
          <a href="https://youtube.com/@microgifter" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M21 8.2a3 3 0 0 0-2.1-2.1C17 5.6 12 5.6 12 5.6s-5 0-6.9.5A3 3 0 0 0 3 8.2C2.5 10.1 2.5 12 2.5 12s0 1.9.5 3.8a3 3 0 0 0 2.1 2.1c1.9.5 6.9.5 6.9.5s5 0 6.9-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-3.8.5-3.8s0-1.9-.5-3.8ZM10 15.4V8.6l6 3.4-6 3.4Z"/></svg></a>
        </div>
        <a class="mg-contact" href="mailto:hello@microgifter.com"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M4 7l8 6 8-6"/></svg>hello@microgifter.com</a>
      </div>

      <div class="mg-footer-col" data-reveal="left" style="--delay:80ms"><h3>Product</h3><nav><a href="#merchants">Rewards</a><a href="#platform">Wallet</a><a href="#growth">Campaigns</a><a href="/pricing.php">Pricing</a></nav></div>
      <div class="mg-footer-col" data-reveal="left" style="--delay:160ms"><h3>Platform</h3><nav><a href="#growth">Distribution API</a><a href="#platform">Agent Toolkit</a><a href="/developer-docs.php">Docs</a><a href="/learn-more.php">Book a Demo</a></nav></div>
      <div class="mg-footer-col" data-reveal="left" style="--delay:240ms"><h3>Company</h3><nav><a href="/about.php">About Us</a><a href="/careers.php">Careers</a><a href="/support.php">Contact</a><a href="/pitch-deck.php">Press Kit</a></nav></div>
    </div>
    <div class="mg-footer-bottom" data-reveal="scale">
      <span>&copy; <?= date('Y') ?> Microgifter. All rights reserved.</span>
      <div class="mg-footer-links"><a href="/privacy.php">Privacy Policy</a><span class="mg-dot">•</span><a href="/pricing.php">Pricing</a><span class="mg-dot">•</span><a href="/terms.php">Terms of Service</a><span class="mg-dot">•</span><a href="/security.php">Security</a><span class="mg-dot">•</span><a href="/status.php">Status</a></div>
    </div>
  </div>
</footer>

<script>
(() => {
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
  const progressBar = document.getElementById('mgProgressBar');
  const screens = Array.from(document.querySelectorAll('.mg-carousel-screen'));
  const dots = Array.from(document.querySelectorAll('.mg-carousel-dot'));
  let activeScreen = 0;

  const showAllRevealItems = () => {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  };

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px 12% 0px' });

    revealItems.forEach((item) => revealObserver.observe(item));
    setTimeout(showAllRevealItems, 1800);
  } else {
    showAllRevealItems();
  }

  document.querySelectorAll('img').forEach((img) => {
    img.addEventListener('error', () => {
      img.classList.add('mg-image-missing');
      const holder = img.closest('.mg-mini-ui, .mg-phone-screen, .mg-float-card');
      if (holder && !holder.querySelector('.mg-missing-note')) {
        const note = document.createElement('div');
        note.className = 'mg-missing-note';
        note.textContent = 'Image missing: ' + img.getAttribute('src');
        holder.appendChild(note);
      }
    }, { once: true });
  });

  const updateProgress = () => {
    if (!progressBar) return;
    const scrollTop = window.scrollY || document.documentElement.scrollTop;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const pct = docHeight > 0 ? Math.min(1, scrollTop / docHeight) : 0;
    progressBar.style.width = `${pct * 100}%`;
  };

  const rotateScreens = () => {
    if (!screens.length) return;
    screens[activeScreen].classList.remove('is-active');
    dots[activeScreen]?.classList.remove('is-active');
    activeScreen = (activeScreen + 1) % screens.length;
    screens[activeScreen].classList.add('is-active');
    dots[activeScreen]?.classList.add('is-active');
  };

  window.addEventListener('scroll', updateProgress, { passive: true });
  window.addEventListener('resize', updateProgress);
  updateProgress();
  setInterval(rotateScreens, 4300);
})();
</script>

</body>
</html>