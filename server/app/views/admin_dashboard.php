<?php /** @var array $stats @var array $requests @var array $recent @var ?array $device @var array $top */ ?>
<div class="page-head"><h1>Tableau de bord</h1><span class="muted small"><?= h(fmt_dt(now(), 'd/m/Y')) ?></span></div>

<div class="hero">
  <div class="hero-label">Total dû par les agents</div>
  <div class="hero-value"><?= h(fmt_amount($stats['total_due'])) ?></div>
  <div class="hero-sub"><?= (int)$stats['agents_due'] ?> agent(s) avec une dette</div>
</div>

<div class="stats">
  <div class="stat"><span class="stat-ic out"><?= icon('up') ?></span><div><div class="stat-v"><?= h(fmt_amount($stats['depots_today'])) ?></div><div class="stat-l">Dépôts aujourd'hui</div></div></div>
  <div class="stat"><span class="stat-ic in"><?= icon('down') ?></span><div><div class="stat-v"><?= h(fmt_amount($stats['remb_om_today'])) ?></div><div class="stat-l">Remboursés par OM</div></div></div>
  <div class="stat"><span class="stat-ic in"><?= icon('cash') ?></span><div><div class="stat-v"><?= h(fmt_amount($stats['remb_cash_today'])) ?></div><div class="stat-l">Remboursés en cash</div></div></div>
  <a class="stat" href="<?= h(url('admin/device')) ?>">
    <span class="stat-ic <?= $device && device_online($device) ? 'in' : 'warn' ?>"><?= icon('phone') ?></span>
    <div><div class="stat-v"><?= $device && $device['last_balance'] !== null ? h(fmt_amount($device['last_balance'])) : '—' ?></div>
    <div class="stat-l"><?= $device ? (device_online($device) ? 'Solde OM · téléphone en ligne' : 'Téléphone hors ligne (' . h(ago($device['last_seen_at'])) . ')') : 'Téléphone non associé' ?></div></div>
  </a>
</div>

<?php if ($stats['unassigned'] || $stats['review']): ?>
  <div class="alert warn">
    <?= icon('alert') ?>
    <?php if ($stats['unassigned']): ?><a href="<?= h(url('admin/operations?f=unassigned')) ?>"><b><?= (int)$stats['unassigned'] ?></b> opération(s) d'un numéro inconnu à attribuer</a><?php endif; ?>
    <?php if ($stats['unassigned'] && $stats['review']): ?> · <?php endif; ?>
    <?php if ($stats['review']): ?><a href="<?= h(url('admin/operations?f=review')) ?>"><b><?= (int)$stats['review'] ?></b> SMS à vérifier</a><?php endif; ?>
  </div>
<?php endif; ?>

<section>
  <div class="sec-head"><h2>Demandes en cours</h2><a href="<?= h(url('admin/requests')) ?>">Tout voir</a></div>
  <?php if (!$requests): ?>
    <div class="empty">Aucune demande en attente.</div>
  <?php else: foreach ($requests as $r): ?>
    <?= request_card($r) ?>
  <?php endforeach; endif; ?>
</section>

<div class="cols">
  <section class="card">
    <div class="sec-head"><h2>Plus grosses dettes</h2><a href="<?= h(url('admin/agents')) ?>">Agents</a></div>
    <?php if (!$top): ?><div class="empty">Aucune dette en cours.</div><?php endif; ?>
    <?php foreach ($top as $a): ?>
      <a class="row-link" href="<?= h(url('admin/agents/' . $a['id'])) ?>">
        <span><?= h($a['name']) ?><span class="muted small"> · <?= h(ago($a['last_op_at'])) ?></span></span>
        <b class="neg"><?= h(fmt_amount($a['due'])) ?></b>
      </a>
    <?php endforeach; ?>
  </section>
  <section class="card">
    <div class="sec-head"><h2>Dernières opérations</h2><a href="<?= h(url('admin/operations')) ?>">Journal</a></div>
    <?php if (!$recent): ?><div class="empty">Les dépôts et remboursements détectés par SMS apparaîtront ici.</div><?php endif; ?>
    <?php foreach ($recent as $o): ?><?= op_row($o) ?><?php endforeach; ?>
  </section>
</div>
<div data-poll="<?= h(url('admin/poll')) ?>"></div>
