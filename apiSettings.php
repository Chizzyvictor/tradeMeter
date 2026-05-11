<?php
require_once __DIR__ . '/helpers.php';

function ensureRbacSchemaForSettings(AppDbConnection $db): void {
    appEnsureRbacSchema($db);
}

function seedRolesAndPermissionsForSettings(AppDbConnection $db, int $cid): void {
    appSeedRolesAndPermissions($db, $cid);
}

function assignSingleRoleToUser(AppDbConnection $db, int $userId, int $roleId): bool {
    $del = $db->prepare("DELETE FROM user_roles WHERE user_id = :uid");
    $del->bindValue(':uid', $userId, SQLITE3_INTEGER);
    if (!$del->execute()) {
        return false;
    }

    $ins = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
    $ins->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $ins->bindValue(':rid', $roleId, SQLITE3_INTEGER);
    return (bool)$ins->execute();
}

function toEpochInt($value): int {
    if ($value === null || $value === '') {
        return 0;
    }

    if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+$/', $value))) {
        return intval($value);
    }

    $ts = strtotime((string)$value);
    return $ts === false ? 0 : intval($ts);
}

function settingsPasswordPolicyError(string $password): string {
    if (strlen($password) < 10) {
        return 'Password must be at least 10 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number';
    }

    if (!preg_match('/[\W_]/', $password)) {
        return 'Password must include at least one special character';
    }

    return '';
}

function settingsVerifyCsrfToken(): void {
    $requestToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($requestToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        respond('error', 'Invalid CSRF token.');
    }
}

function settingsEnforceRateLimit(string $bucket, int $maxRequests, int $windowSeconds): void {
    $max = max(1, $maxRequests);
    $window = max(1, $windowSeconds);
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = 'settings_rl_' . md5($bucket . '|' . $ip . '|' . intval($_SESSION['user_id'] ?? 0));
    $now = time();

    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = ['start' => $now, 'count' => 0];
    }

    $start = intval($_SESSION[$key]['start'] ?? $now);
    $count = intval($_SESSION[$key]['count'] ?? 0);

    if (($now - $start) >= $window) {
        $start = $now;
        $count = 0;
    }

    $count += 1;
    $_SESSION[$key] = ['start' => $start, 'count' => $count];

    if ($count > $max) {
        respond('error', 'Too many requests. Please try again shortly.');
    }
}

function settingsSafeIdentifier(string $identifier): string {
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    if (!is_string($safe) || $safe === '') {
        throw new RuntimeException('Invalid SQL identifier');
    }

    return $safe;
}

function settingsSafeTable(string $table): string {
    $allowed = array_merge(
        settingsTenantDirectTables(),
        array_map(static function (array $cfg): string {
            return (string)($cfg['table'] ?? '');
        }, settingsTenantSubTables())
    );

    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException('Invalid table reference');
    }

    return settingsSafeIdentifier($table);
}

function settingsMaxBackupUploadBytes(): int {
    return 50 * 1024 * 1024;
}

function settingsValidateUploadedBackupSize(array $file): void {
    $size = intval($file['size'] ?? 0);
    if ($size <= 0) {
        respond('error', 'Uploaded backup file is empty');
    }

    if ($size > settingsMaxBackupUploadBytes()) {
        respond('error', 'Backup file too large. Maximum allowed size is 50MB.');
    }
}

function settingsRoleIdIsOwner(AppDbConnection $db, int $cid, int $roleId): bool {
    if ($roleId <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT 1
                         FROM roles
                         WHERE role_id = :rid
                           AND cid = :cid
                           AND lower(role_name) = 'owner'
                         LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bindValue(':rid', $roleId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    return (bool)$stmt->execute()->fetchArray(SQLITE3_ASSOC);
}

function settingsUserIsOwner(AppDbConnection $db, int $cid, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT 1
                         FROM user_roles ur
                         JOIN roles r ON r.role_id = ur.role_id
                         JOIN users u ON u.user_id = ur.user_id
                         WHERE ur.user_id = :uid
                           AND u.cid = :cid
                           AND lower(r.role_name) = 'owner'
                         LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    return (bool)$stmt->execute()->fetchArray(SQLITE3_ASSOC);
}

function settingsCountOwners(AppDbConnection $db, int $cid, bool $activeOnly = false): int {
    $sql = "SELECT COUNT(DISTINCT u.user_id) AS total
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.user_id
            JOIN roles r ON r.role_id = ur.role_id
            WHERE u.cid = :cid
              AND lower(r.role_name) = 'owner'";

    if ($activeOnly) {
        $sql .= ' AND u.is_active = 1';
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return intval($row['total'] ?? 0);
}

function settingsDeleteSessionFile(string $sessionId): void {
    if ($sessionId === '') {
        return;
    }

    $safeSessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', $sessionId);
    if (!is_string($safeSessionId) || $safeSessionId === '') {
        return;
    }

    $savePath = trim((string)session_save_path());
    if ($savePath === '') {
        $savePath = sys_get_temp_dir();
    }

    $basePath = explode(';', $savePath);
    $dir = trim((string)($basePath[count($basePath) - 1] ?? ''));
    if ($dir === '' || !is_dir($dir)) {
        return;
    }

    $sessionFile = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $safeSessionId;
    if (is_file($sessionFile)) {
        @unlink($sessionFile);
    }
}

function settingsPaginationFromRequest(int $defaultPerPage = 10, int $maxPerPage = 50): array {
    $page = intval($_POST['page'] ?? 1);
    $perPage = intval($_POST['per_page'] ?? $defaultPerPage);

    if ($page < 1) {
        $page = 1;
    }

    if ($perPage < 1) {
        $perPage = $defaultPerPage;
    }

    $perPage = min($maxPerPage, $perPage);

    return [
        'page' => $page,
        'per_page' => $perPage,
    ];
}

function settingsBuildPagination(int $totalItems, int $page, int $perPage): array {
    $safePerPage = max(1, $perPage);
    $safeTotalItems = max(0, $totalItems);
    $totalPages = max(1, (int)ceil($safeTotalItems / $safePerPage));
    $safePage = min($totalPages, max(1, $page));

    return [
        'page' => $safePage,
        'per_page' => $safePerPage,
        'total_items' => $safeTotalItems,
        'total_pages' => $totalPages,
        'has_prev' => $safePage > 1,
        'has_next' => $safePage < $totalPages,
    ];
}

function settingsBackupDir(): string {
    $configured = trim((string)(appEnv('TM_BACKUP_DIR', '') ?? ''));
    $dir = $configured !== ''
        ? $configured
        : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private_storage' . DIRECTORY_SEPARATOR . 'backups');

    return rtrim($dir, '/\\');
}

function settingsEnsureBackupDir(): ?string {
    $dir = settingsBackupDir();
    if ($dir === '') {
        return null;
    }

    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return null;
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        return null;
    }

    return $dir;
}

function settingsBackupPrefix(int $cid): string {
    return 'tm-backup-cid' . $cid . '-';
}

function settingsAutoBackupPrefix(int $cid): string {
    return settingsBackupPrefix($cid) . 'auto-';
}

function settingsBackupRetentionDays(): int {
    $raw = intval(appEnv('TM_BACKUP_RETENTION_DAYS', '14') ?? 14);
    return max(1, $raw);
}

function settingsBackupSchedulerHint(): string {
    return trim((string)(appEnv('TM_BACKUP_SCHEDULER_HINT', 'php tasks/run_backup_scheduler.php') ?? 'php tasks/run_backup_scheduler.php'));
}

function settingsEnsureBackupAuditTable(AppDbConnection $db): void {
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

function settingsAuditBackupEvent(AppDbConnection $db, int $cid, ?int $userId, string $eventType, ?string $filename = null, int $sizeBytes = 0, array $details = []): void {
    $stmt = $db->prepare("INSERT INTO backup_audit (
            cid, user_id, event_type, filename, size_bytes, ip_address, user_agent, details, created_at
        ) VALUES (
            :cid, :user_id, :event_type, :filename, :size_bytes, :ip_address, :user_agent, :details, :created_at
        )");

    if (!$stmt) {
        return;
    }

    $uid = $userId !== null && $userId > 0 ? $userId : null;
    $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $detailsJson = empty($details) ? null : json_encode($details, JSON_UNESCAPED_SLASHES);

    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    if ($uid === null) {
        $stmt->bindValue(':user_id', null, SQLITE3_NULL);
    } else {
        $stmt->bindValue(':user_id', $uid, SQLITE3_INTEGER);
    }
    $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
    if ($filename === null || $filename === '') {
        $stmt->bindValue(':filename', null, SQLITE3_NULL);
    } else {
        $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
    }
    $stmt->bindValue(':size_bytes', max(0, $sizeBytes), SQLITE3_INTEGER);
    $stmt->bindValue(':ip_address', $ipAddress, SQLITE3_TEXT);
    $stmt->bindValue(':user_agent', $userAgent, SQLITE3_TEXT);
    if ($detailsJson === null || $detailsJson === false) {
        $stmt->bindValue(':details', null, SQLITE3_NULL);
    } else {
        $stmt->bindValue(':details', $detailsJson, SQLITE3_TEXT);
    }
    $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function settingsRequireBackupAccess(AppDbConnection $db, int $cid): void {
    requirePermission($db, 'manage_users');

    if (!currentUserHasRole($db, 'Owner')) {
        respond('error', 'Only Owner can use database backup and restore operations.');
    }

    if ($cid <= 0) {
        respond('error', 'Invalid company context for backup operation.');
    }
}

function settingsBackupCapability(AppDbConnection $db, int $cid): array {
    $driver = strtolower($db->driver());
    if (!currentUserHasRole($db, 'Owner')) {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'Only Owner can use in-app backup and restore operations.',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
        ];
    }

    if ($cid <= 0) {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'Invalid company context for backup operation.',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
        ];
    }

    $storagePath = settingsEnsureBackupDir();
    if ($storagePath === null) {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'Backup storage directory is missing or not writable.',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
            'storage_path' => settingsBackupDir(),
        ];
    }

    return [
        'supported' => true,
        'driver' => $driver,
        'message' => 'Tenant backup and restore are enabled using JSON export/import.',
        'scheduler_hint' => settingsBackupSchedulerHint(),
        'retention_days' => settingsBackupRetentionDays(),
        'storage_path' => $storagePath,
    ];
}

function settingsTenantDirectTables(): array {
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

function settingsTenantSubTables(): array {
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

function settingsTableColumns(AppDbConnection $db, string $table): array {
    try {
        $safeTable = settingsSafeTable($table);
    } catch (Throwable $e) {
        return [];
    }

    $res = $db->query('PRAGMA table_info(' . $safeTable . ')');
    if (!$res) {
        return [];
    }

    $columns = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            $columns[] = $name;
        }
    }

    return $columns;
}

function settingsTableExists(AppDbConnection $db, string $table): bool {
    return count(settingsTableColumns($db, $table)) > 0;
}

function settingsTableHasColumn(AppDbConnection $db, string $table, string $column): bool {
    $columns = settingsTableColumns($db, $table);
    return in_array($column, $columns, true);
}

function settingsBuildBackupFilename(int $cid, bool $auto = false): string {
    if ($auto) {
        return settingsAutoBackupPrefix($cid) . date('Ymd') . '.json';
    }

    return settingsBackupPrefix($cid) . date('Ymd_His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.json';
}

function settingsExportTenantBackupData(AppDbConnection $db, int $cid): array {
    $backupData = [
        'metadata' => [
            'format' => 'tm-tenant-backup-v1',
            'cid' => $cid,
            'timestamp' => time(),
            'driver' => $db->driver(),
        ],
        'tables' => [],
    ];

    foreach (settingsTenantDirectTables() as $table) {
        $table = settingsSafeTable($table);
        if (!settingsTableExists($db, $table) || !settingsTableHasColumn($db, $table, 'cid')) {
            continue;
        }

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

        $backupData['tables'][$table] = $rows;
    }

    foreach (settingsTenantSubTables() as $cfg) {
        $table = settingsSafeTable((string)$cfg['table']);
        $parent = settingsSafeTable((string)$cfg['parent']);
        $parentPk = settingsSafeIdentifier((string)$cfg['parent_pk']);
        $childFk = settingsSafeIdentifier((string)$cfg['child_fk']);

        if (!settingsTableExists($db, $table) || !settingsTableExists($db, $parent)) {
            continue;
        }
        if (!settingsTableHasColumn($db, $parent, 'cid')) {
            continue;
        }

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

        $backupData['tables'][$table] = $rows;
    }

    return $backupData;
}

function settingsEncodeBackupPayload(array $payload): ?string {
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : null;
}

function settingsCreateBackupFile(AppDbConnection $db, int $cid, bool $auto = false): ?string {
    $payload = settingsExportTenantBackupData($db, $cid);
    $json = settingsEncodeBackupPayload($payload);
    if (!is_string($json)) {
        return null;
    }

    $dir = settingsEnsureBackupDir();
    if ($dir === null) {
        return null;
    }

    $filename = settingsBuildBackupFilename($cid, $auto);
    $targetPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (file_put_contents($targetPath, $json, LOCK_EX) === false) {
        return null;
    }

    return $targetPath;
}

function settingsReadBackupFilePayload(int $cid, string $filename): ?string {
    $safeName = basename(trim($filename));
    if ($safeName === '') {
        return null;
    }

    $prefix = settingsBackupPrefix($cid);
    if (strpos($safeName, $prefix) !== 0 || preg_match('/\.json$/i', $safeName) !== 1) {
        return null;
    }

    $path = rtrim(settingsBackupDir(), '/\\') . DIRECTORY_SEPARATOR . $safeName;
    if (!is_file($path)) {
        return null;
    }

    $content = @file_get_contents($path);
    return is_string($content) ? $content : null;
}

function settingsParseBackupPayload(string $json, int $cid, ?string &$error = null): ?array {
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        $error = 'Invalid backup JSON payload';
        return null;
    }

    $meta = $payload['metadata'] ?? null;
    $tables = $payload['tables'] ?? null;
    if (!is_array($meta) || !is_array($tables)) {
        $error = 'Backup payload is missing metadata or tables';
        return null;
    }

    $payloadCid = intval($meta['cid'] ?? 0);
    if ($payloadCid <= 0 || $payloadCid !== $cid) {
        $error = 'Backup payload does not match current company context';
        return null;
    }

    return $payload;
}

function settingsBindDynamicValue(AppDbStatement $stmt, string $param, $value): void {
    if ($value === null) {
        $stmt->bindValue($param, null, SQLITE3_NULL);
        return;
    }

    if (is_bool($value)) {
        $stmt->bindValue($param, $value ? 1 : 0, SQLITE3_INTEGER);
        return;
    }

    if (is_int($value)) {
        $stmt->bindValue($param, $value, SQLITE3_INTEGER);
        return;
    }

    if (is_float($value)) {
        $stmt->bindValue($param, $value, SQLITE3_FLOAT);
        return;
    }

    if (is_array($value) || is_object($value)) {
        $stmt->bindValue($param, json_encode($value, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        return;
    }

    $stmt->bindValue($param, (string)$value, SQLITE3_TEXT);
}

function settingsInsertTableRows(AppDbConnection $db, string $table, array $rows, int $cid, bool $forceCid): void {
    $table = settingsSafeTable($table);
    $columns = settingsTableColumns($db, $table);
    if (empty($columns)) {
        return;
    }

    $columnSet = array_fill_keys($columns, true);
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $filtered = [];
        foreach ($row as $key => $value) {
            $col = (string)$key;
            if (!isset($columnSet[$col])) {
                continue;
            }

            // Skip computed/generated columns.
            if ($col === 'total') {
                continue;
            }

            $filtered[$col] = $value;
        }

        if ($forceCid && isset($columnSet['cid'])) {
            $filtered['cid'] = $cid;
        }

        if (empty($filtered)) {
            continue;
        }

        $insertCols = array_keys($filtered);
        $placeholders = [];
        foreach ($insertCols as $idx => $_) {
            $placeholders[] = ':p' . $idx;
        }

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare insert for table: ' . $table);
        }

        foreach ($insertCols as $idx => $col) {
            settingsBindDynamicValue($stmt, ':p' . $idx, $filtered[$col]);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to insert row into table: ' . $table);
        }
    }
}

function settingsDeleteTenantRows(AppDbConnection $db, int $cid): void {
    foreach (settingsTenantSubTables() as $cfg) {
        $table = settingsSafeTable((string)$cfg['table']);
        $parent = settingsSafeTable((string)$cfg['parent']);
        $parentPk = settingsSafeIdentifier((string)$cfg['parent_pk']);
        $childFk = settingsSafeIdentifier((string)$cfg['child_fk']);

        if (!settingsTableExists($db, $table) || !settingsTableExists($db, $parent)) {
            continue;
        }

        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $childFk . ' IN (SELECT ' . $parentPk . ' FROM ' . $parent . ' WHERE cid = :cid)';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare delete for table: ' . $table);
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to delete tenant rows for table: ' . $table);
        }
    }

    $tables = array_reverse(settingsTenantDirectTables());
    foreach ($tables as $table) {
        $table = settingsSafeTable($table);
        if (!settingsTableExists($db, $table) || !settingsTableHasColumn($db, $table, 'cid')) {
            continue;
        }

        $stmt = $db->prepare('DELETE FROM ' . $table . ' WHERE cid = :cid');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare delete for table: ' . $table);
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to delete tenant rows for table: ' . $table);
        }
    }
}

function settingsRestoreTenantBackup(AppDbConnection $db, int $cid, array $payload, ?string &$error = null): bool {
    $tables = $payload['tables'] ?? [];
    if (!is_array($tables)) {
        $error = 'Invalid backup payload tables section';
        return false;
    }

    $isSqlite = strtolower($db->driver()) === 'sqlite';

    try {
        if ($isSqlite) {
            $db->exec('PRAGMA foreign_keys = OFF');
        }

        $db->exec('BEGIN');
        settingsDeleteTenantRows($db, $cid);

        foreach (settingsTenantDirectTables() as $table) {
            if (!isset($tables[$table]) || !is_array($tables[$table])) {
                continue;
            }

            settingsInsertTableRows($db, $table, $tables[$table], $cid, true);
        }

        foreach (settingsTenantSubTables() as $cfg) {
            $table = (string)$cfg['table'];
            if (!isset($tables[$table]) || !is_array($tables[$table])) {
                continue;
            }

            settingsInsertTableRows($db, $table, $tables[$table], $cid, false);
        }

        $db->exec('COMMIT');
        if ($isSqlite) {
            $db->exec('PRAGMA foreign_keys = ON');
        }

        return true;
    } catch (Throwable $e) {
        try {
            $db->exec('ROLLBACK');
        } catch (Throwable $_rollbackError) {
            // Ignore rollback errors.
        }

        if ($isSqlite) {
            try {
                $db->exec('PRAGMA foreign_keys = ON');
            } catch (Throwable $_fkError) {
                // Ignore pragma errors.
            }
        }

        $error = $e->getMessage();
        return false;
    }
}

function settingsBackupMeta(string $path): array {
    $filename = basename($path);
    $isAuto = strpos($filename, 'auto-') !== false;
    return [
        'filename' => $filename,
        'size' => is_file($path) ? intval(filesize($path)) : 0,
        'created_at' => is_file($path) ? intval(filemtime($path)) : 0,
        'is_auto' => $isAuto,
    ];
}

function settingsBuildEncryptedBackupPayload(string $plainData, string $passphrase): ?string {
    if (!function_exists('openssl_encrypt')) {
        return null;
    }

    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    if (!is_int($ivLength) || $ivLength <= 0) {
        return null;
    }

    try {
        $iv = random_bytes($ivLength);
    } catch (Throwable $e) {
        return null;
    }

    $key = hash('sha256', $passphrase, true);
    $tag = '';
    $cipherText = openssl_encrypt($plainData, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipherText === false) {
        return null;
    }

    if (!is_string($tag) || strlen($tag) < 12) {
        return null;
    }

    return "TMENC2\n" . base64_encode($iv) . "\n" . base64_encode($tag) . "\n" . base64_encode($cipherText);
}

function settingsDecryptEncryptedBackupPayload(string $payload, string $passphrase): ?string {
    if (!function_exists('openssl_decrypt')) {
        return null;
    }

    $parts = preg_split('/\r\n|\n|\r/', $payload);
    if (!is_array($parts) || count($parts) < 3) {
        return null;
    }

    $version = trim((string)$parts[0]);

    if ($version === 'TMENC2') {
        if (count($parts) < 4) {
            return null;
        }

        $ivB64 = trim((string)$parts[1]);
        $tagB64 = trim((string)$parts[2]);
        $cipherB64 = trim((string)$parts[3]);
        if ($ivB64 === '' || $tagB64 === '' || $cipherB64 === '') {
            return null;
        }

        $iv = base64_decode($ivB64, true);
        $tag = base64_decode($tagB64, true);
        $cipherText = base64_decode($cipherB64, true);
        if (!is_string($iv) || !is_string($tag) || !is_string($cipherText)) {
            return null;
        }

        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        if (!is_int($ivLength) || $ivLength <= 0 || strlen($iv) !== $ivLength) {
            return null;
        }

        $key = hash('sha256', $passphrase, true);
        $plain = openssl_decrypt($cipherText, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : null;
    }

    // Backward compatibility for older encrypted backups.
    if ($version === 'TMENC1') {
        $ivB64 = trim((string)$parts[1]);
        $cipherB64 = trim((string)$parts[2]);
        if ($ivB64 === '' || $cipherB64 === '') {
            return null;
        }

        $iv = base64_decode($ivB64, true);
        $cipherText = base64_decode($cipherB64, true);
        if (!is_string($iv) || !is_string($cipherText)) {
            return null;
        }

        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        if (!is_int($ivLength) || $ivLength <= 0 || strlen($iv) !== $ivLength) {
            return null;
        }

        $key = hash('sha256', $passphrase, true);
        $plain = openssl_decrypt($cipherText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? $plain : null;
    }

    return null;
}

function settingsApplyAutoBackupRetention(int $cid): void {
    $dir = settingsBackupDir();
    $autoPrefix = settingsAutoBackupPrefix($cid);
    $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $autoPrefix . '*.json');
    $files = is_array($matches) ? $matches : [];

    usort($files, function (string $a, string $b): int {
        return intval(@filemtime($b)) <=> intval(@filemtime($a));
    });

    $keep = settingsBackupRetentionDays();
    if (count($files) <= $keep) {
        return;
    }

    foreach (array_slice($files, $keep) as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

function settingsRunDailyAutoBackup(AppDbConnection $db, int $cid): void {
    $dir = settingsBackupDir();
    $todayPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . settingsAutoBackupPrefix($cid) . date('Ymd') . '.json';
    if (!is_file($todayPath)) {
        $created = settingsCreateBackupFile($db, $cid, true);
        if ($created !== null && is_file($created)) {
            settingsAuditBackupEvent(
                $db,
                $cid,
                null,
                'auto_backup_created',
                basename($created),
                intval(filesize($created) ?: 0)
            );
        }
    }

    settingsApplyAutoBackupRetention($cid);
}

ensureRbacSchemaForSettings($db);
seedRolesAndPermissionsForSettings($db, $cid);
settingsEnsureBackupAuditTable($db);

$settingsStrictCsrfActions = [
    'updateProfile',
    'createUser',
    'updateUserDetails',
    'updateUserRole',
    'toggleUserStatus',
    'revokeSession',
    'createBackup',
    'downloadEncryptedBackup',
    'restoreEncryptedBackup',
    'restoreBackup',
    'seedDemoUsers'
];

if (is_string($action) && in_array($action, $settingsStrictCsrfActions, true)) {
    settingsVerifyCsrfToken();
}

switch ($action) {
    case 'getBackupCapability':
        requirePermission($db, 'manage_users');
        respond('success', 'Backup capability loaded', [
            'data' => settingsBackupCapability($db, $cid)
        ]);
        break;

    case 'loadSettings':
        $stmt = $db->prepare("SELECT c.cid,
                                     c.cName,
                                     c.cEmail,
                                     c.cLogo,
                                     c.regDate
                              FROM company c
                              WHERE c.cid = :cid
                              LIMIT 1");
        if (!$stmt) {
            respond('error', 'Failed to load settings');
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            respond('error', 'Company not found');
        }
        respond('success', 'Settings loaded', ['data' => $row]);
        break;

    case 'updateProfile':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_update_profile', 20, 300);
        $name = safe_input($_POST['cName'] ?? '');
        $email = strtolower(safe_input($_POST['cEmail'] ?? ''));

        if ($name === '') {
            respond('error', 'Company name is required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond('error', 'Valid company email is required');
        }

        $checkName = $db->prepare("SELECT 1 FROM company WHERE lower(cName) = lower(:cName) AND cid != :cid LIMIT 1");
        $checkName->bindValue(':cName', $name, SQLITE3_TEXT);
        $checkName->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($checkName->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Company name already exists');
        }

        $checkEmail = $db->prepare("SELECT 1 FROM company WHERE lower(cEmail) = lower(:cEmail) AND cid != :cid LIMIT 1");
        $checkEmail->bindValue(':cEmail', $email, SQLITE3_TEXT);
        $checkEmail->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($checkEmail->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Company email already exists');
        }

        $logoStmt = $db->prepare("SELECT cLogo FROM company WHERE cid = :cid LIMIT 1");
        $logoStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $current = $logoStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$current) {
            respond('error', 'Company not found');
        }

        $logo = handleFileUpload('companyLogo', $current['cLogo'] ?? 'logo.jpg');

        $stmt = $db->prepare("UPDATE company
                              SET cName = :cName,
                                  cEmail = :cEmail,
                                  cLogo = :cLogo
                              WHERE cid = :cid");
        if (!$stmt) {
            respond('error', 'Failed to prepare company update');
        }
        $stmt->bindValue(':cName', $name, SQLITE3_TEXT);
        $stmt->bindValue(':cEmail', $email, SQLITE3_TEXT);
        $stmt->bindValue(':cLogo', $logo ?: 'logo.jpg', SQLITE3_TEXT);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            respond('error', 'Failed to update company profile');
        }

        respond('success', 'Company profile updated successfully');
        break;

    case 'loadRoles':
        requirePermission($db, 'manage_users');
        $roles = [];
        $stmt = $db->prepare("SELECT role_id, role_name
                              FROM roles
                              WHERE cid = :cid
                              ORDER BY CASE lower(role_name)
                                  WHEN 'owner' THEN 1
                                  WHEN 'manager' THEN 2
                                  WHEN 'staff' THEN 3
                                  ELSE 4 END, role_name ASC");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $roles[] = [
                'role_id' => intval($row['role_id']),
                'role_name' => $row['role_name'] ?? ''
            ];
        }

        respond('success', 'Roles loaded', ['data' => $roles]);
        break;

    case 'loadUsers':
        requirePermission($db, 'manage_users');
        $search = strtolower(trim((string)($_POST['search'] ?? '')));
        $pager = settingsPaginationFromRequest(10, 50);

        $whereSql = 'u.cid = :cid';
        if ($search !== '') {
            $whereSql .= ' AND (lower(u.full_name) LIKE :search OR lower(u.email) LIKE :search)';
        }

        $countStmt = $db->prepare("SELECT COUNT(*) AS total
                                   FROM users u
                                   WHERE " . $whereSql);
        if (!$countStmt) {
            respond('error', 'Failed to load users');
        }
        $countStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($search !== '') {
            $countStmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $countRow = $countStmt->execute()->fetchArray(SQLITE3_ASSOC);
        $totalItems = intval($countRow['total'] ?? 0);

        $pagination = settingsBuildPagination($totalItems, $pager['page'], $pager['per_page']);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $users = [];
        $stmt = $db->prepare("SELECT u.user_id,
                                     u.full_name,
                                     u.email,
                                     u.is_active,
                                     u.created_at,
                                     COALESCE(r.role_id, 0) AS role_id,
                                     COALESCE(r.role_name, 'Unassigned') AS role_name
                              FROM users u
                              LEFT JOIN user_roles ur ON ur.user_id = u.user_id
                              LEFT JOIN roles r ON r.role_id = ur.role_id
                              WHERE " . $whereSql . "
                              ORDER BY u.created_at DESC, u.user_id DESC
                              LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $pagination['per_page'], SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $users[] = [
                'user_id' => intval($row['user_id']),
                'full_name' => $row['full_name'] ?? '',
                'email' => $row['email'] ?? '',
                'is_active' => intval($row['is_active'] ?? 0),
                'created_at' => toEpochInt($row['created_at'] ?? 0),
                'role_id' => intval($row['role_id'] ?? 0),
                'role_name' => $row['role_name'] ?? 'Unassigned'
            ];
        }

        respond('success', 'Users loaded', ['data' => $users, 'pagination' => $pagination]);
        break;

    case 'createUser':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_create_user', 15, 300);
        $fullName = safe_input($_POST['full_name'] ?? '');
        $email = strtolower(safe_input($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $roleId = intval($_POST['role_id'] ?? 0);

        if ($fullName === '') {
            respond('error', 'Full name is required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond('error', 'Valid email is required');
        }

        $passwordPolicyError = settingsPasswordPolicyError($password);
        if ($passwordPolicyError !== '') {
            respond('error', $passwordPolicyError);
        }
        if ($roleId <= 0) {
            respond('error', 'Role is required');
        }

        $roleStmt = $db->prepare("SELECT role_id FROM roles WHERE role_id = :rid AND cid = :cid LIMIT 1");
        $roleStmt->bindValue(':rid', $roleId, SQLITE3_INTEGER);
        $roleStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $role = $roleStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$role) {
            respond('error', 'Invalid role selected');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $db->prepare("INSERT INTO users (
                                cid, full_name, email, password, is_active,
                                email_verified_at, email_verification_token_hash, email_verification_expires_at
                            )
                            VALUES (
                                :cid, :full_name, :email, :password, 1,
                                :email_verified_at, NULL, NULL
                            )");
        $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $ins->bindValue(':full_name', $fullName, SQLITE3_TEXT);
        $ins->bindValue(':email', $email, SQLITE3_TEXT);
        $ins->bindValue(':password', $hash, SQLITE3_TEXT);
        $ins->bindValue(':email_verified_at', time(), SQLITE3_INTEGER);

        if (!$ins->execute()) {
            respond('error', 'Failed to create user (email may already exist)');
        }

        $newUserId = intval($db->lastInsertRowID());
        if (!assignSingleRoleToUser($db, $newUserId, $roleId)) {
            respond('error', 'User created but role assignment failed');
        }

        respond('success', 'User created successfully');
        break;

    case 'updateUserDetails':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_update_user_details', 20, 300);
        $userId = intval($_POST['user_id'] ?? 0);
        $fullName = safe_input($_POST['full_name'] ?? '');
        $email = strtolower(safe_input($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = intval($_POST['role_id'] ?? 0);

        if ($userId <= 0) {
            respond('error', 'User is required');
        }
        if ($fullName === '') {
            respond('error', 'Full name is required');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond('error', 'Valid email is required');
        }
        if ($roleId <= 0) {
            respond('error', 'Role is required');
        }

        $userStmt = $db->prepare("SELECT user_id, full_name, email FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
        $userStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $userStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $currentUser = $userStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$currentUser) {
            respond('error', 'User not found');
        }

        $roleStmt = $db->prepare("SELECT role_id FROM roles WHERE role_id = :rid AND cid = :cid LIMIT 1");
        $roleStmt->bindValue(':rid', $roleId, SQLITE3_INTEGER);
        $roleStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$roleStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Invalid role selected');
        }

        $emailStmt = $db->prepare("SELECT 1 FROM users WHERE cid = :cid AND lower(email) = lower(:email) AND user_id != :uid LIMIT 1");
        $emailStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $emailStmt->bindValue(':email', $email, SQLITE3_TEXT);
        $emailStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        if ($emailStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Email already exists');
        }

        $targetIsOwner = settingsUserIsOwner($db, $cid, $userId);
        $newRoleIsOwner = settingsRoleIdIsOwner($db, $cid, $roleId);
        if ($targetIsOwner && !$newRoleIsOwner) {
            $ownerCount = settingsCountOwners($db, $cid, false);
            if ($ownerCount <= 1) {
                respond('error', 'Cannot remove the last Owner role.');
            }
        }

        $fields = [
            'full_name = :full_name',
            'email = :email'
        ];
        $params = [
            ':full_name' => $fullName,
            ':email' => $email
        ];

        $newPassword = trim($password);
        if ($newPassword !== '') {
            $passwordPolicyError = settingsPasswordPolicyError($newPassword);
            if ($passwordPolicyError !== '') {
                respond('error', $passwordPolicyError);
            }
            $fields[] = 'password = :password';
            $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE user_id = :uid AND cid = :cid';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $stmt->bindValue(':full_name', $fullName, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        if (isset($params[':password'])) {
            $stmt->bindValue(':password', $params[':password'], SQLITE3_TEXT);
        }

        if (!$stmt->execute()) {
            respond('error', 'Failed to update user details');
        }

        if (!assignSingleRoleToUser($db, $userId, $roleId)) {
            respond('error', 'User updated but role assignment failed');
        }

        respond('success', 'User details updated successfully');
        break;

    case 'updateUserRole':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_update_user_role', 30, 300);
        $userId = intval($_POST['user_id'] ?? 0);
        $roleId = intval($_POST['role_id'] ?? 0);

        if ($userId <= 0 || $roleId <= 0) {
            respond('error', 'User and role are required');
        }

        $userStmt = $db->prepare("SELECT user_id FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
        $userStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $userStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$userStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'User not found');
        }

        $roleStmt = $db->prepare("SELECT role_id FROM roles WHERE role_id = :rid AND cid = :cid LIMIT 1");
        $roleStmt->bindValue(':rid', $roleId, SQLITE3_INTEGER);
        $roleStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$roleStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Role not found');
        }

        $targetIsOwner = settingsUserIsOwner($db, $cid, $userId);
        $newRoleIsOwner = settingsRoleIdIsOwner($db, $cid, $roleId);
        if ($targetIsOwner && !$newRoleIsOwner) {
            $ownerCount = settingsCountOwners($db, $cid, false);
            if ($ownerCount <= 1) {
                respond('error', 'Cannot remove the last Owner role.');
            }
        }

        if (!assignSingleRoleToUser($db, $userId, $roleId)) {
            respond('error', 'Failed to update user role');
        }

        respond('success', 'User role updated');
        break;

    case 'toggleUserStatus':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_toggle_user_status', 30, 300);
        $userId = intval($_POST['user_id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $currentUserId = intval($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            respond('error', 'User is required');
        }

        if ($currentUserId > 0 && $userId === $currentUserId && $isActive === 0) {
            respond('error', 'You cannot deactivate your own account');
        }

        if ($isActive === 0 && settingsUserIsOwner($db, $cid, $userId)) {
            $activeOwnerCount = settingsCountOwners($db, $cid, true);
            if ($activeOwnerCount <= 1) {
                respond('error', 'Cannot deactivate the last active Owner.');
            }
        }

        $stmt = $db->prepare("UPDATE users
                              SET is_active = :is_active
                              WHERE user_id = :uid AND cid = :cid");
        $stmt->bindValue(':is_active', $isActive, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            respond('error', 'Failed to update user status');
        }

        respond('success', $isActive ? 'User activated' : 'User deactivated');
        break;

    case 'seedDemoUsers':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_seed_demo_users', 3, 300);
        $managerPassword = 'Manager123!';
        $staffPassword = 'Staff123!';

        $domain = 'company.local';
        $cStmt = $db->prepare("SELECT cEmail FROM company WHERE cid = :cid LIMIT 1");
        $cStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $company = $cStmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($company && !empty($company['cEmail']) && strpos($company['cEmail'], '@') !== false) {
            $parts = explode('@', strtolower($company['cEmail']));
            $domain = trim($parts[1] ?? 'company.local');
        }

        $managerEmail = 'manager.' . $cid . '@' . $domain;
        $staffEmail = 'staff.' . $cid . '@' . $domain;

        $roleStmt = $db->prepare("SELECT role_id, role_name FROM roles WHERE cid = :cid");
        $roleStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $roleRes = $roleStmt->execute();
        $roleMap = [];
        while ($roleRow = $roleRes->fetchArray(SQLITE3_ASSOC)) {
            $roleMap[strtolower($roleRow['role_name'])] = intval($roleRow['role_id']);
        }

        if (!isset($roleMap['manager']) || !isset($roleMap['staff'])) {
            respond('error', 'Manager/Staff roles are not available');
        }

        $seeded = [];

        $seedUser = function (string $name, string $email, string $password, int $roleId) use ($db, $cid, &$seeded) {
            $find = $db->prepare("SELECT user_id FROM users WHERE cid = :cid AND lower(email) = lower(:email) LIMIT 1");
            $find->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $find->bindValue(':email', $email, SQLITE3_TEXT);
            $existing = $find->execute()->fetchArray(SQLITE3_ASSOC);

            $userId = intval($existing['user_id'] ?? 0);
            if ($userId <= 0) {
                $ins = $db->prepare("INSERT INTO users (
                                        cid, full_name, email, password, is_active,
                                        email_verified_at, email_verification_token_hash, email_verification_expires_at
                                    )
                                    VALUES (
                                        :cid, :full_name, :email, :password, 1,
                                        :email_verified_at, NULL, NULL
                                    )");
                $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $ins->bindValue(':full_name', $name, SQLITE3_TEXT);
                $ins->bindValue(':email', $email, SQLITE3_TEXT);
                $ins->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
                $ins->bindValue(':email_verified_at', time(), SQLITE3_INTEGER);
                if ($ins->execute()) {
                    $userId = intval($db->lastInsertRowID());
                }
            }

            if ($userId > 0) {
                assignSingleRoleToUser($db, $userId, $roleId);
                $seeded[] = ['email' => $email, 'password' => $password];
            }
        };

        $seedUser('Demo Manager', $managerEmail, $managerPassword, $roleMap['manager']);
        $seedUser('Demo Staff', $staffEmail, $staffPassword, $roleMap['staff']);

        respond('success', 'Demo users are ready', ['data' => $seeded]);
        break;

    case 'loadRememberAudit':
        requirePermission($db, 'manage_users');
        $rows = [];
        $stmt = $db->prepare("SELECT a.id,
                                     a.event_type,
                                     a.user_id,
                                     a.cid,
                                     a.ip_address,
                                     a.user_agent,
                                     a.details,
                                     a.created_at,
                                     COALESCE(u.full_name, '-') AS full_name,
                                     COALESCE(u.email, '-') AS email
                              FROM remember_token_audit a
                              LEFT JOIN users u ON u.user_id = a.user_id
                              WHERE a.cid = :cid
                              ORDER BY a.created_at DESC, a.id DESC
                              LIMIT 50");
        if (!$stmt) {
            respond('error', 'Failed to load remember audit logs');
        }

        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'id' => intval($row['id'] ?? 0),
                'event_type' => (string)($row['event_type'] ?? ''),
                'user_id' => intval($row['user_id'] ?? 0),
                'full_name' => (string)($row['full_name'] ?? '-'),
                'email' => (string)($row['email'] ?? '-'),
                'ip_address' => (string)($row['ip_address'] ?? '-'),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'details' => (string)($row['details'] ?? ''),
                'created_at' => toEpochInt($row['created_at'] ?? 0)
            ];
        }

        respond('success', 'Remember audit logs loaded', ['data' => $rows]);
        break;

    case 'loadActiveSessions':
        requirePermission($db, 'manage_users');
        $search = strtolower(trim((string)($_POST['search'] ?? '')));
        $pager = settingsPaginationFromRequest(10, 50);

        $whereSql = 's.cid = :cid';
        if ($search !== '') {
            $whereSql .= ' AND (
                lower(COALESCE(u.full_name, "")) LIKE :search OR
                lower(COALESCE(u.email, "")) LIKE :search OR
                lower(COALESCE(s.ip_address, "")) LIKE :search OR
                lower(COALESCE(s.user_agent, "")) LIKE :search OR
                lower(COALESCE(s.session_id, "")) LIKE :search
            )';
        }

        $countStmt = $db->prepare("SELECT COUNT(*) AS total
                                   FROM user_sessions s
                                   LEFT JOIN users u ON u.user_id = s.user_id
                                   WHERE " . $whereSql);
        if (!$countStmt) {
            respond('error', 'Failed to load active sessions');
        }
        $countStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($search !== '') {
            $countStmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $countRow = $countStmt->execute()->fetchArray(SQLITE3_ASSOC);
        $totalItems = intval($countRow['total'] ?? 0);

        $pagination = settingsBuildPagination($totalItems, $pager['page'], $pager['per_page']);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $sessions = [];
        $currentSessionId = session_id();

        $stmt = $db->prepare("SELECT s.id,
                                     s.user_id,
                                     s.session_id,
                                     s.ip_address,
                                     s.user_agent,
                                     s.last_activity,
                                     s.created_at,
                                     COALESCE(u.full_name, '-') AS full_name,
                                     COALESCE(u.email, '-') AS email
                              FROM user_sessions s
                              LEFT JOIN users u ON u.user_id = s.user_id
                              WHERE " . $whereSql . "
                              ORDER BY s.last_activity DESC, s.created_at DESC
                              LIMIT :limit OFFSET :offset");
        if (!$stmt) {
            respond('error', 'Failed to load active sessions');
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $pagination['per_page'], SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $res = $stmt->execute();

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $sessionId = (string)($row['session_id'] ?? '');
            $sessions[] = [
                'id' => intval($row['id'] ?? 0),
                'user_id' => intval($row['user_id'] ?? 0),
                'session_id' => $sessionId,
                'full_name' => (string)($row['full_name'] ?? '-'),
                'email' => (string)($row['email'] ?? '-'),
                'ip_address' => (string)($row['ip_address'] ?? '-'),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'last_activity' => toEpochInt($row['last_activity'] ?? 0),
                'created_at' => toEpochInt($row['created_at'] ?? 0),
                'is_current' => ($sessionId !== '' && hash_equals($currentSessionId, $sessionId))
            ];
        }

        respond('success', 'Active sessions loaded', ['data' => $sessions, 'pagination' => $pagination]);
        break;

    case 'revokeSession':
        requirePermission($db, 'manage_users');
        settingsEnforceRateLimit('settings_revoke_session', 40, 300);
        $sessionId = trim((string)($_POST['session_id'] ?? ''));
        if ($sessionId === '') {
            respond('error', 'Session ID is required');
        }

        $find = $db->prepare("SELECT user_id, cid FROM user_sessions WHERE session_id = :sid LIMIT 1");
        if (!$find) {
            respond('error', 'Failed to find session');
        }
        $find->bindValue(':sid', $sessionId, SQLITE3_TEXT);
        $sessionRow = $find->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$sessionRow) {
            respond('error', 'Session not found');
        }

        if (intval($sessionRow['cid'] ?? 0) !== $cid) {
            respond('error', 'Unauthorized');
        }

        $del = $db->prepare("DELETE FROM user_sessions WHERE session_id = :sid AND cid = :cid");
        if (!$del) {
            respond('error', 'Failed to revoke session');
        }
        $del->bindValue(':sid', $sessionId, SQLITE3_TEXT);
        $del->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$del->execute()) {
            respond('error', 'Failed to revoke session');
        }

        settingsDeleteSessionFile($sessionId);

        respond('success', 'Session revoked', [
            'revoked_session_id' => $sessionId,
            'is_current' => hash_equals(session_id(), $sessionId)
        ]);
        break;

    case 'loadLoginLogs':
        requirePermission($db, 'manage_users');
        $allowedStatuses = ['all', 'success', 'failed', 'blocked', 'success_auto', 'failed_auto'];
        $status = strtolower(trim((string)($_POST['status'] ?? 'all')));
        $search = strtolower(trim((string)($_POST['search'] ?? '')));
        $pager = settingsPaginationFromRequest(10, 50);
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $whereSql = 'l.cid = :cid';
        if ($status !== 'all') {
            $whereSql .= ' AND lower(l.status) = lower(:status)';
        }
        if ($search !== '') {
            $whereSql .= ' AND (
                lower(COALESCE(u.full_name, "")) LIKE :search OR
                lower(COALESCE(u.email, "")) LIKE :search OR
                lower(COALESCE(l.ip_address, "")) LIKE :search OR
                lower(COALESCE(l.user_agent, "")) LIKE :search
            )';
        }

        $countStmt = $db->prepare("SELECT COUNT(*) AS total
                                   FROM login_logs l
                                   LEFT JOIN users u ON u.user_id = l.user_id
                                   WHERE " . $whereSql);
        if (!$countStmt) {
            respond('error', 'Failed to load login logs');
        }
        $countStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($status !== 'all') {
            $countStmt->bindValue(':status', $status, SQLITE3_TEXT);
        }
        if ($search !== '') {
            $countStmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $countRow = $countStmt->execute()->fetchArray(SQLITE3_ASSOC);
        $totalItems = intval($countRow['total'] ?? 0);

        $pagination = settingsBuildPagination($totalItems, $pager['page'], $pager['per_page']);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];

        $rows = [];
        $stmt = $db->prepare("SELECT l.id,
                                         l.user_id,
                                         l.ip_address,
                                         l.user_agent,
                                         l.login_time,
                                         l.status,
                                         COALESCE(u.full_name, '-') AS full_name,
                                         COALESCE(u.email, '-') AS email
                                  FROM login_logs l
                                  LEFT JOIN users u ON u.user_id = l.user_id
                                  WHERE " . $whereSql . "
                                  ORDER BY l.login_time DESC, l.id DESC
                                  LIMIT :limit OFFSET :offset");
        if (!$stmt) {
            respond('error', 'Failed to load login logs');
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if ($status !== 'all') {
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        }
        if ($search !== '') {
            $stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $pagination['per_page'], SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);

        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'id' => intval($row['id'] ?? 0),
                'user_id' => intval($row['user_id'] ?? 0),
                'full_name' => (string)($row['full_name'] ?? '-'),
                'email' => (string)($row['email'] ?? '-'),
                'ip_address' => (string)($row['ip_address'] ?? '-'),
                'user_agent' => (string)($row['user_agent'] ?? ''),
                'login_time' => intval($row['login_time'] ?? 0),
                'status' => (string)($row['status'] ?? '-')
            ];
        }

        respond('success', 'Login logs loaded', ['data' => $rows, 'pagination' => $pagination]);
        break;

    case 'createBackup':
        settingsRequireBackupAccess($db, $cid);
        settingsEnforceRateLimit('settings_create_backup', 10, 300);

        $targetPath = settingsCreateBackupFile($db, $cid, false);
        if ($targetPath === null) {
            respond('error', 'Failed to create backup file. Check backup storage permissions and path.');
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'manual_backup_created',
            basename($targetPath),
            intval(filesize($targetPath) ?: 0)
        );

        settingsApplyAutoBackupRetention($cid);

        respond('success', 'Backup created successfully', [
            'data' => settingsBackupMeta($targetPath)
        ]);
        break;

    case 'loadBackups':
        settingsRequireBackupAccess($db, $cid);
        $search = strtolower(trim((string)($_POST['search'] ?? '')));
        $pager = settingsPaginationFromRequest(10, 50);

        $dir = settingsBackupDir();
        $prefix = settingsBackupPrefix($cid);
        $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '*.json');
        $files = is_array($matches) ? $matches : [];

        usort($files, function (string $a, string $b): int {
            return intval(@filemtime($b)) <=> intval(@filemtime($a));
        });

        $allEntries = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $allEntries[] = settingsBackupMeta($file);
            }
        }

        $filteredEntries = $allEntries;
        if ($search !== '') {
            $filteredEntries = array_values(array_filter($allEntries, function (array $entry) use ($search): bool {
                return strpos(strtolower((string)($entry['filename'] ?? '')), $search) !== false;
            }));
        }

        $totalItems = count($filteredEntries);
        $pagination = settingsBuildPagination($totalItems, $pager['page'], $pager['per_page']);
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];
        $rows = array_slice($filteredEntries, $offset, $pagination['per_page']);

        $autoPrefix = settingsAutoBackupPrefix($cid);
        $latestAuto = null;
        foreach ($allEntries as $entry) {
            if (strpos((string)$entry['filename'], $autoPrefix) === 0) {
                $latestAuto = $entry;
                break;
            }
        }

        respond('success', 'Backups loaded', [
            'data' => $rows,
            'pagination' => $pagination,
            'retention_days' => settingsBackupRetentionDays(),
            'last_auto_backup_created_at' => $latestAuto['created_at'] ?? 0,
            'storage_path' => settingsBackupDir(),
            'scheduler_hint' => settingsBackupSchedulerHint(),
        ]);
        break;

    case 'downloadBackup':
        settingsRequireBackupAccess($db, $cid);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        $json = $filename !== '' ? settingsReadBackupFilePayload($cid, $filename) : null;
        if (!is_string($json)) {
            $payload = settingsExportTenantBackupData($db, $cid);
            $json = settingsEncodeBackupPayload($payload);
            if (!is_string($json)) {
                respond('error', 'Failed to generate backup payload');
            }

            if ($filename === '') {
                $filename = settingsBuildBackupFilename($cid, false);
            }
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_downloaded',
            $filename,
            strlen($json)
        );

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $json;
        exit;

    case 'downloadEncryptedBackup':
        settingsRequireBackupAccess($db, $cid);
        settingsEnforceRateLimit('settings_download_encrypted_backup', 10, 300);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        $passphrase = (string)($_POST['passphrase'] ?? '');

        if ($filename === '') {
            respond('error', 'Backup filename is required');
        }
        if (strlen($passphrase) < 8) {
            respond('error', 'Passphrase must be at least 8 characters');
        }

        $plain = settingsReadBackupFilePayload($cid, $filename);
        if (!is_string($plain)) {
            $payloadData = settingsExportTenantBackupData($db, $cid);
            $plain = settingsEncodeBackupPayload($payloadData);
        }
        if (!is_string($plain)) {
            respond('error', 'Failed to read backup file');
        }

        $payload = settingsBuildEncryptedBackupPayload($plain, $passphrase);
        if ($payload === null) {
            respond('error', 'Failed to encrypt backup. OpenSSL support may be unavailable.');
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_encrypted_downloaded',
            $filename,
            strlen($payload)
        );

        $downloadName = preg_replace('/\.json$/i', '.json.enc', $filename);
        if (!is_string($downloadName) || $downloadName === '') {
            $downloadName = settingsBuildBackupFilename($cid, false) . '.enc';
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . strlen($payload));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $payload;
        exit;

    case 'restoreEncryptedBackup':
        settingsRequireBackupAccess($db, $cid);
        settingsEnforceRateLimit('settings_restore_encrypted_backup', 5, 300);

        $passphrase = (string)($_POST['passphrase'] ?? '');
        if (strlen($passphrase) < 8) {
            respond('error', 'Passphrase must be at least 8 characters');
        }

        $file = $_FILES['encryptedBackupFile'] ?? null;
        if (!is_array($file) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond('error', 'Encrypted backup file is required');
        }
        settingsValidateUploadedBackupSize($file);

        $originalName = basename((string)($file['name'] ?? ''));
        if ($originalName === '' || preg_match('/\.json\.enc$/i', $originalName) !== 1) {
            respond('error', 'Invalid encrypted backup file name');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            respond('error', 'Uploaded encrypted backup is unavailable');
        }

        $payload = @file_get_contents($tmpPath);
        if (!is_string($payload) || trim($payload) === '') {
            respond('error', 'Failed to read encrypted backup file');
        }

        $plain = settingsDecryptEncryptedBackupPayload($payload, $passphrase);
        if (!is_string($plain)) {
            respond('error', 'Failed to decrypt backup. Check your passphrase and file.');
        }

        $parseError = null;
        $parsed = settingsParseBackupPayload($plain, $cid, $parseError);
        if (!is_array($parsed)) {
            respond('error', $parseError ?: 'Invalid decrypted backup payload');
        }

        $restoreError = null;
        if (!settingsRestoreTenantBackup($db, $cid, $parsed, $restoreError)) {
            respond('error', 'Failed to restore decrypted backup' . ($restoreError ? ': ' . $restoreError : ''));
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_encrypted_restored',
            $originalName,
            strlen($plain),
            ['format' => 'tm-tenant-backup-v1']
        );

        respond('success', 'Encrypted backup restored successfully');
        break;

    case 'restoreBackup':
        settingsRequireBackupAccess($db, $cid);
        settingsEnforceRateLimit('settings_restore_backup', 5, 300);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        $jsonPayload = null;

        if ($filename !== '') {
            $jsonPayload = settingsReadBackupFilePayload($cid, $filename);
            if (!is_string($jsonPayload)) {
                respond('error', 'Backup file not found or invalid');
            }
        } else {
            $file = $_FILES['backupFile'] ?? null;
            if (!is_array($file) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                respond('error', 'Backup JSON file is required');
            }
            settingsValidateUploadedBackupSize($file);

            $originalName = basename((string)($file['name'] ?? ''));
            if ($originalName === '' || preg_match('/\.json$/i', $originalName) !== 1) {
                respond('error', 'Invalid backup file name. Expected .json file.');
            }

            $tmpPath = (string)($file['tmp_name'] ?? '');
            if ($tmpPath === '' || !is_file($tmpPath)) {
                respond('error', 'Uploaded backup file is unavailable');
            }

            $jsonPayload = @file_get_contents($tmpPath);
            $filename = $originalName;
            if (!is_string($jsonPayload) || trim($jsonPayload) === '') {
                respond('error', 'Failed to read uploaded backup file');
            }
        }

        $parseError = null;
        $payload = settingsParseBackupPayload($jsonPayload, $cid, $parseError);
        if (!is_array($payload)) {
            respond('error', $parseError ?: 'Invalid backup payload');
        }

        $restoreError = null;
        if (!settingsRestoreTenantBackup($db, $cid, $payload, $restoreError)) {
            respond('error', 'Failed to restore backup' . ($restoreError ? ': ' . $restoreError : ''));
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_restored',
            $filename,
            strlen($jsonPayload),
            ['format' => 'tm-tenant-backup-v1']
        );

        respond('success', 'Backup restored successfully');
        break;

    case 'loadBackupAudit':
        settingsRequireBackupAccess($db, $cid);

        $rows = [];
        $stmt = $db->prepare("SELECT a.id,
                                     a.event_type,
                                     a.filename,
                                     a.size_bytes,
                                     a.ip_address,
                                     a.created_at,
                                     COALESCE(u.full_name, '-') AS full_name,
                                     COALESCE(u.email, '-') AS email
                              FROM backup_audit a
                              LEFT JOIN users u ON u.user_id = a.user_id
                              WHERE a.cid = :cid
                              ORDER BY a.created_at DESC, a.id DESC
                              LIMIT 50");
        if (!$stmt) {
            respond('error', 'Failed to load backup audit');
        }

        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = [
                'id' => intval($row['id'] ?? 0),
                'event_type' => (string)($row['event_type'] ?? ''),
                'filename' => (string)($row['filename'] ?? ''),
                'size_bytes' => intval($row['size_bytes'] ?? 0),
                'ip_address' => (string)($row['ip_address'] ?? '-'),
                'created_at' => intval($row['created_at'] ?? 0),
                'full_name' => (string)($row['full_name'] ?? '-'),
                'email' => (string)($row['email'] ?? '-'),
            ];
        }

        respond('success', 'Backup audit loaded', ['data' => $rows]);
        break;



    default:
        respond('error', 'Unknown action');
}

?>
