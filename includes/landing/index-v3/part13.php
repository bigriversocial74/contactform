<?php
declare(strict_types=1);
?>
<style>
/* Stage 12M landing hotfix: white accents, root-level image paths, clearer problem statement. */
:root{
  --mg-line:rgba(255,255,255,.28);
  --mg-gold:#ffffff;
  --mg-gold-2:#e8e8e8;
}
.mg-bg-mesh{
  background:
    linear-gradient(90deg,rgba(0,0,0,.72),rgba(0,0,0,.14) 47%,rgba(0,0,0,.72)),
    url('images/cosmic_golden_network_on_black.png') center/cover no-repeat!important;
}
.mg-mesh-ribbon{
  background:url('images/cosmic_golden_network_on_black.png') center/cover no-repeat!important;
}
.mg-bg-mesh::after,
.mg-story-band::before,
.mg-public-bottom-demo::before{
  background:radial-gradient(circle at 70% 35%,rgba(255,255,255,.08),transparent 35%),linear-gradient(180deg,rgba(0,0,0,.2),#030303 95%)!important;
}
.mg-header-cta,
.mg-card,
.mg-panel,
.mg-panel-icon,
.mg-mini-ui,
.mg-codebox,
.mg-agentic-card,
.mg-example-card,
.mg-api-flow,
.mg-proof-pill,
.mg-socials a,
.mg-public-bottom-demo-secondary,
.mg-market-ticker-center{
  border-color:rgba(255,255,255,.24)!important;
}
.mg-header-cta:hover,
.mg-socials a:hover{
  border-color:rgba(255,255,255,.68)!important;
  background:rgba(255,255,255,.08)!important;
}
.mg-card::after{background:linear-gradient(90deg,transparent,#fff,transparent)!important;}
.mg-eyebrow::before,
.mg-story-kicker::before{
  border-color:#fff!important;
  background:#fff!important;
  box-shadow:0 0 18px rgba(255,255,255,.32)!important;
}
.mg-location-marker,
.mg-icon,
.mg-panel-icon,
.mg-story-kicker,
.mg-flow-item span:last-child,
.mg-example-card small,
.mg-public-bottom-demo-eyebrow,
.mg-footer-col h3,
.mg-footer-links .mg-dot,
.mg-codebox .gold,
.mg-contact svg{
  color:#fff!important;
  stroke:#fff!important;
}
.mg-location-marker::before,
.mg-metric,
.mg-panel-tags,
.mg-footer-bottom{border-color:rgba(255,255,255,.22)!important;}
.mg-carousel-dot{border-color:rgba(255,255,255,.5)!important;}
.mg-carousel-dot.is-active{background:#fff!important;}
.mg-progress-bar{background:linear-gradient(90deg,#fff,#bdbdbd)!important;box-shadow:0 0 20px rgba(255,255,255,.36)!important;}
.mg-market-label{color:#fff!important;}
.mg-ticker-change.flat{color:#fff!important;}
.mg-api-flow-dot{background:rgba(255,255,255,.1)!important;border-color:rgba(255,255,255,.3)!important;color:#fff!important;}
.mg-mini-ui img{filter:grayscale(1) contrast(1.06) brightness(.96);}
</style>
<script>
(() => {
  const text = (selector, value) => {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
  };

  text('.mg-hero-copy .mg-eyebrow', 'Local commerce support layer');
  text('#mgHeroTitle', 'Pre-invest and support your local community.');
  text('.mg-hero-copy .mg-lede', 'Local businesses need revenue and demand before customers walk in. Microgifter turns future visits into wallet-ready rewards customers can buy, gift, save, redeem, or share with others.');
  const note = document.querySelector('.mg-future-demand-note');
  if (note) {
    note.innerHTML = '<strong>Community-first, technology underneath:</strong> help merchants create upfront revenue, measurable demand, and a reason for people to keep coming back.';
  }

  const metrics = document.querySelectorAll('.mg-hero-microcopy .mg-metric');
  const metricCopy = [
    ['Pre-sell demand', 'Turn future visits into simple rewards people can buy today.'],
    ['Support local', 'Give customers a direct way to back neighborhood merchants.'],
    ['Measure results', 'Track claims, visits, revenue, and redemption activity.']
  ];
  metrics.forEach((metric, index) => {
    const copy = metricCopy[index];
    if (!copy) return;
    const strong = metric.querySelector('strong');
    const span = metric.querySelector('span');
    if (strong) strong.textContent = copy[0];
    if (span) span.textContent = copy[1];
  });

  document.querySelectorAll('img[src^="/images/"]').forEach((img) => {
    const next = img.getAttribute('src').replace(/^\/images\//, 'images/');
    const holder = img.closest('.mg-mini-ui, .mg-phone-screen, .mg-float-card');
    if (holder) holder.querySelectorAll('.mg-missing-note').forEach((noteEl) => noteEl.remove());
    img.classList.remove('mg-image-missing');
    img.setAttribute('src', next);
  });
})();
</script>
