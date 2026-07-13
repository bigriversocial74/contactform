(function(){
  'use strict';

  var root = document.querySelector('[data-public-profile-page]');
  if (!root) return;

  var slug = root.getAttribute('data-profile-slug') || '';
  if (!slug) {
    try { slug = new URLSearchParams(window.location.search).get('slug') || ''; } catch (error) {}
  }
  if (!slug) return;

  var MG = window.Microgifter || {};
  var state = {data:null, modal:null, panel:null, tab:null, rating:0};

  function addStyles(){
    if (document.querySelector('link[data-profile-review-styles]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/public-profile-reviews.css?v=1.0.0';
    link.setAttribute('data-profile-review-styles', '1');
    document.head.appendChild(link);
  }

  function escapeText(value){ return String(value == null ? '' : value); }
  function payload(response){ return response && response.data ? response.data : response || {}; }
  function formatDate(value){
    if (!value) return '';
    var raw = String(value);
    var parsed = new Date(raw.replace(' ', 'T') + (raw.indexOf('T') === -1 ? 'Z' : ''));
    return Number.isNaN(parsed.getTime()) ? raw : new Intl.DateTimeFormat(undefined, {dateStyle:'medium'}).format(parsed);
  }
  function stars(value){
    var rating = Math.max(0, Math.min(5, Number(value || 0)));
    var wrap = document.createElement('span');
    wrap.className = 'mg-review-stars';
    wrap.setAttribute('aria-label', rating + ' out of 5 stars');
    for (var i = 1; i <= 5; i += 1) {
      var star = document.createElement('span');
      star.textContent = '★';
      star.className = i <= Math.round(rating) ? 'is-filled' : '';
      wrap.appendChild(star);
    }
    return wrap;
  }

  function installShell(){
    var nav = root.querySelector('.mg-invest-tabs');
    var main = root.querySelector('.mg-invest-main-column');
    if (!nav || !main) return false;

    if (!state.tab) {
      state.tab = document.createElement('button');
      state.tab.type = 'button';
      state.tab.textContent = 'Reviews';
      state.tab.setAttribute('data-invest-tab', 'reviews');
      state.tab.setAttribute('role', 'tab');
      var campaigns = nav.querySelector('[data-invest-tab="campaigns"]');
      nav.insertBefore(state.tab, campaigns || null);
      state.tab.addEventListener('click', function(event){
        event.preventDefault();
        showPanel();
      });
    }

    if (!state.panel) {
      state.panel = document.createElement('section');
      state.panel.className = 'mg-invest-tab-panel mg-profile-reviews-panel';
      state.panel.setAttribute('data-invest-panel', 'reviews');
      state.panel.hidden = true;
      state.panel.innerHTML =
        '<article class="mg-invest-card mg-profile-review-card">' +
          '<div class="mg-profile-review-header">' +
            '<div><span class="mg-profile-section-kicker">Customer experiences</span><h2>Customer Reviews</h2><p>Verified profile reviews connected to merchant reward campaigns.</p></div>' +
            '<button class="mg-invest-btn is-gold" type="button" data-profile-add-review>Add Review</button>' +
          '</div>' +
          '<div class="mg-profile-review-summary" data-profile-review-summary></div>' +
          '<div class="mg-profile-review-list" data-profile-review-list></div>' +
          '<div class="mg-invest-empty-state mg-hidden" data-profile-review-empty>No customer reviews have been submitted yet.</div>' +
        '</article>';
      main.appendChild(state.panel);
      state.panel.querySelector('[data-profile-add-review]').addEventListener('click', openModal);
    }

    root.addEventListener('click', function(event){
      var other = event.target.closest('[data-invest-tab]');
      if (!other || other === state.tab) return;
      state.panel.hidden = true;
      state.panel.classList.remove('is-active');
      state.tab.classList.remove('is-active');
      state.tab.setAttribute('aria-selected', 'false');
    }, true);

    return true;
  }

  function showPanel(){
    root.querySelectorAll('[data-invest-tab]').forEach(function(tab){
      var active = tab === state.tab;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-invest-panel]').forEach(function(panel){
      var active = panel === state.panel;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
    if (!state.data) load();
  }

  function renderSummary(summary){
    var target = state.panel && state.panel.querySelector('[data-profile-review-summary]');
    if (!target) return;
    target.replaceChildren();

    var score = document.createElement('div');
    score.className = 'mg-review-score';
    var average = document.createElement('strong');
    average.textContent = Number(summary.average || 0).toFixed(1);
    score.append(average, stars(summary.average || 0));
    var count = document.createElement('span');
    count.textContent = Number(summary.count || 0).toLocaleString() + ' review' + (Number(summary.count || 0) === 1 ? '' : 's');
    score.appendChild(count);

    var bars = document.createElement('div');
    bars.className = 'mg-review-distribution';
    var total = Math.max(1, Number(summary.count || 0));
    [5,4,3,2,1].forEach(function(rating){
      var row = document.createElement('div');
      var label = document.createElement('span');
      label.textContent = rating + ' ★';
      var track = document.createElement('i');
      var fill = document.createElement('b');
      fill.style.width = Math.round((Number((summary.distribution || {})[String(rating)] || 0) / total) * 100) + '%';
      track.appendChild(fill);
      var number = document.createElement('em');
      number.textContent = Number((summary.distribution || {})[String(rating)] || 0).toLocaleString();
      row.append(label, track, number);
      bars.appendChild(row);
    });
    target.append(score, bars);
  }

  function renderReviews(items){
    var list = state.panel && state.panel.querySelector('[data-profile-review-list]');
    var empty = state.panel && state.panel.querySelector('[data-profile-review-empty]');
    if (!list || !empty) return;
    list.replaceChildren();
    (Array.isArray(items) ? items : []).forEach(function(item){
      var card = document.createElement('article');
      card.className = 'mg-review-item';
      var head = document.createElement('header');
      var identity = document.createElement('div');
      var avatar = document.createElement('span');
      avatar.textContent = escapeText(item.reviewer_name || 'C').trim().charAt(0).toUpperCase() || 'C';
      var name = document.createElement('div');
      var strong = document.createElement('strong');
      strong.textContent = escapeText(item.reviewer_name || 'Microgifter Customer');
      var date = document.createElement('time');
      date.textContent = formatDate(item.submitted_at);
      name.append(strong, date);
      identity.append(avatar, name);
      head.append(identity, stars(item.rating));
      card.appendChild(head);
      if (item.title) {
        var title = document.createElement('h3');
        title.textContent = escapeText(item.title);
        card.appendChild(title);
      }
      var body = document.createElement('p');
      body.textContent = escapeText(item.body);
      card.appendChild(body);
      list.appendChild(card);
    });
    empty.classList.toggle('mg-hidden', list.children.length > 0);
  }

  function render(data){
    state.data = data || {};
    renderSummary(state.data.summary || {count:0,average:0,distribution:{}});
    renderReviews(state.data.reviews || []);
    var button = state.panel && state.panel.querySelector('[data-profile-add-review]');
    if (button) {
      var campaign = state.data.campaign;
      button.disabled = !campaign;
      button.textContent = campaign ? 'Add Review' : 'Reviews Closed';
    }
  }

  async function load(){
    try {
      var response;
      if (MG.get) response = await MG.get('/api/public/profile-reviews.php?slug=' + encodeURIComponent(slug));
      else response = await fetch('/api/public/profile-reviews.php?slug=' + encodeURIComponent(slug), {credentials:'same-origin', headers:{Accept:'application/json'}}).then(function(res){ return res.json(); });
      render(payload(response));
    } catch (error) {
      render({summary:{count:0,average:0,distribution:{}},reviews:[],campaign:null,eligibility:{reason:'Unable to load reviews.'}});
    }
  }

  function ensureModal(){
    if (state.modal) return state.modal;
    state.modal = document.createElement('section');
    state.modal.className = 'mg-review-modal';
    state.modal.hidden = true;
    state.modal.setAttribute('role', 'dialog');
    state.modal.setAttribute('aria-modal', 'true');
    state.modal.setAttribute('aria-labelledby', 'mg-review-modal-title');
    state.modal.innerHTML =
      '<div class="mg-review-modal-shell">' +
        '<header><div><span>Customer Review</span><h2 id="mg-review-modal-title">Share your experience</h2></div><button type="button" data-review-close aria-label="Close review form">×</button></header>' +
        '<div class="mg-review-modal-grid">' +
          '<aside data-review-campaign-copy></aside>' +
          '<main>' +
            '<form data-review-form novalidate>' +
              '<input type="hidden" name="campaign_id"><input type="hidden" name="profile_slug">' +
              '<div class="mg-review-star-picker" role="radiogroup" aria-label="Choose a rating">' +
                '<button type="button" data-review-star="1" role="radio" aria-checked="false" aria-label="1 star">★</button>' +
                '<button type="button" data-review-star="2" role="radio" aria-checked="false" aria-label="2 stars">★</button>' +
                '<button type="button" data-review-star="3" role="radio" aria-checked="false" aria-label="3 stars">★</button>' +
                '<button type="button" data-review-star="4" role="radio" aria-checked="false" aria-label="4 stars">★</button>' +
                '<button type="button" data-review-star="5" role="radio" aria-checked="false" aria-label="5 stars">★</button>' +
              '</div>' +
              '<input type="hidden" name="rating" value="">' +
              '<label>Review title <span>Optional</span><input name="review_title" maxlength="180" placeholder="A quick summary"></label>' +
              '<label>Your review<textarea name="review_body" minlength="10" maxlength="3000" rows="8" placeholder="Tell other customers about your experience." required></textarea></label>' +
              '<div class="mg-review-limit-note" data-review-limit-note></div>' +
              '<div class="mg-form-status" data-review-status role="status" aria-live="polite"></div>' +
              '<button class="mg-invest-btn is-gold" type="submit" data-review-submit>Submit Review &amp; Receive Reward</button>' +
            '</form>' +
            '<div class="mg-review-success" data-review-success hidden></div>' +
          '</main>' +
        '</div>' +
      '</div>';
    document.body.appendChild(state.modal);

    state.modal.addEventListener('click', function(event){
      if (event.target === state.modal || event.target.closest('[data-review-close]')) closeModal();
      var star = event.target.closest('[data-review-star]');
      if (star) selectRating(Number(star.getAttribute('data-review-star') || 0));
    });
    state.modal.querySelector('[data-review-form]').addEventListener('submit', submitReview);
    document.addEventListener('keydown', function(event){
      if (event.key === 'Escape' && state.modal && !state.modal.hidden) closeModal();
    });
    return state.modal;
  }

  function selectRating(value){
    state.rating = Math.max(0, Math.min(5, Number(value || 0)));
    var modal = ensureModal();
    modal.querySelector('input[name="rating"]').value = state.rating > 0 ? String(state.rating) : '';
    modal.querySelectorAll('[data-review-star]').forEach(function(button){
      var selected = Number(button.getAttribute('data-review-star')) <= state.rating;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-checked', Number(button.getAttribute('data-review-star')) === state.rating ? 'true' : 'false');
    });
  }

  function openModal(){
    var data = state.data || {};
    var campaign = data.campaign;
    var eligibility = data.eligibility || {};
    if (!campaign) return;
    if (!eligibility.authenticated) {
      window.location.href = '/signin.php?return=' + encodeURIComponent(window.location.pathname + window.location.search + '#reviews');
      return;
    }

    var modal = ensureModal();
    var form = modal.querySelector('[data-review-form]');
    var success = modal.querySelector('[data-review-success]');
    form.hidden = false;
    success.hidden = true;
    form.reset();
    selectRating(0);
    form.elements.campaign_id.value = campaign.id;
    form.elements.profile_slug.value = slug;

    var copy = modal.querySelector('[data-review-campaign-copy]');
    copy.replaceChildren();
    var kicker = document.createElement('span');
    kicker.textContent = 'Review reward';
    var heading = document.createElement('h3');
    heading.textContent = campaign.headline || campaign.title || 'Share your experience';
    var description = document.createElement('p');
    description.textContent = campaign.description || campaign.prompt || '';
    var reward = document.createElement('div');
    reward.className = 'mg-review-reward-card';
    var rewardTitle = document.createElement('strong');
    rewardTitle.textContent = campaign.reward && campaign.reward.title || 'Microgifter reward';
    var rewardValue = document.createElement('span');
    rewardValue.textContent = campaign.reward && campaign.reward.value || 'Reward';
    var rewardDestination = document.createElement('small');
    rewardDestination.textContent = 'Delivered through Wallet → Inbox PPPM';
    reward.append(rewardTitle, rewardValue, rewardDestination);
    copy.append(kicker, heading, description, reward);

    var note = modal.querySelector('[data-review-limit-note]');
    note.textContent = eligibility.can_review
      ? 'You may submit ' + eligibility.remaining + ' more review' + (eligibility.remaining === 1 ? '' : 's') + ' this ' + eligibility.period + '.'
      : eligibility.reason || 'Review submission is not available.';
    form.querySelector('[data-review-submit]').disabled = !eligibility.can_review;

    modal.hidden = false;
    document.body.classList.add('mg-review-modal-open');
  }

  function closeModal(){
    if (!state.modal) return;
    state.modal.hidden = true;
    document.body.classList.remove('mg-review-modal-open');
  }

  async function submitReview(event){
    event.preventDefault();
    var form = event.currentTarget;
    var status = form.querySelector('[data-review-status]');
    var submit = form.querySelector('[data-review-submit]');
    if (state.rating < 1) {
      status.textContent = 'Choose a star rating.';
      status.className = 'mg-form-status is-error';
      return;
    }
    if (!form.reportValidity()) return;

    var data = Object.fromEntries(new FormData(form).entries());
    data.idempotency_key = 'customer-review:' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now() + ':' + Math.random().toString(16).slice(2));
    submit.disabled = true;
    status.textContent = 'Submitting review and issuing reward…';
    status.className = 'mg-form-status';

    try {
      var response = MG.post
        ? await MG.post('/api/public/campaigns/customer-review.php', data)
        : await fetch('/api/public/campaigns/customer-review.php', {
            method:'POST',
            credentials:'same-origin',
            headers:{'Content-Type':'application/json','Accept':'application/json'},
            body:JSON.stringify(data)
          }).then(function(res){ return res.json(); });
      var result = payload(response);
      var success = state.modal.querySelector('[data-review-success]');
      form.hidden = true;
      success.hidden = false;
      success.replaceChildren();
      var icon = document.createElement('span');
      icon.textContent = '✓';
      var title = document.createElement('h3');
      title.textContent = response && response.message || 'Review submitted';
      var copy = document.createElement('p');
      copy.textContent = 'Your review is live. The attached reward was created in your wallet and handed into the Microgifter Inbox PPPM system.';
      var link = document.createElement('a');
      link.className = 'mg-invest-btn is-gold';
      link.href = result.inbox_url || '/inbox.php';
      link.textContent = 'Open Inbox';
      success.append(icon, title, copy, link);
      await load();
    } catch (error) {
      status.textContent = error && error.message ? error.message : 'Unable to submit review.';
      status.className = 'mg-form-status is-error';
      submit.disabled = false;
    }
  }

  addStyles();
  if (!installShell()) return;
  load();
  if (window.location.hash === '#reviews') showPanel();
})();