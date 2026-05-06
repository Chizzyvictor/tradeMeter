<?php
session_start();
require_once __DIR__ . '/INC/db.php';

header('Content-Type: application/json; charset=utf-8');


// -------------------------
// Helper Functions
// -------------------------
function buildApiPayload($status, $text = "", $extra = []): array {
    $normalizedStatus = strtolower((string)$status) === 'success' ? 'success' : 'error';
    $isSuccess = $normalizedStatus === 'success';
    $safeExtra = is_array($extra) ? $extra : [];
    $meta = [];

    if (isset($safeExtra['meta']) && is_array($safeExtra['meta'])) {
        $meta = $safeExtra['meta'];
        unset($safeExtra['meta']);
    }

    if (array_key_exists('data', $safeExtra)) {
        $data = $safeExtra['data'];
        unset($safeExtra['data']);
    } else {
        $data = !empty($safeExtra) ? $safeExtra : null;
    }

    return array_merge([
        'ok' => $isSuccess,
        'status' => $normalizedStatus,
        'text' => (string)$text,
        'message' => (string)$text,
        'data' => $data,
        'meta' => $meta,
    ], $safeExtra);
}

function respond($status, $text = "", $extra = []) {
    echo json_encode(buildApiPayload($status, $text, $extra));
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

function buildRememberLoginHintCookieOptions(int $expiresAt): array {
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function setRememberLoginHintCookie(string $companyIdentifier, string $userEmail, int $expiresAt): void {
    $payload = json_encode([
        'company' => trim($companyIdentifier),
        'email' => strtolower(trim($userEmail))
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        return;
    }

    setcookie('remember_login_hint', rawurlencode($payload), buildRememberLoginHintCookieOptions($expiresAt));
}

function clearRememberLoginHintCookie(): void {
    setcookie('remember_login_hint', '', buildRememberLoginHintCookieOptions(time() - 3600));
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

function isEmailVerificationStrict(): bool {
    return boolFromEnv((string)envValue('EMAIL_VERIFICATION_STRICT', 'true'));
}

function sendAppEmail(string $toEmail, string $recipientName, string $subject, string $textMessage, string $htmlMessage = ''): bool {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromDomain = strpos($host, ':') !== false ? explode(':', $host)[0] : $host;
    if ($fromDomain === '' || $fromDomain === 'localhost') {
        $fromDomain = 'trademeter.local';
    }

    $smtpUsername = trim((string)envValue('SMTP_USERNAME', ''));
    $fallbackFromEmail = $smtpUsername !== '' ? $smtpUsername : ('no-reply@' . $fromDomain);
    $fromEmail = envValue('SMTP_FROM_EMAIL', $fallbackFromEmail);
    $fromName = envValue('SMTP_FROM_NAME', 'TradeMeter');

    // Preferred transport: SMTP via PHPMailer when configured.
    $smtpHost = trim((string)envValue('SMTP_HOST', ''));
    if ($smtpHost !== '') {
        tryLoadMailer();
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = $smtpHost;
                $mailer->Port = intval(envValue('SMTP_PORT', '587'));
                $mailer->CharSet = 'UTF-8';
                $mailer->SMTPAuth = boolFromEnv((string)envValue('SMTP_AUTH', 'true'));
                $mailer->Username = $smtpUsername;
                $mailer->Password = (string)envValue('SMTP_PASSWORD', '');

                $encryption = strtolower(trim((string)envValue('SMTP_ENCRYPTION', 'tls')));
                if ($encryption === 'ssl') {
                    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mailer->setFrom((string)$fromEmail, (string)$fromName);
                $mailer->addAddress($toEmail, $recipientName);
                $mailer->isHTML($htmlMessage !== '');
                $mailer->Subject = $subject;
                $mailer->Body = $htmlMessage !== '' ? $htmlMessage : nl2br(htmlspecialchars($textMessage, ENT_QUOTES, 'UTF-8'));
                $mailer->AltBody = $textMessage;

                return $mailer->send();
            } catch (Throwable $mailError) {
                error_log('TradeMeter mail error: ' . $mailError->getMessage());
                return false;
            }
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

function ensurePasswordResetTokenTable(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        used_at INTEGER,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_token ON password_reset_tokens(token)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_user_id ON password_reset_tokens(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_expires_at ON password_reset_tokens(expires_at)");
}

function createPasswordResetToken(AppDbConnection $db, int $userId, int $cid): string {
    ensurePasswordResetTokenTable($db);

    $token = bin2hex(random_bytes(32));
    $expiresAt = time() + (24 * 3600);

    $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_id, cid, token, expires_at)
                          VALUES (:user_id, :cid, :token, :expires_at)");
    if (!$stmt) {
        return '';
    }

    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_INTEGER);

    if ($stmt->execute()) {
        return $token;
    }

    return '';
}

function validateResetToken(AppDbConnection $db, string $token): ?array {
    ensurePasswordResetTokenTable($db);

    $now = time();
    $stmt = $db->prepare("SELECT id, user_id, cid, expires_at, used_at
                          FROM password_reset_tokens
                          WHERE token = :token
                            AND expires_at > :now
                            AND used_at IS NULL
                          LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ? $row : null;
}

function markTokenAsUsed(AppDbConnection $db, string $token): bool {
    ensurePasswordResetTokenTable($db);

    $stmt = $db->prepare("UPDATE password_reset_tokens
                          SET used_at = :used_at
                          WHERE token = :token");
    if (!$stmt) {
        return false;
    }

    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':used_at', time(), SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function sendPasswordResetEmail(string $toEmail, string $fullName, string $resetUrl): bool {
    $recipientName = trim($fullName) !== '' ? $fullName : 'User';
    $appName = 'TradeMeter';

    $subject = 'Password Reset Request - ' . $appName;
    $textMessage = "Hello {$recipientName},\n\n" .
                   "You requested a password reset for your {$appName} account.\n" .
                   "Click the link below to reset your password:\n\n" .
                   "{$resetUrl}\n\n" .
                   "This link will expire in 24 hours.\n\n" .
                   "If you did not request this reset, you can safely ignore this message.";

    $htmlMessage = '<p>Hello ' . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . ',</p>' .
                   '<p>You requested a password reset for your <strong>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</strong> account.</p>' .
                   '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset Password</a></p>' .
                   '<p>This link will expire in 24 hours.</p>' .
                   '<p>If you did not request this reset, you can safely ignore this message.</p>';

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

function logRememberEvent(AppDbConnection $db, string $eventType, int $userId = 0, int $cid = 0, array $details = []): void {
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

function ensureSecurityTables(AppDbConnection $db): void {
    appEnsureSecurityTables($db);
}

function ensureUserVerificationColumns(AppDbConnection $db): void {
    appEnsureUserVerificationColumns($db);
}

function logLoginAttempt(AppDbConnection $db, int $userId, int $cid, string $status, int $now): void {
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

function isRateLimited(AppDbConnection $db, string $ipAddress, int $now, int $maxAttempts = 5, int $windowSeconds = 300): bool {
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

function registerFailedLoginAttempt(AppDbConnection $db, string $ipAddress, int $now): void {
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

function clearLoginAttempts(AppDbConnection $db, string $ipAddress): void {
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $stmt->execute();
}

function upsertUserSession(AppDbConnection $db, int $userId, int $cid, string $sessionId, int $now): void {
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
$db = appDbConnectCompat();

function ensureRbacSchema(AppDbConnection $db): void {
    appEnsureRbacSchema($db);
}

function seedRolesAndPermissions(AppDbConnection $db, int $cid): void {
    appSeedRolesAndPermissions($db, $cid);
}

function assignUserRole(AppDbConnection $db, int $userId, int $cid, string $roleName): void {
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

function getUserRoles(AppDbConnection $db, int $userId): array {
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

function getUserPermissions(AppDbConnection $db, int $userId): array {
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
        $companyIdentifierRaw = safe_input($_POST["companyEmail"] ?? $_POST["company"] ?? "");
        $companyIdentifier = strtolower($companyIdentifierRaw);
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
            $rememberCompanyIdentifier = htmlspecialchars_decode($companyIdentifierRaw, ENT_QUOTES);
            setRememberLoginHintCookie($rememberCompanyIdentifier, $userEmail, $expiresAt);
            logRememberEvent($db, 'token_issued', $userId, $companyId, [
                'expires_at' => $expiresAt,
                'source' => 'password_login'
            ]);
        } else {
            // User explicitly logged in without "remember me": clear persisted remembered credentials.
            $delStmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid");
            if ($delStmt) {
                $delStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
                $delStmt->execute();
            }

            if (!empty($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', buildRememberCookieOptions(time() - 3600));
            }

            clearRememberLoginHintCookie();
        }

        respond("success", "Login successful", [
            "user" => (string)($user['full_name'] ?? ''),
            "user_id" => $userId,
            "company" => (string)($company['cName'] ?? ''),
            "roles" => $roles,
            "permissions" => $permissions,
            "csrf_token" => (string)($_SESSION['csrf_token'] ?? '')
        ]);
        break;

    // ---------------- SIGNUP ----------------
    case 'signup':
        $cName = safe_input($_POST["cName"] ?? $_POST["name"] ?? "");
        $email = strtolower(safe_input($_POST["cEmail"] ?? $_POST["email"] ?? ""));
        $rawPassword = (string)($_POST["cPass"] ?? $_POST["password"] ?? "");
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
            $stmt->bindValue(':question', '', SQLITE3_TEXT);
            $stmt->bindValue(':answer', '', SQLITE3_TEXT);
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
                if (isEmailVerificationStrict()) {
                    throw new Exception('Could not send verification email. Please check mail server configuration and try again.');
                }

                $verifyFallbackStmt = $db->prepare("UPDATE users
                                                    SET email_verified_at = :verified_at,
                                                        email_verification_token_hash = NULL,
                                                        email_verification_expires_at = NULL
                                                    WHERE user_id = :uid");
                if ($verifyFallbackStmt) {
                    $verifyFallbackStmt->bindValue(':verified_at', $now, SQLITE3_INTEGER);
                    $verifyFallbackStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
                    $verifyFallbackStmt->execute();
                }

                error_log('TradeMeter signup warning: verification email failed, auto-verified due to EMAIL_VERIFICATION_STRICT=false.');
            }

            $db->exec("COMMIT");
            if (isEmailVerificationStrict()) {
                respond("success", "Account created. Please verify your email before logging in.");
            }
            respond("success", "Account created. Email verification is currently bypassed because mail delivery is unavailable.");
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
        $ret = $db->query("SELECT cid, cName, cEmail, regDate, cLogo FROM company");

        while ($row = $ret->fetchArray(SQLITE3_ASSOC)) {
            $companies[] = $row;
        }

        if ($companies) {
            respond("success", "Companies loaded successfully", ["data" => $companies]);
        } else {
            respond("error", "No companies found");
        }
        break;

    // ---------------- FORGOT PASSWORD: EMAIL-BASED RESET ----------------
    case "requestPasswordReset":
        if (empty($_POST["company"]) || empty($_POST["email"])) {
            respond("error", "Company and email are required.");
        }

        $company = safe_input($_POST["company"]);
        $userEmail = strtolower(safe_input($_POST["email"]));

        // Find company by name or email
        $companyStmt = $db->prepare("SELECT cid, cName, cEmail
                                     FROM company
                                     WHERE lower(cName) = lower(:company)
                                        OR lower(cEmail) = lower(:company)
                                     LIMIT 1");
        $companyStmt->bindValue(':company', $company, SQLITE3_TEXT);
        $companyRow = $companyStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$companyRow) {
            respond("error", "Company not found. Please check and try again.");
        }

        $cid = intval($companyRow['cid']);
        // Find user by email in this company
        $userStmt = $db->prepare("SELECT user_id, full_name, email
                                  FROM users
                                  WHERE cid = :cid
                                    AND lower(email) = lower(:email)
                                    AND COALESCE(is_active, 1) = 1
                                  LIMIT 1");
        $userStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $userStmt->bindValue(':email', $userEmail, SQLITE3_TEXT);
        $userRow = $userStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$userRow) {
            respond("error", "User not found in this company. Please check and try again.");
        }

        $userId = intval($userRow['user_id']);
        $userFullName = $userRow['full_name'] ?? 'User';
        $userEmail = $userRow['email'];

        $token = createPasswordResetToken($db, $userId, $cid);

        if ($token === '') {
            respond("error", "Failed to create reset token. Please try again.");
        }

        // Send reset email
        $resetUrl = buildBaseUrl() . '/reset_password.php?token=' . urlencode($token);
        $sent = sendPasswordResetEmail($userEmail, $userFullName, $resetUrl);

        if ($sent) {
            respond("success", "Password reset link has been sent to your email. Check your inbox and click the link to reset your password.");
        } else {
            respond("error", "Failed to send reset email. Please try again or contact support.");
        }
        break;

    // ---------------- NEW: RESET PASSWORD WITH TOKEN ----------------
    case "resetPasswordWithToken":
        if (empty($_POST["token"]) || empty($_POST["password"])) {
            respond("error", "Invalid request.");
        }

        $token = trim(safe_input($_POST["token"]));
        $newPassword = $_POST["password"] ?? '';

        if (strlen($newPassword) < 6) {
            respond("error", "Password must be at least 6 characters.");
        }

        $tokenData = validateResetToken($db, $token);

        if (!$tokenData) {
            respond("error", "Invalid or expired reset token. Please request a new reset link.");
        }

        $userId = intval($tokenData['user_id']);
        $cid = intval($tokenData['cid']);

        if ($userId <= 0 || $cid <= 0) {
            respond("error", "Invalid token data.");
        }

        // Update password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users
                                    SET password = :password
                                    WHERE user_id = :uid AND cid = :cid");
        $updateStmt->bindValue(':password', $hash, SQLITE3_TEXT);
        $updateStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $updateStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$updateStmt->execute()) {
            respond("error", "Failed to update password. Please try again.");
        }

        // Mark token as used
        markTokenAsUsed($db, $token);

        respond("success", "Password has been reset successfully. You can now log in with your new password.");
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
