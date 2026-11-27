<?php /** @var ?array $agent @var array $errors @var array $prefill */
$v = fn(string $k, $default = '') => $_POST[$k] ?? ($agent[$k] ?? ($prefill[$k] ?? $default));
$extra = $agent ? array_values(array_diff(agent_phones((int)$agent['id']), [$agent['login_phone']])) : [];
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

  <label>Numéro Orange Money principal</label>
  <input type="tel" name="login_phone" inputmode="numeric" required value="<?= h($v('login_phone')) ?>" placeholder="64 31 80 90">
  <p class="small muted">Sert à reconnaître ses dépôts et remboursements dans les SMS, et à se connecter.</p>

  <label>Autres numéros Orange Money (facultatif)</label>
  <textarea name="phones" rows="2" placeholder="Un numéro par ligne"><?= h($_POST['phones'] ?? implode("\n", $extra)) ?></textarea>
  <p class="small muted">Si l'agent rembourse parfois depuis un autre numéro.</p>

  <label><?= $agent ? 'Nouveau code PIN (laisser vide pour ne pas changer)' : 'Code PIN de connexion (4 à 6 chiffres)' ?></label>
  <input type="text" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" <?= $agent ? '' : 'required' ?> value="<?= h($agent ? '' : ($_POST['pin'] ?? (string)random_int(1000, 9999))) ?>" class="mono">

  <label>Quartier / ville (facultatif)</label>
  <input type="text" name="location" value="<?= h($v('location')) ?>">

  <label>Notes (facultatif)</label>
  <input type="text" name="notes" value="<?= h($v('notes')) ?>">

  <button class="btn primary big"><?= $agent ? 'Enregistrer' : 'Créer l\'agent' ?></button>
</form>
