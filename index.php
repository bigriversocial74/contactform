<?php
declare(strict_types=1);

require __DIR__ . '/includes/landing/index-v3/part01.php';
require __DIR__ . '/includes/landing/index-v3/part02.php';
require __DIR__ . '/includes/landing/index-v3/part03.php';
require __DIR__ . '/includes/landing/index-v3/part04.php';
require __DIR__ . '/includes/landing/index-v3/part05.php';
require __DIR__ . '/includes/landing/index-v3/part06.php';
require __DIR__ . '/includes/landing/index-v3/part07.php';
require __DIR__ . '/includes/landing/index-v3/part08.php';
require __DIR__ . '/includes/landing/index-v3/part09.php';
require __DIR__ . '/includes/landing/index-v3/part10.php';
require __DIR__ . '/includes/landing/index-v3/part11.php';
require __DIR__ . '/includes/landing/index-v3/part12.php';
?>
<style>
:root{
  --mg-line:rgba(255,255,255,.28);
  --mg-gold:#ffffff;
  --mg-gold-2:#e8e8e8;
}
.mg-bg-mesh{background:linear-gradient(90deg,rgba(0,0,0,.72),rgba(0,0,0,.14) 47%,rgba(0,0,0,.72)),url('images/cosmic_golden_network_on_black.png') center/cover no-repeat!important;}
.mg-mesh-ribbon{background:url('images/cosmic_golden_network_on_black.png') center/cover no-repeat!important;}
.mg-bg-mesh::after,.mg-story-band::before,.mg-public-bottom-demo::before{background:radial-gradient(circle at 70% 35%,rgba(255,255,255,.08),transparent 35%),linear-gradient(180deg,rgba(0,0,0,.2),#030303 95%)!important;}
.mg-header-cta,.mg-card,.mg-panel,.mg-panel-icon,.mg-mini-ui,.mg-codebox,.mg-agentic-card,.mg-example-card,.mg-api-flow,.mg-proof-pill,.mg-socials a,.mg-public-bottom-demo-secondary,.mg-market-ticker-center{border-color:rgba(255,255,255,.24)!important;}
.mg-header-cta:hover,.mg-socials a:hover{border-color:rgba(255,255,255,.68)!important;background:rgba(255,255,255,.08)!important;}
.mg-card::after{background:linear-gradient(90deg,transparent,#fff,transparent)!important;}
.mg-eyebrow::before,.mg-story-kicker::before{border-color:#fff!important;background:#fff!important;box-shadow:0 0 18px rgba(255,255,255,.32)!important;}
.mg-location-marker,.mg-icon,.mg-panel-icon,.mg-story-kicker,.mg-flow-item span:last-child,.mg-example-card small,.mg-public-bottom-demo-eyebrow,.mg-footer-col h3,.mg-footer-links .mg-dot,.mg-codebox .gold,.mg-contact svg{color:#fff!important;stroke:#fff!important;}
.mg-location-marker::before,.mg-metric,.mg-panel-tags,.mg-footer-bottom{border-color:rgba(255,255,255,.22)!important;}
.mg-carousel-dot{border-color:rgba(255,255,255,.5)!important;}
.mg-carousel-dot.is-active{background:#fff!important;}
.mg-progress-bar{background:linear-gradient(90deg,#fff,#bdbdbd)!important;box-shadow:0 0 20px rgba(255,255,255,.36)!important;}
.mg-market-label,.mg-ticker-change.flat{color:#fff!important;}
.mg-api-flow-dot{background:rgba(255,255,255,.1)!important;border-color:rgba(255,255,255,.3)!important;color:#fff!important;}
.mg-mini-ui img{filter:grayscale(1) contrast(1.06) brightness(.96);}
</style>
<script>
(function(){
  function setText(selector,value){var el=document.querySelector(selector);if(el){el.textContent=value;}}
  setText('.mg-hero-copy .mg-eyebrow','The easiest way to');
  setText('#mgHeroTitle','Pre-purchase products and invest in your local community.');
  setText('.mg-hero-copy .mg-lede','Microgifter helps local businesses pre-sell products, launch reward campaigns, and manage customer demand from one simple platform.');
  var note=document.querySelector('.mg-future-demand-note');
  if(note){note.innerHTML='<strong>Rewards layer for local commerce:</strong> sell prepaid offers, distribute them anywhere, and track every claim, redemption, customer, and campaign through a connected rewards CRM.';}
  var imgs=document.querySelectorAll('img');
  imgs.forEach(function(img){var src=img.getAttribute('src')||'';if(src.indexOf('/images/')===0){img.classList.remove('mg-image-missing');img.setAttribute('src','images/'+src.substring(8));}});
})();
</script>
