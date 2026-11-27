<?php
declare(strict_types=1);

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['config'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $default;
        }
        $v = $v[$k];
    }
    return $v;
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function ts_plus(int $seconds): string
{
    return gmdate('Y-m-d H:i:s', time() + $seconds);
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    // Site servi depuis un dossier parent par réécriture (ex. repo déployé dans public_html, site dans server/) :
    // l'adresse visitée ne contient pas « /server », on remonte jusqu'au préfixe réellement utilisé.
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    while ($dir !== '' && $dir !== '.' && !str_starts_with($uri . '/', $dir . '/')) {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }
    return $base = ($dir === '.' ? '' : $dir);
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    $configured = rtrim((string)cfg('app_url', ''), '/');
    if ($configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/** Retour à la page précédente (même site) ou à $fallback. */
function redirect_back(string $fallback): never
{
    $ref = (string)($_POST['_back'] ?? '');
    if ($ref !== '' && str_starts_with($ref, '/') && !str_starts_with($ref, '//')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', 'AZ'), '=');
}

function new_reference(string $prefix): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $prefix . '-' . $s;
}

/** Clé de comparaison d'un numéro : les 8 derniers chiffres (numéros du Burkina). */
function phone_key(?string $phone): ?string
{
    if ($phone === null) {
        return null;
    }
    $d = preg_replace('/\D+/', '', $phone);
    if ($d === '' || strlen($d) < 8) {
        return null;
    }
    return substr($d, -8);
}

function fmt_phone(?string $phone): string
{
    $k = phone_key($phone);
    if ($k === null) {
        return (string)$phone;
    }
    return trim(chunk_split($k, 2, ' '));
}

function parse_amount(?string $raw): ?float
{
    if ($raw === null) {
        return null;
    }
    $s = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $raw);
    // "5.000" ou "5,000" (séparateur de milliers) vs "5000,50" / "5000.50" (décimales)
    if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $s)) {
        $s = preg_replace('/[.,]/', '', $s);
    } else {
        $s = str_replace(',', '.', $s);
        if (substr_count($s, '.') > 1) {
            $s = preg_replace('/\.(?=.*\.)/', '', $s);
        }
    }
    if (!is_numeric($s)) {
        return null;
    }
    return round((float)$s, 2);
}

/** Montant saisi dans un formulaire ("500 000", "500.000", "500000"). */
function input_amount($raw): ?float
{
    $s = preg_replace('/[^\d]/', '', (string)$raw);
    return $s === '' ? null : (float)$s;
}

function fmt_amount($amount, string $currency = 'F'): string
{
    $n = (float)$amount;
    $dec = fmod($n, 1.0) == 0.0 ? 0 : 2;
    return number_format($n, $dec, ',', ' ') . ' ' . $currency;
}

function local_tz(): DateTimeZone
{
    return new DateTimeZone((string)setting('timezone', 'Africa/Ouagadougou'));
}

function fmt_dt(?string $dt, string $format = 'd/m/Y H:i'): string
{
    if (!$dt) {
        return '—';
    }
    $d = new DateTime($dt, new DateTimeZone('UTC'));
    $d->setTimezone(local_tz());
    return $d->format($format);
}

/** Début de la journée locale, en UTC (pour les totaux « aujourd'hui »). */
function today_start_utc(): string
{
    $d = new DateTime('today', local_tz());
    $d->setTimezone(new DateTimeZone('UTC'));
    return $d->format('Y-m-d H:i:s');
}

function ago(?string $dt): string
{
    if (!$dt) {
        return 'jamais';
    }
    $s = time() - strtotime($dt . ' UTC');
    if ($s < 60) {
        return 'à l\'instant';
    }
    if ($s < 3600) {
        return 'il y a ' . intdiv($s, 60) . ' min';
    }
    if ($s < 86400) {
        return 'il y a ' . intdiv($s, 3600) . ' h';
    }
    return fmt_dt($dt);
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $code, string $message, int $http = 400): never
{
    json_out(['error' => ['code' => $code, 'message' => $message]], $http);
}

function json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('invalid_json', 'Le corps de la requête doit être du JSON valide.');
    }
    return $data;
}

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $h = $v;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m)) {
        return $m[1];
    }
    $alt = $_SERVER['HTTP_X_API_KEY'] ?? '';
    return $alt !== '' ? trim($alt) : null;
}

function app_name(): string
{
    return (string)setting('app_name', 'FlashDépôt');
}

function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_DIR . '/views/' . $name . '.php';
}

/** Durée de connexion (renouvelée à chaque visite). */
const SESSION_LIFETIME = 30 * 86400;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Sessions rangées dans le dossier de l'application : l'hébergeur ne les efface pas au bout de 24 minutes.
    $dir = APP_ROOT . '/data/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents(APP_ROOT . '/data/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        if (random_int(1, 100) === 1) {
            foreach (glob($dir . '/sess_*') ?: [] as $f) {
                if (@filemtime($f) < time() - SESSION_LIFETIME) {
                    @unlink($f);
                }
            }
        }
    }
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $params = [
        'lifetime' => SESSION_LIFETIME,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
        'path' => base_path() === '' ? '/' : base_path() . '/',
    ];
    session_name('flashdepot');
    session_set_cookie_params($params);
    session_start();
    if (!headers_sent() && (!empty($_SESSION['uid']) || !empty($_SESSION['agent_id']))) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + SESSION_LIFETIME,
            'path' => $params['path'],
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="_back" value="' . h($_SERVER['REQUEST_URI'] ?? '') . '">';
}

function csrf_check(): void
{
    start_session();
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }
}

function flash(?string $msg = null, string $type = 'ok')
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'][] = [$type, $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function log_line(string $msg): void
{
    $dir = APP_ROOT . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    @file_put_contents($dir . '/app.log', '[' . now() . '] ' . $msg . "\n", FILE_APPEND);
}

/** Limite les essais de connexion : 8 échecs en 15 min par adresse IP et par identifiant. */
function login_blocked(string $who): bool
{
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    q('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 900)]);
    $n = (int)q_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? OR who = ?', [$ip, $who]);
    return $n >= 8;
}

function login_failed(string $who): void
{
    db_insert('login_attempts', ['ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64), 'who' => mb_substr($who, 0, 100), 'created_at' => now()]);
    usleep(400000);
}

function device_online(array $d): bool
{
    return $d['last_seen_at'] && strtotime($d['last_seen_at'] . ' UTC') > time() - 600;
}

/** Icônes SVG (trait 2px). */
function icon(string $name, int $size = 18): string
{
    $p = [
        'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'inbox' => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5 5h14l2 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'sms' => '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H9l-5 4v-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M8 9h8M8 12.5h5"/>',
        'phone' => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18h2"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'down' => '<path d="M12 5v14M5 12l7 7 7-7"/>',
        'cash' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2z"/><circle cx="16" cy="14.5" r="1.2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11z"/>',
        'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 8.5 3 8.5H3S6 15 6 8"/><path d="M10.3 20a1.9 1.9 0 0 0 3.4 0"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>',
        'send' => '<path d="M21.5 3 2.5 10.5l7 2.5M21.5 3l-4 18-8-8M21.5 3 9.5 13v6l3.5-3.5"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'logo' => '<path d="M7 17V7M7 7 3.5 10.5M7 7l3.5 3.5M17 7v10M17 17l-3.5-3.5M17 17l3.5-3.5"/>',
    ][$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
