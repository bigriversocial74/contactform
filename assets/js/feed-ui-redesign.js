window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  if (!root) return;
  var list = root.querySelector('[data-feed-list]');
  if (!list) return;

  var mediaPriority = { video: 1, image: 2, audio: 3, link: 4, other: 5 };

  function mediaKind(figure) {
    if (!figure || !figure.querySelector) return 'other';
    if (figure.querySelector('video')) return 'video';
    if (figure.querySelector('img')) return 'image';
    if (figure.querySelector('audio')) return 'audio';
    if (figure.querySelector('a')) return 'link';
    return 'other';
  }

  function polishMedia(card) {
    var media = card.querySelector('.mg-feed-media');
    if (!media) return;
    Array.from(media.children).forEach(function (figure) {
      var kind = mediaKind(figure);
      figure.dataset.feedMediaKind = kind;
      figure.style.order = String(mediaPriority[kind] || mediaPriority.other);
      figure.style.gridColumn = (kind === 'video' || kind === 'audio' || kind === 'link') ? '1 / -1' : '';
    });
    Array.from(media.children).sort(function (a, b) {
      return (mediaPriority[a.dataset.feedMediaKind] || 5) - (mediaPriority[b.dataset.feedMediaKind] || 5);
    }).forEach(function (figure) { media.appendChild(figure); });
  }

  function polishActions(card) {
    var actions = card.querySelector('.mg-feed-actions');
    if (!actions) return;
    actions.dataset.feedActionsRedesign = 'true';
    Array.from(actions.querySelectorAll('[data-post-action]')).forEach(function (button) {
      var action = button.dataset.postAction || '';
      var reaction = button.dataset.reactionType || '';
      if (reaction === 'love' || reaction === 'celebrate') {
        button.hidden = true;
        return;
      }
      if (action === 'reaction' && reaction === 'like') {
        button.textContent = 'Like';
        button.style.order = '1';
      } else if (action === 'comments') {
        button.textContent = 'Comment';
        button.style.order = '2';
      } else if (action === 'share') {
        button.textContent = 'Share';
        button.style.order = '3';
      } else if (action === 'save') {
        button.textContent = card.dataset.saved === '1' ? 'Saved' : 'Save';
        button.style.order = '4';
      } else if (action === 'reaction' && reaction === 'support') {
        button.textContent = 'Support';
        button.style.order = '5';
      } else {
        button.style.order = '20';
      }
    });
  }

  function polishCard(card) {
    if (!card || !card.matches || !card.matches('.mg-feed-card')) return;
    polishMedia(card);
    polishActions(card);
  }

  function scan(scope) {
    var cards = [];
    if (scope && scope.matches && scope.matches('.mg-feed-card')) cards.push(scope);
    if (scope && scope.querySelectorAll) cards = cards.concat(Array.from(scope.querySelectorAll('.mg-feed-card')));
    cards.forEach(polishCard);
  }

  scan(list);
  new MutationObserver(function (records) {
    records.forEach(function (record) {
      Array.from(record.addedNodes).forEach(scan);
      if (record.type === 'childList' && record.target && record.target.closest) {
        var card = record.target.closest('.mg-feed-card');
        if (card) polishCard(card);
      }
    });
  }).observe(list, { childList: true, subtree: true });
})(window, document);
