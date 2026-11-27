<?php /** @var array $user */ ?>
<div class="page-head"><h1>Réglages</h1></div>

<div class="menu">
  <a href="<?= h(url('admin/device')) ?>"><?= icon('phone', 22) ?><span><b>Téléphone</b><small>Associer le téléphone qui lit les SMS, solde Orange Money</small></span><?= icon('chevron') ?></a>
  <a href="<?= h(url('admin/sms')) ?>"><?= icon('sms', 22) ?><span><b>SMS reçus</b><small>Tous les SMS Orange Money transmis, tester la lecture</small></span><?= icon('chevron') ?></a>
  <a href="<?= h(url('agent/login')) ?>" target="_blank"><?= icon('users', 22) ?><span><b>Page des agents</b><small><?= h(abs_url('agent')) ?></small></span><?= icon('chevron') ?></a>
</div>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="general">
  <h2>Général</h2>
  <label>Nom de l'application</label>
  <input type="text" name="app_name" value="<?= h(app_name()) ?>">
  <label>Code USSD de dépôt (facultatif)</label>
  <input type="text" name="deposit_ussd" value="<?= h(setting('deposit_ussd')) ?>" placeholder="*144*…*{number}*{amount}#" class="mono">
  <p class="small muted">Affiche un bouton « Composer » sur chaque demande. <code>{number}</code> = numéro de l'agent, <code>{amount}</code> = montant. Vous saisissez toujours votre code secret vous-même.</p>
  <label>Montant minimum d'une demande</label>
  <input type="text" name="min_request" inputmode="numeric" value="<?= h(setting('min_request')) ?>">
  <label class="check"><input type="checkbox" name="balance_check" value="1" <?= setting('balance_check') === '1' ? 'checked' : '' ?>> Contrôle du solde contre les faux SMS (recommandé)</label>
  <button class="btn primary">Enregistrer</button>
</form>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="telegram">
  <h2>Notifications Telegram</h2>
  <p class="small muted">Recevez chaque demande, dépôt et remboursement sur Telegram.
    1) Dans Telegram, parlez à <b>@BotFather</b>, envoyez <code>/newbot</code> et copiez le jeton.
    2) Envoyez un message à votre bot, puis ouvrez <span class="mono">api.telegram.org/bot&lt;jeton&gt;/getUpdates</span> pour lire votre « chat id ».</p>
  <label>Jeton du bot</label>
  <input type="text" name="tg_token" value="<?= h(setting('tg_token')) ?>" class="mono" autocomplete="off">
  <label>Chat id</label>
  <input type="text" name="tg_chat_id" value="<?= h(setting('tg_chat_id')) ?>" class="mono">
  <div class="row">
    <button class="btn primary">Enregistrer</button>
    <button class="btn" name="test" value="1">Enregistrer et tester</button>
  </div>
</form>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="password">
  <h2>Mot de passe</h2>
  <label>Mot de passe actuel</label>
  <input type="password" name="current" required autocomplete="current-password">
  <label>Nouveau mot de passe (8 caractères min.)</label>
  <input type="password" name="new" required minlength="8" autocomplete="new-password">
  <button class="btn">Changer</button>
</form>
<p class="center small muted"><?= h(app_name()) ?> · version <?= h(APP_VERSION) ?></p>
