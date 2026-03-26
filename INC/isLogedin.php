<?php
require_once __DIR__ . '/db.php';

function isHttpsRequestRemember(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return intval($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function rememberCookieOptions(int $expiresAt): array {
    return [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isHttpsRequestRemember(),
        'httponly' => true,
        'samesite' => 'Strict'
    ];
}

function rememberClientIp(): string {
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        return trim((string)$parts[0]);
    }
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function rememberAudit(SQLite3 $db, string $eventType, int $userId = 0, int $cid = 0, array $details = []): void {
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
    $stmt->bindValue(':ip_address', rememberClientIp(), SQLITE3_TEXT);
    $stmt->bindValue(':user_agent', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':details', json_encode($details, JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
    $stmt->execute();
}

function ensureAuthSecurityTables(SQLite3 $db): void {
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

    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_logs_time ON login_logs(login_time)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id, cid)");
}

function rememberLogLoginStatus(SQLite3 $db, int $userId, int $cid, string $status, int $time): void {
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

    $stmt->bindValue(':ip', rememberClientIp(), SQLITE3_TEXT);
    $stmt->bindValue(':ua', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $stmt->bindValue(':time', $time, SQLITE3_INTEGER);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->execute();
}

function rememberUpsertUserSession(SQLite3 $db, int $userId, int $cid, string $sessionId, int $now): void {
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
    $ins->bindValue(':ip', rememberClientIp(), SQLITE3_TEXT);
    $ins->bindValue(':ua', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), SQLITE3_TEXT);
    $ins->bindValue(':last_activity', $now, SQLITE3_INTEGER);
    $ins->bindValue(':created_at', $now, SQLITE3_INTEGER);
    $ins->execute();
}

function rememberTouchSession(SQLite3 $db, string $sessionId, int $now): void {
    if ($sessionId === '') {
        return;
    }

    $stmt = $db->prepare("UPDATE user_sessions SET last_activity = :now WHERE session_id = :sid");
    if (!$stmt) {
        return;
    }
    $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':sid', $sessionId, SQLITE3_TEXT);
    $stmt->execute();
}

function rememberSessionExists(SQLite3 $db, string $sessionId, int $userId, int $cid): bool {
    if ($sessionId === '' || $userId <= 0 || $cid <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT 1 FROM user_sessions
                          WHERE session_id = :sid AND user_id = :uid AND cid = :cid
                          LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bindValue(':sid', $sessionId, SQLITE3_TEXT);
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    return $res && $res->fetchArray(SQLITE3_ASSOC) !== false;
}

function rememberUserHasAnySession(SQLite3 $db, int $userId, int $cid): bool {
    if ($userId <= 0 || $cid <= 0) {
        return false;
    }

    $stmt = $db->prepare("SELECT 1 FROM user_sessions WHERE user_id = :uid AND cid = :cid LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    return $res && $res->fetchArray(SQLITE3_ASSOC) !== false;
}

if (empty($_SESSION['isLogedin'])) {
    $rememberToken = $_COOKIE['remember_token'] ?? '';
    $autoLoggedIn = false;

    if ($rememberToken !== '') {
        $rdb = appDbConnect();
        $tokenHash = hash('sha256', $rememberToken);
        $now = time();

        // Ensure table exists in case this DB was created before the remember-me upgrade
        $rdb->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at INTEGER NOT NULL,
            created_at INTEGER DEFAULT (strftime('%s','now'))
        )");

        $rdb->exec("CREATE TABLE IF NOT EXISTS remember_token_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            user_id INTEGER,
            cid INTEGER,
            ip_address TEXT,
            user_agent TEXT,
            details TEXT,
            created_at INTEGER DEFAULT (strftime('%s','now'))
        )");

        $rdb->exec("CREATE INDEX IF NOT EXISTS idx_token_hash ON remember_tokens(token_hash)");
        $rdb->exec("CREATE INDEX IF NOT EXISTS idx_remember_audit_created_at ON remember_token_audit(created_at)");
        $rdb->exec("DELETE FROM remember_tokens WHERE expires_at < " . intval($now));
        ensureAuthSecurityTables($rdb);

        $stmt = $rdb->prepare(
            "SELECT rt.user_id, rt.cid, u.full_name, u.is_active
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.user_id
             WHERE rt.token_hash = :hash AND rt.expires_at > :now
             LIMIT 1"
        );
        $stmt->bindValue(':hash', $tokenHash, SQLITE3_TEXT);
        $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($row && intval($row['is_active']) === 1) {
            $userId = intval($row['user_id']);
            $cid    = intval($row['cid']);

            session_regenerate_id(true);

            // Load roles
            $roles = [];
            $rStmt = $rdb->prepare(
                "SELECT r.role_name FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.role_id
                 WHERE ur.user_id = :uid
                 ORDER BY CASE lower(r.role_name)
                    WHEN 'owner'   THEN 1
                    WHEN 'manager' THEN 2
                    WHEN 'staff'   THEN 3
                    ELSE 4 END"
            );
            $rStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $rRes = $rStmt->execute();
            while ($rRow = $rRes->fetchArray(SQLITE3_ASSOC)) {
                $roles[] = $rRow['role_name'];
            }

            // Load permissions
            $permissions = [];
            $pStmt = $rdb->prepare(
                "SELECT DISTINCT p.permission_key
                 FROM user_roles ur
                 JOIN role_permissions rp ON ur.role_id = rp.role_id
                 JOIN permissions p ON rp.permission_id = p.permission_id
                 WHERE ur.user_id = :uid"
            );
            $pStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $pRes = $pStmt->execute();
            while ($pRow = $pRes->fetchArray(SQLITE3_ASSOC)) {
                $permissions[] = $pRow['permission_key'];
            }

            // Load company name
            $cStmt = $rdb->prepare("SELECT cName FROM company WHERE cid = :cid LIMIT 1");
            $cStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $cRow = $cStmt->execute()->fetchArray(SQLITE3_ASSOC);

            $_SESSION['isLogedin']    = true;
            $_SESSION['user_id']      = $userId;
            $_SESSION['cid']          = $cid;
            $_SESSION['company']      = $cRow ? (string)$cRow['cName'] : '';
            $_SESSION['user_name']    = (string)$row['full_name'];
            $_SESSION['roles']        = $roles;
            $_SESSION['permissions']  = $permissions;
            $_SESSION['permissions_map'] = array_fill_keys($permissions, true);
            $_SESSION['last_activity'] = $now;
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            // Rotate remember token after successful auto-login
            $newRawToken = bin2hex(random_bytes(32));
            $newTokenHash = hash('sha256', $newRawToken);
            $newExpiresAt = $now + (30 * 24 * 3600);

            $delStmt = $rdb->prepare("DELETE FROM remember_tokens WHERE token_hash = :hash");
            $delStmt->bindValue(':hash', $tokenHash, SQLITE3_TEXT);
            $delStmt->execute();

            $insStmt = $rdb->prepare("INSERT INTO remember_tokens (user_id, cid, token_hash, expires_at)
                                      VALUES (:uid, :cid, :hash, :exp)");
            $insStmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $insStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insStmt->bindValue(':hash', $newTokenHash, SQLITE3_TEXT);
            $insStmt->bindValue(':exp', $newExpiresAt, SQLITE3_INTEGER);
            $insStmt->execute();

            setcookie('remember_token', $newRawToken, rememberCookieOptions($newExpiresAt));
            rememberLogLoginStatus($rdb, $userId, $cid, 'success_auto', $now);
            rememberUpsertUserSession($rdb, $userId, $cid, session_id(), $now);
            rememberAudit($rdb, 'auto_login_success', $userId, $cid, [
                'rotated' => true
            ]);
            rememberAudit($rdb, 'token_rotated', $userId, $cid, [
                'source' => 'auto_login',
                'expires_at' => $newExpiresAt
            ]);

            $autoLoggedIn = true;
        } else {
            // Invalid or expired token — clear cookie
            rememberLogLoginStatus($rdb, intval($row['user_id'] ?? 0), intval($row['cid'] ?? 0), 'failed_auto', $now);
            rememberAudit($rdb, 'auto_login_rejected', 0, 0, [
                'reason' => $row ? 'inactive_user' : 'invalid_or_expired_token'
            ]);
            setcookie('remember_token', '', rememberCookieOptions(time() - 3600));
        }

        $rdb->close();
    }

    if (!$autoLoggedIn) {
        header("Location: login.php");
        exit;
    }
}

if (!empty($_SESSION['isLogedin'])) {
    $rdb = appDbConnect();
    $now = time();
    ensureAuthSecurityTables($rdb);
    $currentSessionId = session_id();
    $uid = intval($_SESSION['user_id'] ?? 0);
    $cid = intval($_SESSION['cid'] ?? 0);
    if ($uid > 0 && $cid > 0) {
        if (rememberSessionExists($rdb, $currentSessionId, $uid, $cid)) {
            rememberTouchSession($rdb, $currentSessionId, $now);
        } else {
            // Backward compatibility: if no sessions exist for this user yet, bootstrap one.
            if (!rememberUserHasAnySession($rdb, $uid, $cid)) {
                rememberUpsertUserSession($rdb, $uid, $cid, $currentSessionId, $now);
            } else {
                // Session was revoked from another device.
                $rdb->close();
                session_unset();
                session_destroy();
                header("Location: login.php");
                exit;
            }
        }
    }

    $rdb->close();
}
?>