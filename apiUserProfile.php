<?php
require_once __DIR__ . '/helpers.php';

$cid = intval($_SESSION['cid'] ?? 0);
if ($cid <= 0) {
    respond('error', 'Invalid session. Please log in again.');
}

$action = safe_input($_POST['action'] ?? '');

switch ($action) {
    case 'getUserProfile':
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

        $stmt = $db->prepare("SELECT u.user_id,
                                     u.email,
                                     u.full_name,
                                     u.created_at,
                                     ur.role_id,
                                     r.role_name,
                                     c.cName
                              FROM users u
                              LEFT JOIN user_roles ur ON ur.user_id = u.user_id
                              LEFT JOIN roles r ON r.role_id = ur.role_id
                              JOIN company c ON c.cid = u.cid
                              WHERE u.user_id = :uid AND u.cid = :cid
                              LIMIT 1");
        if (!$stmt) {
            respond('error', 'Failed to load user profile');
        }
        $stmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            respond('error', 'User profile not found');
        }

        respond('success', 'User profile loaded', [
            'data' => [
                'user_id' => $row['user_id'],
                'email' => $row['email'],
                'full_name' => $row['full_name'],
                'company' => $row['cName'],
                'role' => $row['role_name'] ?? 'User',
                'created_at' => $row['created_at']
            ]
        ]);
        break;

    case 'changeEmail':
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

        $newEmail = strtolower(safe_input($_POST['newEmail'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($newEmail === '') {
            respond('error', 'Email is required');
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            respond('error', 'Invalid email format');
        }
        if ($password === '') {
            respond('error', 'Password is required to confirm email change');
        }

        // Verify password
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
        if (!$stmt) {
            respond('error', 'Failed to verify password');
        }
        $stmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if (!$res) {
            respond('error', 'Failed to verify password');
        }

        $row = $res->fetchArray(SQLITE3_ASSOC);
        $storedPassword = strval($row['password'] ?? '');

        if ($storedPassword === '' || !password_verify($password, $storedPassword)) {
            respond('error', 'Password is incorrect');
        }

        // Check if email already exists
        $checkStmt = $db->prepare("SELECT 1 FROM users WHERE lower(email) = lower(:email) AND user_id != :uid LIMIT 1");
        $checkStmt->bindValue(':email', $newEmail, SQLITE3_TEXT);
        $checkStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        if ($checkStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'This email is already in use');
        }

        // Update email
        $updateStmt = $db->prepare("UPDATE users
                                   SET email = :email
                                   WHERE user_id = :uid AND cid = :cid");
        if (!$updateStmt) {
            respond('error', 'Failed to update email');
        }
        $updateStmt->bindValue(':email', $newEmail, SQLITE3_TEXT);
        $updateStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $updateStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$updateStmt->execute()) {
            respond('error', 'Failed to update email');
        }

        respond('success', 'Email changed successfully. Please log in with your new email.');
        break;

    case 'changePassword':
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

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

        // Verify current password
        $stmt = $db->prepare("SELECT password FROM users WHERE user_id = :uid AND cid = :cid LIMIT 1");
        if (!$stmt) {
            respond('error', 'Failed to verify current password');
        }
        $stmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if (!$res) {
            respond('error', 'Failed to verify current password');
        }

        $row = $res->fetchArray(SQLITE3_ASSOC);
        $storedPassword = strval($row['password'] ?? '');

        if ($storedPassword === '' || !password_verify($currentPassword, $storedPassword)) {
            respond('error', 'Current password is incorrect');
        }

        // Check new password isn't same as current
        if (password_verify($newPassword, $storedPassword)) {
            respond('error', 'New password must be different from current password');
        }

        // Hash and update password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $uStmt = $db->prepare("UPDATE users
                               SET password = :password
                               WHERE user_id = :uid AND cid = :cid");
        if (!$uStmt) {
            respond('error', 'Failed to prepare password update');
        }
        $uStmt->bindValue(':password', $hash, SQLITE3_TEXT);
        $uStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $uStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

        if (!$uStmt->execute()) {
            respond('error', 'Failed to update password');
        }

        respond('success', 'Password changed successfully');
        break;

    default:
        respond('error', 'Unknown action');
}
