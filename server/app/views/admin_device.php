<?php /** @var ?array $device */
$paired = $device && $device['token_hash'];
$codeValid = $device && $device['pair_code'] && $device['pair_expires_at'] > now();
?>
<div class="page-head"><div><a class="back" href="<?= h(url('admin/settings')) ?>">← Plus</a><h1>Téléphone du distributeur</h1></div></div>

<section class="card">
  <?php if ($paired): ?>
    <div class="device">
      <span class="stat-ic <?= device_online($device) ? 'in' : 'warn' ?>"><?= icon('phone', 22) ?></span>
      <div>
        <b><?= device_online($device) ? 'En ligne' : 'Hors ligne' ?></b>
        <div class="small muted">Dernier contact : <?= h(ago($device['last_seen_at'])) ?>
          <?= $device['battery'] !== null ? ' · batterie ' . (int)$device['battery'] . ' %' . ((int)$device['charging'] ? ' (en charge)' : '') : '' ?>
          <?= $device['app_version'] ? ' · app ' . h($device['app_version']) : '' ?></div>
      </div>
    </div>
    <?php if (!device_online($device)): ?>
      <div class="alert warn">Le téléphone ne donne plus de nouvelles. Vérifiez qu'il est allumé, connecté à Internet et que l'application FlashDépôt est ouverte. Les SMS reçus pendant la coupure seront envoyés dès le retour du réseau.</div>
    <?php endif; ?>
  <?php else: ?>
    <p>Aucun téléphone associé. Installez l'application FlashDépôt sur le téléphone qui a la <b>SIM Orange Money du distributeur</b>, puis générez un code.</p>
  <?php endif; ?>

  <?php if ($codeValid): ?>
    <div class="pair-code">
      <div class="small muted">Code à saisir dans l'application (valable 30 min)</div>
      <div class="code mono"><?= h($device['pair_code']) ?></div>
      <div class="small">Adresse du site : <b class="mono"><?= h(abs_url('')) ?></b></div>
    </div>
  <?php endif; ?>
  <div class="row">
    <?= action_button('admin/device/pair', $paired ? 'Associer un autre téléphone' : 'Générer un code d\'association', $paired ? 'btn' : 'btn primary') ?>
    <?php if ($paired): ?><?= action_button('admin/device/revoke', 'Dissocier', 'btn ghost', 'Dissocier ce téléphone ? Les SMS ne seront plus lus.') ?><?php endif; ?>
  </div>
</section>

<section class="card">
  <h2>Solde Orange Money</h2>
  <p class="big-num"><?= $device && $device['last_balance'] !== null ? h(fmt_amount($device['last_balance'])) : '—' ?></p>
  <p class="small muted">Mis à jour à chaque SMS. Il sert à repérer les faux SMS : le nouveau solde annoncé doit correspondre à l'ancien plus ou moins le montant.
    Si un SMS a été manqué, corrigez-le ici avec le solde réel (*144# → Mon compte).</p>
  <form method="post" action="<?= h(url('admin/device/balance')) ?>" class="row">
    <?= csrf_field() ?>
    <input type="text" name="balance" inputmode="numeric" placeholder="Solde réel" required>
    <button class="btn">Corriger</button>
  </form>
</section>

<section class="card">
  <h2>Installer l'application</h2>
  <ol class="steps">
    <li>Sur le téléphone de la SIM distributeur, téléchargez l'application : <a href="<?= h(url('downloads/flashdepot.apk')) ?>"><b>flashdepot.apk</b></a>, puis installez-la (autorisez les « sources inconnues »).</li>
    <li>Ouvrez l'application, saisissez l'adresse du site et le code ci-dessus, puis touchez « Associer ».</li>
    <li>Acceptez la lecture des SMS et les notifications, puis désactivez l'économie de batterie pour l'application.</li>
    <li>Sur Tecno, Infinix, itel ou Xiaomi : activez aussi le « Démarrage automatique ».</li>
    <li>Laissez le téléphone branché et connecté à Internet.</li>
  </ol>
  <p class="small muted">Seuls les SMS d'Orange Money sont envoyés au site. Les SMS personnels restent sur le téléphone.</p>
</section>
