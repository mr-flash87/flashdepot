<?php /** @var array $payments @var int $agents_count */
$d = distributor();
$active = dist_active($d);
$left = dist_days_left($d);
$limit = plan_agent_limit($d);
$pay = trim((string)setting('pay_number'));
$payMoov = trim((string)setting('pay_number_moov'));
$support = trim((string)setting('support_whatsapp'));
?>
<div class="page-head"><div><a class="back" href="<?= h(url('admin/settings')) ?>">← Réglages</a><h1>Abonnement</h1></div></div>

<div class="hero <?= $active ? 'hero-ok' : '' ?>">
  <div class="hero-label">Formule actuelle</div>
  <div class="hero-value"><?= h(plan_label($d['plan'])) ?></div>
  <div class="hero-sub">
    <?php if ($d['plan'] === 'offert'): ?>Offert, sans date de fin
    <?php elseif ($active): ?>Valable jusqu'au <?= h(fmt_dt($d['paid_until'], 'd/m/Y')) ?> (<?= max(0, (int)$left) ?> jour(s))
    <?php else: ?>Expiré<?= $d['paid_until'] ? ' depuis le ' . h(fmt_dt($d['paid_until'], 'd/m/Y')) : '' ?> : vos données sont conservées, l'espace est en lecture seule.
    <?php endif; ?>
  </div>
  <div class="hero-sub">Agents actifs : <?= $agents_count ?><?= $limit > 0 ? ' / ' . $limit : ' (illimité)' ?></div>
</div>

<?php if ($d['plan'] !== 'offert'): ?>
<section class="card">
  <h2>Payer ou renouveler</h2>
  <?php if ($pay !== ''): ?>
    <ol class="steps">
      <li>Depuis l'un de vos numéros <b class="mono"><?= dist_numbers() ? h(implode(' ou ', array_map('fmt_phone', dist_numbers()))) : '— (à renseigner dans Réglages)' ?></b>, envoyez le montant de la formule choisie par <b>Orange Money</b> au <b class="mono"><?= h(fmt_phone($pay)) ?></b><?php if ($payMoov !== ''): ?> ou par <b>Moov Money</b> au <b class="mono"><?= h(fmt_phone($payMoov)) ?></b><?php endif; ?>.</li>
      <li>Votre abonnement est prolongé <b>automatiquement</b> dès réception (quelques minutes).</li>
      <li>Pour plusieurs mois, envoyez le montant multiplié (ex. 3 mois Pro = <?= h(fmt_amount(plans()['pro']['price'] * 3)) ?>).</li>
    </ol>
    <?php if (!dist_numbers()): ?><div class="alert warn" style="margin-top:12px">Renseignez d'abord vos numéros dans <a href="<?= h(url('admin/settings')) ?>">Réglages</a>, sinon le paiement ne pourra pas être reconnu.</div><?php endif; ?>
  <?php else: ?>
    <p>Contactez le support FlashDépôt<?= $support !== '' ? ' sur WhatsApp au <b>' . h($support) . '</b>' : '' ?> pour payer votre abonnement.</p>
  <?php endif; ?>
</section>

<div class="stats">
  <?php foreach (plans() as $key => $p): ?>
    <div class="stat"<?= $d['plan'] === $key ? ' style="border-color:var(--brand)"' : '' ?>>
      <div class="stat-top"><span class="stat-l"><?= h($p['name']) ?><?= $d['plan'] === $key ? ' · actuelle' : '' ?></span><span class="chipic out"><?= icon('wallet') ?></span></div>
      <div class="stat-v"><?= h(fmt_amount($p['price'])) ?></div>
      <div class="stat-s">par mois · <?= $p['agents'] > 0 ? $p['agents'] . ' agents' : 'agents illimités' ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="card">
  <h2>Historique des paiements</h2>
  <?php if (!$payments): ?><div class="empty">Aucun paiement pour l'instant.</div><?php endif; ?>
  <?php foreach ($payments as $pm): ?>
    <div class="row-link">
      <span><b><?= h(fmt_amount($pm['amount'])) ?></b> <span class="muted small">· <?= h(fmt_dt($pm['created_at'])) ?><?= $pm['months'] ? ' · ' . (int)$pm['months'] . ' mois ' . h(plan_label((string)$pm['plan'])) : '' ?></span></span>
      <?= badge($pm['status'] === 'ok' ? 'ok' : 'review', $pm['status'] === 'ok' ? 'Validé' : 'En vérification') ?>
    </div>
  <?php endforeach; ?>
</section>
