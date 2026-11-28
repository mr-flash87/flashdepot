<?php /** @var array $agent @var float $due @var array $ops */
$f = (string)($_GET['f'] ?? 'all');
$list = array_values(array_filter($ops, fn($o) => $f === 'all' || ($f === 'dep' ? op_sign($o['kind']) > 0 : op_sign($o['kind']) < 0)));
$monthStart = (new DateTime('first day of this month 00:00', local_tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$dep = array_sum(array_map(fn($o) => op_sign($o['kind']) > 0 && $o['occurred_at'] >= $monthStart ? (float)$o['amount'] : 0, $ops));
$remb = array_sum(array_map(fn($o) => op_sign($o['kind']) < 0 && $o['occurred_at'] >= $monthStart ? (float)$o['amount'] : 0, $ops));
?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Mon relevé</h1></div><b class="<?= $due > 0 ? 'neg' : 'pos' ?>"><?= due_text($due) ?></b></div>

<div class="stats">
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Reçu ce mois</span><span class="chipic out"><?= icon('down') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($dep)) ?></div><div class="stat-s">dépôts du distributeur</div>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Remboursé ce mois</span><span class="chipic in"><?= icon('up') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($remb)) ?></div><div class="stat-s">Mobile Money et cash</div>
  </div>
</div>

<div class="tabs">
  <?php foreach (['all' => 'Tout', 'dep' => 'Dépôts reçus', 'remb' => 'Remboursements'] as $k => $label): ?>
    <a href="<?= h(url('agent/releve' . ($k === 'all' ? '' : '?f=' . $k))) ?>"<?= $f === $k ? ' class="on"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<section class="card">
  <?php if (!$list): ?><div class="empty">Aucune opération.</div><?php endif; ?>
  <?php foreach (ops_by_day($list) as $day => $items): ?>
    <div class="ag-day"><?= h($day) ?></div>
    <?php foreach ($items as $o): ?><?= op_row($o, false, true, true) ?><?php endforeach; ?>
  <?php endforeach; ?>
</section>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
