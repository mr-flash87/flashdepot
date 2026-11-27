(function () {
  'use strict';

  // Montants : affichage avec espaces (500 000) pendant la saisie.
  function formatAmount(input) {
    var digits = input.value.replace(/\D/g, '');
    input.value = digits ? Number(digits).toLocaleString('fr-FR').replace(/ | /g, ' ') : '';
  }
  document.querySelectorAll('input[name="amount"], input[name="balance"]').forEach(function (el) {
    el.addEventListener('input', function () { formatAmount(el); });
  });

  // Boutons « montant rapide ».
  document.querySelectorAll('[data-fill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.closest('form').querySelector('input[name="amount"]');
      input.value = btn.getAttribute('data-fill');
      formatAmount(input);
      input.focus();
    });
  });

  // Rafraîchissement automatique : la page se recharge quand il y a du nouveau.
  var poll = document.querySelector('[data-poll]');
  if (!poll) return;
  var url = poll.getAttribute('data-poll');
  var last = null;
  var dirty = false;
  var isAdmin = document.body.classList.contains('area-admin');

  function busy() {
    var a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
    if (document.querySelector('details.reject[open]')) return true;
    return false;
  }

  function beep() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      [0, 0.18].forEach(function (t) {
        var o = ctx.createOscillator(), g = ctx.createGain();
        o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
        g.gain.setValueAtTime(0.15, ctx.currentTime + t);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.15);
        o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.16);
      });
    } catch (e) {}
  }

  function notify(text) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    try { new Notification(document.title.split('·').pop().trim(), { body: text, icon: document.body.getAttribute('data-base') + '/assets/icon-192.png' }); } catch (e) {}
  }

  if (isAdmin && 'Notification' in window && Notification.permission === 'default') {
    document.addEventListener('click', function ask() {
      Notification.requestPermission();
      document.removeEventListener('click', ask);
    });
  }

  function check() {
    if (document.hidden) return;
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var sig = JSON.stringify(d);
        if (last !== null && sig !== last) {
          var prev = JSON.parse(last);
          if (isAdmin && d.last_req > prev.last_req) { beep(); notify('Nouvelle demande de dépôt'); }
          else if (isAdmin && d.last_op > prev.last_op) { notify('Nouvelle opération détectée'); }
          dirty = true;
        }
        last = sig;
        // On attend que la personne ait fini de taper avant de recharger.
        if (dirty && !busy()) location.reload();
      })
      .catch(function () {});
  }
  check();
  setInterval(check, 12000);
  document.addEventListener('visibilitychange', check);
})();
