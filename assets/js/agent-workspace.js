document.addEventListener('DOMContentLoaded', function () {
  var composer = document.querySelector('[data-agent-composer]');
  if (composer) {
    var input = composer.querySelector('input,textarea');
    var prompt = new URLSearchParams(window.location.search).get('prompt');
    if (input && prompt) {
      input.value = String(prompt).slice(0, 1000);
      input.focus();
      if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.delete('prompt');
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
      }
    }
    composer.addEventListener('submit', function (event) {
      event.preventDefault();
    });
  }

  document.querySelectorAll('.mg-agent-skill-card').forEach(function (card) {
    card.addEventListener('click', function () {
      var selected = !card.classList.contains('is-selected');
      card.classList.toggle('is-selected', selected);
      card.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  });
});
