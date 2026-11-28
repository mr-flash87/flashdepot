<?php /** @var array $agent @var float $due */
$nums = [];
foreach (dist_operators() as $op) {
    $n = (string)dsetting('receive_' . $op);
    if ($n === '' && $op === 'orange' && distributor()['phone'] && phone_operator(distributor()['phone']) !== 'moov') {
        $n = (string)distributor()['phone'];
    }
    if ($n !== '') {
        $nums[$op] = $n;
    }
}
$mine = agent_phones((int)$agent['id']);
?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Rembourser</h1></div></div>

<div class="ag-card <?= $due > 0 ? '' : 'ok' ?>">
  <div class="ag-card-top"><span><?= $due > 0 ? 'Reste à payer' : 'Rien à rembourser' ?></span></div>
  <div class="ag-amount"><?= h(fmt_amount(max(0, $due))) ?></div>
  <div class="ag-today"><span>Un remboursement partiel est accepté</span></div>
</div>

<?php if (!$nums): ?>
  <div class="card empty">Votre distributeur n'a pas encore indiqué son numéro de remboursement. Contactez-le.</div>
<?php endif; ?>
<?php foreach ($nums as $op => $n): ?>
  <section class="card ag-pay">
    <div class="ag-pay-op"><span class="chipic <?= $op === 'moov' ? 'blue' : 'out' ?>"><?= icon('phone', 18) ?></span><b><?= h(mm_name($op)) ?></b></div>
    <div class="ag-pay-num mono"><?= h(fmt_phone($n)) ?></div>
    <div class="row">
      <button type="button" class="btn primary" data-copy="<?= h(phone_key($n)) ?>"><?= icon('check', 16) ?> Copier le numéro</button>
      <a class="btn" href="tel:<?= h(phone_key($n)) ?>">Appeler</a>
    </div>
  </section>
<?php endforeach; ?>

<section class="card">
  <h2>Comment ça marche</h2>
  <ol class="steps">
    <li>Envoyez l'argent depuis votre numéro <b class="mono"><?= h(implode(' ou ', array_map('fmt_phone', $mine))) ?></b> au numéro ci-dessus, avec votre menu Orange Money ou Moov Money habituel.</li>
    <li>Le distributeur reçoit le SMS de confirmation : <b>votre dette baisse toute seule</b>, en quelques minutes.</li>
    <li>Vous recevez une notification et l'opération apparaît dans votre relevé.</li>
  </ol>
  <p class="small muted">Vous payez en espèces ? Remettez l'argent au distributeur : il l'enregistre de son côté.</p>
</section>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
