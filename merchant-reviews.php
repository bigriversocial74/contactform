<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Merchant Reviews | Microgifter';$page_section='merchant';$header_mode='account';$page_styles=['/assets/css/merchant-workspace.css','/assets/css/reviews-case-studies-management.css?v=1.0.0'];$page_scripts=['/assets/js/merchant-reviews.js?v=1.0.0'];$merchantView='reviews';
$user=mg_current_user();require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php if(file_exists(__DIR__.'/includes/merchant-sidebar.php')) require __DIR__.'/includes/merchant-sidebar.php'; ?>
  <main class="mg-app-workspace rcs-main" data-merchant-reviews-page>
    <div class="rcs-wrap">
      <?php if(!$user): ?>
      <section class="rcs-card rcs-empty"><h1>Merchant reviews</h1><p>Sign in to manage customer reviews.</p><a class="rcs-btn is-primary" href="/signin.php">Sign in</a></section>
      <?php else: ?>
      <header class="rcs-hero"><div><h1>Customer Reviews</h1><p>Manage review visibility, feature customer stories, reply publicly, and continue private follow-up through Microgifter Messages.</p></div><a class="rcs-btn" href="/merchant-campaigns.php">Review campaigns</a></header>
      <div class="rcs-stats" data-merchant-review-stats></div>
      <div class="rcs-card rcs-toolbar"><input type="search" placeholder="Search customer reviews" data-review-search><select data-review-status><option value="all">All statuses</option><option value="pending">Pending</option><option value="published">Published</option><option value="hidden">Hidden</option></select><select data-review-rating><option value="0">All ratings</option><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select><button class="rcs-btn" data-review-refresh>Refresh</button></div>
      <div class="rcs-list" data-merchant-review-list></div><div class="rcs-card rcs-empty" data-merchant-review-empty hidden>No reviews match these filters.</div>
      <div class="rcs-modal" data-reply-modal hidden><div class="rcs-modal-card"><header class="rcs-modal-head"><h2>Public merchant reply</h2><button type="button" data-modal-close>×</button></header><form class="rcs-form" data-reply-form><input type="hidden" name="review_id"><label>Reply<textarea name="reply_body" rows="7" minlength="2" maxlength="3000" required placeholder="Write a helpful public response that will appear below the customer review."></textarea></label><button class="rcs-btn is-primary" type="submit">Publish reply</button><div data-form-status></div></form></div></div>
      <div class="rcs-modal" data-message-modal hidden><div class="rcs-modal-card"><header class="rcs-modal-head"><h2>Message customer privately</h2><button type="button" data-modal-close>×</button></header><form class="rcs-form" data-message-form><input type="hidden" name="review_id"><label>Message<textarea name="message" rows="7" maxlength="5000" required placeholder="Continue this review as a private Microgifter Messages conversation."></textarea></label><button class="rcs-btn is-primary" type="submit">Send message</button><div data-form-status></div></form></div></div>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>