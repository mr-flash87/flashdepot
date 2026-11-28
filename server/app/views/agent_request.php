<?php /** @var array $agent @var float $due @var array $requests */
$min = (float)dsetting('min_request', '1000');
$ops = dist_operators();
$open = array_values(array_filter($requests, fn($r) => in_array($r['status'], ['pending', 'approved'], true)));
?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Demander un dépôt</h1></div></div>

<form method="post" action="<?= h(url('agent/request')) ?>" class="card form ag-form" id="demande">
  <?= csrf_field() ?>
  <?php if (count($ops) > 1): ?>
    <div class="seg">
      <?php foreach ($ops as $i => $op): ?>
        <label><input type="radio" name="operator" value="<?= h($op) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= h(mm_name($op)) ?></span></label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <label class="center">Montant souhaité</label>
  <div class="ag-input"><input type="text" name="amount" inputmode="numeric" required placeholder="0" class="amount-input" autofocus><span>F</span></div>
  <div class="chips">
    <?php foreach ([100000, 250000, 500000, 1000000, 2000000] as $v): ?>
      <button type="button" class="chip" data-fill="<?= $v ?>"><?= h(number_format($v, 0, ',', ' ')) ?></button>
    <?php endforeach; ?>
  </div>
  <label>Message pour le distributeur (facultatif)</label>
  <input type="text" name="note" maxlength="200" placeholder="Ex. urgent, besoin avant midi">
  <button class="btn primary big"><?= icon('send', 20) ?> Envoyer la demande</button>
  <p class="small muted center">Minimum <?= h(fmt_amount($min)) ?> · vous êtes prévenu dès qu'elle est validée.</p>
</form>

<div class="card ag-info">
  <div class="row-link"><span class="muted">Dette actuelle</span><b class="<?= $due > 0 ? 'neg' : 'pos' ?>"><?= h(fmt_amount(abs($due))) ?><?= $due < 0 ? ' d\'avance' : '' ?></b></div>
  <?php foreach (agent_phones((int)$agent['id']) as $k): ?>
    <div class="row-link"><span class="muted">Dépôt <?= h(mm_name(phone_operator($k) ?? 'orange')) ?> sur</span><b class="mono"><?= h(fmt_phone($k)) ?></b></div>
  <?php endforeach; ?>
</div>

<?php if ($requests): ?>
<section class="card">
  <div class="sec-head"><h2>Mes demandes</h2></div>
  <?php foreach ($requests as $r): ?>
    <div class="ag-reqline">
      <div class="row" style="justify-content:space-between">
        <span><b><?= h(fmt_amount($r['amount'])) ?></b> <span class="muted small">· <?= h(fmt_dt($r['created_at'])) ?></span></span>
        <?php if ($r['status'] === 'pending'): ?><?= action_button('agent/request/' . $r['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette demande ?') ?><?php endif; ?>
      </div>
      <?= request_tracker($r) ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
