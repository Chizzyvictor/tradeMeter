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
    return 'tm-backup-cid' . $cid . '-';
}

function schedulerAutoBackupPrefix(int $cid): string {
    return schedulerBackupPrefix($cid) . 'auto-';
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

function schedulerTenantDirectTables(): array {
    return [
        'company',
        'roles',
        'users',
        'partner',
        'product_categories',
        'products',
        'inventory',
        'purchases',
        'sales',
        'stock_ledger',
        'partner_ledger',
        'attendance_policies',
        'employee_shift_rules',
        'employee_attendance_logs',
        'attendance_corrections',
        'remember_tokens',
        'remember_token_audit',
        'user_sessions',
        'login_logs',
    ];
}

function schedulerTenantSubTables(): array {
    return [
        [
            'table' => 'sales_items',
            'parent' => 'sales',
            'parent_pk' => 'sale_id',
            'child_fk' => 'sale_id',
        ],
        [
            'table' => 'purchases_items',
            'parent' => 'purchases',
            'parent_pk' => 'purchase_id',
            'child_fk' => 'purchase_id',
        ],
        [
            'table' => 'user_roles',
            'parent' => 'users',
            'parent_pk' => 'user_id',
            'child_fk' => 'user_id',
        ],
        [
            'table' => 'role_permissions',
            'parent' => 'roles',
            'parent_pk' => 'role_id',
            'child_fk' => 'role_id',
        ],
    ];
}

function schedulerExportTenantBackupData(AppDbConnection $db, int $cid): array {
    $payload = [
        'metadata' => [
            'format' => 'tm-tenant-backup-v1',
            'cid' => $cid,
            'timestamp' => time(),
            'driver' => $db->driver(),
            'source' => 'scheduler',
        ],
        'tables' => [],
    ];

    foreach (schedulerTenantDirectTables() as $table) {
        try {
            $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE cid = :cid');
            if (!$stmt) {
                continue;
            }

            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $res = $stmt->execute();

            $rows = [];
            while ($row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false) {
                $rows[] = $row;
            }

            $payload['tables'][$table] = $rows;
        } catch (Throwable $e) {
            continue;
        }
    }

    foreach (schedulerTenantSubTables() as $cfg) {
        $table = (string)$cfg['table'];
        $parent = (string)$cfg['parent'];
        $parentPk = (string)$cfg['parent_pk'];
        $childFk = (string)$cfg['child_fk'];

        try {
            $sql = 'SELECT st.* FROM ' . $table . ' st INNER JOIN ' . $parent . ' pt ON st.' . $childFk . ' = pt.' . $parentPk . ' WHERE pt.cid = :cid';
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                continue;
            }

            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $res = $stmt->execute();

            $rows = [];
            while ($row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false) {
                $rows[] = $row;
            }

            $payload['tables'][$table] = $rows;
        } catch (Throwable $e) {
            continue;
        }
    }

    return $payload;
}

function schedulerCreateTenantBackupFile(AppDbConnection $db, int $cid): ?string {
    $filename = schedulerAutoBackupPrefix($cid) . date('Ymd') . '.json';
    $targetPath = rtrim(schedulerBackupDir(), '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (is_file($targetPath)) {
        return $targetPath;
    }

    $payload = schedulerExportTenantBackupData($db, $cid);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return null;
    }

    if (@file_put_contents($targetPath, $json, LOCK_EX) === false) {
        return null;
    }

    return $targetPath;
}

function schedulerApplyRetentionForCid(int $cid): void {
    $dir = schedulerBackupDir();
    $prefix = schedulerAutoBackupPrefix($cid);
    $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '*.json');
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
schedulerEnsureBackupAuditTable($db);

$companyStmt = $db->prepare('SELECT cid FROM company ORDER BY cid ASC');
if (!$companyStmt) {
    echo "Skipping scheduler backup: failed to query companies.\n";
    schedulerAudit($db, 0, 'auto_backup_skipped', '', 0, ['reason' => 'query_failed']);
    exit(1);
}

$companyRes = $companyStmt->execute();
$cids = [];
while ($row = $companyRes ? $companyRes->fetchArray(SQLITE3_ASSOC) : false) {
    $cid = intval($row['cid'] ?? 0);
    if ($cid > 0) {
        $cids[] = $cid;
    }
}

if (empty($cids)) {
    echo "Skipping scheduler backup: no company record found.\n";
    schedulerAudit($db, 0, 'auto_backup_skipped', '', 0, ['reason' => 'no_company']);
    exit(0);
}

$createdCount = 0;
$failedCount = 0;
$skippedCount = 0;

foreach ($cids as $cid) {
    $todayName = schedulerAutoBackupPrefix($cid) . date('Ymd') . '.json';
    $targetPath = rtrim(schedulerBackupDir(), '/\\') . DIRECTORY_SEPARATOR . $todayName;

    if (is_file($targetPath)) {
        $skippedCount++;
        echo "Scheduler backup already exists for CID {$cid}: {$todayName}\n";
    } else {
        $createdPath = schedulerCreateTenantBackupFile($db, $cid);
        if ($createdPath === null || !is_file($createdPath)) {
            $failedCount++;
            echo "Failed to create scheduler backup for CID {$cid}.\n";
            schedulerAudit($db, $cid, 'auto_backup_failed', $todayName, 0);
        } else {
            $createdCount++;
            $size = intval(filesize($createdPath) ?: 0);
            schedulerAudit($db, $cid, 'auto_backup_created', basename($createdPath), $size);
            echo "Created scheduler backup for CID {$cid}: " . basename($createdPath) . "\n";
        }
    }

    schedulerApplyRetentionForCid($cid);
}

echo "Scheduler completed. Created: {$createdCount}, Skipped: {$skippedCount}, Failed: {$failedCount}. Retention keep-days: " . schedulerBackupRetentionDays() . "\n";
exit(0);
