<?php
declare(strict_types=1);

/* Espace distributeur : /admin/... */

function admin_user(): ?array
{
    start_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $u = null;
    $u ??= q_one('SELECT id, email, role, distributor_id FROM users WHERE id = ?', [$_SESSION['uid']]);
    return $u ?: null;
}

/** Super-admin qui consulte l'espace d'un distributeur (assistance). */
function admin_impersonating(): bool
{
    $u = admin_user();
    return $u && $u['role'] === 'superadmin' && !empty($_SESSION['as_did']);
}

/** Actions autorisées quand l'abonnement a expiré (le reste est en lecture seule). */
function admin_paywall(string $a, string $method): void
{
    if ($method !== 'POST' || dist_active(distributor()) || admin_impersonating()) {
        return;
    }
    if (in_array($a, ['settings', 'push', 'subscription', 'logout'], true)) {
        return;
    }
    flash('Votre abonnement a expiré : renouvelez-le pour continuer à valider des demandes et à enregistrer des opérations. Vos données sont conservées.', 'err');
    redirect('admin/subscription');
}

function admin_page(string $view, string $title, array $vars = []): void
{
    $vars['title'] = $title;
    $vars['area'] = 'admin';
    $vars['stats'] ??= dashboard_stats();
    view('layout_top', $vars);
    view($view, $vars);
    view('layout_bottom', $vars);
}

function admin_route(string $method, array $p): void
{
    $a = $p[0] ?? '';

    if ($a === 'login') {
        if (admin_user()) {
            redirect('admin');
        }
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            if (login_blocked('admin:' . $email)) {
                $error = 'Trop d\'essais. Réessayez dans 15 minutes.';
            } else {
                $u = q_one('SELECT * FROM users WHERE LOWER(email) = ?', [$email]);
                if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['uid'] = (int)$u['id'];
                    db_update('users', ['last_login_at' => now()], 'id = :id', ['id' => $u['id']]);
                    unset($_SESSION['as_did']);
                    redirect($u['role'] === 'superadmin' ? 'superadmin' : 'admin');
                }
                login_failed('admin:' . $email);
                $error = 'Email ou mot de passe incorrect.';
            }
        }
        view('login', ['area' => 'admin', 'error' => $error]);
        return;
    }

    $user = admin_user();
    if (!$user) {
        redirect('admin/login');
    }
    if ($user['role'] === 'superadmin') {
        if (empty($_SESSION['as_did'])) {
            redirect('superadmin');
        }
        set_did((int)$_SESSION['as_did']);
    } else {
        set_did((int)$user['distributor_id']);
    }
    if (!distributor()) {
        unset($_SESSION['uid'], $_SESSION['as_did']);
        redirect('admin/login');
    }
    if ($method === 'POST') {
        csrf_check();
    }
    if (distributor()['status'] === 'suspended' && !admin_impersonating() && $a !== 'logout') {
        view('layout_top', ['title' => 'Compte suspendu', 'area' => 'public']);
        view('message', ['text' => 'Ce compte est suspendu. Contactez le support FlashDépôt' . (setting('support_whatsapp') ? ' sur WhatsApp au ' . setting('support_whatsapp') : '') . '. Vos données sont conservées.']);
        view('layout_bottom', ['area' => 'public']);
        return;
    }
    admin_paywall($a, $method);

    switch ($a) {
        case '':
            admin_page('admin_dashboard', 'Tableau de bord', [
                'requests' => q_all("SELECT r.*, a.name AS agent_name FROM deposit_requests r JOIN agents a ON a.id = r.agent_id WHERE r.distributor_id = ? AND r.status IN ('pending','approved') ORDER BY r.created_at ASC LIMIT 20", [did()]),
                'recent' => q_all('SELECT o.*, a.name AS agent_name FROM operations o LEFT JOIN agents a ON a.id = o.agent_id WHERE o.distributor_id = ? ORDER BY o.occurred_at DESC, o.id DESC LIMIT 12', [did()]),
                'device' => q_one('SELECT * FROM devices WHERE distributor_id = ? AND active = 1 AND token_hash IS NOT NULL ORDER BY last_seen_at DESC LIMIT 1', [did()]),
                'top' => array_slice(array_filter(agents_with_balance(), fn($r) => (float)$r['due'] > 0), 0, 8),
            ]);
            return;

        case 'logout':
            start_session();
            if (admin_impersonating()) {
                unset($_SESSION['as_did']); // le super-admin revient à son espace
                redirect('superadmin');
            }
            unset($_SESSION['uid']);
            redirect('admin/login');

        case 'subscription':
            admin_page('admin_subscription', 'Abonnement', [
                'payments' => q_all('SELECT * FROM subscription_payments WHERE distributor_id = ? ORDER BY id DESC LIMIT 24', [did()]),
                'agents_count' => (int)q_val('SELECT COUNT(*) FROM agents WHERE distributor_id = ? AND active = 1', [did()]),
            ]);
            return;

        case 'push':
            if ($method !== 'POST') {
                redirect('admin');
            }
            push_handle(null, (int)$user['id']);

        case 'poll':
            $s = dashboard_stats();
            json_out([
                'pending' => $s['pending_requests'],
                'unassigned' => $s['unassigned'],
                'review' => $s['review'],
                'last_op' => (int)q_val('SELECT COALESCE(MAX(id),0) FROM operations WHERE distributor_id = ?', [did()]),
                'last_req' => (int)q_val('SELECT COALESCE(MAX(id),0) FROM deposit_requests WHERE distributor_id = ?', [did()]),
            ]);

        case 'requests':
            admin_requests($method, array_slice($p, 1));
            return;

        case 'agents':
            admin_agents($method, array_slice($p, 1));
            return;

        case 'operations':
            admin_operations($method, array_slice($p, 1));
            return;

        case 'sms':
            $test = null;
            if ($method === 'POST') {
                $test = sms_parse((string)($_POST['sender'] ?? 'OrangeMoney'), (string)($_POST['body'] ?? ''));
            }
            admin_page('admin_sms', 'SMS reçus', [
                'test' => $test,
                'rows' => q_all('SELECT * FROM sms_messages WHERE distributor_id = ? ORDER BY id DESC LIMIT 100', [did()]),
            ]);
            return;

        case 'device':
            admin_device($method, array_slice($p, 1));
            return;

        case 'settings':
            admin_settings($method, $user);
            return;
    }
    http_response_code(404);
    admin_page('message', 'Page introuvable', ['text' => 'Cette page n\'existe pas.']);
}

/* ------------------------------------------------------------------ Demandes */

function admin_requests(string $method, array $p): void
{
    if ($method === 'POST' && isset($p[0], $p[1])) {
        $r = request_find((int)$p[0]);
        if (!$r) {
            flash('Demande introuvable.', 'err');
            redirect_back('admin/requests');
        }
        switch ($p[1]) {
            case 'approve':
                if ($r['status'] === 'pending') {
                    db_update('deposit_requests', ['status' => 'approved', 'decided_at' => now()], 'id = :id', ['id' => $r['id']]);
                    notify_request_status((int)$r['id']);
                    flash('Demande validée. Faites le transfert : il sera détecté automatiquement par SMS.');
                }
                break;
            case 'reject':
                if (in_array($r['status'], ['pending', 'approved'], true)) {
                    db_update('deposit_requests', [
                        'status' => 'rejected', 'decided_at' => now(),
                        'reject_reason' => mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255) ?: null,
                    ], 'id = :id', ['id' => $r['id']]);
                    notify_request_status((int)$r['id']);
                    flash('Demande refusée.');
                }
                break;
            case 'sent':
                // Le SMS n'est pas arrivé (ou envoi fait autrement) : on enregistre le dépôt à la main.
                if (in_array($r['status'], ['pending', 'approved'], true)) {
                    $opId = db_tx(function () use ($r) {
                        $opId = op_create([
                            'agent_id' => $r['agent_id'], 'kind' => 'depot', 'amount' => $r['amount'],
                            'note' => 'Saisi à la main depuis la demande ' . $r['reference'], 'created_by' => 'admin',
                        ]);
                        db_update('operations', ['request_id' => $r['id']], 'id = :id', ['id' => $opId]);
                        db_update('deposit_requests', ['status' => 'sent', 'operation_id' => $opId, 'sent_at' => now(), 'decided_at' => $r['decided_at'] ?? now()], 'id = :id', ['id' => $r['id']]);
                        return $opId;
                    });
                    notify_op($opId, false);
                    flash('Dépôt enregistré. Si le SMS arrive ensuite, il ne sera pas compté deux fois.');
                }
                break;
        }
        redirect_back('admin/requests');
    }

    $filter = (string)($_GET['f'] ?? 'active');
    $where = 'r.distributor_id = ' . did() . ($filter === 'all' ? '' : " AND r.status IN ('pending','approved')");
    admin_page('admin_requests', 'Demandes de dépôt', [
        'filter' => $filter,
        'rows' => q_all("SELECT r.*, a.name AS agent_name, a.login_phone FROM deposit_requests r JOIN agents a ON a.id = r.agent_id WHERE $where ORDER BY CASE WHEN r.status IN ('pending','approved') THEN 0 ELSE 1 END, r.created_at " . ($filter === 'all' ? 'DESC' : 'ASC') . ' LIMIT 200'),
    ]);
}

/* ------------------------------------------------------------------ Agents */

function admin_agent_form_save(?array $agent): array
{
    $name = trim((string)($_POST['name'] ?? ''));
    $pin = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
    $errors = [];
    if ($name === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    // Opérateurs utilisés par l'agent, choisis par le distributeur, avec un numéro pour chacun.
    $mains = [];
    $chosen = count(dist_operators()) > 1 ? (array)($_POST['ops'] ?? []) : dist_operators();
    foreach (dist_operators() as $op) {
        if (!in_array($op, $chosen, true)) {
            continue;
        }
        $k = phone_key((string)($_POST['phone_' . $op] ?? ''));
        if ($k === null) {
            $errors[] = 'Numéro ' . mm_name($op) . ' de l\'agent manquant ou invalide (8 chiffres).';
        } else {
            $mains[$op] = $k;
        }
    }
    // Numéros d'un opérateur que le distributeur a désactivé : conservés tels quels.
    if ($agent) {
        foreach (agent_main_phones((int)$agent['id']) as $op => $k) {
            if (!in_array($op, dist_operators(), true)) {
                $mains[$op] = $k;
            }
        }
    }
    if (!$mains && !$errors) {
        $errors[] = 'Cochez au moins un opérateur (Orange Money ou Moov Money).';
    }
    // L'agent se connecte avec son numéro Orange, ou Moov s'il n'a que Moov.
    $login = $mains['orange'] ?? (reset($mains) ?: null);
    if ($login !== null) {
        $other = q_one('SELECT id, name FROM agents WHERE login_phone = ? AND distributor_id = ?', [$login, did()]);
        if ($other && (!$agent || (int)$other['id'] !== (int)$agent['id'])) {
            $errors[] = 'Ce numéro de connexion est déjà utilisé par ' . $other['name'] . '.';
        }
    }
    if (!$agent && strlen($pin) < 4) {
        $errors[] = 'Le code PIN doit avoir au moins 4 chiffres.';
    }
    if ($pin !== '' && strlen($pin) < 4) {
        $errors[] = 'Le code PIN doit avoir au moins 4 chiffres.';
    }
    $extra = array_filter(array_map('trim', preg_split('/[\n,;]+/', (string)($_POST['phones'] ?? ''))));
    foreach (array_merge(array_values($mains), $extra) as $ph) {
        $k = phone_key($ph);
        if ($k === null) {
            $errors[] = 'Numéro invalide : ' . $ph;
            continue;
        }
        $owner = q_one('SELECT a.id, a.name FROM agent_phones p JOIN agents a ON a.id = p.agent_id WHERE p.phone_key = ? AND p.distributor_id = ?', [$k, did()]);
        if ($owner && (!$agent || (int)$owner['id'] !== (int)$agent['id'])) {
            $errors[] = 'Le numéro ' . fmt_phone($k) . ' appartient déjà à ' . $owner['name'] . '.';
        }
    }
    if ($errors) {
        return ['errors' => $errors];
    }
    $data = [
        'name' => mb_substr($name, 0, 190),
        'login_phone' => $login,
        'location' => mb_substr(trim((string)($_POST['location'] ?? '')), 0, 190) ?: null,
        'notes' => mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500) ?: null,
    ];
    if ($pin !== '') {
        $data['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
    }
    $id = db_tx(function () use ($agent, $data, $mains, $extra) {
        if ($agent) {
            db_update('agents', $data, 'id = :id AND distributor_id = :d', ['id' => $agent['id'], 'd' => did()]);
            $id = (int)$agent['id'];
            q('DELETE FROM agent_phones WHERE agent_id = ? AND distributor_id = ?', [$id, did()]);
        } else {
            $id = db_insert('agents', $data + ['distributor_id' => did(), 'active' => 1, 'created_at' => now()]);
        }
        foreach ($mains as $op => $k) {
            agent_add_phone($id, $k, $op, true); // numéro principal de chaque opérateur
        }
        foreach ($extra as $ph) {
            agent_add_phone($id, $ph);
        }
        // Les opérations « à attribuer » de ces numéros reviennent à l'agent.
        foreach (agent_phones($id) as $k) {
            foreach (q_all("SELECT id FROM operations WHERE status = 'unassigned' AND phone = ? AND distributor_id = ?", [$k, did()]) as $o) {
                db_update('operations', ['agent_id' => $id, 'status' => 'ok'], 'id = :id', ['id' => $o['id']]);
                request_link_deposit((int)$o['id']);
            }
        }
        return $id;
    });
    return ['id' => $id];
}

function admin_agents(string $method, array $p): void
{
    $sub = $p[0] ?? '';

    if ($sub === 'new') {
        $errors = [];
        if ($method === 'POST' && ($err = agent_limit_error())) {
            $errors[] = $err;
        } elseif ($method === 'POST') {
            $r = admin_agent_form_save(null);
            if (isset($r['id'])) {
                flash('Agent créé. Donnez-lui son numéro et son code PIN pour se connecter.');
                redirect('admin/agents/' . $r['id']);
            }
            $errors = $r['errors'];
        }
        admin_page('admin_agent_form', 'Nouvel agent', ['agent' => null, 'errors' => $errors, 'prefill' => [
            'name' => $_GET['name'] ?? '', 'login_phone' => $_GET['phone'] ?? '',
        ]]);
        return;
    }

    if ($sub !== '' && ctype_digit($sub)) {
        $agent = agent_find((int)$sub);
        if (!$agent) {
            http_response_code(404);
            admin_page('message', 'Agent introuvable', ['text' => 'Cet agent n\'existe pas.']);
            return;
        }
        $act = $p[1] ?? '';
        if ($act === 'edit') {
            $errors = [];
            if ($method === 'POST') {
                $r = admin_agent_form_save($agent);
                if (isset($r['id'])) {
                    flash('Agent enregistré.');
                    redirect('admin/agents/' . $agent['id']);
                }
                $errors = $r['errors'];
            }
            admin_page('admin_agent_form', 'Modifier l\'agent', ['agent' => $agent, 'errors' => $errors, 'prefill' => []]);
            return;
        }
        if ($method === 'POST') {
            $amount = input_amount($_POST['amount'] ?? '');
            $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
            $kinds = ['cash' => 'remb_cash', 'depot' => 'depot', 'plus' => 'ajust_plus', 'moins' => 'ajust_moins', 'om' => 'remb_om'];
            if (isset($kinds[$act])) {
                if (!$amount || $amount <= 0) {
                    flash('Montant invalide.', 'err');
                } else {
                    $opr = isset(mm_operators()[$_POST['operator'] ?? '']) ? $_POST['operator'] : 'orange';
                    $opId = op_create(['agent_id' => $agent['id'], 'kind' => $kinds[$act], 'operator' => $opr, 'amount' => $amount, 'note' => $note ?: null, 'created_by' => 'admin']);
                    notify_op($opId, false);
                    flash(op_label($kinds[$act]) . ' de ' . fmt_amount($amount) . ' enregistré. Reste à payer : ' . fmt_amount(agent_balance((int)$agent['id'])) . '.');
                }
            } elseif ($act === 'toggle') {
                if ((int)$agent['active'] !== 1 && ($err = agent_limit_error())) {
                    flash($err, 'err');
                    redirect('admin/agents/' . $agent['id']);
                }
                db_update('agents', ['active' => (int)$agent['active'] === 1 ? 0 : 1], 'id = :id AND distributor_id = :d', ['id' => $agent['id'], 'd' => did()]);
                flash((int)$agent['active'] === 1 ? 'Agent désactivé : il ne peut plus se connecter.' : 'Agent réactivé.');
            }
            redirect('admin/agents/' . $agent['id']);
        }
        admin_page('admin_agent', $agent['name'], [
            'agent' => $agent,
            'due' => agent_balance((int)$agent['id']),
            'phones' => agent_phones((int)$agent['id']),
            'ops' => agent_statement((int)$agent['id']),
            'requests' => q_all("SELECT * FROM deposit_requests WHERE agent_id = ? AND distributor_id = ? ORDER BY created_at DESC LIMIT 20", [$agent['id'], did()]),
        ]);
        return;
    }

    $search = trim((string)($_GET['q'] ?? ''));
    admin_page('admin_agents', 'Agents', [
        'search' => $search,
        'rows' => agents_with_balance($search, (string)($_GET['tri'] ?? 'due')),
    ]);
}

/* ------------------------------------------------------------------ Opérations */

function admin_operations(string $method, array $p): void
{
    if ($method === 'POST' && isset($p[0], $p[1])) {
        $op = op_find((int)$p[0]);
        if (!$op) {
            flash('Opération introuvable.', 'err');
            redirect_back('admin/operations');
        }
        $note = trim((string)($_POST['note'] ?? ''));
        switch ($p[1]) {
            case 'assign':
                $err = db_tx(fn() => op_assign((int)$op['id'], (int)($_POST['agent_id'] ?? 0), !empty($_POST['remember'])));
                flash($err ?? 'Opération attribuée.', $err ? 'err' : 'ok');
                break;
            case 'confirm': // SMS « à vérifier » reconnu comme vrai
                db_tx(fn() => op_set_status((int)$op['id'], $op['agent_id'] ? 'ok' : 'unassigned'));
                notify_op((int)$op['id'], false);
                flash($op['agent_id'] ? 'Opération validée.' : 'Opération confirmée : attribuez-la maintenant à un agent.');
                break;
            case 'reject':
                db_tx(fn() => op_set_status((int)$op['id'], 'rejected', $note ?: 'Rejetée par le distributeur'));
                flash('Opération rejetée : elle ne compte pas.');
                break;
            case 'ignore':
                db_tx(fn() => op_set_status((int)$op['id'], 'ignored', $note ?: null));
                flash('Opération ignorée (pas une opération d\'agent).');
                break;
            case 'cancel':
                db_tx(fn() => op_set_status((int)$op['id'], 'cancelled', $note ?: 'Annulée'));
                flash('Opération annulée.');
                break;
            case 'restore':
                db_tx(fn() => op_set_status((int)$op['id'], $op['agent_id'] ? 'ok' : 'unassigned'));
                flash('Opération rétablie.');
                break;
        }
        redirect_back('admin/operations');
    }

    $f = (string)($_GET['f'] ?? 'all');
    $where = match ($f) {
        'unassigned' => "o.status = 'unassigned'",
        'review' => "o.status = 'review'",
        'depot' => "o.kind = 'depot' AND o.status = 'ok'",
        'remb' => "o.kind IN ('remb_om','remb_cash') AND o.status = 'ok'",
        'cancelled' => "o.status IN ('cancelled','rejected','ignored')",
        default => '1=1',
    };
    $params = [];
    $day = (string)($_GET['jour'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        $start = new DateTime($day, local_tz());
        $start->setTimezone(new DateTimeZone('UTC'));
        $end = (clone $start)->modify('+1 day');
        $where .= ' AND o.occurred_at >= ? AND o.occurred_at < ?';
        $params = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }
    admin_page('admin_operations', 'Opérations', [
        'filter' => $f,
        'day' => $day,
        'rows' => q_all("SELECT o.*, a.name AS agent_name FROM operations o LEFT JOIN agents a ON a.id = o.agent_id WHERE o.distributor_id = ? AND $where ORDER BY o.occurred_at DESC, o.id DESC LIMIT 300", array_merge([did()], $params)),
        'agents' => q_all('SELECT id, name FROM agents WHERE distributor_id = ? AND active = 1 ORDER BY name', [did()]),
    ]);
}

/* ------------------------------------------------------------------ Téléphone */

function admin_device($method, array $p): void
{
    if ($method === 'POST') {
        $act = $p[0] ?? '';
        if ($act === 'pair') {
            $code = (string)random_int(100000, 999999);
            $dev = q_one('SELECT * FROM devices WHERE distributor_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [did()]);
            $data = ['pair_code' => $code, 'pair_expires_at' => ts_plus(30 * 60)];
            if ($dev) {
                db_update('devices', $data, 'id = :id', ['id' => $dev['id']]);
            } else {
                db_insert('devices', $data + ['distributor_id' => did(), 'name' => 'Téléphone du distributeur', 'active' => 1, 'created_at' => now()]);
            }
            flash('Code généré : saisissez-le dans l\'application dans les 30 minutes.');
        } elseif ($act === 'balance') {
            $amount = input_amount($_POST['balance'] ?? '');
            $op = isset(mm_operators()[$_POST['operator'] ?? '']) ? $_POST['operator'] : 'orange';
            $dev = q_one('SELECT id FROM devices WHERE distributor_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [did()]);
            if ($amount !== null && $dev) {
                device_set_balance((int)$dev['id'], $op, $amount);
                flash('Solde ' . mm_name($op) . ' enregistré.');
            }
        } elseif ($act === 'revoke') {
            q('UPDATE devices SET token_hash = NULL, pair_code = NULL WHERE active = 1 AND distributor_id = ?', [did()]);
            flash('Téléphone dissocié.');
        }
        redirect('admin/device');
    }
    admin_page('admin_device', 'Téléphone', [
        'device' => q_one('SELECT * FROM devices WHERE distributor_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [did()]),
    ]);
}

/* ------------------------------------------------------------------ Réglages */

function admin_settings(string $method, array $user): void
{
    $sub = $_POST['form'] ?? '';
    if ($method === 'POST') {
        if ($sub === 'general') {
            $name = mb_substr(trim((string)($_POST['dist_name'] ?? '')), 0, 120);
            // Numéros du distributeur par opérateur : ils activent l'opérateur (Moov s'active dès qu'un numéro Moov est rempli).
            $nums = [];
            foreach (array_keys(mm_operators()) as $op) {
                $raw = trim((string)($_POST['num_' . $op] ?? ''));
                $k = phone_key($raw);
                if ($raw !== '' && $k === null) {
                    flash('Numéro ' . mm_name($op) . ' invalide (8 chiffres).', 'err');
                    redirect('admin/settings');
                }
                if ($k !== null) {
                    $other = dist_find_by_phone($k);
                    if ($other && (int)$other['id'] !== did()) {
                        flash('Le numéro ' . fmt_phone($k) . ' est déjà utilisé par un autre compte distributeur.', 'err');
                        redirect('admin/settings');
                    }
                    $nums[$op] = $k;
                }
            }
            $phone = $nums['orange'] ?? ($nums['moov'] ?? distributor()['phone']);
            db_update('distributors', ['name' => $name ?: distributor()['name'], 'phone' => $phone], 'id = :id', ['id' => did()]);
            $GLOBALS['__dist'] = null;
            foreach (array_keys(mm_operators()) as $op) {
                dsetting_set('receive_' . $op, $nums[$op] ?? '');
            }
            dsetting_set('operators', implode(',', array_keys($nums) ?: ['orange']));
            dsetting_set('deposit_ussd', mb_substr(trim((string)($_POST['deposit_ussd'] ?? '')), 0, 100));
            if (isset($_POST['deposit_ussd_moov'])) {
                dsetting_set('deposit_ussd_moov', mb_substr(trim((string)$_POST['deposit_ussd_moov']), 0, 100));
            }
            dsetting_set('balance_check', !empty($_POST['balance_check']) ? '1' : '0');
            dsetting_set('min_request', (string)(int)(input_amount($_POST['min_request'] ?? '') ?? 0));
            flash('Réglages enregistrés.');
        } elseif ($sub === 'telegram') {
            dsetting_set('tg_token', trim((string)($_POST['tg_token'] ?? '')));
            dsetting_set('tg_chat_id', trim((string)($_POST['tg_chat_id'] ?? '')));
            if (!empty($_POST['test'])) {
                $err = tg_send('✅ ' . app_name() . ' : les notifications Telegram fonctionnent.');
                flash($err ? 'Telegram : ' . $err : 'Message de test envoyé sur Telegram.', $err ? 'err' : 'ok');
            } else {
                flash('Telegram enregistré.');
            }
        } elseif ($sub === 'password') {
            $u = q_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
                flash('Mot de passe actuel incorrect.', 'err');
            } elseif (strlen($new) < 8) {
                flash('Le nouveau mot de passe doit faire au moins 8 caractères.', 'err');
            } else {
                db_update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
                flash('Mot de passe changé.');
            }
        }
        redirect('admin/settings');
    }
    admin_page('admin_settings', 'Réglages', ['user' => $user]);
}

/** Message d'erreur si la formule ne permet pas d'agent actif de plus, sinon null. */
function agent_limit_error(): ?string
{
    $limit = plan_agent_limit(distributor());
    if ($limit <= 0) {
        return null;
    }
    $n = (int)q_val('SELECT COUNT(*) FROM agents WHERE distributor_id = ? AND active = 1', [did()]);
    if ($n < $limit) {
        return null;
    }
    return 'Votre formule ' . plan_label(distributor()['plan']) . ' permet ' . $limit . ' agents actifs. Passez à une formule supérieure (menu Abonnement) ou désactivez un agent.';
}
