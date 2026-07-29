(() => {
  'use strict';

  const root = document.querySelector('[data-investor-portal]');
  if (!root) return;
  const content = root.querySelector('[data-portal-content]');
  if (!content) return;

  const supported = new Set([
    'summary',
    'dataroom',
    'qa',
    'requests',
    'updates',
    'interest',
    'relations',
    'governance',
  ]);

  const labels = {
    relations: 'Investment Relations',
    governance: 'Governance',
  };

  const emptyCopy = {
    relations: {
      title: 'Investment Relations is not active yet.',
      body: 'This section becomes available after an authorized administrator verifies funded money and publishes funded-investor records.',
    },
    governance: {
      title: 'Governance access is not active yet.',
      body: 'This section becomes available to eligible funded investors after governance records are approved and published.',
    },
  };

  const activate = (container, name) => {
    const tabs = Array.from(container.querySelectorAll('[data-portal-tab]'));
    const panels = Array.from(container.querySelectorAll('[data-portal-panel]'));
    const button = tabs.find((item) => item.dataset.portalTab === name);
    const panel = panels.find((item) => item.dataset.portalPanel === name);
    if (!button || !panel) return false;

    tabs.forEach((item) => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      item.setAttribute('role', 'tab');
      item.tabIndex = active ? 0 : -1;
    });
    panels.forEach((item) => {
      const active = item === panel;
      item.hidden = !active;
      item.classList.toggle('is-active', active);
      item.setAttribute('role', 'tabpanel');
    });
    return true;
  };

  const ensureFallback = (container, name) => {
    const nav = container.querySelector('.mg-portal-v3-tabs');
    if (!nav) return;

    let button = nav.querySelector(`[data-portal-tab="${name}"]`);
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.dataset.portalTab = name;
      button.textContent = labels[name];
      nav.appendChild(button);
    }

    let panel = container.querySelector(`[data-portal-panel="${name}"]`);
    if (!panel) {
      const copy = emptyCopy[name];
      panel = document.createElement('section');
      panel.className = 'mg-portal-v3-panel';
      panel.dataset.portalPanel = name;
      panel.hidden = true;
      panel.innerHTML = `<section class="mg-investment-panel mg-investment-empty mg-investor-gated-empty"><h2>${copy.title}</h2><p>${copy.body}</p></section>`;
      container.appendChild(panel);
    }
  };

  const wire = (container) => {
    ensureFallback(container, 'relations');
    ensureFallback(container, 'governance');
    container.querySelectorAll('[data-portal-tab]').forEach((button) => {
      if (button.dataset.v6Bound === '1') return;
      button.dataset.v6Bound = '1';
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const name = button.dataset.portalTab || 'summary';
        if (activate(container, name)) {
          history.replaceState(null, '', `#${encodeURIComponent(name)}`);
        }
      });
      button.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const tabs = Array.from(container.querySelectorAll('[data-portal-tab]'));
        const index = tabs.indexOf(button);
        let next = index;
        if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = tabs.length - 1;
        event.preventDefault();
        tabs[next]?.focus();
        tabs[next]?.click();
      });
    });
  };

  const requested = () => {
    const value = decodeURIComponent(window.location.hash.replace(/^#/, '')).trim().toLowerCase();
    return supported.has(value) ? value : 'summary';
  };

  const sync = () => {
    const containers = Array.from(content.querySelectorAll('[data-round-container]'));
    containers.forEach(wire);
    if (!containers.length) return;
    const section = requested();
    const target = containers.find((container) => container.querySelector(`[data-portal-tab="${section}"]`)) || containers[0];
    if (activate(target, section)) {
      target.dataset.v6Section = section;
    }
  };

  let queued = false;
  const schedule = () => {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(() => {
      queued = false;
      sync();
    });
  };

  new MutationObserver(schedule).observe(content, { childList: true, subtree: true });
  window.addEventListener('hashchange', schedule);
  schedule();
})();
