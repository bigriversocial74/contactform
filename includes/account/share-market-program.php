<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/market/merchant-market-explainer.php';

$shareMarketUser = is_array($user ?? null) ? $user : mg_current_user();
$shareMarketProfile = [];
$shareMarketPayload = null;
$shareMarketError = null;

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare("SELECT id,user_id,slug,display_name,status,visibility FROM public_profiles WHERE user_id=? AND status='active' AND visibility IN ('public','unlisted') ORDER BY updated_at DESC,id DESC LIMIT 1");
    $stmt->execute([(int)($shareMarketUser['id'] ?? 0)]);
    $shareMarketProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($shareMarketProfile) {
        $shareMarketPayload = mg_merchant_market_build($pdo, (string)$shareMarketProfile['slug'], ['viewer_id'=>(int)($shareMarketUser['id'] ?? 0)]);
    }
} catch (Throwable $e) {
    $shareMarketError = $e->getMessage();
}

$market = is_array($shareMarketPayload['merchant_market'] ?? null) ? $shareMarketPayload['merchant_market'] : [];
$metrics = is_array($shareMarketPayload['metrics'] ?? null) ? $shareMarketPayload['metrics'] : [];
$score = (int)($market['merchant_score'] ?? 0);
$tickerValue = (string)($market['ticker_value'] ?? '$0');
$rating = (string)($market['rating'] ?? 'No Market Signal');
$confidence = (string)($market['confidence'] ?? 'no data');
$shareSignalCents = max(1, (int)round(((int)($market['ticker_value_cents'] ?? 0)) / 10000));
$shareSignal = '$' . number_format($shareSignalCents / 100, $shareSignalCents < 10000 ? 2 : 0);
$readiness = $score >= 70 ? 'Ready to pilot' : ($score >= 45 ? 'Build more signal first' : 'Not ready yet');
$readinessClass = $score >= 70 ? 'is-ready' : ($score >= 45 ? 'is-building' : 'is-early');
$enrollmentStatus = 'Not enrolled';
$treasuryPurchased = 0;
$treasuryAvailable = 0;
$treasuryAssigned = 0;
$treasuryCirculating = 0;
$treasuryRedeemed = 0;
?>
<style>
.sm-program,.sm-program *{box-sizing:border-box}.sm-program{--sm-ink:#050505;--sm-muted:#64748b;--sm-line:#dce4ef;--sm-card:#fff;--sm-soft:#f8fafc;--sm-gold:#d9a735;--sm-green:#16a34a;display:grid;gap:18px;font-family:Inter,"Helvetica Neue",Arial,sans-serif;color:#111827}.sm-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.66fr);gap:24px;padding:34px;border:1px solid var(--sm-line);border-radius:28px;background:radial-gradient(circle at 82% 10%,rgba(217,167,53,.22),transparent 28%),linear-gradient(135deg,#fff,#f8fafc);box-shadow:0 22px 70px rgba(15,23,42,.08)}.sm-kicker{display:inline-flex;align-items:center;min-height:26px;width:max-content;padding:0 11px;border-radius:999px;background:#050505;color:#fff;font-size:10px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.sm-hero h1{max-width:790px;margin:20px 0 0;color:#050505;font-size:clamp(38px,4vw,66px);line-height:.92;letter-spacing:-.075em;font-weight:950}.sm-hero p{max-width:760px;margin:18px 0 0;color:#334155;font-size:16px;line-height:1.46;font-weight:560}.sm-action-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.sm-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:43px;padding:0 18px;border-radius:12px;text-decoration:none!important;font-size:13px;font-weight:900}.sm-btn.primary{background:#050505;color:#fff!important}.sm-btn.soft{background:#fff;color:#111827!important;border:1px solid var(--sm-line)}.sm-btn.danger{background:#fff1f2;color:#9f1239!important;border:1px solid #fecdd3}.sm-signal-card{display:grid;gap:12px;align-content:start;padding:18px;border:1px solid var(--sm-line);border-radius:22px;background:rgba(255,255,255,.82)}.sm-signal-card header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.sm-signal-card header span{color:var(--sm-muted);font-size:10px;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.sm-status{display:inline-flex;align-items:center;min-height:27px;padding:0 10px;border-radius:999px;font-size:10px;font-weight:950;text-transform:uppercase}.sm-status.is-ready{background:#dcfce7;color:#166534}.sm-status.is-building{background:#fef3c7;color:#92400e}.sm-status.is-early{background:#fee2e2;color:#991b1b}.sm-signal-value{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sm-stat{min-height:96px;padding:14px;border-radius:16px;background:#f8fafc;border:1px solid #e5edf7}.sm-stat span{display:block;color:var(--sm-muted);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.1em}.sm-stat strong{display:block;margin-top:13px;color:#050505;font-size:28px;line-height:.9;font-weight:950;letter-spacing:-.05em}.sm-stat small{display:block;margin-top:7px;color:#64748b;font-size:11px;font-weight:700}.sm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sm-panel{padding:24px;border:1px solid var(--sm-line);border-radius:22px;background:rgba(255,255,255,.86);box-shadow:0 14px 45px rgba(15,23,42,.045)}.sm-panel h2,.sm-panel h3{margin:0;color:#050505;font-weight:950;letter-spacing:-.055em}.sm-panel h2{font-size:28px;line-height:.98}.sm-panel h3{font-size:22px;line-height:1}.sm-panel p{margin:14px 0 0;color:#475569;font-size:14px;line-height:1.45;font-weight:560}.sm-icon{display:grid;place-items:center;width:42px;height:42px;margin-bottom:18px;border-radius:15px;background:#050505;color:#fff;font-size:18px;font-weight:950}.sm-wide{display:grid;grid-template-columns:minmax(0,.84fr) minmax(360px,1.16fr);gap:14px}.sm-dark{background:linear-gradient(135deg,#050505,#171717 60%,#050505);color:#fff}.sm-dark h2,.sm-dark h3{color:#fff}.sm-dark p{color:rgba(255,255,255,.72)}.sm-dark .sm-kicker{background:rgba(255,255,255,.1);color:#facc15}.sm-list{display:grid;gap:10px;margin-top:20px}.sm-list-item{display:grid;grid-template-columns:42px 1fr auto;gap:12px;align-items:center;min-height:58px;padding:10px 12px;border:1px solid rgba(255,255,255,.12);border-radius:15px;background:rgba(255,255,255,.08)}.sm-list-item b{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.12)}.sm-list-item strong{display:block;color:#fff;font-size:13px;font-weight:900}.sm-list-item span{display:block;margin-top:3px;color:rgba(255,255,255,.58);font-size:11px;line-height:1.25}.sm-list-item em{font-style:normal;color:#fde68a;font-size:11px;font-weight:950;white-space:nowrap}.sm-table{display:grid;gap:0;margin-top:18px;border:1px solid #e5edf7;border-radius:18px;overflow:hidden;background:#fff}.sm-table-row{display:grid;grid-template-columns:1fr 1fr 92px;gap:12px;align-items:center;min-height:62px;padding:13px 15px;border-top:1px solid #e5edf7}.sm-table-row:first-child{border-top:0}.sm-table-row span{display:block;color:#64748b;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.sm-table-row strong{display:block;margin-top:4px;color:#111827;font-size:13px;font-weight:900}.sm-table-row em{display:inline-flex;align-items:center;justify-content:center;min-height:28px;border-radius:999px;background:#f8f1df;color:#8a650c;font-style:normal;font-size:11px;font-weight:950}.sm-note{padding:18px;border-radius:18px;background:#f8fafc;border:1px solid #e5edf7;color:#334155;font-size:13px;line-height:1.45;font-weight:650}.sm-callout{display:flex;gap:14px;align-items:flex-start;padding:18px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12}.sm-callout b{display:grid;place-items:center;flex:0 0 auto;width:34px;height:34px;border-radius:12px;background:#fdba74;color:#7c2d12}.sm-callout p{margin:0;color:#7c2d12}.sm-security-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px}.sm-security-card{padding:18px;border:1px solid #dbeafe;border-radius:18px;background:#f8fbff}.sm-security-card strong{display:block;color:#0f172a;font-size:14px;font-weight:950}.sm-security-card span{display:block;margin-top:8px;color:#475569;font-size:12px;line-height:1.4;font-weight:650}.sm-final{padding:28px;border-radius:24px;border:1px solid var(--sm-line);background:#fff;text-align:center}.sm-final h2{max-width:760px;margin:0 auto;color:#050505;font-size:clamp(30px,3.4vw,48px);line-height:.96;letter-spacing:-.065em;font-weight:950}.sm-final p{max-width:720px;margin:16px auto 0;color:#475569;font-size:15px;line-height:1.48;font-weight:560}.sm-final .sm-action-row{justify-content:center}@media(max-width:1040px){.sm-hero,.sm-wide{grid-template-columns:1fr}.sm-grid,.sm-security-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.sm-hero,.sm-panel,.sm-final{padding:22px}.sm-grid,.sm-security-grid{grid-template-columns:1fr}.sm-signal-value{grid-template-columns:1fr}.sm-table-row{grid-template-columns:1fr}.sm-list-item{grid-template-columns:38px 1fr}.sm-list-item em{grid-column:2}}
</style>

<section class="mg-app-panel mg-account-pane is-active sm-program" data-account-pane="share-market">
  <section class="sm-hero">
    <div>
      <span class="sm-kicker">Optional Program</span>
      <h1>Turn DRM value into a professional share market.</h1>
      <p>This is an opt-in layer on top of the existing DRM Market Dashboard. Merchants, artists, bands, creators, and event operators can keep using Microgifter without participating, or launch a controlled fan market when they are ready.</p>
      <div class="sm-action-row">
        <a class="sm-btn primary" href="/learn-more.php">Request Program Review <span>→</span></a>
        <a class="sm-btn soft" href="/account-market.php">Back to DRM Dashboard</a>
        <a class="sm-btn danger" href="/account.php">Do Not Participate</a>
      </div>
    </div>
    <aside class="sm-signal-card" aria-label="Current DRM share readiness">
      <header><span>Program State</span><strong class="sm-status <?= mg_e($readinessClass) ?>"><?= mg_e($enrollmentStatus) ?></strong></header>
      <div class="sm-signal-value">
        <div class="sm-stat"><span>DRM Value</span><strong><?= mg_e($tickerValue) ?></strong><small><?= mg_e($rating) ?> · <?= mg_e($confidence) ?></small></div>
        <div class="sm-stat"><span>Artist Value Index</span><strong><?= mg_e((string)$score) ?></strong><small><?= mg_e($readiness) ?></small></div>
        <div class="sm-stat"><span>Share Signal</span><strong><?= mg_e($shareSignal) ?></strong><small>Estimated value per 10k-share pool</small></div>
        <div class="sm-stat"><span>Participation</span><strong>Opt-in</strong><small>No account is enrolled by default</small></div>
      </div>
    </aside>
  </section>

  <?php if (!$shareMarketProfile): ?>
    <section class="sm-panel"><h2>No active public profile yet.</h2><p>The Share Market program uses the same DRM signal as the Market Dashboard. Create or publish a profile first, then this page can calculate readiness, value signal, and launch requirements.</p><div class="sm-action-row"><a class="sm-btn primary" href="/account.php">Open Profile Editor</a></div></section>
  <?php elseif ($shareMarketError): ?>
    <section class="sm-panel"><h2>Share Market signal unavailable.</h2><p><?= mg_e($shareMarketError) ?></p></section>
  <?php endif; ?>

  <section class="sm-grid" aria-label="Program boundaries">
    <article class="sm-panel"><span class="sm-icon">✓</span><h3>Optional by design</h3><p>The normal DRM dashboard, products, gift cards, tickets, rewards, CRM, and commerce tools continue to work whether or not a merchant launches a share market.</p></article>
    <article class="sm-panel"><span class="sm-icon">D</span><h3>Powered by DRM</h3><p>Share value is attached to the artist or merchant value profile calculated from Microgifter-visible demand, redemptions, sales, engagement, distribution, followers, and risk.</p></article>
    <article class="sm-panel"><span class="sm-icon">⚿</span><h3>Ledger first</h3><p>The first production version should use a hardened internal ledger. Blockchain can come later as a proof/export layer without making every transaction public by default.</p></article>
  </section>

  <section class="sm-wide">
    <article class="sm-panel sm-dark">
      <span class="sm-kicker">Controlled launch model</span>
      <h2>Merchants buy share credits only when they want to launch a fan market.</h2>
      <p>Microgifter controls the platform share pool. A participating artist or merchant buys share credits in bulk, assigns them to a specific series, sets redemption rules, and publishes the market after review.</p>
      <div class="sm-list">
        <div class="sm-list-item"><b>1</b><div><strong>Buy share credits</strong><span>Example: 10,000 credits for $100, held in merchant treasury.</span></div><em>OPTIONAL</em></div>
        <div class="sm-list-item"><b>2</b><div><strong>Create a series</strong><span>Example: First 10,000, Tour Drop, Album Drop, Opening Night.</span></div><em>DRAFT</em></div>
        <div class="sm-list-item"><b>3</b><div><strong>Attach DRM value</strong><span>Artist Value Index and demand ticker shape pricing, readiness, and reissue guidance.</span></div><em>DATA</em></div>
        <div class="sm-list-item"><b>4</b><div><strong>Submit for review</strong><span>Admin approval before public market launch, resale, or redemption activation.</span></div><em>SECURE</em></div>
      </div>
    </article>

    <article class="sm-panel">
      <h2>Share Credit Treasury</h2>
      <p>This is the merchant-side inventory view. It stays at zero until the merchant opts in and purchases a pool.</p>
      <div class="sm-table">
        <div class="sm-table-row"><div><span>Purchased</span><strong><?= mg_e(number_format($treasuryPurchased)) ?> credits</strong></div><div><span>Available</span><strong><?= mg_e(number_format($treasuryAvailable)) ?> credits</strong></div><em>Treasury</em></div>
        <div class="sm-table-row"><div><span>Assigned</span><strong><?= mg_e(number_format($treasuryAssigned)) ?> credits</strong></div><div><span>Circulating</span><strong><?= mg_e(number_format($treasuryCirculating)) ?> shares</strong></div><em>Market</em></div>
        <div class="sm-table-row"><div><span>Redeemed / burned</span><strong><?= mg_e(number_format($treasuryRedeemed)) ?> shares</strong></div><div><span>Status</span><strong><?= mg_e($enrollmentStatus) ?></strong></div><em>Opt-in</em></div>
      </div>
      <p class="sm-note">A share credit is the account-side inventory unit. The fan-facing product is the artist or merchant share attached to a public market series.</p>
    </article>
  </section>

  <section class="sm-wide">
    <article class="sm-panel">
      <h2>Secure launch checklist</h2>
      <p>Before any market can go live, the account should pass profile, value, treasury, redemption, and review gates.</p>
      <div class="sm-table">
        <div class="sm-table-row"><div><span>Identity</span><strong>Active public or unlisted profile</strong></div><div><span>Current</span><strong><?= $shareMarketProfile ? 'Detected' : 'Missing' ?></strong></div><em>Required</em></div>
        <div class="sm-table-row"><div><span>Value</span><strong>DRM score and ticker signal</strong></div><div><span>Current</span><strong><?= mg_e((string)$score) ?> / 100</strong></div><em>Required</em></div>
        <div class="sm-table-row"><div><span>Treasury</span><strong>Share credits purchased</strong></div><div><span>Current</span><strong><?= mg_e(number_format($treasuryPurchased)) ?></strong></div><em>Required</em></div>
        <div class="sm-table-row"><div><span>Utility</span><strong>Redemption catalog configured</strong></div><div><span>Current</span><strong>Draft needed</strong></div><em>Required</em></div>
        <div class="sm-table-row"><div><span>Review</span><strong>Microgifter admin approval</strong></div><div><span>Current</span><strong>Not submitted</strong></div><em>Gate</em></div>
      </div>
    </article>

    <article class="sm-panel">
      <h2>Series builder preview</h2>
      <p>The first real workflow should let the merchant create a controlled market series without activating it publicly until review is complete.</p>
      <div class="sm-table">
        <div class="sm-table-row"><div><span>Series</span><strong>First 10,000</strong></div><div><span>Supply</span><strong>10,000 shares</strong></div><em>Draft</em></div>
        <div class="sm-table-row"><div><span>Pricing</span><strong>Launch price + max per buyer</strong></div><div><span>Resale</span><strong>Off until approved</strong></div><em>Locked</em></div>
        <div class="sm-table-row"><div><span>Redemption</span><strong>Microgifts, tickets, merch, VIP</strong></div><div><span>Activation</span><strong>Catalog required</strong></div><em>Utility</em></div>
        <div class="sm-table-row"><div><span>Reissue</span><strong>Follower, sales, or ticket milestones</strong></div><div><span>Approval</span><strong>Required per phase</strong></div><em>Governed</em></div>
      </div>
    </article>
  </section>

  <section class="sm-panel">
    <h2>Over-the-top security model</h2>
    <p>The secure version should treat every share-credit movement as a ledger event with strict controls, not as a normal editable balance.</p>
    <div class="sm-security-grid">
      <div class="sm-security-card"><strong>Append-only internal ledger</strong><span>No destructive edits to ownership, treasury, redemption, or resale events. Corrections become reversal events.</span></div>
      <div class="sm-security-card"><strong>Hash-chained audit trail</strong><span>Every event can include previous-event hash, payload hash, actor ID, IP/device metadata, and timestamp.</span></div>
      <div class="sm-security-card"><strong>Role-gated approvals</strong><span>Buying pools, publishing series, enabling resale, approving reissues, and changing redemption value require separate permissions.</span></div>
      <div class="sm-security-card"><strong>Step-up verification</strong><span>High-risk actions should require fresh authentication, CSRF protection, device/session checks, and optional admin approval.</span></div>
      <div class="sm-security-card"><strong>Escrow-style state machine</strong><span>Draft → submitted → approved → live → paused → closed, with no shortcut to live markets.</span></div>
      <div class="sm-security-card"><strong>Blockchain-ready export</strong><span>Start private and secure. Later, publish periodic Merkle roots or proofs to a chain without exposing customer data.</span></div>
    </div>
  </section>

  <section class="sm-panel">
    <div class="sm-callout"><b>!</b><p><strong>Blockchain should be optional infrastructure, not the first dependency.</strong> The safest path is a private Microgifter ledger now, with cryptographic proofs and optional blockchain anchoring later.</p></div>
  </section>

  <section class="sm-final">
    <h2>Share Markets should be an opt-in layer on top of verified DRM value.</h2>
    <p>The current DRM dashboard measures demand. This optional page turns that demand into a structured program: buy share credits, create a limited series, attach redemption utility, run approvals, and let fans hold, gift, resell, or redeem artist-value shares.</p>
    <div class="sm-action-row"><a class="sm-btn primary" href="/learn-more.php">Request Program Review</a><a class="sm-btn soft" href="/buy-in.php">View Public Buy-In Page</a></div>
  </section>
</section>
