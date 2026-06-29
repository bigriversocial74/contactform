(() => {
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  qsa('[data-tcl-progress]').forEach((bar) => {
    const value = Math.max(0, Math.min(100, Number(bar.getAttribute('data-tcl-progress') || '0')));
    const fill = qs('span', bar);
    if (fill) fill.style.width = `${value}%`;
  });

  qsa('[data-tcl-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.closest('[data-tcl-filter-group]');
      if (!group) return;
      qsa('[data-tcl-filter]', group).forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      const filter = button.getAttribute('data-tcl-filter') || 'all';
      qsa('[data-tcl-campaign-card]').forEach((card) => {
        const tags = (card.getAttribute('data-tcl-tags') || '').toLowerCase();
        const visible = filter === 'all' || tags.includes(filter.toLowerCase());
        card.style.display = visible ? '' : 'none';
      });
    });
  });

  const search = qs('[data-tcl-campaign-search]');
  if (search) {
    search.addEventListener('input', () => {
      const term = search.value.trim().toLowerCase();
      qsa('[data-tcl-campaign-card]').forEach((card) => {
        const haystack = (card.textContent || '').toLowerCase();
        card.style.display = haystack.includes(term) ? '' : 'none';
      });
    });
  }
})();
