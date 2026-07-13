<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
$page_title='Reviews & Case Studies | Microgifter Admin';$page_section='account';$header_mode='account';$page_styles=['/assets/css/admin-dashboard.css','/assets/css/reviews-case-studies-management.css?v=1.0.0'];$page_scripts=['/assets/js/admin-reviews-case-studies.js?v=1.0.0'];
$user=mg_current_user();$roles=is_array($user['roles']??null)?$user['roles']:[];$permissions=is_array($user['permissions']??null)?$user['permissions']:[];$hasAdminAccess=$user&&(in_array('super_admin',$roles,true)||in_array('admin.profiles.moderation.view',$permissions,true)||in_array('admin.profiles.moderation.manage',$permissions,true)||in_array('admin.users.manage',$permissions,true));$adminActive='reviews';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace rcs-main" data-admin-reviews-page>
    <div class="rcs-wrap">
      <?php if(!$user): ?>
        <section class="rcs-card rcs-empty"><h1>Admin access</h1><p>Sign in to manage reviews and case studies.</p><a class="rcs-btn is-primary" href="/signin.php">Sign in</a></section>
      <?php elseif(!$hasAdminAccess): ?>
        <section class="rcs-card rcs-empty"><h1>Access unavailable</h1><p>This account does not have review moderation permission.</p></section>
      <?php else: ?>
      <header class="rcs-hero"><div><h1>Reviews &amp; Case Studies</h1><p>Moderate customer reviews, curate public success stories, feature testimonials, and inspect the complete audit trail.</p></div><a class="rcs-btn" href="/featured-case-studies.php" target="_blank" rel="noopener">View public page</a></header>
      <nav class="rcs-tabs" aria-label="Management sections"><button class="is-active" data-rcs-tab="reviews">Review moderation</button><button data-rcs-tab="cases">Case studies</button><button data-rcs-tab="audit">Audit history</button></nav>
      <section class="rcs-panel is-active" data-rcs-panel="reviews">
        <div class="rcs-stats" data-admin-review-stats></div>
        <div class="rcs-card rcs-toolbar"><input type="search" placeholder="Search reviews or merchants" data-review-search><select data-review-status><option value="all">All statuses</option><option value="pending">Pending</option><option value="published">Published</option><option value="hidden">Hidden</option><option value="removed">Removed</option></select><select data-review-rating><option value="0">All ratings</option><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select><button class="rcs-btn" data-review-refresh>Refresh</button></div>
        <div class="rcs-list" data-admin-review-list></div><div class="rcs-card rcs-empty" data-admin-review-empty hidden>No reviews match these filters.</div>
      </section>
      <section class="rcs-panel" data-rcs-panel="cases">
        <div class="rcs-case-grid" data-admin-case-list></div><div class="rcs-card rcs-empty" data-admin-case-empty hidden>No curated case studies exist yet. Choose a review and use “Build Case Study.”</div>
      </section>
      <section class="rcs-panel" data-rcs-panel="audit"><div class="rcs-card" style="overflow:auto"><table class="rcs-audit"><thead><tr><th>Date</th><th>Action</th><th>Merchant</th><th>Actor</th></tr></thead><tbody data-admin-audit-list></tbody></table></div></section>
      <div class="rcs-modal" data-admin-review-modal hidden><div class="rcs-modal-card"><header class="rcs-modal-head"><h2>Moderate review</h2><button type="button" data-modal-close>×</button></header><form class="rcs-form" data-admin-review-form><input type="hidden" name="review_id"><label>Status<select name="status"><option value="published">Published</option><option value="pending">Pending</option><option value="hidden">Hidden</option><option value="removed">Removed</option></select></label><label><span><input type="checkbox" name="featured_on_profile" value="1"> Feature on merchant profile</span></label><label><span><input type="checkbox" name="featured_in_case_study" value="1"> Feature in case study</span></label><label>Moderation notes<textarea name="moderation_notes" rows="5" maxlength="3000"></textarea></label><button class="rcs-btn is-primary" type="submit">Save review</button><div data-form-status></div></form></div></div>
      <div class="rcs-modal" data-admin-case-modal hidden><div class="rcs-modal-card"><header class="rcs-modal-head"><h2>Case study editor</h2><button type="button" data-modal-close>×</button></header><form class="rcs-form" data-admin-case-form><input type="hidden" name="case_study_id"><input type="hidden" name="profile_id"><input type="hidden" name="selected_review_id"><div class="rcs-form-grid"><label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="hidden">Hidden</option><option value="archived">Archived</option></select></label><label>Display order<input type="number" name="display_order" value="100"></label></div><label><span><input type="checkbox" name="hero_featured" value="1"> Main hero case study</span></label><label>Title<input name="title" maxlength="220"></label><label>Subtitle<textarea name="subtitle" rows="2" maxlength="320"></textarea></label><label>Challenge<textarea name="challenge" rows="5"></textarea></label><label>Solution<textarea name="solution" rows="5"></textarea></label><label>Outcomes, one per line<textarea name="outcomes_text" rows="5"></textarea></label><label>Testimonial<textarea name="testimonial_text" rows="4"></textarea></label><div class="rcs-form-grid"><label>Testimonial name<input name="testimonial_name" maxlength="180"></label><label>Role<input name="testimonial_role" maxlength="180"></label></div><label>Internal notes<textarea name="internal_notes" rows="4"></textarea></label><button class="rcs-btn is-primary" type="submit">Save case study</button><div data-form-status></div></form></div></div>
      <?php endif; ?>
    </div>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>