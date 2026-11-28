<?php /** @var array $rows @var string $search */
$total = array_sum(array_map(fn($r) => max(0, (float)$r['due']), $rows));
?>
<div class="page-head">
  <h1>Agents <span class="muted small">(<?= count($rows) ?>)</span></h1>
  <a class="btn primary" href="<?= h(url('admin/agents/new')) ?>"><?= icon('plus') ?> Ajouter</a>
</div>
<form class="search" method="get">
  <?= icon('search') ?>
  <input type="search" name="q" value="<?= h($search) ?>" placeholder="Nom, numéro, quartier…">
</form>
<div class="tabs">
  <a href="<?= h(url('admin/agents' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) ?>"<?= ($_GET['tri'] ?? '') !== 'name' ? ' class="on"' : '' ?>>Par dette</a>
  <a href="<?= h(url('admin/agents?tri=name' . ($search !== '' ? '&q=' . rawurlencode($search) : ''))) ?>"<?= ($_GET['tri'] ?? '') === 'name' ? ' class="on"' : '' ?>>Par nom</a>
  <span class="muted small tabs-right">Total dû : <b><?= h(fmt_amount($total)) ?></b></span>
</div>
<?php if (!$rows): ?>
  <div class="empty"><?= $search !== '' ? 'Aucun agent trouvé.' : 'Aucun agent. Ajoutez vos agents avec leur numéro Orange Money : leurs dépôts et remboursements seront reconnus automatiquement.' ?></div>
<?php endif; ?>
<div class="list">
<?php foreach ($rows as $a): $due = (float)$a['due']; ?>
  <a class="agent-row<?= (int)$a['active'] === 1 ? '' : ' off' ?>" href="<?= h(url('admin/agents/' . $a['id'])) ?>">
    <span class="avatar-sm"><?= h(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
    <span class="agent-main">
      <b><?= h($a['name']) ?></b><?= (int)$a['active'] === 1 ? '' : ' <span class="badge b-cancelled">désactivé</span>' ?>
      <span class="muted small"><?= h(fmt_phone($a['login_phone'])) ?><?= $a['location'] ? ' · ' . h($a['location']) : '' ?> · <?= h(ago($a['last_op_at'])) ?></span>
    </span>
    <b class="<?= money_class($due) ?>"><?= h(fmt_amount($due)) ?></b>
  </a>
<?php endforeach; ?>
</div>
