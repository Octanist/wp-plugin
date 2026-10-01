(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var options = document.querySelectorAll('.octanist-option');
    if (!options.length) return;

    function sync() {
      options.forEach(function (opt) {
        var radio = opt.querySelector('input[type="radio"]');
        if (!radio) return;
        opt.classList.toggle('is-active', radio.checked);
      });
    }

    options.forEach(function (opt) {
      var radio = opt.querySelector('input[type="radio"]');
      if (!radio) return;
      radio.addEventListener('change', sync);
    });
  });
})();
