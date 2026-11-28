<?php
declare(strict_types=1);

/*
 * Grand livre des agents.
 *  depot        : le distributeur a envoyé de l'argent à l'agent  -> la dette augmente
 *  remb_om      : l'agent a remboursé par Orange Money            -> la dette baisse
 *  remb_cash    : l'agent a remboursé en espèces                  -> la dette baisse
 *  ajust_plus / ajust_moins : correction manuelle
 * Statuts : ok (compté), review (solde incohérent, à vérifier), unassigned (numéro inconnu),
 *           ignored (pas une opération d'agent), rejected (faux SMS), cancelled (annulée).
 */

const OP_KINDS = [
    'depot' => ['Dépôt envoyé', 1],
    'remb_om' => ['Remboursement Mobile Money', -1],
    'remb_cash' => ['Remboursement cash', -1],
    'ajust_plus' => ['Correction (+)', 1],
    'ajust_moins' => ['Correction (−)', -1],
];

function op_label(string $kind): string
{
    return OP_KINDS[$kind][0] ?? $kind;
}

function op_sign(string $kind): int
{
    return OP_KINDS[$kind][1] ?? 0;
}

function op_status_label(string $s): string
{
    return [
        'ok' => 'Validée', 'review' => 'À vérifier', 'unassigned' => 'À attribuer', 'ignored' => 'Ignorée',
        'rejected' => 'Rejetée', 'cancelled' => 'Annulée',
    ][$s] ?? $s;
}

function req_status_label(string $s): string
{
    return [
        'pending' => 'En attente', 'approved' => 'Validée, à envoyer', 'sent' => 'Envoyée',
        'rejected' => 'Refusée', 'cancelled' => 'Annulée',
    ][$s] ?? $s;
}

function badge(string $s, string $label): string
{
    return '<span class="badge b-' . h($s) . '">' . h($label) . '</span>';
}

/* ------------------------------------------------------------------ Agents */

function agent_find(int $id): ?array
{
    return q_one('SELECT * FROM agents WHERE id = ? AND distributor_id = ?', [$id, did()]);
}

function agent_by_phone(?string $phone): ?array
{
    $k = phone_key($phone);
    if ($k === null) {
        return null;
    }
    return q_one('SELECT a.* FROM agents a JOIN agent_phones p ON p.agent_id = a.id WHERE p.phone_key = ? AND p.distributor_id = ?', [$k, did()]);
}

/** Numéro principal de l'agent par opérateur : ['orange' => '64318090', 'moov' => '70445566']. */
function agent_main_phones(int $agentId): array
{
    $out = [];
    foreach (q_all('SELECT phone_key, operator, main FROM agent_phones WHERE agent_id = ? AND distributor_id = ? ORDER BY main DESC, id', [$agentId, did()]) as $r) {
        if ((int)$r['main'] === 1 && !isset($out[$r['operator']])) {
            $out[$r['operator']] = $r['phone_key'];
        }
    }
    return $out;
}

/** Opérateurs utilisés par l'agent (choisis par le distributeur), dans l'ordre Orange, Moov. */
function agent_operators(int $agentId): array
{
    $mine = agent_main_phones($agentId);
    $ops = array_values(array_filter(array_keys(mm_operators()), fn($op) => isset($mine[$op])));
    return $ops ?: ['orange'];
}

function agent_phones(int $agentId): array
{
    return array_column(q_all('SELECT phone_key FROM agent_phones WHERE agent_id = ? AND distributor_id = ? ORDER BY id', [$agentId, did()]), 'phone_key');
}

/** Ajoute un numéro à un agent (opérateur deviné au préfixe si non précisé). Retourne un message d'erreur ou null. */
function agent_add_phone(int $agentId, string $phone, ?string $operator = null, bool $main = false): ?string
{
    $k = phone_key($phone);
    if ($k === null) {
        return 'Numéro invalide (8 chiffres attendus).';
    }
    $owner = q_one('SELECT a.id, a.name FROM agent_phones p JOIN agents a ON a.id = p.agent_id WHERE p.phone_key = ? AND p.distributor_id = ?', [$k, did()]);
    if ($owner && (int)$owner['id'] !== $agentId) {
        return 'Le numéro ' . fmt_phone($k) . ' appartient déjà à ' . $owner['name'] . '.';
    }
    if (!$owner) {
        db_insert('agent_phones', [
            'distributor_id' => did(), 'agent_id' => $agentId, 'phone_key' => $k,
            'operator' => isset(mm_operators()[$operator ?? '']) ? $operator : (phone_operator($k) ?? 'orange'),
            'main' => $main ? 1 : 0, 'created_at' => now(),
        ]);
    }
    return null;
}

/** SQL du solde dû (dépôts − remboursements) des opérations validées. */
function balance_sql(string $alias = 'o'): string
{
    $plus = "'depot','ajust_plus'";
    return "COALESCE(SUM(CASE WHEN $alias.status = 'ok' AND $alias.kind IN ($plus) THEN $alias.amount "
        . "WHEN $alias.status = 'ok' THEN -$alias.amount ELSE 0 END), 0)";
}

function agent_balance(int $agentId): float
{
    return (float)q_val('SELECT ' . balance_sql() . ' FROM operations o WHERE o.agent_id = ? AND o.distributor_id = ?', [$agentId, did()]);
}

/** Liste des agents avec leur solde dû et leur dernière opération. */
function agents_with_balance(string $search = '', string $order = 'due'): array
{
    $params = [did()];
    $where = 'a.distributor_id = ?';
    if ($search !== '') {
        $where .= ' AND (a.name LIKE ? OR a.login_phone LIKE ? OR a.location LIKE ? OR a.id IN (SELECT agent_id FROM agent_phones WHERE phone_key LIKE ?))';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $orderSql = $order === 'name' ? 'a.name ASC' : 'due DESC, a.name ASC';
    return q_all(
        'SELECT a.*, ' . balance_sql() . ' AS due, MAX(CASE WHEN o.status = \'ok\' THEN o.occurred_at END) AS last_op_at
         FROM agents a LEFT JOIN operations o ON o.agent_id = a.id
         WHERE ' . $where . ' GROUP BY a.id ORDER BY ' . $orderSql,
        $params
    );
}

/** Relevé d'un agent : opérations (plus récentes d'abord) avec le solde après chacune. */
function agent_statement(int $agentId, int $limit = 200): array
{
    $ops = q_all('SELECT * FROM operations WHERE agent_id = ? AND distributor_id = ? ORDER BY occurred_at ASC, id ASC', [$agentId, did()]);
    $run = 0.0;
    foreach ($ops as &$o) {
        if ($o['status'] === 'ok') {
            $run += op_sign($o['kind']) * (float)$o['amount'];
        }
        $o['balance_after'] = $run;
    }
    unset($o);
    return array_slice(array_reverse($ops), 0, $limit);
}

/* ------------------------------------------------------------------ Opérations */

function op_find(int $id): ?array
{
    return q_one('SELECT o.*, a.name AS agent_name FROM operations o LEFT JOIN agents a ON a.id = o.agent_id WHERE o.id = ? AND o.distributor_id = ?', [$id, did()]);
}

function op_create(array $data): int
{
    $data += [
        'distributor_id' => did(),
        'agent_id' => null, 'operator' => 'orange', 'status' => 'ok', 'source' => 'manual', 'phone' => null, 'counterpart' => null,
        'txn_id' => null, 'sms_id' => null, 'request_id' => null, 'note' => null, 'created_by' => null,
        'occurred_at' => now(), 'created_at' => now(),
    ];
    return db_insert('operations', $data);
}

/** Crée l'opération correspondant à un SMS (dépôt ou remboursement) et rapproche la demande. */
function ledger_from_sms(array $sms, bool $balanceOk): array
{
    $kind = $sms['kind'] === 'out' ? 'depot' : 'remb_om';
    $agent = agent_by_phone($sms['phone']);
    $amount = (float)$sms['amount'];
    $status = !$balanceOk ? 'review' : ($agent ? 'ok' : 'unassigned');

    // Dépôt déjà saisi à la main (SMS arrivé en retard) : on le complète au lieu de le compter deux fois.
    if ($agent && $kind === 'depot') {
        $manual = q_one(
            "SELECT * FROM operations WHERE agent_id = ? AND kind = 'depot' AND status = 'ok' AND sms_id IS NULL AND source = 'manual'
             AND amount = ? AND occurred_at >= ? AND distributor_id = ? ORDER BY occurred_at ASC LIMIT 1",
            [$agent['id'], $amount, gmdate('Y-m-d H:i:s', time() - 2 * 86400), did()]
        );
        if ($manual) {
            db_update('operations', [
                'sms_id' => $sms['id'], 'txn_id' => $sms['txn_id'], 'phone' => $sms['phone'],
                'counterpart' => $sms['counterpart'], 'source' => 'sms',
            ], 'id = :id', ['id' => $manual['id']]);
            return ['id' => (int)$manual['id'], 'status' => 'ok'];
        }
    }

    $opId = op_create([
        'agent_id' => $agent['id'] ?? null,
        'kind' => $kind,
        'operator' => $sms['operator'] ?: 'orange',
        'amount' => $amount,
        'status' => $status,
        'source' => 'sms',
        'phone' => $sms['phone'],
        'counterpart' => $sms['counterpart'],
        'txn_id' => $sms['txn_id'],
        'sms_id' => $sms['id'],
        'occurred_at' => $sms['received_at'],
    ]);
    if ($status === 'ok' && $kind === 'depot') {
        request_link_deposit($opId);
    }
    notify_op($opId, true);
    return ['id' => $opId, 'status' => $status];
}

/** Rattache un dépôt à la demande en cours de l'agent (même montant, la plus ancienne). */
function request_link_deposit(int $opId): void
{
    $op = op_find($opId);
    if (!$op || $op['kind'] !== 'depot' || !$op['agent_id'] || $op['request_id']) {
        return;
    }
    $req = q_one(
        "SELECT * FROM deposit_requests WHERE agent_id = ? AND distributor_id = ? AND status IN ('approved','pending') AND operation_id IS NULL AND amount = ?
         AND (operator IS NULL OR operator = ?)
         ORDER BY CASE WHEN status = 'approved' THEN 0 ELSE 1 END, created_at ASC LIMIT 1",
        [$op['agent_id'], did(), $op['amount'], $op['operator'] ?? 'orange']
    );
    if (!$req) {
        return;
    }
    db_update('deposit_requests', [
        'status' => 'sent', 'operation_id' => $opId, 'sent_at' => now(),
        'decided_at' => $req['decided_at'] ?? now(),
    ], 'id = :id', ['id' => $req['id']]);
    db_update('operations', ['request_id' => $req['id']], 'id = :id', ['id' => $opId]);
}

/** Attribue une opération « à attribuer » à un agent (et retient le numéro si demandé). */
function op_assign(int $opId, int $agentId, bool $rememberPhone): ?string
{
    $op = op_find($opId);
    $agent = agent_find($agentId);
    if (!$op || !$agent) {
        return 'Opération ou agent introuvable.';
    }
    if (!in_array($op['status'], ['unassigned', 'review', 'ignored'], true) && $op['agent_id']) {
        return 'Cette opération est déjà attribuée.';
    }
    if ($rememberPhone && $op['phone']) {
        // Numéro retenu avec l'opérateur du SMS ; il devient le numéro principal de cet opérateur s'il n'y en avait pas.
        $opr = $op['operator'] ?? 'orange';
        $err = agent_add_phone($agentId, $op['phone'], $opr, !isset(agent_main_phones($agentId)[$opr]));
        if ($err) {
            return $err;
        }
    }
    db_update('operations', ['agent_id' => $agentId, 'status' => 'ok'], 'id = :id', ['id' => $opId]);
    if ($op['sms_id']) {
        q("UPDATE sms_messages SET status = 'ok' WHERE id = ?", [$op['sms_id']]);
    }
    request_link_deposit($opId);
    // Les autres opérations du même numéro encore « à attribuer » suivent.
    if ($rememberPhone && $op['phone']) {
        foreach (q_all("SELECT id FROM operations WHERE status = 'unassigned' AND phone = ? AND id <> ? AND distributor_id = ?", [$op['phone'], $opId, did()]) as $o) {
            db_update('operations', ['agent_id' => $agentId, 'status' => 'ok'], 'id = :id', ['id' => $o['id']]);
            request_link_deposit((int)$o['id']);
            notify_op((int)$o['id'], false);
        }
    }
    notify_op($opId, false);
    return null;
}

function op_set_status(int $opId, string $status, ?string $note = null): void
{
    $data = ['status' => $status];
    if ($note !== null && $note !== '') {
        $data['note'] = mb_substr($note, 0, 255);
    } elseif (in_array($status, ['ok', 'unassigned'], true)) {
        $data['note'] = null; // rétablie : l'ancien motif d'annulation n'a plus lieu d'être
    }
    db_update('operations', $data, 'id = :id AND distributor_id = :d', ['id' => $opId, 'd' => did()]);
    $op = op_find($opId);
    if ($op && $op['sms_id']) {
        q('UPDATE sms_messages SET status = ? WHERE id = ?', [$status, $op['sms_id']]);
    }
    // Un dépôt annulé libère la demande liée.
    if ($op && $status !== 'ok' && $op['request_id']) {
        q("UPDATE deposit_requests SET status = 'approved', operation_id = NULL, sent_at = NULL WHERE id = ? AND operation_id = ?", [$op['request_id'], $opId]);
        db_update('operations', ['request_id' => null], 'id = :id', ['id' => $opId]);
    }
    if ($op && $status === 'ok') {
        request_link_deposit($opId);
    }
}

/* ------------------------------------------------------------------ Demandes */

function request_find(int $id): ?array
{
    return q_one('SELECT r.*, a.name AS agent_name, a.login_phone FROM deposit_requests r JOIN agents a ON a.id = r.agent_id WHERE r.id = ? AND r.distributor_id = ?', [$id, did()]);
}

function request_create(int $agentId, float $amount, string $note, ?string $operator = null): int
{
    $id = db_insert('deposit_requests', [
        'distributor_id' => did(),
        'reference' => new_reference('D'),
        'agent_id' => $agentId,
        'operator' => isset(mm_operators()[$operator ?? '']) ? $operator : null,
        'amount' => $amount,
        'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
        'status' => 'pending',
        'created_at' => now(),
    ]);
    notify_new_request($id);
    return $id;
}

/** Numéro où envoyer le dépôt : le premier numéro Orange Money de l'agent. */
function request_target_phone(array $req): string
{
    // Numéro principal de l'agent pour l'opérateur demandé (Orange ou Moov).
    $main = agent_main_phones((int)$req['agent_id']);
    $op = $req['operator'] ?? null;
    if ($op && isset($main[$op])) {
        return $main[$op];
    }
    return reset($main) ?: ((agent_phones((int)$req['agent_id'])[0] ?? null) ?: (string)$req['login_phone']);
}

/** Opérateurs activés par le distributeur (Orange Money par défaut). */
function dist_operators(): array
{
    $ops = array_values(array_intersect(explode(',', (string)dsetting('operators', 'orange')), array_keys(mm_operators())));
    return $ops ?: ['orange'];
}

/* ------------------------------------------------------------------ Tableau de bord */

function dashboard_stats(): array
{
    $d = did();
    $today = today_start_utc();
    $sum = fn(string $kinds) => (float)q_val("SELECT COALESCE(SUM(amount),0) FROM operations WHERE distributor_id = ? AND status = 'ok' AND kind IN ($kinds) AND occurred_at >= ?", [$d, $today]);
    return [
        // Somme des dettes (les avances d'un agent ne réduisent pas la dette d'un autre).
        'total_due' => (float)q_val('SELECT COALESCE(SUM(due), 0) FROM (SELECT ' . balance_sql() . ' AS due FROM operations o WHERE o.distributor_id = ? AND o.agent_id IS NOT NULL GROUP BY o.agent_id) t WHERE due > 0', [$d]),
        'agents_due' => (int)q_val('SELECT COUNT(*) FROM (SELECT o.agent_id FROM operations o WHERE o.distributor_id = ? AND o.agent_id IS NOT NULL GROUP BY o.agent_id HAVING ' . balance_sql() . ' > 0) t', [$d]),
        'depots_today' => $sum("'depot'"),
        'remb_om_today' => $sum("'remb_om'"),
        'remb_cash_today' => $sum("'remb_cash'"),
        'pending_requests' => (int)q_val("SELECT COUNT(*) FROM deposit_requests WHERE distributor_id = ? AND status IN ('pending','approved')", [$d]),
        'unassigned' => (int)q_val("SELECT COUNT(*) FROM operations WHERE distributor_id = ? AND status = 'unassigned'", [$d]),
        'review' => (int)q_val("SELECT COUNT(*) FROM operations WHERE distributor_id = ? AND status = 'review'", [$d]),
    ];
}
