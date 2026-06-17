<div class="mg-builder-sidebar-backdrop" data-builder-sidebar-backdrop hidden></div>
<aside class="mg-builder-sidebar mg-app-sidebar" id="product-builder-sidebar" data-builder-sidebar aria-label="Product builder controls">
  <div class="mg-app-sidebar-brand mg-builder-brand-row">
    <a class="mg-brand" href="/index.php" aria-label="Microgifter home"><span>Microgifter</span></a>
  </div>
  <button class="mg-builder-sidebar-close" type="button" data-builder-sidebar-close aria-label="Close builder controls">×</button>
  <div class="mg-builder-sidebar-scroll">
    <div class="mg-builder-steps" role="tablist" aria-label="Builder steps">
      <button class="mg-builder-step is-active" type="button" data-builder-step="product"><span>01</span>Product</button>
      <button class="mg-builder-step" type="button" data-builder-step="gift"><span>02</span>Gift</button>
      <button class="mg-builder-step" type="button" data-builder-step="media"><span>03</span>Media</button>
      <button class="mg-builder-step" type="button" data-builder-step="publish"><span>04</span>Publish</button>
    </div>

    <section class="mg-builder-panel is-active" data-builder-panel="product">
      <div class="mg-builder-section-title">Choose a template</div>
      <div class="mg-builder-template-grid">
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="simple_product" checked><span class="mg-builder-template-card"><span class="mg-builder-template-icon">▣</span><span class="mg-builder-template-copy"><strong>Simple product</strong><span>Fast product, voucher, credit, or reward display.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="greeting_card"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">✉</span><span class="mg-builder-template-copy"><strong>Greeting card</strong><span>Cover, inside image, message, and claim details.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="multimedia_greeting_card"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">▶</span><span class="mg-builder-template-copy"><strong>Multi-media greeting card</strong><span>Greeting card with one audio and one video item.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="simple_collab"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">＋</span><span class="mg-builder-template-copy"><strong>Simple collab</strong><span>Invite multiple people to contribute to one gift.</span></span></span></label>
      </div>

      <div class="mg-builder-section-title mg-builder-section-spacer">Product details</div>
      <div class="mg-builder-field"><label for="productTitle">Product or gift title</label><input id="productTitle" value="Coffee for two" maxlength="160" autocomplete="off"></div>
      <div class="mg-builder-field"><label for="merchantName">Merchant or brand</label><input id="merchantName" value="Local Coffee House" maxlength="160" autocomplete="off"></div>
      <div class="mg-builder-field"><label for="productCategory">Product category</label><select id="productCategory"><option>Prepaid gift</option><option>Voucher</option><option>Local reward</option><option>Workplace perk</option><option>Digital product</option><option>Prize</option><option>Credit</option></select></div>
      <div class="mg-builder-grid-2"><div class="mg-builder-field"><label for="price">Value</label><input id="price" inputmode="decimal" value="25.00"></div><div class="mg-builder-field"><label for="currency">Currency</label><select id="currency"><option value="USD">USD</option><option value="CAD">CAD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></div></div>
      <div class="mg-builder-field"><label for="discount">Offer or bonus</label><input id="discount" value="20% bonus" maxlength="120"></div>
      <div class="mg-builder-field"><label for="location">Location</label><input id="location" value="Phoenix, AZ" maxlength="160"></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="gift">
      <div class="mg-builder-section-title">Gift experience</div>
      <div class="mg-builder-field"><label for="headline">Card headline</label><input id="headline" value="A small gift, already waiting for you." maxlength="180"></div>
      <div class="mg-builder-field"><label for="message">Inside message</label><textarea id="message" maxlength="4000">I wanted to send something useful, local, and easy to claim. Enjoy this prepaid gift when the timing is right.</textarea></div>
      <div class="mg-builder-field"><label for="recipient">Recipient note</label><input id="recipient" value="For friends, family, co-workers, or customers" maxlength="200"></div>
      <div class="mg-builder-field"><label for="collaborationPrompt">Collaboration prompt</label><textarea id="collaborationPrompt" maxlength="1000">Add a message or contribution to help complete this gift.</textarea><small>Used by the Simple Collab template.</small></div>
      <div class="mg-builder-field"><label for="claimCode">Claim code label</label><input id="claimCode" value="Merchant claim code" maxlength="120"><small>The merchant sets and manages actual claim codes by location.</small></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="media">
      <div class="mg-builder-section-title">Product media</div>
      <div class="mg-builder-upload"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="coverImage">Cover image</label><span class="mg-builder-help">JPG, PNG, WebP, GIF</span></div><input id="coverImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="cover"><div class="mg-builder-media-preview" data-media-preview="cover"><img alt="Cover image preview"><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-upload"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="insideImage">Inside image</label><span class="mg-builder-help">One image per card</span></div><input id="insideImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="inside_cover"><div class="mg-builder-media-preview" data-media-preview="inside_cover"><img alt="Inside image preview"><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-upload"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="audioFile">Audio or voice note</label><span class="mg-builder-help">One audio item</span></div><input id="audioFile" type="file" accept="audio/mpeg,audio/mp4,audio/wav,audio/ogg" data-asset-role="audio"><div class="mg-builder-media-preview" data-media-preview="audio"><audio controls hidden></audio><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-field"><label for="audioLabel">Audio label</label><input id="audioLabel" value="Play the audio greeting" maxlength="120"></div>
      <div class="mg-builder-upload"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="videoFile">Video message</label><span class="mg-builder-help">One video item</span></div><input id="videoFile" type="file" accept="video/mp4,video/webm,video/quicktime" data-asset-role="video"><div class="mg-builder-media-preview" data-media-preview="video"><video controls playsinline hidden></video><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-field"><label for="videoLabel">Video label</label><input id="videoLabel" value="Watch the video message" maxlength="120"></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="publish">
      <div class="mg-builder-section-title">Publishing</div>
      <div class="mg-builder-field"><label for="slug">Product URL slug</label><input id="slug" value="coffee-for-two" maxlength="160"></div>
      <div class="mg-builder-field"><label for="visibility">Visibility</label><select id="visibility"><option value="draft">Draft</option><option value="private">Private preview</option><option value="published">Published</option></select></div>
      <div class="mg-builder-field"><label for="expiration">Expiration policy</label><input id="expiration" value="No expiration until issued" maxlength="180"></div>
      <div class="mg-builder-field"><label for="terms">Terms</label><textarea id="terms" maxlength="4000">Valid at participating merchant locations. Subject to merchant availability.</textarea></div>
      <button class="mg-builder-primary" type="button" data-publish-product>Publish Product</button>
      <p class="mg-builder-help mg-builder-publish-help">Publishing creates an immutable product version and a PPPM issuance template. It does not create an issued gift until a purchase, grant, contest, game, or API source requests one.</p>
    </section>
  </div>
</aside>