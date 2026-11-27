<?php /** @var array $rows @var ?array $test */
$kinds = ['in' => 'Argent reçu', 'out' => 'Transfert envoyé', 'other' => 'Autre débit'];
?>
<div class="page-head"><div><a class="back" href="<?= h(url('admin/settings')) ?>">← Plus</a><h1>SMS reçus</h1></div></div>

<details class="card" <?= $test !== null ? 'open' : '' ?>>
  <summary><b>Tester la lecture d'un SMS</b></summary>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="sender" value="OrangeMoney">
    <textarea name="body" rows="4" placeholder="Collez ici un SMS Orange Money…"><?= h($_POST['body'] ?? '') ?></textarea>
    <button class="btn primary">Analyser</button>
  </form>
  <?php if ($test !== null): ?>
    <div class="parsed">
      <?php if ($test['kind'] === null): ?>
        <div class="alert warn">Ce SMS n'est pas reconnu comme une opération.</div>
      <?php else: $ag = agent_by_phone($test['phone']); ?>
        <div><span class="muted">Type :</span> <b><?= h($kinds[$test['kind']] ?? $test['kind']) ?></b></div>
        <div><span class="muted">Montant :</span> <b><?= $test['amount'] !== null ? h(fmt_amount($test['amount'])) : '—' ?></b></div>
        <div><span class="muted">Numéro :</span> <b class="mono"><?= h(fmt_phone($test['phone'])) ?: '—' ?></b> <?= $ag ? '→ ' . h($ag['name']) : ($test['phone'] ? '<span class="muted">(aucun agent)</span>' : '') ?></div>
        <div><span class="muted">Nom :</span> <?= h($test['counterpart'] ?? '—') ?></div>
        <div><span class="muted">Transaction :</span> <span class="mono"><?= h($test['txn'] ?? '—') ?></span></div>
        <div><span class="muted">Nouveau solde :</span> <?= $test['balance'] !== null ? h(fmt_amount($test['balance'])) : '—' ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</details>

<section class="card">
  <?php if (!$rows): ?><div class="empty">Aucun SMS reçu. Associez le téléphone du distributeur (menu Téléphone).</div><?php endif; ?>
  <?php foreach ($rows as $s): ?>
    <div class="sms">
      <div class="sms-head">
        <span class="small muted"><?= h(fmt_dt($s['received_at'])) ?> · <?= h($s['sender']) ?></span>
        <?= badge($s['status'], ['ok' => 'Compté', 'info' => 'Solde', 'ignored' => 'Ignoré', 'duplicate' => 'Doublon', 'pending' => '…'][$s['status']] ?? op_status_label($s['status'])) ?>
      </div>
      <div class="sms-body"><?= h($s['body']) ?></div>
      <?php if ($s['kind']): ?>
        <div class="small muted"><?= h($kinds[$s['kind']] ?? '') ?><?= $s['amount'] !== null ? ' · ' . h(fmt_amount($s['amount'])) : '' ?><?= $s['phone'] ? ' · ' . h(fmt_phone($s['phone'])) : '' ?><?= $s['balance'] !== null ? ' · solde ' . h(fmt_amount($s['balance'])) : '' ?><?= $s['note'] ? ' · ' . h($s['note']) : '' ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
