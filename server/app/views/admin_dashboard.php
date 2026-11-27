<?php /** @var array $stats @var array $requests @var array $recent @var ?array $device @var array $top */
$months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$today = new DateTime('now', local_tz());
$online = $device && device_online($device);
?>
<div class="page-head"><h1>Tableau de bord</h1></div>

<div class="chip-date"><?= icon('clock', 20) ?><span>Aujourd'hui</span><span><?= (int)$today->format('j') . ' ' . $months[(int)$today->format('n') - 1] . ' ' . $today->format('Y') ?></span></div>

<div class="hero">
  <div class="hero-label">Total dû par les agents</div>
  <div class="hero-value"><?= h(fmt_amount($stats['total_due'])) ?></div>
  <div class="hero-sub"><?= (int)$stats['agents_due'] ?> agent(s) avec une dette en cours</div>
  <div class="hero-actions">
    <a class="btn solid" href="<?= h(url('admin/requests')) ?>"><?= icon('inbox') ?> Demandes<?= $stats['pending_requests'] ? ' (' . (int)$stats['pending_requests'] . ')' : '' ?></a>
    <a class="btn" href="<?= h(url('admin/agents')) ?>"><?= icon('users') ?> Agents</a>
  </div>
</div>

<div class="stats">
  <a class="stat" href="<?= h(url('admin/operations?f=depot&jour=' . $today->format('Y-m-d'))) ?>">
    <div class="stat-top"><span class="stat-l">Dépôts envoyés</span><span class="chipic out"><?= icon('up') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($stats['depots_today'])) ?></div>
    <div class="stat-s">aujourd'hui</div>
  </a>
  <a class="stat" href="<?= h(url('admin/operations?f=remb&jour=' . $today->format('Y-m-d'))) ?>">
    <div class="stat-top"><span class="stat-l">Remboursés par OM</span><span class="chipic in"><?= icon('down') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($stats['remb_om_today'])) ?></div>
    <div class="stat-s">aujourd'hui</div>
  </a>
  <a class="stat" href="<?= h(url('admin/operations?f=remb&jour=' . $today->format('Y-m-d'))) ?>">
    <div class="stat-top"><span class="stat-l">Remboursés en cash</span><span class="chipic blue"><?= icon('cash') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($stats['remb_cash_today'])) ?></div>
    <div class="stat-s">aujourd'hui</div>
  </a>
  <a class="stat" href="<?= h(url('admin/device')) ?>">
    <div class="stat-top"><span class="stat-l">Solde Orange Money</span><span class="chipic <?= $online ? 'in' : 'warn' ?>"><?= icon('phone') ?></span></div>
    <div class="stat-v"><?= $device && $device['last_balance'] !== null ? h(fmt_amount($device['last_balance'])) : '—' ?></div>
    <div class="stat-s"><?= $device ? ($online ? 'téléphone en ligne' : 'hors ligne · ' . h(ago($device['last_seen_at']))) : 'téléphone non associé' ?></div>
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
    <div class="card empty">Aucune demande en attente.</div>
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
        <span class="avatar-sm"><?= h(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
        <span class="agent-main"><b><?= h($a['name']) ?></b><span class="muted small"><?= h(ago($a['last_op_at'])) ?></span></span>
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
