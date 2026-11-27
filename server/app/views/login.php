<?php $isAdmin = ($area ?? '') === 'admin'; ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f5f6f8">
<title>Connexion · <?= h(app_name()) ?></title>
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="manifest" href="<?= h(url('manifest.webmanifest?area=' . ($isAdmin ? 'admin' : 'agent'))) ?>">
<script>try{var t=localStorage.getItem('fd-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="<?= h(url('assets/style.css?v=' . APP_VERSION)) ?>">
</head>
<body class="login-page">
<div class="login-box">
  <div class="login-head">
    <span class="mark"><?= icon('logo', 30) ?></span>
    <h1><?= h(app_name()) ?></h1>
    <p><?= $isAdmin ? 'Espace distributeur' : 'Espace agent : demandez vos dépôts et suivez votre compte' ?></p>
  </div>
  <?php if (!empty($error)): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <?php if ($isAdmin): ?>
      <label>Email</label>
      <input type="email" name="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
      <label>Mot de passe</label>
      <input type="password" name="password" required autocomplete="current-password">
    <?php else: ?>
      <label>Votre numéro Orange Money</label>
      <input type="tel" name="phone" inputmode="numeric" required placeholder="70 00 00 00" autocomplete="username" value="<?= h($_POST['phone'] ?? '') ?>">
      <label>Code PIN</label>
      <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" required autocomplete="current-password" class="pin">
      <p class="small muted">Ce n'est pas votre code secret Orange Money : c'est le code donné par votre distributeur.</p>
    <?php endif; ?>
    <button class="btn primary big">Se connecter</button>
  </form>
</div>
</body>
</html>
