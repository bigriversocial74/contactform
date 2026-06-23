.mg-footer-col h3{margin:8px 0 34px;color:var(--mg-gold);font-size:13px;letter-spacing:.42em;text-transform:uppercase;font-weight:800;}
.mg-footer-col h3::after{content:"";display:block;width:40px;height:2px;margin-top:20px;background:var(--mg-gold);}
.mg-footer-col nav{display:grid;gap:0;}
.mg-footer-col a{display:block;padding:18px 0;border-bottom:1px solid rgba(255,255,255,.07);color:#f2eee6;text-decoration:none;font-size:20px;transition:color .2s ease,transform .2s ease;}
.mg-footer-col a:hover{color:var(--mg-gold);transform:translateX(4px);}
.mg-footer-bottom{display:flex;justify-content:space-between;gap:30px;align-items:center;margin-top:92px;padding-top:46px;border-top:1px solid rgba(217,167,53,.45);color:#bbb4a7;font-size:15px;}
.mg-footer-links{display:flex;gap:24px;align-items:center;flex-wrap:wrap;}
.mg-footer-links a{color:#f5efe5;text-decoration:none;}.mg-dot{color:var(--mg-gold);}

/* Reveal safety: content is visible by default. JS only enhances it. */
[data-reveal]{opacity:1;transform:none;filter:none;}
.mg-js [data-reveal]{opacity:0;transform:translateY(42px);filter:blur(8px);transition:opacity .85s cubic-bezier(.16,1,.3,1),transform .85s cubic-bezier(.16,1,.3,1),filter .85s cubic-bezier(.16,1,.3,1);transition-delay:var(--delay,0ms);}
.mg-js [data-reveal].is-visible{opacity:1;transform:none;filter:blur(0);}
.mg-js [data-reveal="left"]{transform:translateX(-42px);}
.mg-js [data-reveal="right"]{transform:translateX(42px);}
.mg-js [data-reveal="scale"]{transform:scale(.94);}
.mg-js [data-reveal="left"].is-visible,.mg-js [data-reveal="right"].is-visible,.mg-js [data-reveal="scale"].is-visible{transform:none;}
.mg-image-missing{display:none!important;}
.mg-missing-note{display:grid;place-items:center;min-height:140px;padding:18px;border:1px dashed rgba(217,167,53,.42);border-radius:22px;color:rgba(247,247,242,.82);font-size:12px;line-height:1.45;text-align:center;background:rgba(217,167,53,.05);word-break:break-word;}
.mg-phone-screen .mg-missing-note{position:absolute;inset:10px;border-radius:28px;min-height:0;}


.mg-progress{
  position:fixed;
  z-index:90;
  left:0;
  top:0;
  width:100%;
  height:3px;
  background:rgba(255,255,255,.04);
}
.mg-progress-bar{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--mg-gold),#fff0a7);box-shadow:0 0 20px rgba(217,167,53,.5);}

@media(max-width:1100px){
  .mg-container,.mg-nav{width:min(100% - 40px,var(--mg-max));}
  .mg-nav-links{gap:22px;}
  .mg-hero-grid{grid-template-columns:1fr;gap:40px;}
  .mg-hero-visual{min-height:650px;}
  .mg-visual-stage{height:650px;width:min(640px,100%);margin:0 auto;}
  .mg-cards-grid{grid-template-columns:repeat(2,1fr);}
  .mg-feature-panels{grid-template-columns:1fr;}
  .mg-panel{min-height:auto;}
  .mg-split-intro{grid-template-columns:1fr;}
  .mg-footer-grid{grid-template-columns:1fr 1fr;}
  .mg-footer-brand{grid-column:1/-1;}
}
@media(max-width:760px){
  .mg-site-header{position:sticky;}
  .mg-nav{height:72px;}
  .mg-logo-text{font-size:14px;letter-spacing:.18em;}
  .mg-logo-mark{width:34px;height:34px;}
  .mg-nav-links{display:none;}
  .mg-header-cta{min-height:40px;padding:0 13px;font-size:12px;}
  .mg-section{padding:92px 0;min-height:auto;}
  .mg-hero{padding:54px 0 80px;}
  .mg-title{font-size:clamp(46px,14vw,72px);letter-spacing:-.065em;}
  .mg-section-title{font-size:clamp(42px,12vw,64px);}
  .mg-lede{font-size:17px;}
  .mg-actions{display:grid;grid-template-columns:1fr;gap:12px;}
  .mg-btn{width:100%;min-height:54px;}
  .mg-hero-microcopy{grid-template-columns:1fr;}
  .mg-hero-visual{min-height:560px;margin-top:20px;}
  .mg-visual-stage{height:560px;}
  .mg-phone-orbit{inset:34px 70px 52px 88px;}
  .mg-float-card{display:none;}
  .mg-device-base{width:300px;bottom:18px;}
  .mg-cards-grid{grid-template-columns:1fr;gap:16px;}
  .mg-card{min-height:230px;}
  .mg-panel{padding:22px;}
  .mg-panel-head{grid-template-columns:46px 1fr;}
  .mg-panel-icon{width:46px;height:46px;}
  .mg-mini-ui img{height:260px;}
  .mg-footer{padding:90px 0 48px;}
  .mg-footer-grid{grid-template-columns:1fr;gap:44px;}
  .mg-footer-bottom{display:grid;margin-top:56px;}
  .mg-footer-col a{font-size:17px;}
}
@media(max-width:460px){
  .mg-container,.mg-nav{width:calc(100% - 28px);}
  .mg-eyebrow{font-size:10px;letter-spacing:.22em;}
  .mg-phone-orbit{inset:40px 48px 64px 54px;}
  .mg-hero-visual{min-height:520px;}
  .mg-visual-stage{height:520px;}
  
.mg-location-marker{
  position:absolute;
  z-index:2;
  width:34px;
  height:44px;
  color:var(--mg-gold);
  opacity:.72;
  filter:drop-shadow(0 0 18px rgba(217,167,53,.38));
  animation:mgMarkerFloat 7s ease-in-out infinite;
}
.mg-location-marker::before{
  content:"";
  position:absolute;
  left:50%;
  top:50%;
  width:76px;
  height:76px;
  border-radius:999px;
  border:1px solid rgba(217,167,53,.16);
  transform:translate(-50%,-35%) scale(.7);
  opacity:.55;
}
.mg-location-marker svg{display:block;width:100%;height:100%;}
.mg-location-marker.marker-a{left:72px;top:188px;animation-delay:-1.2s;}
.mg-location-marker.marker-b{right:48px;top:144px;transform:scale(.78);animation-delay:-3.1s;}
.mg-location-marker.marker-c{left:118px;bottom:128px;transform:scale(.66);animation-delay:-4.4s;}
.mg-location-marker.marker-d{right:112px;bottom:180px;transform:scale(.58);animation-delay:-2.2s;}
@keyframes mgMarkerFloat{
  0%,100%{translate:0 0;opacity:.54;}
  50%{translate:0 -14px;opacity:.9;}
}
.mg-mesh-ribbon{
  position:absolute;
  z-index:0;
  inset:60px -30px 20px 20px;
  opacity:.54;
  background:url('/images/cosmic_golden_network_on_black.png') center/cover no-repeat;
  mix-blend-mode:screen;
  mask-image:radial-gradient(circle at 62% 48%,#000 0 44%,transparent 76%);
  pointer-events:none;
}

.mg-carousel-dots{bottom:38px;}
}
@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important;}
  [data-reveal]{opacity:1;transform:none;filter:none;}
}

/* Phone camera notch removed so it does not block app screens. */

/* Mobile hero order: show visual first, then text. */
@media(max-width:780px){
  .mg-hero-grid{
    display:flex!important;
    flex-direction:column!important;
  }
  .mg-hero-visual{
    order:1!important;
  }
  .mg-hero-copy{
    order:2!important;
  }
}


