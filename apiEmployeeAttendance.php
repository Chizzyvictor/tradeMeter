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

    $db->exec("CREATE TABLE IF NOT EXISTS employee_shift_rules (
        shift_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        shift_start TEXT NOT NULL DEFAULT '09:00',
        shift_end TEXT NOT NULL DEFAULT '17:00',
        grace_minutes INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
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
        signin_method TEXT NOT NULL DEFAULT 'account_password',
        minutes_late INTEGER DEFAULT 0,
        late_grade TEXT DEFAULT 'on_time',
        fine_amount REAL DEFAULT 0,
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (cid, user_id, attendance_date)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS attendance_corrections (
        correction_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        attendance_date TEXT NOT NULL,
        requested_by INTEGER,
        current_signin_at TEXT,
        current_signout_at TEXT,
        proposed_signin_at TEXT,
        proposed_signout_at TEXT,
        reason TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        reviewed_by INTEGER,
        review_note TEXT,
        reviewed_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_logs_cid_date ON employee_attendance_logs(cid, attendance_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_logs_user_date ON employee_attendance_logs(user_id, attendance_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_policy_cid ON attendance_policies(cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_shift_rules_user ON employee_shift_rules(user_id, cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_corrections_cid_status ON attendance_corrections(cid, status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_attendance_corrections_user_date ON attendance_corrections(user_id, attendance_date)");
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

function attendanceGetShift(AppDbConnection $db, int $cid, int $userId): array {
    $stmt = $db->prepare("SELECT shift_start, shift_end, grace_minutes, is_active
                          FROM employee_shift_rules
                          WHERE cid = :cid AND user_id = :uid
                          LIMIT 1");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    $policy = attendanceGetPolicy($db, $cid);
    $active = intval($row['is_active'] ?? 0) === 1;

    return [
        'shift_start' => $active ? (string)($row['shift_start'] ?? $policy['resumption_time']) : (string)$policy['resumption_time'],
        'shift_end' => $active ? (string)($row['shift_end'] ?? '17:00') : '17:00',
        'grace_minutes' => $active ? intval($row['grace_minutes'] ?? 0) : 0,
        'is_active' => $active ? 1 : 0,
    ];
}

function attendanceResolveUserByLogin(AppDbConnection $db, int $cid, string $email): ?array {
    $stmt = $db->prepare("SELECT u.user_id,
                                 u.full_name,
                                 u.email,
                                 u.password,
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
                                 ), 'User') AS role_name
                          FROM users u
                          WHERE u.cid = :cid
                            AND lower(u.email) = lower(:email)
                          LIMIT 1");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return is_array($row) ? $row : null;
}

function attendanceCalculateLateMetaWithShift(string $signinAt, array $policy, array $shift): array {
    $signinTs = strtotime($signinAt);
    $datePrefix = substr($signinAt, 0, 10);
    $shiftStart = trim((string)($shift['shift_start'] ?? $policy['resumption_time']));
    $grace = max(0, intval($shift['grace_minutes'] ?? 0));
    $startTs = strtotime($datePrefix . ' ' . $shiftStart . ':00');
    if ($signinTs === false || $startTs === false) {
        return ['minutes_late' => 0, 'late_grade' => 'on_time', 'fine_amount' => 0.0];
    }

    $minutesLate = max(0, intval(round(($signinTs - ($startTs + ($grace * 60))) / 60)));
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

function attendanceComputePerformanceMetrics(int $attendanceDays, int $lateDays, int $lateMinutes): array {
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

    return [
        'gpi' => $gpi,
        'performance_tone' => $tone,
        'performance_label' => $label,
    ];
}

function attendanceBuildRangeFilter(string $range): array {
    $range = strtolower(trim($range));
    if (!in_array($range, ['today', '7d', '30d', 'all'], true)) {
        $range = '30d';
    }

    if ($range === 'all') {
        return ['range' => 'all', 'filter' => '', 'params' => []];
    }

    $daysMap = ['today' => 0, '7d' => 6, '30d' => 29];
    $todayStart = new DateTimeImmutable('today');
    $from = $todayStart->modify('-' . $daysMap[$range] . ' days');
    $to = $todayStart->modify('+1 day');

    return [
        'range' => $range,
        'filter' => ' AND l.attendance_date >= :from_date AND l.attendance_date < :to_date',
        'params' => [
            ':from_date' => $from->format('Y-m-d'),
            ':to_date' => $to->format('Y-m-d'),
        ],
    ];
}

function attendanceUpsertLog(AppDbConnection $db, int $cid, int $userId, string $date, ?string $signinAt, ?string $signoutAt, string $method, array $lateMeta, string $notes): void {
    $existing = $db->prepare("SELECT attendance_id FROM employee_attendance_logs WHERE cid = :cid AND user_id = :uid AND attendance_date = :dt LIMIT 1");
    $existing->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $existing->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $existing->bindValue(':dt', $date, SQLITE3_TEXT);
    $row = $existing->execute()->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $up = $db->prepare("UPDATE employee_attendance_logs
                            SET signin_at = :signin_at,
                                signout_at = :signout_at,
                                signin_method = :signin_method,
                                minutes_late = :minutes_late,
                                late_grade = :late_grade,
                                fine_amount = :fine_amount,
                                notes = :notes,
                                updated_at = :updated_at
                            WHERE attendance_id = :aid AND cid = :cid");
        $up->bindValue(':signin_at', $signinAt, $signinAt === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $up->bindValue(':signout_at', $signoutAt, $signoutAt === null ? SQLITE3_NULL : SQLITE3_TEXT);
        $up->bindValue(':signin_method', $method, SQLITE3_TEXT);
        $up->bindValue(':minutes_late', intval($lateMeta['minutes_late'] ?? 0), SQLITE3_INTEGER);
        $up->bindValue(':late_grade', (string)($lateMeta['late_grade'] ?? 'on_time'), SQLITE3_TEXT);
        $up->bindValue(':fine_amount', floatval($lateMeta['fine_amount'] ?? 0), SQLITE3_FLOAT);
        $up->bindValue(':notes', $notes, SQLITE3_TEXT);
        $up->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
        $up->bindValue(':aid', intval($row['attendance_id']), SQLITE3_INTEGER);
        $up->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $up->execute();
        return;
    }

    $ins = $db->prepare("INSERT INTO employee_attendance_logs (
                            cid, user_id, attendance_date, signin_at, signout_at, signin_method, minutes_late, late_grade, fine_amount, notes, created_at, updated_at
                        ) VALUES (
                            :cid, :uid, :dt, :signin_at, :signout_at, :signin_method, :minutes_late, :late_grade, :fine_amount, :notes, :created_at, :updated_at
                        )");
    $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $ins->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $ins->bindValue(':dt', $date, SQLITE3_TEXT);
    $ins->bindValue(':signin_at', $signinAt, $signinAt === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $ins->bindValue(':signout_at', $signoutAt, $signoutAt === null ? SQLITE3_NULL : SQLITE3_TEXT);
    $ins->bindValue(':signin_method', $method, SQLITE3_TEXT);
    $ins->bindValue(':minutes_late', intval($lateMeta['minutes_late'] ?? 0), SQLITE3_INTEGER);
    $ins->bindValue(':late_grade', (string)($lateMeta['late_grade'] ?? 'on_time'), SQLITE3_TEXT);
    $ins->bindValue(':fine_amount', floatval($lateMeta['fine_amount'] ?? 0), SQLITE3_FLOAT);
    $ins->bindValue(':notes', $notes, SQLITE3_TEXT);
    $ins->bindValue(':created_at', appNowBusinessDateTime(), SQLITE3_TEXT);
    $ins->bindValue(':updated_at', appNowBusinessDateTime(), SQLITE3_TEXT);
    $ins->execute();
}

try {
    attendanceEnsureSchema($db);
    attendanceEnsureManagerOrOwner($db);

    $cid = intval($_SESSION['cid'] ?? 0);
    $uid = intval($_SESSION['user_id'] ?? 0);

    switch ($action) {
        case 'loadAttendancePolicy':
            respond('success', 'Attendance policy loaded', ['data' => attendanceGetPolicy($db, $cid)]);
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

            $insert = $db->prepare("INSERT INTO attendance_policies (cid, resumption_time, fine_0_15, fine_15_60, fine_60_plus, updated_by, updated_at)
                                    VALUES (:cid, :rt, :f1, :f2, :f3, :ub, :ua)");
            $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insert->bindValue(':rt', $resumptionTime, SQLITE3_TEXT);
            $insert->bindValue(':f1', $fine0To15, SQLITE3_FLOAT);
            $insert->bindValue(':f2', $fine15To60, SQLITE3_FLOAT);
            $insert->bindValue(':f3', $fine60Plus, SQLITE3_FLOAT);
            $insert->bindValue(':ub', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
            $insert->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
            try {
                $insert->execute();
            } catch (Throwable $e) {
                $up = $db->prepare("UPDATE attendance_policies
                                    SET resumption_time = :rt,
                                        fine_0_15 = :f1,
                                        fine_15_60 = :f2,
                                        fine_60_plus = :f3,
                                        updated_by = :ub,
                                        updated_at = :ua
                                    WHERE cid = :cid");
                $up->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $up->bindValue(':rt', $resumptionTime, SQLITE3_TEXT);
                $up->bindValue(':f1', $fine0To15, SQLITE3_FLOAT);
                $up->bindValue(':f2', $fine15To60, SQLITE3_FLOAT);
                $up->bindValue(':f3', $fine60Plus, SQLITE3_FLOAT);
                $up->bindValue(':ub', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
                $up->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
                $up->execute();
            }
            respond('success', 'Attendance policy saved.');
            break;

        case 'loadEmployees':
            $rows = [];
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
                                         COALESCE(sr.shift_start, '') AS shift_start,
                                         COALESCE(sr.shift_end, '') AS shift_end,
                                         COALESCE(sr.grace_minutes, 0) AS grace_minutes,
                                         COALESCE(sr.is_active, 0) AS has_shift
                                  FROM users u
                                  LEFT JOIN employee_shift_rules sr ON sr.cid = u.cid AND sr.user_id = u.user_id
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
                $rows[] = [
                    'user_id' => intval($row['user_id'] ?? 0),
                    'full_name' => (string)($row['full_name'] ?? ''),
                    'email' => (string)($row['email'] ?? ''),
                    'role_name' => (string)($row['role_name'] ?? 'User'),
                    'is_active' => intval($row['is_active'] ?? 0),
                    'created_at' => intval($row['created_at'] ?? 0),
                    'shift_start' => (string)($row['shift_start'] ?? ''),
                    'shift_end' => (string)($row['shift_end'] ?? ''),
                    'grace_minutes' => intval($row['grace_minutes'] ?? 0),
                    'has_shift' => intval($row['has_shift'] ?? 0),
                ];
            }
            respond('success', 'Employees loaded', ['data' => $rows]);
            break;

        case 'saveShiftRule':
            $userId = intval($_POST['user_id'] ?? 0);
            $shiftStart = trim((string)($_POST['shift_start'] ?? '09:00'));
            $shiftEnd = trim((string)($_POST['shift_end'] ?? '17:00'));
            $graceMinutes = max(0, intval($_POST['grace_minutes'] ?? 0));
            $isActive = intval($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

            if ($userId <= 0) respond('error', 'Employee is required.');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $shiftStart)) respond('error', 'Shift start must be HH:MM.');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $shiftEnd)) respond('error', 'Shift end must be HH:MM.');

            $insert = $db->prepare("INSERT INTO employee_shift_rules (cid, user_id, shift_start, shift_end, grace_minutes, is_active, updated_at)
                                    VALUES (:cid, :uid, :ss, :se, :gm, :ia, :ua)");
            $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insert->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $insert->bindValue(':ss', $shiftStart, SQLITE3_TEXT);
            $insert->bindValue(':se', $shiftEnd, SQLITE3_TEXT);
            $insert->bindValue(':gm', $graceMinutes, SQLITE3_INTEGER);
            $insert->bindValue(':ia', $isActive, SQLITE3_INTEGER);
            $insert->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
            try {
                $insert->execute();
            } catch (Throwable $e) {
                $up = $db->prepare("UPDATE employee_shift_rules
                                    SET shift_start = :ss,
                                        shift_end = :se,
                                        grace_minutes = :gm,
                                        is_active = :ia,
                                        updated_at = :ua
                                    WHERE cid = :cid AND user_id = :uid");
                $up->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $up->bindValue(':uid', $userId, SQLITE3_INTEGER);
                $up->bindValue(':ss', $shiftStart, SQLITE3_TEXT);
                $up->bindValue(':se', $shiftEnd, SQLITE3_TEXT);
                $up->bindValue(':gm', $graceMinutes, SQLITE3_INTEGER);
                $up->bindValue(':ia', $isActive, SQLITE3_INTEGER);
                $up->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
                $up->execute();
            }
            respond('success', 'Shift rule saved.');
            break;

        case 'signInEmployee':
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond('error', 'Valid employee email is required.');
            if ($password === '') respond('error', 'Employee password is required.');

            $employee = attendanceResolveUserByLogin($db, $cid, $email);
            if (!$employee) respond('error', 'Employee account not found.');
            if (intval($employee['is_active'] ?? 0) !== 1) respond('error', 'Employee account is inactive.');
            if (strtolower((string)($employee['role_name'] ?? '')) === 'owner') respond('error', 'Owner attendance is not tracked here.');
            if (!password_verify($password, (string)($employee['password'] ?? ''))) respond('error', 'Employee login credentials are invalid.');

            $employeeId = intval($employee['user_id'] ?? 0);
            $todayDate = date('Y-m-d');
            $existing = $db->prepare("SELECT attendance_id, signin_at FROM employee_attendance_logs WHERE cid = :cid AND user_id = :uid AND attendance_date = :dt LIMIT 1");
            $existing->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $existing->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $existing->bindValue(':dt', $todayDate, SQLITE3_TEXT);
            $existingRow = $existing->execute()->fetchArray(SQLITE3_ASSOC);
            if ($existingRow && !empty($existingRow['signin_at'])) respond('error', 'Employee already signed in today.');

            $signinAt = appNowBusinessDateTime();
            $policy = attendanceGetPolicy($db, $cid);
            $shift = attendanceGetShift($db, $cid, $employeeId);
            $lateMeta = attendanceCalculateLateMetaWithShift($signinAt, $policy, $shift);
            attendanceUpsertLog($db, $cid, $employeeId, $todayDate, $signinAt, null, 'account_password', $lateMeta, $notes);

            respond('success', 'Employee signed in.', [
                'data' => array_merge($lateMeta, [
                    'user_id' => $employeeId,
                    'full_name' => (string)($employee['full_name'] ?? ''),
                    'email' => (string)($employee['email'] ?? ''),
                ])
            ]);
            break;

        case 'signOutEmployee':
            $employeeId = intval($_POST['user_id'] ?? 0);
            if ($employeeId <= 0) respond('error', 'Employee is required.');
            $todayDate = date('Y-m-d');
            $stmt = $db->prepare("SELECT attendance_id, signin_at, signout_at FROM employee_attendance_logs WHERE cid = :cid AND user_id = :uid AND attendance_date = :dt LIMIT 1");
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $stmt->bindValue(':dt', $todayDate, SQLITE3_TEXT);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$row || empty($row['signin_at'])) respond('error', 'Employee has not signed in today.');
            if (!empty($row['signout_at'])) respond('error', 'Employee already signed out today.');

            $up = $db->prepare("UPDATE employee_attendance_logs SET signout_at = :so, updated_at = :ua WHERE attendance_id = :aid AND cid = :cid");
            $up->bindValue(':so', appNowBusinessDateTime(), SQLITE3_TEXT);
            $up->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
            $up->bindValue(':aid', intval($row['attendance_id']), SQLITE3_INTEGER);
            $up->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $up->execute();
            respond('success', 'Employee signed out.');
            break;

        case 'runAutoAbsence':
            $targetDate = trim((string)($_POST['date'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) respond('error', 'Date must be YYYY-MM-DD.');

            $roleMap = [];
            $rstmt = $db->prepare("SELECT u.user_id,
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
                                          ), 'User') AS role_name
                                   FROM users u
                                   WHERE u.cid = :cid AND COALESCE(u.is_active, 1) = 1");
            $rstmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $rres = $rstmt->execute();
            while ($rres && ($rr = $rres->fetchArray(SQLITE3_ASSOC))) {
                $roleMap[intval($rr['user_id'] ?? 0)] = strtolower((string)($rr['role_name'] ?? 'user'));
            }

            $inserted = 0;
            foreach ($roleMap as $employeeId => $roleName) {
                if ($employeeId <= 0 || $roleName === 'owner') continue;
                $check = $db->prepare("SELECT 1 FROM employee_attendance_logs WHERE cid = :cid AND user_id = :uid AND attendance_date = :dt LIMIT 1");
                $check->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $check->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
                $check->bindValue(':dt', $targetDate, SQLITE3_TEXT);
                if ($check->execute()->fetchArray(SQLITE3_ASSOC)) continue;

                $meta = ['minutes_late' => 0, 'late_grade' => 'absent', 'fine_amount' => 0.0];
                attendanceUpsertLog($db, $cid, $employeeId, $targetDate, null, null, 'auto_absence', $meta, 'Auto-marked absent');
                $inserted++;
            }
            respond('success', 'Auto-absence completed.', ['data' => ['inserted' => $inserted, 'date' => $targetDate]]);
            break;

        case 'requestCorrection':
            $employeeId = intval($_POST['user_id'] ?? 0);
            $attendanceDate = trim((string)($_POST['attendance_date'] ?? ''));
            $proposedSignin = trim((string)($_POST['proposed_signin_at'] ?? ''));
            $proposedSignout = trim((string)($_POST['proposed_signout_at'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));

            if ($employeeId <= 0) respond('error', 'Employee is required.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDate)) respond('error', 'Attendance date must be YYYY-MM-DD.');
            if ($reason === '') respond('error', 'Correction reason is required.');

            $current = $db->prepare("SELECT signin_at, signout_at FROM employee_attendance_logs WHERE cid = :cid AND user_id = :uid AND attendance_date = :dt LIMIT 1");
            $current->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $current->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $current->bindValue(':dt', $attendanceDate, SQLITE3_TEXT);
            $currentRow = $current->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

            $ins = $db->prepare("INSERT INTO attendance_corrections (
                                    cid, user_id, attendance_date, requested_by, current_signin_at, current_signout_at,
                                    proposed_signin_at, proposed_signout_at, reason, status, created_at, updated_at
                                ) VALUES (
                                    :cid, :uid, :dt, :rb, :csin, :csout, :psin, :psout, :reason, 'pending', :ca, :ua
                                )");
            $ins->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $ins->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $ins->bindValue(':dt', $attendanceDate, SQLITE3_TEXT);
            $ins->bindValue(':rb', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
            $ins->bindValue(':csin', $currentRow['signin_at'] ?? null, empty($currentRow['signin_at']) ? SQLITE3_NULL : SQLITE3_TEXT);
            $ins->bindValue(':csout', $currentRow['signout_at'] ?? null, empty($currentRow['signout_at']) ? SQLITE3_NULL : SQLITE3_TEXT);
            $ins->bindValue(':psin', $proposedSignin === '' ? null : $proposedSignin, $proposedSignin === '' ? SQLITE3_NULL : SQLITE3_TEXT);
            $ins->bindValue(':psout', $proposedSignout === '' ? null : $proposedSignout, $proposedSignout === '' ? SQLITE3_NULL : SQLITE3_TEXT);
            $ins->bindValue(':reason', $reason, SQLITE3_TEXT);
            $ins->bindValue(':ca', appNowBusinessDateTime(), SQLITE3_TEXT);
            $ins->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
            $ins->execute();
            respond('success', 'Correction request submitted.');
            break;

        case 'loadCorrectionRequests':
            $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
            if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) $status = 'pending';
            $statusSql = $status === 'all' ? '' : ' AND c.status = :status';
            $rows = [];
            $stmt = $db->prepare("SELECT c.correction_id,
                                         c.user_id,
                                         u.full_name,
                                         u.email,
                                         c.attendance_date,
                                         c.current_signin_at,
                                         c.current_signout_at,
                                         c.proposed_signin_at,
                                         c.proposed_signout_at,
                                         c.reason,
                                         c.status,
                                         c.review_note,
                                         c.created_at,
                                         c.reviewed_at
                                  FROM attendance_corrections c
                                  JOIN users u ON u.user_id = c.user_id
                                  WHERE c.cid = :cid {$statusSql}
                                  ORDER BY CASE c.status WHEN 'pending' THEN 1 ELSE 2 END, c.created_at DESC
                                  LIMIT 200");
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            if ($status !== 'all') $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $res = $stmt->execute();
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $rows[] = [
                    'correction_id' => intval($row['correction_id'] ?? 0),
                    'user_id' => intval($row['user_id'] ?? 0),
                    'full_name' => (string)($row['full_name'] ?? ''),
                    'email' => (string)($row['email'] ?? ''),
                    'attendance_date' => (string)($row['attendance_date'] ?? ''),
                    'current_signin_at' => (string)($row['current_signin_at'] ?? ''),
                    'current_signout_at' => (string)($row['current_signout_at'] ?? ''),
                    'proposed_signin_at' => (string)($row['proposed_signin_at'] ?? ''),
                    'proposed_signout_at' => (string)($row['proposed_signout_at'] ?? ''),
                    'reason' => (string)($row['reason'] ?? ''),
                    'status' => (string)($row['status'] ?? 'pending'),
                    'review_note' => (string)($row['review_note'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
                ];
            }
            respond('success', 'Corrections loaded', ['data' => $rows]);
            break;

        case 'reviewCorrection':
            $correctionId = intval($_POST['correction_id'] ?? 0);
            $decision = strtolower(trim((string)($_POST['decision'] ?? '')));
            $reviewNote = trim((string)($_POST['review_note'] ?? ''));
            if ($correctionId <= 0) respond('error', 'Correction is required.');
            if (!in_array($decision, ['approve', 'reject'], true)) respond('error', 'Decision must be approve or reject.');

            $stmt = $db->prepare("SELECT * FROM attendance_corrections WHERE correction_id = :id AND cid = :cid LIMIT 1");
            $stmt->bindValue(':id', $correctionId, SQLITE3_INTEGER);
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $corr = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$corr) respond('error', 'Correction request not found.');
            if (strtolower((string)($corr['status'] ?? 'pending')) !== 'pending') respond('error', 'Correction request already reviewed.');

            $newStatus = $decision === 'approve' ? 'approved' : 'rejected';

            if ($decision === 'approve') {
                $employeeId = intval($corr['user_id'] ?? 0);
                $attendanceDate = (string)($corr['attendance_date'] ?? '');
                $proposedSignin = trim((string)($corr['proposed_signin_at'] ?? ''));
                $proposedSignout = trim((string)($corr['proposed_signout_at'] ?? ''));

                if ($proposedSignin === '') {
                    $lateMeta = ['minutes_late' => 0, 'late_grade' => 'absent', 'fine_amount' => 0.0];
                } else {
                    $policy = attendanceGetPolicy($db, $cid);
                    $shift = attendanceGetShift($db, $cid, $employeeId);
                    $lateMeta = attendanceCalculateLateMetaWithShift($proposedSignin, $policy, $shift);
                }

                attendanceUpsertLog(
                    $db,
                    $cid,
                    $employeeId,
                    $attendanceDate,
                    $proposedSignin === '' ? null : $proposedSignin,
                    $proposedSignout === '' ? null : $proposedSignout,
                    'correction_approved',
                    $lateMeta,
                    'Correction approved'
                );
            }

            $up = $db->prepare("UPDATE attendance_corrections
                                SET status = :status,
                                    reviewed_by = :rb,
                                    review_note = :rn,
                                    reviewed_at = :ra,
                                    updated_at = :ua
                                WHERE correction_id = :id AND cid = :cid");
            $up->bindValue(':status', $newStatus, SQLITE3_TEXT);
            $up->bindValue(':rb', $uid > 0 ? $uid : null, $uid > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
            $up->bindValue(':rn', $reviewNote, SQLITE3_TEXT);
            $up->bindValue(':ra', appNowBusinessDateTime(), SQLITE3_TEXT);
            $up->bindValue(':ua', appNowBusinessDateTime(), SQLITE3_TEXT);
            $up->bindValue(':id', $correctionId, SQLITE3_INTEGER);
            $up->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $up->execute();
            respond('success', 'Correction request ' . $newStatus . '.');
            break;

        case 'loadEmployeeOverview':
            $rangeMeta = attendanceBuildRangeFilter((string)($_POST['range'] ?? '30d'));

            $employees = [];
            $usersStmt = $db->prepare("SELECT u.user_id, u.full_name, u.email, u.is_active,
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
                                              COALESCE(sr.shift_start, '') AS shift_start,
                                              COALESCE(sr.shift_end, '') AS shift_end,
                                              COALESCE(sr.grace_minutes, 0) AS grace_minutes,
                                              COALESCE(sr.is_active, 0) AS has_shift
                                       FROM users u
                                       LEFT JOIN employee_shift_rules sr ON sr.cid = u.cid AND sr.user_id = u.user_id
                                       WHERE u.cid = :cid
                                       ORDER BY u.full_name ASC");
            $usersStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $usersRes = $usersStmt->execute();
            while ($usersRes && ($u = $usersRes->fetchArray(SQLITE3_ASSOC))) {
                $roleName = strtolower((string)($u['role_name'] ?? 'user'));
                if ($roleName === 'owner') continue;
                $eid = intval($u['user_id'] ?? 0);
                $employees[$eid] = [
                    'user_id' => $eid,
                    'full_name' => (string)($u['full_name'] ?? ''),
                    'email' => (string)($u['email'] ?? ''),
                    'role_name' => (string)($u['role_name'] ?? 'User'),
                    'is_active' => intval($u['is_active'] ?? 0),
                    'shift_start' => (string)($u['shift_start'] ?? ''),
                    'shift_end' => (string)($u['shift_end'] ?? ''),
                    'grace_minutes' => intval($u['grace_minutes'] ?? 0),
                    'has_shift' => intval($u['has_shift'] ?? 0),
                    'attendance_days' => 0,
                    'on_time_days' => 0,
                    'late_days' => 0,
                    'absent_days' => 0,
                    'signout_days' => 0,
                    'total_late_minutes' => 0,
                    'total_fine' => 0.0,
                    'gpi' => 0.0,
                    'performance_tone' => 'danger',
                    'performance_label' => 'Needs attention',
                ];
            }

            $statsStmt = $db->prepare("SELECT l.user_id,
                                              COUNT(l.attendance_id) AS attendance_days,
                                              SUM(CASE WHEN l.late_grade = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                                              SUM(CASE WHEN l.minutes_late <= 0 AND l.late_grade <> 'absent' THEN 1 ELSE 0 END) AS on_time_days,
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
                if (!isset($employees[$employeeId])) continue;
                $attendanceDays = intval($row['attendance_days'] ?? 0);
                $lateDays = intval($row['late_days'] ?? 0);
                $lateMinutes = intval($row['total_late_minutes'] ?? 0);
                $perf = attendanceComputePerformanceMetrics($attendanceDays, $lateDays, $lateMinutes);

                $employees[$employeeId]['attendance_days'] = $attendanceDays;
                $employees[$employeeId]['on_time_days'] = intval($row['on_time_days'] ?? 0);
                $employees[$employeeId]['late_days'] = $lateDays;
                $employees[$employeeId]['absent_days'] = intval($row['absent_days'] ?? 0);
                $employees[$employeeId]['signout_days'] = intval($row['signout_days'] ?? 0);
                $employees[$employeeId]['total_late_minutes'] = $lateMinutes;
                $employees[$employeeId]['total_fine'] = floatval($row['total_fine'] ?? 0);
                $employees[$employeeId]['gpi'] = floatval($perf['gpi']);
                $employees[$employeeId]['performance_tone'] = (string)$perf['performance_tone'];
                $employees[$employeeId]['performance_label'] = (string)$perf['performance_label'];
            }

            $summaryStmt = $db->prepare("SELECT
                                            COUNT(CASE WHEN l.signin_at IS NOT NULL AND l.signin_at <> '' THEN 1 END) AS signed_in_today,
                                            COUNT(CASE WHEN l.minutes_late > 0 THEN 1 END) AS late_today,
                                            COUNT(CASE WHEN l.late_grade = 'absent' THEN 1 END) AS absent_today,
                                            COALESCE(SUM(COALESCE(l.fine_amount, 0)), 0) AS total_fines_today
                                         FROM employee_attendance_logs l
                                         WHERE l.cid = :cid
                                           AND l.attendance_date = :today_date");
            $summaryStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $summaryStmt->bindValue(':today_date', date('Y-m-d'), SQLITE3_TEXT);
            $summary = $summaryStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

            respond('success', 'Attendance overview loaded', [
                'data' => array_values($employees),
                'summary' => [
                    'employees' => count($employees),
                    'signed_in_today' => intval($summary['signed_in_today'] ?? 0),
                    'late_today' => intval($summary['late_today'] ?? 0),
                    'absent_today' => intval($summary['absent_today'] ?? 0),
                    'total_fines_today' => floatval($summary['total_fines_today'] ?? 0),
                ]
            ]);
            break;

        case 'loadEmployeeProfile':
            $employeeId = intval($_POST['user_id'] ?? 0);
            if ($employeeId <= 0) respond('error', 'Employee is required.');
            $rangeMeta = attendanceBuildRangeFilter((string)($_POST['range'] ?? '30d'));

            $profileStmt = $db->prepare("SELECT u.user_id, u.full_name, u.email, u.is_active,
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
                                                COALESCE(sr.shift_start, '') AS shift_start,
                                                COALESCE(sr.shift_end, '') AS shift_end,
                                                COALESCE(sr.grace_minutes, 0) AS grace_minutes,
                                                COALESCE(sr.is_active, 0) AS has_shift
                                         FROM users u
                                         LEFT JOIN employee_shift_rules sr ON sr.cid = u.cid AND sr.user_id = u.user_id
                                         WHERE u.cid = :cid AND u.user_id = :uid
                                         LIMIT 1");
            $profileStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $profileStmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            $profile = $profileStmt->execute()->fetchArray(SQLITE3_ASSOC);
            if (!$profile || strtolower((string)($profile['role_name'] ?? '')) === 'owner') respond('error', 'Employee not found.');

            $activityStmt = $db->prepare("SELECT attendance_date, signin_at, signout_at, signin_method, minutes_late, late_grade, fine_amount, notes
                                          FROM employee_attendance_logs l
                                          WHERE l.cid = :cid AND l.user_id = :uid {$rangeMeta['filter']}
                                          ORDER BY l.attendance_date DESC
                                          LIMIT 180");
            $activityStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $activityStmt->bindValue(':uid', $employeeId, SQLITE3_INTEGER);
            bindParams($activityStmt, $rangeMeta['params']);
            $activityRes = $activityStmt->execute();

            $activities = [];
            $chartLabels = [];
            $chartOnTime = [];
            $chartLate = [];
            $attendanceDays = 0;
            $lateDays = 0;
            $lateMinutes = 0;
            $onTimeDays = 0;
            $absentDays = 0;
            $totalFine = 0.0;
            $signoutDays = 0;

            while ($activityRes && ($row = $activityRes->fetchArray(SQLITE3_ASSOC))) {
                $minutesLate = intval($row['minutes_late'] ?? 0);
                $grade = (string)($row['late_grade'] ?? 'on_time');
                $attendanceDays++;
                $lateMinutes += $minutesLate;
                $totalFine += floatval($row['fine_amount'] ?? 0);
                if ($grade === 'absent') {
                    $absentDays++;
                } elseif ($minutesLate > 0) {
                    $lateDays++;
                } else {
                    $onTimeDays++;
                }
                if (!empty($row['signout_at'])) $signoutDays++;

                $dateLabel = (string)($row['attendance_date'] ?? '');
                $activities[] = [
                    'attendance_date' => $dateLabel,
                    'signin_at' => (string)($row['signin_at'] ?? ''),
                    'signout_at' => (string)($row['signout_at'] ?? ''),
                    'signin_method' => (string)($row['signin_method'] ?? 'account_password'),
                    'minutes_late' => $minutesLate,
                    'late_grade' => $grade,
                    'fine_amount' => floatval($row['fine_amount'] ?? 0),
                    'notes' => (string)($row['notes'] ?? ''),
                    'status_color' => $grade === 'absent' ? 'red' : ($minutesLate <= 0 ? 'green' : ($minutesLate <= 60 ? 'yellow' : 'red')),
                ];
                $chartLabels[] = $dateLabel;
                $chartOnTime[] = ($grade !== 'absent' && $minutesLate <= 0) ? 1 : 0;
                $chartLate[] = ($grade === 'absent' || $minutesLate > 0) ? 1 : 0;
            }

            $perf = attendanceComputePerformanceMetrics($attendanceDays, $lateDays, $lateMinutes);

            respond('success', 'Employee profile loaded', [
                'data' => [
                    'profile' => [
                        'user_id' => intval($profile['user_id'] ?? 0),
                        'full_name' => (string)($profile['full_name'] ?? ''),
                        'email' => (string)($profile['email'] ?? ''),
                        'role_name' => (string)($profile['role_name'] ?? 'User'),
                        'is_active' => intval($profile['is_active'] ?? 0),
                        'signin_auth' => 'Email + Password',
                        'shift_start' => (string)($profile['shift_start'] ?? ''),
                        'shift_end' => (string)($profile['shift_end'] ?? ''),
                        'grace_minutes' => intval($profile['grace_minutes'] ?? 0),
                        'has_shift' => intval($profile['has_shift'] ?? 0),
                    ],
                    'summary' => [
                        'attendance_days' => $attendanceDays,
                        'on_time_days' => $onTimeDays,
                        'late_days' => $lateDays,
                        'absent_days' => $absentDays,
                        'signout_days' => $signoutDays,
                        'total_late_minutes' => $lateMinutes,
                        'total_fine' => round($totalFine, 2),
                        'gpi' => floatval($perf['gpi']),
                        'performance_tone' => (string)$perf['performance_tone'],
                        'performance_label' => (string)$perf['performance_label'],
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
