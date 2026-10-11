// Small helpers. The site works without JavaScript, this only makes it nicer.
(function () {
  // Copy buttons: <button data-copy="text">
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn) return;
    var text = btn.getAttribute('data-copy');
    var done = function () {
      var old = btn.getAttribute('data-label') || btn.textContent;
      btn.setAttribute('data-label', old);
      btn.classList.add('green');
      btn.querySelector('.t') ? (btn.querySelector('.t').textContent = 'Copied') : null;
      setTimeout(function () {
        btn.classList.remove('green');
        btn.querySelector('.t') ? (btn.querySelector('.t').textContent = 'Copy') : null;
      }, 1400);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); done(); } catch (err) {}
      document.body.removeChild(ta);
    }
  });

  // Confirm before dangerous forms: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Bill form: show only the providers that serve the chosen bill type, and the right hint.
  var type = document.getElementById('bill_type');
  var provider = document.getElementById('provider');
  var data = document.getElementById('provider-data');
  if (type && provider && data) {
    var options = JSON.parse(data.textContent);
    var hints = JSON.parse(document.getElementById('type-data').textContent);
    var label = document.getElementById('identifier_label');
    var hint = document.getElementById('identifier_hint');
    var update = function () {
      var current = provider.getAttribute('data-current') || provider.value;
      var list = options[type.value] || {};
      provider.innerHTML = '';
      Object.keys(list).forEach(function (k) {
        var o = document.createElement('option');
        o.value = k; o.textContent = list[k];
        if (k === current) o.selected = true;
        provider.appendChild(o);
      });
      if (!provider.options.length) {
        var o = document.createElement('option');
        o.value = ''; o.textContent = 'No provider available yet';
        provider.appendChild(o);
      }
      var h = hints[type.value];
      if (h && label) { label.textContent = h.label; hint.textContent = h.hint; }
    };
    type.addEventListener('change', function () { provider.removeAttribute('data-current'); update(); });
    update();
  }
})();
