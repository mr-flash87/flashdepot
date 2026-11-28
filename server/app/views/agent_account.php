<?php /** @var array $agent */ ?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Mon compte</h1></div></div>

<div class="card ag-profile">
  <span class="avatar"><?= h(mb_strtoupper(mb_substr($agent['name'], 0, 2))) ?></span>
  <div><b><?= h($agent['name']) ?></b><div class="small muted"><?= h(implode(' · ', array_map('fmt_phone', agent_phones((int)$agent['id'])))) ?></div><div class="small muted">Distributeur : <?= h(distributor()['name']) ?></div></div>
</div>

<form method="post" action="<?= h(url('agent/pin')) ?>" class="card form">
  <?= csrf_field() ?>
  <h2>Changer mon code PIN</h2>
  <label>Code PIN actuel</label>
  <input type="password" name="current" inputmode="numeric" required class="pin">
  <label>Nouveau code PIN (4 à 6 chiffres)</label>
  <input type="password" name="new" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required class="pin">
  <button class="btn primary big">Changer le code</button>
  <p class="small muted">Ce code sert seulement à ouvrir <?= h(app_name()) ?>. Ne donnez jamais votre code secret Orange Money ou Moov Money.</p>
</form>

<a class="btn out-btn" href="<?= h(url('agent/logout')) ?>"><?= icon('logout', 20) ?> Se déconnecter</a>
