(function () {
  'use strict';
  var FD = window.FD || {};
  var $$ = function (s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };

  /* ---------------- Montants : 500 000 pendant la saisie ---------------- */
  function formatAmount(input) {
    var digits = input.value.replace(/\D/g, '');
    input.value = digits ? Number(digits).toLocaleString('fr-FR').replace(/ | /g, ' ') : '';
  }
  $$('input[name="amount"], input[name="balance"]').forEach(function (el) {
    el.addEventListener('input', function () { formatAmount(el); });
  });
  $$('[data-fill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.closest('form').querySelector('input[name="amount"]');
      input.value = btn.getAttribute('data-fill');
      formatAmount(input);
      $$('[data-fill]', btn.closest('form')).forEach(function (b) { b.classList.toggle('on', b === btn); });
    });
  });

  /* ---------------- Annonces du super-admin (fermées = ne reviennent plus) ---------------- */
  $$('[data-ann]').forEach(function (el) {
    var key = 'fd-ann-' + el.getAttribute('data-ann');
    if (store.get(key) === '1') return;
    el.hidden = false;
    el.querySelector('[data-ann-close]').addEventListener('click', function () { el.remove(); store.set(key, '1'); });
  });

  /* ---------------- Copier un numéro ---------------- */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-copy'), label = b.innerHTML;
      var done = function () { b.textContent = 'Numéro copié ✓'; setTimeout(function () { b.innerHTML = label; }, 2000); };
      if (navigator.clipboard) { navigator.clipboard.writeText(v).then(done, function () { prompt('Numéro :', v); }); }
      else { prompt('Numéro :', v); }
    });
  });

  /* ---------------- Messages qui disparaissent ---------------- */
  $$('[data-autohide]').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 450); }, 6000);
  });

  /* ---------------- Thème clair / sombre ---------------- */
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var root = document.documentElement;
      var cur = root.getAttribute('data-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      store.set('fd-theme', next);
    });
  });

  /* ---------------- Feuilles (Plus, Notifications, Installer) ---------------- */
  function openSheet(name) {
    $$('.sheet').forEach(function (s) { s.hidden = true; });
    var s = document.getElementById('sheet-' + name);
    if (s) { s.hidden = false; document.body.style.overflow = 'hidden'; }
  }
  function closeSheets() {
    $$('.sheet').forEach(function (s) { s.hidden = true; });
    document.body.style.overflow = '';
  }
  $$('[data-open]').forEach(function (b) {
    b.addEventListener('click', function () { openSheet(b.getAttribute('data-open')); });
  });
  $$('[data-close]').forEach(function (b) { b.addEventListener('click', closeSheets); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheets(); });

  /* ---------------- Son ---------------- */
  var soundOn = store.get('fd-sound') !== '0';
  $$('[data-sound-toggle]').forEach(function (c) {
    c.checked = soundOn;
    c.addEventListener('change', function () { soundOn = c.checked; store.set('fd-sound', soundOn ? '1' : '0'); if (soundOn) chime(); });
  });
  var audioCtx = null, lastChime = 0;
  function chime() {
    if (!soundOn || Date.now() - lastChime < 4000) return; // un seul son pour un même événement
    lastChime = Date.now();
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      var t0 = audioCtx.currentTime;
      [[880, 0], [1175, 0.14], [1568, 0.28]].forEach(function (n) {
        var o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'sine'; o.frequency.value = n[0];
        o.connect(g); g.connect(audioCtx.destination);
        g.gain.setValueAtTime(0.0001, t0 + n[1]);
        g.gain.exponentialRampToValueAtTime(0.25, t0 + n[1] + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t0 + n[1] + 0.35);
        o.start(t0 + n[1]); o.stop(t0 + n[1] + 0.4);
      });
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
  }
  // Débloque le son au premier toucher (exigence des navigateurs).
  document.addEventListener('pointerdown', function unlock() {
    try { audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)(); audioCtx.resume(); } catch (e) {}
    document.removeEventListener('pointerdown', unlock);
  });

  /* ---------------- Installation (PWA) ---------------- */
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var deferred = null;
  var card = document.querySelector('[data-install-card]');
  function showCard() {
    if (!card || standalone || store.get('fd-install-hide') === '1') return;
    card.hidden = false;
  }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    $$('[data-pwa-install]').forEach(function (b) { b.hidden = false; });
    $$('[data-install-manual]').forEach(function (b) { b.hidden = true; });
    showCard();
  });
  window.addEventListener('appinstalled', function () {
    standalone = true;
    if (card) card.hidden = true;
    $$('[data-pwa-install]').forEach(function (b) { b.hidden = true; });
  });
  $$('[data-pwa-install]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!deferred) { openSheet('install'); return; }
      deferred.prompt();
      deferred.userChoice.then(function () { deferred = null; closeSheets(); });
    });
  });
  if (card) {
    card.querySelector('[data-install-dismiss]').addEventListener('click', function () { card.hidden = true; store.set('fd-install-hide', '1'); });
    if (isIOS && !standalone) {
      var t = card.querySelector('[data-install-text]');
      if (t) t.textContent = 'Sur iPhone : Partager → « Sur l\'écran d\'accueil ».';
      var more = document.createElement('button');
      more.type = 'button'; more.className = 'btn primary sm'; more.textContent = 'Comment ?';
      more.addEventListener('click', function () { openSheet('install'); });
      card.insertBefore(more, card.querySelector('[data-install-dismiss]'));
      showCard();
    }
  }
  $$('[data-install-ios]').forEach(function (el) { el.hidden = !isIOS; });
  $$('[data-install-android]').forEach(function (el) { el.hidden = isIOS || standalone; });
  $$('[data-install-done]').forEach(function (el) { el.hidden = !standalone; });
  if (standalone) $$('[data-pwa-install-tile]').forEach(function (el) { el.hidden = true; });

  /* ---------------- Notifications push ---------------- */
  var swReg = null;
  function b64uToBytes(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '=';
    var raw = atob(s), out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function bytesToB64u(buf) {
    var b = new Uint8Array(buf), s = '';
    for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]);
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  function post(data) {
    var body = new URLSearchParams(data); body.append('_csrf', FD.csrf);
    return fetch(FD.base + '/' + FD.area + '/push', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  var messages = {
    unsupported: 'Ce navigateur ne gère pas les notifications. Utilisez Chrome sur Android.',
    'ios-install': 'Sur iPhone, installez d\'abord l\'application (Plus → Installer), puis ouvrez-la depuis son icône.',
    denied: 'Notifications bloquées. Autorisez-les dans les réglages du navigateur pour ce site.',
    off: 'Notifications désactivées sur cet appareil.',
    on: '✅ Activées sur cet appareil'
  };
  function render(state) {
    $$('[data-push-status]').forEach(function (el) { el.textContent = messages[state]; });
    $$('[data-push-enable]').forEach(function (el) { el.hidden = state !== 'off'; });
    $$('[data-push-on]').forEach(function (el) { el.hidden = state !== 'on'; });
    $$('[data-push-dot]').forEach(function (el) { el.hidden = state !== 'off'; });
  }
  function refresh() {
    if (!FD.vapid || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !swReg) {
      return render(isIOS && !standalone ? 'ios-install' : 'unsupported');
    }
    if (Notification.permission === 'denied') return render('denied');
    swReg.pushManager.getSubscription().then(function (s) { render(s ? 'on' : 'off'); });
  }
  function enable() {
    Notification.requestPermission().then(function (p) {
      if (p !== 'granted') return refresh();
      return swReg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(FD.vapid) })
        .then(function (s) {
          return post({ action: 'subscribe', endpoint: s.endpoint, p256dh: bytesToB64u(s.getKey('p256dh')), auth: bytesToB64u(s.getKey('auth')) });
        })
        .then(function () { return post({ action: 'test' }); })
        .then(refresh);
    }).catch(function (e) { alert('Activation impossible : ' + e.message); refresh(); });
  }
  function disable() {
    swReg.pushManager.getSubscription().then(function (s) {
      if (!s) return;
      return post({ action: 'unsubscribe', endpoint: s.endpoint }).then(function () { return s.unsubscribe(); });
    }).then(refresh);
  }
  $$('[data-push-enable]').forEach(function (b) { b.addEventListener('click', enable); });
  $$('[data-push-disable]').forEach(function (b) { b.addEventListener('click', disable); });
  $$('[data-push-test]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      post({ action: 'test' }).then(function (r) {
        b.textContent = r.ok ? 'Notification envoyée ✓' : 'Échec de l\'envoi';
      }).catch(function () { b.textContent = 'Échec de l\'envoi'; }).then(function () { b.disabled = false; });
    });
  });

  if (FD.area === 'admin' || FD.area === 'agent') {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register(FD.base + '/sw.js', { scope: FD.base + '/' })
        .then(function () { return navigator.serviceWorker.ready; })
        .then(function (r) { swReg = r; refresh(); })
        .catch(function () { render('unsupported'); });
      // Notification reçue pendant que l'application est ouverte : son + rafraîchissement.
      navigator.serviceWorker.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'push') { chime(); dirty = true; reloadWhenIdle(); }
      });
    } else {
      render('unsupported');
    }
  }

  /* ---------------- Rafraîchissement automatique ---------------- */
  var poll = document.querySelector('[data-poll]');
  var last = null, dirty = false;
  function busy() {
    var a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
    if (document.querySelector('details.reject[open]') || $$('.sheet').some(function (s) { return !s.hidden; })) return true;
    return false;
  }
  function reloadWhenIdle() { if (dirty && !busy()) location.reload(); }
  function check() {
    if (!poll || document.hidden) return;
    fetch(poll.getAttribute('data-poll'), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var sig = JSON.stringify(d);
        if (last !== null && sig !== last) {
          var prev = JSON.parse(last);
          var fresh = FD.area === 'admin' ? (d.last_req > prev.last_req || d.last_op > prev.last_op) : true;
          if (fresh) chime();
          dirty = true;
        }
        last = sig;
        reloadWhenIdle();
      })
      .catch(function () {});
  }
  if (poll) {
    check();
    setInterval(check, 12000);
    document.addEventListener('visibilitychange', check);
  }
})();
