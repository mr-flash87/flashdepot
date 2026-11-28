<?php
declare(strict_types=1);

/* Espace agent : /agent/... */

function agent_user(): ?array
{
    start_session();
    if (empty($_SESSION['agent_id'])) {
        return null;
    }
    $a = q_one('SELECT * FROM agents WHERE id = ?', [(int)$_SESSION['agent_id']]);
    if (!$a || (int)$a['active'] !== 1) {
        unset($_SESSION['agent_id']);
        return null;
    }
    set_did((int)$a['distributor_id']); // l'agent ne voit que les données de son distributeur
    if (!distributor() || distributor()['status'] === 'suspended') {
        unset($_SESSION['agent_id']);
        return null;
    }
    return $a;
}

function agent_route(string $method, array $p): void
{
    $a = $p[0] ?? '';

    if ($a === 'login') {
        if (agent_user()) {
            redirect('agent');
        }
        $error = null;
        if ($method === 'POST') {
            csrf_check();
            $phone = phone_key((string)($_POST['phone'] ?? '')) ?? '';
            if (login_blocked('agent:' . $phone)) {
                $error = 'Trop d\'essais. Réessayez dans 15 minutes.';
            } else {
                // Un même numéro peut travailler avec plusieurs distributeurs : le code PIN désigne le bon compte.
                $pin = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
                $ag = null;
                $found = $phone !== '' ? q_all('SELECT * FROM agents WHERE login_phone = ? ORDER BY active DESC, id', [$phone]) : [];
                foreach ($found as $cand) {
                    if ($cand['pin_hash'] && password_verify($pin, $cand['pin_hash'])) {
                        $ag = $cand;
                        break;
                    }
                }
                $ag ??= $found[0] ?? null;
                if ($ag && (int)$ag['active'] === 1 && $ag['pin_hash'] && password_verify($pin, $ag['pin_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['agent_id'] = (int)$ag['id'];
                    db_update('agents', ['last_login_at' => now()], 'id = :id', ['id' => $ag['id']]);
                    redirect('agent');
                }
                login_failed('agent:' . $phone);
                $error = $ag && (int)$ag['active'] !== 1 ? 'Compte désactivé. Contactez votre distributeur.' : 'Numéro ou code PIN incorrect.';
            }
        }
        view('login', ['area' => 'agent', 'error' => $error]);
        return;
    }

    $agent = agent_user();
    if (!$agent) {
        redirect('agent/login');
    }
    if ($method === 'POST') {
        csrf_check();
    }

    switch ($a) {
        case '':
            agent_page($agent, 'agent_home', 'Accueil');
            return;

        case 'demande':
            agent_page($agent, 'agent_request', 'Demander un dépôt');
            return;

        case 'releve':
            agent_page($agent, 'agent_statement', 'Mon relevé');
            return;

        case 'rembourser':
            agent_page($agent, 'agent_repay', 'Rembourser');
            return;

        case 'compte':
            agent_page($agent, 'agent_account', 'Code PIN');
            return;

        case 'logout':
            start_session();
            unset($_SESSION['agent_id']);
            redirect('agent/login');

        case 'push':
            if ($method !== 'POST') {
                redirect('agent');
            }
            push_handle((int)$agent['id'], null);

        case 'poll':
            json_out([
                'due' => agent_balance((int)$agent['id']),
                'sig' => md5((string)q_val("SELECT GROUP_CONCAT(status) FROM (SELECT status FROM deposit_requests WHERE agent_id = ? ORDER BY id DESC LIMIT 10) t", [$agent['id']])
                    . '|' . q_val('SELECT COALESCE(MAX(id),0) FROM operations WHERE agent_id = ?', [$agent['id']])),
            ]);

        case 'request':
            if ($method === 'POST') {
                if (isset($p[1], $p[2]) && $p[2] === 'cancel') {
                    $n = q("UPDATE deposit_requests SET status = 'cancelled', decided_at = ? WHERE id = ? AND agent_id = ? AND status = 'pending'", [now(), (int)$p[1], $agent['id']])->rowCount();
                    flash($n ? 'Demande annulée.' : 'Cette demande ne peut plus être annulée.', $n ? 'ok' : 'err');
                    redirect('agent');
                }
                if (!dist_active(distributor())) {
                    flash('Les demandes sont momentanément indisponibles. Contactez votre distributeur.', 'err');
                    redirect('agent/demande');
                }
                $amount = input_amount($_POST['amount'] ?? '');
                $min = (float)dsetting('min_request', '1000');
                $open = (int)q_val("SELECT COUNT(*) FROM deposit_requests WHERE agent_id = ? AND status = 'pending'", [$agent['id']]);
                if (!$amount || $amount < $min) {
                    flash('Montant invalide (minimum ' . fmt_amount($min) . ').', 'err');
                    redirect('agent/demande');
                } elseif ($open >= 3) {
                    flash('Vous avez déjà 3 demandes en attente. Attendez la réponse du distributeur.', 'err');
                    redirect('agent/demande');
                } else {
                    $opr = (string)($_POST['operator'] ?? '');
                    $opr = in_array($opr, dist_operators(), true) ? $opr : (count(dist_operators()) === 1 ? dist_operators()[0] : null);
                    request_create((int)$agent['id'], $amount, trim((string)($_POST['note'] ?? '')), $opr);
                    flash('Demande de ' . fmt_amount($amount) . ' envoyée au distributeur ✅');
                }
            }
            redirect('agent');

        case 'pin':
            if ($method === 'POST') {
                $cur = preg_replace('/\D+/', '', (string)($_POST['current'] ?? ''));
                $new = preg_replace('/\D+/', '', (string)($_POST['new'] ?? ''));
                if (!password_verify($cur, (string)$agent['pin_hash'])) {
                    flash('Code PIN actuel incorrect.', 'err');
                } elseif (strlen($new) < 4) {
                    flash('Le nouveau code PIN doit avoir au moins 4 chiffres.', 'err');
                } else {
                    db_update('agents', ['pin_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :id', ['id' => $agent['id']]);
                    flash('Code PIN changé.');
                    redirect('agent');
                }
            }
            redirect('agent/compte');
    }
    http_response_code(404);
    redirect('agent');
}

function agent_page(array $agent, string $view, string $title): void
{
    $vars = [
        'title' => $title,
        'area' => 'agent',
        'agent' => $agent,
        'due' => agent_balance((int)$agent['id']),
        'requests' => q_all('SELECT * FROM deposit_requests WHERE agent_id = ? ORDER BY created_at DESC LIMIT 15', [$agent['id']]),
        'ops' => array_values(array_filter(agent_statement((int)$agent['id'], 200), fn($o) => $o['status'] === 'ok')),
    ];
    view('layout_top', $vars);
    view($view, $vars);
    view('layout_bottom', $vars);
}
