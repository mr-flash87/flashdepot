<?php /** @var ?string $error */ ?>
<div class="page-head"><div><a class="back" href="<?= h(url('superadmin')) ?>">← Distributeurs</a><h1>Nouveau distributeur</h1></div></div>
<?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <label>Nom de l'entreprise</label>
  <input type="text" name="name" required value="<?= h($_POST['name'] ?? '') ?>">
  <label>Numéro Orange ou Moov Money (reconnaît ses paiements)</label>
  <input type="tel" name="phone" inputmode="numeric" value="<?= h($_POST['phone'] ?? '') ?>">
  <label>Email de connexion</label>
  <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
  <label>Mot de passe (8 caractères min.)</label>
  <input type="text" name="password" required minlength="8" value="<?= h($_POST['password'] ?? substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 10)) ?>" class="mono">
  <label>Formule</label>
  <select name="plan">
    <option value="essai">Essai gratuit (<?= (int)setting('trial_days', '30') ?> jours)</option>
    <?php foreach (plans() as $k => $p): ?><option value="<?= h($k) ?>"><?= h($p['name']) ?> · <?= h(fmt_amount($p['price'])) ?>/mois</option><?php endforeach; ?>
    <option value="offert">Offert (gratuit, sans fin)</option>
  </select>
  <label>Nombre de mois payés (formules payantes)</label>
  <select name="months"><?php foreach ([1, 2, 3, 6, 12] as $m): ?><option value="<?= $m ?>"><?= $m ?> mois</option><?php endforeach; ?></select>
  <button class="btn primary big">Créer le distributeur</button>
  <p class="small muted">Il se connecte ensuite sur <span class="mono"><?= h(abs_url('admin')) ?></span> avec cet email et ce mot de passe.</p>
</form>
