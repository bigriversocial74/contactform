<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Microgifter MCP Server | Agent-Ready Local Commerce';
$page_section = 'mcp-server';
$header_mode = 'public';
$page_body_class = 'mg-mcp-public-page';
$page_styles = ['/assets/css/public-header-footer-fixes.css'];
$page_meta = [
    'description' => 'Connect compatible AI agents and agent harnesses to Microgifter commerce, gifting, CRM, campaign, reward, claim, and merchant workflows through one governed MCP server.',
    'canonical' => 'https://microgifter.com/mcp-server.php',
    'og_title' => 'Microgifter MCP Server',
    'og_description' => 'Give compatible agents governed access to Microgifter gifting, merchant CRM, campaigns, rewards, claims, and commerce workflows.',
];
$page_manifest = [
    'id' => 'mcp-server',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'assets' => ['universal-header'],
    'header_controls' => [],
    'description' => $page_meta['description'],
    'public_header' => [
        'presentation' => false,
        'links' => [
            ['label' => 'MCP Server', 'href' => '/mcp-server.php'],
            ['label' => 'Pricing', 'href' => '/pricing.php'],
            ['label' => 'Book A Demo', 'href' => '/learn-more.php'],
        ],
    ],
    'onboarding' => ['enabled' => false, 'page' => 'mcp-server', 'sections' => []],
];

require __DIR__ . '/includes/header.php';
?>
<style>
:root{--mcp-bg:#f3f5f7;--mcp-ink:#101216;--mcp-muted:#626a75;--mcp-line:#dce1e7;--mcp-card:#fff;--mcp-dark:#101318;--mcp-accent:#ff8e5b;--mcp-max:1380px}
.mg-mcp-public-page,.mg-mcp-public-page .mg-main{background:var(--mcp-bg)!important;color:var(--mcp-ink)}
.mcp-page,.mcp-page *{box-sizing:border-box}.mcp-page{overflow:hidden;font-family:Inter,"Helvetica Neue",Arial,sans-serif;background:linear-gradient(180deg,#f8fafb 0,#eef2f5 54%,#fff 100%)}
.mcp-wrap{width:min(var(--mcp-max),calc(100% - 48px));margin:auto}.mcp-hero{position:relative;min-height:760px;padding:92px 0 76px;display:grid;grid-template-columns:minmax(0,.9fr) minmax(540px,1.1fr);gap:72px;align-items:center}
.mcp-hero:before{content:"";position:absolute;inset:5% -20% auto 42%;height:640px;border-radius:50%;background:radial-gradient(circle,rgba(255,142,91,.24),rgba(171,196,222,.15) 40%,transparent 72%);filter:blur(18px);pointer-events:none}
.mcp-kicker{display:inline-flex;align-items:center;gap:10px;margin:0;color:#343b45;font-size:12px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.mcp-kicker:before{content:"";width:9px;height:9px;border-radius:50%;background:var(--mcp-accent);box-shadow:0 0 0 5px rgba(255,142,91,.14)}
.mcp-hero h1{max-width:760px;margin:24px 0 0;font-size:clamp(54px,7vw,104px);font-weight:320;letter-spacing:-.075em;line-height:.91}.mcp-hero-copy>p{max-width:690px;margin:30px 0 0;color:var(--mcp-muted);font-size:clamp(18px,1.55vw,23px);line-height:1.55}
.mcp-actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:34px}.mcp-btn{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:0 24px;border-radius:10px;text-decoration:none!important;font-size:14px;font-weight:900}.mcp-btn--dark{background:var(--mcp-dark);color:#fff!important}.mcp-btn--light{border:1px solid #aeb6c0;background:rgba(255,255,255,.72);color:#16191e!important}
.mcp-terminal{position:relative;z-index:1;overflow:hidden;border:1px solid rgba(255,255,255,.16);border-radius:24px;background:#0d1117;color:#dbe7f3;box-shadow:0 45px 130px rgba(27,36,48,.26)}.mcp-terminal__bar{height:52px;display:flex;align-items:center;gap:8px;padding:0 18px;border-bottom:1px solid rgba(255,255,255,.08);background:#151a21}.mcp-terminal__bar i{width:10px;height:10px;border-radius:50%;background:#59616d}.mcp-terminal__bar i:first-child{background:#ff7868}.mcp-terminal__bar i:nth-child(2){background:#ffc45c}.mcp-terminal__bar i:nth-child(3){background:#64d38a}.mcp-terminal__bar span{margin-left:12px;color:#8e9aaa;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.mcp-terminal pre{min-height:500px;margin:0;padding:34px;white-space:pre-wrap;font:600 14px/1.85 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#b8c8d8}.mcp-terminal .key{color:#ffb38e}.mcp-terminal .value{color:#8fe1b3}.mcp-terminal .soft{color:#6e7a89}
.mcp-band{padding:86px 0}.mcp-band--dark{background:#101318;color:#fff}.mcp-heading{max-width:920px}.mcp-heading span{color:var(--mcp-accent);font-size:12px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.mcp-heading h2{margin:18px 0 0;font-size:clamp(40px,5vw,72px);font-weight:330;line-height:.98;letter-spacing:-.06em}.mcp-heading p{max-width:760px;margin:22px 0 0;color:#727b86;font-size:18px;line-height:1.65}.mcp-band--dark .mcp-heading p{color:#aeb7c2}
.mcp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin-top:44px}.mcp-card{min-height:260px;padding:28px;border:1px solid var(--mcp-line);background:rgba(255,255,255,.86);box-shadow:0 18px 54px rgba(39,51,66,.07)}.mcp-card b{display:grid;place-items:center;width:42px;height:42px;border-radius:50%;background:#171b21;color:#fff;font-size:13px}.mcp-card h3{margin:28px 0 0;font-size:24px;letter-spacing:-.035em}.mcp-card p{margin:13px 0 0;color:var(--mcp-muted);font-size:15px;line-height:1.62}
.mcp-flow{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:48px;border:1px solid rgba(255,255,255,.12)}.mcp-step{position:relative;min-height:250px;padding:28px;border-left:1px solid rgba(255,255,255,.12)}.mcp-step:first-child{border-left:0}.mcp-step small{color:#ffb18c;font-weight:900;letter-spacing:.12em}.mcp-step h3{margin:46px 0 0;font-size:25px;letter-spacing:-.04em}.mcp-step p{margin:14px 0 0;color:#aeb7c2;line-height:1.6}.mcp-step:after{content:"→";position:absolute;right:22px;top:25px;color:#67717e;font-size:20px}.mcp-step:last-child:after{display:none}
.mcp-guardrails{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:46px;align-items:start}.mcp-rule-list{display:grid;gap:12px}.mcp-rule{display:grid;grid-template-columns:34px 1fr;gap:15px;padding:20px;border:1px solid var(--mcp-line);background:#fff}.mcp-rule b{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#ffe4d6;color:#a94e22}.mcp-rule strong{display:block;font-size:15px}.mcp-rule span{display:block;margin-top:5px;color:var(--mcp-muted);font-size:14px;line-height:1.45}
.mcp-cta{margin:0 auto 90px;padding:54px;display:grid;grid-template-columns:1fr auto;gap:30px;align-items:center;background:linear-gradient(135deg,#fff,#f5e8e1);border:1px solid #e2d7d1}.mcp-cta h2{margin:0;font-size:clamp(34px,4vw,58px);font-weight:350;letter-spacing:-.055em}.mcp-cta p{max-width:720px;margin:15px 0 0;color:var(--mcp-muted);font-size:17px;line-height:1.6}
@media(max-width:1050px){.mcp-hero{grid-template-columns:1fr;min-height:auto}.mcp-terminal pre{min-height:420px}.mcp-grid{grid-template-columns:repeat(2,1fr)}.mcp-flow{grid-template-columns:repeat(2,1fr)}.mcp-step:nth-child(3){border-left:0;border-top:1px solid rgba(255,255,255,.12)}.mcp-step:nth-child(4){border-top:1px solid rgba(255,255,255,.12)}.mcp-guardrails{grid-template-columns:1fr}.mcp-cta{grid-template-columns:1fr}}
@media(max-width:680px){.mcp-wrap{width:calc(100% - 28px)}.mcp-hero{padding:58px 0}.mcp-hero h1{font-size:52px}.mcp-terminal{border-radius:15px}.mcp-terminal pre{padding:22px;font-size:12px;min-height:370px}.mcp-grid,.mcp-flow{grid-template-columns:1fr}.mcp-step,.mcp-step:nth-child(n){border-left:0;border-top:1px solid rgba(255,255,255,.12)}.mcp-step:first-child{border-top:0}.mcp-band{padding:64px 0}.mcp-cta{padding:32px;margin-bottom:56px}}
</style>
<main class="mcp-page">
  <section class="mcp-wrap mcp-hero">
    <div class="mcp-hero-copy">
      <p class="mcp-kicker">Model Context Protocol</p>
      <h1>Connect any compatible agent to Microgifter.</h1>
      <p>The Microgifter MCP Server gives supported AI agents and agent harnesses governed access to gifting, merchant CRM, campaigns, rewards, claims, messaging, and commerce workflows.</p>
      <div class="mcp-actions">
        <a class="mcp-btn mcp-btn--dark" href="/learn-more.php">Request access</a>
        <a class="mcp-btn mcp-btn--light" href="#how-it-works">See how it works</a>
      </div>
    </div>
    <div class="mcp-terminal" aria-label="Example Microgifter MCP connection">
      <div class="mcp-terminal__bar"><i></i><i></i><i></i><span>Microgifter MCP</span></div>
      <pre><span class="soft">// Compatible agent connection</span>
{
  <span class="key">"server"</span>: <span class="value">"Microgifter"</span>,
  <span class="key">"transport"</span>: <span class="value">"secure MCP"</span>,
  <span class="key">"access"</span>: [
    <span class="value">"gifting"</span>,
    <span class="value">"merchant_crm"</span>,
    <span class="value">"campaigns"</span>,
    <span class="value">"rewards"</span>,
    <span class="value">"claims"</span>,
    <span class="value">"messaging"</span>
  ],
  <span class="key">"governance"</span>: {
    <span class="key">"permissions"</span>: <span class="value">"user + merchant scoped"</span>,
    <span class="key">"rules"</span>: <span class="value">"campaign and reward enforced"</span>,
    <span class="key">"receipts"</span>: <span class="value">"auditable actions"</span>
  }
}</pre>
    </div>
  </section>

  <section class="mcp-band" id="how-it-works">
    <div class="mcp-wrap">
      <div class="mcp-heading"><span>One server. Many agent experiences.</span><h2>The same Microgifter capabilities, available beyond the inline workspace.</h2><p>Customers and merchants can use compatible assistants, desktop clients, custom agent applications, and enterprise harnesses while Microgifter remains the governed commerce and relationship layer.</p></div>
      <div class="mcp-grid">
        <article class="mcp-card"><b>01</b><h3>Customer agents</h3><p>Discover local gifts, plan purchases, send or schedule Microgifts, review inbox activity, and act on rewards with customer-approved access.</p></article>
        <article class="mcp-card"><b>02</b><h3>Merchant agents</h3><p>Work with customer records, campaigns, offers, messages, claims, follow-up, recurring programs, and product opportunities.</p></article>
        <article class="mcp-card"><b>03</b><h3>Enterprise agents</h3><p>Coordinate workplace rewards, group gifting, sponsored commerce, recognition programs, and approved bulk actions across teams.</p></article>
      </div>
    </div>
  </section>

  <section class="mcp-band mcp-band--dark">
    <div class="mcp-wrap">
      <div class="mcp-heading"><span>Connection flow</span><h2>Agents connect. Microgifter governs. Commerce moves.</h2><p>The MCP does not bypass platform rules. It exposes approved tools and context while permissions, campaign requirements, reward logic, ownership states, and action receipts stay enforced by Microgifter.</p></div>
      <div class="mcp-flow">
        <article class="mcp-step"><small>01</small><h3>Connect</h3><p>Add the Microgifter MCP connection to a supported agent client or harness.</p></article>
        <article class="mcp-step"><small>02</small><h3>Authorize</h3><p>The user or merchant grants scoped access to approved Microgifter capabilities.</p></article>
        <article class="mcp-step"><small>03</small><h3>Act</h3><p>The agent discovers available tools and performs approved gifting or commerce actions.</p></article>
        <article class="mcp-step"><small>04</small><h3>Verify</h3><p>Microgifter validates rules, records results, and returns structured action receipts.</p></article>
      </div>
    </div>
  </section>

  <section class="mcp-band">
    <div class="mcp-wrap mcp-guardrails">
      <div class="mcp-heading"><span>Built-in governance</span><h2>Agent access without abandoning platform rules.</h2><p>Every MCP action remains subject to the same ownership, permission, campaign, reward, claim, and merchant controls used inside Microgifter.</p></div>
      <div class="mcp-rule-list">
        <div class="mcp-rule"><b>✓</b><div><strong>Scoped identity and permissions</strong><span>Agents only receive the tools and records authorized for the connected customer, merchant, or enterprise account.</span></div></div>
        <div class="mcp-rule"><b>✓</b><div><strong>Campaign and reward enforcement</strong><span>Eligibility, limits, timing, inventory, redemption, and reward rules remain authoritative.</span></div></div>
        <div class="mcp-rule"><b>✓</b><div><strong>Ownership-aware gifting</strong><span>Inbox, sent, claimed, redeemed, refunded, and regifted states remain connected to the Microgift lifecycle.</span></div></div>
        <div class="mcp-rule"><b>✓</b><div><strong>Auditable action receipts</strong><span>Important actions can return structured results for confirmation, tracking, and operational review.</span></div></div>
      </div>
    </div>
  </section>

  <section class="mcp-wrap mcp-cta">
    <div><h2>Bring Microgifter into your agent stack.</h2><p>Request MCP access, discuss a merchant or enterprise integration, and identify the tools and permissions your agent experience needs.</p></div>
    <a class="mcp-btn mcp-btn--dark" href="/learn-more.php">Book an integration call</a>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
