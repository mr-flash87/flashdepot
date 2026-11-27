<?php /** @var array $agent @var float $due @var array $ops */
$dep = array_sum(array_map(fn($o) => op_sign($o['kind']) > 0 ? (float)$o['amount'] : 0, $ops));
$remb = array_sum(array_map(fn($o) => op_sign($o['kind']) < 0 ? (float)$o['amount'] : 0, $ops));
?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Mon relevé</h1></div></div>

<div class="stats">
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Total reçu</span><span class="chipic out"><?= icon('down') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($dep)) ?></div>
    <div class="stat-s">dépôts du distributeur</div>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Total remboursé</span><span class="chipic in"><?= icon('up') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($remb)) ?></div>
    <div class="stat-s">Orange Money et cash</div>
  </div>
</div>

<section class="card">
  <div class="sec-head"><h2>Opérations</h2><b class="<?= $due > 0 ? 'neg' : 'pos' ?>"><?= due_text($due) ?></b></div>
  <?php if (!$ops): ?><div class="empty">Aucune opération pour l'instant.</div><?php endif; ?>
  <?php foreach ($ops as $o): ?><?= op_row($o, false, true, true) ?><?php endforeach; ?>
</section>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
