<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Retail Subscriptions | Microgifter';
$page_section='public';
$header_mode='public';
$page_styles=['/assets/css/public-header-footer-fixes.css'];
$page_manifest=[
  'id'=>'retail',
  'title'=>$page_title,
  'section'=>$page_section,
  'header_mode'=>$header_mode,
  'styles'=>$page_styles,
  'public_header'=>['links'=>[
    ['label'=>'Corporate Gifting','href'=>'/corporate.php'],
    ['label'=>'Retail Subscriptions','href'=>'/retail.php'],
    ['label'=>'Locations','href'=>'/locations.php'],
    ['label'=>'Book A Demo','href'=>'/learn-more.php'],
  ]],
];
require __DIR__.'/includes/header.php';
?>
<style>
.mg-retail-page{background:#f4f7fb;color:#071225}.mg-retail-hero,.mg-retail-section{position:relative;overflow:hidden;padding:150px max(24px,calc((100% - 1180px)/2));border-bottom:1px solid #dbe5f1}.mg-retail-hero{min-height:calc(100svh - 68px);display:grid;grid-template-columns:1.05fr .95fr;gap:60px;align-items:center;background:radial-gradient(circle at 18% 20%,rgba(219,234,254,.95),transparent 36%),radial-gradient(circle at 86% 18%,rgba(237,233,254,.8),transparent 34%),linear-gradient(180deg,#fff,#f8fafc)}.mg-retail-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border:1px solid #d8e4f4;border-radius:999px;background:#fff;color:#2563eb;font-size:12px;font-weight:950;letter-spacing:.09em;text-transform:uppercase}.mg-retail-hero h1,.mg-retail-section h2{margin:22px 0;color:#071225;font-size:clamp(44px,6vw,84px);line-height:.94;letter-spacing:-.075em}.mg-retail-hero p,.mg-retail-section>p{max-width:720px;margin:0;color:#64748b;font-size:20px;line-height:1.62;font-weight:620}.mg-retail-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:32px}.mg-retail-actions a{min-height:50px;display:inline-flex;align-items:center;justify-content:center;padding:0 20px;border:1px solid #d8e4f4;border-radius:14px;background:#fff;color:#071225;text-decoration:none;font-weight:900}.mg-retail-actions a:first-child{border-color:#2563eb;background:#2563eb;color:#fff}.mg-retail-card{padding:28px;border:1px solid #dbe5f1;border-radius:30px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.12)}.mg-retail-stack{display:grid;gap:14px}.mg-retail-metric{display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center;padding:18px;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc}.mg-retail-metric span{color:#64748b;font-weight:850}.mg-retail-metric strong{font-size:28px;color:#1d4ed8}.mg-retail-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:42px}.mg-retail-tile{padding:26px;border:1px solid #dbe5f1;border-radius:24px;background:#fff;box-shadow:0 14px 34px rgba(15,23,42,.06)}.mg-retail-tile h3{margin:0 0 12px;font-size:22px;letter-spacing:-.035em}.mg-retail-tile p{margin:0;color:#64748b;line-height:1.55;font-weight:600}.mg-retail-flow{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:42px}.mg-retail-step{padding:22px;border-radius:22px;background:#071225;color:#fff}.mg-retail-step span{display:grid;place-items:center;width:34px;height:34px;margin-bottom:16px;border-radius:999px;background:#2563eb;font-weight:950}.mg-retail-step h3{margin:0 0 8px}.mg-retail-step p{margin:0;color:#cbd5e1;line-height:1.5}.mg-retail-band{background:linear-gradient(135deg,#071225,#1d4ed8);color:#fff}.mg-retail-band h2{color:#fff}.mg-retail-band p{color:#dbeafe}.mg-retail-band .mg-retail-actions a:first-child{background:#fff;color:#1d4ed8;border-color:#fff}@media(max-width:900px){.mg-retail-hero{grid-template-columns:1fr;padding-top:96px}.mg-retail-grid{grid-template-columns:1fr 1fr}.mg-retail-flow{grid-template-columns:1fr 1fr}}@media(max-width:640px){.mg-retail-hero,.mg-retail-section{padding:84px 20px}.mg-retail-grid,.mg-retail-flow{grid-template-columns:1fr}.mg-retail-hero p,.mg-retail-section>p{font-size:18px}}
</style>
<article class="mg-retail-page">
  <section class="mg-retail-hero">
    <div>
      <span class="mg-retail-eyebrow">Retail subscriptions</span>
      <h1>Turn repeat visits into prepaid local demand.</h1>
      <p>Microgifter helps local retailers package subscription-style offers, prepaid bundles, and recurring customer rewards without building a custom commerce system.</p>
      <div class="mg-retail-actions"><a href="/signup.php">Create an account</a><a href="/learn-more.php">Book a demo</a></div>
    </div>
    <aside class="mg-retail-card">
      <div class="mg-retail-stack">
        <div class="mg-retail-metric"><span>Prepaid customer demand</span><strong>Before visit</strong></div>
        <div class="mg-retail-metric"><span>Recurring local rewards</span><strong>Monthly</strong></div>
        <div class="mg-retail-metric"><span>Customer habit building</span><strong>Repeat</strong></div>
      </div>
    </aside>
  </section>

  <section class="mg-retail-section">
    <span class="mg-retail-eyebrow">What retailers can sell</span>
    <h2>Subscription products that feel simple to buy.</h2>
    <p>Package common retail experiences into digital products customers understand immediately.</p>
    <div class="mg-retail-grid">
      <div class="mg-retail-tile"><h3>Monthly bundles</h3><p>Coffee cards, bakery packs, flower clubs, lunch credits, wellness passes, and local essentials.</p></div>
      <div class="mg-retail-tile"><h3>Giftable subscriptions</h3><p>Let customers buy a recurring local benefit for family, friends, teams, clients, or employees.</p></div>
      <div class="mg-retail-tile"><h3>Prepaid demand</h3><p>Collect revenue before the visit and give merchants clearer signals on future customer demand.</p></div>
    </div>
  </section>

  <section class="mg-retail-section">
    <span class="mg-retail-eyebrow">How it works</span>
    <h2>Simple setup, clear redemption, measurable activity.</h2>
    <p>Retailers create the offer, publish it to their storefront, and manage redemption through Microgifter.</p>
    <div class="mg-retail-flow">
      <div class="mg-retail-step"><span>1</span><h3>Create</h3><p>Build a subscription-style retail offer or prepaid bundle.</p></div>
      <div class="mg-retail-step"><span>2</span><h3>Publish</h3><p>Add it to the merchant storefront and public discovery surfaces.</p></div>
      <div class="mg-retail-step"><span>3</span><h3>Sell</h3><p>Customers buy for themselves or send the offer as a gift.</p></div>
      <div class="mg-retail-step"><span>4</span><h3>Redeem</h3><p>Locations verify claims, redemptions, and customer activity.</p></div>
    </div>
  </section>

  <section class="mg-retail-section mg-retail-band">
    <span class="mg-retail-eyebrow">Local revenue infrastructure</span>
    <h2>Retail subscriptions without a technical lift.</h2>
    <p>Microgifter gives local businesses a guided path from prepaid offer to storefront, cart, claim, redemption, and reporting.</p>
    <div class="mg-retail-actions"><a href="/signup.php">Start building</a><a href="/learn-more.php">Talk to Microgifter</a></div>
  </section>
</article>
<?php require __DIR__.'/includes/footer.php'; ?>
