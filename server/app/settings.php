<?php
declare(strict_types=1);

function setting_defaults(): array
{
    return [
        'app_name' => 'FlashDépôt',
        'timezone' => 'Africa/Ouagadougou',
        // Code USSD affiché au distributeur pour faire le dépôt (il compose lui-même).
        // {number} = numéro de l'agent, {amount} = montant entier. Vide = pas de raccourci.
        'deposit_ussd' => '',
        // Vérifie que le nouveau solde annoncé par le SMS correspond à l'ancien solde ± le montant.
        'balance_check' => '1',
        'min_request' => '1000',
        'tg_token' => '',
        'tg_chat_id' => '',
        'sms_rules' => '',
    ];
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
    $tpl = trim((string)setting('deposit_ussd'));
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
