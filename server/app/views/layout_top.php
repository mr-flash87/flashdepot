<?php
/** @var string $title @var string $area */
$area = $area ?? 'public';
$stats = $stats ?? null;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rel = '/' . trim(substr($path, strlen(base_path())), '/');
$isOn = function (string $prefix) use ($rel): bool {
    return in_array($prefix, ['/admin', '/agent', '/superadmin'], true) ? $rel === $prefix : str_starts_with($rel, $prefix);
};
$person = in_array($area, ['admin', 'superadmin'], true) ? (has_did() ? distributor()['name'] : (admin_user()['email'] ?? '')) : (($agent ?? null)['name'] ?? '');
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/[\s@._-]+/u', trim($person)) ?: [''], 0, 2)))) ?: '?';

// Onglets du bas (4 + « Plus ») et entrées du menu « Plus ».
$tabs = [];
$more = [];
if ($area === 'admin') {
    $opsBadge = $stats ? $stats['unassigned'] + $stats['review'] : 0;
    $tabs = [
        ['/admin', 'admin', 'home', 'Accueil', 0],
        ['/admin/requests', 'admin/requests', 'inbox', 'Demandes', $stats['pending_requests'] ?? 0],
        ['/admin/agents', 'admin/agents', 'users', 'Agents', 0],
        ['/admin/operations', 'admin/operations', 'list', 'Opérations', $opsBadge],
    ];
    $more = [
        ['admin/device', 'phone', 'Téléphone'],
        ['admin/sms', 'sms', 'SMS reçus'],
        ['admin/settings', 'gear', 'Réglages'],
        ['admin/subscription', 'wallet', 'Abonnement'],
        ['#notif', 'bell', 'Notifications'],
        ['#install', 'download', 'Installer l\'app'],
        ['agent/login', 'users', 'Page agents'],
    ];
    $logout = 'admin/logout';
} elseif ($area === 'superadmin') {
    $tabs = [
        ['/superadmin', 'superadmin', 'users', 'Distributeurs', 0],
        ['/superadmin/payments', 'superadmin/payments', 'wallet', 'Paiements', (int)q_val("SELECT COUNT(*) FROM subscription_payments WHERE status IN ('unassigned','review')")],
        ['/superadmin/announcements', 'superadmin/announcements', 'bell', 'Annonces', 0],
        ['/superadmin/settings', 'superadmin/settings', 'gear', 'Plateforme', 0],
    ];
    $more = [['#install', 'download', 'Installer l\'app']];
    $logout = 'superadmin/logout';
} elseif ($area === 'agent') {
    $tabs = [
        ['/agent', 'agent', 'home', 'Accueil', 0],
        ['/agent/demande', 'agent/demande', 'inbox', 'Demander', 0],
        ['/agent/rembourser', 'agent/rembourser', 'send', 'Rembourser', 0],
        ['/agent/releve', 'agent/releve', 'chart', 'Relevé', 0],
    ];
    $more = [
        ['agent/compte', 'lock', 'Mon compte'],
        ['#notif', 'bell', 'Notifications'],
        ['#install', 'download', 'Installer l\'app'],
    ];
    $logout = 'agent/logout';
}
$pwa = [
    'base' => base_path(),
    'area' => $area,
    'csrf' => csrf_token(),
    'vapid' => in_array($area, ['admin', 'agent'], true) && webpush_supported() ? vapid_public_b64u() : '',
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#f5f6f8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h(['admin' => 'Distributeur', 'superadmin' => 'Super-admin'][$area] ?? app_name()) ?>">
<title><?= h($title ?? '') ?> · <?= h(app_name()) ?></title>
<script>try{var t=localStorage.getItem('fd-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= h(url('assets/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= h(url('manifest.webmanifest?area=' . (in_array($area, ['admin', 'superadmin'], true) ? $area : 'agent'))) ?>">
<link rel="stylesheet" href="<?= h(asset_url('assets/style.css')) ?>">
<script>window.FD = <?= json_encode($pwa, JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body class="area-<?= h($area) ?>" data-base="<?= h(base_path()) ?>">

<?php if ($tabs): ?>
<aside class="sidebar">
  <a class="brand" href="<?= h(url($area)) ?>"><span class="mark"><?= icon('logo', 20) ?></span><span><?= h(app_name()) ?></span></a>
  <nav>
    <?php foreach ($tabs as [$prefix, $href, $ic, $label, $count]): ?>
      <a href="<?= h(url($href)) ?>"<?= $isOn($prefix) ? ' class="on"' : '' ?>><?= icon($ic, 20) ?><span><?= h($label) ?></span><?php if ($count > 0): ?><b class="count"><?= (int)$count ?></b><?php endif; ?></a>
    <?php endforeach; ?>
    <hr>
    <?php foreach ($more as [$href, $ic, $label]): ?>
      <?php if ($href[0] === '#'): ?>
        <button type="button" data-open="<?= h(substr($href, 1)) ?>"><?= icon($ic, 20) ?><span><?= h($label) ?></span></button>
      <?php else: ?>
        <a href="<?= h(url($href)) ?>"<?= $isOn('/' . $href) ? ' class="on"' : '' ?>><?= icon($ic, 20) ?><span><?= h($label) ?></span></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <a class="side-out" href="<?= h(url($logout)) ?>"><?= icon('logout', 20) ?><span>Se déconnecter</span></a>
</aside>
<?php endif; ?>

<div class="shell">
<header class="appbar">
  <a class="brand" href="<?= h(url(in_array($area, ['admin', 'superadmin'], true) ? $area : 'agent')) ?>"><span class="mark"><?= icon('logo', 20) ?></span><span><?= h(app_name()) ?></span></a>
  <?php if ($tabs): ?>
  <div class="appbar-actions">
    <button type="button" class="round" data-theme-toggle aria-label="Changer le thème"><span class="i-sun"><?= icon('sun', 20) ?></span><span class="i-moon"><?= icon('moon', 20) ?></span></button>
    <?php if ($area !== 'superadmin'): ?><button type="button" class="round" data-open="notif" aria-label="Notifications"><?= icon('bell', 20) ?><i class="dot" data-push-dot hidden></i></button><?php endif; ?>
    <button type="button" class="avatar" data-open="more" aria-label="Menu"><?= h($initials) ?></button>
  </div>
  <?php endif; ?>
</header>

<main class="wrap">
<?php foreach (flash() as [$type, $msg]): ?>
  <div class="alert <?= h($type) ?>" data-autohide><?= h($msg) ?></div>
<?php endforeach; ?>
<?php if ($area === 'admin' && has_did()):
    $dd = distributor();
    $left = dist_days_left($dd); ?>
  <?php if (admin_impersonating()): ?>
    <div class="alert warn">Vous consultez l'espace de <b><?= h($dd['name']) ?></b> (assistance). <a href="<?= h(url('admin/logout')) ?>">Revenir au super-admin</a></div>
  <?php endif; ?>
  <?php foreach (q_all('SELECT * FROM announcements WHERE active = 1 ORDER BY id DESC LIMIT 3') as $ann): ?>
    <div class="alert <?= h(['ok' => 'ok', 'warn' => 'warn'][$ann['level']] ?? 'info') ?> ann" data-ann="<?= (int)$ann['id'] ?>" hidden>
      <div><b><?= h($ann['title']) ?></b><?= $ann['body'] ? '<div class="small" style="font-weight:500">' . nl2br(h($ann['body'])) . '</div>' : '' ?></div>
      <button type="button" class="x" data-ann-close aria-label="Fermer"><?= icon('x', 16) ?></button>
    </div>
  <?php endforeach; ?>
  <?php if (!dist_active($dd)): ?>
    <div class="alert err">Abonnement expiré : votre espace est en lecture seule, vos données sont conservées. <a href="<?= h(url('admin/subscription')) ?>">Renouveler</a></div>
  <?php elseif ($left !== null && $left <= 7): ?>
    <div class="alert warn"><?= $dd['plan'] === 'essai' ? 'Essai gratuit' : 'Abonnement' ?> : plus que <?= max(0, $left) ?> jour(s). <a href="<?= h(url('admin/subscription')) ?>">Voir comment payer</a></div>
  <?php endif; ?>
<?php endif; ?>
<?php if ($tabs): ?>
  <div class="install-card" data-install-card hidden>
    <span class="mark"><?= icon('logo', 22) ?></span>
    <div><b>Installer <?= h(app_name()) ?></b><small data-install-text>Accès en un geste depuis l'écran d'accueil, et notifications avec son.</small></div>
    <button type="button" class="btn primary sm" data-pwa-install hidden>Installer</button>
    <button type="button" class="x" data-install-dismiss aria-label="Fermer"><?= icon('x', 18) ?></button>
  </div>
<?php endif; ?>
