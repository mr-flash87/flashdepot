<?php /** @var array $rows @var string $filter @var string $day @var array $agents @var array $stats */
$tabs = [
    'all' => 'Tout',
    'unassigned' => 'À attribuer' . ($stats['unassigned'] ? ' (' . $stats['unassigned'] . ')' : ''),
    'review' => 'À vérifier' . ($stats['review'] ? ' (' . $stats['review'] . ')' : ''),
    'depot' => 'Dépôts',
    'remb' => 'Remboursements',
    'cancelled' => 'Annulées',
];
$q = fn(string $f) => url('admin/operations?f=' . $f . ($day !== '' ? '&jour=' . $day : ''));
$total = array_sum(array_map(fn($o) => $o['status'] === 'ok' ? (float)$o['amount'] : 0, $rows));
?>
<div class="page-head">
  <h1>Opérations</h1>
  <form method="get" class="day">
    <input type="hidden" name="f" value="<?= h($filter) ?>">
    <input type="date" name="jour" value="<?= h($day) ?>" onchange="this.form.submit()">
    <?php if ($day !== ''): ?><a href="<?= h(url('admin/operations?f=' . $filter)) ?>" class="small">Tous les jours</a><?php endif; ?>
  </form>
</div>
<div class="tabs scroll">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="<?= h($q($k)) ?>"<?= $filter === $k ? ' class="on"' : '' ?>><?= h($label) ?></a>
  <?php endforeach; ?>
</div>
<?php if (in_array($filter, ['depot', 'remb'], true)): ?>
  <p class="small muted">Total affiché : <b><?= h(fmt_amount($total)) ?></b> (<?= count($rows) ?> opérations)</p>
<?php endif; ?>
<?php if ($filter === 'unassigned'): ?>
  <p class="small muted">Argent reçu ou envoyé à un numéro qui n'est lié à aucun agent. Attribuez-le à un agent (cochez « Retenir ce numéro » pour la prochaine fois), ou ignorez-le si ce n'est pas une opération d'agent.</p>
<?php elseif ($filter === 'review'): ?>
  <p class="small muted">Le solde annoncé par le SMS ne correspond pas au solde précédent. Cela arrive si un SMS a été manqué, mais peut aussi être un faux SMS. Vérifiez dans votre historique Orange Money avant de confirmer.</p>
<?php endif; ?>

<section class="card">
<?php if (!$rows): ?><div class="empty">Rien ici.</div><?php endif; ?>
<?php foreach ($rows as $o): ?>
  <div class="op-wrap">
    <?= op_row($o) ?>
    <?php if ($o['status'] === 'unassigned'): ?>
      <div class="op-actions">
        <form method="post" action="<?= h(url('admin/operations/' . $o['id'] . '/assign')) ?>" class="assign">
          <?= csrf_field() ?>
          <select name="agent_id" required>
            <option value="">Choisir l'agent…</option>
            <?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>"><?= h($a['name']) ?></option><?php endforeach; ?>
          </select>
          <label class="check"><input type="checkbox" name="remember" value="1" checked> Retenir ce numéro</label>
          <button class="btn primary">Attribuer</button>
        </form>
        <a class="btn" href="<?= h(url('admin/agents/new?phone=' . rawurlencode((string)$o['phone']) . '&name=' . rawurlencode((string)$o['counterpart']))) ?>">Nouvel agent</a>
        <?= action_button('admin/operations/' . $o['id'] . '/ignore', 'Ignorer', 'btn ghost') ?>
      </div>
    <?php elseif ($o['status'] === 'review'): ?>
      <div class="op-actions">
        <?= action_button('admin/operations/' . $o['id'] . '/confirm', icon('check') . ' C\'est un vrai SMS', 'btn primary', 'Confirmer que cette opération a bien eu lieu (vérifiée dans Orange Money) ?') ?>
        <?= action_button('admin/operations/' . $o['id'] . '/reject', 'Faux SMS : rejeter', 'btn danger') ?>
      </div>
    <?php elseif ($o['status'] === 'ok'): ?>
      <?= action_button('admin/operations/' . $o['id'] . '/cancel', 'Annuler', 'link small danger', 'Annuler cette opération ? Elle ne comptera plus dans la dette de l\'agent.') ?>
    <?php else: ?>
      <?= action_button('admin/operations/' . $o['id'] . '/restore', 'Rétablir', 'link small') ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</section>
<div data-poll="<?= h(url('admin/poll')) ?>"></div>
