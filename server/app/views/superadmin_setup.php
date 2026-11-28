<?php $pageTitle = 'Créer le super-admin'; ?><!doctype html>
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
    <h1>Super-admin</h1>
    <p>Première configuration : créez le compte du propriétaire de la plateforme.</p>
  </div>
  <?php if (!empty($error)): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <label>Clé du serveur</label>
    <input type="password" name="setup_key" required autocomplete="off">
    <p class="small muted">C'est le <b>mot de passe de la base MySQL</b> saisi à l'installation (hPanel → Bases de données). Il prouve que vous êtes le propriétaire du serveur.</p>
    <label>Votre email (différent de celui des distributeurs)</label>
    <input type="email" name="email" required autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
    <label>Mot de passe (8 caractères min.)</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
    <button class="btn primary big">Créer le compte super-admin</button>
  </form>
</div>
</body>
</html>
