<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('APP_VERSION', '1.1.0');

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli') {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        while ($base !== '' && $base !== '.' && !str_starts_with($uri . '/', $base . '/')) {
            $base = rtrim(dirname($base), '/'); // site servi depuis server/ par réécriture
        }
        $base = $base === '.' ? '' : $base;
        header('Location: ' . $base . '/install.php');
        exit;
    }
    fwrite(STDERR, "config.php introuvable. Lancez install.php.\n");
    exit(1);
}

$GLOBALS['config'] = require $configFile;
date_default_timezone_set('UTC');

require APP_DIR . '/helpers.php';
require APP_DIR . '/db.php';
require APP_DIR . '/settings.php';
require APP_DIR . '/sms.php';
require APP_DIR . '/ledger.php';
require APP_DIR . '/telegram.php';
require APP_DIR . '/webpush.php';
require APP_DIR . '/notify.php';

db_migrate();
