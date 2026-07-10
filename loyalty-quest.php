<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$ref=trim((string)($_GET['campaign']??$_GET['c']??$_GET['id']??$_GET['slug']??''));
$ref=preg_replace('/[^a-zA-Z0-9_-]+/','',$ref)??'';
$page_title='Loyalty Quest | Microgifter';
$page_section='quests';
$header_mode='public';
$page_styles=['/assets/css/loyalty-quest-participant.css'];
$page_scripts=['/assets/js/loyalty-quest-participant.js'];
$page_meta=['description'=>'Complete a verified Microgifter Loyalty Quest and earn a merchant reward.','robots'=>$ref!==''?'index, follow':'noindex, follow'];
require __DIR__.'/includes/header.php';
?>
<main class="mg-lqp-page" data-loyalty-quest-participant data-campaign-ref="<?= mg_e($ref) ?>">
  <section class="mg-lqp-loading" data-lqp-loading><div class="mg-lqp-spinner"></div><p>Loading Loyalty Quest…</p></section>
  <section class="mg-lqp-error" data-lqp-error hidden><span>Quest unavailable</span><h1>This Loyalty Quest cannot be opened.</h1><p data-lqp-error-message>Check the link or explore another quest.</p><a href="/quests.php">Explore Loyalty Quests</a></section>
  <div data-lqp-content hidden>
    <section class="mg-lqp-hero">
      <div class="mg-lqp-hero-copy">
        <a class="mg-lqp-back" href="/quests.php">← Explore quests</a>
        <span class="mg-lqp-kicker" data-lqp-merchant>Microgifter Merchant</span>
        <h1 data-lqp-title>Loyalty Quest</h1>
        <p data-lqp-description></p>
        <div class="mg-lqp-tags"><span data-lqp-action></span><span data-lqp-verification></span><span data-lqp-location></span></div>
      </div>
      <aside class="mg-lqp-reward-card">
        <span>Quest reward</span><h2 data-lqp-reward-title>Microgifter Reward</h2><strong data-lqp-reward-value></strong><p data-lqp-reward-description></p><small>Delivered to your Microgifter wallet after verified completion.</small>
      </aside>
    </section>

    <section class="mg-lqp-layout">
      <div class="mg-lqp-main">
        <section class="mg-lqp-panel">
          <div class="mg-lqp-panel-head"><div><span class="mg-lqp-kicker">Quest instructions</span><h2>Complete the required action</h2></div><span class="mg-lqp-state" data-lqp-state>Available</span></div>
          <p class="mg-lqp-instructions" data-lqp-instructions></p>
          <div class="mg-lqp-location-card" data-lqp-location-card hidden><strong data-lqp-location-name></strong><span data-lqp-location-address></span><small data-lqp-location-radius></small></div>
          <div class="mg-lqp-progress" data-lqp-progress hidden><div><span>Progress</span><strong data-lqp-progress-label>0 of 1</strong></div><div class="mg-lqp-progress-track"><span data-lqp-progress-bar></span></div></div>
        </section>

        <section class="mg-lqp-panel" data-lqp-action-panel>
          <div class="mg-lqp-panel-head"><div><span class="mg-lqp-kicker">Verification</span><h2 data-lqp-action-title>Start this quest</h2></div></div>
          <div class="mg-lqp-auth-gate" data-lqp-auth-gate hidden><h3>Use your Microgifter account</h3><p>Sign in or create an account to track progress and receive the reward in your wallet.</p><div><a data-lqp-signin href="/signin.php">Sign in</a><a data-lqp-signup href="/signup.php">Create account</a></div></div>
          <div class="mg-lqp-start" data-lqp-start hidden><p>Your progress stays connected to this Microgifter account.</p><button type="button" data-lqp-start-button>Start Loyalty Quest</button></div>
          <form class="mg-lqp-proof-form" data-lqp-proof-form hidden>
            <div class="mg-lqp-method" data-lqp-code-field hidden><label><span data-lqp-code-label>Quest code</span><input name="code" autocomplete="one-time-code" maxlength="500" placeholder="Enter or scan code"></label><button type="button" data-lqp-camera>Scan QR</button></div>
            <div class="mg-lqp-method" data-lqp-location-field hidden><p>Location access verifies that you are at the participating merchant.</p><button type="button" data-lqp-location-button>Verify my location</button><small data-lqp-location-result></small></div>
            <label data-lqp-reference-field hidden><span>Purchase or reference ID</span><input name="reference_id" maxlength="190" placeholder="Order, receipt, or transaction reference"></label>
            <label data-lqp-proof-url-field hidden><span>Proof link</span><input name="proof_url" type="url" maxlength="700" placeholder="https://…"></label>
            <label data-lqp-note-field hidden><span>Completion note</span><textarea name="proof_note" rows="4" maxlength="4000" placeholder="Describe what you completed"></textarea></label>
            <button class="mg-lqp-submit" type="submit">Submit completion</button>
          </form>
          <div class="mg-lqp-status" data-lqp-status role="status" aria-live="polite"></div>
        </section>

        <section class="mg-lqp-panel" data-lqp-evidence-panel hidden><div class="mg-lqp-panel-head"><div><span class="mg-lqp-kicker">Activity</span><h2>Your quest evidence</h2></div></div><div class="mg-lqp-evidence-list" data-lqp-evidence-list></div></section>
      </div>

      <aside class="mg-lqp-sidebar">
        <section class="mg-lqp-panel"><span class="mg-lqp-kicker">Quest details</span><dl><div><dt>Merchant</dt><dd data-lqp-detail-merchant></dd></div><div><dt>Ends</dt><dd data-lqp-end-date></dd></div><div><dt>Required actions</dt><dd data-lqp-required-count></dd></div><div><dt>Verification</dt><dd data-lqp-detail-verification></dd></div></dl></section>
        <section class="mg-lqp-panel mg-lqp-wallet-result" data-lqp-wallet-result hidden><span class="mg-lqp-kicker">Reward earned</span><h2 data-lqp-wallet-title></h2><p>Your reward is now available in your Microgifter wallet.</p><a href="/wallet.php">Open wallet</a></section>
        <section class="mg-lqp-panel"><h3>Need help?</h3><p>Ask the merchant for the correct quest code or contact Microgifter support when verification does not work.</p><a href="/contact.php">Get support</a></section>
      </aside>
    </section>
  </div>
</main>
<div class="mg-lqp-scanner" data-lqp-scanner hidden aria-hidden="true"><div class="mg-lqp-scanner-dialog" role="dialog" aria-modal="true" aria-labelledby="mg-lqp-scanner-title"><button type="button" data-lqp-scanner-close aria-label="Close scanner">×</button><h2 id="mg-lqp-scanner-title">Scan quest QR code</h2><video data-lqp-video playsinline muted></video><canvas data-lqp-canvas hidden></canvas><p data-lqp-scanner-status>Point your camera at the quest QR code.</p></div></div>
<?php require __DIR__.'/includes/footer.php';
