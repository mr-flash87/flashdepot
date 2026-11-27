<?php /** @var array $agent @var float $due @var array $requests @var array $ops */
$first = explode(' ', trim($agent['name']))[0];
$open = array_values(array_filter($requests, fn($r) => in_array($r['status'], ['pending', 'approved'], true)));
$today = today_start_utc();
$depToday = array_sum(array_map(fn($o) => $o['kind'] === 'depot' && $o['occurred_at'] >= $today ? (float)$o['amount'] : 0, $ops));
$rembToday = array_sum(array_map(fn($o) => op_sign($o['kind']) < 0 && $o['occurred_at'] >= $today ? (float)$o['amount'] : 0, $ops));
?>
<div class="page-head"><div><div class="sub">Bonjour <?= h($first) ?> 👋</div><h1>Mon compte</h1></div></div>

<div class="hero <?= $due > 0 ? '' : 'hero-ok' ?>">
  <div class="hero-label"><?= $due >= 0 ? 'Vous devez au distributeur' : 'Votre avance' ?></div>
  <div class="hero-value"><?= h(fmt_amount(abs($due))) ?></div>
  <div class="hero-sub">Mis à jour automatiquement à chaque dépôt et remboursement</div>
  <div class="hero-actions">
    <a class="btn solid" href="<?= h(url('agent/demande')) ?>"><?= icon('send') ?> Demander un dépôt</a>
    <a class="btn" href="<?= h(url('agent/releve')) ?>"><?= icon('chart') ?> Relevé</a>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Dépôts reçus</span><span class="chipic out"><?= icon('down') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($depToday)) ?></div>
    <div class="stat-s">aujourd'hui</div>
  </div>
  <div class="stat">
    <div class="stat-top"><span class="stat-l">Remboursé</span><span class="chipic in"><?= icon('up') ?></span></div>
    <div class="stat-v"><?= h(fmt_amount($rembToday)) ?></div>
    <div class="stat-s">aujourd'hui</div>
  </div>
</div>

<?php if ($open): ?>
<section>
  <div class="sec-head"><h2>Demandes en cours</h2></div>
  <?php foreach ($open as $r): ?>
    <div class="req req-<?= h($r['status']) ?>">
      <div class="req-top">
        <div><b><?= h(fmt_amount($r['amount'])) ?></b><div class="muted small"><?= h(fmt_dt($r['created_at'])) ?></div></div>
        <?= badge($r['status'], req_status_label($r['status'])) ?>
      </div>
      <div class="small muted" style="margin-top:8px"><?= $r['status'] === 'pending' ? 'Le distributeur a reçu votre demande.' : 'Validée : le dépôt arrive sur votre numéro Orange Money.' ?></div>
      <?php if ($r['status'] === 'pending'): ?>
        <div class="req-actions"><?= action_button('agent/request/' . $r['id'] . '/cancel', 'Annuler la demande', 'btn ghost sm', 'Annuler cette demande ?') ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="card">
  <div class="sec-head"><h2>Dernières opérations</h2><a href="<?= h(url('agent/releve')) ?>">Tout voir</a></div>
  <?php if (!$ops): ?><div class="empty">Aucune opération pour l'instant.</div><?php endif; ?>
  <?php foreach (array_slice($ops, 0, 5) as $o): ?><?= op_row($o, false, true, true) ?><?php endforeach; ?>
</section>

<div class="card small muted">
  <b style="color:var(--text)">Pour rembourser</b><br>
  Envoyez l'argent par Orange Money au distributeur depuis votre numéro <b class="mono"><?= h(fmt_phone($agent['login_phone'])) ?></b> :
  votre dette baisse toute seule. Un remboursement partiel est accepté.
</div>
<div data-poll="<?= h(url('agent/poll')) ?>"></div>
