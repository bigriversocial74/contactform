<?php declare(strict_types=1); ?>
<section data-merchant-reviews-page>
  <header class="rcs-hero">
    <div>
      <span class="mg-eyebrow">Customer experience</span>
      <h1>Customer Reviews</h1>
      <p>Manage review visibility, feature customer stories, reply publicly, and continue private follow-up through Microgifter Messages.</p>
    </div>
    <a class="rcs-btn" href="/merchant-campaigns.php">Review campaigns</a>
  </header>

  <div class="rcs-stats" data-merchant-review-stats></div>

  <div class="rcs-card rcs-toolbar rcs-review-toolbar" role="search" aria-label="Customer review filters">
    <input type="search" placeholder="Search customer reviews" aria-label="Search customer reviews" data-review-search>
    <select aria-label="Filter by review status" data-review-status>
      <option value="all">All statuses</option>
      <option value="pending">Pending</option>
      <option value="published">Published</option>
      <option value="hidden">Hidden</option>
    </select>
    <select aria-label="Filter by review rating" data-review-rating>
      <option value="0">All ratings</option>
      <option value="5">5 stars</option>
      <option value="4">4 stars</option>
      <option value="3">3 stars</option>
      <option value="2">2 stars</option>
      <option value="1">1 star</option>
    </select>
    <button class="rcs-btn" type="button" data-review-refresh>Refresh</button>
  </div>

  <div class="rcs-list" data-merchant-review-list></div>
  <div class="rcs-card rcs-empty" data-merchant-review-empty hidden>
    <strong>No reviews match these filters</strong>
    <p>Try a broader search, status, or rating.</p>
  </div>

  <div class="rcs-modal" data-reply-modal hidden>
    <div class="rcs-modal-card">
      <header class="rcs-modal-head"><h2>Public merchant reply</h2><button type="button" data-modal-close>×</button></header>
      <form class="rcs-form" data-reply-form>
        <input type="hidden" name="review_id">
        <label>Reply<textarea name="reply_body" rows="7" minlength="2" maxlength="3000" required placeholder="Write a helpful public response that will appear below the customer review."></textarea></label>
        <button class="rcs-btn is-primary" type="submit">Publish reply</button>
        <div data-form-status></div>
      </form>
    </div>
  </div>

  <div class="rcs-modal" data-message-modal hidden>
    <div class="rcs-modal-card">
      <header class="rcs-modal-head"><h2>Message customer privately</h2><button type="button" data-modal-close>×</button></header>
      <form class="rcs-form" data-message-form>
        <input type="hidden" name="review_id">
        <label>Message<textarea name="message" rows="7" maxlength="5000" required placeholder="Continue this review as a private Microgifter Messages conversation."></textarea></label>
        <button class="rcs-btn is-primary" type="submit">Send message</button>
        <div data-form-status></div>
      </form>
    </div>
  </div>
</section>
