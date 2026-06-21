<?php
declare(strict_types=1);

$post_composer_id_suffix = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($post_composer_id_suffix ?? 'default'));
$post_composer_title_id = 'mg-feed-composer-title-' . ($post_composer_id_suffix !== '' ? $post_composer_id_suffix : 'default');
$post_composer_hidden = (bool) ($post_composer_hidden ?? true);
$post_composer_class = 'mg-feed-composer' . ($post_composer_hidden ? ' mg-hidden' : '');
?>
<section class="<?= mg_e($post_composer_class) ?>" data-post-composer aria-labelledby="<?= mg_e($post_composer_title_id) ?>">
  <div class="mg-feed-section-heading">
    <div>
      <span class="mg-kicker">Post composer</span>
      <h2 id="<?= mg_e($post_composer_title_id) ?>" data-composer-title>Create a post</h2>
      <p class="mg-feed-composer-intro">Write the update first, then add media or optional Microgifter links.</p>
    </div>
    <button class="mg-btn mg-btn-ghost" type="button" data-composer-close>Close</button>
  </div>

  <form data-post-form>
    <input type="hidden" name="post_id">

    <div class="mg-feed-compose-copy">
      <label>Title <span class="mg-feed-optional">Optional</span>
        <input type="text" name="headline" maxlength="240" placeholder="Give the post a clear headline">
      </label>
      <label>What do you want to share?
        <textarea name="body" rows="7" maxlength="10000" placeholder="Share an update, story, offer, event, or local gifting idea."></textarea>
      </label>
    </div>

    <section class="mg-feed-media-uploader" data-feed-media-uploader aria-labelledby="mg-feed-media-title-<?= mg_e($post_composer_id_suffix) ?>">
      <div class="mg-feed-media-uploader-head">
        <div>
          <span class="mg-kicker">Media</span>
          <h3 id="mg-feed-media-title-<?= mg_e($post_composer_id_suffix) ?>">Add photos, video, or audio</h3>
          <p>Upload up to eight items. Drag attachments to choose their order; the first item becomes the lead media.</p>
        </div>
        <span data-feed-upload-count>0 of 8 attached</span>
      </div>
      <div class="mg-feed-upload-grid">
        <label class="mg-feed-upload-card">
          <span class="mg-feed-upload-kind">Photo</span>
          <strong>Add photos</strong>
          <small>JPG, PNG, GIF, WebP, or AVIF. Up to 12 MB each.</small>
          <span class="mg-btn mg-btn-soft">Choose photos</span>
          <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" multiple data-feed-upload-input="image">
        </label>
        <label class="mg-feed-upload-card">
          <span class="mg-feed-upload-kind">Video</span>
          <strong>Add video</strong>
          <small>MP4, WebM, or MOV. Up to 200 MB each.</small>
          <span class="mg-btn mg-btn-soft">Choose video</span>
          <input type="file" accept="video/mp4,video/webm,video/quicktime" multiple data-feed-upload-input="video">
        </label>
        <label class="mg-feed-upload-card">
          <span class="mg-feed-upload-kind">Audio</span>
          <strong>Add audio</strong>
          <small>MP3, WAV, OGG, or M4A. Up to 50 MB each.</small>
          <span class="mg-btn mg-btn-soft">Choose audio</span>
          <input type="file" accept="audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a" multiple data-feed-upload-input="audio">
        </label>
      </div>
      <div class="mg-feed-upload-status" data-feed-upload-status role="status" aria-live="polite"></div>
      <div class="mg-feed-upload-list" data-feed-upload-list aria-label="Attached media. Drag to reorder."></div>
    </section>

    <div class="mg-feed-publish-settings">
      <label>Who can see this post?
        <select name="visibility">
          <option value="public">Everyone</option>
          <option value="unlisted">Anyone with the link</option>
          <option value="followers">Followers</option>
          <option value="subscribers">Subscribers</option>
          <option value="premium">Premium members</option>
          <option value="private">Only me / draft</option>
        </select>
      </label>
      <p>The audience can be changed later from My posts.</p>
    </div>

    <details class="mg-feed-attachments mg-feed-advanced">
      <summary><span>Advanced options</span><small>Link products, gifts, plans, or external media</small></summary>
      <div class="mg-feed-advanced-body">
        <div class="mg-feed-form-grid">
          <label>Post format
            <select name="post_type">
              <option value="simple">Automatic</option>
              <option value="image">Image post</option>
              <option value="audio">Audio post</option>
              <option value="video">Video post</option>
              <option value="greeting_card">Greeting card</option>
              <option value="multimedia_card">Multimedia card</option>
              <option value="collab">Collaboration</option>
            </select>
          </label>
          <label>External link
            <input type="url" name="link_url" maxlength="500" placeholder="https://example.com">
          </label>
        </div>

        <details class="mg-feed-technical-links">
          <summary>Technical Microgifter linking</summary>
          <p>Use these IDs only when linking an existing product, Microgift, or subscription plan.</p>
          <div class="mg-feed-form-grid">
            <label>Product public ID
              <input type="text" name="product_id" maxlength="80" placeholder="Optional product UUID">
            </label>
            <label>Microgift public ID
              <input type="text" name="microgift_id" maxlength="80" placeholder="Optional Microgift UUID">
            </label>
            <label>Subscription plan public ID
              <input type="text" name="subscription_plan_id" maxlength="80" placeholder="Required for subscriber visibility">
            </label>
          </div>
        </details>

        <details class="mg-feed-technical-links">
          <summary>External media URLs</summary>
          <p>Uploaded media is listed here automatically. You can also add one secure media URL per line.</p>
          <label>Media URLs
            <textarea name="media_urls" rows="4" placeholder="One image, audio, video, or link URL per line. Maximum 8 total."></textarea>
          </label>
        </details>
      </div>
    </details>

    <div class="mg-feed-composer-footer">
      <div class="mg-feed-composer-actions">
        <button class="mg-btn mg-btn-soft" type="button" data-post-save-draft>Save draft</button>
        <button class="mg-btn mg-btn-primary" type="submit" data-post-publish>Publish post</button>
        <button class="mg-btn mg-btn-ghost mg-hidden" type="button" data-post-cancel-edit>Cancel edit</button>
      </div>
      <div class="mg-feed-action-status" data-composer-status role="status" aria-live="polite"></div>
    </div>
  </form>
</section>
