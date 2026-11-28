<?php
$trial = (int)setting('trial_days', '30');
$support = trim((string)setting('support_whatsapp'));
$wa = $support !== '' ? 'https://wa.me/' . preg_replace('/\D+/', '', $support) : '';
$plans = plans();
$open = setting('registrations_open') === '1';
$cta = $open ? url('inscription') : url('admin/login');
$arrow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$chev = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h(app_name()) ?> · Les dépôts Orange Money de vos agents, suivis tout seuls</title>
<meta name="description" content="Logiciel pour distributeurs Orange Money : vos agents demandent leurs dépôts en ligne, chaque transfert et remboursement est enregistré automatiquement grâce aux SMS. Essai gratuit <?= $trial ?> jours.">
<meta name="theme-color" content="#eceef2">
<meta property="og:title" content="<?= h(app_name()) ?> · Dépôts Orange Money suivis automatiquement">
<meta property="og:description" content="Demandes en ligne, SMS lus automatiquement, dette de chaque agent à jour en temps réel.">
<meta property="og:image" content="<?= h(abs_url('assets/icon-512.png')) ?>">
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= h(url('assets/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="<?= h(asset_url('assets/style.css')) ?>">
</head>
<body class="lp">

<header class="lp-nav-wrap">
  <nav class="lp-nav">
    <a class="lp-brand" href="<?= h(url('')) ?>"><span class="mark"><?= icon('logo', 18) ?></span><span><?= h(app_name()) ?></span></a>
    <div class="lp-links">
      <a href="#fonctions">Fonctionnalités</a>
      <a href="#tarifs">Tarifs</a>
      <a href="#faq">FAQ</a>
    </div>
    <div class="lp-nav-cta">
      <a class="lp-btn lp-btn-ghost" href="<?= h(url('admin/login')) ?>">Se connecter</a>
      <?php if ($open): ?><a class="lp-btn lp-btn-dark lp-hide-xs" href="<?= h(url('inscription')) ?>">Essai gratuit</a><?php endif; ?>
    </div>
  </nav>
</header>

<main>
<!-- ============================================================ Accueil -->
<section class="lp-panel lp-hero">
  <div class="lp-net" aria-hidden="true">
    <svg class="lp-net-lines" viewBox="0 0 1000 400" preserveAspectRatio="none">
      <path d="M500 200 C 420 200, 380 70, 250 70"/>
      <path class="lp-l-side" d="M500 200 C 400 200, 320 215, 150 215"/>
      <path d="M500 200 C 420 200, 380 340, 260 340"/>
      <path d="M500 200 C 580 200, 620 70, 750 70"/>
      <path class="lp-l-side" d="M500 200 C 600 200, 690 200, 850 200"/>
      <path d="M500 200 C 580 200, 620 340, 740 340"/>
    </svg>
    <div class="lp-node lp-center"><span class="lp-logo-big"><?= icon('logo', 56) ?></span></div>
    <div class="lp-node lp-chip" style="left:25%;top:17.5%"><?= icon('users', 24) ?></div>
    <div class="lp-node lp-card lp-card-left" style="left:15%;top:53.75%">
      <span class="lp-ic lp-ic-orange"><?= icon('inbox', 18) ?></span>
      <span><b>Nouvelle demande</b><small>OUEMA · 500 000 F</small></span>
    </div>
    <div class="lp-node lp-chip" style="left:26%;top:85%"><?= icon('bell', 24) ?></div>
    <div class="lp-node lp-chip lp-chip-sms" style="left:75%;top:17.5%"><?= icon('sms', 24) ?></div>
    <div class="lp-node lp-card lp-card-dark" style="left:85%;top:50%">
      <small>Dette de KAFANDO</small>
      <b>100 000 F</b>
      <span class="lp-ok"><?= icon('check', 14) ?> mise à jour par SMS</span>
    </div>
    <div class="lp-node lp-chip" style="left:74%;top:85%"><?= icon('cash', 24) ?></div>
  </div>

  <h1 class="lp-h1">Les dépôts de vos agents,<br>suivis <span class="lp-accent">tout seuls.</span></h1>
  <p class="lp-lead">Vos agents demandent leurs dépôts en ligne, vous validez, et chaque transfert ou remboursement Orange Money est enregistré automatiquement grâce aux SMS.</p>
  <a class="lp-btn lp-btn-main" href="<?= h($cta) ?>"><?= $open ? 'Commencer gratuitement' : 'Se connecter' ?> <?= $arrow ?></a>
  <p class="lp-note"><?= $trial ?> jours gratuits · sans engagement · aucun code secret demandé</p>
</section>

<!-- ============================================================ 3 étapes -->
<section class="lp-panel lp-white" id="comment">
  <div class="lp-head">
    <span class="lp-badge-ic"><?= icon('list', 18) ?></span>
    <h2>Demandez. Validez. <span class="lp-accent">C'est noté.</span></h2>
    <p>Trois gestes, et votre cahier de dettes se remplit tout seul.</p>
  </div>
  <div class="lp-steps">
    <article class="lp-step">
      <div class="lp-illus">
        <div class="lp-mock">
          <small>Montant souhaité</small>
          <div class="lp-mock-amount">500 000</div>
          <div class="lp-mock-chips"><span>100 000</span><span>250 000</span><span class="on">500 000</span></div>
          <div class="lp-mock-btn"><?= icon('send', 14) ?> Envoyer la demande</div>
        </div>
      </div>
      <h3><span class="lp-step-n">1</span>L'agent demande</h3>
      <p>Depuis son téléphone, en quelques secondes. Vous êtes prévenu avec un son.</p>
    </article>
    <article class="lp-step">
      <div class="lp-illus">
        <div class="lp-mock">
          <div class="lp-mock-row"><b>OUEMA ISSA</b><b>500 000 F</b></div>
          <small>Dette actuelle : 200 000 F</small>
          <div class="lp-mock-actions"><span class="lp-mock-btn"><?= icon('check', 14) ?> Valider</span><span class="lp-mock-btn ghost">Refuser</span></div>
        </div>
      </div>
      <h3><span class="lp-step-n">2</span>Vous validez et envoyez</h3>
      <p>Vous faites le transfert comme d'habitude : le SMS de confirmation enregistre le dépôt.</p>
    </article>
    <article class="lp-step">
      <div class="lp-illus">
        <div class="lp-sms">« Vous avez reçu 400 000 FCFA du 64 31 80 90… »</div>
        <div class="lp-mock lp-mock-sm">
          <div class="lp-mock-row"><span><span class="lp-dot-green"></span> Remboursement</span><b class="lp-green">−400 000 F</b></div>
          <small>Reste à payer : 100 000 F</small>
        </div>
      </div>
      <h3><span class="lp-step-n">3</span>Le remboursement est détecté</h3>
      <p>Total ou partiel, la dette baisse toute seule. Le cash se note en un clic.</p>
    </article>
  </div>
</section>

<!-- ============================================================ Fonctions (mosaïque) -->
<section class="lp-panel" id="fonctions">
  <div class="lp-head">
    <span class="lp-pill"><?= icon('home', 14) ?> Pour les distributeurs</span>
    <h2>Pensé pour <span class="lp-accent">gérer vos agents</span></h2>
    <p>Tout ce qu'il faut pour ne plus jamais perdre le fil d'une dette — rien de compliqué.</p>
  </div>
  <div class="lp-bento">
    <article class="lp-tile">
      <div class="lp-illus lp-illus-sms">
        <div class="lp-bubble">Vous avez transféré 500 000 FCFA au 44 49 06 75…</div>
        <div class="lp-bubble b2">Vous avez reçu 250 000 FCFA du 70 11 22 33…</div>
        <div class="lp-arrowdown"><?= icon('down', 18) ?></div>
        <div class="lp-mini-ok"><?= icon('check', 14) ?> 2 opérations enregistrées</div>
      </div>
      <h3>SMS lus automatiquement</h3>
      <p>Un téléphone Android avec votre SIM transmet les SMS Orange Money. Vos SMS personnels restent privés.</p>
    </article>
    <article class="lp-tile">
      <div class="lp-illus">
        <div class="lp-list">
          <div><span><span class="lp-ic lp-ic-green"><?= icon('down', 14) ?></span> Orange Money</span><b>−150 000</b></div>
          <div><span><span class="lp-ic lp-ic-blue"><?= icon('cash', 14) ?></span> Cash</span><b>−50 000</b></div>
          <div class="on"><span><span class="lp-ic lp-ic-orange"><?= icon('wallet', 14) ?></span> Reste à payer</span><b>300 000</b></div>
        </div>
      </div>
      <h3>Partiel, total ou cash</h3>
      <p>Chaque remboursement compte, même en plusieurs fois. Le reste à payer est toujours juste.</p>
    </article>
    <article class="lp-tile">
      <div class="lp-illus">
        <div class="lp-notifs">
          <div class="n1"><span class="lp-ic lp-ic-orange"><?= icon('inbox', 14) ?></span><span><b>Demande de 300 000 F</b><small>ZONGO · à l'instant</small></span></div>
          <div class="n2"><span class="lp-ic lp-ic-green"><?= icon('check', 14) ?></span><span><b>Remboursement reçu</b><small>KAFANDO · 400 000 F</small></span></div>
        </div>
      </div>
      <h3>Notifications avec son</h3>
      <p>Vous et vos agents êtes prévenus sur le téléphone, même écran verrouillé.</p>
    </article>
    <article class="lp-tile lp-wide">
      <div class="lp-illus lp-illus-chart">
        <div class="lp-bars"><i style="height:38%"></i><i style="height:55%"></i><i style="height:46%"></i><i style="height:64%"></i><i style="height:58%"></i><i style="height:78%"></i><i style="height:70%"></i><i class="on" style="height:96%"></i></div>
      </div>
      <div class="lp-tile-row">
        <span class="lp-ic lp-ic-orange lp-ic-lg"><?= icon('chart', 20) ?></span>
        <div><h3>Vos dettes en temps réel</h3><p>Total dû, dépôts et remboursements du jour, relevé de chaque agent avec le solde après chaque opération.</p></div>
      </div>
    </article>
    <article class="lp-tile">
      <div class="lp-illus">
        <div class="lp-shield">
          <span class="lp-ic lp-ic-green lp-ic-xl"><?= icon('lock', 26) ?></span>
          <div class="lp-mini-ok"><?= icon('check', 14) ?> Solde vérifié</div>
          <div class="lp-mini-ko"><?= icon('x', 14) ?> Faux SMS bloqué</div>
        </div>
      </div>
      <h3>Protection anti-faux SMS</h3>
      <p>Le solde annoncé est contrôlé et un même SMS n'est jamais compté deux fois.</p>
    </article>
  </div>
</section>

<!-- ============================================================ Confiance -->
<section class="lp-panel lp-white lp-trust">
  <span class="lp-ic lp-ic-green lp-ic-xl"><?= icon('lock', 26) ?></span>
  <h2>Votre argent ne passe <span class="lp-accent">jamais</span> par nous.</h2>
  <p><?= h(app_name()) ?> est un outil de suivi : vous faites vos transferts vous-même, comme d'habitude. Aucun code secret Orange Money n'est demandé ni enregistré.</p>
</section>

<!-- ============================================================ Tarifs -->
<section class="lp-panel" id="tarifs">
  <div class="lp-head">
    <span class="lp-pill"><?= icon('wallet', 14) ?> Tarifs</span>
    <h2>Des prix <span class="lp-accent">simples</span></h2>
    <p><?= $trial ?> jours d'essai avec toutes les fonctions, puis un abonnement mensuel payé par Orange Money.</p>
  </div>
  <div class="lp-prices">
    <?php foreach ($plans as $key => $p): $best = $key === 'pro'; ?>
      <article class="lp-price<?= $best ? ' lp-best' : '' ?>">
        <?php if ($best): ?><span class="lp-tag">Le plus choisi</span><?php endif; ?>
        <h3><?= h($p['name']) ?></h3>
        <div class="lp-amount"><?= h(number_format((float)$p['price'], 0, ',', ' ')) ?><small> F / mois</small></div>
        <ul>
          <li><?= icon('check', 16) ?> <?= $p['agents'] > 0 ? 'Jusqu\'à ' . (int)$p['agents'] . ' agents' : 'Agents illimités' ?></li>
          <li><?= icon('check', 16) ?> SMS lus automatiquement</li>
          <li><?= icon('check', 16) ?> Notifications et relevés</li>
          <li><?= icon('check', 16) ?> Applications installables</li>
        </ul>
        <a class="lp-btn <?= $best ? 'lp-btn-main' : 'lp-btn-dark' ?> lp-btn-block" href="<?= h($cta) ?>"><?= $open ? 'Essayer gratuitement' : 'Se connecter' ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================================================ FAQ -->
<section class="lp-panel lp-white" id="faq">
  <div class="lp-head"><h2>Questions <span class="lp-accent">fréquentes</span></h2></div>
  <div class="lp-faq">
    <details><summary>Dois-je donner mon code secret Orange Money ?<?= $chev ?></summary><p>Non, jamais. Vous faites vos transferts vous-même ; <?= h(app_name()) ?> lit seulement les SMS de confirmation pour tenir les comptes.</p></details>
    <details><summary>De quoi ai-je besoin pour commencer ?<?= $chev ?></summary><p>Un téléphone Android avec la SIM Orange Money de votre activité et Internet. Vous installez notre application en 2 minutes, puis vous ajoutez vos agents.</p></details>
    <details><summary>Mes agents doivent-ils installer quelque chose ?<?= $chev ?></summary><p>Non. Ils ouvrent leur espace avec leur numéro et un code PIN que vous leur donnez, et peuvent l'ajouter à l'écran d'accueil comme une application.</p></details>
    <details><summary>Et si un agent rembourse en cash ou en plusieurs fois ?<?= $chev ?></summary><p>Les remboursements partiels sont détectés automatiquement ; le cash s'enregistre en un clic sur la fiche de l'agent.</p></details>
    <details><summary>Que se passe-t-il si j'arrête de payer ?<?= $chev ?></summary><p>Votre espace passe en lecture seule : tout votre historique reste disponible et se débloque dès le paiement suivant.</p></details>
    <details><summary>Comment payer l'abonnement ?<?= $chev ?></summary><p>Par Orange Money depuis votre numéro : l'abonnement est prolongé automatiquement dès réception.</p></details>
  </div>
</section>

<!-- ============================================================ Appel final -->
<section class="lp-panel lp-dark">
  <span class="lp-logo-big"><?= icon('logo', 40) ?></span>
  <h2>Prêt à ne plus jamais <span class="lp-accent">oublier une dette</span> ?</h2>
  <p>Créez votre compte en 1 minute et testez gratuitement pendant <?= $trial ?> jours.</p>
  <div class="lp-final-cta">
    <a class="lp-btn lp-btn-main" href="<?= h($cta) ?>"><?= $open ? 'Commencer gratuitement' : 'Se connecter' ?> <?= $arrow ?></a>
    <?php if ($wa !== ''): ?><a class="lp-btn lp-btn-glass" href="<?= h($wa) ?>" target="_blank" rel="noopener">Parler sur WhatsApp</a><?php endif; ?>
  </div>
</section>
</main>

<footer class="lp-foot">
  <a class="lp-brand" href="<?= h(url('')) ?>"><span class="mark"><?= icon('logo', 16) ?></span><span><?= h(app_name()) ?></span></a>
  <nav>
    <a href="<?= h(url('admin/login')) ?>">Espace distributeur</a>
    <a href="<?= h(url('agent/login')) ?>">Espace agent</a>
    <?php if ($wa !== ''): ?><a href="<?= h($wa) ?>" target="_blank" rel="noopener">Support WhatsApp</a><?php endif; ?>
  </nav>
  <p><?= h(app_name()) ?> est un outil de suivi indépendant, compatible avec Orange Money. Il n'est pas affilié à Orange.</p>
</footer>
</body>
</html>
