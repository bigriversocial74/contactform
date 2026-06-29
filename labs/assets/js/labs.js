(() => {
  const toggles = document.querySelectorAll('[data-labs-toggle]');
  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const target = document.querySelector(toggle.getAttribute('data-labs-toggle'));
      if (target) target.classList.toggle('is-open');
    });
  });
})();
