<?php
require_once __DIR__ . '/helpers.php';

requirePermission($db, 'manage_users');

function ensureRbacSchemaForSettings(AppDbConnection $db): void {
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

    $columns = [];
    $columnRes = $db->query("PRAGMA table_info(users)");
    while ($col = $columnRes->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = strtolower((string)($col['name'] ?? ''));
    }
    if (!in_array('email_verified_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verified_at INTEGER");
        $db->exec("UPDATE users SET email_verified_at = strftime('%s','now') WHERE email_verified_at IS NULL");
    }
    if (!in_array('email_verification_token_hash', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_token_hash TEXT");
    }
    if (!in_array('email_verification_expires_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_expires_at INTEGER");
    }

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

    $db->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        cid INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        login_time INTEGER,
        status TEXT
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_remember_audit_created_at ON remember_token_audit(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id, cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_logs_time ON login_logs(login_time)");
}

function seedRolesAndPermissionsForSettings(AppDbConnection $db, int $cid): void {
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

ensureRbacSchemaForSettings($db);
seedRolesAndPermissionsForSettings($db, $cid);

switch ($action) {
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



    default:
        respond('error', 'Unknown action');
}

?>