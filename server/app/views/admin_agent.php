<?php /** @var array $agent @var float $due @var array $phones @var array $ops @var array $requests */
$id = (int)$agent['id'];
$base = 'admin/agents/' . $id;
$sumOk = fn(array $kinds) => array_sum(array_map(fn($o) => in_array($o['kind'], $kinds, true) && $o['status'] === 'ok' ? (float)$o['amount'] : 0, $ops));
?>
<div class="page-head">
  <div>
    <a class="back" href="<?= h(url('admin/agents')) ?>">← Agents</a>
    <h1><?= h($agent['name']) ?><?= (int)$agent['active'] === 1 ? '' : ' <span class="badge b-cancelled">désactivé</span>' ?></h1>
    <div class="muted small">
      <?php $mainsH = agent_main_phones($id); ?>
      <?= implode(' · ', array_map(fn($op) => h(mm_operators()[$op]['short']) . ' <span class="mono">' . h(fmt_phone($mainsH[$op])) . '</span>', array_keys($mainsH))) ?>
      <?php $others = array_diff($phones, array_values($mainsH)); if ($others): ?> · autres : <?= implode(', ', array_map(fn($k) => '<span class="mono">' . h(fmt_phone($k)) . '</span>', $others)) ?><?php endif; ?>
      <?= $agent['location'] ? ' · ' . h($agent['location']) : '' ?>
    </div>
  </div>
  <a class="btn" href="<?= h(url($base . '/edit')) ?>">Modifier</a>
</div>

<div class="hero <?= $due > 0 ? '' : 'hero-ok' ?>">
  <div class="hero-label"><?= $due >= 0 ? 'Reste à payer' : 'Avance de l\'agent' ?></div>
  <div class="hero-value"><?= h(fmt_amount(abs($due))) ?></div>
  <div class="hero-sub">Dépôts : <?= h(fmt_amount($sumOk(['depot', 'ajust_plus']))) ?> · Remboursé : <?= h(fmt_amount($sumOk(['remb_om', 'remb_cash', 'ajust_moins']))) ?></div>
</div>

<div class="quick">
  <details class="quick-item" open>
    <summary class="btn primary"><?= icon('cash') ?> Remboursement cash</summary>
    <form method="post" action="<?= h(url($base . '/cash')) ?>" class="card">
      <?= csrf_field() ?>
      <label>Montant reçu en espèces</label>
      <input type="text" name="amount" inputmode="numeric" required placeholder="<?= h(number_format(max(0, $due), 0, ',', ' ')) ?>" class="amount-input">
      <?php if ($due > 0): ?><button type="button" class="chip" data-fill="<?= (int)$due ?>">Tout (<?= h(fmt_amount($due)) ?>)</button><?php endif; ?>
      <input type="text" name="note" placeholder="Note (facultatif)">
      <button class="btn primary">Enregistrer le cash</button>
    </form>
  </details>
  <details class="quick-item">
    <summary class="btn"><?= icon('plus') ?> Autre opération</summary>
    <div class="card">
      <p class="small muted">À utiliser si un SMS n'est pas remonté, ou pour corriger une erreur.</p>
      <?php foreach ([
          'depot' => 'Dépôt envoyé (sans SMS)',
          'om' => 'Remboursement Mobile Money (sans SMS)',
          'plus' => 'Correction : ajouter à la dette',
          'moins' => 'Correction : retirer de la dette',
      ] as $act => $label): ?>
        <form method="post" action="<?= h(url($base . '/' . $act)) ?>" class="inline-form">
          <?= csrf_field() ?>
          <b class="small"><?= h($label) ?></b>
          <div class="row">
            <input type="text" name="amount" inputmode="numeric" required placeholder="Montant">
            <input type="text" name="note" placeholder="Note">
            <?php if (count(dist_operators()) > 1 && in_array($act, ['depot', 'om'], true)): ?>
              <select name="operator" style="width:auto;margin:0"><?php foreach (dist_operators() as $op): ?><option value="<?= h($op) ?>"><?= h(mm_operators()[$op]['short']) ?></option><?php endforeach; ?></select>
            <?php endif; ?>
            <button class="btn">OK</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </details>
</div>

<?php $open = array_filter($requests, fn($r) => in_array($r['status'], ['pending', 'approved'], true)); ?>
<?php if ($open): ?>
<section>
  <div class="sec-head"><h2>Demandes en cours</h2></div>
  <?php foreach ($open as $r): ?>
    <?= request_card($r + ['agent_name' => $agent['name'], 'login_phone' => $agent['login_phone']]) ?>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card">
  <div class="sec-head"><h2>Relevé</h2><span class="muted small"><?= count($ops) ?> opération(s)</span></div>
  <?php if (!$ops): ?><div class="empty">Aucune opération pour cet agent.</div><?php endif; ?>
  <?php foreach ($ops as $o): ?>
    <div class="op-wrap">
      <?= op_row($o + ['agent_name' => null], false, true) ?>
      <?php if ($o['status'] === 'ok'): ?>
        <?= action_button('admin/operations/' . $o['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette opération ? Elle ne comptera plus dans la dette.') ?>
      <?php elseif (in_array($o['status'], ['cancelled', 'rejected', 'ignored'], true)): ?>
        <?= action_button('admin/operations/' . $o['id'] . '/restore', 'Rétablir', 'link small') ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<?php $closed = array_filter($requests, fn($r) => !in_array($r['status'], ['pending', 'approved'], true)); ?>
<?php if ($closed): ?>
<section class="card">
  <div class="sec-head"><h2>Demandes passées</h2></div>
  <?php foreach ($closed as $r): ?>
    <div class="row-link">
      <span><?= h(fmt_amount($r['amount'])) ?> <span class="muted small">· <?= h(fmt_dt($r['created_at'])) ?></span></span>
      <?= badge($r['status'], req_status_label($r['status'])) ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card">
  <div class="sec-head"><h2>Compte</h2></div>
  <p class="small">Connexion de l'agent : numéro <b class="mono"><?= h(fmt_phone($agent['login_phone'])) ?></b> + son code PIN, sur <span class="mono"><?= h(abs_url('agent')) ?></span></p>
  <p class="small muted">Dernière connexion : <?= h(ago($agent['last_login_at'])) ?><?= $agent['notes'] ? ' · ' . h($agent['notes']) : '' ?></p>
  <?= action_button($base . '/toggle', (int)$agent['active'] === 1 ? 'Désactiver l\'agent' : 'Réactiver l\'agent', 'btn ghost', (int)$agent['active'] === 1 ? 'Désactiver cet agent ? Il ne pourra plus se connecter (son historique est conservé).' : '') ?>
</section>
