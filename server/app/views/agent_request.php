<?php /** @var array $agent @var float $due @var array $requests */
$min = (float)setting('min_request', '1000');
?>
<div class="page-head"><div><a class="back" href="<?= h(url('agent')) ?>">← Accueil</a><h1>Demander un dépôt</h1></div></div>

<form method="post" action="<?= h(url('agent/request')) ?>" class="card form" id="demande">
  <?= csrf_field() ?>
  <label class="center">Montant souhaité</label>
  <input type="text" name="amount" inputmode="numeric" required placeholder="0" class="amount-input" autofocus>
  <div class="chips">
    <?php foreach ([100000, 250000, 500000, 1000000, 2000000] as $v): ?>
      <button type="button" class="chip" data-fill="<?= $v ?>"><?= h(number_format($v, 0, ',', ' ')) ?></button>
    <?php endforeach; ?>
  </div>
  <label>Message pour le distributeur (facultatif)</label>
  <input type="text" name="note" maxlength="200" placeholder="Ex. urgent, besoin avant midi">
  <button class="btn primary big"><?= icon('send', 20) ?> Envoyer la demande</button>
  <p class="small muted center">Minimum <?= h(fmt_amount($min)) ?>. Vous recevez une notification dès qu'elle est validée.</p>
</form>

<div class="card">
  <div class="row-link"><span class="muted">Dette actuelle</span><b class="<?= $due > 0 ? 'neg' : 'pos' ?>"><?= h(fmt_amount(abs($due))) ?><?= $due < 0 ? ' d\'avance' : '' ?></b></div>
  <div class="row-link"><span class="muted">Envoyé sur</span><b class="mono"><?= h(fmt_phone($agent['login_phone'])) ?></b></div>
</div>

<?php if ($requests): ?>
<section class="card">
  <div class="sec-head"><h2>Mes demandes</h2></div>
  <?php foreach ($requests as $r): ?>
    <div class="row-link">
      <span><b><?= h(fmt_amount($r['amount'])) ?></b> <span class="muted small">· <?= h(fmt_dt($r['created_at'])) ?></span>
        <?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?><br><span class="small">Motif : <?= h($r['reject_reason']) ?></span><?php endif; ?>
      </span>
      <span class="right">
        <?= badge($r['status'], req_status_label($r['status'])) ?>
        <?php if ($r['status'] === 'pending'): ?><br><?= action_button('agent/request/' . $r['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette demande ?') ?><?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
