<?php
declare(strict_types=1);

/*
 * Espace super-admin (propriétaire de la plateforme) : /superadmin/...
 * Gère les distributeurs, leurs abonnements et les réglages de la plateforme.
 */

function superadmin_user(): ?array
{
    $u = admin_user();
    return $u && $u['role'] === 'superadmin' ? $u : null;
}

function superadmin_page(string $view, string $title, array $vars = []): void
{
    $vars['title'] = $title;
    $vars['area'] = 'superadmin';
    view('layout_top', $vars);
    view($view, $vars);
    view('layout_bottom', $vars);
}

/** Clé qui prouve qu'on possède le serveur : le mot de passe MySQL de config.php (ou le secret de l'application). */
function superadmin_setup_key(): string
{
    $pass = (string)cfg('db.pass', '');
    return $pass !== '' ? $pass : (string)cfg('app_secret', '');
}

function superadmin_route(string $method, array $p): void
{
    $a = $p[0] ?? '';
    $hasSuper = (int)q_val("SELECT COUNT(*) FROM users WHERE role = 'superadmin'") > 0;

    if ($a === 'setup') {
        if ($hasSuper) {
            redirect('admin/login');
        }
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
            $pass = (string)($_POST['password'] ?? '');
            $key = (string)($_POST['setup_key'] ?? '');
            if (login_blocked('setup')) {
                $error = 'Trop d\'essais. Réessayez dans 15 minutes.';
            } elseif (!hash_equals(superadmin_setup_key(), $key)) {
                login_failed('setup');
                $error = 'Clé du serveur incorrecte.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
                $error = 'Email valide et mot de passe de 8 caractères minimum.';
            } elseif (q_one('SELECT id FROM users WHERE LOWER(email) = ?', [$email])) {
                $error = 'Cet email est déjà utilisé par un compte distributeur. Choisissez un autre email pour le super-admin.';
            } else {
                $id = db_insert('users', ['distributor_id' => null, 'role' => 'superadmin', 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created_at' => now()]);
                start_session();
                session_regenerate_id(true);
                $_SESSION['uid'] = $id;
                flash('Compte super-admin créé.');
                redirect('superadmin');
            }
        }
        view('superadmin_setup', ['error' => $error]);
        return;
    }

    $user = superadmin_user();
    if (!$user) {
        redirect($hasSuper ? 'admin/login' : 'superadmin/setup');
    }
    if ($method === 'POST') {
        csrf_check();
    }
    unset($_SESSION['as_did']); // de retour dans l'espace super-admin
    set_did(null);

    switch ($a) {
        case '':
            $search = trim((string)($_GET['q'] ?? ''));
            $filter = (string)($_GET['f'] ?? 'all');
            superadmin_page('superadmin_home', 'Distributeurs', [
                'search' => $search, 'filter' => $filter,
                'rows' => superadmin_distributors($search, $filter),
                'stats' => superadmin_stats(),
                'charts' => superadmin_charts(),
                'soon' => superadmin_distributors('', 'soon'),
                'recent_payments' => q_all("SELECT p.*, d.name AS dist_name FROM subscription_payments p LEFT JOIN distributors d ON d.id = p.distributor_id WHERE p.status = 'ok' ORDER BY p.id DESC LIMIT 5"),
            ]);
            return;

        case 'new':
            superadmin_new($method);
            return;

        case 'announcements':
            superadmin_announcements($method, array_slice($p, 1));
            return;

        case 'export':
            superadmin_export((string)($p[1] ?? ''));

        case 'logout':
            start_session();
            unset($_SESSION['uid'], $_SESSION['as_did']);
            redirect('admin/login');

        case 'd':
            superadmin_distributor($method, array_slice($p, 1));
            return;

        case 'payments':
            superadmin_payments($method, array_slice($p, 1));
            return;

        case 'settings':
            superadmin_settings($method, $user);
            return;
    }
    http_response_code(404);
    superadmin_page('message', 'Page introuvable', ['text' => 'Cette page n\'existe pas.']);
}

function superadmin_distributors(string $search = '', string $filter = 'all'): array
{
    $params = [];
    $where = '1=1';
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where = '(d.name LIKE ? OR d.phone LIKE ? OR d.id IN (SELECT distributor_id FROM users WHERE email LIKE ?))';
        $params = [$like, $like, $like];
    }
    $rows = q_all(
        "SELECT d.*,
            (SELECT email FROM users u WHERE u.distributor_id = d.id ORDER BY u.id LIMIT 1) AS email,
            (SELECT MAX(last_login_at) FROM users u WHERE u.distributor_id = d.id) AS last_login_at,
            (SELECT COUNT(*) FROM agents a WHERE a.distributor_id = d.id AND a.active = 1) AS agents,
            (SELECT MAX(occurred_at) FROM operations o WHERE o.distributor_id = d.id) AS last_op_at,
            (SELECT MAX(last_seen_at) FROM devices v WHERE v.distributor_id = d.id) AS device_seen_at
         FROM distributors d WHERE $where ORDER BY d.created_at DESC",
        $params
    );
    return array_values(array_filter($rows, fn($d) => superadmin_match($d, $filter)));
}

/** Filtres de la liste : payants, essai, expirés, suspendus, à renouveler sous 7 jours. */
function superadmin_match(array $d, string $filter): bool
{
    $active = dist_active($d);
    $left = dist_days_left($d);
    return match ($filter) {
        'paying' => $active && !in_array($d['plan'], ['essai', 'offert'], true),
        'trial' => $active && $d['plan'] === 'essai',
        'expired' => $d['status'] === 'active' && !$active,
        'suspended' => $d['status'] === 'suspended',
        'soon' => $active && $left !== null && $left <= 7,
        'offert' => $d['plan'] === 'offert',
        default => true,
    };
}

function superadmin_stats(): array
{
    $all = q_all('SELECT * FROM distributors');
    $plans = plans();
    $s = ['total' => count($all), 'paying' => 0, 'trial' => 0, 'expired' => 0, 'offert' => 0, 'mrr' => 0.0,
        'unassigned' => (int)q_val("SELECT COUNT(*) FROM subscription_payments WHERE status IN ('unassigned','review')"),
        'month_revenue' => (float)q_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'ok' AND created_at >= ?", [gmdate('Y-m-01 00:00:00')])];
    foreach ($all as $d) {
        if ($d['plan'] === 'offert') {
            $s['offert']++;
        } elseif (!dist_active($d)) {
            $s['expired']++;
        } elseif ($d['plan'] === 'essai') {
            $s['trial']++;
        } else {
            $s['paying']++;
            $s['mrr'] += (float)($plans[$d['plan']]['price'] ?? 0);
        }
    }
    return $s;
}

function superadmin_distributor(string $method, array $p): void
{
    $d = isset($p[0]) && ctype_digit($p[0]) ? q_one('SELECT * FROM distributors WHERE id = ?', [(int)$p[0]]) : null;
    if (!$d) {
        http_response_code(404);
        superadmin_page('message', 'Distributeur introuvable', ['text' => 'Ce distributeur n\'existe pas.']);
        return;
    }
    $id = (int)$d['id'];
    $act = $p[1] ?? '';
    if ($method === 'POST') {
        switch ($act) {
            case 'extend':
                $months = max(1, min(36, (int)($_POST['months'] ?? 1)));
                $plan = (string)($_POST['plan'] ?? '');
                $plan = isset(plans()[$plan]) ? $plan : null;
                $amount = input_amount($_POST['amount'] ?? '') ?? 0;
                dist_extend($id, $months, $plan);
                db_insert('subscription_payments', ['distributor_id' => $id, 'amount' => $amount, 'months' => $months, 'plan' => $plan ?? q_val('SELECT plan FROM distributors WHERE id = ?', [$id]), 'source' => 'manual', 'status' => 'ok', 'note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255) ?: null, 'created_at' => now()]);
                flash('Abonnement prolongé de ' . $months . ' mois.');
                break;
            case 'edit':
                $plan = (string)($_POST['plan'] ?? $d['plan']);
                $allowed = array_merge(array_keys(plans()), ['essai', 'offert']);
                $until = trim((string)($_POST['paid_until'] ?? ''));
                $data = [
                    'name' => mb_substr(trim((string)($_POST['name'] ?? $d['name'])), 0, 190) ?: $d['name'],
                    'phone' => phone_key((string)($_POST['phone'] ?? '')),
                    'plan' => in_array($plan, $allowed, true) ? $plan : $d['plan'],
                    'notes' => mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 500) ?: null,
                ];
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
                    $dt = new DateTime($until . ' 23:59:59', local_tz());
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $data['paid_until'] = $dt->format('Y-m-d H:i:s');
                }
                if ($data['phone'] && q_one('SELECT id FROM distributors WHERE phone = ? AND id <> ?', [$data['phone'], $id])) {
                    flash('Ce numéro est déjà utilisé par un autre distributeur.', 'err');
                    break;
                }
                db_update('distributors', $data, 'id = :id', ['id' => $id]);
                flash('Distributeur enregistré.');
                break;
            case 'toggle':
                db_update('distributors', ['status' => $d['status'] === 'active' ? 'suspended' : 'active'], 'id = :id', ['id' => $id]);
                flash($d['status'] === 'active' ? 'Compte suspendu : le distributeur et ses agents ne peuvent plus se connecter (les SMS continuent d\'être enregistrés).' : 'Compte réactivé.');
                break;
            case 'password':
                $new = (string)($_POST['password'] ?? '');
                if (strlen($new) < 8) {
                    flash('8 caractères minimum.', 'err');
                    break;
                }
                q("UPDATE users SET password_hash = ? WHERE distributor_id = ? AND role = 'owner'", [password_hash($new, PASSWORD_DEFAULT), $id]);
                flash('Mot de passe du distributeur changé. Communiquez-le-lui.');
                break;
            case 'open': // assistance : consulter son espace
                start_session();
                $_SESSION['as_did'] = $id;
                redirect('admin');
            case 'email':
                $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('Email invalide.', 'err');
                } elseif (q_one('SELECT id FROM users WHERE LOWER(email) = ? AND (distributor_id IS NULL OR distributor_id <> ?)', [$email, $id])) {
                    flash('Cet email est déjà utilisé par un autre compte.', 'err');
                } else {
                    q("UPDATE users SET email = ? WHERE distributor_id = ? AND role = 'owner'", [$email, $id]);
                    flash('Email de connexion changé.');
                }
                break;
            case 'trial': // ajoute des jours (essai ou geste commercial)
                $days = max(1, min(365, (int)($_POST['days'] ?? 7)));
                $from = !empty($d['paid_until']) && $d['paid_until'] > now() ? strtotime($d['paid_until'] . ' UTC') : time();
                db_update('distributors', ['paid_until' => gmdate('Y-m-d H:i:s', $from + $days * 86400)], 'id = :id', ['id' => $id]);
                flash($days . ' jour(s) ajouté(s).');
                break;
            case 'revoke_device':
                q('UPDATE devices SET token_hash = NULL, pair_code = NULL WHERE distributor_id = ?', [$id]);
                flash('Téléphone dissocié : le distributeur doit générer un nouveau code.');
                break;
            case 'delete':
                if (trim((string)($_POST['confirm'] ?? '')) !== $d['name']) {
                    flash('Suppression annulée : le nom saisi ne correspond pas.', 'err');
                    break;
                }
                db_tx(function () use ($id) {
                    foreach (['operations', 'deposit_requests', 'agent_phones', 'agents', 'sms_messages', 'devices', 'push_subscriptions', 'subscription_payments', 'users'] as $t) {
                        q("DELETE FROM $t WHERE distributor_id = ?", [$id]);
                    }
                    q('DELETE FROM distributors WHERE id = ?', [$id]);
                });
                flash('Distributeur « ' . $d['name'] . ' » et toutes ses données supprimés.');
                redirect('superadmin');
        }
        redirect('superadmin/d/' . $id);
    }
    superadmin_page('superadmin_distributor', $d['name'], [
        'd' => $d,
        'owner' => q_one("SELECT email, created_at, last_login_at FROM users WHERE distributor_id = ? ORDER BY id LIMIT 1", [$id]),
        'agents' => q_all('SELECT a.id, a.name, a.login_phone, a.active, a.last_login_at FROM agents a WHERE a.distributor_id = ? ORDER BY a.name LIMIT 200', [$id]),
        'device' => q_one('SELECT * FROM devices WHERE distributor_id = ? AND active = 1 ORDER BY id DESC LIMIT 1', [$id]),
        'volume' => (float)q_val("SELECT COALESCE(SUM(amount),0) FROM operations WHERE distributor_id = ? AND status = 'ok' AND kind = 'depot' AND occurred_at >= ?", [$id, gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
        'payments' => q_all('SELECT * FROM subscription_payments WHERE distributor_id = ? ORDER BY id DESC LIMIT 30', [$id]),
        'counts' => [
            'agents' => (int)q_val('SELECT COUNT(*) FROM agents WHERE distributor_id = ? AND active = 1', [$id]),
            'ops' => (int)q_val("SELECT COUNT(*) FROM operations WHERE distributor_id = ? AND status = 'ok'", [$id]),
            'ops_30' => (int)q_val("SELECT COUNT(*) FROM operations WHERE distributor_id = ? AND status = 'ok' AND occurred_at >= ?", [$id, gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
            'device' => q_val('SELECT MAX(last_seen_at) FROM devices WHERE distributor_id = ?', [$id]),
        ],
    ]);
}

function superadmin_payments(string $method, array $p): void
{
    if ($method === 'POST' && isset($p[0], $p[1])) {
        $pm = q_one('SELECT * FROM subscription_payments WHERE id = ?', [(int)$p[0]]);
        if ($pm && in_array($pm['status'], ['unassigned', 'review'], true)) {
            if ($p[1] === 'assign') {
                $d = q_one('SELECT * FROM distributors WHERE id = ?', [(int)($_POST['distributor_id'] ?? 0)]);
                if ($d) {
                    [$plan, $months] = subscription_plan_for($d, (float)$pm['amount']);
                    $months = max(1, (int)($_POST['months'] ?? 0) ?: $months);
                    dist_extend((int)$d['id'], $months, $plan);
                    db_update('subscription_payments', ['distributor_id' => $d['id'], 'status' => 'ok', 'months' => $months, 'plan' => $plan], 'id = :id', ['id' => $pm['id']]);
                    if (!empty($_POST['remember']) && $pm['phone'] && !$d['phone']) {
                        db_update('distributors', ['phone' => phone_key($pm['phone'])], 'id = :id', ['id' => $d['id']]);
                    }
                    flash('Paiement attribué : ' . $d['name'] . ' prolongé de ' . $months . ' mois.');
                }
            } elseif ($p[1] === 'ignore') {
                db_update('subscription_payments', ['status' => 'ignored'], 'id = :id', ['id' => $pm['id']]);
                flash('Paiement ignoré.');
            }
        }
        redirect('superadmin/payments');
    }
    superadmin_page('superadmin_payments', 'Paiements', [
        'rows' => q_all('SELECT p.*, d.name AS dist_name FROM subscription_payments p LEFT JOIN distributors d ON d.id = p.distributor_id ORDER BY CASE WHEN p.status IN (\'unassigned\',\'review\') THEN 0 ELSE 1 END, p.id DESC LIMIT 200'),
        'distributors' => q_all('SELECT id, name FROM distributors ORDER BY name'),
    ]);
}

function superadmin_settings(string $method, array $user): void
{
    if ($method === 'POST') {
        $form = (string)($_POST['form'] ?? '');
        if ($form === 'platform') {
            setting_set('pay_number', phone_key((string)($_POST['pay_number'] ?? '')) ?? '');
            setting_set('pay_number_moov', phone_key((string)($_POST['pay_number_moov'] ?? '')) ?? '');
            setting_set('support_whatsapp', mb_substr(trim((string)($_POST['support_whatsapp'] ?? '')), 0, 30));
            setting_set('trial_days', (string)max(0, min(90, (int)($_POST['trial_days'] ?? 30))));
            setting_set('registrations_open', !empty($_POST['registrations_open']) ? '1' : '0');
            $plans = [];
            foreach (plans() as $k => $pl) {
                $plans[$k] = [
                    'name' => mb_substr(trim((string)($_POST['plan_name'][$k] ?? $pl['name'])), 0, 30) ?: $pl['name'],
                    'price' => (int)(input_amount($_POST['plan_price'][$k] ?? '') ?? $pl['price']),
                    'agents' => max(0, (int)($_POST['plan_agents'][$k] ?? $pl['agents'])),
                ];
            }
            setting_set('plans', json_encode($plans, JSON_UNESCAPED_UNICODE));
            flash('Réglages de la plateforme enregistrés.');
        } elseif ($form === 'pair') {
            // Téléphone de la plateforme : reçoit les paiements d'abonnement (distributor_id NULL).
            $code = (string)random_int(100000, 999999);
            $dev = q_one('SELECT * FROM devices WHERE distributor_id IS NULL AND active = 1 ORDER BY id DESC LIMIT 1');
            $data = ['pair_code' => $code, 'pair_expires_at' => ts_plus(30 * 60)];
            if ($dev) {
                db_update('devices', $data, 'id = :id', ['id' => $dev['id']]);
            } else {
                db_insert('devices', $data + ['distributor_id' => null, 'name' => 'Téléphone de la plateforme', 'active' => 1, 'created_at' => now()]);
            }
            flash('Code généré : saisissez-le dans l\'application sur le téléphone de votre numéro de paiement.');
        } elseif ($form === 'password') {
            $u = q_one('SELECT * FROM users WHERE id = ?', [$user['id']]);
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
                flash('Mot de passe actuel incorrect.', 'err');
            } elseif (strlen($new) < 8) {
                flash('8 caractères minimum.', 'err');
            } else {
                db_update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
                flash('Mot de passe changé.');
            }
        }
        redirect('superadmin/settings');
    }
    superadmin_page('superadmin_settings', 'Plateforme', [
        'user' => $user,
        'device' => q_one('SELECT * FROM devices WHERE distributor_id IS NULL AND active = 1 ORDER BY id DESC LIMIT 1'),
    ]);
}

/* ------------------------------------------------------------------ Inscription d'un distributeur */

function register_route(string $method): void
{
    if (admin_user()) {
        redirect('admin');
    }
    $open = setting('registrations_open') === '1';
    $error = null;
    if ($open && $method === 'POST') {
        csrf_check();
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $phone = phone_key((string)($_POST['phone'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $who = 'register:' . substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60);
        if (login_blocked($who)) {
            $error = 'Trop d\'inscriptions depuis cette connexion. Réessayez plus tard.';
        } elseif ($name === '' || $phone === null) {
            $error = 'Nom de l\'entreprise et numéro Orange Money (8 chiffres) obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email invalide.';
        } elseif (strlen($pass) < 8) {
            $error = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif (q_one('SELECT id FROM users WHERE LOWER(email) = ?', [$email])) {
            $error = 'Un compte existe déjà avec cet email. Connectez-vous.';
        } elseif (dist_find_by_phone($phone)) {
            $error = 'Un compte existe déjà avec ce numéro. Connectez-vous ou contactez le support.';
        } else {
            $uid = db_tx(function () use ($name, $phone, $email, $pass) {
                $op = phone_operator($phone) ?? 'orange';
                $did = db_insert('distributors', [
                    'name' => $name, 'phone' => $phone, 'plan' => 'essai', 'status' => 'active',
                    'paid_until' => ts_plus(max(1, (int)setting('trial_days', '30')) * 86400),
                    'settings' => json_encode(['receive_' . $op => $phone, 'operators' => $op]), 'created_at' => now(),
                ]);
                return db_insert('users', ['distributor_id' => $did, 'role' => 'owner', 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created_at' => now()]);
            });
            login_failed($who); // compte aussi les inscriptions (limite anti-abus), sans délai notable
            start_session();
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            flash('Bienvenue ! Votre essai gratuit de ' . (int)setting('trial_days', '30') . ' jours commence. Ajoutez vos agents, puis associez votre téléphone.');
            redirect('admin/agents');
        }
    }
    view('register', ['error' => $error, 'open' => $open]);
}

/* ------------------------------------------------------------------ Statistiques */

function superadmin_charts(): array
{
    $months = [];
    $tz = local_tz();
    $names = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    for ($i = 5; $i >= 0; $i--) {
        $start = (new DateTime('first day of this month 00:00', $tz))->modify("-$i month");
        $end = (clone $start)->modify('+1 month');
        $su = (clone $start)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $eu = (clone $end)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $months[] = [
            'label' => $names[(int)$start->format('n') - 1],
            'revenue' => (float)q_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'ok' AND created_at >= ? AND created_at < ?", [$su, $eu]),
            'signups' => (int)q_val('SELECT COUNT(*) FROM distributors WHERE created_at >= ? AND created_at < ?', [$su, $eu]),
        ];
    }
    return [
        'months' => $months,
        'signups_30' => (int)q_val('SELECT COUNT(*) FROM distributors WHERE created_at >= ?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
        'agents_total' => (int)q_val('SELECT COUNT(*) FROM agents WHERE active = 1'),
        'ops_30' => (int)q_val("SELECT COUNT(*) FROM operations WHERE status = 'ok' AND occurred_at >= ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
    ];
}

/* ------------------------------------------------------------------ Création manuelle */

function superadmin_new(string $method): void
{
    $error = null;
    if ($method === 'POST') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $phone = phone_key((string)($_POST['phone'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $pass = (string)($_POST['password'] ?? '');
        $plan = (string)($_POST['plan'] ?? 'essai');
        $months = max(0, min(36, (int)($_POST['months'] ?? 0)));
        $allowed = array_merge(['essai', 'offert'], array_keys(plans()));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            $error = 'Nom, email valide et mot de passe (8 caractères min.) obligatoires.';
        } elseif (q_one('SELECT id FROM users WHERE LOWER(email) = ?', [$email])) {
            $error = 'Cet email est déjà utilisé.';
        } elseif ($phone && q_one('SELECT id FROM distributors WHERE phone = ?', [$phone])) {
            $error = 'Ce numéro est déjà utilisé par un distributeur.';
        } elseif (!in_array($plan, $allowed, true)) {
            $error = 'Formule inconnue.';
        } else {
            $until = $plan === 'offert' ? null : ($plan === 'essai'
                ? ts_plus(max(1, (int)setting('trial_days', '30')) * 86400)
                : (new DateTime('now', new DateTimeZone('UTC')))->modify('+' . max(1, $months) . ' month')->format('Y-m-d H:i:s'));
            $did = db_tx(function () use ($name, $phone, $email, $pass, $plan, $until) {
                $did = db_insert('distributors', ['name' => $name, 'phone' => $phone, 'plan' => $plan, 'status' => 'active', 'paid_until' => $until, 'settings' => json_encode([]), 'created_at' => now()]);
                db_insert('users', ['distributor_id' => $did, 'role' => 'owner', 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'created_at' => now()]);
                return $did;
            });
            flash('Distributeur créé. Donnez-lui son email et son mot de passe : connexion sur ' . abs_url('admin') . '.');
            redirect('superadmin/d/' . $did);
        }
    }
    superadmin_page('superadmin_new', 'Nouveau distributeur', ['error' => $error]);
}

/* ------------------------------------------------------------------ Annonces */

function superadmin_announcements(string $method, array $p): void
{
    if ($method === 'POST') {
        $act = $p[0] ?? 'create';
        if ($act === 'create') {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190);
            if ($title !== '') {
                $id = db_insert('announcements', [
                    'title' => $title,
                    'body' => mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000) ?: null,
                    'level' => in_array($_POST['level'] ?? '', ['info', 'warn', 'ok'], true) ? $_POST['level'] : 'info',
                    'active' => 1,
                    'created_at' => now(),
                ]);
                if (!empty($_POST['push'])) {
                    $sent = 0;
                    foreach (q_all('SELECT id FROM distributors WHERE status = ?', ['active']) as $d) {
                        set_did((int)$d['id']);
                        $sent += push_notify('📣 ' . $title, (string)($_POST['body'] ?? ''), 'admin', null, 'ann-' . $id);
                    }
                    set_did(null);
                    flash('Annonce publiée et envoyée en notification (' . $sent . ' appareil(s)).');
                } else {
                    flash('Annonce publiée dans l\'espace des distributeurs.');
                }
            }
        } elseif (isset($p[1]) && ctype_digit($p[0])) {
            if ($p[1] === 'toggle') {
                q('UPDATE announcements SET active = 1 - active WHERE id = ?', [(int)$p[0]]);
            } elseif ($p[1] === 'delete') {
                q('DELETE FROM announcements WHERE id = ?', [(int)$p[0]]);
            }
        }
        redirect('superadmin/announcements');
    }
    superadmin_page('superadmin_announcements', 'Annonces', ['rows' => q_all('SELECT * FROM announcements ORDER BY id DESC LIMIT 50')]);
}

/* ------------------------------------------------------------------ Exports CSV (Excel) */

function superadmin_export(string $what): never
{
    $rows = [];
    if ($what === 'payments') {
        $rows[] = ['Date', 'Distributeur', 'Montant', 'Mois', 'Formule', 'Source', 'Numéro', 'Transaction', 'Statut', 'Note'];
        foreach (q_all('SELECT p.*, d.name AS dist_name FROM subscription_payments p LEFT JOIN distributors d ON d.id = p.distributor_id ORDER BY p.id DESC') as $r) {
            $rows[] = [fmt_dt($r['created_at']), $r['dist_name'] ?? '', (int)$r['amount'], (int)$r['months'], plan_label((string)$r['plan']), $r['source'], $r['phone'], $r['txn_id'], $r['status'], $r['note']];
        }
    } else {
        $what = 'distributeurs';
        $rows[] = ['Nom', 'Email', 'Numéro', 'Formule', 'Statut', 'Valable jusqu\'au', 'Agents actifs', 'Inscrit le', 'Dernière connexion', 'Dernière opération'];
        foreach (superadmin_distributors() as $d) {
            $rows[] = [$d['name'], $d['email'], $d['phone'], plan_label($d['plan']), dist_active($d) ? 'actif' : ($d['status'] === 'suspended' ? 'suspendu' : 'expiré'),
                $d['paid_until'] ? fmt_dt($d['paid_until'], 'd/m/Y') : '', (int)$d['agents'], fmt_dt($d['created_at'], 'd/m/Y'), fmt_dt($d['last_login_at']), fmt_dt($d['last_op_at'])];
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="flashdepot-' . $what . '-' . gmdate('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // accents corrects dans Excel
    foreach ($rows as $r) {
        fputcsv($out, array_map(fn($v) => (string)($v ?? ''), $r), ';');
    }
    fclose($out);
    exit;
}
