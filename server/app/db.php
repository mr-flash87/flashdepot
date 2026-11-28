<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = $GLOBALS['config']['db'];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function db_driver(): string
{
    return $GLOBALS['config']['db']['driver'] ?? 'mysql';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', $cols),
        implode(',', array_map(fn($c) => ':' . $c, $cols))
    );
    q($sql, $data);
    return (int)db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        $sets[] = "$k = :set_$k";
        $params["set_$k"] = $v;
    }
    $st = q(sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where), array_merge($params, $whereParams));
    return $st->rowCount();
}

/** Exécute $fn dans une transaction (verrouillage des écritures concurrentes). */
function db_tx(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Schéma compatible MySQL et SQLite (installation neuve : structure finale multi-distributeurs). */
function db_schema(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
    $text = $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
    $engine = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $dt = $driver === 'sqlite' ? 'TEXT' : 'DATETIME';

    $t = [];
    // Distributeurs (clients de la plateforme)
    $t[] = "CREATE TABLE IF NOT EXISTS distributors (
        id $pk,
        name VARCHAR(190) NOT NULL,
        phone VARCHAR(12) NULL,
        plan VARCHAR(20) NOT NULL DEFAULT 'starter',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        paid_until $dt NULL,
        settings $text NULL,
        notes VARCHAR(500) NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_dist_phone ON distributors (phone)";
    // Comptes : role = owner (distributeur) ou superadmin (propriétaire de la plateforme)
    $t[] = "CREATE TABLE IF NOT EXISTS users (
        id $pk,
        distributor_id $fk NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'owner',
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(100) NOT NULL PRIMARY KEY,
        value $text
    )$engine";
    // Agents Orange Money (chaque distributeur a les siens)
    $t[] = "CREATE TABLE IF NOT EXISTS agents (
        id $pk,
        distributor_id $fk NOT NULL,
        name VARCHAR(190) NOT NULL,
        login_phone VARCHAR(12) NOT NULL,
        pin_hash VARCHAR(255) NULL,
        location VARCHAR(190) NULL,
        notes VARCHAR(500) NULL,
        active SMALLINT NOT NULL DEFAULT 1,
        last_login_at $dt NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE UNIQUE INDEX uq_agent_login ON agents (distributor_id, login_phone)";
    $t[] = "CREATE INDEX idx_agent_phone ON agents (login_phone)";
    // Numéros Orange Money d'un agent (pour reconnaître ses dépôts et remboursements)
    $t[] = "CREATE TABLE IF NOT EXISTS agent_phones (
        id $pk,
        distributor_id $fk NOT NULL,
        agent_id $fk NOT NULL,
        phone_key VARCHAR(12) NOT NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE UNIQUE INDEX uq_aphone ON agent_phones (distributor_id, phone_key)";
    $t[] = "CREATE INDEX idx_aphone_agent ON agent_phones (agent_id)";
    // Demandes de dépôt faites par les agents
    $t[] = "CREATE TABLE IF NOT EXISTS deposit_requests (
        id $pk,
        distributor_id $fk NOT NULL,
        reference VARCHAR(20) NOT NULL UNIQUE,
        agent_id $fk NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        note VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reject_reason VARCHAR(255) NULL,
        operation_id $fk NULL,
        created_at $dt NOT NULL,
        decided_at $dt NULL,
        sent_at $dt NULL
    )$engine";
    $t[] = "CREATE INDEX idx_req_status ON deposit_requests (distributor_id, status, agent_id)";
    // Grand livre : chaque dépôt (dette +) et chaque remboursement (dette -)
    $t[] = "CREATE TABLE IF NOT EXISTS operations (
        id $pk,
        distributor_id $fk NOT NULL,
        agent_id $fk NULL,
        kind VARCHAR(20) NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'ok',
        source VARCHAR(20) NOT NULL DEFAULT 'manual',
        phone VARCHAR(20) NULL,
        counterpart VARCHAR(190) NULL,
        txn_id VARCHAR(100) NULL,
        sms_id $fk NULL,
        request_id $fk NULL,
        note VARCHAR(255) NULL,
        created_by VARCHAR(60) NULL,
        occurred_at $dt NOT NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_op_agent ON operations (agent_id, status, occurred_at)";
    $t[] = "CREATE INDEX idx_op_dist ON operations (distributor_id, status, occurred_at)";
    $t[] = "CREATE INDEX idx_op_txn ON operations (txn_id)";
    // Téléphones Android qui lisent les SMS (distributor_id NULL = téléphone de la plateforme : abonnements)
    $t[] = "CREATE TABLE IF NOT EXISTS devices (
        id $pk,
        distributor_id $fk NULL,
        name VARCHAR(190) NOT NULL,
        token_hash CHAR(64) NULL UNIQUE,
        pair_code VARCHAR(10) NULL,
        pair_expires_at $dt NULL,
        last_seen_at $dt NULL,
        last_balance DECIMAL(14,2) NULL,
        battery SMALLINT NULL,
        charging SMALLINT NULL,
        app_version VARCHAR(20) NULL,
        active SMALLINT NOT NULL DEFAULT 1,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE TABLE IF NOT EXISTS sms_messages (
        id $pk,
        distributor_id $fk NULL,
        device_id $fk NULL,
        sender VARCHAR(60) NOT NULL,
        body $text NOT NULL,
        received_at $dt NOT NULL,
        fingerprint CHAR(64) NOT NULL UNIQUE,
        kind VARCHAR(20) NULL,
        amount DECIMAL(14,2) NULL,
        phone VARCHAR(40) NULL,
        counterpart VARCHAR(190) NULL,
        txn_id VARCHAR(100) NULL,
        balance DECIMAL(14,2) NULL,
        status VARCHAR(20) NOT NULL,
        note VARCHAR(255) NULL,
        operation_id $fk NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_sms_txn ON sms_messages (txn_id)";
    $t[] = "CREATE INDEX idx_sms_dist ON sms_messages (distributor_id, id)";
    $t[] = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id $pk,
        distributor_id $fk NULL,
        user_id $fk NULL,
        agent_id $fk NULL,
        endpoint VARCHAR(500) NOT NULL,
        endpoint_hash CHAR(64) NOT NULL UNIQUE,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(100) NOT NULL,
        user_agent VARCHAR(255) NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_push_agent ON push_subscriptions (agent_id)";
    $t[] = "CREATE INDEX idx_push_dist ON push_subscriptions (distributor_id)";
    // Paiements d'abonnement reçus par la plateforme
    $t[] = "CREATE TABLE IF NOT EXISTS subscription_payments (
        id $pk,
        distributor_id $fk NULL,
        amount DECIMAL(14,2) NOT NULL,
        months INT NOT NULL DEFAULT 0,
        plan VARCHAR(20) NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'manual',
        phone VARCHAR(20) NULL,
        txn_id VARCHAR(100) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'ok',
        note VARCHAR(255) NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_subpay_dist ON subscription_payments (distributor_id, created_at)";
    $t[] = "CREATE TABLE IF NOT EXISTS login_attempts (
        id $pk,
        ip VARCHAR(64) NOT NULL,
        who VARCHAR(100) NOT NULL,
        created_at $dt NOT NULL
    )$engine";
    return $t;
}

function db_install_schema(PDO $pdo, string $driver): void
{
    foreach (db_schema($driver) as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Les index existent peut-être déjà (réinstallation) : on ignore.
            if (stripos($sql, 'CREATE INDEX') !== 0) {
                throw $e;
            }
        }
    }
}

/** Version du schéma : les changements faits après l'installation sont appliqués automatiquement. */
const SCHEMA_VERSION = 3; // 2 : notifications push ; 3 : plusieurs distributeurs

function db_add_column(string $table, string $column, string $mysqlType, string $sqliteType): void
{
    try {
        db()->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, db_driver() === 'sqlite' ? $sqliteType : $mysqlType));
    } catch (PDOException $e) {
        // La colonne existe déjà.
    }
}

/** Retire la contrainte UNIQUE d'une colonne (elle devient unique par distributeur). */
function db_drop_unique(string $table, string $column): void
{
    $pdo = db();
    if (db_driver() === 'sqlite') {
        $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($table))->fetchColumn();
        $rx = '/(' . preg_quote($column, '/') . '\s+VARCHAR\(\d+\)\s+NOT NULL)\s+UNIQUE/i';
        if (!preg_match($rx, $sql)) {
            return;
        }
        $new = preg_replace($rx, '$1', $sql);
        $new = preg_replace('/CREATE TABLE\s+(IF NOT EXISTS\s+)?"?' . preg_quote($table, '/') . '"?/i', 'CREATE TABLE ' . $table . '_new', $new, 1);
        $cols = implode(',', array_map(fn($c) => $c['name'], $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll()));
        $indexes = $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . $pdo->quote($table) . ' AND sql IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec($new);
        $pdo->exec("INSERT INTO {$table}_new ($cols) SELECT $cols FROM $table");
        $pdo->exec("DROP TABLE $table");
        $pdo->exec("ALTER TABLE {$table}_new RENAME TO $table");
        foreach ($indexes as $ix) {
            try {
                $pdo->exec($ix);
            } catch (PDOException $e) {
            }
        }
        return;
    }
    // Seulement l'ancien index UNIQUE sur cette seule colonne (pas les index composés).
    $keys = [];
    foreach ($pdo->query('SHOW INDEX FROM ' . $table)->fetchAll() as $ix) {
        $keys[$ix['Key_name']]['cols'][] = $ix['Column_name'];
        $keys[$ix['Key_name']]['unique'] = (int)$ix['Non_unique'] === 0;
    }
    foreach ($keys as $name => $k) {
        if ($name !== 'PRIMARY' && $k['unique'] && $k['cols'] === [$column]) {
            try {
                $pdo->exec('ALTER TABLE ' . $table . ' DROP INDEX `' . str_replace('`', '', $name) . '`');
            } catch (PDOException $e) {
            }
        }
    }
}

function db_migrate(): void
{
    $v = (int)setting('schema_version', '0');
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    $pdo = db();
    if ($v < 3) {
        // Ajoute d'abord les colonnes : les index du schéma final en dépendent.
        $fkM = 'INT UNSIGNED NULL';
        foreach (['agents', 'agent_phones', 'deposit_requests', 'operations', 'devices', 'sms_messages', 'push_subscriptions', 'users'] as $tbl) {
            try {
                $pdo->query("SELECT 1 FROM $tbl LIMIT 1");
                db_add_column($tbl, 'distributor_id', $fkM, 'INTEGER NULL');
            } catch (PDOException $e) {
                // table absente : elle sera créée par le schéma
            }
        }
        db_add_column('users', 'role', "VARCHAR(20) NOT NULL DEFAULT 'owner'", "TEXT NOT NULL DEFAULT 'owner'");
    }
    db_install_schema($pdo, db_driver());
    if ($v >= 1 && $v < 3) {
        db_migrate_to_multi();
    }
    setting_set('schema_version', (string)SCHEMA_VERSION);
}

/**
 * Passage à plusieurs distributeurs : tout ce qui existe (agents, opérations, demandes, téléphone,
 * SMS, réglages) devient le distributeur n°1. Le compte existant reste son compte, rien n'est perdu.
 */
function db_migrate_to_multi(): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $did = (int)q_val('SELECT MIN(id) FROM distributors');
        if (!$did) {
            $owner = q_one("SELECT * FROM users WHERE role = 'owner' OR role IS NULL ORDER BY id LIMIT 1");
            $settings = [];
            foreach (['deposit_ussd', 'balance_check', 'min_request', 'tg_token', 'tg_chat_id'] as $k) {
                $val = q_val('SELECT value FROM settings WHERE name = ?', [$k]);
                if ($val !== null) {
                    $settings[$k] = $val;
                }
            }
            $did = db_insert('distributors', [
                'name' => (function () {
                    $n = trim((string)q_val("SELECT value FROM settings WHERE name = 'app_name'"));
                    return $n !== '' && $n !== 'FlashDépôt' ? $n : 'Distributeur principal';
                })(),
                'phone' => null,
                'plan' => 'offert', // premier client : gratuit, sans date de fin (modifiable par le super-admin)
                'status' => 'active',
                'paid_until' => null,
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'notes' => 'Compte créé automatiquement à partir des données existantes' . ($owner ? ' (' . $owner['email'] . ')' : ''),
                'created_at' => (string)(q_val('SELECT MIN(created_at) FROM users') ?: now()),
            ]);
        }
        foreach (['agents', 'agent_phones', 'deposit_requests', 'operations', 'devices', 'sms_messages'] as $tbl) {
            q("UPDATE $tbl SET distributor_id = ? WHERE distributor_id IS NULL", [$did]);
        }
        q("UPDATE users SET distributor_id = ?, role = 'owner' WHERE distributor_id IS NULL AND (role IS NULL OR role <> 'superadmin')", [$did]);
        q('UPDATE push_subscriptions SET distributor_id = ? WHERE distributor_id IS NULL', [$did]);
        // L'ancien nom de l'application était celui du distributeur : la plateforme reprend « FlashDépôt ».
        q("DELETE FROM settings WHERE name = 'app_name'");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    // Unicité par distributeur (et non plus sur toute la plateforme).
    db_drop_unique('agents', 'login_phone');
    db_drop_unique('agent_phones', 'phone_key');
    foreach ([
        'CREATE UNIQUE INDEX uq_agent_login ON agents (distributor_id, login_phone)',
        'CREATE INDEX idx_agent_phone ON agents (login_phone)',
        'CREATE UNIQUE INDEX uq_aphone ON agent_phones (distributor_id, phone_key)',
        'CREATE INDEX idx_aphone_agent ON agent_phones (agent_id)',
    ] as $ix) {
        try {
            $pdo->exec($ix);
        } catch (PDOException $e) {
        }
    }
}
