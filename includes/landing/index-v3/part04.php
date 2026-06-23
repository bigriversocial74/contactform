/* V3 story polish: tighter UI, smaller type, smaller hero device, richer narrative sections. */
:root{
  --mg-max:1280px;
}
.mg-container{width:min(var(--mg-max),calc(100% - 72px));}
.mg-nav{width:min(var(--mg-max),calc(100% - 72px));height:54px;}
.mg-logo-text{font-size:12px;letter-spacing:.18em;}
.mg-nav-links{gap:24px;}
.mg-nav-links a{font-size:11px;}
.mg-header-cta{min-height:34px;padding:0 14px;font-size:11px;border-radius:9px;}
.mg-section{padding:118px 0;min-height:auto;}
.mg-hero{min-height:calc(100svh - 54px);padding:88px 0 58px;}
.mg-hero-grid{grid-template-columns:minmax(0,.98fr) minmax(390px,.92fr);gap:42px;}
.mg-eyebrow{font-size:10px;letter-spacing:.32em;margin-bottom:18px;}
.mg-eyebrow::before{width:10px;height:10px;border-width:1.5px;}
.mg-title{max-width:760px;font-size:clamp(42px,5.2vw,84px);line-height:.95;}
.mg-section-title{font-size:clamp(36px,4.4vw,68px);line-height:.98;max-width:1040px;}
.mg-lede,.mg-section-copy{font-size:clamp(15px,1.05vw,18px);line-height:1.58;max-width:650px;margin-top:20px;}
.mg-actions{margin-top:28px;gap:14px;}
.mg-btn{min-height:50px;padding:0 22px;border-radius:11px;font-size:13px;gap:13px;}
.mg-hero-microcopy{margin-top:36px;gap:18px;}
.mg-metric{padding-top:14px;}
.mg-metric strong{font-size:13px;}
.mg-metric span{font-size:11px;}
.mg-hero-visual{min-height:590px;}
.mg-visual-stage{height:590px;width:min(575px,100%);}
.mg-phone-orbit{inset:42px 146px 78px 128px;}
.mg-phone{border-radius:36px;padding:10px;}
.mg-phone-screen{border-radius:28px;}
.mg-device-base{width:350px;height:68px;bottom:42px;}
.mg-carousel-dots{bottom:12px;transform:translateX(-50%) scale(.85);}
.mg-mesh-ribbon{inset:28px -12px 46px 0;opacity:.45;}
.mg-location-marker{width:28px;height:38px;}
.mg-location-marker::before{width:58px;height:58px;}
.mg-location-marker.marker-a{left:78px;top:156px;}
.mg-location-marker.marker-b{right:52px;top:116px;}
.mg-location-marker.marker-c{left:112px;bottom:126px;}
.mg-location-marker.marker-d{right:110px;bottom:168px;}
.mg-agent-orbit{position:absolute;z-index:1;left:50%;top:49%;width:520px;height:520px;border:1px solid rgba(217,167,53,.13);border-radius:999px;transform:translate(-50%,-50%);pointer-events:none;animation:mgOrbitSpin 28s linear infinite;}
.mg-agent-orbit::before,.mg-agent-orbit::after{content:"";position:absolute;border-radius:999px;border:1px solid rgba(217,167,53,.1);}
.mg-agent-orbit::before{inset:58px;}
.mg-agent-orbit::after{inset:114px;}
.mg-agent-node{position:absolute;width:9px;height:9px;border-radius:999px;background:#f1c15a;box-shadow:0 0 18px rgba(241,193,90,.75);}
.mg-agent-node:nth-child(1){left:50%;top:-5px;}
.mg-agent-node:nth-child(2){right:34px;top:128px;}
.mg-agent-node:nth-child(3){left:54px;bottom:94px;}
@keyframes mgOrbitSpin{to{transform:translate(-50%,-50%) rotate(360deg);}}
.mg-signal-card{position:absolute;z-index:4;width:172px;padding:14px;border:1px solid rgba(217,167,53,.28);border-radius:16px;background:linear-gradient(145deg,rgba(7,7,7,.92),rgba(20,17,10,.74));box-shadow:0 24px 70px rgba(0,0,0,.42);backdrop-filter:blur(12px);}
.mg-signal-card small{display:block;color:#d9a735;font-size:9px;font-weight:850;letter-spacing:.2em;text-transform:uppercase;}
.mg-signal-card strong{display:block;margin-top:7px;color:#fff;font-size:14px;letter-spacing:-.03em;}
.mg-signal-card span{display:block;margin-top:6px;color:#aaa49a;font-size:11px;line-height:1.35;}
.mg-signal-agent{left:4px;top:70px;}
.mg-signal-wallet{right:0;bottom:112px;}
.mg-section-head{margin-bottom:38px;}
.mg-card-grid{gap:18px;}
.mg-feature-card,.mg-system-card{padding:26px;border-radius:16px;min-height:220px;}
.mg-feature-card h3{font-size:18px;}
.mg-feature-card p,.mg-system-card p{font-size:13px;}
.mg-system-grid{gap:18px;}
.mg-system-card{min-height:430px;}
.mg-system-card h3{font-size:23px;}
.mg-ui-shell img{height:250px;}
.mg-code-panel{min-height:250px;font-size:12px;}
.mg-story-band{position:relative;overflow:hidden;background:#050505;}
.mg-story-band::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 20% 12%,rgba(217,167,53,.13),transparent 34%),radial-gradient(circle at 84% 48%,rgba(255,255,255,.05),transparent 30%);}
.mg-story-grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:54px;align-items:start;}
.mg-story-kicker{display:inline-flex;align-items:center;gap:10px;color:#d9a735;font-size:10px;font-weight:850;letter-spacing:.3em;text-transform:uppercase;margin-bottom:18px;}
.mg-story-kicker::before{content:"";width:10px;height:10px;border-radius:999px;background:#d9a735;box-shadow:0 0 20px rgba(217,167,53,.55);}
.mg-story-copy{position:sticky;top:92px;}
.mg-story-copy h2{margin:0;color:#fff;font-size:clamp(34px,4vw,62px);line-height:.98;letter-spacing:-.06em;}
.mg-story-copy p{margin:20px 0 0;color:#c4bfb6;font-size:16px;line-height:1.62;max-width:560px;}
.mg-story-list{display:grid;gap:14px;}
.mg-story-step{display:grid;grid-template-columns:52px 1fr;gap:18px;padding:24px;border:1px solid rgba(217,167,53,.24);border-radius:18px;background:linear-gradient(145deg,rgba(255,255,255,.035),rgba(255,255,255,.012));box-shadow:0 0 0 1px rgba(255,255,255,.025) inset;}
.mg-story-step b{width:42px;height:42px;display:grid;place-items:center;border:1px solid rgba(217,167,53,.45);border-radius:13px;color:#d9a735;font-size:12px;}
.mg-story-step h3{margin:0;color:#fff;font-size:20px;letter-spacing:-.04em;}
.mg-story-step p{margin:9px 0 0;color:#bbb5aa;font-size:14px;line-height:1.55;}
.mg-agentic-panel{display:grid;grid-template-columns:1.05fr .95fr;gap:24px;align-items:stretch;margin-top:48px;}
.mg-agentic-card{padding:28px;border:1px solid rgba(217,167,53,.25);border-radius:22px;background:linear-gradient(145deg,rgba(12,12,11,.9),rgba(0,0,0,.68));}
.mg-agentic-card h3{margin:0;color:#fff;font-size:26px;letter-spacing:-.05em;}
.mg-agentic-card p{margin:16px 0 0;color:#c3beb4;font-size:15px;line-height:1.62;}
.mg-agent-flow{display:grid;gap:12px;}
.mg-flow-item{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border:1px solid rgba(255,255,255,.08);border-radius:15px;background:rgba(255,255,255,.025);font-size:13px;color:#f4f1e8;font-weight:750;}
.mg-flow-item span:last-child{color:#d9a735;}
.mg-examples-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:40px;}
.mg-example-card{position:relative;overflow:hidden;min-height:230px;padding:22px;border:1px solid rgba(217,167,53,.23);border-radius:18px;background:linear-gradient(160deg,rgba(255,255,255,.04),rgba(255,255,255,.012));}
.mg-example-card::after{content:"";position:absolute;right:-36px;bottom:-36px;width:120px;height:120px;border-radius:999px;border:1px solid rgba(217,167,53,.15);}
.mg-example-card small{display:block;color:#d9a735;font-size:10px;font-weight:850;letter-spacing:.22em;text-transform:uppercase;}
.mg-example-card h3{margin:18px 0 0;color:#fff;font-size:20px;letter-spacing:-.04em;}
.mg-example-card p{margin:10px 0 18px;color:#bdb7ad;font-size:13px;line-height:1.45;}
.mg-example-meta{display:grid;gap:8px;margin-top:auto;}
.mg-example-meta div{display:flex;justify-content:space-between;gap:14px;padding-top:8px;border-top:1px solid rgba(255,255,255,.07);font-size:12px;color:#aaa49a;}
.mg-example-meta strong{color:#fff;}
.mg-api-story{display:grid;grid-template-columns:.95fr 1.05fr;gap:24px;align-items:stretch;margin-top:38px;}
.mg-api-flow{padding:28px;border:1px solid rgba(217,167,53,.25);border-radius:22px;background:rgba(255,255,255,.025);}
.mg-api-flow h3{margin:0 0 18px;color:#fff;font-size:24px;letter-spacing:-.045em;}
.mg-api-flow-row{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;margin-top:12px;}
.mg-api-flow-dot{width:34px;height:34px;display:grid;place-items:center;border-radius:12px;background:rgba(217,167,53,.12);border:1px solid rgba(217,167,53,.32);color:#d9a735;font-size:11px;font-weight:900;}
.mg-api-flow-row p{margin:0;color:#c3beb4;font-size:14px;line-height:1.45;}
.mg-social-proof{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-top:34px;}
.mg-proof-pill{min-height:78px;display:flex;align-items:center;justify-content:center;text-align:center;padding:14px;border:1px solid rgba(217,167,53,.18);border-radius:16px;background:rgba(255,255,255,.025);color:#e9e4d8;font-size:12px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;}
.mg-public-bottom-demo{position:relative;isolation:isolate;overflow:hidden;padding:104px 20px;background:#050505;color:#fff;border-top:1px solid rgba(217,167,53,.24);}
.mg-public-bottom-demo::before{content:"";position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 18% 18%,rgba(217,167,53,.16),transparent 34%),radial-gradient(circle at 82% 60%,rgba(217,167,53,.11),transparent 36%);}
.mg-public-bottom-demo-inner{position:relative;z-index:1;width:min(920px,100%);margin:0 auto;text-align:center;}
.mg-public-bottom-demo-eyebrow{display:inline-flex;align-items:center;gap:10px;margin-bottom:18px;color:#d9a735;font-size:10px;font-weight:850;letter-spacing:.28em;text-transform:uppercase;}
.mg-public-bottom-demo h2{margin:0;color:#fff;font-size:clamp(36px,5vw,68px);line-height:.98;letter-spacing:-.06em;font-weight:850;}
.mg-public-bottom-demo p{margin:22px auto 0;max-width:660px;color:#c9c4ba;font-size:17px;line-height:1.6;}
.mg-public-bottom-demo-actions{display:flex;justify-content:center;flex-wrap:wrap;gap:14px;margin-top:32px;}
.mg-public-bottom-demo-actions a{min-height:52px;display:inline-flex;align-items:center;justify-content:center;padding:0 22px;border-radius:12px;text-decoration:none;font-size:13px;font-weight:850;}
.mg-public-bottom-demo-primary{background:#fff;color:#050505;}
.mg-public-bottom-demo-secondary{border:1px solid rgba(217,167,53,.46);color:#fff;background:rgba(255,255,255,.03);}
.mg-footer{padding-top:98px;}
.mg-footer-brand p{font-size:17px;}
.mg-footer-column a{font-size:15px;padding:13px 0;}
@media(max-width:1180px){
  .mg-story-grid,.mg-agentic-panel,.mg-api-story{grid-template-columns:1fr;}
  .mg-story-copy{position:relative;top:auto;}
  .mg-examples-grid{grid-template-columns:repeat(2,1fr);}
  .mg-social-proof{grid-template-columns:repeat(3,1fr);}
}
@media(max-width:780px){
  .mg-container{width:min(100% - 32px,680px);}
  .mg-hero-grid{display:flex!important;flex-direction:column!important;}
  .mg-hero-visual{order:1!important;min-height:470px;margin-top:0;}
  .mg-hero-copy{order:2!important;}
  .mg-visual-stage{height:470px;}
  .mg-phone-orbit{inset:18px 92px 70px 82px;}
  .mg-agent-orbit{width:420px;height:420px;}
  .mg-signal-card{width:145px;padding:12px;}
  .mg-signal-agent{left:0;top:48px;}
  .mg-signal-wallet{right:0;bottom:96px;}
  .mg-title{font-size:clamp(40px,12vw,64px);}
  .mg-section{padding:84px 0;}
  .mg-hero{padding:24px 0 78px;}
  .mg-story-step{grid-template-columns:1fr;gap:14px;}
  .mg-examples-grid,.mg-social-proof{grid-template-columns:1fr;}
  .mg-agentic-card,.mg-api-flow{padding:22px;}
  .mg-api-story .mg-code-panel{min-height:0;}
}
@media(max-width:520px){
  .mg-phone-orbit{inset:24px 64px 82px 54px;}
  .mg-agent-orbit{width:360px;height:360px;}
  .mg-signal-card{display:none;}
  .mg-device-base{width:280px;}
  .mg-location-marker.marker-a{left:24px;top:112px;}
  .mg-location-marker.marker-b{right:18px;top:96px;}
  .mg-location-marker.marker-c{left:46px;bottom:118px;}
  .mg-location-marker.marker-d{right:46px;bottom:152px;}
}


