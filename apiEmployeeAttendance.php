<?php
require_once __DIR__ . '/helpers.php';

function attendanceEnsureSchema(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS attendance_policies (
        policy_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        resumption_time TEXT NOT NULL DEFAULT '09:00',
        fine_0_15 REAL NOT NULL DEFAULT 200,
        fine_15_60 REAL NOT NULL DEFAULT 500,
        fine_60_plus REAL NOT NULL DEFAULT 1000,
        updated_by INTEGER,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (cid)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS employee_attendance_credentials (
        credential_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        pin_hash TEXT,
        biometric_hash TEXT,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (cid, user_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS employee_attendance_logs (
        attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        attendance_date TEXT NOT NULL,
        signin_at TEXT,
        signout_at TEXT,
        signin_method TEXT NOT NULL DEFAULT 'pin',
        minutes_late INTEGER DEFAULT 0,
        late_grade TEXT DEFAULT 'on_time',
        fine_amount REAL DEFAULT 0,
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (cid, user_id, attendance_date)
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_logs_cid_date ON employee_attendance_logs(cid, attendance_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_logs_user_date ON employee_attendance_logs(user_id, attendance_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_policy_cid ON attendance_policies(cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_credentials_user ON employee_attendance_credentials(user_id, cid)");
}

function attendanceIsManagerOrOwner(AppDbConnection $db): bool {
    $role = strtolower(trim(currentUserPrimaryRole($db)));
    return in_array($role, ['owner', 'manager'], true);
}

function attendanceEnsureManagerOrOwner(AppDbConnection $db): void {
    if (!attendanceIsManagerOrOwner($db)) {
        respond('error', 'Unauthorized');
    }
}

function attendanceEnsureOwnerOnly(AppDbConnection $db): void {
    if (!currentUserHasRole($db, 'Owner')) {
        respond('error', 'Only owner can change attendance policy.');
    }
}

function attendanceGetPolicy(AppDbConnection $db, int $cid): array {
    $stmt = $db->prepare("SELECT resumption_time,
                                 fine_0_15,
                                 fine_15_60,
                                 fine_60_plus
                          FROM attendance_policies
                          WHERE cid = :cid
                          LIMIT 1");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    return [
        'resumption_time' => (string)($row['resumption_time'] ?? '09:00'),
        'fine_0_15' => floatval($row['fine_0_15'] ?? 200),
        'fine_15_60' => floatval($row['fine_15_60'] ?? 500),
        'fine_60_plus' => floatval($row['fine_60_plus'] ?? 1000),
    ];
}

function attendanceGetRoleMapByUserId(AppDbConnection $db, int $cid): array {
    $map = [];
    $stmt = $db->prepare("SELECT u.user_id,
                                 COALESCE((
                                     SELECT r.role_name
                                     FROM user_roles ur
                                     JOIN roles r ON r.role_id = ur.role_id
                                     WHERE ur.user_id = u.user_id
                                     ORDER BY CASE lower(r.role_name)
                                         WHEN 'owner' THEN 1
                                         WHEN 'manager' THEN 2
                                         WHEN 'staff' THEN 3
                                         ELSE 4 END,
                                         r.role_name ASC
                                     LIMIT 1
                                 ), 'User') AS role_name
                          FROM users u
                          WHERE u.cid = :cid");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $uid = intval($row['user_id'] ?? 0);
        if ($uid > 0) {
            $map[$uid] = (string)($row['role_name'] ?? 'User');
        }
    }
    return $map;
}

function attendanceCalculateLateMeta(string $signinAt, array $policy): array {
    $signinTs = strtotime($signinAt);
    $datePrefix = substr($signinAt, 0, 10);
    $resumptionTime = trim((string)($policy['resumption_time'] ?? '09:00'));
    $resumptionAt = strtotime($datePrefix . ' ' . $resumptionTime . ':00');
    if ($signinTs === false || $resumptionAt === false) {
        return ['minutes_late' => 0, 'late_grade' => 'on_time', 'fine_amount' => 0.0];
    }

    $minutesLate = max(0, intval(round(($signinTs - $resumptionAt) / 60)));
    $grade = 'on_time';
    $fine = 0.0;

    if ($minutesLate > 0 && $minutesLate <= 15) {
        $grade = 'late_low';
        $fine = floatval($policy['fine_0_15'] ?? 200);
    } elseif ($minutesLate > 15 && $minutesLate <= 60) {
        $grade = 'late_mid';
        $fine = floatval($policy['fine_15_60'] ?? 500);
    } elseif ($minutesLate > 60) {
        $grade = 'late_high';
        $fine = floatval($policy['fine_60_plus'] ?? 1000);
    }

    return [
        'minutes_late' => $minutesLate,
        'late_grade' => $grade,
        'fine_amount' => $fine,
    ];
}

function attendanceVerifyCredential(AppDbConnection $db, int $cid, int $userId, string $method, string $secret): bool {
    if ($method === '' || $secret === '') {
        return false;
    }

    $stmt = $db->prepare("SELECT pin_hash, biometric_hash
                          FROM employee_attendance_credentials
                          WHERE cid = :cid AND user_id = :uid
                          LIMIT 1");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$row) {
        return false;
    }

    if ($method === 'pin') {
        $hash = (string)($row['pin_hash'] ?? '');
        return $hash !== '' && password_verify($secret, $hash);
    }

    if ($method === 'biometric') {
        $hash = (string)($row['biometric_hash'] ?? '');
        return $hash !== '' && password_verify($secret, $hash);
    }

    return false;
}

function attendanceBuildRangeFilter(string $range): array {
    $range = strtolower(trim($range));
    if (!in_array($range, ['today', '7d', '30d', 'all'], true)) {
        $range = '30d';
    }

    if ($range === 'all') {
        return ['range' => 'all', 'from' => '', 'to' => '', 'filter' => '', 'params' => []];
    }

    $daysMap = ['today' => 0, '7d' => 6, '30d' => 29];
    $todayStart = new DateTimeImmutable('today');
    $from = $todayStart->modify('-' . $daysMap[$range] . ' days');
    $to = $todayStart->modify('+1 day');

    return [
        'range' => $range,
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'filter' => ' AND l.attendance_date >= :from_date AND l.attendance_date < :to_date',
        'params' => [
            ':from_date' => $from->format('Y-m-d'),
            ':to_date' => $to->format('Y-m-d'),
        ],
    ];
}

try {
    attendanceEnsureSchema($db);
    attendanceEnsureManagerOrOwner($db);

    $cid = intval($_SESSION['cid'] ?? 0);
    $uid = intval($_SESSION['user_id'] ?? 0);

    switch ($action) {
        case 'loadAttendancePolicy':
            $policy = attendanceGetPolicy($db, $cid);
            respond('success', 'Attendance policy loaded', ['data' => $policy]);
            break;

        case 'saveAttendancePolicy':
            attendanceEnsureOwnerOnly($db);

            $resumptionTime = trim((string)($_POST['resumption_time'] ?? '09:00'));
            $fine0To15 = floatval($_POST['fine_0_15'] ?? 200);
            $fine15To60 = floatval($_POST['fine_15_60'] ?? 500);
            $fine60Plus = floatval($_POST['fine_60_plus'] ?? 1000);

            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $resumptionTime)) {
                respond('error', 'Resumption time must be in HH:MM format.');
            }

            if ($fine0To15 < 0 || $fine15To60 < 0 || $fine60Plus < 0) {
                respond('error', 'Fine values cannot be negative.');
            }

            $stmt = $db->prepare("INSERT INTO attendance_policies (
                                    cid, resumption_time, fine_0_15, fine_15_60, fine_60_plus, updated_by, updated_at
                                  ) VALUES (
                                    :cid, :resumption_time, :fine_0_15, :fine_15_60, :fine_60_plus, :updated_by, :updated_at
                                  )");
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':resumption_time', $resumptionTime, SQLITE3_TEXT);
            $stmt->bindValue(':fine_0_15', $fine0To15, SQLITE3_FLOAT);
            $stmt->bindValue(':fine_15_60', $fine15To60, SQLITE3_FLOAT);
            $stmt->bindValue(':fine_60_plus', $fine60Plus, SQLITE3_FLOAT);
            $stmt->bindValue(':updated_by', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);

            try {
                $stmt->execute();
            } catch (Throwable $e) {
                $update = $db->prepare("UPDATE attendance_policies
                                        SET resumption_time = :resumption_time,
                                            fine_0_15 = :fine_0_15,
                                            fine_15_60 = :fine_15_60,
                                            fine_60_plus = :fine_60_plus,
                                            updated_by = :updated_by,
                                            updated_at = :updated_at
                                        WHERE cid = :cid");
                $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $update->bindValue(':resumption_time', $resumptionTime, SQLITE3_TEXT);
                $update->bindValue(':fine_0_15', $fine0To15, SQLITE3_FLOAT);
                $update->bindValue(':fine_15_60', $fine15To60, SQLITE3_FLOAT);
                $update->bindValue(':fine_60_plus', $fine60Plus, SQLITE3_FLOAT);
                $update->bindValue(':updated_by', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
                $update->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
                $update->execute();
            }

            respond('success', 'Attendance policy saved.');
            break;

        case 'loadEmployees':
            $employees = [];
            $stmt = $db->prepare("SELECT u.user_id,
                                         u.full_name,
                                         u.email,
                                         u.is_active,
                                         u.created_at,
                                         COALESCE((
                                            SELECT r.role_name
                                            FROM user_roles ur
                                            JOIN roles r ON r.role_id = ur.role_id
                                            WHERE ur.user_id = u.user_id
                                            ORDER BY CASE lower(r.role_name)
                                                WHEN 'owner' THEN 1
                                                WHEN 'manager' THEN 2
                                                WHEN 'staff' THEN 3
                                                ELSE 4 END
                                            LIMIT 1
                                         ), 'User') AS role_name,
                                         CASE WHEN c.pin_hash IS NULL OR c.pin_hash = '' THEN 0 ELSE 1 END AS has_pin,
                                         CASE WHEN c.biometric_hash IS NULL OR c.biometric_hash = '' THEN 0 ELSE 1 END AS has_biometric
                                  FROM users u
                                  LEFT JOIN employee_attendance_credentials c
                                    ON c.cid = u.cid AND c.user_id = u.user_id
                                  WHERE u.cid = :cid
                                    AND lower(COALESCE((
                                        SELECT r2.role_name
                                        FROM user_roles ur2
                                        JOIN roles r2 ON r2.role_id = ur2.role_id
                                        WHERE ur2.user_id = u.user_id
                                        ORDER BY CASE lower(r2.role_name)
                                            WHEN 'owner' THEN 1
                                            WHEN 'manager' THEN 2
                                            WHEN 'staff' THEN 3
                                            ELSE 4 END
                                        LIMIT 1
                                    ), 'user')) <> 'owner'
                                  ORDER BY u.full_name ASC");
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $res = $stmt->execute();
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $employees[] = [
                    'user_id' => intval($row['user_id'] ?? 0),
                    'full_name' => (string)($row['full_name'] ?? ''),
                    'email' => (string)($row['email'] ?? ''),
                    'role_name' => (string)($row['role_name'] ?? 'User'),
                    'is_active' => intval($row['is_active'] ?? 0),
                    'created_at' => intval($row['created_at'] ?? 0),
                    'has_pin' => intval($row['has_pin'] ?? 0),
                    'has_biometric' => intval($row['has_biometric'] ?? 0),
                ];
            }

            respond('success', 'Employees loaded', ['data' => $employees]);
            break;

        case 'saveAttendanceCredential':
            attendanceEnsureOwnerOnly($db);

            $employeeId = intval($_POST['user_id'] ?? 0);
            $method = strtolower(trim((string)($_POST['method'] ?? 'pin')));
            $secret = trim((string)($_POST['secret'] ?? ''));

            if ($employeeId <= 0) {
                respond('error', 'Employee is required.');
            }

            if (!in_array($method, ['pin', 'biometric'], true)) {
                respond('error', 'Invalid credential method.');
            }

            if ($method === 'pin' && !preg_match('/^\d{4,8}$/', $secret)) {
                respond('error', 'PIN must be 4 to 8 digits.');
            }

            if ($method === 'biometric' && strlen($secret) < 4) {
                respond('error', 'Biometric token must be at least 4 characters.');
            }

            $check = $db->prepare("SELECT 1 FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
            $check->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $check->bindValue(':cid', $cid, SQLITE3_INTEGER);
            if (!$check->execute()->fetchArray(SQLITE3_ASSOC)) {
                respond('error', 'Employee not found.');
            }

            $hashed = password_hash($secret, PASSWORD_DEFAULT);
            $column = $method === 'pin' ? 'pin_hash' : 'biometric_hash';

            $insert = $db->prepare("INSERT INTO employee_attendance_credentials (cid, user_id, pin_hash, biometric_hash, updated_at)
                                    VALUES (:cid, :uid, :pin_hash, :biometric_hash, :updated_at)");
            $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insert->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $insert->bindValue(':pin_hash', $method === 'pin' ? $hashed : null, $method === 'pin' ? SQLITE3_TEXT : SQLITE3_NULL);
            $insert->bindValue(':biometric_hash', $method === 'biometric' ? $hashed : null, $method === 'biometric' ? SQLITE3_TEXT : SQLITE3_NULL);
            $insert->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);

            try {
                $insert->execute();
            } catch (Throwable $e) {
                $update = $db->prepare("UPDATE employee_attendance_credentials
                                        SET {$column} = :hash,
                                            updated_at = :updated_at
                                        WHERE cid = :cid AND user_id = :uid");
                $update->bindValue(':hash', $hashed, SQLITE3_TEXT);
                $update->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
                $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $update->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
                $update->execute();
            }

            respond('success', ucfirst($method) . ' credential saved.');
            break;

        case 'signInEmployee':
            $employeeId = intval($_POST['user_id'] ?? 0);
            $method = strtolower(trim((string)($_POST['method'] ?? 'pin')));
            $secret = trim((string)($_POST['secret'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($employeeId <= 0) {
                respond('error', 'Employee is required.');
            }

            if (!attendanceVerifyCredential($db, $cid, $employeeId, $method, $secret)) {
                respond('error', 'Credential verification failed.');
            }

            $todayDate = date('Y-m-d');
            $existing = $db->prepare("SELECT attendance_id, signin_at
                                     FROM employee_attendance_logs
                                     WHERE cid = :cid AND user_id = :uid AND attendance_date = :attendance_date
                                     LIMIT 1");
            $existing->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $existing->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $existing->bindValue(':attendance_date', $todayDate, SQLITE3_TEXT);
            $existingRow = $existing->execute()->fetchArray(SQLITE3_ASSOC);

            if ($existingRow && !empty($existingRow['signin_at'])) {
                respond('error', 'Employee already signed in today.');
            }

            $policy = attendanceGetPolicy($db, $cid);
            $signinAt = appNowBusinessDateTime();
            $lateMeta = attendanceCalculateLateMeta($signinAt, $policy);

            if ($existingRow) {
                $update = $db->prepare("UPDATE employee_attendance_logs
                                        SET signin_at = :signin_at,
                                            signin_method = :signin_method,
                                            minutes_late = :minutes_late,
                                            late_grade = :late_grade,
                                            fine_amount = :fine_amount,
                                            notes = :notes,
                                            updated_at = :updated_at
                                        WHERE attendance_id = :attendance_id AND cid = :cid");
                $update->bindValue(':signin_at', $signinAt, SQLITE3_TEXT);
                $update->bindValue(':signin_method', $method, SQLITE3_TEXT);
                $update->bindValue(':minutes_late', intval($lateMeta['minutes_late']), SQLITE3_INTEGER);
                $update->bindValue(':late_grade', (string)$lateMeta['late_grade'], SQLITE3_TEXT);
                $update->bindValue(':fine_amount', floatval($lateMeta['fine_amount']), SQLITE3_FLOAT);
                $update->bindValue(':notes', $notes, SQLITE3_TEXT);
                $update->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
                $update->bindValue(':attendance_id', intval($existingRow['attendance_id']), SQLITE3_INTEGER);
                $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $update->execute();
            } else {
                $ins = $db->prepare("INSERT INTO employee_attendance_logs (
                                        cid, user_id, attendance_date, signin_at, signin_method, minutes_late, late_grade, fine_amount, notes, created_at, updated_at
                                    ) VALUES (
                                        :cid, :uid, :attendance_date, :signin_at, :signin_method, :minutes_late, :late_grade, :fine_amount, :notes, :created_at, :updated_at
                                    )");
                $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $ins->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
                $ins->bindValue(':attendance_date', $todayDate, SQLITE3_TEXT);
                $ins->bindValue(':signin_at', $signinAt, SQLITE3_TEXT);
                $ins->bindValue(':signin_method', $method, SQLITE3_TEXT);
                $ins->bindValue(':minutes_late', intval($lateMeta['minutes_late']), SQLITE3_INTEGER);
                $ins->bindValue(':late_grade', (string)$lateMeta['late_grade'], SQLITE3_TEXT);
                $ins->bindValue(':fine_amount', floatval($lateMeta['fine_amount']), SQLITE3_FLOAT);
                $ins->bindValue(':notes', $notes, SQLITE3_TEXT);
                $ins->bindValue(':created_at', appNowBusinessDateTime(), SQLITE3_TEXT);
                $ins->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
                $ins->execute();
            }

            respond('success', 'Employee signed in.', ['data' => $lateMeta]);
            break;

        case 'signOutEmployee':
            $employeeId = intval($_POST['user_id'] ?? 0);
            if ($employeeId <= 0) {
                respond('error', 'Employee is required.');
            }

            $todayDate = date('Y-m-d');
            $stmt = $db->prepare("SELECT attendance_id, signin_at, signout_at
                                  FROM employee_attendance_logs
                                  WHERE cid = :cid AND user_id = :uid AND attendance_date = :attendance_date
                                  LIMIT 1");
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $stmt->bindValue(':attendance_date', $todayDate, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            if (!$row || empty($row['signin_at'])) {
                respond('error', 'Employee has not signed in today.');
            }

            if (!empty($row['signout_at'])) {
                respond('error', 'Employee already signed out today.');
            }

            $update = $db->prepare("UPDATE employee_attendance_logs
                                    SET signout_at = :signout_at,
                                        updated_at = :updated_at
                                    WHERE attendance_id = :attendance_id AND cid = :cid");
            $update->bindValue(':signout_at', appNowBusinessDateTime(), SQLITE3_TEXT);
            $update->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
            $update->bindValue(':attendance_id', intval($row['attendance_id']), SQLITE3_INTEGER);
            $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $update->execute();

            respond('success', 'Employee signed out.');
            break;

        case 'loadEmployeeOverview':
            $range = strtolower(trim((string)($_POST['range'] ?? '30d')));
            $rangeMeta = attendanceBuildRangeFilter($range);

            $roleMap = attendanceGetRoleMapByUserId($db, $cid);
            $employees = [];
            $usersStmt = $db->prepare("SELECT user_id, full_name, email, is_active
                                       FROM users
                                       WHERE cid = :cid
                                       ORDER BY full_name ASC");
            $usersStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $usersRes = $usersStmt->execute();
            while ($usersRes && ($u = $usersRes->fetchArray(SQLITE3_ASSOC))) {
                $eid = intval($u['user_id'] ?? 0);
                $role = strtolower((string)($roleMap[$eid] ?? 'user'));
                if ($role === 'owner') {
                    continue;
                }
                $employees[$eid] = [
                    'user_id' => $eid,
                    'full_name' => (string)($u['full_name'] ?? ''),
                    'email' => (string)($u['email'] ?? ''),
                    'role_name' => (string)($roleMap[$eid] ?? 'User'),
                    'is_active' => intval($u['is_active'] ?? 0),
                    'attendance_days' => 0,
                    'on_time_days' => 0,
                    'late_days' => 0,
                    'signout_days' => 0,
                    'total_late_minutes' => 0,
                    'total_fine' => 0.0,
                    'gpi' => 0.0,
                    'performance_tone' => 'danger',
                    'performance_label' => 'Needs attention',
                ];
            }

            if (count($employees) === 0) {
                respond('success', 'No employees found', [
                    'data' => [],
                    'summary' => [
                        'employees' => 0,
                        'signed_in_today' => 0,
                        'late_today' => 0,
                        'total_fines_today' => 0,
                    ]
                ]);
            }

            $statsStmt = $db->prepare("SELECT l.user_id,
                                              COUNT(l.attendance_id) AS attendance_days,
                                              SUM(CASE WHEN l.minutes_late <= 0 THEN 1 ELSE 0 END) AS on_time_days,
                                              SUM(CASE WHEN l.minutes_late > 0 THEN 1 ELSE 0 END) AS late_days,
                                              SUM(CASE WHEN l.signout_at IS NOT NULL AND l.signout_at <> '' THEN 1 ELSE 0 END) AS signout_days,
                                              SUM(COALESCE(l.minutes_late, 0)) AS total_late_minutes,
                                              SUM(COALESCE(l.fine_amount, 0)) AS total_fine
                                       FROM employee_attendance_logs l
                                       WHERE l.cid = :cid {$rangeMeta['filter']}
                                       GROUP BY l.user_id");
            $statsStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            bindParams($statsStmt, $rangeMeta['params']);
            $statsRes = $statsStmt->execute();

            while ($statsRes && ($row = $statsRes->fetchArray(SQLITE3_ASSOC))) {
                $employeeId = intval($row['user_id'] ?? 0);
                if (!isset($employees[$employeeId])) {
                    continue;
                }

                $attendanceDays = intval($row['attendance_days'] ?? 0);
                $onTimeDays = intval($row['on_time_days'] ?? 0);
                $lateDays = intval($row['late_days'] ?? 0);
                $signoutDays = intval($row['signout_days'] ?? 0);
                $lateMinutes = intval($row['total_late_minutes'] ?? 0);
                $totalFine = floatval($row['total_fine'] ?? 0);

                $lateRate = $attendanceDays > 0 ? ($lateDays / $attendanceDays) : 0;
                $avgLatePenalty = $attendanceDays > 0 ? min(1.0, ($lateMinutes / max(1, $attendanceDays)) / 60.0) : 0;
                $absencePenalty = max(0.0, 1.0 - ($attendanceDays / max(1, 22)));
                $attendanceScore = min(100, ($attendanceDays / max(1, 22)) * 100);
                $lateScore = ($lateRate * 35) + ($avgLatePenalty * 20);
                $gpi = max(0.0, round($attendanceScore - $lateScore - ($absencePenalty * 20), 2));

                $tone = 'danger';
                $label = 'Needs attention';
                if ($gpi >= 80) {
                    $tone = 'success';
                    $label = 'Excellent';
                } elseif ($gpi >= 60) {
                    $tone = 'warning';
                    $label = 'Average';
                }

                $employees[$employeeId]['attendance_days'] = $attendanceDays;
                $employees[$employeeId]['on_time_days'] = $onTimeDays;
                $employees[$employeeId]['late_days'] = $lateDays;
                $employees[$employeeId]['signout_days'] = $signoutDays;
                $employees[$employeeId]['total_late_minutes'] = $lateMinutes;
                $employees[$employeeId]['total_fine'] = $totalFine;
                $employees[$employeeId]['gpi'] = $gpi;
                $employees[$employeeId]['performance_tone'] = $tone;
                $employees[$employeeId]['performance_label'] = $label;
            }

            $summaryStmt = $db->prepare("SELECT
                                            COUNT(CASE WHEN l.signin_at IS NOT NULL AND l.signin_at <> '' THEN 1 END) AS signed_in_today,
                                            COUNT(CASE WHEN l.minutes_late > 0 THEN 1 END) AS late_today,
                                            COALESCE(SUM(COALESCE(l.fine_amount, 0)), 0) AS total_fines_today
                                         FROM employee_attendance_logs l
                                         WHERE l.cid = :cid
                                           AND l.attendance_date = :today_date");
            $summaryStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $summaryStmt->bindValue(':today_date', date('Y-m-d'), SQLITE3_TEXT);
            $summaryRow = $summaryStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

            respond('success', 'Attendance overview loaded', [
                'data' => array_values($employees),
                'summary' => [
                    'employees' => count($employees),
                    'signed_in_today' => intval($summaryRow['signed_in_today'] ?? 0),
                    'late_today' => intval($summaryRow['late_today'] ?? 0),
                    'total_fines_today' => floatval($summaryRow['total_fines_today'] ?? 0),
                ]
            ]);
            break;

        case 'loadEmployeeProfile':
            $employeeId = intval($_POST['user_id'] ?? 0);
            $range = strtolower(trim((string)($_POST['range'] ?? '30d')));
            $rangeMeta = attendanceBuildRangeFilter($range);

            if ($employeeId <= 0) {
                respond('error', 'Employee is required.');
            }

            $profileStmt = $db->prepare("SELECT u.user_id,
                                                u.full_name,
                                                u.email,
                                                u.is_active,
                                                COALESCE((
                                                    SELECT r.role_name
                                                    FROM user_roles ur
                                                    JOIN roles r ON r.role_id = ur.role_id
                                                    WHERE ur.user_id = u.user_id
                                                    ORDER BY CASE lower(r.role_name)
                                                        WHEN 'owner' THEN 1
                                                        WHEN 'manager' THEN 2
                                                        WHEN 'staff' THEN 3
                                                        ELSE 4 END
                                                    LIMIT 1
                                                ), 'User') AS role_name,
                                                CASE WHEN c.pin_hash IS NULL OR c.pin_hash = '' THEN 0 ELSE 1 END AS has_pin,
                                                CASE WHEN c.biometric_hash IS NULL OR c.biometric_hash = '' THEN 0 ELSE 1 END AS has_biometric
                                         FROM users u
                                         LEFT JOIN employee_attendance_credentials c
                                           ON c.cid = u.cid AND c.user_id = u.user_id
                                         WHERE u.cid = :cid AND u.user_id = :uid
                                         LIMIT 1");
            $profileStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $profileStmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $profile = $profileStmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$profile || strtolower((string)($profile['role_name'] ?? '')) === 'owner') {
                respond('error', 'Employee not found.');
            }

            $activityStmt = $db->prepare("SELECT attendance_date,
                                                 signin_at,
                                                 signout_at,
                                                 signin_method,
                                                 minutes_late,
                                                 late_grade,
                                                 fine_amount,
                                                 notes
                                          FROM employee_attendance_logs l
                                          WHERE l.cid = :cid AND l.user_id = :uid {$rangeMeta['filter']}
                                          ORDER BY l.attendance_date DESC
                                          LIMIT 120");
            $activityStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $activityStmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            bindParams($activityStmt, $rangeMeta['params']);
            $activityRes = $activityStmt->execute();

            $activities = [];
            $chartLabels = [];
            $chartOnTime = [];
            $chartLate = [];

            while ($activityRes && ($row = $activityRes->fetchArray(SQLITE3_ASSOC))) {
                $minutesLate = intval($row['minutes_late'] ?? 0);
                $dateLabel = (string)($row['attendance_date'] ?? '');

                $activities[] = [
                    'attendance_date' => $dateLabel,
                    'signin_at' => (string)($row['signin_at'] ?? ''),
                    'signout_at' => (string)($row['signout_at'] ?? ''),
                    'signin_method' => (string)($row['signin_method'] ?? 'pin'),
                    'minutes_late' => $minutesLate,
                    'late_grade' => (string)($row['late_grade'] ?? 'on_time'),
                    'fine_amount' => floatval($row['fine_amount'] ?? 0),
                    'notes' => (string)($row['notes'] ?? ''),
                    'status_color' => $minutesLate <= 0 ? 'green' : ($minutesLate <= 60 ? 'yellow' : 'red'),
                ];

                $chartLabels[] = $dateLabel;
                $chartOnTime[] = $minutesLate <= 0 ? 1 : 0;
                $chartLate[] = $minutesLate > 0 ? 1 : 0;
            }

            respond('success', 'Employee profile loaded', [
                'data' => [
                    'profile' => [
                        'user_id' => intval($profile['user_id'] ?? 0),
                        'full_name' => (string)($profile['full_name'] ?? ''),
                        'email' => (string)($profile['email'] ?? ''),
                        'role_name' => (string)($profile['role_name'] ?? 'User'),
                        'is_active' => intval($profile['is_active'] ?? 0),
                        'has_pin' => intval($profile['has_pin'] ?? 0),
                        'has_biometric' => intval($profile['has_biometric'] ?? 0),
                    ],
                    'activities' => $activities,
                    'chart' => [
                        'labels' => array_reverse($chartLabels),
                        'on_time' => array_reverse($chartOnTime),
                        'late' => array_reverse($chartLate),
                    ]
                ]
            ]);
            break;

        default:
            respond('error', 'Unknown action: ' . (string)$action);
    }
} catch (Throwable $e) {
    respond('error', 'Attendance API failed: ' . $e->getMessage());
}
