<?php /** @var array $rows @var array $distributors */ ?>
<div class="page-head"><div><div class="sub">Super-admin</div><h1>Paiements</h1></div></div>
<p class="small muted">Les paiements reçus sur votre numéro (téléphone de la plateforme) prolongent automatiquement l'abonnement du distributeur reconnu par son numéro. Ceux d'un numéro inconnu ou au montant douteux attendent ici.</p>

<section class="card">
<?php if (!$rows): ?><div class="empty">Aucun paiement pour l'instant.</div><?php endif; ?>
<?php foreach ($rows as $pm): $todo = in_array($pm['status'], ['unassigned', 'review'], true); ?>
  <div class="op-wrap">
    <div class="op">
      <div class="op-ic in"><?= icon('wallet') ?></div>
      <div class="op-main">
        <div class="op-title"><?= h($pm['dist_name'] ?? 'Numéro inconnu') ?>
          <?php if ($todo): ?><?= badge('review', $pm['status'] === 'unassigned' ? 'À attribuer' : 'À vérifier') ?><?php elseif ($pm['status'] === 'ignored'): ?><?= badge('cancelled', 'Ignoré') ?><?php endif; ?></div>
        <div class="op-meta"><?= h(fmt_dt($pm['created_at'])) ?> · <?= $pm['source'] === 'sms' ? 'SMS' : 'manuel' ?><?= $pm['phone'] ? ' · ' . h(fmt_phone($pm['phone'])) : '' ?><?= $pm['months'] ? ' · ' . (int)$pm['months'] . ' mois ' . h(plan_label((string)$pm['plan'])) : '' ?><?= $pm['txn_id'] ? ' · <span class="mono">' . h($pm['txn_id']) . '</span>' : '' ?><?= $pm['note'] ? ' · ' . h($pm['note']) : '' ?></div>
      </div>
      <div class="op-amt in">+<?= h(fmt_amount($pm['amount'])) ?></div>
    </div>
    <?php if ($todo): ?>
      <div class="op-actions">
        <form method="post" action="<?= h(url('superadmin/payments/' . $pm['id'] . '/assign')) ?>" class="assign">
          <?= csrf_field() ?>
          <select name="distributor_id" required>
            <option value="">Distributeur…</option>
            <?php foreach ($distributors as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (int)$pm['distributor_id'] === (int)$d['id'] ? ' selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach; ?>
          </select>
          <input type="number" name="months" min="1" max="36" placeholder="mois (auto)" style="width:120px;margin:0">
          <label class="check"><input type="checkbox" name="remember" value="1" checked> Retenir ce numéro</label>
          <button class="btn primary">Valider</button>
        </form>
        <?= action_button('superadmin/payments/' . $pm['id'] . '/ignore', 'Ignorer', 'btn ghost') ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</section>
