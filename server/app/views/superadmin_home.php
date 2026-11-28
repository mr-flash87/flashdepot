<?php /** @var array $rows @var array $stats @var array $charts @var array $soon @var array $recent_payments @var string $search @var string $filter */
$maxRev = max(1, ...array_map(fn($m) => $m['revenue'], $charts['months']));
$maxSign = max(1, ...array_map(fn($m) => $m['signups'], $charts['months']));
$tabs = ['all' => 'Tous', 'paying' => 'Payants', 'trial' => 'En essai', 'soon' => 'À renouveler', 'expired' => 'Expirés', 'suspended' => 'Suspendus', 'offert' => 'Offerts'];
?>
<div class="page-head">
  <div><div class="sub">Super-admin</div><h1>Tableau de bord</h1></div>
  <div class="row">
    <a class="btn" href="<?= h(url('superadmin/export/distributeurs')) ?>"><?= icon('download') ?> Export</a>
    <a class="btn primary" href="<?= h(url('superadmin/new')) ?>"><?= icon('plus') ?> Distributeur</a>
  </div>
</div>

<div class="hero">
  <div class="hero-label">Revenu mensuel des abonnements actifs</div>
  <div class="hero-value"><?= h(fmt_amount($stats['mrr'])) ?></div>
  <div class="hero-sub">Encaissé ce mois : <?= h(fmt_amount($stats['month_revenue'])) ?> · <?= (int)$charts['signups_30'] ?> inscription(s) sur 30 jours</div>
</div>

<div class="stats">
  <a class="stat" href="<?= h(url('superadmin')) ?>"><div class="stat-top"><span class="stat-l">Distributeurs</span><span class="chipic blue"><?= icon('users') ?></span></div><div class="stat-v"><?= (int)$stats['total'] ?></div><div class="stat-s"><?= (int)$charts['agents_total'] ?> agents actifs</div></a>
  <a class="stat" href="<?= h(url('superadmin?f=paying')) ?>"><div class="stat-top"><span class="stat-l">Payants</span><span class="chipic in"><?= icon('check') ?></span></div><div class="stat-v"><?= (int)$stats['paying'] ?></div><div class="stat-s"><?= (int)$stats['offert'] ?> offert(s)</div></a>
  <a class="stat" href="<?= h(url('superadmin?f=trial')) ?>"><div class="stat-top"><span class="stat-l">En essai</span><span class="chipic out"><?= icon('clock') ?></span></div><div class="stat-v"><?= (int)$stats['trial'] ?></div><div class="stat-s">à convertir</div></a>
  <a class="stat" href="<?= h(url('superadmin?f=expired')) ?>"><div class="stat-top"><span class="stat-l">Expirés</span><span class="chipic warn"><?= icon('alert') ?></span></div><div class="stat-v"><?= (int)$stats['expired'] ?></div><div class="stat-s"><?= (int)$stats['unassigned'] ?> paiement(s) à traiter</div></a>
</div>

<div class="cols">
  <section class="card">
    <div class="sec-head"><h2>Revenus</h2><span class="muted small">6 derniers mois</span></div>
    <div class="sa-chart">
      <?php foreach ($charts['months'] as $i => $m): ?>
        <div class="sa-col<?= $i === 5 ? ' on' : '' ?>">
          <span class="sa-val"><?= $m['revenue'] > 0 ? h(number_format($m['revenue'] / 1000, 0, ',', ' ')) . 'k' : '' ?></span>
          <i style="height:<?= max(3, (int)round($m['revenue'] / $maxRev * 100)) ?>%"></i>
          <span><?= h($m['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="sec-head"><h2>Inscriptions</h2><span class="muted small"><?= (int)$charts['ops_30'] ?> opérations sur 30 j</span></div>
    <div class="sa-chart sa-blue">
      <?php foreach ($charts['months'] as $i => $m): ?>
        <div class="sa-col<?= $i === 5 ? ' on' : '' ?>">
          <span class="sa-val"><?= $m['signups'] ?: '' ?></span>
          <i style="height:<?= max(3, (int)round($m['signups'] / $maxSign * 100)) ?>%"></i>
          <span><?= h($m['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php if ($soon || $recent_payments): ?>
<div class="cols" style="margin-top:14px">
  <section class="card">
    <div class="sec-head"><h2>À renouveler (7 jours)</h2><a href="<?= h(url('superadmin?f=soon')) ?>">Voir</a></div>
    <?php if (!$soon): ?><div class="empty">Personne pour l'instant.</div><?php endif; ?>
    <?php foreach (array_slice($soon, 0, 6) as $d): ?>
      <a class="row-link" href="<?= h(url('superadmin/d/' . $d['id'])) ?>"><span><b><?= h($d['name']) ?></b> <span class="small muted">· <?= h(plan_label($d['plan'])) ?></span></span><span class="badge b-review"><?= max(0, (int)dist_days_left($d)) ?> j</span></a>
    <?php endforeach; ?>
  </section>
  <section class="card">
    <div class="sec-head"><h2>Derniers paiements</h2><a href="<?= h(url('superadmin/payments')) ?>">Tout voir</a></div>
    <?php if (!$recent_payments): ?><div class="empty">Aucun paiement.</div><?php endif; ?>
    <?php foreach ($recent_payments as $pm): ?>
      <div class="row-link"><span><b><?= h($pm['dist_name'] ?? '—') ?></b> <span class="small muted">· <?= h(fmt_dt($pm['created_at'], 'd/m H:i')) ?></span></span><b class="pos">+<?= h(fmt_amount($pm['amount'])) ?></b></div>
    <?php endforeach; ?>
  </section>
</div>
<?php endif; ?>

<div class="sec-head" style="margin-top:22px"><h2>Distributeurs</h2><span class="muted small"><?= count($rows) ?></span></div>
<form class="search" method="get">
  <?= icon('search') ?>
  <input type="hidden" name="f" value="<?= h($filter) ?>">
  <input type="search" name="q" value="<?= h($search) ?>" placeholder="Nom, numéro, email…">
</form>
<div class="tabs scroll">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="<?= h(url('superadmin' . ($k === 'all' ? '' : '?f=' . $k))) ?>"<?= $filter === $k ? ' class="on"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?><div class="card empty">Aucun distributeur ici. Lien d'inscription : <b class="mono"><?= h(abs_url('inscription')) ?></b></div><?php endif; ?>
<div class="list">
<?php foreach ($rows as $d):
    $active = dist_active($d);
    $left = dist_days_left($d); ?>
  <a class="agent-row" href="<?= h(url('superadmin/d/' . $d['id'])) ?>">
    <span class="avatar-sm"><?= h(mb_strtoupper(mb_substr($d['name'], 0, 1))) ?></span>
    <span class="agent-main">
      <b><?= h($d['name']) ?></b>
      <span class="muted small"><?= h($d['email'] ?? '—') ?><?= $d['phone'] ? ' · ' . h(fmt_phone($d['phone'])) : '' ?></span>
      <span class="muted small"><?= (int)$d['agents'] ?> agent(s) · connexion <?= h(ago($d['last_login_at'])) ?> · activité <?= h(ago($d['last_op_at'])) ?></span>
    </span>
    <span class="right">
      <?php if ($d['status'] === 'suspended'): ?><?= badge('rejected', 'Suspendu') ?>
      <?php elseif (!$active): ?><?= badge('rejected', 'Expiré') ?>
      <?php else: ?><?= badge($d['plan'] === 'essai' ? 'pending' : 'ok', plan_label($d['plan'])) ?><?php endif; ?>
      <?php if ($left !== null && $active): ?><br><span class="small muted"><?= max(0, $left) ?> j</span><?php endif; ?>
    </span>
  </a>
<?php endforeach; ?>
</div>
<p class="small muted center" style="margin-top:14px">Lien d'inscription à partager : <b class="mono"><?= h(abs_url('inscription')) ?></b></p>
