(() => {
  document.documentElement.classList.add('mg-js');

  const boot = () => {
    const root = document.querySelector('.mg-home-page');
    if (!root) return;

    const revealItems = Array.from(root.querySelectorAll('[data-reveal]'));
    const progressBar = root.querySelector('#mgProgressBar');
    const heroSearch = root.querySelector('[data-hero-search]');

    if (heroSearch) {
      const input = heroSearch.querySelector('[data-hero-search-input]');
      const results = heroSearch.querySelector('[data-hero-search-results]');
      let timer = null;
      let controller = null;

      const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]);
      const hide = () => { if (results) { results.hidden = true; results.innerHTML = ''; } };
      const label = (item) => item.has_published_storefront ? 'Business' : (item.profile_type || 'Creator');

      const render = (items, query) => {
        if (!results) return;
        const safeQuery = encodeURIComponent(query);
        results.hidden = false;
        if (!items.length) {
          results.innerHTML = '<div class="mg-hero-search-empty"><strong>No direct matches yet.</strong><a href="/discover.php?q=' + safeQuery + '">Search all Microgifter →</a></div>';
          return;
        }
        results.innerHTML = items.slice(0, 6).map((item) => {
          const name = item.display_name || item.name || 'Microgifter profile';
          const href = item.url || ('/discover.php?q=' + safeQuery);
          const initial = (name.trim().charAt(0) || 'M').toUpperCase();
          return '<a class="mg-hero-search-result" href="' + esc(href) + '"><span class="mg-hero-search-avatar">' + esc(initial) + '</span><span><strong>' + esc(name) + '</strong><small>' + esc(label(item)) + '</small></span></a>';
        }).join('') + '<a class="mg-hero-search-all" href="/discover.php?q=' + safeQuery + '">View all results →</a>';
      };

      const runSearch = (query) => {
        if (!query || query.length < 2) { hide(); return; }
        if (controller) controller.abort();
        controller = new AbortController();
        fetch('/api/public/discover.php?' + new URLSearchParams({ q: query, limit: '6' }).toString(), { credentials:'same-origin', headers:{ Accept:'application/json' }, signal:controller.signal })
          .then((response) => response.json())
          .then((payload) => render(((((payload || {}).data || {}).results || {}).items) || [], query))
          .catch((error) => { if (error.name !== 'AbortError') hide(); });
      };

      input?.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => runSearch(input.value.trim()), 180);
      });
      heroSearch.addEventListener('submit', (event) => {
        if (!input || !input.value.trim()) { event.preventDefault(); input?.focus(); }
      });
      document.addEventListener('click', (event) => { if (!heroSearch.contains(event.target)) hide(); });
      hide();
    }

    const showAllRevealItems = () => {
      revealItems.forEach((item) => item.classList.add('is-visible'));
    };

    if ('IntersectionObserver' in window) {
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        });
      }, { threshold: 0.08, rootMargin: '0px 0px 12% 0px' });

      revealItems.forEach((item) => revealObserver.observe(item));
      window.setTimeout(showAllRevealItems, 1800);
    } else {
      showAllRevealItems();
    }

    const updateProgress = () => {
      if (!progressBar) return;
      const scrollTop = window.scrollY || document.documentElement.scrollTop;
      const docHeight = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
      const pct = docHeight > 0 ? Math.min(1, scrollTop / docHeight) : 0;
      progressBar.style.width = `${pct * 100}%`;
    };

    window.addEventListener('scroll', updateProgress, { passive:true });
    window.addEventListener('resize', updateProgress);
    updateProgress();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once:true });
  } else {
    boot();
  }
})();
