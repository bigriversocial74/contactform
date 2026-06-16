<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user = mg_current_user();
if ($user) {
    header('Location: /agent.php', true, 302);
    exit;
}

$csrfToken = mg_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= mg_e($csrfToken) ?>">
<title>Microgifter | Pre-Purchase Gifts</title>
<style>
:root{--bg:#f7f8fb;--panel:#fff;--panel-soft:#f4f7fb;--text:#050505;--muted:#64748b;--border:#e2e8f0;--green:#16a34a;--purple:#7c3aed;--orange:#f97316;--pink:#ec4899;--cyan:#06b6d4;--blue:#2563eb;--shadow:0 24px 70px rgba(15,23,42,.10);--soft-shadow:0 14px 34px rgba(15,23,42,.06);--header-h:76px}*{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}body{font-family:Inter,Arial,sans-serif;background:#fff;color:var(--text);line-height:1.5;overflow-x:hidden}a{color:inherit;text-decoration:none}button,input,select{font-family:inherit}.page{width:100%}.container{width:min(1180px,92%);margin:0 auto}.nav{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.9);backdrop-filter:blur(16px);border-bottom:1px solid rgba(226,232,240,.9)}.nav-inner{min-height:var(--header-h);display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:12px;font-weight:950;letter-spacing:-.04em;font-size:22px}.brand-mark{width:42px;height:42px;border-radius:14px;background:#fff;color:#050505;display:grid;place-items:center;border:1px solid #e5e7eb;box-shadow:0 10px 24px rgba(15,23,42,.08)}.brand-mark svg{width:23px;height:23px}.nav-links{display:flex;align-items:center;gap:20px;color:var(--muted);font-weight:800;font-size:14px}.nav-links a:hover{color:#050505}.nav-actions{display:flex;align-items:center;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:44px;padding:0 18px;border-radius:999px;border:1px solid transparent;cursor:pointer;font-weight:950;font-size:14px;transition:.22s ease;white-space:nowrap}.btn-primary{background:#050505;color:#fff;box-shadow:0 14px 28px rgba(15,23,42,.18)}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 18px 36px rgba(15,23,42,.22)}.btn-ghost{background:#fff;color:#050505;border-color:var(--border);box-shadow:inset 0 1px 0 rgba(255,255,255,.9)}.btn-ghost:hover{border-color:#cbd5e1;transform:translateY(-2px)}.badge{display:inline-flex;align-items:center;gap:9px;padding:8px 12px;border:1px solid var(--border);background:rgba(255,255,255,.88);color:#050505;border-radius:999px;font-weight:950;font-size:13px;box-shadow:0 10px 25px rgba(15,23,42,.04)}.pulse{width:9px;height:9px;border-radius:999px;background:#050505;box-shadow:0 0 0 7px rgba(15,23,42,.08)}.scroll-progress{margin-top:28px;width:min(360px,100%);height:8px;border-radius:999px;background:rgba(15,23,42,.09);overflow:hidden}.scroll-progress span{display:block;width:0%;height:100%;border-radius:999px;background:#050505;transition:width .12s linear}.mesh-section{position:relative;overflow:visible}.mesh-section:before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.86;background:linear-gradient(90deg,rgba(15,23,42,.04) 1px,transparent 1px),linear-gradient(0deg,rgba(15,23,42,.04) 1px,transparent 1px),radial-gradient(circle at 20% 18%,rgba(255,255,255,.92),transparent 30%),radial-gradient(circle at 80% 20%,rgba(255,255,255,.62),transparent 32%);background-size:72px 72px,72px 72px,auto,auto;z-index:0}.mesh-section:after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.42;background:linear-gradient(115deg,transparent 0%,transparent 36%,rgba(255,255,255,.72) 37%,transparent 49%),radial-gradient(circle at 30% 80%,rgba(15,23,42,.04),transparent 28%);z-index:0}.hero{min-height:380vh;background:radial-gradient(circle at 18% 18%,rgba(255,255,255,.95),transparent 28%),radial-gradient(circle at 80% 12%,rgba(248,250,252,.95),transparent 30%),linear-gradient(135deg,#fff 0%,#f8fafc 45%,#eef2f7 100%)}.hero-pin{position:sticky;top:var(--header-h);height:calc(100svh - var(--header-h));min-height:650px;display:flex;align-items:center;overflow:hidden;z-index:2}.hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:48px;align-items:center;width:min(1180px,92%);margin:0 auto;position:relative;z-index:2;opacity:var(--hero-opacity,1);transform:translateY(calc((1 - var(--hero-opacity,1)) * -32px));transition:opacity .12s linear,transform .12s linear}.hero-copy{min-width:0;position:relative;min-height:420px;display:flex;align-items:center}.hero-copy-slide{position:absolute;inset:auto 0;opacity:0;transform:translateY(26px) scale(.99);filter:blur(4px);transition:opacity .75s ease,transform .85s cubic-bezier(.16,1,.3,1),filter .75s ease;pointer-events:none}.hero-copy-slide.is-active{opacity:1;transform:translateY(0) scale(1);filter:blur(0);pointer-events:auto}.hero-title{margin-top:20px;max-width:760px;font-size:clamp(40px,4.8vw,60px);line-height:.98;letter-spacing:-.072em;color:#050505}.hero-title span{display:block}.hero-text{margin-top:22px;max-width:660px;color:var(--muted);font-size:19px}.hero-phone-wrap{width:100%;display:flex;justify-content:center;align-items:center;position:relative;min-height:540px;perspective:1300px;transform-style:preserve-3d}.hero-phone-slide{--hero-local:.68;position:absolute;inset:0;display:flex;justify-content:center;align-items:center;opacity:0;transform:translateX(100px) translateY(26px) translateZ(-180px) scale(.78) rotateY(-20deg) rotateX(6deg);filter:blur(8px);transition:opacity .85s ease,transform .95s cubic-bezier(.16,1,.3,1),filter .85s ease;pointer-events:none;transform-style:preserve-3d}.hero-phone-slide.is-active{opacity:1;transform:translateX(calc((1 - var(--hero-local)) * 72px)) translateY(calc((1 - var(--hero-local)) * 20px)) translateZ(calc((1 - var(--hero-local)) * -130px)) scale(calc(.84 + (var(--hero-local) * .16))) rotateY(calc((1 - var(--hero-local)) * -17deg)) rotateX(calc((1 - var(--hero-local)) * 5deg));filter:blur(calc((1 - var(--hero-local)) * 5px));pointer-events:auto}.hero-phone-slide.is-before{opacity:0;transform:translateX(-100px) translateY(-22px) translateZ(-180px) scale(.82) rotateY(20deg) rotateX(-5deg);filter:blur(7px)}.iphone-frame{width:min(360px,92%);aspect-ratio:390/780;background:#fff;border:1px solid rgba(15,23,42,.08);border-radius:42px;padding:13px;position:relative;box-shadow:0 28px 70px rgba(15,23,42,.16),0 12px 28px rgba(15,23,42,.08),inset 0 0 0 1px rgba(255,255,255,.9);transition:transform .28s ease,box-shadow .28s ease;transform-origin:center}.iphone-frame:hover{transform:scale(1.055) rotate(-.5deg);box-shadow:0 38px 95px rgba(15,23,42,.22),0 18px 42px rgba(15,23,42,.12),inset 0 0 0 1px rgba(255,255,255,.95)}.iphone-screen{width:100%;height:100%;overflow:hidden;border-radius:32px;background:#f8fafc;border:1px solid rgba(15,23,42,.08);position:relative}.phone-ui{height:100%;padding:26px 18px 18px;background:linear-gradient(180deg,#fff,#eef2f7);display:flex;flex-direction:column;gap:14px}.phone-head{display:flex;justify-content:space-between;align-items:center}.phone-pill{padding:6px 10px;border-radius:999px;background:#050505;color:#fff;font-size:11px;font-weight:900}.phone-card{background:#fff;border:1px solid var(--border);border-radius:22px;padding:18px;box-shadow:var(--soft-shadow)}.phone-card h3{font-size:25px;line-height:1;letter-spacing:-.06em}.phone-card p{margin-top:8px;color:var(--muted);font-size:13px}.phone-img{height:190px;border-radius:24px;background:linear-gradient(135deg,var(--a),var(--b));display:grid;place-items:center;color:#fff;font-weight:1000;font-size:56px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.35)}.phone-list{display:grid;gap:9px}.phone-row{display:flex;align-items:center;justify-content:space-between;padding:12px 13px;border-radius:14px;background:#fff;border:1px solid var(--border);font-size:12px;font-weight:900}.iphone-speaker{position:absolute;top:19px;left:50%;transform:translateX(-50%);width:80px;height:23px;border-radius:0 0 18px 18px;background:#fff;z-index:3;box-shadow:0 1px 0 rgba(15,23,42,.06)}.iphone-speaker:before{content:"";position:absolute;top:8px;left:50%;transform:translateX(-50%);width:40px;height:5px;border-radius:999px;background:#0f172a;opacity:.16}.sticky{position:relative;min-height:440vh;border-top:1px solid var(--border);border-bottom:1px solid var(--border);overflow:visible}.sticky-pin{position:sticky;top:var(--header-h);height:calc(100svh - var(--header-h));min-height:700px;display:flex;align-items:center;overflow:hidden;z-index:2}.sticky-grid{width:min(1180px,92%);margin:0 auto;display:grid;grid-template-columns:.84fr 1.16fr;gap:48px;align-items:center;opacity:var(--section-opacity,1);transform:translateY(calc((1 - var(--section-opacity,1)) * -20px));transition:opacity .12s linear,transform .12s linear;position:relative;z-index:2}.sticky-copy{position:relative;min-height:390px;display:flex;align-items:center}.copy-slide{position:absolute;inset:auto 0;opacity:0;transform:translateY(26px);filter:blur(4px);transition:opacity .7s ease,transform .8s cubic-bezier(.16,1,.3,1),filter .7s ease;pointer-events:none}.copy-slide.is-active{opacity:1;transform:translateY(0);filter:blur(0);pointer-events:auto}.sticky-copy h2{margin-top:18px;font-size:clamp(36px,5vw,62px);line-height:.96;letter-spacing:-.075em}.sticky-copy p{margin-top:18px;max-width:540px;color:var(--muted);font-size:18px}.stage{position:relative;min-height:610px;perspective:1300px;transform-style:preserve-3d}.feature-card{position:absolute;inset:50% auto auto 50%;width:min(590px,100%);min-height:430px;padding:34px;border-radius:36px;border:1px solid rgba(226,232,240,.95);background:rgba(255,255,255,.95);box-shadow:0 34px 90px rgba(15,23,42,.14);backdrop-filter:blur(10px);opacity:0;transform:translate(-50%,-50%) translateX(92px) translateZ(-140px) scale(.84) rotateY(-17deg);filter:blur(6px);transition:opacity .75s ease,transform .9s cubic-bezier(.16,1,.3,1),filter .75s ease;pointer-events:none;overflow:hidden}.feature-card:before{content:"";position:absolute;inset:0 0 auto 0;height:8px;background:linear-gradient(90deg,var(--card-a),var(--card-b))}.feature-card.is-active{opacity:1;transform:translate(-50%,-50%) translateX(calc((1 - var(--card-local,1)) * 56px)) translateZ(calc((1 - var(--card-local,1)) * -90px)) scale(calc(.92 + (var(--card-local,1) * .08))) rotateY(calc((1 - var(--card-local,1)) * -10deg));filter:blur(calc((1 - var(--card-local,1)) * 3px));pointer-events:auto}.feature-card.is-before{opacity:0;transform:translate(-50%,-50%) translateX(-82px) translateZ(-120px) scale(.88) rotateY(14deg);filter:blur(5px)}.feature-icon{width:62px;height:62px;border-radius:22px;display:grid;place-items:center;color:#fff;font-weight:1000;font-size:20px;box-shadow:0 16px 32px rgba(15,23,42,.16);background:linear-gradient(135deg,var(--card-a),var(--card-b))}.feature-card h3{margin-top:26px;font-size:38px;line-height:1;letter-spacing:-.065em}.feature-card p{margin-top:14px;color:var(--muted);font-size:17px}.feature-list{margin-top:22px;display:grid;gap:10px;list-style:none}.feature-list li{padding:13px 15px;border-radius:16px;background:#f4f7fb;color:#334155;font-size:14px;font-weight:850}.meta-grid{margin-top:22px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.meta-grid div{padding:14px;border-radius:16px;background:#fff;border:1px solid var(--border)}.meta-grid strong{display:block;font-size:20px;letter-spacing:-.04em}.meta-grid span{display:block;margin-top:4px;color:var(--muted);font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}.contest{background:radial-gradient(circle at 18% 16%,rgba(255,255,255,.95),transparent 30%),radial-gradient(circle at 78% 18%,rgba(253,244,255,.74),transparent 34%),linear-gradient(180deg,#fff 0%,#f8fafc 48%,#eef2f7 100%)}.confetti{position:absolute;inset:0;pointer-events:none;overflow:hidden}.confetti span{position:absolute;top:-20px;width:9px;height:14px;border-radius:3px;background:var(--c);left:var(--x);animation:fall var(--d) linear infinite;animation-delay:var(--delay);transform:rotate(var(--r))}@keyframes fall{0%{transform:translateY(-20px) rotate(0deg);opacity:0}8%{opacity:1}100%{transform:translateY(760px) rotate(540deg);opacity:0}}.roi-section{padding:130px 0;background:linear-gradient(180deg,#fff,#f8fafc);border-top:1px solid var(--border);text-align:center}.roi-section .container{position:relative;z-index:2}.roi-section h2{font-size:clamp(40px,6vw,72px);line-height:.95;letter-spacing:-.075em}.roi-section p{margin:20px auto 0;max-width:700px;color:var(--muted);font-size:19px}.roi-grid{margin-top:40px;display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.roi-card{padding:28px;border:1px solid var(--border);border-radius:28px;background:#fff;box-shadow:var(--soft-shadow);text-align:left}.roi-card strong{font-size:34px;letter-spacing:-.06em}.roi-card span{display:block;margin-top:8px;color:var(--muted);font-weight:800}.footer{padding:46px 0;border-top:1px solid var(--border);background:#050505;color:#fff}.footer .container{display:flex;justify-content:space-between;align-items:center;gap:22px}.footer p{color:rgba(255,255,255,.64);font-size:14px}.mobile-menu-btn{display:none}@media(max-width:920px){:root{--header-h:68px}.nav-links{display:none}.mobile-menu-btn{display:inline-flex}.nav-actions .btn-ghost{display:none}.hero,.sticky{min-height:auto}.hero-pin,.sticky-pin{position:relative;top:auto;height:auto;min-height:auto;padding:64px 0}.hero-grid,.sticky-grid{grid-template-columns:1fr;gap:32px}.hero-copy,.sticky-copy{min-height:360px}.hero-phone-wrap,.stage{min-height:520px}.feature-card{width:100%;padding:26px}.meta-grid,.roi-grid{grid-template-columns:1fr}.footer .container{display:grid}.hero-title,.sticky-copy h2,.roi-section h2{letter-spacing:-.055em}}@media(max-width:640px){.nav-inner{min-height:64px}.brand span:last-child{font-size:18px}.brand-mark{width:38px;height:38px}.btn{min-height:40px;padding:0 14px;font-size:13px}.hero-copy{min-height:430px}.hero-title{font-size:40px}.hero-text,.sticky-copy p,.roi-section p{font-size:16px}.iphone-frame{width:min(310px,92%)}.feature-card{min-height:410px;border-radius:28px}.feature-card h3{font-size:31px}.sticky-copy{min-height:430px}.roi-section{padding:90px 0}}
</style>
</head>
<body>
<div class="page">
  <header class="nav">
    <div class="nav-inner container">
      <a class="brand" href="/index.php" aria-label="Microgifter home">
        <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4z" stroke="currentColor" stroke-width="2"/><path d="M8 12h8M12 8v8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
        <span>Microgifter</span>
      </a>
      <nav class="nav-links" aria-label="Primary navigation">
        <a href="#gifts">Gifts</a>
        <a href="#sales">Local Sales</a>
        <a href="#contest">Contest</a>
        <a href="#workplace">Workplace</a>
        <a href="#roi">ROI</a>
      </nav>
      <div class="nav-actions">
        <a class="btn btn-ghost" href="/signin.php">Sign in</a>
        <a class="btn btn-primary" href="/build.php">Start gifting</a>
      </div>
    </div>
  </header>

  <section class="hero mesh-section" data-sticky-section data-slides="3">
    <div class="hero-pin">
      <div class="hero-grid">
        <div class="hero-copy">
          <div class="hero-copy-slide is-active" data-slide="0">
            <div class="badge"><span class="pulse"></span> Local gifting automation</div>
            <h1 class="hero-title">Pre-Purchase Gifts <span>for Family, Friends & Co-workers.</span></h1>
            <p class="hero-text">Microgifter helps people plan meaningful local gifts ahead of time, schedule rewards, and deliver claimable digital-to-local experiences.</p>
            <div class="scroll-progress"><span></span></div>
          </div>
          <div class="hero-copy-slide" data-slide="1">
            <div class="badge"><span class="pulse"></span> Send, notify, verify</div>
            <h1 class="hero-title">The delivery layer <span>for phygital gifting.</span></h1>
            <p class="hero-text">Every gift needs a trackable delivery path: sent, opened, verified, claimed, fulfilled, and confirmed.</p>
            <div class="scroll-progress"><span></span></div>
          </div>
          <div class="hero-copy-slide" data-slide="2">
            <div class="badge"><span class="pulse"></span> Agent-managed gifting</div>
            <h1 class="hero-title">Launch a live agent <span>for local commerce.</span></h1>
            <p class="hero-text">Build a product, activate a gift flow, and use the agent workspace to manage delivery, inbox, and next actions.</p>
            <div class="scroll-progress"><span></span></div>
          </div>
        </div>
        <div class="hero-phone-wrap" aria-hidden="true">
          <div class="hero-phone-slide is-active" data-slide="0"><div class="iphone-frame"><div class="iphone-speaker"></div><div class="iphone-screen"><div class="phone-ui"><div class="phone-head"><strong>Micro Gift</strong><span class="phone-pill">Ready</span></div><div class="phone-img" style="--a:#16a34a;--b:#06b6d4">🎁</div><div class="phone-card"><h3>Birthday Coffee</h3><p>Send a prepaid local reward with a claim code and QR.</p></div><div class="phone-list"><div class="phone-row"><span>Notify</span><strong>Queued</strong></div><div class="phone-row"><span>Claim</span><strong>Verified</strong></div></div></div></div></div></div>
          <div class="hero-phone-slide" data-slide="1"><div class="iphone-frame"><div class="iphone-speaker"></div><div class="iphone-screen"><div class="phone-ui"><div class="phone-head"><strong>Delivery</strong><span class="phone-pill">Tracked</span></div><div class="phone-img" style="--a:#2563eb;--b:#7c3aed">✓</div><div class="phone-card"><h3>Gift confirmed</h3><p>Every action is recorded as a delivery event.</p></div><div class="phone-list"><div class="phone-row"><span>Opened</span><strong>Yes</strong></div><div class="phone-row"><span>Fulfilled</span><strong>Pending</strong></div></div></div></div></div></div>
          <div class="hero-phone-slide" data-slide="2"><div class="iphone-frame"><div class="iphone-speaker"></div><div class="iphone-screen"><div class="phone-ui"><div class="phone-head"><strong>Agent</strong><span class="phone-pill">Live</span></div><div class="phone-img" style="--a:#f97316;--b:#ec4899">AI</div><div class="phone-card"><h3>Workspace ready</h3><p>Manage product activation, inbox, and gift delivery.</p></div><div class="phone-list"><div class="phone-row"><span>Tasks</span><strong>4</strong></div><div class="phone-row"><span>Inbox</span><strong>On</strong></div></div></div></div></div></div>
        </div>
      </div>
    </div>
  </section>

  <section id="gifts" class="sticky mesh-section" data-sticky-section data-slides="3">
    <div class="sticky-pin"><div class="sticky-grid"><div class="sticky-copy">
      <div class="copy-slide is-active" data-slide="0"><div class="badge"><span class="pulse"></span> Gift categories</div><h2>Plan ahead without making gifting feel robotic.</h2><p>Turn birthdays, thank-yous, sales moments, and workplace rewards into prepaid local experiences.</p><div class="scroll-progress"><span></span></div></div>
      <div class="copy-slide" data-slide="1"><div class="badge"><span class="pulse"></span> Claimable value</div><h2>Every gift has a clear claim path.</h2><p>QR codes, claim codes, and status tracking keep the recipient and merchant in sync.</p><div class="scroll-progress"><span></span></div></div>
      <div class="copy-slide" data-slide="2"><div class="badge"><span class="pulse"></span> Local-first</div><h2>Built for local merchants and community commerce.</h2><p>Pre-purchased demand becomes today’s revenue and tomorrow’s foot traffic.</p><div class="scroll-progress"><span></span></div></div>
    </div><div class="stage">
      <article class="feature-card is-active" data-slide="0" style="--card-a:#16a34a;--card-b:#06b6d4"><div class="feature-icon">01</div><h3>Personal Gifts</h3><p>Send coffee, lunch, treats, services, and small local offers.</p><ul class="feature-list"><li>Scheduled gifts</li><li>Recurring rewards</li><li>Recipient claim links</li></ul><div class="meta-grid"><div><strong>Fast</strong><span>activation</span></div><div><strong>Local</strong><span>merchant</span></div><div><strong>QR</strong><span>claim</span></div></div></article>
      <article class="feature-card" data-slide="1" style="--card-a:#2563eb;--card-b:#7c3aed"><div class="feature-icon">02</div><h3>Verified Claims</h3><p>Prevent confusion with clear gift status and claim confirmation.</p><ul class="feature-list"><li>Claim code input</li><li>Unique QR per voucher</li><li>Delivery event history</li></ul><div class="meta-grid"><div><strong>Track</strong><span>status</span></div><div><strong>Verify</strong><span>claim</span></div><div><strong>Audit</strong><span>history</span></div></div></article>
      <article class="feature-card" data-slide="2" style="--card-a:#f97316;--card-b:#ec4899"><div class="feature-icon">03</div><h3>Agent Support</h3><p>The live agent keeps gifting steps, inbox, and delivery actions organized.</p><ul class="feature-list"><li>Activation checklist</li><li>Inbox visibility after account</li><li>Product preview</li></ul><div class="meta-grid"><div><strong>Build</strong><span>product</span></div><div><strong>Send</strong><span>gift</span></div><div><strong>Manage</strong><span>agent</span></div></div></article>
    </div></div></div>
  </section>

  <section id="sales" class="sticky mesh-section" data-sticky-section data-slides="3"><div class="sticky-pin"><div class="sticky-grid"><div class="sticky-copy">
    <div class="copy-slide is-active" data-slide="0"><div class="badge"><span class="pulse"></span> Local sales</div><h2>Turn future demand into present-day revenue.</h2><p>Merchants can package giftable offers and receive demand before the recipient walks in.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="1"><div class="badge"><span class="pulse"></span> Pre-sales engine</div><h2>Sell moments before they happen.</h2><p>Birthdays, employee rewards, holidays, contests, and thank-yous become revenue opportunities.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="2"><div class="badge"><span class="pulse"></span> Merchant dashboard</div><h2>Keep fulfillment simple.</h2><p>Voucher status, claim confirmation, and inbox activity create a clean operating loop.</p><div class="scroll-progress"><span></span></div></div>
  </div><div class="stage">
    <article class="feature-card is-active" data-slide="0" style="--card-a:#16a34a;--card-b:#22c55e"><div class="feature-icon">$</div><h3>Prepaid Offers</h3><p>Create products that can be purchased now and redeemed later.</p><div class="meta-grid"><div><strong>$25</strong><span>avg gift</span></div><div><strong>Now</strong><span>revenue</span></div><div><strong>Later</strong><span>visit</span></div></div></article>
    <article class="feature-card" data-slide="1" style="--card-a:#2563eb;--card-b:#06b6d4"><div class="feature-icon">↗</div><h3>Growth Loops</h3><p>Recipients become visitors. Visitors become buyers. Buyers become senders.</p><ul class="feature-list"><li>Recipient discovery</li><li>Merchant profile/store page</li><li>Repeat gifting flows</li></ul></article>
    <article class="feature-card" data-slide="2" style="--card-a:#7c3aed;--card-b:#ec4899"><div class="feature-icon">✓</div><h3>Fulfillment</h3><p>Track the handoff from buyer to recipient to merchant confirmation.</p><ul class="feature-list"><li>Sent</li><li>Opened</li><li>Claimed</li><li>Fulfilled</li></ul></article>
  </div></div></div></section>

  <section id="contest" class="sticky mesh-section contest" data-sticky-section data-slides="3"><div class="confetti" aria-hidden="true"></div><div class="sticky-pin"><div class="sticky-grid"><div class="sticky-copy">
    <div class="copy-slide is-active" data-slide="0"><div class="badge"><span class="pulse"></span> Contest gifting</div><h2>Make rewards feel like a moment.</h2><p>Launch promotions with real prizes, real claims, and visible delivery tracking.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="1"><div class="badge"><span class="pulse"></span> Reward mechanics</div><h2>Confetti is fun. Proof is better.</h2><p>Every reward needs a durable voucher, claim code, and confirmation status behind it.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="2"><div class="badge"><span class="pulse"></span> Community reach</div><h2>Turn promotions into local traffic.</h2><p>Campaigns can connect sponsors, merchants, workplaces, and recipients.</p><div class="scroll-progress"><span></span></div></div>
  </div><div class="stage">
    <article class="feature-card is-active" data-slide="0" style="--card-a:#f97316;--card-b:#ec4899"><div class="feature-icon">🏆</div><h3>Prize Drops</h3><p>Celebrate rewards without losing the operational trail.</p><div class="meta-grid"><div><strong>QR</strong><span>unique</span></div><div><strong>Code</strong><span>claim</span></div><div><strong>Event</strong><span>tracked</span></div></div></article>
    <article class="feature-card" data-slide="1" style="--card-a:#7c3aed;--card-b:#ec4899"><div class="feature-icon">🎉</div><h3>Contest Flow</h3><p>Gift cards, vouchers, discounts, and local rewards can all share one claim system.</p><ul class="feature-list"><li>Enter</li><li>Win</li><li>Claim</li><li>Redeem</li></ul></article>
    <article class="feature-card" data-slide="2" style="--card-a:#06b6d4;--card-b:#2563eb"><div class="feature-icon">📍</div><h3>Local Impact</h3><p>Rewards send attention back to merchants and communities.</p><div class="meta-grid"><div><strong>Foot</strong><span>traffic</span></div><div><strong>New</strong><span>customers</span></div><div><strong>Data</strong><span>events</span></div></div></article>
  </div></div></div></section>

  <section id="workplace" class="sticky mesh-section" data-sticky-section data-slides="3"><div class="sticky-pin"><div class="sticky-grid"><div class="sticky-copy">
    <div class="copy-slide is-active" data-slide="0"><div class="badge"><span class="pulse"></span> Workplace rewards</div><h2>Reward teams with local value.</h2><p>Companies can fund recurring recognition while local businesses fulfill the rewards.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="1"><div class="badge"><span class="pulse"></span> Programs</div><h2>Build repeatable gifting programs.</h2><p>Birthdays, milestones, referrals, sales contests, and wellness rewards become program templates.</p><div class="scroll-progress"><span></span></div></div>
    <div class="copy-slide" data-slide="2"><div class="badge"><span class="pulse"></span> Agent-managed</div><h2>The workspace keeps every step visible.</h2><p>Agent actions, inbox messages, and product activation steps stay in one operating view.</p><div class="scroll-progress"><span></span></div></div>
  </div><div class="stage">
    <article class="feature-card is-active" data-slide="0" style="--card-a:#2563eb;--card-b:#7c3aed"><div class="feature-icon">HR</div><h3>Team Rewards</h3><p>Distribute prepaid local gifts to employees and partners.</p><div class="meta-grid"><div><strong>Plan</strong><span>events</span></div><div><strong>Fund</strong><span>program</span></div><div><strong>Track</strong><span>claims</span></div></div></article>
    <article class="feature-card" data-slide="1" style="--card-a:#16a34a;--card-b:#06b6d4"><div class="feature-icon">∞</div><h3>Recurring Programs</h3><p>Automate the repeat moments without losing personal context.</p><ul class="feature-list"><li>Birthdays</li><li>Milestones</li><li>Sales goals</li><li>Thank-yous</li></ul></article>
    <article class="feature-card" data-slide="2" style="--card-a:#f97316;--card-b:#ec4899"><div class="feature-icon">AI</div><h3>Agent Workspace</h3><p>Manage activation, delivery, inbox, and follow-up from the live agent page.</p><ul class="feature-list"><li>Create product</li><li>Publish gift</li><li>Track claim</li><li>Confirm fulfillment</li></ul></article>
  </div></div></div></section>

  <section id="roi" class="roi-section mesh-section"><div class="container"><div class="badge"><span class="pulse"></span> ROI engine</div><h2>Microgifter connects future intent to present commerce.</h2><p>The platform is being built as the delivery system for instant phygital gifting: send, notify, verify, track, confirm, audit, and recover.</p><div class="roi-grid"><div class="roi-card"><strong>Send</strong><span>Create a giftable offer and send it through a trackable flow.</span></div><div class="roi-card"><strong>Verify</strong><span>Use QR codes, claim codes, and durable state before fulfillment.</span></div><div class="roi-card"><strong>Confirm</strong><span>Record delivery events so buyers, recipients, and merchants know what happened.</span></div></div><div style="margin-top:38px"><a class="btn btn-primary" href="/build.php">Create a gift</a> <a class="btn btn-ghost" href="/signup.php">Create account</a></div></div></section>

  <footer class="footer"><div class="container"><div><strong>Microgifter</strong><p>Pre-purchase gifts, local rewards, and agent-assisted gifting.</p></div><div><a class="btn btn-ghost" href="/signin.php">Sign in</a></div></div></footer>
</div>
<script>
window.MicrogifterServerContext={authenticated:false,user:null,urls:{home:'/index.php',build:'/build.php',agent:'/agent.php',signin:'/signin.php',signup:'/signup.php',account:'/account.php'}};
(function(){
  var sections=[].slice.call(document.querySelectorAll('[data-sticky-section]'));
  function clamp(v){return Math.max(0,Math.min(1,v));}
  function updateSection(section){
    var rect=section.getBoundingClientRect();
    var total=Math.max(1,rect.height-window.innerHeight);
    var progress=clamp((0-rect.top)/total);
    var count=parseInt(section.getAttribute('data-slides')||'1',10);
    var exact=progress*count;
    var index=Math.min(count-1,Math.floor(exact));
    var local=clamp(exact-index);
    section.style.setProperty('--section-opacity',progress>.94?1-clamp((progress-.94)/.06):1);
    if(section.classList.contains('hero')){
      section.style.setProperty('--hero-opacity',progress>.94?1-clamp((progress-.94)/.06):1);
      section.style.setProperty('--hero-local',local.toFixed(3));
    }
    section.querySelectorAll('[data-slide]').forEach(function(el){
      var i=parseInt(el.getAttribute('data-slide'),10);
      el.classList.toggle('is-active',i===index);
      el.classList.toggle('is-before',i<index);
      el.style.setProperty('--card-local',local.toFixed(3));
      el.style.setProperty('--hero-local',local.toFixed(3));
    });
    section.querySelectorAll('.scroll-progress span').forEach(function(span){span.style.width=(progress*100).toFixed(1)+'%';});
  }
  function update(){sections.forEach(updateSection);}
  window.addEventListener('scroll',update,{passive:true});
  window.addEventListener('resize',update);update();
  var confetti=document.querySelector('.confetti');
  if(confetti){var colors=['#f97316','#ec4899','#7c3aed','#06b6d4','#16a34a','#facc15'];for(var i=0;i<60;i++){var s=document.createElement('span');s.style.setProperty('--x',(Math.random()*100)+'%');s.style.setProperty('--c',colors[i%colors.length]);s.style.setProperty('--d',(4+Math.random()*4)+'s');s.style.setProperty('--delay',(-Math.random()*6)+'s');s.style.setProperty('--r',(Math.random()*360)+'deg');confetti.appendChild(s);}}
})();
</script>
<script src="/assets/js/auth-state.js" defer></script>
</body>
</html>
