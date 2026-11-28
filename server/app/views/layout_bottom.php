<?php
$area = $area ?? 'public';
$hasApp = in_array($area, ['admin', 'agent', 'superadmin'], true);
?>
</main>
</div>

<?php if ($hasApp):
    // Reprend les onglets calculés par layout_top.php (même requête).
    $tabsB = match ($area) {
        'admin' => [['/admin', 'admin', 'home', 'Accueil'], ['/admin/requests', 'admin/requests', 'inbox', 'Demandes'], ['/admin/agents', 'admin/agents', 'users', 'Agents'], ['/admin/operations', 'admin/operations', 'list', 'Opérations']],
        'superadmin' => [['/superadmin', 'superadmin', 'users', 'Distributeurs'], ['/superadmin/payments', 'superadmin/payments', 'wallet', 'Paiements'], ['/superadmin/settings', 'superadmin/settings', 'gear', 'Plateforme']],
        default => [['/agent', 'agent', 'home', 'Accueil'], ['/agent/demande', 'agent/demande', 'send', 'Demander'], ['/agent/releve', 'agent/releve', 'chart', 'Relevé']],
    };
    $relB = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', strlen(base_path())), '/');
    $badges = [];
    if ($area === 'admin' && !empty($stats)) {
        $badges = ['/admin/requests' => $stats['pending_requests'], '/admin/operations' => $stats['unassigned'] + $stats['review']];
    }
    $moreB = $area === 'superadmin' ? [['#install', 'download', 'Installer']] : ($area === 'admin'
        ? [['admin/device', 'phone', 'Téléphone'], ['admin/sms', 'sms', 'SMS reçus'], ['admin/settings', 'gear', 'Réglages'], ['admin/subscription', 'wallet', 'Abonnement'], ['#notif', 'bell', 'Notifications'], ['#install', 'download', 'Installer']]
        : [['agent/compte', 'lock', 'Code PIN'], ['#notif', 'bell', 'Notifications'], ['#install', 'download', 'Installer']]);
?>
<nav class="tabbar">
  <?php foreach ($tabsB as [$prefix, $href, $ic, $label]):
      $on = in_array($prefix, ['/admin', '/agent', '/superadmin'], true) ? $relB === $prefix : str_starts_with($relB, $prefix); ?>
    <a href="<?= h(url($href)) ?>"<?= $on ? ' class="on"' : '' ?>><span class="ti"><?= icon($ic, 22) ?><?php if (($badges[$prefix] ?? 0) > 0): ?><b class="count"><?= (int)$badges[$prefix] ?></b><?php endif; ?></span><span><?= h($label) ?></span></a>
  <?php endforeach; ?>
  <button type="button" data-open="more"><span class="ti"><?= icon('grid', 22) ?></span><span>Plus</span></button>
</nav>

<div class="sheet" id="sheet-more" hidden>
  <div class="sheet-bg" data-close></div>
  <div class="sheet-card" role="dialog" aria-label="Plus">
    <div class="sheet-head"><h2>Plus</h2><button type="button" class="x" data-close aria-label="Fermer"><?= icon('x', 22) ?></button></div>
    <div class="tiles">
      <?php foreach ($moreB as [$href, $ic, $label]): ?>
        <?php if ($href[0] === '#'): ?>
          <button type="button" class="tile" data-open="<?= h(substr($href, 1)) ?>"<?= $href === '#install' ? ' data-pwa-install-tile' : '' ?>><?= icon($ic, 24) ?><span><?= h($label) ?></span></button>
        <?php else: ?>
          <a class="tile" href="<?= h(url($href)) ?>"><?= icon($ic, 24) ?><span><?= h($label) ?></span></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <a class="btn out-btn" href="<?= h(url($area . '/logout')) ?>"><?= icon('logout', 20) ?> Se déconnecter</a>
  </div>
</div>

<div class="sheet" id="sheet-notif" hidden>
  <div class="sheet-bg" data-close></div>
  <div class="sheet-card" role="dialog" aria-label="Notifications">
    <div class="sheet-head"><h2>Notifications</h2><button type="button" class="x" data-close aria-label="Fermer"><?= icon('x', 22) ?></button></div>
    <div class="notif-state">
      <span class="notif-ic"><?= icon('bell', 26) ?></span>
      <p data-push-status>Chargement…</p>
    </div>
    <p class="small muted"><?= $area === 'admin'
        ? 'Soyez prévenu avec le son du téléphone à chaque demande de dépôt, dépôt envoyé, remboursement reçu ou SMS à vérifier, même téléphone verrouillé.'
        : 'Soyez prévenu avec le son du téléphone quand votre demande est validée, quand le dépôt arrive et quand un remboursement est enregistré.' ?></p>
    <button type="button" class="btn primary big" data-push-enable hidden><?= icon('bell', 20) ?> Activer les notifications</button>
    <div class="row" data-push-on hidden>
      <button type="button" class="btn" data-push-test>Envoyer un test</button>
      <button type="button" class="btn ghost" data-push-disable>Désactiver</button>
    </div>
    <label class="check sound"><input type="checkbox" data-sound-toggle checked> Son quand l'application est ouverte</label>
  </div>
</div>

<div class="sheet" id="sheet-install" hidden>
  <div class="sheet-bg" data-close></div>
  <div class="sheet-card" role="dialog" aria-label="Installer">
    <div class="sheet-head"><h2>Installer l'application</h2><button type="button" class="x" data-close aria-label="Fermer"><?= icon('x', 22) ?></button></div>
    <div data-install-android>
      <p>Ajoutez <?= h(app_name()) ?> à l'écran d'accueil : elle s'ouvre comme une vraie application et peut vous envoyer des notifications.</p>
      <button type="button" class="btn primary big" data-pwa-install hidden><?= icon('download', 20) ?> Installer maintenant</button>
      <p class="small muted" data-install-manual>Si le bouton n'apparaît pas : menu <b>⋮</b> de Chrome → <b>« Installer l'application »</b> ou <b>« Ajouter à l'écran d'accueil »</b>.</p>
    </div>
    <div data-install-ios hidden>
      <ol class="steps">
        <li>Ouvrez cette page dans <b>Safari</b>.</li>
        <li>Touchez le bouton <b>Partager</b> (carré avec une flèche).</li>
        <li>Choisissez <b>« Sur l'écran d'accueil »</b>, puis <b>Ajouter</b>.</li>
        <li>Ouvrez l'application depuis son icône pour activer les notifications.</li>
      </ol>
    </div>
    <p class="small muted" data-install-done hidden>✅ L'application est déjà installée sur cet appareil.</p>
  </div>
</div>
<?php endif; ?>

<script src="<?= h(url('assets/app.js?v=' . APP_VERSION)) ?>"></script>
</body>
</html>
