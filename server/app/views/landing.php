<?php
$trial = (int)setting('trial_days', '30');
$support = trim((string)setting('support_whatsapp'));
$wa = $support !== '' ? 'https://wa.me/' . preg_replace('/\D+/', '', $support) : '';
$plans = plans();
$open = setting('registrations_open') === '1';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h(app_name()) ?> · Suivez les dépôts Orange Money de vos agents automatiquement</title>
<meta name="description" content="Logiciel pour distributeurs Orange Money : vos agents demandent leurs dépôts en ligne, chaque transfert et remboursement est enregistré automatiquement grâce aux SMS. Essai gratuit <?= $trial ?> jours.">
<meta name="theme-color" content="#f5f6f8">
<meta property="og:title" content="<?= h(app_name()) ?> · Dépôts Orange Money suivis automatiquement">
<meta property="og:description" content="Fini le cahier et les dettes oubliées : demandes en ligne, SMS lus automatiquement, relevé par agent.">
<meta property="og:image" content="<?= h(abs_url('assets/icon-512.png')) ?>">
<script>try{var t=localStorage.getItem('fd-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= h(url('assets/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= h(url('assets/style.css?v=' . APP_VERSION)) ?>">
</head>
<body class="lp">

<header class="lp-nav">
  <a class="brand" href="<?= h(url('')) ?>"><span class="mark"><?= icon('logo', 20) ?></span><span><?= h(app_name()) ?></span></a>
  <nav class="lp-links">
    <a href="#comment">Fonctionnement</a>
    <a href="#tarifs">Tarifs</a>
    <a href="#faq">Questions</a>
  </nav>
  <div class="lp-nav-cta">
    <a class="btn sm" href="<?= h(url('admin/login')) ?>">Se connecter</a>
    <?php if ($open): ?><a class="btn primary sm lp-hide-sm" href="<?= h(url('inscription')) ?>">Essai gratuit</a><?php endif; ?>
  </div>
</header>

<section class="lp-hero">
  <div class="lp-hero-text">
    <span class="lp-pill">Pour les distributeurs Orange Money</span>
    <h1>Les dépôts de vos agents, suivis <span class="lp-accent">automatiquement</span>.</h1>
    <p class="lp-lead">Vos agents demandent leurs dépôts en ligne, vous validez en un clic, et chaque transfert ou remboursement est enregistré tout seul grâce aux SMS Orange Money. Fini le cahier, les calculs et les dettes oubliées.</p>
    <div class="lp-cta">
      <?php if ($open): ?><a class="btn primary big" href="<?= h(url('inscription')) ?>">Commencer l'essai gratuit</a><?php endif; ?>
      <a class="btn big" href="#comment">Voir comment ça marche</a>
    </div>
    <p class="small muted"><?= $trial ?> jours gratuits · sans engagement · aucun code secret demandé</p>
  </div>
  <div class="lp-phone" aria-hidden="true">
    <div class="lp-screen">
      <div class="lp-mini-head"><span class="mark"><?= icon('logo', 14) ?></span><b><?= h(app_name()) ?></b></div>
      <div class="hero lp-mini-hero">
        <div class="hero-label">Total dû par les agents</div>
        <div class="hero-value">1 250 000 F</div>
        <div class="hero-sub">4 agents avec une dette</div>
      </div>
      <div class="lp-notif"><span class="chipic in"><?= icon('down') ?></span><div><b>Remboursement reçu : 400 000 F</b><small>KAFANDO · reste à payer 100 000 F</small></div></div>
      <div class="lp-notif"><span class="chipic out"><?= icon('inbox') ?></span><div><b>Demande de 500 000 F</b><small>OUEMA · il y a 2 min</small></div></div>
      <div class="lp-notif"><span class="chipic out"><?= icon('up') ?></span><div><b>Dépôt envoyé : 500 000 F</b><small>détecté par SMS · demande validée</small></div></div>
    </div>
  </div>
</section>

<section class="lp-strip">
  <div><b>0</b><span>dette oubliée</span></div>
  <div><b>Temps réel</b><span>chaque SMS compte tout de suite</span></div>
  <div><b>100 %</b><span>votre argent reste chez vous</span></div>
</section>

<section class="lp-section" id="comment">
  <h2 class="lp-title">Comment ça marche</h2>
  <div class="lp-steps">
    <div class="lp-step"><span class="lp-num">1</span><h3>L'agent demande</h3><p>Depuis son téléphone, l'agent tape le montant qu'il veut. Vous êtes prévenu tout de suite, avec un son.</p></div>
    <div class="lp-step"><span class="lp-num">2</span><h3>Vous validez et envoyez</h3><p>Vous validez la demande puis faites le transfert comme d'habitude. Le SMS « vous avez transféré… » l'enregistre automatiquement.</p></div>
    <div class="lp-step"><span class="lp-num">3</span><h3>Le remboursement est détecté</h3><p>Quand l'agent vous renvoie l'argent, même en partie, sa dette baisse toute seule. Le cash se note en un clic.</p></div>
  </div>
</section>

<section class="lp-section">
  <h2 class="lp-title">Tout ce qu'il faut pour gérer vos agents</h2>
  <div class="lp-features">
    <div class="card"><span class="chipic out"><?= icon('inbox') ?></span><h3>Demandes en ligne</h3><p>Plus d'appels à toute heure : chaque agent a son espace pour demander ses dépôts et suivre leur statut.</p></div>
    <div class="card"><span class="chipic in"><?= icon('sms') ?></span><h3>SMS lus automatiquement</h3><p>Un téléphone Android avec votre SIM lit les SMS Orange Money et met à jour les dettes en temps réel.</p></div>
    <div class="card"><span class="chipic blue"><?= icon('cash') ?></span><h3>Partiel, total ou cash</h3><p>Remboursements partiels acceptés, cash en un clic, corrections possibles. Le reste à payer est toujours juste.</p></div>
    <div class="card"><span class="chipic warn"><?= icon('alert') ?></span><h3>Protection anti-faux SMS</h3><p>Le solde annoncé est vérifié à chaque SMS, et un même SMS n'est jamais compté deux fois.</p></div>
    <div class="card"><span class="chipic out"><?= icon('bell') ?></span><h3>Notifications avec son</h3><p>Vous et vos agents êtes prévenus sur le téléphone : demande, dépôt, remboursement.</p></div>
    <div class="card"><span class="chipic in"><?= icon('chart') ?></span><h3>Relevé par agent</h3><p>Historique complet, solde après chaque opération, tableau de bord du jour. Installable comme une appli.</p></div>
  </div>
</section>

<section class="lp-section lp-trust">
  <div class="card">
    <span class="chipic in"><?= icon('lock', 22) ?></span>
    <div>
      <h3>Votre argent ne passe jamais par nous</h3>
      <p><?= h(app_name()) ?> est un outil de suivi : vous faites vos transferts vous-même, comme d'habitude. Nous ne demandons et ne stockons <b>aucun code secret Orange Money</b>. Seuls les SMS Orange Money sont transmis, vos SMS personnels restent sur votre téléphone.</p>
    </div>
  </div>
</section>

<section class="lp-section" id="tarifs">
  <h2 class="lp-title">Tarifs simples</h2>
  <p class="lp-sub"><?= $trial ?> jours d'essai gratuit avec toutes les fonctions. Ensuite, un abonnement mensuel payé par Orange Money.</p>
  <div class="lp-prices">
    <?php $i = 0; foreach ($plans as $key => $p): $i++; ?>
      <div class="card lp-price<?= $key === 'pro' ? ' lp-best' : '' ?>">
        <?php if ($key === 'pro'): ?><span class="lp-tag">Le plus choisi</span><?php endif; ?>
        <h3><?= h($p['name']) ?></h3>
        <div class="lp-amount"><?= h(number_format((float)$p['price'], 0, ',', ' ')) ?> <small>F / mois</small></div>
        <ul>
          <li><?= icon('check') ?> <?= $p['agents'] > 0 ? 'Jusqu\'à ' . (int)$p['agents'] . ' agents' : 'Agents illimités' ?></li>
          <li><?= icon('check') ?> Lecture automatique des SMS</li>
          <li><?= icon('check') ?> Notifications et relevés</li>
          <?php if ($key !== 'starter'): ?><li><?= icon('check') ?> Support prioritaire</li><?php endif; ?>
        </ul>
        <?php if ($open): ?><a class="btn <?= $key === 'pro' ? 'primary' : '' ?> big" href="<?= h(url('inscription')) ?>">Essayer gratuitement</a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="lp-section" id="faq">
  <h2 class="lp-title">Questions fréquentes</h2>
  <div class="lp-faq">
    <details class="card"><summary>Dois-je donner mon code secret Orange Money ?</summary><p>Non, jamais. Vous faites vos transferts vous-même. <?= h(app_name()) ?> lit seulement les SMS de confirmation pour tenir les comptes.</p></details>
    <details class="card"><summary>De quoi ai-je besoin pour commencer ?</summary><p>Un téléphone Android avec la SIM Orange Money de votre activité (pour lire les SMS) et une connexion Internet. Vous installez notre application en 2 minutes, puis vous ajoutez vos agents.</p></details>
    <details class="card"><summary>Mes agents doivent-ils installer quelque chose ?</summary><p>Non. Ils ouvrent une page web avec leur numéro et un code PIN que vous leur donnez. Ils peuvent l'ajouter à leur écran d'accueil comme une application.</p></details>
    <details class="card"><summary>Et si un agent rembourse en cash ou en plusieurs fois ?</summary><p>Les remboursements partiels sont détectés automatiquement. Le cash s'enregistre en un clic sur la fiche de l'agent.</p></details>
    <details class="card"><summary>Que se passe-t-il si j'arrête de payer ?</summary><p>Votre espace passe en lecture seule : vous gardez l'accès à tout votre historique. Il se débloque dès le paiement suivant.</p></details>
    <details class="card"><summary>Comment payer l'abonnement ?</summary><p>Par Orange Money, depuis votre numéro : l'abonnement est prolongé automatiquement dès réception.</p></details>
  </div>
</section>

<section class="lp-final">
  <h2>Prêt à ne plus jamais oublier une dette ?</h2>
  <p>Créez votre compte en 1 minute et testez gratuitement pendant <?= $trial ?> jours.</p>
  <div class="lp-cta">
    <?php if ($open): ?><a class="btn solid big" href="<?= h(url('inscription')) ?>">Commencer gratuitement</a><?php endif; ?>
    <?php if ($wa !== ''): ?><a class="btn big" href="<?= h($wa) ?>" target="_blank" rel="noopener">Parler sur WhatsApp</a><?php endif; ?>
  </div>
</section>

<footer class="lp-foot">
  <div class="brand"><span class="mark"><?= icon('logo', 18) ?></span><span><?= h(app_name()) ?></span></div>
  <nav>
    <a href="<?= h(url('admin/login')) ?>">Espace distributeur</a>
    <a href="<?= h(url('agent/login')) ?>">Espace agent</a>
    <?php if ($wa !== ''): ?><a href="<?= h($wa) ?>" target="_blank" rel="noopener">Support WhatsApp</a><?php endif; ?>
  </nav>
  <p class="small muted"><?= h(app_name()) ?> est un outil de suivi indépendant, compatible avec Orange Money. Il n'est pas affilié à Orange.</p>
</footer>
</body>
</html>
