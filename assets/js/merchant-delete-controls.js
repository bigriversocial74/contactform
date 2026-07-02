window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }
  function data(response) { return response && response.data ? response.data : response; }

  function setBusy(button, busy, busyText) {
    if (!button) return;
    if (MG.setBusy) return MG.setBusy(button, busy, busyText);
    if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
    button.disabled = Boolean(busy);
    button.textContent = busy ? (busyText || 'Working…') : button.dataset.originalText;
  }

  function notify(message, type) {
    if (MG.toast) MG.toast(message, type || 'success');
    else window.alert(message);
  }

  function scheduleOnce(callback, delay) {
    var timer = null;
    return function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(callback, delay || 120);
    };
  }

  function apiAvailable() {
    return MG && typeof MG.get === 'function' && typeof MG.post === 'function';
  }

  function initProductDeleteControls() {
    var list = qs('[data-product-list]');
    if (!list || !apiAvailable()) return;

    async function syncProductControls() {
      var rows = qsa('.mg-product-row[data-product-id]', list);
      var ids = rows.map(function (row) { return String(row.dataset.productId || '').trim(); }).filter(Boolean);
      if (!ids.length) return;

      try {
        var payload = data(await MG.get('/api/merchant/product-delete-status.php?ids=' + encodeURIComponent(ids.join(',')))) || {};
        var statuses = payload.products || {};
        rows.forEach(function (row) {
          var id = String(row.dataset.productId || '');
          if (!id) return;
          var actions = qs('.mg-product-actions', row);
          if (!actions || actions.querySelector('[data-mg-delete-product]')) return;
          var status = statuses[id] || {};
          var archive = actions.querySelector('[data-product-action="archive"]');
          if (archive && Number(status.purchase_count || 0) > 0) {
            archive.title = 'This product has purchases, so it can only be archived.';
          }
          if (!status.can_delete) return;
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'mg-delete-control mg-product-delete-button';
          button.textContent = 'Delete';
          button.dataset.mgDeleteProduct = id;
          button.title = 'Delete this product permanently because it has no purchases.';
          actions.appendChild(button);
        });
      } catch (error) {
        if (window.console && console.warn) console.warn('Unable to load product delete status.', error);
      }
    }

    var schedule = scheduleOnce(syncProductControls, 120);
    new MutationObserver(schedule).observe(list, { childList: true });
    list.addEventListener('click', async function (event) {
      var button = event.target.closest('[data-mg-delete-product]');
      if (!button) return;
      var id = String(button.dataset.mgDeleteProduct || '');
      if (!id) return;
      if (!window.confirm('Delete this product permanently? This is only allowed when the product has no purchases. This cannot be undone.')) return;
      setBusy(button, true, 'Deleting…');
      try {
        var response = await MG.post('/api/merchant/product-delete.php', { id: id });
        notify(response.message || 'Product deleted.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      } catch (error) {
        notify(error.message || 'Unable to delete product.', 'error');
        setBusy(button, false);
      }
    });
    schedule();
  }

  function mediaQueryString() {
    var params = new URLSearchParams();
    var search = qs('[data-asset-search]');
    var type = qs('[data-asset-type]');
    var status = qs('[data-asset-status]');
    params.set('q', search ? search.value || '' : '');
    params.set('type', type ? type.value || 'all' : 'all');
    params.set('status', status ? status.value || 'all' : 'all');
    return params.toString();
  }

  function initMediaDeleteControls() {
    var grid = qs('[data-asset-grid]');
    if (!grid || !apiAvailable()) return;
    var syncing = false;

    async function syncAssetControls() {
      if (syncing) return;
      var cards = qsa('.mg-asset-card', grid);
      if (!cards.length) return;
      syncing = true;
      try {
        var payload = data(await MG.get('/api/merchant/assets.php?' + mediaQueryString())) || {};
        var assets = Array.isArray(payload.assets) ? payload.assets : [];
        var ids = assets.map(function (asset) { return String(asset.public_id || '').trim(); }).filter(Boolean);
        var statusPayload = ids.length ? data(await MG.get('/api/merchant/asset-delete-status.php?ids=' + encodeURIComponent(ids.join(',')))) || {} : {};
        var statuses = statusPayload.assets || {};

        cards.forEach(function (card, index) {
          if (card.querySelector('[data-mg-delete-asset]') || card.querySelector('[data-mg-locked-asset]')) return;
          var asset = assets[index];
          if (!asset || !asset.public_id) return;
          var info = statuses[String(asset.public_id)] || { can_delete: true };
          var body = card.lastElementChild;
          if (!body) return;
          var actions = document.createElement('div');
          actions.className = 'mg-asset-card-actions';
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'mg-delete-control mg-asset-delete-button';
          if (info.can_delete === false) {
            button.textContent = 'Locked';
            button.disabled = true;
            button.dataset.mgLockedAsset = String(asset.public_id);
            button.title = 'This media is attached to a published or archived product and cannot be deleted.';
          } else {
            button.textContent = 'Delete';
            button.dataset.mgDeleteAsset = String(asset.public_id);
            button.title = 'Delete this media item.';
          }
          actions.appendChild(button);
          body.appendChild(actions);
        });
      } catch (error) {
        if (window.console && console.warn) console.warn('Unable to load media delete status.', error);
      } finally {
        syncing = false;
      }
    }

    var schedule = scheduleOnce(syncAssetControls, 160);
    new MutationObserver(schedule).observe(grid, { childList: true });
    ['[data-asset-search]', '[data-asset-type]', '[data-asset-status]'].forEach(function (selector) {
      var control = qs(selector);
      if (!control) return;
      control.addEventListener(control.matches('input') ? 'input' : 'change', schedule);
    });
    grid.addEventListener('click', async function (event) {
      var button = event.target.closest('[data-mg-delete-asset]');
      if (!button) return;
      var id = String(button.dataset.mgDeleteAsset || '');
      if (!id) return;
      if (!window.confirm('Delete this media item? This cannot be undone from the media library.')) return;
      setBusy(button, true, 'Deleting…');
      try {
        var response = await MG.post('/api/merchant/asset-delete.php', { id: id });
        notify(response.message || 'Media deleted.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 450);
      } catch (error) {
        notify(error.message || 'Unable to delete media.', 'error');
        setBusy(button, false);
      }
    });
    schedule();
  }

  function init() {
    initProductDeleteControls();
    initMediaDeleteControls();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
