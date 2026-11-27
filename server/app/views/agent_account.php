<?php /** @var array $agent */ ?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Code PIN</h1></div></div>

<div class="card">
  <div class="row-link"><span class="muted">Nom</span><b><?= h($agent['name']) ?></b></div>
  <div class="row-link"><span class="muted">Numéro de connexion</span><b class="mono"><?= h(fmt_phone($agent['login_phone'])) ?></b></div>
</div>

<form method="post" action="<?= h(url('agent/pin')) ?>" class="card form">
  <?= csrf_field() ?>
  <h2>Changer mon code PIN</h2>
  <label>Code PIN actuel</label>
  <input type="password" name="current" inputmode="numeric" required class="pin">
  <label>Nouveau code PIN (4 à 6 chiffres)</label>
  <input type="password" name="new" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required class="pin">
  <button class="btn primary big">Changer le code</button>
  <p class="small muted">Ce code sert seulement à ouvrir FlashDépôt. Ne donnez jamais votre code secret Orange Money.</p>
</form>
