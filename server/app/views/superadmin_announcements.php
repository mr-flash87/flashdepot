<?php /** @var array $rows */ ?>
<div class="page-head"><div><div class="sub">Super-admin</div><h1>Annonces</h1></div></div>
<p class="small muted">Un message affiché en haut de l'espace de tous les distributeurs : nouveauté, maintenance, promotion… Chaque distributeur peut le fermer.</p>

<form method="post" action="<?= h(url('superadmin/announcements/create')) ?>" class="card form">
  <?= csrf_field() ?>
  <label>Titre</label>
  <input type="text" name="title" required maxlength="190" placeholder="Ex. Nouveau : Moov Money est disponible">
  <label>Message (facultatif)</label>
  <textarea name="body" rows="3" maxlength="2000" placeholder="Détails…"></textarea>
  <label>Couleur</label>
  <select name="level"><option value="info">Information (bleu)</option><option value="ok">Bonne nouvelle (vert)</option><option value="warn">Important (orange)</option></select>
  <label class="check"><input type="checkbox" name="push" value="1"> Envoyer aussi en notification sur leurs téléphones</label>
  <button class="btn primary">Publier</button>
</form>

<section class="card">
  <h2>Annonces publiées</h2>
  <?php if (!$rows): ?><div class="empty">Aucune annonce.</div><?php endif; ?>
  <?php foreach ($rows as $a): ?>
    <div class="row-link">
      <span><b><?= h($a['title']) ?></b><br><span class="small muted"><?= h(fmt_dt($a['created_at'])) ?><?= $a['body'] ? ' · ' . h(mb_strimwidth($a['body'], 0, 80, '…')) : '' ?></span></span>
      <span class="right">
        <?= badge($a['active'] ? 'ok' : 'cancelled', $a['active'] ? 'Visible' : 'Masquée') ?><br>
        <?= action_button('superadmin/announcements/' . $a['id'] . '/toggle', $a['active'] ? 'Masquer' : 'Afficher', 'link small') ?>
        · <?= action_button('superadmin/announcements/' . $a['id'] . '/delete', 'Supprimer', 'link small danger', 'Supprimer cette annonce ?') ?>
      </span>
    </div>
  <?php endforeach; ?>
</section>
