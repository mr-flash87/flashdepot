<?php /** @var array $agent @var float $due @var array $requests @var array $ops */
$first = explode(' ', trim($agent['name']))[0];
$open = array_values(array_filter($requests, fn($r) => in_array($r['status'], ['pending', 'approved'], true)));
$lastSent = array_values(array_filter($requests, fn($r) => $r['status'] === 'sent' && $r['sent_at'] > gmdate('Y-m-d H:i:s', time() - 86400)));
$today = today_start_utc();
$depToday = array_sum(array_map(fn($o) => $o['kind'] === 'depot' && $o['occurred_at'] >= $today ? (float)$o['amount'] : 0, $ops));
$rembToday = array_sum(array_map(fn($o) => op_sign($o['kind']) < 0 && $o['occurred_at'] >= $today ? (float)$o['amount'] : 0, $ops));
?>
<div class="ag-hello">
  <span class="avatar-sm"><?= h(mb_strtoupper(mb_substr($first, 0, 1))) ?></span>
  <div><div class="sub">Bonjour <?= h($first) ?> 👋</div><div class="small muted">Distributeur : <b><?= h(distributor()['name']) ?></b></div></div>
</div>

<div class="ag-card <?= $due > 0 ? '' : 'ok' ?>">
  <div class="ag-card-top">
    <span><?= $due >= 0 ? 'Vous devez' : 'Votre avance' ?></span>
    <span class="ag-live"><i></i> à jour</span>
  </div>
  <div class="ag-amount"><?= h(fmt_amount(abs($due))) ?></div>
  <div class="ag-today">
    <span><?= icon('down', 14) ?> Reçu aujourd'hui <b><?= h(fmt_amount($depToday)) ?></b></span>
    <span><?= icon('up', 14) ?> Remboursé <b><?= h(fmt_amount($rembToday)) ?></b></span>
  </div>
  <div class="ag-actions">
    <a class="btn solid" href="<?= h(url('agent/demande')) ?>"><?= icon('plus') ?> Demander</a>
    <a class="btn" href="<?= h(url('agent/rembourser')) ?>"><?= icon('send') ?> Rembourser</a>
  </div>
</div>

<div class="ag-grid">
  <a href="<?= h(url('agent/demande')) ?>"><span class="chipic out"><?= icon('inbox', 20) ?></span>Demander</a>
  <a href="<?= h(url('agent/rembourser')) ?>"><span class="chipic in"><?= icon('send', 20) ?></span>Rembourser</a>
  <a href="<?= h(url('agent/releve')) ?>"><span class="chipic blue"><?= icon('chart', 20) ?></span>Relevé</a>
  <a href="<?= h(url('agent/compte')) ?>"><span class="chipic warn"><?= icon('lock', 20) ?></span>Code PIN</a>
</div>

<?php foreach (array_merge($open, array_slice($lastSent, 0, 1)) as $r): ?>
  <section class="card ag-req">
    <div class="sec-head">
      <h2><?= $r['status'] === 'sent' ? 'Dernier dépôt' : 'Demande en cours' ?></h2>
      <b><?= h(fmt_amount($r['amount'])) ?></b>
    </div>
    <?= request_tracker($r) ?>
    <div class="row" style="justify-content:space-between;margin-top:10px">
      <span class="small muted"><?= h(fmt_dt($r['created_at'])) ?><?= !empty($r['operator']) ? ' · ' . h(mm_name($r['operator'])) : '' ?></span>
      <?php if ($r['status'] === 'pending'): ?><?= action_button('agent/request/' . $r['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette demande ?') ?><?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

<section class="card">
  <div class="sec-head"><h2>Activité récente</h2><a href="<?= h(url('agent/releve')) ?>">Tout voir</a></div>
  <?php if (!$ops): ?><div class="empty">Aucune opération pour l'instant. Vos dépôts et remboursements apparaîtront ici.</div><?php endif; ?>
  <?php foreach (ops_by_day(array_slice($ops, 0, 8)) as $day => $list): ?>
    <div class="ag-day"><?= h($day) ?></div>
    <?php foreach ($list as $o): ?><?= op_row($o, false, true, true) ?><?php endforeach; ?>
  <?php endforeach; ?>
</section>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
