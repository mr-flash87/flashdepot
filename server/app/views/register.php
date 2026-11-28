<?php $pageTitle = 'Créer un compte distributeur'; ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f5f6f8">
<title><?= h($pageTitle) ?> · <?= h(app_name()) ?></title>
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="manifest" href="<?= h(url('manifest.webmanifest?area=admin')) ?>">
<script>try{var t=localStorage.getItem('fd-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="<?= h(url('assets/style.css?v=' . APP_VERSION)) ?>">
</head>
<body class="login-page">
<div class="login-box">
  <div class="login-head">
    <span class="mark"><?= icon('logo', 30) ?></span>
    <h1>Créer mon compte</h1>
    <p>Distributeurs Orange Money : suivez les dépôts et remboursements de vos agents automatiquement. <?= (int)setting('trial_days', '30') ?> jours d'essai gratuit.</p>
  </div>
  <?php if (!empty($error)): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if (!$open): ?>
    <div class="card center"><p>Les inscriptions sont fermées pour le moment.<?= setting('support_whatsapp') ? ' Contactez-nous sur WhatsApp au <b>' . h(setting('support_whatsapp')) . '</b>.' : '' ?></p></div>
  <?php else: ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <label>Nom de votre entreprise</label>
    <input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>" placeholder="Ex. ETS Transfert Service">
    <label>Votre numéro Orange Money</label>
    <input type="tel" name="phone" inputmode="numeric" required value="<?= h($_POST['phone'] ?? '') ?>" placeholder="70 00 00 00">
    <label>Email</label>
    <input type="email" name="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
    <label>Mot de passe (8 caractères min.)</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
    <button class="btn primary big">Commencer l'essai gratuit</button>
    <p class="small muted center">Aucun paiement maintenant. Vous gardez vos données à la fin de l'essai.</p>
  </form>
  <?php endif; ?>
  <p class="center small"><a href="<?= h(url('admin/login')) ?>">J'ai déjà un compte</a></p>
</div>
</body>
</html>
