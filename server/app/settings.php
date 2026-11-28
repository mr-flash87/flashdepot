<?php
declare(strict_types=1);

function setting_defaults(): array
{
    return [
        // Réglages de la plateforme (super-admin)
        'app_name' => 'FlashDépôt',
        'timezone' => 'Africa/Ouagadougou',
        'trial_days' => '30',
        'registrations_open' => '1',
        // Numéro Orange Money où les distributeurs paient leur abonnement.
        'pay_number' => '',
        'plans' => '',
        'support_whatsapp' => '',
    ];
}

/** Réglages propres à chaque distributeur (valeurs par défaut). */
function dsetting_defaults(): array
{
    return [
        // Code USSD affiché au distributeur pour faire le dépôt (il compose lui-même).
        // {number} = numéro de l'agent, {amount} = montant entier. Vide = pas de raccourci.
        'deposit_ussd' => '',
        // Vérifie que le nouveau solde annoncé par le SMS correspond à l'ancien solde ± le montant.
        'balance_check' => '1',
        'min_request' => '1000',
        'tg_token' => '',
        'tg_chat_id' => '',
    ];
}

/* ------------------------------------------------------------------ Distributeur courant */

/** Choisit le distributeur dont on traite les données (connexion, agent, téléphone). */
function set_did(?int $id): void
{
    $GLOBALS['__did'] = $id;
    $GLOBALS['__dist'] = null;
}

/** Identifiant du distributeur courant. Erreur si aucun : aucune donnée ne doit fuiter entre distributeurs. */
function did(): int
{
    $id = $GLOBALS['__did'] ?? null;
    if (!$id) {
        throw new RuntimeException('Aucun distributeur sélectionné.');
    }
    return (int)$id;
}

function has_did(): bool
{
    return !empty($GLOBALS['__did']);
}

function distributor(): array
{
    if (empty($GLOBALS['__dist'])) {
        $GLOBALS['__dist'] = q_one('SELECT * FROM distributors WHERE id = ?', [did()]) ?? [];
    }
    return $GLOBALS['__dist'];
}

function dsetting(string $name, $default = null)
{
    $all = json_decode((string)(distributor()['settings'] ?? ''), true);
    if (is_array($all) && array_key_exists($name, $all) && $all[$name] !== null) {
        return $all[$name];
    }
    return dsetting_defaults()[$name] ?? $default;
}

function dsetting_set(string $name, ?string $value): void
{
    $all = json_decode((string)(distributor()['settings'] ?? ''), true);
    $all = is_array($all) ? $all : [];
    $all[$name] = $value;
    db_update('distributors', ['settings' => json_encode($all, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => did()]);
    $GLOBALS['__dist'] = null;
}

/* ------------------------------------------------------------------ Formules d'abonnement */

/** Formules : prix mensuel en F et nombre d'agents (0 = illimité). Modifiables par le super-admin. */
function plans(): array
{
    $defaults = [
        'starter' => ['name' => 'Starter', 'price' => 10000, 'agents' => 30],
        'pro' => ['name' => 'Pro', 'price' => 25000, 'agents' => 100],
        'premium' => ['name' => 'Premium', 'price' => 50000, 'agents' => 0],
    ];
    $saved = json_decode((string)setting('plans'), true);
    if (is_array($saved)) {
        foreach ($saved as $k => $p) {
            if (isset($defaults[$k]) && is_array($p)) {
                $defaults[$k] = array_merge($defaults[$k], array_intersect_key($p, $defaults[$k]));
            }
        }
    }
    return $defaults;
}

function plan_label(string $plan): string
{
    return plans()[$plan]['name'] ?? ['essai' => 'Essai gratuit', 'offert' => 'Offert'][$plan] ?? $plan;
}

/** Nombre maximal d'agents (0 = illimité). Pendant l'essai : comme la formule Pro. */
function plan_agent_limit(array $d): int
{
    if ($d['plan'] === 'offert') {
        return 0;
    }
    if ($d['plan'] === 'essai') {
        return (int)(plans()['pro']['agents'] ?? 100);
    }
    return (int)(plans()[$d['plan']]['agents'] ?? 0);
}

/** Abonnement valide : compte actif et (offert ou payé jusqu'à une date future). */
function dist_active(array $d): bool
{
    if (($d['status'] ?? '') !== 'active') {
        return false;
    }
    if (($d['plan'] ?? '') === 'offert') {
        return true;
    }
    return !empty($d['paid_until']) && $d['paid_until'] > now();
}

function dist_days_left(array $d): ?int
{
    if (($d['plan'] ?? '') === 'offert' || empty($d['paid_until'])) {
        return null;
    }
    return (int)floor((strtotime($d['paid_until'] . ' UTC') - time()) / 86400);
}

/** Prolonge l'abonnement de $months mois (à partir de la fin actuelle si elle est future). */
function dist_extend(int $id, int $months, ?string $plan = null): void
{
    $d = q_one('SELECT * FROM distributors WHERE id = ?', [$id]);
    if (!$d || $months <= 0) {
        return;
    }
    $from = !empty($d['paid_until']) && $d['paid_until'] > now() ? strtotime($d['paid_until'] . ' UTC') : time();
    $until = (new DateTime('@' . $from))->modify('+' . $months . ' month')->format('Y-m-d H:i:s');
    $data = ['paid_until' => $until];
    if ($plan !== null) {
        $data['plan'] = $plan;
    } elseif (in_array($d['plan'], ['essai', 'offert'], true)) {
        $data['plan'] = 'starter';
    }
    db_update('distributors', $data, 'id = :id', ['id' => $id]);
    if (has_did() && did() === $id) {
        $GLOBALS['__dist'] = null;
    }
}

function setting(string $name, $default = null)
{
    static $cache = null;
    if ($name === '__reset__') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT name, value FROM settings') as $r) {
                $cache[$r['name']] = $r['value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    if (array_key_exists($name, $cache) && $cache[$name] !== null) {
        return $cache[$name];
    }
    $defaults = setting_defaults();
    return $defaults[$name] ?? $default;
}

function setting_set(string $name, ?string $value): void
{
    $exists = q_val('SELECT COUNT(*) FROM settings WHERE name = ?', [$name]);
    if ((int)$exists > 0) {
        q('UPDATE settings SET value = ? WHERE name = ?', [$value, $name]);
    } else {
        q('INSERT INTO settings (name, value) VALUES (?, ?)', [$name, $value]);
    }
    setting('__reset__');
}

/** Code USSD de dépôt pour un agent, ou '' si non configuré. */
function deposit_ussd(string $phone, float $amount): string
{
    $tpl = trim((string)dsetting('deposit_ussd'));
    $k = phone_key($phone);
    if ($tpl === '' || $k === null) {
        return '';
    }
    return strtr($tpl, ['{number}' => $k, '{amount}' => (string)(int)round($amount)]);
}

function ussd_link(string $code): string
{
    return 'tel:' . str_replace('#', '%23', $code);
}
