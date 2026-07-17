(() => {
  'use strict';

  const root = document.querySelector('[data-design-content-calendar]');
  if (!root) return;

  let scheduled = false;

  function titleFromCard(card) {
    const label = String(card.getAttribute('aria-label') || '').trim();
    return label.replace(/^Edit scheduled ad for\s*/i, '').trim();
  }

  function decorateCard(card) {
    const selector = card.querySelector('.mg-calendar-select-item');
    if (selector) selector.remove();

    if (!card.querySelector('.mg-calendar-event-title')) {
      const title = titleFromCard(card);
      if (title) {
        const node = document.createElement('strong');
        node.className = 'mg-calendar-event-title';
        node.textContent = title;
        const head = card.querySelector('.mg-design-calendar-event-head');
        if (head) head.insertAdjacentElement('afterend', node);
        else card.prepend(node);
      }
    }
  }

  function decorate() {
    scheduled = false;
    root.querySelectorAll('.mg-design-calendar-event').forEach(decorateCard);
  }

  function scheduleDecorate() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(decorate);
  }

  const observer = new MutationObserver(scheduleDecorate);
  observer.observe(root, { childList: true, subtree: true });
  decorate();
})();
