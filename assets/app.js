document.addEventListener('DOMContentLoaded', function () {
  var err = document.getElementById('errors');
  if (err) { err.focus(); }
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
});
