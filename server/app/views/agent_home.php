<?php /** @var array $agent @var float $due @var array $requests @var array $ops */
$min = (float)setting('min_request', '1000');
?>
<div class="hello">Bonjour <b><?= h($agent['name']) ?></b></div>

<div class="hero <?= $due > 0 ? '' : 'hero-ok' ?>">
  <div class="hero-label"><?= $due >= 0 ? 'Vous devez au distributeur' : 'Votre avance' ?></div>
  <div class="hero-value"><?= h(fmt_amount(abs($due))) ?></div>
  <div class="hero-sub">Mis à jour automatiquement à chaque dépôt et remboursement</div>
</div>

<form method="post" action="<?= h(url('agent/request')) ?>" class="card form" id="demande">
  <?= csrf_field() ?>
  <h2>Demander un dépôt</h2>
  <label>Montant</label>
  <input type="text" name="amount" inputmode="numeric" required placeholder="Ex. 500 000" class="amount-input">
  <div class="chips">
    <?php foreach ([100000, 250000, 500000, 1000000] as $v): ?>
      <button type="button" class="chip" data-fill="<?= $v ?>"><?= h(fmt_amount($v)) ?></button>
    <?php endforeach; ?>
  </div>
  <label>Message (facultatif)</label>
  <input type="text" name="note" maxlength="200" placeholder="Ex. urgent">
  <button class="btn primary big">Envoyer la demande</button>
  <p class="small muted">Minimum <?= h(fmt_amount($min)) ?>. Le distributeur reçoit votre demande tout de suite.</p>
</form>

<?php if ($requests): ?>
<section class="card">
  <h2>Mes demandes</h2>
  <?php foreach ($requests as $r): ?>
    <div class="row-link">
      <span><b><?= h(fmt_amount($r['amount'])) ?></b> <span class="muted small">· <?= h(fmt_dt($r['created_at'])) ?></span>
        <?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?><br><span class="small">Motif : <?= h($r['reject_reason']) ?></span><?php endif; ?>
      </span>
      <span class="right">
        <?= badge($r['status'], req_status_label($r['status'])) ?>
        <?php if ($r['status'] === 'pending'): ?>
          <?= action_button('agent/request/' . $r['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette demande ?') ?>
        <?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2>Mon relevé</h2>
  <p class="small muted">Pour rembourser, envoyez l'argent par Orange Money au distributeur depuis votre numéro <b class="mono"><?= h(fmt_phone($agent['login_phone'])) ?></b> : votre dette baisse toute seule. Un remboursement partiel est accepté.</p>
  <?php if (!$ops): ?><div class="empty">Aucune opération pour l'instant.</div><?php endif; ?>
  <?php foreach ($ops as $o): ?><?= op_row($o, false, true) ?><?php endforeach; ?>
</section>

<details class="card">
  <summary><b>Changer mon code PIN</b></summary>
  <form method="post" action="<?= h(url('agent/pin')) ?>" class="form">
    <?= csrf_field() ?>
    <label>Code PIN actuel</label>
    <input type="password" name="current" inputmode="numeric" required>
    <label>Nouveau code PIN (4 à 6 chiffres)</label>
    <input type="password" name="new" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" required>
    <button class="btn">Changer</button>
  </form>
</details>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
