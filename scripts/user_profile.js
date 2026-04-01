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
    const formData = new FormData();
    formData.append('action', 'getUserProfile');
    formData.append('csrf_token', this.app.CSRF_TOKEN);

    $.ajax({
      url: 'apiUserProfile.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        if (response.status === 'success') {
          const user = response.data;
          $('#userFullName').text(user.full_name || 'N/A');
          $('#userCompany').text(user.company || 'N/A');
          $('#userRole').text(user.role || 'User');
          $('#currentEmail').val(user.email || '');
          
          if (user.created_at) {
            const date = new Date(parseInt(user.created_at) * 1000);
            $('#userCreatedAt').text(date.toLocaleDateString());
          }
        } else {
          this.app.showAlert(response.text || response.message || 'Failed to load profile', 'error');
        }
      },
      error: () => {
        this.app.showAlert('Error loading profile', 'error');
      }
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

    const formData = new FormData();
    formData.append('action', 'changeEmail');
    formData.append('csrf_token', this.app.CSRF_TOKEN);
    formData.append('newEmail', newEmail);
    formData.append('password', password);

    $.ajax({
      url: 'apiUserProfile.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        if (response.status === 'success') {
          this.app.showAlert(response.text || response.message || 'Email changed successfully', 'success');
          $('#emailForm')[0].reset();
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 2000);
        } else {
          this.app.showAlert(response.text || response.message || 'Failed to change email', 'error');
        }
      },
      error: () => {
        this.app.showAlert('Error changing email', 'error');
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

    const formData = new FormData();
    formData.append('action', 'changePassword');
    formData.append('csrf_token', this.app.CSRF_TOKEN);
    formData.append('currentPassword', current);
    formData.append('newPassword', newPwd);
    formData.append('confirmPassword', confirm);

    $.ajax({
      url: 'apiUserProfile.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        if (response.status === 'success') {
          this.app.showAlert(response.text || response.message || 'Password changed successfully', 'success');
          $('#passwordForm')[0].reset();
        } else {
          this.app.showAlert(response.text || response.message || 'Failed to change password', 'error');
        }
      },
      error: () => {
        this.app.showAlert('Error changing password', 'error');
      }
    });
  }
}

$(document).ready(() => {
  new UserProfilePage();
});
