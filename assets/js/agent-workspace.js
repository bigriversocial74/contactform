document.addEventListener('DOMContentLoaded', function () {
  var composer = document.querySelector('[data-agent-composer]');
  if (composer) {
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
