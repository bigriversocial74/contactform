window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var modal = document.querySelector('[data-create-menu]');
  var form = modal && modal.querySelector('[data-create-inline-form="storefront"]');
  var MG = window.Microgifter;
  if (!modal || !form) return;

  function unwrap(response) {
    return response && response.data ? response.data : response;
  }

  function revision(payload) {
    return payload && (payload.draft || payload.published) ? (payload.draft || payload.published) : {};
  }

  function slugify(value) {
    return String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 110);
  }

  function status(message, tone) {
    var node = modal.querySelector('[data-create-inline-status="storefront"]');
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-create-inline-status' + (message ? ' is-visible' : '') + (tone ? ' is-' + tone : '');
  }

  function busy(button, active, label) {
    if (!button) return;
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(active);
    button.textContent = active ? (label || 'Saving…') : button.dataset.originalText;
  }

  function products() {
    return Array.from(modal.querySelectorAll('[name="storefront_products[]"]:checked')).map(function (input, index) {
      return {
        product_id: input.value,
        sort_order: index,
        is_featured: index === 0 ? 1 : 0,
        visibility: 'visible'
      };
    });
  }

  function showSuccess(message, href, label) {
    var node = modal.querySelector('[data-create-inline-success="storefront"]');
    if (!node) return;
    var messageNode = node.querySelector('[data-create-success-message]');
    var link = node.querySelector('[data-create-success-link]');
    if (messageNode) messageNode.textContent = message;
    if (link) {
      link.href = href || '/merchant-storefront.php';
      link.textContent = label || 'View storefront manager';
    }
    node.hidden = false;
    node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function saveStorefront(event) {
    event.preventDefault();
    event.stopImmediatePropagation();

    if (!MG.get || !MG.post) {
      status('Storefront creation is unavailable on this page.', 'error');
      return;
    }

    var button = form.querySelector('[type="submit"]');
    var displayName = String(form.elements.display_name.value || '').trim();
    var slug = slugify(form.elements.slug.value || displayName);
    var selectedProducts = products();
    var publish = form.elements.save_mode.value === 'publish';

    if (!displayName) return status('Enter a store name.', 'error');
    if (!slug) return status('Enter a valid public storefront address.', 'error');
    if (publish && !selectedProducts.length) return status('Select at least one published product before publishing.', 'error');

    busy(button, true, publish ? 'Publishing…' : 'Saving…');
    status(publish ? 'Saving and publishing storefront…' : 'Saving storefront draft…', 'working');

    try {
      var currentResponse = await MG.get('/api/merchant/storefront.php');
      var current = unwrap(currentResponse) || {};
      var currentRevision = revision(current);
      var payload = {
        action: 'save',
        display_name: displayName,
        slug: slug,
        headline: String(form.elements.headline.value || '').trim(),
        description: String(form.elements.description.value || '').trim(),
        logo_asset_id: String(currentRevision.logo_asset_public_id || ''),
        cover_asset_id: String(currentRevision.cover_asset_public_id || ''),
        contact: {
          email: String(form.elements.contact_email.value || '').trim(),
          phone: String(form.elements.contact_phone.value || '').trim(),
          website: String(form.elements.website_url.value || '').trim()
        },
        theme: { accent: String(form.elements.accent.value || '#2563eb').trim().toLowerCase() },
        products: selectedProducts
      };

      await MG.post('/api/merchant/storefront.php', payload);
      if (publish) await MG.post('/api/merchant/storefront.php', { action: 'publish' });

      var refreshed = unwrap(await MG.get('/api/merchant/storefront.php')) || {};
      var publicUrl = refreshed.public_url || '/merchant-storefront.php';
      var message = publish ? 'Storefront published successfully.' : 'Storefront draft saved successfully.';
      status(message, 'success');
      showSuccess(
        publish ? 'The storefront is now live and its existing logo and cover image were preserved.' : 'The storefront draft is saved and its existing logo and cover image were preserved.',
        publicUrl,
        publish && publicUrl !== '/merchant-storefront.php' ? 'Open storefront' : 'View storefront manager'
      );
    } catch (error) {
      status(error.message || 'Unable to save the storefront.', 'error');
    } finally {
      busy(button, false);
    }
  }

  form.addEventListener('submit', saveStorefront, true);
})(window, document);
