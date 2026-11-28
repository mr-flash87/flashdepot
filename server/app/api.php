<?php
declare(strict_types=1);

/*
 * API du téléphone Android (application FlashDépôt).
 *  POST /api/device/pair       { code }                -> { token, device, config }
 *  POST /api/device/heartbeat  { battery, charging }   -> { ok, config }
 *  POST /api/device/sms        { messages: [{ client_id, sender, body, timestamp }] }
 */

function device_from_token(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    return q_one('SELECT * FROM devices WHERE token_hash = ? AND active = 1', [hash('sha256', $token)]);
}

function device_config(): array
{
    return ['allowed_senders' => sms_allowed_senders()];
}

function api_device(string $method, array $parts): never
{
    $action = $parts[0] ?? '';
    if ($method !== 'POST') {
        json_error('method_not_allowed', 'POST attendu.', 405);
    }
    $in = json_input();

    if ($action === 'pair') {
        $code = preg_replace('/\D+/', '', (string)($in['code'] ?? ''));
        $dev = $code !== '' ? q_one('SELECT * FROM devices WHERE pair_code = ? AND active = 1', [$code]) : null;
        if (!$dev || !$dev['pair_expires_at'] || $dev['pair_expires_at'] < now()) {
            json_error('invalid_code', 'Code invalide ou expiré. Générez-en un nouveau sur le site (menu Téléphone).', 403);
        }
        $token = 'dev_' . random_token(32);
        db_update('devices', [
            'token_hash' => hash('sha256', $token),
            'pair_code' => null,
            'pair_expires_at' => null,
            'last_seen_at' => now(),
            'app_version' => mb_substr((string)($in['app_version'] ?? ''), 0, 20),
        ], 'id = :id', ['id' => $dev['id']]);
        json_out(['token' => $token, 'device' => ['id' => (int)$dev['id'], 'name' => $dev['name']], 'config' => device_config()]);
    }

    $device = device_from_token(bearer_token());
    if (!$device) {
        json_error('unauthorized', 'Téléphone non associé ou révoqué.', 401);
    }
    // Chaque téléphone appartient à un distributeur (ou à la plateforme pour les abonnements).
    set_did($device['distributor_id'] !== null ? (int)$device['distributor_id'] : null);
    $seen = ['last_seen_at' => now()];
    if (isset($in['battery'])) {
        $seen['battery'] = max(0, min(100, (int)$in['battery']));
    }
    if (isset($in['charging'])) {
        $seen['charging'] = $in['charging'] ? 1 : 0;
    }
    if (!empty($in['app_version'])) {
        $seen['app_version'] = mb_substr((string)$in['app_version'], 0, 20);
    }
    db_update('devices', $seen, 'id = :id', ['id' => $device['id']]);

    if ($action === 'heartbeat') {
        json_out(['ok' => true, 'config' => device_config()]);
    }

    if ($action === 'sms') {
        $messages = $in['messages'] ?? null;
        if (!is_array($messages) || count($messages) === 0) {
            json_error('invalid_request', 'messages[] attendu.', 422);
        }
        $results = [];
        foreach (array_slice($messages, 0, 50) as $m) {
            if (!is_array($m) || !isset($m['sender'], $m['body'])) {
                $results[] = ['client_id' => $m['client_id'] ?? null, 'status' => 'invalid'];
                continue;
            }
            // recharger le téléphone pour avoir le dernier solde connu
            $device = q_one('SELECT * FROM devices WHERE id = ?', [$device['id']]);
            $r = sms_ingest($device, (string)$m['sender'], (string)$m['body'], isset($m['timestamp']) ? (int)$m['timestamp'] : null);
            // Vocabulaire attendu par l'application : matched = reconnu, unmatched = à attribuer, review = à vérifier.
            $status = ['ok' => 'matched', 'unassigned' => 'unmatched'][$r['status']] ?? $r['status'];
            $results[] = ['client_id' => $m['client_id'] ?? null, 'status' => $status, 'payment' => $r['operation'] ? ('#' . $r['operation']) : null];
        }
        json_out(['ok' => true, 'results' => $results]);
    }

    json_error('not_found', 'Route inconnue.', 404);
}
