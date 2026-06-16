<?php
declare(strict_types=1);
?>
<section class="mg-app-panel mg-account-pane is-active mg-profile-editor" data-account-pane="profile" data-profile-editor>
  <div class="mg-profile-editor-loading" data-editor-loading>
    <div class="mg-profile-editor-loading-line is-wide"></div>
    <div class="mg-profile-editor-loading-grid">
      <div class="mg-profile-editor-loading-card"></div>
      <div class="mg-profile-editor-loading-card"></div>
    </div>
  </div>

  <div class="mg-profile-editor-error mg-hidden" data-editor-error role="alert">
    <h2>Unable to load the profile editor</h2>
    <p data-editor-error-message>The profile editor could not be loaded.</p>
    <button class="mg-btn mg-btn-primary" type="button" data-editor-retry>Try again</button>
  </div>

  <div class="mg-hidden" data-editor-content>
    <header class="mg-profile-editor-header">
      <div>
        <div class="mg-profile-editor-kicker">Owner workspace</div>
        <h2>Profile editor</h2>
        <p>Build, preview, and publish the identity people see across your Microgifter profile and storefront.</p>
      </div>
      <div class="mg-profile-editor-header-actions">
        <div class="mg-profile-editor-state">
          <span class="mg-profile-editor-pill" data-editor-status-pill>Draft</span>
          <span class="mg-profile-editor-score" data-editor-score>0% complete</span>
        </div>
        <a class="mg-btn mg-btn-ghost" href="#" target="_blank" rel="noopener" data-editor-preview-link>Owner preview</a>
        <button class="mg-btn mg-btn-primary" type="button" data-editor-save>Save changes</button>
      </div>
    </header>

    <div class="mg-profile-editor-workspace">
      <nav class="mg-profile-editor-nav" aria-label="Profile editor sections">
        <button type="button" class="is-active" data-editor-nav="identity"><strong>Identity</strong><span>Name, address, biography</span></button>
        <button type="button" data-editor-nav="links"><strong>Links</strong><span>Public destinations</span></button>
        <button type="button" data-editor-nav="sections"><strong>Sections</strong><span>Custom profile content</span></button>
        <button type="button" data-editor-nav="content"><strong>Public content</strong><span>Store, posts, plans, tips</span></button>
        <button type="button" data-editor-nav="media"><strong>Media</strong><span>Avatar and cover</span></button>
        <button type="button" data-editor-nav="publish"><strong>Publish</strong><span>Readiness and visibility</span></button>
      </nav>

      <main class="mg-profile-editor-main">
        <section class="mg-profile-editor-section" id="profile-editor-identity" data-editor-section="identity">
          <header><span>Section 1</span><h3>Profile identity</h3><p>Control the public name, address, biography, and account type used across the profile experience.</p></header>
          <form data-profile-editor-form novalidate>
            <div class="mg-profile-editor-field-grid">
              <label class="mg-profile-editor-field">
                <span>Display name <small data-counter-for="display_name">0/120</small></span>
                <input name="display_name" maxlength="120" autocomplete="name" required>
                <em>The primary name shown on your profile.</em>
              </label>
              <label class="mg-profile-editor-field">
                <span>Public profile address</span>
                <div class="mg-profile-slug-field"><span>/profile.php?slug=</span><input name="slug" maxlength="110" autocomplete="off" required></div>
                <em data-editor-slug-message>Lowercase letters, numbers, and hyphens only.</em>
              </label>
            </div>

            <label class="mg-profile-editor-field">
              <span>Headline <small data-counter-for="headline">0/180</small></span>
              <input name="headline" maxlength="180" placeholder="Local gifting creator, merchant, or customer">
              <em>A concise description shown directly below your name.</em>
            </label>

            <label class="mg-profile-editor-field">
              <span>Biography <small data-counter-for="bio">0/5000</small></span>
              <textarea name="bio" rows="7" maxlength="5000" placeholder="Tell people what you create, sell, or support on Microgifter."></textarea>
            </label>

            <div class="mg-profile-editor-field-grid">
              <label class="mg-profile-editor-field">
                <span>Location <small data-counter-for="location_label">0/160</small></span>
                <input name="location_label" maxlength="160" placeholder="Phoenix, AZ">
              </label>
              <label class="mg-profile-editor-field">
                <span>Website</span>
                <input name="website_url" inputmode="url" placeholder="https://example.com">
              </label>
            </div>

            <div class="mg-profile-editor-field-grid">
              <label class="mg-profile-editor-field">
                <span>Profile type</span>
                <select name="profile_type">
                  <option value="customer">Customer</option>
                  <option value="creator">Creator</option>
                  <option value="merchant">Merchant</option>
                  <option value="marketing_affiliate">Marketing affiliate</option>
                </select>
              </label>
              <label class="mg-profile-editor-field">
                <span>Visibility</span>
                <select name="visibility">
                  <option value="public">Public — discoverable and indexable</option>
                  <option value="unlisted">Unlisted — direct link only</option>
                  <option value="private">Private — owner preview only</option>
                </select>
              </label>
            </div>

            <input type="hidden" name="status" value="draft">
            <input type="hidden" name="avatar_url" value="">
            <input type="hidden" name="cover_url" value="">
            <div class="mg-profile-editor-form-status" data-editor-form-status role="status" aria-live="polite"></div>
          </form>
        </section>

        <section class="mg-profile-editor-section" id="profile-editor-links" data-editor-section="links">
          <header class="mg-profile-editor-section-header"><div><span>Section 2</span><h3>Profile links</h3><p>Add up to twelve destinations and control their order and public visibility.</p></div><button class="mg-btn mg-btn-soft" type="button" data-editor-add-link>Add link</button></header>
          <div class="mg-profile-editor-sort-list" data-editor-links></div>
          <div class="mg-profile-editor-empty mg-hidden" data-editor-links-empty><strong>No profile links yet.</strong><span>Add a website, shop, social account, portfolio, or newsletter.</span></div>
          <div class="mg-profile-editor-inline-actions"><button class="mg-btn mg-btn-primary" type="button" data-editor-save-links>Save links</button><span data-editor-links-status role="status" aria-live="polite"></span></div>
        </section>

        <section class="mg-profile-editor-section" id="profile-editor-sections" data-editor-section="sections">
          <header class="mg-profile-editor-section-header"><div><span>Section 2</span><h3>Custom sections</h3><p>Build structured profile content, reorder it, and choose what is publicly active.</p></div><button class="mg-btn mg-btn-soft" type="button" data-editor-add-section>Add section</button></header>
          <div class="mg-profile-editor-sort-list" data-editor-sections></div>
          <div class="mg-profile-editor-empty mg-hidden" data-editor-sections-empty><strong>No custom sections yet.</strong><span>Add an about block, story, highlights, FAQ, contact information, or custom content.</span></div>
          <div class="mg-profile-editor-inline-actions"><button class="mg-btn mg-btn-primary" type="button" data-editor-save-sections>Save sections</button><span data-editor-sections-status role="status" aria-live="polite"></span></div>
        </section>

        <section class="mg-profile-editor-section" id="profile-editor-content" data-editor-section="content">
          <header><span>Section 3</span><h3>Public content</h3><p>Review the canonical storefront, product, post, subscription, audience, and tip authorities attached to this profile.</p></header>
          <div class="mg-profile-editor-summary-grid" data-editor-summary-grid>
            <article data-summary-card="storefront"><span>Storefront</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="/merchant-storefront.php">Manage storefront</a></article>
            <article data-summary-card="products"><span>Products</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="/merchant-products.php">Manage products</a></article>
            <article data-summary-card="posts"><span>Posts</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="#" data-summary-post-link>View public updates</a></article>
            <article data-summary-card="subscriptions"><span>Memberships</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="/account-subscriptions.php">Manage memberships</a></article>
            <article data-summary-card="tip"><span>Tipping</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="/wallet.php">Open wallet</a></article>
            <article data-summary-card="audience"><span>Audience</span><strong>Loading…</strong><p></p><a class="mg-btn mg-btn-ghost" href="#" data-summary-public-link>Open profile</a></article>
          </div>
        </section>

        <section class="mg-profile-editor-section" id="profile-editor-media" data-editor-section="media">
          <header><span>Section 4</span><h3>Profile media</h3><p>Upload owner-scoped images through the catalog asset authority. Published profiles serve them through the public media route.</p></header>
          <div class="mg-profile-media-grid">
            <article class="mg-profile-media-card" data-media-card="avatar">
              <div class="mg-profile-media-preview is-avatar" data-media-preview="avatar"><span>A</span></div>
              <div><h4>Avatar</h4><p>Square image, up to 5 MB. JPEG, PNG, WebP, or GIF.</p></div>
              <label class="mg-btn mg-btn-soft">Choose avatar<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-media-input="avatar" hidden></label>
              <button class="mg-btn mg-btn-ghost" type="button" data-media-remove="avatar">Remove</button>
              <div class="mg-profile-media-progress mg-hidden" data-media-progress="avatar"><span></span></div>
              <small data-media-status="avatar"></small>
            </article>
            <article class="mg-profile-media-card" data-media-card="cover">
              <div class="mg-profile-media-preview is-cover" data-media-preview="cover"><span>Cover</span></div>
              <div><h4>Cover image</h4><p>Wide image, up to 10 MB. JPEG, PNG, WebP, or GIF.</p></div>
              <label class="mg-btn mg-btn-soft">Choose cover<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-media-input="cover" hidden></label>
              <button class="mg-btn mg-btn-ghost" type="button" data-media-remove="cover">Remove</button>
              <div class="mg-profile-media-progress mg-hidden" data-media-progress="cover"><span></span></div>
              <small data-media-status="cover"></small>
            </article>
          </div>
        </section>

        <section class="mg-profile-editor-section" id="profile-editor-publish" data-editor-section="publish">
          <header><span>Section 4</span><h3>Preview and publish</h3><p>Compare the saved profile with the current draft and resolve required items before publishing.</p></header>
          <div class="mg-profile-publish-grid">
            <div class="mg-profile-readiness-card">
              <div class="mg-profile-readiness-head"><div><h4>Publish readiness</h4><p data-readiness-summary>Checking profile requirements…</p></div><strong data-readiness-score>0%</strong></div>
              <ul data-readiness-list></ul>
            </div>
            <div class="mg-profile-comparison-card">
              <h4>Saved versus current draft</h4>
              <dl>
                <div><dt>Saved state</dt><dd data-comparison-saved>Draft</dd></div>
                <div><dt>Draft changes</dt><dd data-comparison-changes>No unsaved changes</dd></div>
                <div><dt>Public address</dt><dd data-comparison-url>—</dd></div>
                <div><dt>Last saved</dt><dd data-comparison-updated>—</dd></div>
              </dl>
              <a class="mg-btn mg-btn-ghost" href="#" target="_blank" rel="noopener" data-editor-preview-link-secondary>Open owner preview</a>
            </div>
          </div>
          <div class="mg-profile-publish-actions">
            <button class="mg-btn mg-btn-soft" type="button" data-editor-save-draft>Save as draft</button>
            <button class="mg-btn mg-btn-primary" type="button" data-editor-publish>Publish profile</button>
            <button class="mg-btn mg-btn-ghost" type="button" data-editor-hide>Hide profile</button>
          </div>
          <div class="mg-profile-editor-form-status" data-editor-publish-status role="status" aria-live="polite"></div>
        </section>
      </main>

      <aside class="mg-profile-editor-preview" aria-label="Live profile preview">
        <div class="mg-profile-editor-preview-head"><div><span>Live draft preview</span><strong data-preview-state>Unsaved draft</strong></div><button type="button" data-preview-refresh>Reset preview</button></div>
        <article class="mg-profile-editor-preview-card">
          <div class="mg-profile-editor-preview-cover" data-preview-cover></div>
          <div class="mg-profile-editor-preview-body">
            <div class="mg-profile-editor-preview-avatar" data-preview-avatar><span>M</span></div>
            <span class="mg-profile-editor-preview-type" data-preview-type>Customer</span>
            <h3 data-preview-name>Microgifter profile</h3>
            <p class="mg-profile-editor-preview-headline" data-preview-headline>Your profile headline will appear here.</p>
            <p class="mg-profile-editor-preview-bio" data-preview-bio>Your biography preview will appear here as you type.</p>
            <div class="mg-profile-editor-preview-meta"><span data-preview-location>Location</span><span data-preview-visibility>Public</span></div>
            <div class="mg-profile-editor-preview-links" data-preview-links></div>
          </div>
        </article>
      </aside>
    </div>

    <div class="mg-profile-editor-dirty-bar mg-hidden" data-editor-dirty-bar role="status">
      <span><strong>Unsaved changes</strong><small data-editor-dirty-message>Your current draft differs from the saved profile.</small></span>
      <div><button class="mg-btn mg-btn-ghost" type="button" data-editor-discard>Discard</button><button class="mg-btn mg-btn-primary" type="button" data-editor-save-bottom>Save changes</button></div>
    </div>
  </div>
</section>
