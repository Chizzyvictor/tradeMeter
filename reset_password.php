<?php
session_start();

// Redirect if already logged in
if (!empty($_SESSION['isLogedin'])) {
    header("Location: index.php");
    exit;
}

// Ensure CSRF token exists for the form submission
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "INC/header.php";

$token = isset($_GET['token']) ? trim(strval($_GET['token'])) : '';
?>

<div class="container py-4">

    <!-- RESET PASSWORD -->
    <div class="panel-box" id="resetPwdTab">
        <h4 class="text-center text-info mb-4">Reset Your Password</h4>
        <div class="col-lg-6 m-auto d-block">
            <?php if ($token === ''): ?>
                <div class="alert alert-danger" role="alert">
                    Invalid or missing reset token. 
                    <a href="login.php">Back to login</a>
                </div>
            <?php else: ?>
                <form id="resetPasswordForm">
                    <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="form-group">
                        <label for="resetPassword">New Password</label>
                        <input type="password"
                               id="resetPassword"
                               name="password"
                               placeholder="Enter new password"
                               class="form-control"
                               required>
                        <small class="form-text text-muted">Must be at least 6 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="resetPasswordConfirm">Confirm Password</label>
                        <input type="password"
                               id="resetPasswordConfirm"
                               name="confirmPassword"
                               placeholder="Confirm password"
                               class="form-control"
                               required>
                    </div>

                    <button type="submit"
                            id="resetPasswordBtn"
                            class="btn btn-primary btn-block">
                        Reset Password
                    </button>
                </form>

                <hr>
                <p class="text-center">
                    <a href="login.php">Back to login</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "INC/footer.php"; ?>
<script src="scripts/reset_password.js?v=<?= asset_ver('scripts/reset_password.js') ?>"></script>

</div>
</body>
</html>
