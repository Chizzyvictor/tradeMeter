<?php
session_start();
require_once __DIR__ . '/INC/db.php';

header('Content-Type: application/json; charset=utf-8');


// -------------------------
// Helper Functions
// -------------------------
function respond($status, $text = "", $extra = []) {
    echo json_encode(array_merge(["status" => $status, "text" => $text], $extra));
    exit;
}

function safe_input($value) {
    return trim(htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));
}

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return intval($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function buildRememberCookieOptions(int $expiresAt): array {
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Strict'
    ];
}

function buildBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['PHP_SELF'] ?? '')));
    $dir = rtrim($dir, '/');
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }
    return $scheme . '://' . $host . $dir;
}

function envValue(string $key, ?string $default = null): ?string {
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

function tryLoadMailer(): void {
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        return;
    }

    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

function boolFromEnv(string $value): bool {
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function sendAppEmail(string $toEmail, string $recipientName, string $subject, string $textMessage, string $htmlMessage = ''): bool {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromDomain = strpos($host, ':') !== false ? explode(':', $host)[0] : $host;
    if ($fromDomain === '' || $fromDomain === 'localhost') {
        $fromDomain = 'trademeter.local';
    }

    $fromEmail = envValue('SMTP_FROM_EMAIL', 'no-reply@' . $fromDomain);
    $fromName = envValue('SMTP_FROM_NAME', 'TradeMeter');

    // Preferred transport: SMTP via PHPMailer when configured.
    $smtpHost = trim((string)envValue('SMTP_HOST', ''));
    if ($smtpHost !== '') {
        tryLoadMailer();
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            $mailer->Port = intval(envValue('SMTP_PORT', '587'));
            $mailer->CharSet = 'UTF-8';
            $mailer->SMTPAuth = boolFromEnv((string)envValue('SMTP_AUTH', 'true'));
            $mailer->Username = (string)envValue('SMTP_USERNAME', '');
            $mailer->Password = (string)envValue('SMTP_PASSWORD', '');

            $encryption = strtolower(trim((string)envValue('SMTP_ENCRYPTION', 'tls')));
            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mailer->setFrom($fromEmail, $fromName);
            $mailer->addAddress($toEmail, $recipientName);
            $mailer->isHTML($htmlMessage !== '');
            $mailer->Subject = $subject;
            $mailer->Body = $htmlMessage !== '' ? $htmlMessage : nl2br(htmlspecialchars($textMessage, ENT_QUOTES, 'UTF-8'));
            $mailer->AltBody = $textMessage;

            return $mailer->send();
        }
    }

    // Fallback transport: native mail() for local/dev environments.
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>'
    ];

    return @mail($toEmail, $subject, $textMessage, implode("\r\n", $headers));
}

function sendVerificationEmail(string $toEmail, string $fullName, string $token): bool {
    $verifyUrl = buildBaseUrl() . '/verify_email.php?token=' . urlencode($token);
    $appName = 'TradeMeter';
    $recipientName = trim($fullName) !== '' ? $fullName : 'there';

    $subject = 'Verify your email address - ' . $appName;
    $textMessage = "Hello {$recipientName},\n\n" .
                   "Thanks for signing up on {$appName}.\n" .
                   "Please verify your email by clicking the link below:\n\n" .
                   "{$verifyUrl}\n\n" .
                   "This link will expire in 24 hours.\n\n" .
                   "If you did not create this account, you can ignore this message.";

    $htmlMessage = '<p>Hello ' . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . ',</p>' .
                   '<p>Thanks for signing up on <strong>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' .
                   '<p>Please verify your email by clicking the link below:</p>' .
                   '<p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '">Verify Email</a></p>' .
                   '<p>This link will expire in 24 hours.</p>' .
                   '<p>If you did not create this account, you can ignore this message.</p>';

    return sendAppEmail($toEmail, $recipientName, $subject, $textMessage, $htmlMessage);
}

function getClientIpAddress(): string {
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        return trim((string)$parts[0]);
    }
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function logRememberEvent(SQLite3 $db, string $eventType, int $userId = 0, int $cid = 0, array $details = []): void {
    $stmt = $db->prepare("INSERT INTO remember_token_audit (event_type, user_id, cid, ip_address, user_agent, details, created_at)
                          VALUES (:event_type, :user_id, :cid, :ip_address, :user_agent, :details, :created_at)");
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':event_type', $eventType, SQLITE3_TEXT);
    if ($userId > 0) {
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':user_id', null, SQLITE3_NULL);
    }
    if ($cid > 0) {
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':cid', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':ip_address', getClientIpAddress(), SQLITE3_TEXT);
    $stmt->bindValue(':user_agent', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':details', json_encode($details, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function ensureSecurityTables(SQLite3 $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        cid INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        login_time INTEGER,
        status TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        cid INTEGER,
        session_id TEXT NOT NULL UNIQUE,
        ip_address TEXT,
        user_agent TEXT,
        last_activity INTEGER,
        created_at INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL UNIQUE,
        attempts INTEGER NOT NULL DEFAULT 0,
        last_attempt INTEGER NOT NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_logs_time ON login_logs(login_time)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id, cid)");
}

function ensureUserVerificationColumns(SQLite3 $db): void {
    $columns = [];
    $addedVerifiedColumn = false;

    $res = $db->query("PRAGMA table_info(users)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = strtolower((string)($row['name'] ?? ''));
    }

    if (!in_array('email_verified_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verified_at INTEGER");
        $addedVerifiedColumn = true;
    }
    if (!in_array('email_verification_token_hash', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_token_hash TEXT");
    }
    if (!in_array('email_verification_expires_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_expires_at INTEGER");
    }

    if ($addedVerifiedColumn) {
        $db->exec("UPDATE users SET email_verified_at = strftime('%s','now') WHERE email_verified_at IS NULL");
    }
}

function logLoginAttempt(SQLite3 $db, int $userId, int $cid, string $status, int $now): void {
    $stmt = $db->prepare("INSERT INTO login_logs (user_id, cid, ip_address, user_agent, login_time, status)
                          VALUES (:uid, :cid, :ip, :ua, :time, :status)");
    if (!$stmt) {
        return;
    }

    if ($userId > 0) {
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':uid', null, SQLITE3_NULL);
    }

    if ($cid > 0) {
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':cid', null, SQLITE3_NULL);
    }

    $stmt->bindValue(':ip', getClientIpAddress(), SQLITE3_TEXT);
    $stmt->bindValue(':ua', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':time', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->execute();
}

function isRateLimited(SQLite3 $db, string $ipAddress, int $now, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $stmt = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = :ip LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) {
        return false;
    }

    $attempts = intval($row['attempts'] ?? 0);
    $lastAttempt = intval($row['last_attempt'] ?? 0);
    return $attempts >= $maxAttempts && ($now - $lastAttempt) < $windowSeconds;
}

function registerFailedLoginAttempt(SQLite3 $db, string $ipAddress, int $now): void {
    $stmt = $db->prepare("SELECT attempts FROM login_attempts WHERE ip_address = :ip LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $upd = $db->prepare("UPDATE login_attempts
                             SET attempts = :attempts, last_attempt = :last_attempt
                             WHERE ip_address = :ip");
        if ($upd) {
            $upd->bindValue(':attempts', intval($row['attempts'] ?? 0) + 1, SQLITE3_INTEGER);
            $upd->bindValue(':last_attempt', $now, SQLITE3_INTEGER);
            $upd->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
            $upd->execute();
        }
        return;
    }

    $ins = $db->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt)
                         VALUES (:ip, 1, :last_attempt)");
    if ($ins) {
        $ins->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
        $ins->bindValue(':last_attempt', $now, SQLITE3_INTEGER);
        $ins->execute();
    }
}

function clearLoginAttempts(SQLite3 $db, string $ipAddress): void {
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $stmt->execute();
}

function upsertUserSession(SQLite3 $db, int $userId, int $cid, string $sessionId, int $now): void {
    if ($userId <= 0 || $cid <= 0 || $sessionId === '') {
        return;
    }

    $del = $db->prepare("DELETE FROM user_sessions WHERE session_id = :sid");
    if ($del) {
        $del->bindValue(':sid', $sessionId, SQLITE3_TEXT);
        $del->execute();
    }

    $ins = $db->prepare("INSERT INTO user_sessions (user_id, cid, session_id, ip_address, user_agent, last_activity, created_at)
                         VALUES (:uid, :cid, :sid, :ip, :ua, :last_activity, :created_at)");
    if (!$ins) {
        return;
    }

    $ins->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $ins->bindValue(':sid', $sessionId, SQLITE3_TEXT);
    $ins->bindValue(':ip', getClientIpAddress(), SQLITE3_TEXT);
    $ins->bindValue(':ua', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $ins->bindValue(':last_activity', $now, SQLITE3_INTEGER);
    $ins->bindValue(':created_at', $now, SQLITE3_INTEGER);
    $ins->execute();
}






// -------------------------
// Database Connection
// -------------------------
$db = appDbConnect();

function ensureRbacSchema(SQLite3 $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL,
        is_active INTEGER DEFAULT 1 CHECK (is_active IN (0,1)),
        created_at INTEGER DEFAULT (strftime('%s','now')),
        UNIQUE (cid, email)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        role_name TEXT NOT NULL,
        is_system INTEGER DEFAULT 0,
        created_at INTEGER DEFAULT (strftime('%s','now')),
        UNIQUE (cid, role_name)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        UNIQUE (user_id, role_id)
    )");

    ensureUserVerificationColumns($db);

    $db->exec("CREATE TABLE IF NOT EXISTS permissions (
        permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        permission_key TEXT NOT NULL UNIQUE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL,
        UNIQUE (role_id, permission_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS remember_token_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT NOT NULL,
        user_id INTEGER,
        cid INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        details TEXT,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_token_hash ON remember_tokens(token_hash)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_remember_audit_created_at ON remember_token_audit(created_at)");

    ensureSecurityTables($db);
}

function seedRolesAndPermissions(SQLite3 $db, int $cid): void {
    $permissions = [
        'view_dashboard',
        'manage_products',
        'manage_inventory',
        'create_sales',
        'create_purchases',
        'view_reports',
        'delete_records',
        'manage_users'
    ];

    foreach ($permissions as $perm) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO permissions (permission_key) VALUES (:perm)");
        $stmt->bindValue(':perm', $perm, SQLITE3_TEXT);
        $stmt->execute();
    }

    $roleNames = [
        ['name' => 'Owner', 'system' => 1],
        ['name' => 'Manager', 'system' => 1],
        ['name' => 'Staff', 'system' => 1],
    ];

    foreach ($roleNames as $role) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO roles (cid, role_name, is_system) VALUES (:cid, :role_name, :is_system)");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $stmt->bindValue(':role_name', $role['name'], SQLITE3_TEXT);
        $stmt->bindValue(':is_system', $role['system'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    $roleMap = [];
    $stmt = $db->prepare("SELECT role_id, role_name FROM roles WHERE cid = :cid");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $roleMap[strtolower($row['role_name'])] = intval($row['role_id']);
    }

    $permMap = [];
    $pres = $db->query("SELECT permission_id, permission_key FROM permissions");
    while ($row = $pres->fetchArray(SQLITE3_ASSOC)) {
        $permMap[$row['permission_key']] = intval($row['permission_id']);
    }

    $assignment = [
        'owner' => $permissions,
        'manager' => ['manage_products', 'manage_inventory', 'create_sales', 'create_purchases', 'view_reports'],
        'staff' => ['create_sales']
    ];

    foreach ($assignment as $roleName => $permList) {
        if (!isset($roleMap[$roleName])) {
            continue;
        }
        $roleId = $roleMap[$roleName];
        foreach ($permList as $permKey) {
            if (!isset($permMap[$permKey])) {
                continue;
            }
            $stmt = $db->prepare("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            $stmt->bindValue(':role_id', $roleId, SQLITE3_INTEGER);
            $stmt->bindValue(':permission_id', $permMap[$permKey], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

function assignUserRole(SQLite3 $db, int $userId, int $cid, string $roleName): void {
    $stmt = $db->prepare("SELECT role_id FROM roles WHERE cid = :cid AND lower(role_name) = lower(:role_name) LIMIT 1");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':role_name', $roleName, SQLITE3_TEXT);
    $role = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$role) {
        return;
    }

    $stmt = $db->prepare("INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':role_id', intval($role['role_id']), SQLITE3_INTEGER);
    $stmt->execute();
}

function getUserRoles(SQLite3 $db, int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    $roles = [];
    $stmt = $db->prepare("SELECT r.role_name
                          FROM user_roles ur
                          JOIN roles r ON ur.role_id = r.role_id
                          WHERE ur.user_id = :uid
                          ORDER BY CASE lower(r.role_name)
                                WHEN 'owner' THEN 1
                                WHEN 'manager' THEN 2
                                WHEN 'staff' THEN 3
                                ELSE 4 END, r.role_name ASC");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $roles[] = (string)($row['role_name'] ?? '');
    }

    return array_values(array_filter($roles));
}

function getUserPermissions(SQLite3 $db, int $userId): array {
    if ($userId <= 0) {
        return [];
    }

    $permissions = [];
    $stmt = $db->prepare("SELECT DISTINCT p.permission_key
                          FROM user_roles ur
                          JOIN role_permissions rp ON ur.role_id = rp.role_id
                          JOIN permissions p ON rp.permission_id = p.permission_id
                          WHERE ur.user_id = :uid
                          ORDER BY p.permission_key ASC");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $permissions[] = (string)($row['permission_key'] ?? '');
    }

    return array_values(array_filter($permissions));
}

// -------------------------
// Timezone and Date Ranges
// -------------------------
date_default_timezone_set("Africa/Lagos");
$now        = time();
$today      = strtotime('today');
$lastMonth  = strtotime('-1 month');
$beginning  = strtotime("first day of this month");
$end        = strtotime("last day of this month");
$begin      = strtotime("first day of last month");
$ending     = strtotime("last day of last month");
$action     = $_POST['action'] ?? null;




// -------------------------
// Action Handler
// -------------------------
// === Ensure action exists ===
if (!$action) respond("error", "No action provided");




switch ($action) {

    // ---------------- LOGIN ----------------
    case 'login':
        $companyIdentifier = strtolower(safe_input($_POST["companyEmail"] ?? $_POST["company"] ?? ""));
        $userEmail = strtolower(safe_input($_POST["email"] ?? ""));
        $pass = (string)($_POST["pass"] ?? $_POST["password"] ?? "");
        $clientIp = getClientIpAddress();

        if ($companyIdentifier === '' || $userEmail === '' || $pass === '') {
            respond("error", "Company email, user email and password are required");
        }

        ensureRbacSchema($db);

        if (isRateLimited($db, $clientIp, $now, 5, 300)) {
            logLoginAttempt($db, 0, 0, 'blocked', $now);
            respond('error', 'Too many attempts. Try again in 5 minutes.');
        }

        $cStmt = $db->prepare("SELECT cid, cName, cEmail
                               FROM company
                               WHERE lower(cEmail) = lower(:identifier)
                                  OR lower(cName) = lower(:identifier)
                               LIMIT 1");
        $cStmt->bindValue(':identifier', $companyIdentifier, SQLITE3_TEXT);
        $company = $cStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$company) {
            registerFailedLoginAttempt($db, $clientIp, $now);
            logLoginAttempt($db, 0, 0, 'failed', $now);
            respond("error", "Company not found");
        }

        $companyId = intval($company['cid'] ?? 0);
        seedRolesAndPermissions($db, $companyId);

        $uStmt = $db->prepare("SELECT user_id, cid, full_name, email, password, is_active
                                                                            , email_verified_at
                               FROM users
                               WHERE lower(email) = lower(:email)
                                 AND cid = :cid
                               LIMIT 1");
        $uStmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
        $uStmt->bindValue(':cid', $companyId, SQLITE3_INTEGER);
        $user = $uStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$user || intval($user['is_active']) !== 1 || !password_verify($pass, $user['password'])) {
            registerFailedLoginAttempt($db, $clientIp, $now);
            logLoginAttempt($db, intval($user['user_id'] ?? 0), $companyId, 'failed', $now);
            respond("error", "Invalid login");
        }

        if (empty($user['email_verified_at'])) {
            registerFailedLoginAttempt($db, $clientIp, $now);
            logLoginAttempt($db, intval($user['user_id'] ?? 0), $companyId, 'failed_unverified', $now);
            respond("error", "Please verify your email before logging in. Check your inbox for the verification link.");
        }

        session_regenerate_id(true);

        $userId = intval($user['user_id']);
        $roles = getUserRoles($db, $userId);
        $permissions = getUserPermissions($db, $userId);

        $_SESSION['isLogedin'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['cid'] = $companyId;
        $_SESSION['company'] = (string)($company['cName'] ?? '');
        $_SESSION['user_name'] = (string)($user['full_name'] ?? '');
        $_SESSION['roles'] = $roles;
        $_SESSION['permissions'] = $permissions;
        $_SESSION['permissions_map'] = array_fill_keys($permissions, true);
        $_SESSION['last_activity'] = $now;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        clearLoginAttempts($db, $clientIp);
        logLoginAttempt($db, $userId, $companyId, 'success', $now);
        upsertUserSession($db, $userId, $companyId, session_id(), $now);

        // Remember Me: generate a secure cookie token, store hash in DB
        if (!empty($_POST['remember']) && $_POST['remember'] === '1') {
            // Guarantee the table exists (handles existing DBs upgraded without migration)
            $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                cid INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at INTEGER NOT NULL,
                created_at INTEGER DEFAULT (strftime('%s','now'))
            )");

            $db->exec("CREATE INDEX IF NOT EXISTS idx_token_hash ON remember_tokens(token_hash)");
            $db->exec("DELETE FROM remember_tokens WHERE expires_at < " . intval($now));

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = $now + (30 * 24 * 3600); // 30 days

            // Remove old tokens for this user to avoid accumulation
            $delStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            $delStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $delStmt->execute();

            $insStmt = $db->prepare("INSERT INTO remember_tokens (user_id, cid, token_hash, expires_at)
                                     VALUES (:uid, :cid, :hash, :exp)");
            $insStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $insStmt->bindValue(':cid', $companyId, SQLITE3_INTEGER);
            $insStmt->bindValue(':hash', $tokenHash, SQLITE3_TEXT);
            $insStmt->bindValue(':exp', $expiresAt, SQLITE3_INTEGER);
            $insStmt->execute();

            setcookie('remember_token', $rawToken, buildRememberCookieOptions($expiresAt));
            logRememberEvent($db, 'token_issued', $userId, $companyId, [
                'expires_at' => $expiresAt,
                'source' => 'password_login'
            ]);
        }

        respond("success", "Login successful", [
            "user" => (string)($user['full_name'] ?? ''),
            "roles" => $roles
        ]);
        break;

    // ---------------- SIGNUP ----------------
    case 'signup':
        $cName = safe_input($_POST["cName"] ?? $_POST["name"] ?? "");
        $email = strtolower(safe_input($_POST["cEmail"] ?? $_POST["email"] ?? ""));
        $rawPassword = (string)($_POST["cPass"] ?? $_POST["password"] ?? "");
        $question = safe_input($_POST["cQuestion"] ?? $_POST["question"] ?? "");
        $answer = strtolower(safe_input($_POST["cAnswer"] ?? $_POST["answer"] ?? ""));
        $fullName = safe_input($_POST["fullName"] ?? $cName);

        if ($cName === '' || $email === '' || $rawPassword === '' || $fullName === '') {
            respond("error", "Company, owner name, email and password are required");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond("error", "Invalid email");
        }

        $passHash = password_hash($rawPassword, PASSWORD_DEFAULT);

        // check if name exists
        $stmt = $db->prepare("SELECT 1 FROM company WHERE cName = :cName");
        $stmt->bindValue(':cName', $cName, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            respond("error", "Name already exists");
        }

        // check if email exists
        $stmt = $db->prepare("SELECT 1 FROM company WHERE cEmail = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            respond("error", "Email already exists");
        }

        $db->exec("BEGIN");

        try {
            // insert new company
            $stmt = $db->prepare("INSERT INTO company (cName, cEmail, cPass, regDate, question, answer, cLogo)
                                  VALUES (:cName, :email, :pass, :regDate, :question, :answer, :cLogo)");
            $stmt->bindValue(':cName', $cName, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':pass', $passHash, SQLITE3_TEXT);
            $stmt->bindValue(':regDate', $now, SQLITE3_INTEGER);
            $stmt->bindValue(':question', $question, SQLITE3_TEXT);
            $stmt->bindValue(':answer', $answer, SQLITE3_TEXT);
            $stmt->bindValue(':cLogo', "logo.jpg", SQLITE3_TEXT);

            if (!$stmt->execute()) {
                throw new Exception($db->lastErrorMsg());
            }

            $companyId = intval($db->lastInsertRowID());

            ensureRbacSchema($db);
            seedRolesAndPermissions($db, $companyId);

            $verificationToken = bin2hex(random_bytes(32));
            $verificationTokenHash = hash('sha256', $verificationToken);
            $verificationExpiry = $now + (24 * 3600);

            $uStmt = $db->prepare("INSERT INTO users (
                                       cid, full_name, email, password, is_active,
                                       email_verified_at, email_verification_token_hash, email_verification_expires_at
                                   )
                                   VALUES (
                                       :cid, :full_name, :email, :password, 1,
                                       NULL, :token_hash, :token_expires_at
                                   )");
            $uStmt->bindValue(':cid', $companyId, SQLITE3_INTEGER);
            $uStmt->bindValue(':full_name', $fullName, SQLITE3_TEXT);
            $uStmt->bindValue(':email', $email, SQLITE3_TEXT);
            $uStmt->bindValue(':password', $passHash, SQLITE3_TEXT);
            $uStmt->bindValue(':token_hash', $verificationTokenHash, SQLITE3_TEXT);
            $uStmt->bindValue(':token_expires_at', $verificationExpiry, SQLITE3_INTEGER);

            if (!$uStmt->execute()) {
                throw new Exception('Failed to create owner user');
            }

            $userId = intval($db->lastInsertRowID());
            assignUserRole($db, $userId, $companyId, 'Owner');

            if (!sendVerificationEmail($email, $fullName, $verificationToken)) {
                throw new Exception('Could not send verification email. Please check mail server configuration and try again.');
            }

            $db->exec("COMMIT");
            respond("success", "Account created. Please verify your email before logging in.");
        } catch (Throwable $e) {
            $db->exec("ROLLBACK");
            respond("error", $e->getMessage());
        }
        break;

    // ---------------- LOGOUT ----------------
    case 'logout':
        // Invalidate any remember_me token stored for this user
        $loggedOutUserId = intval($_SESSION['user_id'] ?? 0);
        $loggedOutCid = intval($_SESSION['cid'] ?? 0);
        $currentSessionId = session_id();
        if ($loggedOutUserId > 0) {
            ensureRbacSchema($db);
            $delStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            $delStmt->bindValue(':uid', $loggedOutUserId, SQLITE3_INTEGER);
            $delStmt->execute();

            $sessionDel = $db->prepare("DELETE FROM user_sessions WHERE session_id = :sid");
            if ($sessionDel) {
                $sessionDel->bindValue(':sid', $currentSessionId, SQLITE3_TEXT);
                $sessionDel->execute();
            }

            logRememberEvent($db, 'token_revoked', $loggedOutUserId, intval($_SESSION['cid'] ?? 0), [
                'source' => 'logout'
            ]);
        }
        // Clear the remember_token cookie
        if (!empty($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', buildRememberCookieOptions(time() - 3600));
        }
        session_unset();
        session_destroy();
        respond("success", "Logout successful");
        break;

    // ---------------- LOGOUT ALL DEVICES ----------------
    case 'logoutAllDevices':
        $loggedOutUserId = intval($_SESSION['user_id'] ?? 0);
        $loggedOutCid = intval($_SESSION['cid'] ?? 0);
        if ($loggedOutUserId > 0) {
            ensureRbacSchema($db);

            $tokenDel = $db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            if ($tokenDel) {
                $tokenDel->bindValue(':uid', $loggedOutUserId, SQLITE3_INTEGER);
                $tokenDel->execute();
            }

            $sessionsDel = $db->prepare("DELETE FROM user_sessions WHERE user_id = :uid AND cid = :cid");
            if ($sessionsDel) {
                $sessionsDel->bindValue(':uid', $loggedOutUserId, SQLITE3_INTEGER);
                $sessionsDel->bindValue(':cid', $loggedOutCid, SQLITE3_INTEGER);
                $sessionsDel->execute();
            }

            logRememberEvent($db, 'token_revoked', $loggedOutUserId, $loggedOutCid, [
                'source' => 'logout_all_devices'
            ]);
        }

        if (!empty($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', buildRememberCookieOptions(time() - 3600));
        }

        session_unset();
        session_destroy();
        respond("success", "Logged out from all devices");
        break;

    // ---------------- COMPANY LOGO ----------------
    case 'cLogo':
        $cid = $_SESSION['cid'] ?? 0;
        if (!$cid) {
            respond("success", "Not logged in", ["data" => "logo.jpg"]);
        }

        $stmt = $db->prepare("SELECT cLogo FROM company WHERE cid = :cid");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row && !empty($row['cLogo'])) {
            respond("success", "Company logo loaded successfully!", ["data" => $row["cLogo"]]);
        } else {
            respond("success", "Logo not found", ["data" => "logo.jpg"]);
        }
        break;

    // ---------------- LOAD ALL COMPANIES ----------------
    case 'loadCompanies':
        $companies = [];
        $ret = $db->query("SELECT cid, cName, cEmail, regDate, question, cLogo FROM company");

        while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
            $companies[] = $row;
        }

        if ($companies) {
            respond("success", "Companies loaded successfully", ["data" => $companies]);
        } else {
            respond("error", "No companies found");
        }
        break;

    // ---------------- FORGOT PASSWORD: STEP 1 ----------------
    case "requestPasswordReset":
        if (empty($_POST["fEmail"])) {
            respond("error", "Email is required.");
        }

        $email = strtolower(safe_input($_POST["fEmail"]));
        $stmt = $db->prepare("SELECT question FROM company WHERE cEmail = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row) {
            $_SESSION["reset_email"] = $email;
            respond("success", "Email found", ["question" => $row["question"]]);
        } else {
            respond("error", "Account not found");
        }
        break;

    // ---------------- FORGOT PASSWORD: STEP 2 ----------------
    case "forgotQandA":
        if (empty($_POST["answer"])) {
            respond("error", "Answer is required.");
        }

        if (!isset($_SESSION["reset_email"])) {
            respond("error", "Session expired. Please restart reset process.");
        }

        $answer = strtolower(safe_input($_POST["answer"]));
        $email  = $_SESSION["reset_email"];

        $stmt = $db->prepare("SELECT answer FROM company WHERE cEmail = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $ret = $stmt->execute();
        $row = $ret->fetchArray(SQLITE3_ASSOC);

        if ($row && strtolower($row["answer"]) === $answer) {
            $_SESSION["can_reset"] = true;
            respond("success", "Answer verified. You may now reset your password.");
        } else {
            respond("error", "Incorrect answer.");
        }
        break;

    // ---------------- FORGOT PASSWORD: STEP 3 ----------------
    case "resetPassword":
        if (empty($_POST["pwd"])) {
            respond("error", "Password is required.");
        }

        if (!isset($_SESSION["reset_email"]) || empty($_SESSION["can_reset"])) {
            respond("error", "Unauthorized request.");
        }

        $email = $_SESSION["reset_email"];
        $pwd   = password_hash($_POST["pwd"], PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE company SET cPass = :pwd WHERE cEmail = :email");
        $stmt->bindValue(':pwd', $pwd, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);

        if ($stmt->execute()) {
            unset($_SESSION["reset_email"], $_SESSION["can_reset"]);
            respond("success", "Password reset successful.");
        } else {
            respond("error", "Failed to reset password.");
        }
        break;

    // ---------------- GET USER PERMISSIONS ----------------
    case 'getUserPermissions':
        $userId = $_SESSION['user_id'] ?? null;
        $cid = $_SESSION['cid'] ?? 0;
        
        if (!$userId) {
            respond("success", "No user logged in", ["permissions" => []]);
        }

        $permissions = getUserPermissions($db, intval($userId));
        
        respond("success", "Permissions loaded", ["permissions" => $permissions]);
        break;

    // ---------------- GET CURRENT USER CONTEXT ----------------
    case 'getCurrentUserContext':
        $userId = intval($_SESSION['user_id'] ?? 0);
        $cid = intval($_SESSION['cid'] ?? 0);

        if ($cid <= 0) {
            respond("error", "Not logged in");
        }

        if ($userId <= 0) {
            respond("success", "Legacy user context", [
                "user" => [
                    "user_id" => 0,
                    "full_name" => "Owner",
                    "email" => "",
                    "role" => "Owner"
                ]
            ]);
        }

        $stmt = $db->prepare("SELECT u.user_id,
                                     u.full_name,
                                     u.email,
                                     COALESCE((
                                        SELECT r.role_name
                                        FROM user_roles ur
                                        JOIN roles r ON ur.role_id = r.role_id
                                        WHERE ur.user_id = u.user_id
                                        ORDER BY CASE lower(r.role_name)
                                            WHEN 'owner' THEN 1
                                            WHEN 'manager' THEN 2
                                            WHEN 'staff' THEN 3
                                            ELSE 4 END
                                        LIMIT 1
                                     ), 'User') AS role_name
                              FROM users u
                              WHERE u.user_id = :uid AND u.cid = :cid
                              LIMIT 1");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            respond("error", "User not found");
        }

        respond("success", "Current user context loaded", [
            "user" => [
                "user_id" => intval($row['user_id']),
                "full_name" => $row['full_name'] ?? '',
                "email" => $row['email'] ?? '',
                "role" => $row['role_name'] ?? 'User'
            ]
        ]);
        break;

    // ---------------- SMTP TEST EMAIL ----------------
    case 'sendSmtpTestEmail':
        $userId = intval($_SESSION['user_id'] ?? 0);
        $cid = intval($_SESSION['cid'] ?? 0);
        $permissions = $_SESSION['permissions'] ?? [];

        if ($userId <= 0 || $cid <= 0) {
            respond('error', 'Not logged in');
        }

        if (!is_array($permissions) || !in_array('manage_users', $permissions, true)) {
            respond('error', 'Unauthorized');
        }

        $targetEmail = strtolower(safe_input($_POST['email'] ?? ''));
        if ($targetEmail === '') {
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
            if ($stmt) {
                $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                $targetEmail = strtolower((string)($row['email'] ?? ''));
            }
        }

        if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            respond('error', 'A valid email address is required');
        }

        $recipientName = trim((string)($_SESSION['user_name'] ?? 'TradeMeter User'));
        $subject = 'TradeMeter SMTP test email';
        $timestamp = date('Y-m-d H:i:s');
        $smtpHost = (string)envValue('SMTP_HOST', 'not-set');

        $textBody = "Hello {$recipientName},\n\n" .
                    "This is a TradeMeter SMTP test email.\n" .
                    "Timestamp: {$timestamp}\n" .
                    "SMTP_HOST: {$smtpHost}\n\n" .
                    "If you received this, email sending is working.";

        $htmlBody = '<p>Hello ' . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . ',</p>' .
                    '<p>This is a <strong>TradeMeter SMTP test email</strong>.</p>' .
                    '<p>Timestamp: ' . htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') . '<br>' .
                    'SMTP_HOST: ' . htmlspecialchars($smtpHost, ENT_QUOTES, 'UTF-8') . '</p>' .
                    '<p>If you received this, email sending is working.</p>';

        if (!sendAppEmail($targetEmail, $recipientName, $subject, $textBody, $htmlBody)) {
            respond('error', 'Failed to send test email. Check SMTP settings and mail transport.');
        }

        respond('success', 'Test email sent successfully to ' . $targetEmail);
        break;

    // ---------------- DEFAULT ----------------
    default:
        respond("error", "Unknown action");
        break;
}
?>