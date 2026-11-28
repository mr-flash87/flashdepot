<?php /** @var array $d @var ?array $owner @var array $payments @var array $counts */
$id = (int)$d['id'];
$active = dist_active($d);
$left = dist_days_left($d);
$untilLocal = $d['paid_until'] ? fmt_dt($d['paid_until'], 'Y-m-d') : '';
?>
<div class="page-head">
  <div>
    <a class="back" href="<?= h(url('superadmin')) ?>">← Distributeurs</a>
    <h1><?= h($d['name']) ?></h1>
    <div class="muted small"><?= h($owner['email'] ?? '—') ?><?= $d['phone'] ? ' · ' . h(fmt_phone($d['phone'])) : '' ?> · inscrit le <?= h(fmt_dt($d['created_at'], 'd/m/Y')) ?></div>
  </div>
  <?= action_button('superadmin/d/' . $id . '/open', icon('home') . ' Ouvrir son espace', 'btn') ?>
</div>

<div class="hero <?= $active ? 'hero-ok' : '' ?>">
  <div class="hero-label"><?= $d['status'] === 'suspended' ? 'Compte suspendu' : 'Formule' ?></div>
  <div class="hero-value"><?= h(plan_label($d['plan'])) ?></div>
  <div class="hero-sub">
    <?php if ($d['plan'] === 'offert'): ?>Sans date de fin
    <?php elseif ($d['paid_until']): ?><?= $active ? 'Jusqu\'au ' : 'Expiré depuis le ' ?><?= h(fmt_dt($d['paid_until'], 'd/m/Y')) ?><?= $active && $left !== null ? ' (' . max(0, $left) . ' j)' : '' ?>
    <?php else: ?>Aucune date<?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-top"><span class="stat-l">Agents actifs</span><span class="chipic blue"><?= icon('users') ?></span></div><div class="stat-v"><?= $counts['agents'] ?></div><div class="stat-s">limite : <?= plan_agent_limit($d) ?: 'illimité' ?></div></div>
  <div class="stat"><div class="stat-top"><span class="stat-l">Opérations</span><span class="chipic out"><?= icon('list') ?></span></div><div class="stat-v"><?= $counts['ops'] ?></div><div class="stat-s"><?= $counts['ops_30'] ?> sur 30 jours</div></div>
  <div class="stat"><div class="stat-top"><span class="stat-l">Téléphone SMS</span><span class="chipic in"><?= icon('phone') ?></span></div><div class="stat-v" style="font-size:16px"><?= h(ago($counts['device'])) ?></div><div class="stat-s">dernier contact</div></div>
</div>

<form method="post" action="<?= h(url('superadmin/d/' . $id . '/extend')) ?>" class="card form">
  <?= csrf_field() ?>
  <h2>Enregistrer un paiement / prolonger</h2>
  <div class="row">
    <select name="plan" style="flex:1;min-width:140px;margin:0">
      <?php foreach (plans() as $k => $p): ?><option value="<?= h($k) ?>"<?= $d['plan'] === $k ? ' selected' : '' ?>><?= h($p['name']) ?> · <?= h(fmt_amount($p['price'])) ?>/mois</option><?php endforeach; ?>
    </select>
    <select name="months" style="width:auto;margin:0">
      <?php foreach ([1, 2, 3, 6, 12] as $m): ?><option value="<?= $m ?>"><?= $m ?> mois</option><?php endforeach; ?>
    </select>
  </div>
  <label>Montant reçu (facultatif)</label>
  <input type="text" name="amount" inputmode="numeric" placeholder="0">
  <input type="text" name="note" placeholder="Note (ex. payé en cash)">
  <button class="btn primary">Prolonger</button>
</form>

<form method="post" action="<?= h(url('superadmin/d/' . $id . '/edit')) ?>" class="card form">
  <?= csrf_field() ?>
  <h2>Fiche</h2>
  <label>Nom</label>
  <input type="text" name="name" value="<?= h($d['name']) ?>" required>
  <label>Numéro Orange Money (reconnaît ses paiements)</label>
  <input type="tel" name="phone" inputmode="numeric" value="<?= h($d['phone']) ?>">
  <label>Formule</label>
  <select name="plan">
    <?php foreach (array_merge(['essai' => ['name' => 'Essai gratuit'], 'offert' => ['name' => 'Offert (gratuit, sans fin)']], plans()) as $k => $p): ?>
      <option value="<?= h($k) ?>"<?= $d['plan'] === $k ? ' selected' : '' ?>><?= h($p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Valable jusqu'au</label>
  <input type="date" name="paid_until" value="<?= h($untilLocal) ?>">
  <label>Notes internes</label>
  <input type="text" name="notes" value="<?= h($d['notes']) ?>">
  <button class="btn primary">Enregistrer</button>
</form>

<section class="card">
  <h2>Paiements</h2>
  <?php if (!$payments): ?><div class="empty">Aucun paiement.</div><?php endif; ?>
  <?php foreach ($payments as $pm): ?>
    <div class="row-link">
      <span><b><?= h(fmt_amount($pm['amount'])) ?></b> <span class="muted small">· <?= h(fmt_dt($pm['created_at'])) ?> · <?= $pm['source'] === 'sms' ? 'SMS' : 'manuel' ?><?= $pm['months'] ? ' · ' . (int)$pm['months'] . ' mois ' . h(plan_label((string)$pm['plan'])) : '' ?><?= $pm['note'] ? ' · ' . h($pm['note']) : '' ?></span></span>
      <?= badge($pm['status'] === 'ok' ? 'ok' : 'review', $pm['status'] === 'ok' ? 'Validé' : 'À vérifier') ?>
    </div>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Compte</h2>
  <form method="post" action="<?= h(url('superadmin/d/' . $id . '/password')) ?>" class="row" style="margin-bottom:12px">
    <?= csrf_field() ?>
    <input type="text" name="password" placeholder="Nouveau mot de passe du distributeur" minlength="8">
    <button class="btn">Changer</button>
  </form>
  <?= action_button('superadmin/d/' . $id . '/toggle', $d['status'] === 'active' ? 'Suspendre le compte' : 'Réactiver le compte', $d['status'] === 'active' ? 'btn danger' : 'btn primary', $d['status'] === 'active' ? 'Suspendre ce distributeur ? Lui et ses agents ne pourront plus se connecter. Ses données sont conservées.' : '') ?>
</section>
