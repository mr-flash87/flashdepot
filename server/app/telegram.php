<?php
declare(strict_types=1);

/*
 * Notifications Telegram pour le distributeur (facultatif).
 * Réglages : jeton du bot (créé avec @BotFather) + identifiant de la discussion.
 */

function tg_enabled(): bool
{
    return trim((string)setting('tg_token')) !== '' && trim((string)setting('tg_chat_id')) !== '';
}

/** Envoie un message. Retourne null si OK, sinon le message d'erreur. */
function tg_send(string $text, ?string $chatId = null, ?string $token = null): ?string
{
    $token = trim($token ?? (string)setting('tg_token'));
    $chatId = trim($chatId ?? (string)setting('tg_chat_id'));
    if ($token === '' || $chatId === '' || !function_exists('curl_init')) {
        return 'Telegram non configuré.';
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        log_line('telegram: ' . $err);
        return 'Réseau : ' . $err;
    }
    if ($code !== 200) {
        $j = json_decode((string)$res, true);
        $msg = (string)($j['description'] ?? ('HTTP ' . $code));
        log_line('telegram: ' . $msg);
        return $msg;
    }
    return null;
}

function tg_safe(callable $fn): void
{
    if (!tg_enabled()) {
        return;
    }
    // Pendant une transaction, on envoie après la réponse (ne bloque pas la base ni le téléphone).
    if (db()->inTransaction()) {
        static $registered = false;
        $GLOBALS['tg_after'][] = $fn;
        if (!$registered) {
            $registered = true;
            register_shutdown_function(function () {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                foreach ($GLOBALS['tg_after'] ?? [] as $f) {
                    try {
                        $f();
                    } catch (Throwable $e) {
                        log_line('telegram: ' . $e->getMessage());
                    }
                }
            });
        }
        return;
    }
    try {
        $fn();
    } catch (Throwable $e) {
        log_line('telegram: ' . $e->getMessage());
    }
}

function tg_notify_request(int $reqId): void
{
    tg_safe(function () use ($reqId) {
        $r = request_find($reqId);
        if (!$r) {
            return;
        }
        $due = agent_balance((int)$r['agent_id']);
        tg_send("📥 <b>Demande de dépôt</b>\n" . h($r['agent_name']) . ' demande <b>' . h(fmt_amount($r['amount'])) . "</b>\n"
            . 'Dette actuelle : ' . h(fmt_amount($due))
            . ($r['note'] ? "\nNote : " . h($r['note']) : '')
            . "\n\n" . abs_url('admin/requests'));
    });
}

function tg_notify_op(int $opId): void
{
    tg_safe(function () use ($opId) {
        $o = op_find($opId);
        if (!$o) {
            return;
        }
        $who = $o['agent_name'] ? h($o['agent_name']) : (fmt_phone($o['phone']) . ($o['counterpart'] ? ' (' . h($o['counterpart']) . ')' : ''));
        $amount = '<b>' . h(fmt_amount($o['amount'])) . '</b>';
        if ($o['status'] === 'review') {
            $text = "⚠️ <b>SMS à vérifier</b> (solde incohérent)\n" . h(op_label($o['kind'])) . " $amount — $who";
        } elseif ($o['status'] === 'unassigned') {
            $text = '❓ <b>Numéro inconnu</b>' . "\n" . ($o['kind'] === 'depot' ? 'Dépôt envoyé' : 'Argent reçu') . " $amount — $who\nÀ attribuer à un agent.";
        } elseif ($o['kind'] === 'depot') {
            $text = "📤 <b>Dépôt envoyé</b> $amount à $who\nNouvelle dette : " . h(fmt_amount(agent_balance((int)$o['agent_id'])));
        } else {
            $text = "✅ <b>Remboursement reçu</b> $amount de $who\nReste à payer : " . h(fmt_amount(agent_balance((int)$o['agent_id'])));
        }
        tg_send($text);
    });
}
