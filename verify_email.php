<?php
session_start();
require_once __DIR__ . '/INC/db.php';

function ensureVerificationColumns(SQLite3 $db): void {
    $columns = [];
    $res = $db->query("PRAGMA table_info(users)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = strtolower((string)($row['name'] ?? ''));
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
}

$status = 'error';
$message = 'Invalid or expired verification link.';

$token = trim((string)($_GET['token'] ?? ''));
if ($token !== '') {
    $db = appDbConnect();
    ensureVerificationColumns($db);

    $tokenHash = hash('sha256', $token);
    $now = time();

    $stmt = $db->prepare("SELECT user_id, email_verified_at
                         FROM users
                         WHERE email_verification_token_hash = :token_hash
                           AND email_verification_expires_at >= :now
                         LIMIT 1");
    if ($stmt) {
        $stmt->bindValue(':token_hash', $tokenHash, SQLITE3_TEXT);
        $stmt->bindValue(':now', $now, SQLITE3_INTEGER);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($user) {
            if (!empty($user['email_verified_at'])) {
                $status = 'success';
                $message = 'Your email is already verified. You can log in now.';
            } else {
                $upd = $db->prepare("UPDATE users
                                     SET email_verified_at = :verified_at,
                                         email_verification_token_hash = NULL,
                                         email_verification_expires_at = NULL
                                     WHERE user_id = :uid");
                if ($upd) {
                    $upd->bindValue(':verified_at', $now, SQLITE3_INTEGER);
                    $upd->bindValue(':uid', intval($user['user_id']), SQLITE3_INTEGER);
                    if ($upd->execute()) {
                        $status = 'success';
                        $message = 'Email verification successful. You can now log in.';
                    } else {
                        $message = 'Verification failed. Please try again.';
                    }
                }
            }
        }
    }

    $db->close();
}

include "INC/header.php";
?>

<div class="container py-5" style="max-width: 680px;">
    <div class="card shadow-sm">
        <div class="card-header font-weight-bold <?php echo $status === 'success' ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
            Email Verification
        </div>
        <div class="card-body">
            <p class="mb-4"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="login.php" class="btn btn-primary">Go to Login</a>
        </div>
    </div>
</div>

<?php include "INC/footer.php"; ?>
