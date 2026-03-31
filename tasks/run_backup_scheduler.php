<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../INC/db.php';

function schedulerBackupDir(): string {
    $configured = trim((string)(appEnv('TM_BACKUP_DIR', '') ?? ''));
    $dir = $configured !== ''
        ? $configured
        : (__DIR__ . '/../storage/backups');

    $dir = rtrim($dir, '/\\');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function schedulerBackupRetentionDays(): int {
    $raw = intval(appEnv('TM_BACKUP_RETENTION_DAYS', '14') ?? 14);
    return max(1, $raw);
}

function schedulerBackupPrefix(int $cid): string {
    return 'tm-backup-cid' . $cid . '-auto-';
}

function schedulerEnsureBackupAuditTable(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS backup_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        user_id INTEGER,
        event_type TEXT NOT NULL,
        filename TEXT,
        size_bytes INTEGER DEFAULT 0,
        ip_address TEXT,
        user_agent TEXT,
        details TEXT,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");
}

function schedulerAudit(AppDbConnection $db, int $cid, string $eventType, string $filename = '', int $sizeBytes = 0, array $details = []): void {
    $stmt = $db->prepare("INSERT INTO backup_audit (
            cid, user_id, event_type, filename, size_bytes, ip_address, user_agent, details, created_at
        ) VALUES (
            :cid, NULL, :event_type, :filename, :size_bytes, :ip_address, :user_agent, :details, :created_at
        )");

    if (!$stmt) {
        return;
    }

    $detailsJson = empty($details) ? null : json_encode($details, JSON_UNESCAPED_SLASHES);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
    if ($filename === '') {
        $stmt->bindValue(':filename', null, SQLITE3_NULL);
    } else {
        $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
    }
    $stmt->bindValue(':size_bytes', max(0, $sizeBytes), SQLITE3_INTEGER);
    $stmt->bindValue(':ip_address', 'scheduler', SQLITE3_TEXT);
    $stmt->bindValue(':user_agent', 'cli', SQLITE3_TEXT);
    if ($detailsJson === null || $detailsJson === false) {
        $stmt->bindValue(':details', null, SQLITE3_NULL);
    } else {
        $stmt->bindValue(':details', $detailsJson, SQLITE3_TEXT);
    }
    $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function schedulerSnapshotSqlite(string $sourcePath, string $targetPath): bool {
    if (!is_file($sourcePath)) {
        return false;
    }

    if (is_file($targetPath)) {
        @unlink($targetPath);
    }

    $sourceDb = null;
    $targetDb = null;

    try {
        $sourceDb = new SQLite3($sourcePath);
        $targetDb = new SQLite3($targetPath);
        $sourceDb->enableExceptions(true);
        $targetDb->enableExceptions(true);
        $sourceDb->busyTimeout(5000);
        $targetDb->busyTimeout(5000);

        $ok = $sourceDb->backup($targetDb);
        $sourceDb->close();
        $targetDb->close();

        if (!$ok || !is_file($targetPath)) {
            @unlink($targetPath);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        if ($sourceDb instanceof SQLite3) {
            @$sourceDb->close();
        }
        if ($targetDb instanceof SQLite3) {
            @$targetDb->close();
        }
        @unlink($targetPath);
        return false;
    }
}

function schedulerApplyRetentionForCid(int $cid): void {
    $dir = schedulerBackupDir();
    $prefix = schedulerBackupPrefix($cid);
    $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '*.sqlite');
    $files = is_array($matches) ? $matches : [];

    usort($files, function (string $a, string $b): int {
        return intval(@filemtime($b)) <=> intval(@filemtime($a));
    });

    $keep = schedulerBackupRetentionDays();
    if (count($files) <= $keep) {
        return;
    }

    foreach (array_slice($files, $keep) as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

$db = appDbConnectCompat();

if ($db->driver() !== 'sqlite') {
    echo "Skipping scheduler backup: non-SQLite deployment detected.\n";
    exit(0);
}

schedulerEnsureBackupAuditTable($db);

$companyCount = intval($db->querySingle('SELECT COUNT(*) FROM company'));
if ($companyCount !== 1) {
    echo "Skipping scheduler backup: expected single-company DB, found {$companyCount}.\n";
    schedulerAudit($db, 0, 'auto_backup_skipped', '', 0, ['reason' => 'multi_company', 'count' => $companyCount]);
    exit(0);
}

$cid = intval($db->querySingle('SELECT cid FROM company ORDER BY cid ASC LIMIT 1'));
if ($cid <= 0) {
    echo "Skipping scheduler backup: no company record found.\n";
    schedulerAudit($db, 0, 'auto_backup_skipped', '', 0, ['reason' => 'no_company']);
    exit(0);
}

$todayName = schedulerBackupPrefix($cid) . date('Ymd') . '.sqlite';
$targetPath = rtrim(schedulerBackupDir(), '/\\') . DIRECTORY_SEPARATOR . $todayName;

if (!is_file($targetPath)) {
    $ok = schedulerSnapshotSqlite(appSqlitePath(), $targetPath);
    if (!$ok) {
        echo "Failed to create scheduler backup.\n";
        schedulerAudit($db, $cid, 'auto_backup_failed', $todayName, 0);
        exit(1);
    }

    $size = intval(filesize($targetPath) ?: 0);
    schedulerAudit($db, $cid, 'auto_backup_created', $todayName, $size);
    echo "Created scheduler backup: {$todayName}\n";
} else {
    echo "Scheduler backup already exists for today: {$todayName}\n";
}

schedulerApplyRetentionForCid($cid);
echo "Retention applied (keep " . schedulerBackupRetentionDays() . " days).\n";
exit(0);
