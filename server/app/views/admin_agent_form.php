<?php /** @var ?array $agent @var array $errors @var array $prefill */
$v = fn(string $k, $default = '') => $_POST[$k] ?? ($agent[$k] ?? ($prefill[$k] ?? $default));
$mains = $agent ? agent_main_phones((int)$agent['id']) : [];
$extra = $agent ? array_values(array_diff(agent_phones((int)$agent['id']), array_values($mains))) : [];
$ops = dist_operators();
$prefOp = ($prefill['login_phone'] ?? '') !== '' ? (phone_operator($prefill['login_phone']) ?? 'orange') : 'orange';
$checked = $_POST ? (array)($_POST['ops'] ?? []) : ($agent ? array_keys($mains) : [$prefOp]);
?>
<div class="page-head">
  <div>
    <a class="back" href="<?= h(url($agent ? 'admin/agents/' . $agent['id'] : 'admin/agents')) ?>">← Retour</a>
    <h1><?= $agent ? 'Modifier ' . h($agent['name']) : 'Nouvel agent' ?></h1>
  </div>
</div>
<?php foreach ($errors as $e): ?><div class="alert err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <label>Nom de l'agent / du point de vente</label>
  <input type="text" name="name" required value="<?= h($v('name')) ?>" placeholder="Ex. KAFANDO Richard ETS">

  <label><?= count($ops) > 1 ? 'Opérateurs utilisés par cet agent' : 'Numéro de l\'agent' ?></label>
  <div class="opgrid">
  <?php foreach ($ops as $op):
      $val = $_POST['phone_' . $op] ?? ($mains[$op] ?? ($op === (phone_operator($prefill['login_phone'] ?? '') ?? 'orange') ? ($prefill['login_phone'] ?? '') : '')); ?>
    <div class="opbox">
      <?php if (count($ops) > 1): ?>
        <label class="check"><input type="checkbox" name="ops[]" value="<?= h($op) ?>" <?= in_array($op, $checked, true) ? 'checked' : '' ?> data-op-toggle> <b><?= h(mm_name($op)) ?></b></label>
      <?php else: ?>
        <b><?= h(mm_name($op)) ?></b>
      <?php endif; ?>
      <input type="tel" name="phone_<?= h($op) ?>" inputmode="numeric" value="<?= h($val) ?>" placeholder="Numéro <?= h(mm_operators()[$op]['short']) ?> de l'agent">
    </div>
  <?php endforeach; ?>
  </div>
  <?php if (!in_array('moov', $ops, true)): ?>
    <div class="alert info small" style="margin-top:10px">Pour donner un numéro Moov Money à vos agents, ajoutez d'abord <b>votre</b> numéro Moov dans <a href="<?= h(url('admin/settings')) ?>"><b>Réglages → Mes numéros</b></a>.</div>
  <?php endif; ?>
  <p class="small muted">Ces numéros servent à reconnaître ses dépôts et remboursements dans les SMS. L'agent se connecte avec son numéro Orange (ou Moov s'il n'a que Moov), et choisit l'opérateur quand il demande un dépôt.</p>

  <label>Autres numéros (facultatif)</label>
  <textarea name="phones" rows="2" placeholder="Un numéro par ligne"><?= h($_POST['phones'] ?? implode("\n", $extra)) ?></textarea>
  <p class="small muted">S'il rembourse parfois depuis un autre numéro.</p>

  <label><?= $agent ? 'Nouveau code PIN (laisser vide pour ne pas changer)' : 'Code PIN de connexion (4 à 6 chiffres)' ?></label>
  <input type="text" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" <?= $agent ? '' : 'required' ?> value="<?= h($agent ? '' : ($_POST['pin'] ?? (string)random_int(1000, 9999))) ?>" class="mono">

  <label>Quartier / ville (facultatif)</label>
  <input type="text" name="location" value="<?= h($v('location')) ?>">

  <label>Notes (facultatif)</label>
  <input type="text" name="notes" value="<?= h($v('notes')) ?>">

  <button class="btn primary big"><?= $agent ? 'Enregistrer' : 'Créer l\'agent' ?></button>
</form>
