<?php

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
    ];
}

function appDbConnect(): SQLite3 {
    $db = new SQLite3(appSqlitePath());
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}
