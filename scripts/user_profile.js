class UserProfilePage {
  constructor() {
    const csrf   = $('meta[name="csrf-token"]').attr('content') || '';
    this.app     = new AppCore(csrf);
    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.loadUserProfile();
  }

  bindEvents() {
    $('#emailForm').on('submit', (e) => {
      e.preventDefault();
      this.changeEmail();
    });

    $('#passwordForm').on('submit', (e) => {
      e.preventDefault();
      this.changePassword();
    });
  }

  loadUserProfile() {
    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'getUserProfile',
      silent: true,
      onSuccess: (response) => {
        const user = response.data;
        $('#userFullName').text(user.full_name || 'N/A');
        $('#userCompany').text(user.company || 'N/A');
        $('#userRole').text(user.role || 'User');
        $('#currentEmail').val(user.email || '');

        if (user.created_at) {
          const date = new Date(parseInt(user.created_at, 10) * 1000);
          $('#userCreatedAt').text(date.toLocaleDateString());
        }
      },
      errorMsg: 'Error loading profile'
    });
  }

  changeEmail() {
    const newEmail = $('#newEmail').val().trim();
    const password = $('#emailPassword').val();

    if (!newEmail) {
      this.app.showAlert('Please enter new email', 'error');
      return;
    }

    if (!password) {
      this.app.showAlert('Please enter your password to confirm', 'error');
      return;
    }

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'changeEmail',
      data: { newEmail, password },
      successMsg: 'Email changed successfully. Please log in with your new email.',
      errorMsg: 'Failed to change email',
      onSuccess: () => {
        $('#emailForm')[0].reset();
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 2000);
      }
    });
  }

  changePassword() {
    const current = $('#currentPassword').val();
    const newPwd = $('#newPassword').val();
    const confirm = $('#confirmPassword').val();

    if (!current || !newPwd || !confirm) {
      this.app.showAlert('All password fields are required', 'error');
      return;
    }

    if (newPwd.length < 6) {
      this.app.showAlert('New password must be at least 6 characters', 'error');
      return;
    }

    if (newPwd !== confirm) {
      this.app.showAlert('New passwords do not match', 'error');
      return;
    }

    this.app.ajaxHelper({
      url: 'apiUserProfile.php',
      action: 'changePassword',
      data: {
        currentPassword: current,
        newPassword: newPwd,
        confirmPassword: confirm
      },
      successMsg: 'Password changed successfully',
      errorMsg: 'Failed to change password',
      onSuccess: () => {
        $('#passwordForm')[0].reset();
      }
    });
  }
}

$(document).ready(() => {
  new UserProfilePage();
});
