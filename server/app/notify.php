<?php
declare(strict_types=1);

/*
 * Notifications : push sur le téléphone (distributeur et agents) + Telegram (distributeur).
 * Tout est envoyé après la réponse au navigateur / au téléphone Android, pour ne rien ralentir.
 */

function after_response(callable $fn): void
{
    static $registered = false;
    $GLOBALS['__after'][] = $fn;
    if ($registered) {
        return;
    }
    $registered = true;
    register_shutdown_function(function () {
        // 1) Libère la session : la page suivante n'attend pas la fin des envois.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // 2) Termine la réponse au navigateur / au téléphone, puis envoie les notifications.
        ignore_user_abort(true);
        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request(); // Hostinger (LiteSpeed)
        } elseif (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();   // PHP-FPM
        }
        foreach ($GLOBALS['__after'] ?? [] as $f) {
            try {
                $f();
            } catch (Throwable $e) {
                log_line('notification: ' . $e->getMessage());
            }
        }
    });
}

/** Nouvelle demande de dépôt -> distributeur. */
function notify_new_request(int $reqId): void
{
    after_response(function () use ($reqId) {
        $r = request_find($reqId);
        if (!$r) {
            return;
        }
        $due = agent_balance((int)$r['agent_id']);
        push_notify(
            '📥 Demande de ' . fmt_amount($r['amount']),
            $r['agent_name'] . ' · dette actuelle ' . fmt_amount($due) . ($r['note'] ? ' · « ' . $r['note'] . ' »' : ''),
            'admin/requests',
            null,
            'req-' . $reqId
        );
        tg_notify_request($r, $due);
    });
}

/** Demande validée ou refusée -> agent. */
function notify_request_status(int $reqId): void
{
    after_response(function () use ($reqId) {
        $r = request_find($reqId);
        if (!$r) {
            return;
        }
        if ($r['status'] === 'approved') {
            push_notify('✅ Demande validée', 'Votre dépôt de ' . fmt_amount($r['amount']) . ' est en cours d\'envoi.', 'agent', (int)$r['agent_id'], 'req-' . $reqId);
        } elseif ($r['status'] === 'rejected') {
            push_notify('❌ Demande refusée', 'Dépôt de ' . fmt_amount($r['amount']) . ($r['reject_reason'] ? ' · ' . $r['reject_reason'] : ''), 'agent', (int)$r['agent_id'], 'req-' . $reqId);
        }
    });
}

/**
 * Opération enregistrée : l'agent est prévenu (dépôt reçu, remboursement enregistré),
 * le distributeur aussi quand elle vient d'un SMS ($toAdmin).
 */
function notify_op(int $opId, bool $toAdmin): void
{
    after_response(function () use ($opId, $toAdmin) {
        $o = op_find($opId);
        if (!$o) {
            return;
        }
        $amount = fmt_amount($o['amount']);
        $agentId = $o['agent_id'] ? (int)$o['agent_id'] : null;
        $due = $agentId ? agent_balance($agentId) : 0.0;
        $dueText = $due >= 0 ? 'Reste à payer : ' . fmt_amount($due) : 'Avance : ' . fmt_amount(-$due);

        if ($agentId && $o['status'] === 'ok') {
            $sign = op_sign($o['kind']);
            push_notify(
                $o['kind'] === 'depot' ? '💸 Dépôt reçu : ' . $amount : ($sign < 0 ? '✅ Remboursement de ' . $amount . ' enregistré' : op_label($o['kind']) . ' : ' . $amount),
                $o['kind'] === 'depot' ? 'Votre dette passe à ' . fmt_amount($due) . '.' : $dueText . '.',
                'agent',
                $agentId,
                'op-' . $opId
            );
        }
        if (!$toAdmin) {
            return;
        }
        $who = $o['agent_name'] ?: (fmt_phone($o['phone']) . ($o['counterpart'] ? ' · ' . $o['counterpart'] : ''));
        if ($o['status'] === 'review') {
            push_notify('⚠️ SMS à vérifier', op_label($o['kind']) . ' ' . $amount . ' · ' . $who . ' · solde incohérent', 'admin/operations?f=review', null, 'op-' . $opId);
        } elseif ($o['status'] === 'unassigned') {
            push_notify('❓ Numéro inconnu', ($o['kind'] === 'depot' ? 'Dépôt envoyé ' : 'Argent reçu ') . $amount . ' · ' . $who, 'admin/operations?f=unassigned', null, 'op-' . $opId);
        } elseif ($o['kind'] === 'depot') {
            push_notify('📤 Dépôt envoyé : ' . $amount, $who . ' · dette ' . fmt_amount($due), 'admin/agents/' . $agentId, null, 'op-' . $opId);
        } else {
            push_notify('💰 Remboursement reçu : ' . $amount, $who . ' · ' . mb_strtolower($dueText), 'admin/agents/' . $agentId, null, 'op-' . $opId);
        }
        tg_notify_op($o, $due);
    });
}
