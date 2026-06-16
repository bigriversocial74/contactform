document.addEventListener('click', function (event) {
  'use strict';
  var button = event.target.closest('[data-item-action="load"]');
  if (!button) return;
  var item = button.closest('[data-gift-item]');
  if (!item || !item.dataset.itemId) return;
  event.preventDefault();
  event.stopImmediatePropagation();
  window.location.assign('/gift-stream.php?item=' + encodeURIComponent(item.dataset.itemId));
}, true);
