<?php /** @var array $user @var ?array $device */
$codeValid = $device && $device['pair_code'] && $device['pair_expires_at'] > now();
$paired = $device && $device['token_hash'];
?>
<div class="page-head"><div><div class="sub">Super-admin</div><h1>Plateforme</h1></div></div>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="platform">
  <h2>Abonnements</h2>
  <label>Votre numéro Orange Money (où les distributeurs paient)</label>
  <input type="tel" name="pay_number" inputmode="numeric" value="<?= h(setting('pay_number')) ?>" placeholder="70 00 00 00">
  <label>WhatsApp du support</label>
  <input type="text" name="support_whatsapp" value="<?= h(setting('support_whatsapp')) ?>" placeholder="+226 70 00 00 00">
  <label>Durée de l'essai gratuit (jours)</label>
  <input type="number" name="trial_days" min="0" max="90" value="<?= h(setting('trial_days')) ?>">
  <label class="check"><input type="checkbox" name="registrations_open" value="1" <?= setting('registrations_open') === '1' ? 'checked' : '' ?>> Inscriptions ouvertes (<span class="mono"><?= h(abs_url('inscription')) ?></span>)</label>

  <h2 style="margin-top:18px">Formules</h2>
  <?php foreach (plans() as $k => $p): ?>
    <div class="row" style="margin-bottom:8px">
      <input type="text" name="plan_name[<?= h($k) ?>]" value="<?= h($p['name']) ?>" style="max-width:130px">
      <input type="text" name="plan_price[<?= h($k) ?>]" inputmode="numeric" value="<?= (int)$p['price'] ?>" title="Prix par mois (F)">
      <input type="number" name="plan_agents[<?= h($k) ?>]" min="0" value="<?= (int)$p['agents'] ?>" title="Agents (0 = illimité)" style="max-width:110px">
    </div>
  <?php endforeach; ?>
  <p class="small muted">Nom · prix par mois en F · nombre d'agents (0 = illimité). L'essai gratuit donne les mêmes droits que la formule Pro.</p>
  <button class="btn primary">Enregistrer</button>
</form>

<section class="card">
  <h2>Téléphone de la plateforme</h2>
  <p class="small muted">Installez l'application FlashDépôt sur le téléphone de votre numéro de paiement et associez-le ici : chaque abonnement payé par Orange Money est reconnu et prolongé automatiquement.</p>
  <?php if ($paired): ?>
    <p><b><?= device_online($device) ? '✅ En ligne' : '⚠️ Hors ligne' ?></b> <span class="muted small">· dernier contact <?= h(ago($device['last_seen_at'])) ?></span></p>
  <?php endif; ?>
  <?php if ($codeValid): ?>
    <div class="pair-code">
      <div class="small muted">Code à saisir dans l'application (30 min)</div>
      <div class="code mono"><?= h($device['pair_code']) ?></div>
      <div class="small">Adresse du site : <b class="mono"><?= h(abs_url('')) ?></b></div>
    </div>
  <?php endif; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="pair"><button class="btn<?= $paired ? '' : ' primary' ?>"><?= $paired ? 'Associer un autre téléphone' : 'Générer un code d\'association' ?></button></form>
</section>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="password">
  <h2>Mon mot de passe</h2>
  <p class="small muted"><?= h($user['email']) ?></p>
  <input type="password" name="current" placeholder="Mot de passe actuel" required autocomplete="current-password">
  <input type="password" name="new" placeholder="Nouveau (8 caractères min.)" minlength="8" required autocomplete="new-password">
  <button class="btn">Changer</button>
</form>
