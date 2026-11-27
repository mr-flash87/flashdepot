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

/** Schéma compatible MySQL et SQLite. */
function db_schema(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk = $driver === 'sqlite' ? 'INTEGER' : 'INT UNSIGNED';
    $text = $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
    $engine = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $dt = $driver === 'sqlite' ? 'TEXT' : 'DATETIME';

    $t = [];
    // Distributeur (administrateur)
    $t[] = "CREATE TABLE IF NOT EXISTS users (
        id $pk,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(100) NOT NULL PRIMARY KEY,
        value $text
    )$engine";
    // Agents Orange Money
    $t[] = "CREATE TABLE IF NOT EXISTS agents (
        id $pk,
        name VARCHAR(190) NOT NULL,
        login_phone VARCHAR(12) NOT NULL UNIQUE,
        pin_hash VARCHAR(255) NULL,
        location VARCHAR(190) NULL,
        notes VARCHAR(500) NULL,
        active SMALLINT NOT NULL DEFAULT 1,
        last_login_at $dt NULL,
        created_at $dt NOT NULL
    )$engine";
    // Numéros Orange Money d'un agent (pour reconnaître ses dépôts et remboursements)
    $t[] = "CREATE TABLE IF NOT EXISTS agent_phones (
        id $pk,
        agent_id $fk NOT NULL,
        phone_key VARCHAR(12) NOT NULL UNIQUE,
        created_at $dt NOT NULL
    )$engine";
    $t[] = "CREATE INDEX idx_aphone_agent ON agent_phones (agent_id)";
    // Demandes de dépôt faites par les agents
    $t[] = "CREATE TABLE IF NOT EXISTS deposit_requests (
        id $pk,
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
    $t[] = "CREATE INDEX idx_req_status ON deposit_requests (status, agent_id)";
    // Grand livre : chaque dépôt (dette +) et chaque remboursement (dette -)
    $t[] = "CREATE TABLE IF NOT EXISTS operations (
        id $pk,
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
    $t[] = "CREATE INDEX idx_op_status ON operations (status, occurred_at)";
    $t[] = "CREATE INDEX idx_op_txn ON operations (txn_id)";
    // Téléphone Android qui lit les SMS de la SIM du distributeur
    $t[] = "CREATE TABLE IF NOT EXISTS devices (
        id $pk,
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
    $t[] = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id $pk,
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
const SCHEMA_VERSION = 2; // 2 : notifications push

function db_add_column(string $table, string $column, string $mysqlType, string $sqliteType): void
{
    try {
        db()->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, db_driver() === 'sqlite' ? $sqliteType : $mysqlType));
    } catch (PDOException $e) {
        // La colonne existe déjà.
    }
}

function db_migrate(): void
{
    $v = (int)setting('schema_version', '0');
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    db_install_schema(db(), db_driver());
    setting_set('schema_version', (string)SCHEMA_VERSION);
}
