<?php /** @var array $rows @var array $stats @var string $search */ ?>
<div class="page-head"><div><div class="sub">Super-admin</div><h1>Distributeurs</h1></div></div>

<div class="hero">
  <div class="hero-label">Revenu mensuel des abonnements actifs</div>
  <div class="hero-value"><?= h(fmt_amount($stats['mrr'])) ?></div>
  <div class="hero-sub">Encaissé ce mois : <?= h(fmt_amount($stats['month_revenue'])) ?></div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-top"><span class="stat-l">Distributeurs</span><span class="chipic blue"><?= icon('users') ?></span></div><div class="stat-v"><?= (int)$stats['total'] ?></div><div class="stat-s"><?= (int)$stats['offert'] ?> offert(s)</div></div>
  <div class="stat"><div class="stat-top"><span class="stat-l">Payants</span><span class="chipic in"><?= icon('check') ?></span></div><div class="stat-v"><?= (int)$stats['paying'] ?></div><div class="stat-s">abonnement en cours</div></div>
  <div class="stat"><div class="stat-top"><span class="stat-l">En essai</span><span class="chipic out"><?= icon('clock') ?></span></div><div class="stat-v"><?= (int)$stats['trial'] ?></div><div class="stat-s">à convertir</div></div>
  <a class="stat" href="<?= h(url('superadmin/payments')) ?>"><div class="stat-top"><span class="stat-l">Expirés</span><span class="chipic warn"><?= icon('alert') ?></span></div><div class="stat-v"><?= (int)$stats['expired'] ?></div><div class="stat-s"><?= (int)$stats['unassigned'] ?> paiement(s) à traiter</div></a>
</div>

<form class="search" method="get">
  <?= icon('search') ?>
  <input type="search" name="q" value="<?= h($search) ?>" placeholder="Nom, numéro, email…">
</form>

<?php if (!$rows): ?><div class="card empty">Aucun distributeur. Partagez le lien d'inscription : <b class="mono"><?= h(abs_url('inscription')) ?></b></div><?php endif; ?>
<div class="list">
<?php foreach ($rows as $d):
    $active = dist_active($d);
    $left = dist_days_left($d); ?>
  <a class="agent-row" href="<?= h(url('superadmin/d/' . $d['id'])) ?>">
    <span class="avatar-sm"><?= h(mb_strtoupper(mb_substr($d['name'], 0, 1))) ?></span>
    <span class="agent-main">
      <b><?= h($d['name']) ?></b>
      <span class="muted small"><?= h($d['email'] ?? '—') ?><?= $d['phone'] ? ' · ' . h(fmt_phone($d['phone'])) : '' ?> · <?= (int)$d['agents'] ?> agent(s) · activité <?= h(ago($d['last_op_at'])) ?></span>
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
