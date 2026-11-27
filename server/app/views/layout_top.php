<?php
/** @var string $title @var string $area */
$area = $area ?? 'public';
$stats = $stats ?? null;
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rel = '/' . trim(substr($path, strlen(base_path())), '/');
$active = function (string $prefix) use ($rel): string {
    $is = $prefix === '/admin' ? $rel === '/admin' : str_starts_with($rel, $prefix);
    return $is ? ' class="on"' : '';
};
$nav = [];
if ($area === 'admin') {
    $opsBadge = $stats ? $stats['unassigned'] + $stats['review'] : 0;
    $nav = [
        ['/admin', 'admin', 'home', 'Accueil', 0],
        ['/admin/requests', 'admin/requests', 'inbox', 'Demandes', $stats['pending_requests'] ?? 0],
        ['/admin/agents', 'admin/agents', 'users', 'Agents', 0],
        ['/admin/operations', 'admin/operations', 'list', 'Opérations', $opsBadge],
        ['/admin/settings', 'admin/settings', 'gear', 'Plus', 0],
    ];
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#ff7900">
<title><?= h($title ?? '') ?> · <?= h(app_name()) ?></title>
<link rel="icon" href="<?= h(url('assets/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= h(url('assets/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= h(url('manifest.webmanifest?area=' . ($area === 'admin' ? 'admin' : 'agent'))) ?>">
<link rel="stylesheet" href="<?= h(url('assets/style.css?v=' . APP_VERSION)) ?>">
</head>
<body class="area-<?= h($area) ?>" data-base="<?= h(base_path()) ?>">
<header class="topbar">
  <div class="brand"><span class="logo"><?= icon('wallet', 18) ?></span><?= h(app_name()) ?></div>
  <?php if ($area === 'admin'): ?>
    <nav class="sidenav">
      <?php foreach ($nav as [$prefix, $href, $ic, $label, $count]): ?>
        <a href="<?= h(url($href)) ?>"<?= $active($prefix) ?>><?= icon($ic) ?><span><?= h($label) ?></span><?php if ($count > 0): ?><b class="count"><?= (int)$count ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <a class="top-out" href="<?= h(url('admin/logout')) ?>" title="Déconnexion"><?= icon('logout') ?></a>
  <?php elseif ($area === 'agent'): ?>
    <a class="top-out" href="<?= h(url('agent/logout')) ?>" title="Déconnexion"><?= icon('logout') ?><span>Sortir</span></a>
  <?php endif; ?>
</header>
<main class="wrap">
<?php foreach (flash() as [$type, $msg]): ?>
  <div class="alert <?= h($type) ?>"><?= h($msg) ?></div>
<?php endforeach; ?>
<?php if ($area === 'admin'): ?>
<nav class="bottomnav">
  <?php foreach ($nav as [$prefix, $href, $ic, $label, $count]): ?>
    <a href="<?= h(url($href)) ?>"<?= $active($prefix) ?>><?= icon($ic, 22) ?><span><?= h($label) ?></span><?php if ($count > 0): ?><b class="count"><?= (int)$count ?></b><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
