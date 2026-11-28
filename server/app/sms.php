<?php
declare(strict_types=1);

/*
 * Lecture des SMS Orange Money Burkina reçus sur la SIM du distributeur.
 *
 * Exemples réels :
 *  Reçu  : « Vous avez recu 400,000.00 FCFA du 64318090,ANSV KAFANDO RICHARD ETS KKAFANDO RICHARD.
 *            Le solde de votre compte est de 3227000.0 FCFA Trans ID: PP261127.1716.95777515. ... »
 *  Envoi : « Cher client, vous avez transfere 500,000.00 FCFA au numero 44490675, ANSV OUEMA ISSA ISSA OUEMA
 *            TRANSFERT SERVICE. Votre solde est de 2727000.0 FCFA. ID Trans: PP261127.1716.95777731. ... »
 */

const SMS_AMOUNT = '(\d[\d\s.,]*\d|\d)\s*(?:F\s?CFA|FCFA|XOF|F)\b';

/** Expéditeurs acceptés (regex, insensible à la casse). Seuls ces SMS sont transmis par le téléphone. */
function sms_allowed_senders(): array
{
    return ['orange'];
}

function sms_rx(string $pattern, string $subject, ?array &$m = null): bool
{
    return @preg_match('~' . str_replace('~', '\~', $pattern) . '~iu', $subject, $m) === 1;
}

function sms_get(string $pattern, string $text): ?string
{
    return sms_rx($pattern, $text, $m) && isset($m[1]) ? trim($m[1]) : null;
}

/**
 * Analyse un SMS.
 * kind : 'in' (argent reçu = remboursement d'un agent), 'out' (transfert envoyé = dépôt à un agent),
 *        'other' (autre débit : seul le solde est utile), null si le SMS n'est pas une opération.
 * @return array{kind:?string,amount:?float,phone:?string,counterpart:?string,txn:?string,balance:?float}
 */
function sms_parse(string $sender, string $body): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $body));
    $r = ['kind' => null, 'amount' => null, 'phone' => null, 'counterpart' => null, 'txn' => null, 'balance' => null];

    $isSender = false;
    foreach (sms_allowed_senders() as $rx) {
        if (sms_rx($rx, $sender)) {
            $isSender = true;
        }
    }
    if (!$isSender) {
        return $r;
    }

    $txn = sms_get('(?:Trans(?:action)?\s*ID|ID\s*(?:de\s*(?:la\s*)?)?Trans(?:action)?|R[ée]f(?:[ée]rence)?)\s*[:.#]?\s*([A-Z0-9][A-Z0-9.\-]{5,})', $text);
    $r['txn'] = $txn !== null ? rtrim($txn, '.-') : null;
    $r['balance'] = parse_amount(sms_get('solde\D{0,40}?(\d[\d\s.,]*\d|\d)\s*(?:F\s?CFA|FCFA|XOF|F)\b', $text));

    $phone = '(\+?\d[\d ]{6,14}\d)';
    $name = '\s*,\s*([^.]{2,120}?)\s*\.';

    if (sms_rx('vous avez re[çc]u', $text) && !sms_rx('vous avez (?:transf|envoy|pay|retir)', $text)) {
        $r['kind'] = 'in';
        $r['amount'] = parse_amount(sms_get('re[çc]u\D{0,30}?' . SMS_AMOUNT, $text));
        $r['phone'] = sms_get('\b(?:du|de)\s*(?:num[ée]ro\s*)?' . $phone, $text);
        $r['counterpart'] = sms_get('\b(?:du|de)\s*(?:num[ée]ro\s*)?\+?\d[\d ]{6,14}\d' . $name, $text);
    } elseif (sms_rx('vous avez transf[ée]r[ée]|transfert\s+de\s+\d', $text)) {
        $r['kind'] = 'out';
        $r['amount'] = parse_amount(sms_get('transf[ée]r[ée]\D{0,30}?' . SMS_AMOUNT, $text))
            ?? parse_amount(sms_get(SMS_AMOUNT, $text));
        $r['phone'] = sms_get('\b(?:au|vers\s+le|vers)\s*(?:num[ée]ro\s*)?' . $phone, $text);
        $r['counterpart'] = sms_get('\b(?:au|vers)\s*(?:num[ée]ro\s*)?\+?\d[\d ]{6,14}\d' . $name, $text);
    } elseif (sms_rx('retrait|pay[ée]|d[ée]bit|envoy', $text)) {
        $r['kind'] = 'other';
        $r['amount'] = parse_amount(sms_get(SMS_AMOUNT, $text));
    }
    if ($r['phone'] !== null) {
        $r['phone'] = phone_key($r['phone']);
    }
    if (in_array($r['kind'], ['in', 'out'], true) && ($r['amount'] === null || $r['amount'] <= 0)) {
        $r['kind'] = null; // montant illisible : pas exploitable
    }
    return $r;
}

/**
 * Enregistre un SMS transmis par le téléphone et met à jour le grand livre.
 * @return array{id:int,status:string,operation:?int}
 */
function sms_ingest(?array $device, string $sender, string $body, ?int $receivedAtMs = null): array
{
    $sender = mb_substr(trim($sender), 0, 60);
    $body = trim($body);
    $receivedAt = $receivedAtMs ? gmdate('Y-m-d H:i:s', intdiv($receivedAtMs, 1000)) : now();
    $fingerprint = hash('sha256', ($device['id'] ?? 0) . '|' . $sender . '|' . $body . '|' . ($receivedAtMs ?? $receivedAt));

    return db_tx(function () use ($device, $sender, $body, $receivedAt, $fingerprint) {
        $existing = q_one('SELECT id, status, operation_id FROM sms_messages WHERE fingerprint = ?', [$fingerprint]);
        if ($existing) {
            return ['id' => (int)$existing['id'], 'status' => 'already_received', 'operation' => null];
        }
        $p = sms_parse($sender, $body);
        $dist = $device['distributor_id'] ?? null; // null = téléphone de la plateforme (abonnements)
        $row = [
            'distributor_id' => $dist,
            'device_id' => $device['id'] ?? null,
            'sender' => $sender,
            'body' => $body,
            'received_at' => $receivedAt,
            'fingerprint' => $fingerprint,
            'kind' => $p['kind'],
            'amount' => $p['amount'],
            'phone' => $p['phone'],
            'counterpart' => $p['counterpart'] !== null ? mb_substr($p['counterpart'], 0, 190) : null,
            'txn_id' => $p['txn'],
            'balance' => $p['balance'],
            'status' => 'ignored',
            'note' => null,
            'operation_id' => null,
            'created_at' => now(),
        ];

        if ($p['kind'] === null) {
            $row['note'] = 'Pas une opération reconnue';
            $id = db_insert('sms_messages', $row);
            return ['id' => $id, 'status' => 'ignored', 'operation' => null];
        }

        // Anti-doublon : un même ID de transaction n'est compté qu'une fois.
        if ($p['txn'] && in_array($p['kind'], ['in', 'out'], true)) {
            $dup = $dist === null
                ? q_one("SELECT id FROM sms_messages WHERE txn_id = ? AND kind IN ('in','out') AND distributor_id IS NULL AND status IN ('ok','review','unassigned')", [$p['txn']])
                : q_one("SELECT id FROM sms_messages WHERE txn_id = ? AND kind IN ('in','out') AND distributor_id = ? AND status IN ('ok','review','unassigned')", [$p['txn'], $dist]);
            if ($dup) {
                $row['status'] = 'duplicate';
                $row['note'] = 'Transaction déjà enregistrée (SMS #' . $dup['id'] . ')';
                $id = db_insert('sms_messages', $row);
                return ['id' => $id, 'status' => 'duplicate', 'operation' => null];
            }
        }

        // Contrôle du solde : nouveau solde = ancien ± montant (bloque les faux SMS).
        $balanceOk = sms_balance_ok($device, $p);
        if ($device && $p['balance'] !== null && ($balanceOk || $p['kind'] !== 'in')) {
            db_update('devices', ['last_balance' => $p['balance']], 'id = :id', ['id' => $device['id']]);
        }

        if ($p['kind'] === 'other') {
            $row['status'] = 'info';
            $row['note'] = 'Autre opération : solde mis à jour';
            $id = db_insert('sms_messages', $row);
            return ['id' => $id, 'status' => 'info', 'operation' => null];
        }

        $row['status'] = 'pending';
        $smsId = db_insert('sms_messages', $row);
        $sms = q_one('SELECT * FROM sms_messages WHERE id = ?', [$smsId]);
        if ($dist === null) {
            // Téléphone de la plateforme : l'argent reçu = paiement d'abonnement d'un distributeur.
            $status = $p['kind'] === 'in' ? subscription_from_sms($sms, $balanceOk) : 'info';
            db_update('sms_messages', ['status' => $status], 'id = :id', ['id' => $smsId]);
            return ['id' => $smsId, 'status' => $status, 'operation' => null];
        }
        $op = ledger_from_sms($sms, $balanceOk);
        db_update('sms_messages', ['status' => $op['status'], 'operation_id' => $op['id'], 'note' => $balanceOk ? null : 'Solde incohérent : à vérifier'], 'id = :id', ['id' => $smsId]);
        return ['id' => $smsId, 'status' => $op['status'], 'operation' => (int)$op['id']];
    });
}

function sms_balance_ok(?array $device, array $p): bool
{
    $check = has_did() ? (string)dsetting('balance_check') : '1';
    if ($check !== '1' || !$device || $device['last_balance'] === null || $p['balance'] === null || $p['amount'] === null) {
        return true;
    }
    $before = (float)$device['last_balance'];
    $after = (float)$p['balance'];
    $amount = (float)$p['amount'];
    if ($p['kind'] === 'in') {
        return abs($before + $amount - $after) < 1;
    }
    if ($p['kind'] === 'out') {
        // Un transfert peut avoir des frais : jusqu'à 3 % de plus que le montant.
        $spent = $before - $after;
        return $spent >= $amount - 1 && $spent <= $amount * 1.03 + 1;
    }
    return true;
}

/**
 * Paiement d'abonnement reçu sur le numéro de la plateforme.
 * Le distributeur est reconnu par son numéro ; le montant donne la formule et le nombre de mois
 * (ex. 25 000 F = 1 mois Pro, 30 000 F = 3 mois Starter). Retourne le statut du SMS.
 */
function subscription_from_sms(array $sms, bool $balanceOk): string
{
    $amount = (float)$sms['amount'];
    $d = $sms['phone'] ? q_one('SELECT * FROM distributors WHERE phone = ?', [phone_key($sms['phone'])]) : null;
    $row = [
        'distributor_id' => $d['id'] ?? null,
        'amount' => $amount,
        'months' => 0,
        'plan' => null,
        'source' => 'sms',
        'phone' => $sms['phone'],
        'txn_id' => $sms['txn_id'],
        'status' => 'ok',
        'note' => null,
        'created_at' => now(),
    ];
    if (!$balanceOk) {
        $row['status'] = 'review';
        $row['note'] = 'Solde incohérent : à vérifier avant de prolonger';
    } elseif (!$d) {
        $row['status'] = 'unassigned';
        $row['note'] = 'Numéro inconnu' . ($sms['counterpart'] ? ' (' . $sms['counterpart'] . ')' : '');
    } else {
        [$plan, $months] = subscription_plan_for($d, $amount);
        if ($months < 1) {
            $row['status'] = 'review';
            $row['note'] = 'Montant insuffisant pour un mois';
        } else {
            $row['plan'] = $plan;
            $row['months'] = $months;
            dist_extend((int)$d['id'], $months, $plan);
        }
    }
    db_insert('subscription_payments', $row);
    return $row['status'] === 'ok' ? 'ok' : ($row['status'] === 'unassigned' ? 'unassigned' : 'review');
}

/** Formule et nombre de mois correspondant à un montant payé. */
function subscription_plan_for(array $d, float $amount): array
{
    $plans = plans();
    // Multiple exact du prix de la formule actuelle : on la prolonge.
    if (isset($plans[$d['plan']]) && $plans[$d['plan']]['price'] > 0 && fmod($amount, (float)$plans[$d['plan']]['price']) == 0.0) {
        return [$d['plan'], (int)($amount / $plans[$d['plan']]['price'])];
    }
    // Sinon multiple exact d'une autre formule : la plus chère qui correspond (changement de formule).
    $byPrice = $plans;
    uasort($byPrice, fn($a, $b) => $b['price'] <=> $a['price']);
    foreach ($byPrice as $key => $p) {
        if ($p['price'] > 0 && fmod($amount, (float)$p['price']) == 0.0) {
            return [$key, (int)($amount / $p['price'])];
        }
    }
    $current = isset($plans[$d['plan']]) ? $d['plan'] : 'starter';
    $price = (float)$plans[$current]['price'];
    return [$current, $price > 0 ? (int)floor($amount / $price) : 0];
}
