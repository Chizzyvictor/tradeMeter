<?php

if (!defined('SQLITE3_ASSOC')) {
    define('SQLITE3_ASSOC', 1);
}
if (!defined('SQLITE3_NUM')) {
    define('SQLITE3_NUM', 2);
}
if (!defined('SQLITE3_BOTH')) {
    define('SQLITE3_BOTH', 3);
}
if (!defined('SQLITE3_INTEGER')) {
    define('SQLITE3_INTEGER', 1);
}
if (!defined('SQLITE3_FLOAT')) {
    define('SQLITE3_FLOAT', 2);
}
if (!defined('SQLITE3_TEXT')) {
    define('SQLITE3_TEXT', 3);
}
if (!defined('SQLITE3_BLOB')) {
    define('SQLITE3_BLOB', 4);
}
if (!defined('SQLITE3_NULL')) {
    define('SQLITE3_NULL', 5);
}

function appEnv(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value !== false && $value !== null && $value !== '') {
        return (string)$value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }

    return $default;
}

function appDbDriver(): string {
    $databaseUrl = trim((string)appEnv('DATABASE_URL', ''));
    if ($databaseUrl !== '') {
        return 'pgsql';
    }

    return 'sqlite';
}

function appSqlitePath(): string {
    $configured = trim((string)appEnv('SQLITE_DB_PATH', ''));
    if ($configured !== '') {
        return $configured;
    }

    return __DIR__ . '/../mysqlitedb.db';
}

function appPostgresConfig(): array {
    $databaseUrl = trim((string)appEnv('DATABASE_URL', ''));
    if ($databaseUrl === '') {
        return [];
    }

    $parts = parse_url($databaseUrl);
    if (!is_array($parts)) {
        return [];
    }

    return [
        'scheme' => strtolower((string)($parts['scheme'] ?? '')),
        'host' => (string)($parts['host'] ?? ''),
        'port' => intval($parts['port'] ?? 5432),
        'user' => (string)($parts['user'] ?? ''),
        'pass' => (string)($parts['pass'] ?? ''),
        'dbname' => ltrim((string)($parts['path'] ?? ''), '/'),
        'query' => (string)($parts['query'] ?? ''),
        'sslmode' => trim((string)appEnv('PGSSLMODE', 'require')),
    ];
}

function appDbConnect(): SQLite3 {
    $db = new SQLite3(appSqlitePath());
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

class AppDbResult {
    private string $driver;
    private $native;

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function fetchArray(int $mode = SQLITE3_ASSOC) {
        if ($this->driver === 'sqlite') {
            return $this->native->fetchArray($mode);
        }

        if (!($this->native instanceof PDOStatement)) {
            return false;
        }

        if ($mode === SQLITE3_NUM) {
            $row = $this->native->fetch(PDO::FETCH_NUM);
        } elseif ($mode === SQLITE3_BOTH) {
            $row = $this->native->fetch(PDO::FETCH_BOTH);
        } else {
            $row = $this->native->fetch(PDO::FETCH_ASSOC);
        }

        return $row === false ? false : $row;
    }
}

class AppDbStatement {
    private string $driver;
    private $native;
    private array $bound = [];

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function bindValue(string $param, $value, int $type = SQLITE3_TEXT): bool {
        if ($this->driver === 'sqlite') {
            return $this->native->bindValue($param, $value, $type);
        }

        $this->bound[$param] = $value;
        $pdoType = PDO::PARAM_STR;
        if ($type === SQLITE3_INTEGER) {
            $pdoType = PDO::PARAM_INT;
        } elseif ($type === SQLITE3_NULL) {
            $pdoType = PDO::PARAM_NULL;
        }

        return $this->native->bindValue($param, $value, $pdoType);
    }

    public function execute() {
        if ($this->driver === 'sqlite') {
            $result = $this->native->execute();
            return $result === false ? false : new AppDbResult('sqlite', $result);
        }

        $ok = $this->native->execute();
        if (!$ok) {
            return false;
        }

        return new AppDbResult('pgsql', $this->native);
    }
}

class AppDbConnection {
    private string $driver;
    private $native;

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function driver(): string {
        return $this->driver;
    }

    public function exec(string $sql): bool {
        if ($this->driver === 'sqlite') {
            return $this->native->exec($sql);
        }

        $normalized = $this->normalizeSqlForPg($sql);
        $this->native->exec($normalized);
        return true;
    }

    public function query(string $sql) {
        if ($this->driver === 'sqlite') {
            $result = $this->native->query($sql);
            return $result === false ? false : new AppDbResult('sqlite', $result);
        }

        $trimmed = trim($sql);
        if (preg_match('/^PRAGMA\s+table_info\(([^)]+)\)/i', $trimmed, $matches)) {
            $table = trim($matches[1], " \t\n\r\0\x0B`\"'");
            $stmt = $this->native->prepare(
                "SELECT column_name AS name
                 FROM information_schema.columns
                 WHERE table_schema = 'public' AND table_name = :table
                 ORDER BY ordinal_position"
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();
            return new AppDbResult('pgsql', $stmt);
        }

        $stmt = $this->native->query($this->normalizeSqlForPg($sql));
        return $stmt === false ? false : new AppDbResult('pgsql', $stmt);
    }

    public function querySingle(string $sql, bool $entireRow = false) {
        if ($this->driver === 'sqlite') {
            return $this->native->querySingle($sql, $entireRow);
        }

        $stmt = $this->native->query($this->normalizeSqlForPg($sql));
        if (!$stmt) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        if ($entireRow) {
            return $row;
        }

        $first = array_values($row);
        return $first[0] ?? null;
    }

    public function prepare(string $sql) {
        if ($this->driver === 'sqlite') {
            $stmt = $this->native->prepare($sql);
            return $stmt === false ? false : new AppDbStatement('sqlite', $stmt);
        }

        $stmt = $this->native->prepare($this->normalizeSqlForPg($sql));
        return $stmt === false ? false : new AppDbStatement('pgsql', $stmt);
    }

    public function lastInsertRowID(): int {
        if ($this->driver === 'sqlite') {
            return intval($this->native->lastInsertRowID());
        }

        $stmt = $this->native->query('SELECT LASTVAL() AS id');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return intval($row['id'] ?? 0);
    }

    public function close(): void {
        if ($this->driver === 'sqlite') {
            $this->native->close();
            return;
        }

        $this->native = null;
    }

    private function normalizeSqlForPg(string $sql): string {
        $normalized = $sql;

        $normalized = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT', $normalized) ?? $normalized;

        if (stripos($sql, 'INSERT OR IGNORE') !== false) {
            $normalized = rtrim($normalized);
            if (substr($normalized, -1) === ';') {
                $normalized = rtrim(substr($normalized, 0, -1));
            }
            $normalized .= ' ON CONFLICT DO NOTHING';
        }

        $normalized = str_replace("strftime('%s','now')", 'EXTRACT(EPOCH FROM NOW())::bigint', $normalized);
        $normalized = preg_replace('/INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i', 'BIGSERIAL PRIMARY KEY', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $normalized) ?? $normalized;

        return $normalized;
    }
}

function appDbConnectCompat(): AppDbConnection {
    if (appDbDriver() === 'sqlite') {
        return new AppDbConnection('sqlite', appDbConnect());
    }

    $config = appPostgresConfig();
    if (($config['host'] ?? '') === '' || ($config['dbname'] ?? '') === '') {
        return new AppDbConnection('sqlite', appDbConnect());
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $config['host'],
        intval($config['port'] ?? 5432),
        $config['dbname'],
        $config['sslmode'] ?? 'require'
    );

    $pdo = new PDO(
        $dsn,
        (string)($config['user'] ?? ''),
        (string)($config['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return new AppDbConnection('pgsql', $pdo);
}
