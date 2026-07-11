window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  if (!modal) return;

  var MG = window.Microgifter;
  var views = Array.from(modal.querySelectorAll('[data-create-center-view]'));
  var titleNode = modal.querySelector('[data-create-center-title]');
  var descriptionNode = modal.querySelector('[data-create-center-description]');
  var loaded = { product: false, campaign: false, storefront: false };
  var storefrontState = null;

  var viewCopy = {
    home: ['Create something new', 'Choose a tool, complete the form, and submit without leaving the current page.'],
    product: ['Create a product', 'Build a draft or publish a voucher directly from the full-screen create center.'],
    campaign: ['Create a campaign', 'Configure the campaign essentials and save it immediately.'],
    reward: ['Create a reward', 'Build a reusable reward template for campaigns and distribution.'],
    storefront: ['Configure your storefront', 'Save the public identity, theme, and featured products.'],
    location: ['Add a merchant location', 'Register a claim, pickup, check-in, or redemption site.']
  };

  function unwrap(response) {
    return response && response.data ? response.data : response;
  }

  function csrf() {
    return MG.getCsrfToken ? MG.getCsrfToken() : ((document.querySelector('meta[name="csrf-token"]') || {}).content || '');
  }

  function formFor(type) {
    return modal.querySelector('[data-create-inline-form="' + type + '"]');
  }

  function statusFor(type) {
    return modal.querySelector('[data-create-inline-status="' + type + '"]');
  }

  function successFor(type) {
    return modal.querySelector('[data-create-inline-success="' + type + '"]');
  }

  function setStatus(type, message, tone) {
    var node = statusFor(type);
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-create-inline-status' + (message ? ' is-visible' : '') + (tone ? ' is-' + tone : '');
  }

  function clearSuccess(type) {
    var node = successFor(type);
    if (node) node.hidden = true;
  }

  function showSuccess(type, message, href, label) {
    var node = successFor(type);
    if (!node) return;
    var messageNode = node.querySelector('[data-create-success-message]');
    var link = node.querySelector('[data-create-success-link]');
    if (messageNode) messageNode.textContent = message || 'Saved successfully.';
    if (link) {
      link.href = href || link.getAttribute('href') || '#';
      if (label) link.textContent = label;
    }
    node.hidden = false;
    node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(busy);
    button.textContent = busy ? (label || 'Saving…') : button.dataset.originalText;
  }

  function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 110);
  }

  function moneyToCents(value) {
    var amount = Number(String(value || '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100)) : 0;
  }

  function selectedValues(select) {
    if (!select) return [];
    return Array.from(select.selectedOptions || []).map(function (option) { return option.value; }).filter(Boolean);
  }

  function showView(name) {
    name = name || 'home';
    views.forEach(function (view) {
      var active = view.dataset.createCenterView === name;
      view.hidden = !active;
      view.classList.toggle('is-active', active);
    });
    modal.querySelectorAll('[data-create-tool-key]').forEach(function (link) {
      link.classList.toggle('is-active', link.dataset.createToolKey === name);
    });
    var home = modal.querySelector('.mg-create-center-home');
    if (home) home.classList.toggle('is-active', name === 'home');
    var copy = viewCopy[name] || viewCopy.home;
    if (titleNode) titleNode.textContent = copy[0];
    if (descriptionNode) descriptionNode.textContent = copy[1];
    var content = modal.querySelector('.mg-create-center-content');
    if (content) content.scrollTop = 0;
    ensureContext(name);
    window.requestAnimationFrame(function () {
      var activeView = modal.querySelector('[data-create-center-view="' + name + '"]');
      var first = activeView && activeView.querySelector('input:not([type="hidden"]),select,textarea,button');
      if (first && typeof first.focus === 'function') first.focus({ preventScroll: true });
    });
  }

  async function uploadProductImage(file) {
    if (!file) return '';
    var body = new FormData();
    body.append('file', file);
    body.append('role', 'cover');
    body.append('csrf_token', csrf());
    var response = await MG.api('/api/catalog/upload.php', { method: 'POST', body: body });
    var data = unwrap(response) || {};
    if (!data.asset_id) throw new Error('The product image upload did not return an asset ID.');
    return String(data.asset_id);
  }

  async function loadProductContext(force) {
    if (loaded.product && !force) return;
    var form = formFor('product');
    if (!form) return;
    var select = form.querySelector('[data-create-product-locations]');
    if (select) select.innerHTML = '<option disabled>Loading active locations…</option>';
    try {
      var response = await MG.get('/api/catalog/builder-draft.php');
      var data = unwrap(response) || {};
      var locations = data.locations || [];
      if (select) {
        select.replaceChildren();
        locations.forEach(function (location) {
          var option = document.createElement('option');
          option.value = String(location.public_id || '');
          var place = [location.city, location.region].filter(Boolean).join(', ');
          option.textContent = String(location.name || 'Location') + (place ? ' · ' + place : '') + (location.is_primary ? ' · Primary' : '');
          option.selected = Boolean(location.is_primary);
          select.appendChild(option);
        });
        if (!locations.length) {
          var empty = document.createElement('option');
          empty.disabled = true;
          empty.textContent = 'Add an active merchant location before publishing';
          select.appendChild(empty);
        }
      }
      loaded.product = true;
      setStatus('product', locations.length ? 'Product form ready.' : 'Save as draft, or add a location before publishing.', locations.length ? 'ready' : 'warning');
    } catch (error) {
      setStatus('product', error.message || 'Unable to load product locations.', 'error');
    }
  }

  async function submitProduct(form) {
    var button = form.querySelector('[type="submit"]');
    clearSuccess('product');
    var title = String(form.elements.title.value || '').trim();
    var valueCents = moneyToCents(form.elements.value_amount.value);
    var locationSelect = form.querySelector('[data-create-product-locations]');
    var locationIds = selectedValues(locationSelect);
    var allLocations = Boolean(form.elements.all_locations && form.elements.all_locations.checked);
    var publish = form.elements.save_mode.value === 'publish';
    if (!title) return setStatus('product', 'Enter a product title.', 'error');
    if (valueCents < 1) return setStatus('product', 'Enter a product value greater than zero.', 'error');
    if (publish && !allLocations && !locationIds.length) return setStatus('product', 'Choose at least one active location before publishing.', 'error');

    setBusy(button, true, 'Creating…');
    setStatus('product', 'Creating product…', 'working');
    try {
      var imageInput = form.elements.product_image;
      var coverAsset = imageInput && imageInput.files && imageInput.files[0] ? await uploadProductImage(imageInput.files[0]) : '';
      var payload = {
        title: title,
        merchant_name: '',
        product_category: 'Voucher',
        description: String(form.elements.description.value || '').trim(),
        value_cents: valueCents,
        currency: String(form.elements.currency.value || 'USD'),
        offer: '',
        location_ids: locationIds,
        all_locations: allLocations,
        headline: '',
        message: '',
        recipient_note: '',
        collaboration_prompt: '',
        audio_label: '',
        video_label: '',
        claim_code_label: 'Merchant claim code',
        slug: '',
        visibility: 'public',
        demo: false,
        terms: { note: String(form.elements.terms.value || '').trim() },
        expiration_policy: { label: String(form.elements.expiration.value || '').trim() }
      };
      var assets = coverAsset ? { cover: coverAsset } : {};
      var saveResponse = await MG.post('/api/catalog/builder-draft.php', {
        action: 'save', product_id: '', builder_type: 'simple_product', payload: payload, assets: assets, lock_version: 0, csrf_token: csrf()
      });
      var saved = unwrap(saveResponse) || {};
      var result = saved;
      if (publish) {
        var publishResponse = await MG.post('/api/catalog/builder-draft.php', {
          action: 'publish', product_id: saved.product_id, builder_type: 'simple_product', payload: payload, assets: assets,
          lock_version: Number(saved.lock_version || 0), csrf_token: csrf()
        });
        result = unwrap(publishResponse) || {};
      }
      var editorUrl = saved.product_id ? '/build.php?id=' + encodeURIComponent(saved.product_id) : '/merchant-products.php';
      var destination = result.product_url || editorUrl;
      setStatus('product', publish ? 'Product published successfully.' : 'Product draft created successfully.', 'success');
      showSuccess('product', publish ? 'The product is published and ready to view.' : 'The draft is saved and ready for additional editing.', destination, publish ? 'View product' : 'Open product draft');
    } catch (error) {
      setStatus('product', error.message || 'Unable to create the product.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function loadCampaignContext(force) {
    if (loaded.campaign && !force) return;
    var form = formFor('campaign');
    if (!form) return;
    var select = form.querySelector('[data-create-campaign-rewards]');
    try {
      var response = await MG.get('/api/merchant/reward-templates.php?status=active');
      var templates = (unwrap(response) || {}).templates || [];
      if (select) {
        select.innerHTML = '<option value="">No reward attached</option>';
        templates.forEach(function (template) {
          var option = document.createElement('option');
          option.value = String(template.id || '');
          option.textContent = String(template.title || 'Reward template');
          select.appendChild(option);
        });
      }
      loaded.campaign = true;
      setStatus('campaign', templates.length ? 'Campaign form ready.' : 'No active rewards found. Draft campaigns can still be saved.', templates.length ? 'ready' : 'warning');
    } catch (error) {
      setStatus('campaign', error.message || 'Unable to load reward templates.', 'error');
    }
  }

  async function submitCampaign(form) {
    var button = form.querySelector('[type="submit"]');
    clearSuccess('campaign');
    var data = Object.fromEntries(new FormData(form).entries());
    data.campaign_id = '';
    data.agent_discoverable = form.elements.agent_discoverable && form.elements.agent_discoverable.checked ? 1 : 0;
    if (!String(data.title || '').trim()) return setStatus('campaign', 'Enter a campaign title.', 'error');
    if (String(data.status || '') === 'active' && !String(data.reward_template_id || '').trim()) {
      return setStatus('campaign', 'Choose an active reward template before activating the campaign.', 'error');
    }
    setBusy(button, true, 'Saving…');
    setStatus('campaign', 'Saving campaign…', 'working');
    try {
      var response = await MG.post('/api/merchant/campaigns.php', data);
      var result = unwrap(response) || {};
      setStatus('campaign', response.message || 'Campaign saved.', 'success');
      showSuccess('campaign', response.message || 'The campaign is saved and available in Campaigns.', result.public_url || '/merchant-campaigns.php', result.public_url ? 'Open public campaign' : 'View campaigns');
    } catch (error) {
      setStatus('campaign', error.message || 'Unable to save the campaign.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function submitReward(form) {
    var button = form.querySelector('[type="submit"]');
    clearSuccess('reward');
    if (!String(form.elements.title.value || '').trim()) return setStatus('reward', 'Enter a reward title.', 'error');
    var body = new FormData(form);
    body.set('agent_discoverable', form.elements.agent_discoverable && form.elements.agent_discoverable.checked ? '1' : '0');
    setBusy(button, true, 'Saving…');
    setStatus('reward', 'Saving reward…', 'working');
    try {
      var response = await MG.post('/api/merchant/reward-templates.php', body);
      setStatus('reward', response.message || 'Reward template saved.', 'success');
      showSuccess('reward', response.message || 'The reward is saved and ready for campaign use.', '/merchant-reward-templates.php', 'View rewards');
    } catch (error) {
      setStatus('reward', error.message || 'Unable to save the reward.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  function storefrontRevision(payload) {
    return payload && (payload.draft || payload.published) ? (payload.draft || payload.published) : {};
  }

  function renderStorefrontProducts(payload) {
    var mount = modal.querySelector('[data-create-storefront-products]');
    if (!mount) return;
    var selected = {};
    (payload.products || []).forEach(function (item) { selected[String(item.public_id || item.product_id || '')] = item; });
    var products = payload.available_products || [];
    mount.replaceChildren();
    if (!products.length) {
      var empty = document.createElement('div');
      empty.className = 'mg-create-loading';
      empty.textContent = 'No published products are available yet.';
      mount.appendChild(empty);
      return;
    }
    products.forEach(function (product) {
      var id = String(product.public_id || '');
      var row = document.createElement('label');
      row.className = 'mg-create-product-choice';
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.name = 'storefront_products[]';
      input.value = id;
      input.checked = Boolean(selected[id]);
      var copy = document.createElement('span');
      var strong = document.createElement('strong');
      strong.textContent = String(product.title || 'Published product');
      var small = document.createElement('small');
      small.textContent = String(product.product_type || 'product').replace(/_/g, ' ') + ' · ' + ((Number(product.unit_value_cents || 0) / 100).toFixed(2)) + ' ' + String(product.currency || 'USD');
      copy.append(strong, small);
      row.append(input, copy);
      mount.appendChild(row);
    });
  }

  function fillStorefront(payload) {
    var form = formFor('storefront');
    if (!form) return;
    storefrontState = payload;
    var revision = storefrontRevision(payload);
    var store = payload.storefront || {};
    form.elements.display_name.value = revision.display_name || store.display_name || '';
    form.elements.slug.value = store.slug || '';
    form.elements.headline.value = revision.headline || '';
    form.elements.description.value = revision.description || '';
    form.elements.contact_email.value = revision.contact && revision.contact.email || '';
    form.elements.contact_phone.value = revision.contact && revision.contact.phone || '';
    form.elements.website_url.value = revision.contact && revision.contact.website || '';
    form.elements.accent.value = revision.theme && revision.theme.accent || '#2563eb';
    renderStorefrontProducts(payload);
  }

  async function loadStorefrontContext(force) {
    if (loaded.storefront && !force) return;
    try {
      setStatus('storefront', 'Loading storefront…', 'working');
      var response = await MG.get('/api/merchant/storefront.php');
      fillStorefront(unwrap(response) || {});
      loaded.storefront = true;
      setStatus('storefront', 'Storefront form ready.', 'ready');
    } catch (error) {
      setStatus('storefront', error.message || 'Unable to load storefront settings.', 'error');
    }
  }

  function storefrontProducts() {
    return Array.from(modal.querySelectorAll('[name="storefront_products[]"]:checked')).map(function (input, index) {
      return { product_id: input.value, sort_order: index, is_featured: index === 0 ? 1 : 0, visibility: 'visible' };
    });
  }

  async function submitStorefront(form) {
    var button = form.querySelector('[type="submit"]');
    clearSuccess('storefront');
    var displayName = String(form.elements.display_name.value || '').trim();
    var slug = slugify(form.elements.slug.value || displayName);
    var products = storefrontProducts();
    var publish = form.elements.save_mode.value === 'publish';
    if (!displayName) return setStatus('storefront', 'Enter a store name.', 'error');
    if (!slug) return setStatus('storefront', 'Enter a valid public storefront address.', 'error');
    if (publish && !products.length) return setStatus('storefront', 'Select at least one published product before publishing.', 'error');
    var payload = {
      action: 'save', display_name: displayName, slug: slug,
      headline: String(form.elements.headline.value || '').trim(),
      description: String(form.elements.description.value || '').trim(),
      logo_asset_id: '', cover_asset_id: '',
      contact: {
        email: String(form.elements.contact_email.value || '').trim(),
        phone: String(form.elements.contact_phone.value || '').trim(),
        website: String(form.elements.website_url.value || '').trim()
      },
      theme: { accent: String(form.elements.accent.value || '#2563eb').trim().toLowerCase() },
      products: products
    };
    setBusy(button, true, publish ? 'Publishing…' : 'Saving…');
    setStatus('storefront', publish ? 'Saving and publishing storefront…' : 'Saving storefront draft…', 'working');
    try {
      await MG.post('/api/merchant/storefront.php', payload);
      if (publish) await MG.post('/api/merchant/storefront.php', { action: 'publish' });
      loaded.storefront = false;
      await loadStorefrontContext(true);
      var publicUrl = storefrontState && storefrontState.public_url ? storefrontState.public_url : '/merchant-storefront.php';
      setStatus('storefront', publish ? 'Storefront published successfully.' : 'Storefront draft saved successfully.', 'success');
      showSuccess('storefront', publish ? 'The storefront is now live.' : 'The storefront draft is saved.', publicUrl, publish && publicUrl !== '/merchant-storefront.php' ? 'Open storefront' : 'View storefront manager');
    } catch (error) {
      setStatus('storefront', error.message || 'Unable to save the storefront.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  async function submitLocation(form) {
    var button = form.querySelector('[type="submit"]');
    clearSuccess('location');
    var data = Object.fromEntries(new FormData(form).entries());
    data.claim_code = String(data.claim_code || '').trim().toUpperCase();
    data.is_primary = form.elements.is_primary && form.elements.is_primary.checked ? 1 : 0;
    if (!String(data.name || '').trim()) return setStatus('location', 'Enter a location title.', 'error');
    if (!String(data.address_line1 || '').trim()) return setStatus('location', 'Enter the location address.', 'error');
    if (!data.claim_code) return setStatus('location', 'Enter a protected claim code.', 'error');
    setBusy(button, true, 'Saving…');
    setStatus('location', 'Saving location…', 'working');
    try {
      var response = await MG.post('/api/merchant/locations.php', data);
      var result = unwrap(response) || {};
      var note = response.message || 'Location saved.';
      if (result.claim_code_last4) note += ' Claim code ends in ' + result.claim_code_last4 + '.';
      setStatus('location', note, 'success');
      showSuccess('location', note, '/merchant-locations.php', 'View locations');
    } catch (error) {
      setStatus('location', error.message || 'Unable to save the location.', 'error');
    } finally {
      setBusy(button, false);
    }
  }

  function resetForm(type) {
    var form = formFor(type);
    if (!form) return;
    clearSuccess(type);
    form.reset();
    setStatus(type, '', '');
    if (type === 'product') {
      loaded.product = false;
      loadProductContext(true);
    }
    if (type === 'campaign') {
      if (form.elements.per_user_limit) form.elements.per_user_limit.value = '1';
      loaded.campaign = false;
      loadCampaignContext(true);
    }
    if (type === 'reward' && form.elements.per_user_limit) form.elements.per_user_limit.value = '1';
    if (type === 'storefront') {
      loaded.storefront = false;
      loadStorefrontContext(true);
    }
    if (type === 'location') {
      if (form.elements.country_code) form.elements.country_code.value = 'US';
      if (form.elements.timezone) form.elements.timezone.value = 'America/Phoenix';
      if (form.elements.status) form.elements.status.value = 'active';
      if (form.elements.check_in_radius_meters) form.elements.check_in_radius_meters.value = '150';
    }
  }

  function ensureContext(name) {
    if (!MG.get || !MG.post) return;
    if (name === 'product') loadProductContext(false);
    if (name === 'campaign') loadCampaignContext(false);
    if (name === 'storefront') loadStorefrontContext(false);
  }

  modal.addEventListener('click', function (event) {
    var inline = event.target.closest('[data-create-inline-target]');
    if (inline && modal.contains(inline)) {
      event.preventDefault();
      event.stopImmediatePropagation();
      showView(inline.dataset.createInlineTarget);
      return;
    }
    var home = event.target.closest('[data-create-center-home]');
    if (home && modal.contains(home)) {
      event.preventDefault();
      showView('home');
      return;
    }
    var reset = event.target.closest('[data-create-inline-reset]');
    if (reset && modal.contains(reset)) {
      event.preventDefault();
      resetForm(reset.dataset.createInlineReset);
    }
  }, true);

  modal.querySelectorAll('[data-create-inline-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var type = form.dataset.createInlineForm;
      if (!MG.post) return setStatus(type, 'Creation tools are unavailable on this page.', 'error');
      if (type === 'product') submitProduct(form);
      if (type === 'campaign') submitCampaign(form);
      if (type === 'reward') submitReward(form);
      if (type === 'storefront') submitStorefront(form);
      if (type === 'location') submitLocation(form);
    });
  });

  var productImage = modal.querySelector('[data-create-inline-form="product"] input[name="product_image"]');
  if (productImage) productImage.addEventListener('change', function () {
    var label = modal.querySelector('[data-create-product-image-name]');
    if (label) label.textContent = productImage.files && productImage.files[0] ? productImage.files[0].name : 'Choose a JPG, PNG, WebP, or GIF.';
  });

  var productAll = modal.querySelector('[data-create-inline-form="product"] input[name="all_locations"]');
  if (productAll) productAll.addEventListener('change', function () {
    var select = modal.querySelector('[data-create-product-locations]');
    if (select) select.disabled = productAll.checked;
  });

  var storefrontName = modal.querySelector('[data-create-inline-form="storefront"] input[name="display_name"]');
  var storefrontSlug = modal.querySelector('[data-create-inline-form="storefront"] input[name="slug"]');
  if (storefrontName && storefrontSlug) storefrontName.addEventListener('input', function () {
    if (!storefrontSlug.dataset.touched) storefrontSlug.value = slugify(storefrontName.value);
  });
  if (storefrontSlug) storefrontSlug.addEventListener('input', function () {
    storefrontSlug.dataset.touched = 'true';
    storefrontSlug.value = slugify(storefrontSlug.value);
  });

  new MutationObserver(function () {
    if (!modal.hidden) showView('home');
  }).observe(modal, { attributes: true, attributeFilter: ['hidden'] });

  showView('home');
})(window, document);
