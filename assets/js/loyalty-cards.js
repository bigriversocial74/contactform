document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
    });
  }

  function isAuthed() {
    return !!(window.Microgifter && typeof Microgifter.isAuthenticated === 'function' && Microgifter.isAuthenticated());
  }

  function redirectToSignin() {
    window.location.href = '/signin.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
  }

  function toast(message, type) {
    if (window.Microgifter && typeof Microgifter.toast === 'function') {
      Microgifter.toast(message, type || '');
    }
  }

  function responseData(response) {
    return (response && response.data) || response || {};
  }

  function setButtonState(button, saved) {
    if (!button) return;
    button.classList.toggle('is-saved', !!saved);
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    button.setAttribute('data-saved', saved ? 'true' : 'false');
    var icon = button.querySelector('[data-loyalty-save-icon]');
    var label = button.querySelector('[data-loyalty-save-label]');
    if (icon) icon.textContent = saved ? '★' : '☆';
    if (label) label.textContent = saved ? 'Saved Card' : 'Save Card';
  }

  function initStampSaveButton() {
    var button = document.querySelector('[data-loyalty-save-toggle]');
    if (!button) return;
    var campaignId = String(button.getAttribute('data-campaign-id') || '').trim();
    if (!campaignId) return;

    if (isAuthed() && window.Microgifter && typeof Microgifter.get === 'function') {
      button.disabled = true;
      Microgifter.get('/api/account/loyalty-cards.php?campaign=' + encodeURIComponent(campaignId)).then(function (response) {
        setButtonState(button, !!responseData(response).saved);
      }).catch(function () {
        setButtonState(button, false);
      }).finally(function () {
        button.disabled = false;
      });
    }

    button.addEventListener('click', function () {
      if (!isAuthed()) {
        redirectToSignin();
        return;
      }
      if (!window.Microgifter || typeof Microgifter.post !== 'function') return;
      button.disabled = true;
      Microgifter.post('/api/account/loyalty-cards.php', {
        campaign_id: campaignId,
        action: 'toggle'
      }).then(function (response) {
        var data = responseData(response);
        setButtonState(button, !!data.saved);
        toast(data.saved ? 'Loyalty card saved.' : 'Loyalty card removed.', data.saved ? 'success' : '');
      }).catch(function (error) {
        if (error && error.status === 401) {
          redirectToSignin();
          return;
        }
        toast((error && error.message) || 'Unable to update saved card.', 'error');
      }).finally(function () {
        button.disabled = false;
      });
    });
  }

  function progressBar(card) {
    var percent = Math.max(0, Math.min(100, Number(card.progress_percent || 0)));
    return '<div class="mg-loyalty-progress"><div><span>' + esc(card.stamp_count || 0) + ' / ' + esc(card.required_count || 0) + ' stamps</span><strong>' + esc(percent) + '%</strong></div><i><b style="width:' + percent + '%"></b></i></div>';
  }

  function renderCard(card) {
    var image = card.image_url
      ? '<img src="' + esc(card.image_url) + '" alt="' + esc(card.title) + ' campaign image">'
      : '<span>Stamp Card</span>';
    var status = card.campaign_status && card.campaign_status !== 'active'
      ? '<span class="mg-loyalty-card-status">' + esc(card.campaign_status) + '</span>'
      : '';
    return '<article class="mg-loyalty-card" data-loyalty-card="' + esc(card.id) + '">' +
      '<a class="mg-loyalty-card-image" href="' + esc(card.public_url) + '">' + image + '</a>' +
      '<div class="mg-loyalty-card-body"><div class="mg-loyalty-card-top"><span>Saved Loyalty Card</span><button type="button" data-loyalty-remove="' + esc(card.campaign_id) + '" aria-label="Remove saved card">★</button></div>' +
      '<h2>' + esc(card.title) + '</h2><p>' + esc(card.merchant_name) + '</p>' + status + progressBar(card) +
      '<div class="mg-loyalty-card-meta"><span>' + esc(card.stamps_remaining) + ' remaining</span><span>' + esc(card.reward_title) + '</span></div>' +
      '<a class="mg-btn mg-btn-primary" href="' + esc(card.public_url) + '">Open card</a></div></article>';
  }

  function initLoyaltyCardsPage() {
    var page = document.querySelector('[data-loyalty-cards-page]');
    if (!page) return;
    var list = page.querySelector('[data-loyalty-cards-list]');
    var status = page.querySelector('[data-loyalty-cards-status]');
    var empty = page.querySelector('[data-loyalty-cards-empty]');

    function setStatus(message, type) {
      if (!status) return;
      status.textContent = message || '';
      status.classList.toggle('is-error', type === 'error');
      status.hidden = !message;
    }

    function loadCards() {
      if (!isAuthed()) {
        if (list) list.innerHTML = '';
        if (empty) {
          empty.hidden = false;
          var heading = empty.querySelector('h2');
          if (heading) heading.textContent = 'Sign in to view saved loyalty cards.';
        }
        setStatus('');
        return;
      }
      setStatus('Loading saved loyalty cards…');
      Microgifter.get('/api/account/loyalty-cards.php').then(function (response) {
        var data = responseData(response);
        var cards = Array.isArray(data.cards) ? data.cards : [];
        if (list) list.innerHTML = cards.map(renderCard).join('');
        if (empty) empty.hidden = cards.length > 0;
        setStatus(cards.length ? '' : (data.schema_ready === false ? 'Saved cards schema is not installed yet.' : ''));
      }).catch(function (error) {
        setStatus((error && error.message) || 'Unable to load saved cards.', 'error');
      });
    }

    page.addEventListener('click', function (event) {
      var remove = event.target.closest('[data-loyalty-remove]');
      if (!remove) return;
      var campaignId = remove.getAttribute('data-loyalty-remove') || '';
      if (!campaignId) return;
      remove.disabled = true;
      Microgifter.post('/api/account/loyalty-cards.php', {
        campaign_id: campaignId,
        action: 'unsave'
      }).then(function () {
        loadCards();
      }).catch(function (error) {
        remove.disabled = false;
        setStatus((error && error.message) || 'Unable to remove saved card.', 'error');
      });
    });

    loadCards();
  }

  initStampSaveButton();
  initLoyaltyCardsPage();
});