<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/ui.php';
require APP_DIR . '/api.php';
require APP_DIR . '/admin.php';
require APP_DIR . '/agent.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim(rawurldecode($path), '/');
if ($path === '/index.php') {
    $path = '/';
}
$parts = array_values(array_filter(explode('/', $path), 'strlen'));

try {
    switch ($parts[0] ?? '') {
        case '':
            redirect('agent');
        case 'manifest.webmanifest':
            $area = ($_GET['area'] ?? '') === 'admin' ? 'admin' : 'agent';
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            echo json_encode([
                'name' => app_name() . ($area === 'admin' ? ' – Distributeur' : ' – Agent'),
                'short_name' => app_name(),
                'id' => url($area),
                'start_url' => url($area),
                'scope' => url(''),
                'display' => 'standalone',
                'description' => 'Dépôts Orange Money entre le distributeur et ses agents.',
                'orientation' => 'portrait',
                'background_color' => '#f5f6f8',
                'theme_color' => '#f5f6f8',
                'lang' => 'fr',
                'icons' => [
                    ['src' => url('assets/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                    ['src' => url('assets/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                    ['src' => url('assets/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        case 'api':
            if (($parts[1] ?? '') === 'device') {
                api_device($method, array_slice($parts, 2));
            }
            json_error('not_found', 'Route inconnue.', 404);
        case 'admin':
            admin_route($method, array_slice($parts, 1));
            break;
        case 'agent':
            agent_route($method, array_slice($parts, 1));
            break;
        default:
            http_response_code(404);
            view('layout_top', ['title' => 'Page introuvable', 'area' => 'public']);
            view('message', ['text' => 'Cette page n\'existe pas.']);
            view('layout_bottom', ['area' => 'public']);
    }
} catch (Throwable $e) {
    log_line('ERROR ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (($parts[0] ?? '') === 'api') {
        json_error('server_error', 'Erreur interne du serveur.', 500);
    }
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erreur</title>'
        . '<p style="font-family:sans-serif;padding:20px">Une erreur est survenue.' . (cfg('debug', false) ? ' ' . h($e->getMessage()) : '') . '</p>';
}
