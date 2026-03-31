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

function settingsBackupDir(): string {
    $configured = trim((string)(appEnv('TM_BACKUP_DIR', '') ?? ''));
    $dir = $configured !== ''
        ? $configured
        : (__DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups');

    $dir = rtrim($dir, '/\\');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
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

function settingsEnsureSqliteForBackup(AppDbConnection $db): void {
    if ($db->driver() !== 'sqlite') {
        respond('error', 'Backup and restore are currently available only on SQLite deployments.');
    }
}

function settingsRequireBackupAccess(AppDbConnection $db, int $cid): void {
    requirePermission($db, 'manage_users');
    settingsEnsureSqliteForBackup($db);

    if (!currentUserHasRole($db, 'Owner')) {
        respond('error', 'Only Owner can use database backup and restore operations.');
    }

    $companyCount = intval($db->querySingle('SELECT COUNT(*) FROM company'));
    if ($companyCount > 1) {
        respond('error', 'Backup operations are disabled for shared multi-company databases. Use instance-level backups.');
    }

    if ($cid <= 0) {
        respond('error', 'Invalid company context for backup operation.');
    }
}

function settingsBackupCapability(AppDbConnection $db, int $cid): array {
    $driver = strtolower($db->driver());
    if ($driver !== 'sqlite') {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'This deployment is using PostgreSQL. Use platform-level backups (for example: Heroku PG Backups).',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
        ];
    }

    if (!currentUserHasRole($db, 'Owner')) {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'Only Owner can use in-app backup and restore operations.',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
        ];
    }

    $companyCount = intval($db->querySingle('SELECT COUNT(*) FROM company'));
    if ($companyCount > 1) {
        return [
            'supported' => false,
            'driver' => $driver,
            'message' => 'In-app backups are disabled for shared multi-company SQLite databases. Use instance-level backups.',
            'scheduler_hint' => settingsBackupSchedulerHint(),
            'retention_days' => settingsBackupRetentionDays(),
        ];
    }

    return [
        'supported' => true,
        'driver' => $driver,
        'message' => 'In-app backup and restore are enabled.',
        'scheduler_hint' => settingsBackupSchedulerHint(),
        'retention_days' => settingsBackupRetentionDays(),
        'storage_path' => settingsBackupDir(),
    ];
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

    $cipher = 'AES-256-CBC';
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
    $cipherText = openssl_encrypt($plainData, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($cipherText === false) {
        return null;
    }

    return "TMENC1\n" . base64_encode($iv) . "\n" . base64_encode($cipherText);
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
    $ivB64 = trim((string)$parts[1]);
    $cipherB64 = trim((string)$parts[2]);

    if ($version !== 'TMENC1' || $ivB64 === '' || $cipherB64 === '') {
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

function settingsOpenRawSqlite(string $path): SQLite3 {
    $sqlite = new SQLite3($path);
    $sqlite->enableExceptions(true);
    $sqlite->busyTimeout(5000);
    return $sqlite;
}

function settingsSnapshotSqliteDatabase(string $sourcePath, string $targetPath): bool {
    if (!is_file($sourcePath)) {
        return false;
    }

    if (is_file($targetPath)) {
        @unlink($targetPath);
    }

    $sourceDb = null;
    $targetDb = null;

    try {
        $sourceDb = settingsOpenRawSqlite($sourcePath);
        $targetDb = settingsOpenRawSqlite($targetPath);
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

function settingsRestoreSqliteDatabaseFromFile(string $backupPath, string $targetPath): bool {
    if (!is_file($backupPath)) {
        return false;
    }

    $backupDb = null;
    $targetDb = null;

    try {
        $backupDb = settingsOpenRawSqlite($backupPath);
        $targetDb = settingsOpenRawSqlite($targetPath);
        $ok = $backupDb->backup($targetDb);
        $backupDb->close();
        $targetDb->close();
        return (bool)$ok;
    } catch (Throwable $e) {
        if ($backupDb instanceof SQLite3) {
            @$backupDb->close();
        }
        if ($targetDb instanceof SQLite3) {
            @$targetDb->close();
        }
        return false;
    }
}

function settingsCreateBackupFile(int $cid, bool $auto = false): ?string {
    $sourcePath = appSqlitePath();
    if (!is_file($sourcePath)) {
        return null;
    }

    $dir = settingsBackupDir();
    if ($auto) {
        $filename = settingsAutoBackupPrefix($cid) . date('Ymd') . '.sqlite';
    } else {
        $filename = settingsBackupPrefix($cid) . date('Ymd_His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.sqlite';
    }

    $targetPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!settingsSnapshotSqliteDatabase($sourcePath, $targetPath)) {
        return null;
    }

    return $targetPath;
}

function settingsApplyAutoBackupRetention(int $cid): void {
    $dir = settingsBackupDir();
    $autoPrefix = settingsAutoBackupPrefix($cid);
    $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $autoPrefix . '*.sqlite');
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
    if ($db->driver() !== 'sqlite') {
        return;
    }

    $dir = settingsBackupDir();
    $todayPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . settingsAutoBackupPrefix($cid) . date('Ymd') . '.sqlite';
    if (!is_file($todayPath)) {
        $created = settingsCreateBackupFile($cid, true);
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

switch ($action) {
    case 'getBackupCapability':
        requirePermission($db, 'manage_users');
        respond('success', 'Backup capability loaded', [
            'data' => settingsBackupCapability($db, $cid)
        ]);
        break;

    case 'loadSettings':
        $stmt = $db->prepare("SELECT cid, cName, cEmail, question, cLogo, regDate FROM company WHERE cid = :cid LIMIT 1");
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

    case 'updateSecurity':
        $question = safe_input($_POST['question'] ?? '');
        $answer = strtolower(safe_input($_POST['answer'] ?? ''));

        if ($question === '') {
            respond('error', 'Security question is required');
        }
        if (mb_strlen($question) < 5) {
            respond('error', 'Security question must be at least 5 characters');
        }
        if ($answer === '') {
            respond('error', 'Security answer is required');
        }
        if (mb_strlen($answer) < 2) {
            respond('error', 'Security answer must be at least 2 characters');
        }

        $stmt = $db->prepare("UPDATE company SET question = :question, answer = :answer WHERE cid = :cid");
        if (!$stmt) {
            respond('error', 'Failed to prepare security update');
        }
        $stmt->bindValue(':question', $question, SQLITE3_TEXT);
        $stmt->bindValue(':answer', $answer, SQLITE3_TEXT);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$stmt->execute()) {
            respond('error', 'Failed to update security settings');
        }

        respond('success', 'Security settings updated successfully');
        break;

    case 'changePassword':
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            respond('error', 'All password fields are required');
        }

        if (strlen($newPassword) < 6) {
            respond('error', 'New password must be at least 6 characters');
        }

        if ($newPassword !== $confirmPassword) {
            respond('error', 'New password and confirm password do not match');
        }

        $stmt = $db->prepare("SELECT cPass FROM company WHERE cid = :cid LIMIT 1");
        if (!$stmt) {
            respond('error', 'Failed to verify current password');
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            respond('error', 'Company not found');
        }

        if (!password_verify($currentPassword, $row['cPass'])) {
            respond('error', 'Current password is incorrect');
        }

        if (password_verify($newPassword, $row['cPass'])) {
            respond('error', 'New password must be different from current password');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $uStmt = $db->prepare("UPDATE company SET cPass = :cPass WHERE cid = :cid");
        if (!$uStmt) {
            respond('error', 'Failed to prepare password update');
        }
        $uStmt->bindValue(':cPass', $hash, SQLITE3_TEXT);
        $uStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$uStmt->execute()) {
            respond('error', 'Failed to update password');
        }

        respond('success', 'Password changed successfully');
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
                              WHERE u.cid = :cid
                              ORDER BY u.created_at DESC, u.user_id DESC");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
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

        respond('success', 'Users loaded', ['data' => $users]);
        break;

    case 'createUser':
        requirePermission($db, 'manage_users');
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
        if (strlen($password) < 6) {
            respond('error', 'Password must be at least 6 characters');
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

    case 'updateUserRole':
        requirePermission($db, 'manage_users');
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

        if (!assignSingleRoleToUser($db, $userId, $roleId)) {
            respond('error', 'Failed to update user role');
        }

        respond('success', 'User role updated');
        break;

    case 'toggleUserStatus':
        requirePermission($db, 'manage_users');
        $userId = intval($_POST['user_id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $currentUserId = intval($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            respond('error', 'User is required');
        }

        if ($currentUserId > 0 && $userId === $currentUserId && $isActive === 0) {
            respond('error', 'You cannot deactivate your own account');
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
                              WHERE s.cid = :cid
                              ORDER BY s.last_activity DESC, s.created_at DESC
                              LIMIT 100");
        if (!$stmt) {
            respond('error', 'Failed to load active sessions');
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
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

        respond('success', 'Active sessions loaded', ['data' => $sessions]);
        break;

    case 'revokeSession':
        requirePermission($db, 'manage_users');
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

        respond('success', 'Session revoked', [
            'revoked_session_id' => $sessionId,
            'is_current' => hash_equals(session_id(), $sessionId)
        ]);
        break;

    case 'loadLoginLogs':
        requirePermission($db, 'manage_users');
        $allowedStatuses = ['all', 'success', 'failed', 'blocked', 'success_auto', 'failed_auto'];
        $status = strtolower(trim((string)($_POST['status'] ?? 'all')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $rows = [];
        if ($status === 'all') {
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
                                  WHERE l.cid = :cid
                                  ORDER BY l.login_time DESC, l.id DESC
                                  LIMIT 100");
            if (!$stmt) {
                respond('error', 'Failed to load login logs');
            }
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        } else {
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
                                  WHERE l.cid = :cid AND lower(l.status) = lower(:status)
                                  ORDER BY l.login_time DESC, l.id DESC
                                  LIMIT 100");
            if (!$stmt) {
                respond('error', 'Failed to load login logs');
            }
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        }

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

        respond('success', 'Login logs loaded', ['data' => $rows]);
        break;

    case 'createBackup':
        settingsRequireBackupAccess($db, $cid);

        $targetPath = settingsCreateBackupFile($cid, false);
        if ($targetPath === null) {
            respond('error', 'Failed to create backup file');
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

        $dir = settingsBackupDir();
        $prefix = settingsBackupPrefix($cid);
        $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '*.sqlite');
        $files = is_array($matches) ? $matches : [];

        usort($files, function (string $a, string $b): int {
            return intval(@filemtime($b)) <=> intval(@filemtime($a));
        });

        $rows = [];
        $maxItems = 30;
        foreach (array_slice($files, 0, $maxItems) as $file) {
            if (is_file($file)) {
                $rows[] = settingsBackupMeta($file);
            }
        }

        $autoPrefix = settingsAutoBackupPrefix($cid);
        $latestAuto = null;
        foreach ($rows as $entry) {
            if (strpos((string)$entry['filename'], $autoPrefix) === 0) {
                $latestAuto = $entry;
                break;
            }
        }

        respond('success', 'Backups loaded', [
            'data' => $rows,
            'retention_days' => settingsBackupRetentionDays(),
            'last_auto_backup_created_at' => $latestAuto['created_at'] ?? 0,
            'storage_path' => settingsBackupDir(),
            'scheduler_hint' => settingsBackupSchedulerHint(),
        ]);
        break;

    case 'downloadBackup':
        settingsRequireBackupAccess($db, $cid);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        if ($filename === '') {
            respond('error', 'Backup filename is required');
        }

        $prefix = settingsBackupPrefix($cid);
        if (strpos($filename, $prefix) !== 0 || preg_match('/\.sqlite$/', $filename) !== 1) {
            respond('error', 'Invalid backup filename');
        }

        $dir = settingsBackupDir();
        $backupPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($backupPath)) {
            respond('error', 'Backup file not found');
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        settingsAuditBackupEvent(
            $db,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_downloaded',
            $filename,
            intval(filesize($backupPath) ?: 0)
        );

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . intval(filesize($backupPath) ?: 0));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($backupPath);
        exit;

    case 'downloadEncryptedBackup':
        settingsRequireBackupAccess($db, $cid);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        $passphrase = (string)($_POST['passphrase'] ?? '');

        if ($filename === '') {
            respond('error', 'Backup filename is required');
        }
        if (strlen($passphrase) < 8) {
            respond('error', 'Passphrase must be at least 8 characters');
        }

        $prefix = settingsBackupPrefix($cid);
        if (strpos($filename, $prefix) !== 0 || preg_match('/\.sqlite$/', $filename) !== 1) {
            respond('error', 'Invalid backup filename');
        }

        $dir = settingsBackupDir();
        $backupPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($backupPath)) {
            respond('error', 'Backup file not found');
        }

        $plain = @file_get_contents($backupPath);
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

        $downloadName = preg_replace('/\.sqlite$/', '.sqlite.enc', $filename);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . strlen($payload));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo $payload;
        exit;

    case 'restoreEncryptedBackup':
        settingsRequireBackupAccess($db, $cid);

        $passphrase = (string)($_POST['passphrase'] ?? '');
        if (strlen($passphrase) < 8) {
            respond('error', 'Passphrase must be at least 8 characters');
        }

        $file = $_FILES['encryptedBackupFile'] ?? null;
        if (!is_array($file) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond('error', 'Encrypted backup file is required');
        }

        $originalName = basename((string)($file['name'] ?? ''));
        if ($originalName === '' || preg_match('/\.sqlite\.enc$/i', $originalName) !== 1) {
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

        if (strncmp($plain, 'SQLite format 3', 15) !== 0) {
            respond('error', 'Decrypted content is not a valid SQLite backup');
        }

        $sourcePath = appSqlitePath();
        $tempRestorePath = rtrim(settingsBackupDir(), '/\\') . DIRECTORY_SEPARATOR . 'restore-' . uniqid('', true) . '.sqlite';
        if (@file_put_contents($tempRestorePath, $plain, LOCK_EX) === false) {
            respond('error', 'Failed to prepare decrypted backup for restore');
        }

        $db->close();

        if (!settingsRestoreSqliteDatabaseFromFile($tempRestorePath, $sourcePath)) {
            @unlink($tempRestorePath);
            respond('error', 'Failed to restore decrypted backup');
        }
        @unlink($tempRestorePath);

        $uid = intval($_SESSION['user_id'] ?? 0);
        $auditDb = appDbConnectCompat();
        settingsEnsureBackupAuditTable($auditDb);
        settingsAuditBackupEvent(
            $auditDb,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_encrypted_restored',
            $originalName,
            strlen($plain)
        );
        $auditDb->close();

        respond('success', 'Encrypted backup restored successfully');
        break;

    case 'restoreBackup':
        settingsRequireBackupAccess($db, $cid);

        $filename = basename(trim((string)($_POST['filename'] ?? '')));
        if ($filename === '') {
            respond('error', 'Backup filename is required');
        }

        $prefix = settingsBackupPrefix($cid);
        if (strpos($filename, $prefix) !== 0 || preg_match('/\.sqlite$/', $filename) !== 1) {
            respond('error', 'Invalid backup filename');
        }

        $dir = settingsBackupDir();
        $backupPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($backupPath)) {
            respond('error', 'Backup file not found');
        }

        $sourcePath = appSqlitePath();
        $db->close();

        if (!settingsRestoreSqliteDatabaseFromFile($backupPath, $sourcePath)) {
            respond('error', 'Failed to restore backup');
        }

        $uid = intval($_SESSION['user_id'] ?? 0);
        $auditDb = appDbConnectCompat();
        settingsEnsureBackupAuditTable($auditDb);
        settingsAuditBackupEvent(
            $auditDb,
            $cid,
            $uid > 0 ? $uid : null,
            'backup_restored',
            $filename,
            intval(filesize($backupPath) ?: 0)
        );
        $auditDb->close();

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
