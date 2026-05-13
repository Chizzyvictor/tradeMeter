class ResetPasswordPage {
  constructor() {
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    this.app = new AppCore(csrf);
    this.bindEvents();
  }

  bindEvents() {
    $('#resetPasswordForm').on('submit', (e) => {
      e.preventDefault();
      this.handleReset();
    });
  }

  handleReset() {
    const token = $('#resetToken').val().trim();
    const password = $('#resetPassword').val();
    const confirmPassword = $('#resetPasswordConfirm').val();

    if (!token) {
      this.app.showAlert('Invalid reset token', 'error');
      return;
    }

    if (!password) {
      this.app.showAlert('Password is required', 'error');
      return;
    }

    const pwdRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
    if (!pwdRegex.test(password)) {
      this.app.showAlert('Password must be at least 8 characters with uppercase, lowercase, number, and special character', 'error');
      return;
    }

    if (password !== confirmPassword) {
      this.app.showAlert('Passwords do not match', 'error');
      return;
    }

    const $btn = $('#resetPasswordBtn');
    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiAuthentications.php',
      action: 'resetPasswordWithToken',
      data: { token, password },
      successMsg: 'Password reset successfully!',
      errorMsg: 'Failed to reset password',
      onSuccess: () => {
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 2000);
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }
}

$(document).ready(() => {
  new ResetPasswordPage();
});
