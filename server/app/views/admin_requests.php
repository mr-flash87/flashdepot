<?php /** @var array $rows @var string $filter */ ?>
<div class="page-head"><h1>Demandes de dépôt</h1></div>
<div class="tabs">
  <a href="<?= h(url('admin/requests')) ?>"<?= $filter !== 'all' ? ' class="on"' : '' ?>>En cours</a>
  <a href="<?= h(url('admin/requests?f=all')) ?>"<?= $filter === 'all' ? ' class="on"' : '' ?>>Historique</a>
</div>
<?php if (!$rows): ?>
  <div class="empty"><?= $filter === 'all' ? 'Aucune demande pour l\'instant.' : 'Aucune demande en attente. Les nouvelles demandes des agents apparaissent ici.' ?></div>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
  <?= request_card($r) ?>
<?php endforeach; ?>
<div data-poll="<?= h(url('admin/poll')) ?>"></div>
