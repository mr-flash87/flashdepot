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
            superadmin_page('superadmin_home', 'Distributeurs', ['search' => $search, 'rows' => superadmin_distributors($search), 'stats' => superadmin_stats()]);
            return;

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

function superadmin_distributors(string $search = ''): array
{
    $params = [];
    $where = '1=1';
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where = '(d.name LIKE ? OR d.phone LIKE ? OR d.id IN (SELECT distributor_id FROM users WHERE email LIKE ?))';
        $params = [$like, $like, $like];
    }
    return q_all(
        "SELECT d.*,
            (SELECT email FROM users u WHERE u.distributor_id = d.id ORDER BY u.id LIMIT 1) AS email,
            (SELECT COUNT(*) FROM agents a WHERE a.distributor_id = d.id AND a.active = 1) AS agents,
            (SELECT MAX(occurred_at) FROM operations o WHERE o.distributor_id = d.id) AS last_op_at,
            (SELECT MAX(last_seen_at) FROM devices v WHERE v.distributor_id = d.id) AS device_seen_at
         FROM distributors d WHERE $where ORDER BY d.created_at DESC",
        $params
    );
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
        }
        redirect('superadmin/d/' . $id);
    }
    superadmin_page('superadmin_distributor', $d['name'], [
        'd' => $d,
        'owner' => q_one("SELECT email, created_at FROM users WHERE distributor_id = ? ORDER BY id LIMIT 1", [$id]),
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
        } elseif (q_one('SELECT id FROM distributors WHERE phone = ?', [$phone])) {
            $error = 'Un compte existe déjà avec ce numéro. Connectez-vous ou contactez le support.';
        } else {
            $uid = db_tx(function () use ($name, $phone, $email, $pass) {
                $did = db_insert('distributors', [
                    'name' => $name, 'phone' => $phone, 'plan' => 'essai', 'status' => 'active',
                    'paid_until' => ts_plus(max(1, (int)setting('trial_days', '30')) * 86400),
                    'settings' => json_encode([]), 'created_at' => now(),
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
