(() => {
  document.documentElement.classList.add('mg-js');

  const boot = () => {
    const root = document.querySelector('.mg-home-page');
    if (!root) return;

    const secondaryCta = root.querySelector('.mg-hero .mg-btn-secondary');
    if (secondaryCta) {
      secondaryCta.href = '/learn-more.php';
      secondaryCta.innerHTML = 'Book Demo <span aria-hidden="true">→</span>';
      secondaryCta.setAttribute('aria-label', 'Book a Microgifter demo');
    }

    const revealItems = Array.from(root.querySelectorAll('[data-reveal]'));
    const progressBar = root.querySelector('#mgProgressBar');

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
