<div class="mg-builder-sidebar-backdrop" data-builder-sidebar-backdrop hidden></div>
<aside class="mg-builder-sidebar mg-app-sidebar" id="product-builder-sidebar" data-builder-sidebar aria-label="Product builder controls" aria-hidden="false">
  <div class="mg-app-sidebar-brand mg-builder-brand-row">
    <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
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
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="simple_product" checked><span class="mg-builder-template-card"><span class="mg-builder-template-icon">▣</span><span class="mg-builder-template-copy"><strong>Simple product</strong><span>Fast voucher, credit, reward, or prepaid gift display.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="greeting_card"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">✉</span><span class="mg-builder-template-copy"><strong>Greeting card</strong><span>Cover, inside image, message, and claim details.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="multimedia_greeting_card"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">▶</span><span class="mg-builder-template-copy"><strong>Multi-media greeting card</strong><span>Greeting card with one audio or one video item.</span></span></span></label>
        <label class="mg-builder-template-option"><input type="radio" name="builder_type" value="simple_collab"><span class="mg-builder-template-card"><span class="mg-builder-template-icon">＋</span><span class="mg-builder-template-copy"><strong>Simple collab</strong><span>Invite multiple people to contribute to one gift.</span></span></span></label>
      </div>

      <div class="mg-builder-section-title mg-builder-section-spacer">Product details</div>
      <input id="merchantName" type="hidden" value="">
      <input id="productCategory" type="hidden" value="Voucher">
      <input id="discount" type="hidden" value="">
      <input id="claimCode" type="hidden" value="Merchant claim code">
      <div class="mg-builder-field"><label for="productTitle">Product or voucher title</label><input id="productTitle" value="" placeholder="Coffee for two" maxlength="160" autocomplete="off"></div>
      <div class="mg-builder-field"><label for="productDescription">Product description</label><textarea id="productDescription" maxlength="4000" placeholder="Describe what the customer receives, why it is valuable, and how it can be used."></textarea></div>
      <div class="mg-builder-upload"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="productImage">Product image</label><span class="mg-builder-help">Shown in the product preview · JPG, PNG, WebP, GIF</span></div><input id="productImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="thumbnail"><div class="mg-builder-media-preview" data-media-preview="thumbnail"><img alt="Product image preview"><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-grid-2"><div class="mg-builder-field"><label for="price">Value</label><input id="price" inputmode="decimal" value="" placeholder="25.00"></div><div class="mg-builder-field"><label for="currency">Currency</label><select id="currency"><option value="USD">USD</option><option value="CAD">CAD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></div></div>
      <div class="mg-builder-field"><label for="locationIds">Merchant locations</label><select id="locationIds" multiple size="4" data-location-select aria-describedby="locationHelp"><option value="" disabled>Loading active locations…</option></select><small id="locationHelp">Choose where customers can discover and verify this voucher. Your primary location is selected automatically.</small></div>
      <div class="mg-builder-field"><label class="mg-builder-check"><input id="allLocations" type="checkbox"> Available at all active merchant locations</label></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="gift">
      <div class="mg-builder-section-title">Gift experience</div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="headline">Card headline</label><input id="headline" value="" placeholder="HAPPY BIRTHDAY!" maxlength="180"></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="message">Inside message</label><textarea id="message" maxlength="4000" placeholder="Add the message the recipient will see inside the card."></textarea></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="signature">Sent from signature</label><input id="signature" value="" placeholder="Sent from Tom" maxlength="160"></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="recipient">Recipient note</label><input id="recipient" value="" placeholder="For friends, family, co-workers, or customers" maxlength="200"></div>
      <div class="mg-builder-section-title mg-builder-section-spacer" data-builder-types="greeting_card multimedia_greeting_card">Card text style</div>
      <div class="mg-builder-grid-2" data-builder-types="greeting_card multimedia_greeting_card"><div class="mg-builder-field"><label for="cardBgColor">Background</label><input id="cardBgColor" type="color" value="#ffffff"></div><div class="mg-builder-field"><label for="cardTextColor">Text color</label><input id="cardTextColor" type="color" value="#071225"></div></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="cardFontFamily">Font</label><select id="cardFontFamily"><option value="system">Modern Sans</option><option value="serif">Serif</option><option value="script">Script</option><option value="handwritten">Handwritten</option><option value="display">Display</option></select></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="headlineFontSize">Headline size</label><input id="headlineFontSize" type="range" min="28" max="84" value="52"></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="messageFontSize">Message size</label><input id="messageFontSize" type="range" min="14" max="42" value="24"></div>
      <div class="mg-builder-field" data-builder-types="greeting_card multimedia_greeting_card"><label for="fontOpacity">Font opacity</label><input id="fontOpacity" type="range" min="35" max="100" value="100"></div>
      <div class="mg-builder-grid-2" data-builder-types="greeting_card multimedia_greeting_card"><div class="mg-builder-field"><label for="cardTextAlign">Text align</label><select id="cardTextAlign"><option value="center">Center</option><option value="left">Left</option><option value="right">Right</option></select></div><div class="mg-builder-field"><label for="cardTextVertical">Position</label><select id="cardTextVertical"><option value="center">Center</option><option value="top">Top</option><option value="bottom">Bottom</option></select></div></div>
      <button class="mg-btn mg-btn-soft mg-builder-reset-style" type="button" data-card-style-reset data-builder-types="greeting_card multimedia_greeting_card">Reset card style</button>
      <div class="mg-builder-field" data-builder-types="simple_collab"><label for="collaborationPrompt">Collaboration prompt</label><textarea id="collaborationPrompt" maxlength="1000" placeholder="Add a message or contribution to help complete this gift."></textarea><small>Used by the Simple Collab template.</small></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="media">
      <div class="mg-builder-section-title">Card media</div>
      <div class="mg-builder-upload" data-builder-types="greeting_card multimedia_greeting_card simple_collab"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="coverImage">Cover image</label><span class="mg-builder-help">Full-bleed card cover · JPG, PNG, WebP, GIF</span></div><input id="coverImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="cover"><div class="mg-builder-media-preview" data-media-preview="cover"><img alt="Cover image preview"><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-upload" data-builder-types="greeting_card multimedia_greeting_card"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="insideImage">Inside image</label><span class="mg-builder-help">Optional full-bleed inside artwork</span></div><input id="insideImage" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-asset-role="inside_cover"><div class="mg-builder-media-preview" data-media-preview="inside_cover"><img alt="Inside image preview"><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-upload" data-builder-types="multimedia_greeting_card"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="audioFile">Audio or voice note</label><span class="mg-builder-help">One audio item · cannot be combined with video</span></div><input id="audioFile" type="file" accept="audio/mpeg,audio/mp4,audio/wav,audio/ogg" data-asset-role="audio"><div class="mg-builder-media-preview" data-media-preview="audio"><audio controls hidden></audio><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-field" data-builder-types="multimedia_greeting_card"><label for="audioLabel">Audio label</label><input id="audioLabel" value="" placeholder="Play the audio greeting" maxlength="120"></div>
      <div class="mg-builder-upload" data-builder-types="multimedia_greeting_card"><div class="mg-builder-upload-head"><label class="mg-builder-upload-label" for="videoFile">Video message</label><span class="mg-builder-help">One video item · cannot be combined with audio</span></div><input id="videoFile" type="file" accept="video/mp4,video/webm,video/quicktime" data-asset-role="video"><div class="mg-builder-media-preview" data-media-preview="video"><video controls playsinline hidden></video><div class="mg-builder-upload-meta" data-media-meta></div></div></div>
      <div class="mg-builder-field" data-builder-types="multimedia_greeting_card"><label for="videoLabel">Video label</label><input id="videoLabel" value="" placeholder="Watch the video message" maxlength="120"></div>
    </section>

    <section class="mg-builder-panel" data-builder-panel="publish">
      <div class="mg-builder-section-title">Publishing</div>
      <div class="mg-builder-field"><label for="slug">Product URL slug</label><input id="slug" value="" placeholder="coffee-for-two" maxlength="160"></div>
      <input id="visibility" type="hidden" value="published">
      <div class="mg-builder-field"><label for="expiration">Expiration policy</label><input id="expiration" value="" placeholder="No expiration until issued" maxlength="180"></div>
      <div class="mg-builder-field"><label for="terms">Terms</label><textarea id="terms" maxlength="4000" placeholder="Valid at participating merchant locations. Subject to merchant availability."></textarea></div>
      <button class="mg-builder-primary" type="button" data-publish-product>Publish Product</button>
      <p class="mg-builder-help mg-builder-publish-help">Publishing creates the immutable voucher definition, adds it to your store and feed, and makes it discoverable at the selected merchant locations. It does not issue a voucher until a customer purchase, pickup, grant, promotion, contest, game, or API request occurs.</p>
    </section>
  </div>
</aside>
