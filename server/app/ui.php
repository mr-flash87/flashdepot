<?php
declare(strict_types=1);

/* Petits morceaux d'interface réutilisés par les pages. */

function money_class(float $due): string
{
    return $due > 0 ? 'neg' : ($due < 0 ? 'pos' : '');
}

/** « dû : 150 000 F » ou « avance : 150 000 F ». */
function due_text(float $due): string
{
    return $due >= 0 ? 'dû : ' . h(fmt_amount($due)) : 'avance : ' . h(fmt_amount(-$due));
}

/** Ligne d'opération (journal, relevé, tableau de bord). */
function op_row(array $o, bool $showAgent = true, bool $showBalance = false, bool $forAgent = false): string
{
    $label = $forAgent ? (['depot' => 'Dépôt reçu', 'remb_om' => 'Remboursement Orange Money', 'remb_cash' => 'Remboursement cash'][$o['kind']] ?? op_label($o['kind'])) : op_label($o['kind']);
    $sign = op_sign($o['kind']);
    $counted = $o['status'] === 'ok';
    $ic = $o['kind'] === 'depot' || $o['kind'] === 'ajust_plus' ? 'up' : ($o['kind'] === 'remb_cash' ? 'cash' : 'down');
    $tone = $sign > 0 ? 'out' : 'in';
    $who = '';
    if ($showAgent) {
        $who = $o['agent_name'] ?? null
            ? '<a href="' . h(url('admin/agents/' . $o['agent_id'])) . '">' . h($o['agent_name']) . '</a>'
            : '<span class="muted">' . h(fmt_phone($o['phone'])) . ($o['counterpart'] ? ' · ' . h($o['counterpart']) : '') . '</span>';
    }
    $meta = [];
    $meta[] = h(fmt_dt($o['occurred_at']));
    if ($o['source'] === 'sms') {
        $meta[] = 'SMS';
    } elseif ($o['source'] === 'manual') {
        $meta[] = 'saisie manuelle';
    }
    if ($o['txn_id']) {
        $meta[] = '<span class="mono">' . h($o['txn_id']) . '</span>';
    }
    if ($o['note']) {
        $meta[] = h($o['note']);
    }
    $html = '<div class="op' . ($counted ? '' : ' op-off') . '">'
        . '<div class="op-ic ' . $tone . '">' . icon($ic, 18) . '</div>'
        . '<div class="op-main"><div class="op-title">' . h($label)
        . ($o['status'] !== 'ok' ? ' ' . badge($o['status'], op_status_label($o['status'])) : '') . '</div>'
        . ($who !== '' ? '<div class="op-who">' . $who . '</div>' : '')
        . '<div class="op-meta">' . implode(' · ', $meta) . '</div></div>'
        . '<div class="op-amt ' . $tone . '">' . ($sign > 0 ? '+' : '−') . h(fmt_amount($o['amount']))
        . ($showBalance && $counted ? '<div class="op-bal">' . due_text((float)$o['balance_after']) . '</div>' : '')
        . '</div></div>';
    return $html;
}

/** Carte d'une demande de dépôt avec ses actions (distributeur). */
function request_card(array $r): string
{
    $id = (int)$r['id'];
    $due = agent_balance((int)$r['agent_id']);
    $target = request_target_phone($r + ['login_phone' => $r['login_phone'] ?? '']);
    $h = '<div class="req req-' . h($r['status']) . '">'
        . '<div class="req-top"><div><a class="req-name" href="' . h(url('admin/agents/' . $r['agent_id'])) . '">' . h($r['agent_name']) . '</a>'
        . '<div class="muted small">' . h(fmt_dt($r['created_at'])) . ' · ' . h($r['reference']) . '</div></div>'
        . '<div class="req-amt">' . h(fmt_amount($r['amount'])) . '</div></div>'
        . '<div class="req-info">' . badge($r['status'], req_status_label($r['status']))
        . ' <span class="small">' . ($due >= 0 ? 'Dette actuelle : <b class="neg">' . h(fmt_amount($due)) : 'Avance de l\'agent : <b class="pos">' . h(fmt_amount(-$due))) . '</b></span></div>';
    if ($r['note']) {
        $h .= '<div class="req-note">« ' . h($r['note']) . ' »</div>';
    }
    if ($r['status'] === 'rejected' && $r['reject_reason']) {
        $h .= '<div class="req-note">Motif : ' . h($r['reject_reason']) . '</div>';
    }
    if (in_array($r['status'], ['pending', 'approved'], true)) {
        $h .= '<div class="req-send">Envoyer au <b class="mono">' . h(fmt_phone($target)) . '</b>';
        $code = deposit_ussd($target, (float)$r['amount']);
        if ($code !== '') {
            $h .= ' · <a class="mono" href="' . h(ussd_link($code)) . '">' . h($code) . '</a>';
        }
        $h .= '</div><div class="req-actions">';
        if ($r['status'] === 'pending') {
            $h .= action_button('admin/requests/' . $id . '/approve', icon('check') . ' Valider', 'btn primary');
        } elseif ($code !== '') {
            $h .= '<a class="btn primary" href="' . h(ussd_link($code)) . '">' . icon('phone') . ' Composer</a>';
        }
        $h .= action_button('admin/requests/' . $id . '/sent', 'Déjà envoyé', 'btn', 'Enregistrer ce dépôt à la main ? (à utiliser seulement si le SMS ne remonte pas)')
            . '<details class="reject"><summary class="btn ghost">Refuser</summary>'
            . '<form method="post" action="' . h(url('admin/requests/' . $id . '/reject')) . '">' . csrf_field()
            . '<input type="text" name="reason" placeholder="Motif (facultatif)"><button class="btn danger">Confirmer le refus</button></form></details>'
            . '</div>';
        if ($r['status'] === 'approved') {
            $h .= '<div class="small muted">Dès que le SMS « vous avez transféré… » arrive, la demande passe en « Envoyée » toute seule.</div>';
        }
    }
    return $h . '</div>';
}

/** Petit formulaire POST d'une action (bouton). */
function action_button(string $action, string $label, string $class = 'btn', string $confirm = '', array $hidden = []): string
{
    $h = '<form method="post" action="' . h(url($action)) . '" class="inline"'
        . ($confirm !== '' ? ' onsubmit="return confirm(' . h(json_encode($confirm, JSON_UNESCAPED_UNICODE)) . ')"' : '') . '>' . csrf_field();
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $h . '<button class="' . h($class) . '">' . $label . '</button></form>';
}
