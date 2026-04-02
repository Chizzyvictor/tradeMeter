<?php
require_once __DIR__ . '/helpers.php';

function ensureUserMessagesTable(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS user_messages (
        message_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        sender_user_id INTEGER NOT NULL,
        recipient_user_id INTEGER NOT NULL,
        category TEXT NOT NULL DEFAULT 'info',
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        is_read INTEGER NOT NULL DEFAULT 0 CHECK (is_read IN (0,1)),
        read_at INTEGER,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_messages_cid ON user_messages(cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_messages_recipient ON user_messages(recipient_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_messages_sender ON user_messages(sender_user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_messages_created_at ON user_messages(created_at)");
}

function currentProfileUserId(): int {
    return intval($_SESSION['user_id'] ?? 0);
}

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

    case 'loadMessagingData':
        $uid = currentProfileUserId();
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

        ensureUserMessagesTable($db);

        $users = [];
        $usersStmt = $db->prepare("SELECT u.user_id,
                                         u.full_name,
                                         u.email,
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
                                  WHERE u.cid = :cid
                                    AND u.user_id != :uid
                                    AND COALESCE(u.is_active, 1) = 1
                                  ORDER BY u.full_name ASC, u.email ASC");
        if (!$usersStmt) {
            respond('error', 'Failed to load messaging users');
        }
        $usersStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $usersStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $usersRes = $usersStmt->execute();
        while ($row = $usersRes->fetchArray(SQLITE3_ASSOC)) {
            $users[] = [
                'user_id' => intval($row['user_id'] ?? 0),
                'full_name' => $row['full_name'] ?? '',
                'email' => $row['email'] ?? '',
                'role_name' => $row['role_name'] ?? 'User'
            ];
        }

        $inbox = [];
        $inboxStmt = $db->prepare("SELECT m.message_id,
                                         m.category,
                                         m.subject,
                                         m.body,
                                         m.is_read,
                                         m.read_at,
                                         m.created_at,
                                         sender.full_name AS sender_name,
                                         sender.email AS sender_email
                                  FROM user_messages m
                                  JOIN users sender ON sender.user_id = m.sender_user_id
                                  WHERE m.cid = :cid
                                    AND m.recipient_user_id = :uid
                                  ORDER BY m.created_at DESC, m.message_id DESC
                                  LIMIT 50");
        if (!$inboxStmt) {
            respond('error', 'Failed to load inbox');
        }
        $inboxStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $inboxStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $inboxRes = $inboxStmt->execute();
        while ($row = $inboxRes->fetchArray(SQLITE3_ASSOC)) {
            $inbox[] = [
                'message_id' => intval($row['message_id'] ?? 0),
                'category' => $row['category'] ?? 'info',
                'subject' => $row['subject'] ?? '',
                'body' => $row['body'] ?? '',
                'is_read' => intval($row['is_read'] ?? 0),
                'read_at' => intval($row['read_at'] ?? 0),
                'created_at' => intval($row['created_at'] ?? 0),
                'sender_name' => $row['sender_name'] ?? 'Unknown user',
                'sender_email' => $row['sender_email'] ?? ''
            ];
        }

        $sent = [];
        $sentStmt = $db->prepare("SELECT m.message_id,
                                        m.category,
                                        m.subject,
                                        m.body,
                                        m.is_read,
                                        m.read_at,
                                        m.created_at,
                                        recipient.full_name AS recipient_name,
                                        recipient.email AS recipient_email
                                 FROM user_messages m
                                 JOIN users recipient ON recipient.user_id = m.recipient_user_id
                                 WHERE m.cid = :cid
                                   AND m.sender_user_id = :uid
                                 ORDER BY m.created_at DESC, m.message_id DESC
                                 LIMIT 50");
        if (!$sentStmt) {
            respond('error', 'Failed to load sent messages');
        }
        $sentStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $sentStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $sentRes = $sentStmt->execute();
        while ($row = $sentRes->fetchArray(SQLITE3_ASSOC)) {
            $sent[] = [
                'message_id' => intval($row['message_id'] ?? 0),
                'category' => $row['category'] ?? 'info',
                'subject' => $row['subject'] ?? '',
                'body' => $row['body'] ?? '',
                'is_read' => intval($row['is_read'] ?? 0),
                'read_at' => intval($row['read_at'] ?? 0),
                'created_at' => intval($row['created_at'] ?? 0),
                'recipient_name' => $row['recipient_name'] ?? 'Unknown user',
                'recipient_email' => $row['recipient_email'] ?? ''
            ];
        }

        respond('success', 'Messaging data loaded', [
            'data' => [
                'users' => $users,
                'inbox' => $inbox,
                'sent' => $sent,
                'unread_count' => count(array_filter($inbox, static function ($item) {
                    return intval($item['is_read'] ?? 0) === 0;
                }))
            ]
        ]);
        break;

    case 'sendMessage':
        $uid = currentProfileUserId();
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

        ensureUserMessagesTable($db);

        $recipientUserId = intval($_POST['recipient_user_id'] ?? 0);
        $category = strtolower(safe_input($_POST['category'] ?? 'info'));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));

        if ($recipientUserId <= 0) {
            respond('error', 'Select a colleague to receive the message');
        }

        if ($recipientUserId === $uid) {
            respond('error', 'You cannot send a message to yourself');
        }

        if (!in_array($category, ['info', 'report', 'suggestion'], true)) {
            respond('error', 'Invalid message category');
        }

        if ($subject === '') {
            respond('error', 'Subject is required');
        }

        if (strlen($subject) > 150) {
            respond('error', 'Subject must not exceed 150 characters');
        }

        if ($body === '') {
            respond('error', 'Message body is required');
        }

        if (strlen($body) > 5000) {
            respond('error', 'Message body must not exceed 5000 characters');
        }

        $recipientStmt = $db->prepare("SELECT user_id
                                       FROM users
                                       WHERE user_id = :recipient_user_id
                                         AND cid = :cid
                                         AND COALESCE(is_active, 1) = 1
                                       LIMIT 1");
        if (!$recipientStmt) {
            respond('error', 'Failed to validate recipient');
        }
        $recipientStmt->bindValue(':recipient_user_id', $recipientUserId, SQLITE3_INTEGER);
        $recipientStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$recipientStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond('error', 'Recipient not found in your company');
        }

        $insertStmt = $db->prepare("INSERT INTO user_messages (
                                        cid, sender_user_id, recipient_user_id, category, subject, body, is_read, created_at
                                    )
                                    VALUES (
                                        :cid, :sender_user_id, :recipient_user_id, :category, :subject, :body, 0, :created_at
                                    )");
        if (!$insertStmt) {
            respond('error', 'Failed to prepare message');
        }
        $insertStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $insertStmt->bindValue(':sender_user_id', $uid, SQLITE3_INTEGER);
        $insertStmt->bindValue(':recipient_user_id', $recipientUserId, SQLITE3_INTEGER);
        $insertStmt->bindValue(':category', $category, SQLITE3_TEXT);
        $insertStmt->bindValue(':subject', $subject, SQLITE3_TEXT);
        $insertStmt->bindValue(':body', $body, SQLITE3_TEXT);
        $insertStmt->bindValue(':created_at', time(), SQLITE3_INTEGER);

        if (!$insertStmt->execute()) {
            respond('error', 'Failed to send message');
        }

        respond('success', 'Message sent successfully');
        break;

    case 'markMessageRead':
        $uid = currentProfileUserId();
        if ($uid <= 0) {
            respond('error', 'Session expired. Please log in again');
        }

        ensureUserMessagesTable($db);

        $messageId = intval($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            respond('error', 'Invalid message');
        }

        $updateStmt = $db->prepare("UPDATE user_messages
                                   SET is_read = 1,
                                       read_at = :read_at
                                   WHERE message_id = :message_id
                                     AND cid = :cid
                                     AND recipient_user_id = :uid
                                     AND is_read = 0");
        if (!$updateStmt) {
            respond('error', 'Failed to update message status');
        }
        $updateStmt->bindValue(':read_at', time(), SQLITE3_INTEGER);
        $updateStmt->bindValue(':message_id', $messageId, SQLITE3_INTEGER);
        $updateStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $updateStmt->bindValue(':uid', $uid, SQLITE3_INTEGER);
        $updateStmt->execute();

        respond('success', 'Message marked as read');
        break;

    default:
        respond('error', 'Unknown action');
}
