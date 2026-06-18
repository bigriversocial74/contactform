<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'My prepaid commitments | Microgifter';
$page_section = 'commitments';
$header_mode = mg_current_user() ? 'agent' : 'public';
$page_styles = ['/assets/css/demand-commitments.css'];
$page_scripts = ['/assets/js/demand-commitments.js'];
$page_manifest = [
    'id'=>'commitments','title'=>$page_title,'section'=>$page_section,'header_mode'=>$header_mode,
    'styles'=>$page_styles,'scripts'=>$page_scripts,'body_class'=>'mg-demand-commitments-page',
    'public_header'=>[
        'presentation'=>false,
        'links'=>[
            ['label'=>'Home','href'=>'/index.php'],
            ['label'=>'Discover','href'=>'/discover.php'],
            ['label'=>'Feed','href'=>'/feed.php'],
            ['label'=>'Learn More','href'=>'/learn-more.php'],
        ],
    ],
    'onboarding'=>['enabled'=>false,'page'=>'commitments','sections'=>[]],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-commitments" data-demand-commitments>
  <header class="mg-commitments-hero">
    <div>
      <span class="mg-kicker">Prepaid future demand</span>
      <h1>Your purchased and received Microgift commitments.</h1>
      <p>These commitments come directly from prepaid Microgifts. Sending, claiming, redeeming, cancellation, refund, replacement, and expiration update the same record automatically.</p>
    </div>
    <a class="mg-btn mg-btn-primary" href="/build.php">Create a gift</a>
  </header>

  <section class="mg-commitment-policy">
    <strong>No manual intent is collected here.</strong>
    <span>A commitment exists only after a gift is purchased. “I might visit someday” remains a separate future planning concept.</span>
  </section>

  <div class="mg-commitment-summary" data-commitment-summary aria-live="polite"></div>

  <div class="mg-commitment-toolbar">
    <div class="mg-commitment-tabs" role="tablist" aria-label="Commitment status">
      <button type="button" class="is-active" data-commitment-status="">All</button>
      <button type="button" data-commitment-status="outstanding">Upcoming</button>
      <button type="button" data-commitment-status="redeemed">Redeemed</button>
      <button type="button" data-commitment-status="canceled">Cancelled or refunded</button>
      <button type="button" data-commitment-status="expired">Expired</button>
    </div>
    <button class="mg-btn mg-btn-ghost" type="button" data-commitment-refresh>Refresh</button>
  </div>

  <div class="mg-commitment-status" data-commitment-status-text role="status" aria-live="polite"></div>
  <section class="mg-commitment-loading" data-commitment-loading aria-busy="true">
    <?php for ($i=0;$i<4;$i++): ?><article class="mg-commitment-card is-skeleton" aria-hidden="true"></article><?php endfor; ?>
  </section>
  <section class="mg-commitment-message mg-hidden" data-commitment-signin>
    <h2>Sign in to view your commitments.</h2>
    <p>Your purchased and received Microgifts are private.</p>
    <a class="mg-btn mg-btn-primary" href="/signin.php?return=%2Fcommitments.php">Sign in</a>
  </section>
  <section class="mg-commitment-message mg-hidden" data-commitment-empty>
    <h2>No commitments in this view.</h2>
    <p>Prepaid gifts will appear here after purchase.</p>
  </section>
  <section class="mg-commitment-message mg-hidden" data-commitment-error role="alert">
    <h2>Unable to load commitments.</h2>
    <p data-commitment-error-message>Please try again.</p>
    <button class="mg-btn mg-btn-primary" type="button" data-commitment-retry>Try again</button>
  </section>
  <section class="mg-commitment-list mg-hidden" data-commitment-list aria-label="Prepaid gift commitments"></section>
  <div class="mg-commitment-more mg-hidden" data-commitment-pagination>
    <button class="mg-btn mg-btn-soft" type="button" data-commitment-more>Load more</button>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
