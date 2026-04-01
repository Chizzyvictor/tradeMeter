class ResetPasswordPage {
  constructor() {
    this.app = new AppCore('');
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

    if (password.length < 6) {
      this.app.showAlert('Password must be at least 6 characters', 'error');
      return;
    }

    if (password !== confirmPassword) {
      this.app.showAlert('Passwords do not match', 'error');
      return;
    }

    const $btn = $('#resetPasswordBtn');
    $btn.prop('disabled', true);

    $.ajax({
      url: 'apiAuthentications.php',
      method: 'POST',
      data: {
        action: 'resetPasswordWithToken',
        token: token,
        password: password
      },
      dataType: 'json',
      success: (response) => {
        if (response.status === 'success') {
          this.app.showAlert(response.text || response.message || 'Password reset successfully!', 'success');
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 2000);
        } else {
          this.app.showAlert(response.text || response.message || 'Failed to reset password', 'error');
          $btn.prop('disabled', false);
        }
      },
      error: () => {
        this.app.showAlert('Error resetting password', 'error');
        $btn.prop('disabled', false);
      }
    });
  }
}

$(document).ready(() => {
  new ResetPasswordPage();
});
