class SettingsPage {
  constructor() {
    const csrf   = $('meta[name="csrf-token"]').attr('content') || '';
    this.app     = new AppCore(csrf);
    this.AuthApp = new Auth(this.app);
    this.roles = [];
    this.canManageUsers = false;

    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.loadSettings();

    this.app.loadUserPermissions(() => {
      this.canManageUsers = this.app.hasPermission('manage_users');

      if (this.canManageUsers) {
        $('.settings-admin-section').show();
        this.loadRoles(() => this.loadUsers());
        this.loadRememberAudit();
        this.loadActiveSessions();
        this.loadLoginLogs();
      } else {
        $('.settings-admin-section').hide();
      }
    });
  }

  bindEvents() {
    $('#companyProfileForm').on('submit', (e) => {
      e.preventDefault();
      this.updateProfile();
    });

    $('#companyLogo').on('change', (e) => {
      const file = e.target?.files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (evt) => {
        $('#settingsCompanyLogo').attr('src', String(evt.target?.result || ''));
      };
      reader.readAsDataURL(file);
    });

    $('#securityForm').on('submit', (e) => {
      e.preventDefault();
      this.updateSecurity();
    });

    $('#passwordForm').on('submit', (e) => {
      e.preventDefault();
      this.changePassword();
    });

    $('#createUserForm').on('submit', (e) => {
      e.preventDefault();
      this.createUser();
    });

    $('#seedDemoUsersBtn').on('click', () => {
      this.seedDemoUsers();
    });

    $('#refreshRememberAuditBtn').on('click', () => {
      this.loadRememberAudit();
    });

    $('#refreshSessionsBtn').on('click', () => {
      this.loadActiveSessions();
    });

    $('#logoutAllDevicesBtn').on('click', () => {
      this.logoutAllDevices();
    });

    $('#refreshLoginLogsBtn').on('click', () => {
      this.loadLoginLogs();
    });

    $('#loginLogsStatusFilter').on('change', () => {
      this.loadLoginLogs();
    });

    $('#smtpTestEmailForm').on('submit', (e) => {
      e.preventDefault();
      this.sendSmtpTestEmail();
    });

    $(document).on('click', '.save-user-role-btn', (e) => {
      const userId = Number($(e.currentTarget).data('id')) || 0;
      const roleId = Number($(`#roleSelect_${userId}`).val()) || 0;
      if (!userId || !roleId) return;
      this.updateUserRole(userId, roleId);
    });

    $(document).on('click', '.toggle-user-status-btn', (e) => {
      const userId = Number($(e.currentTarget).data('id')) || 0;
      const nextState = Number($(e.currentTarget).data('next')) || 0;
      if (!userId) return;
      this.toggleUserStatus(userId, nextState);
    });

    $(document).on('click', '.revoke-session-btn', (e) => {
      const sessionId = String($(e.currentTarget).data('session') || '').trim();
      if (!sessionId) return;
      this.revokeSession(sessionId);
    });
  }


  loadSettings() {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadSettings',
      data: {},
      onSuccess: (res) => {
        const data = res.data || {};
        this.renderSettings(data);
      }
    });
  }

  renderSettings(data) {
    const cName = data.cName || '-';
    const cEmail = data.cEmail || '-';
    const question = data.question || '';
    const logo = data.cLogo || 'logo.jpg';

    $('#settingsCompanyName').text(cName);
    $('#settingsCompanyEmail').text(cEmail);
    $('#settingsRegDate').text(this.app.formatDateSafe(data.regDate, '-'));
    const logoSrc = this.app.resolveImagePath(logo, 'Images/companyDP', 'Images/companyDP/logo.jpg');
    $('#settingsCompanyLogo').attr('src', logoSrc);

    $('#companyName').val(cName === '-' ? '' : cName);
    $('#companyEmail').val(cEmail === '-' ? '' : cEmail);
    $('#securityQuestion').val(question);
    $('#securityAnswer').val('');
    $('#companyLogo').val('');
  }

  updateProfile() {
    const $btn = $('#saveCompanyProfileBtn');
    $btn.prop('disabled', true);

    const formData = new FormData();
    formData.append('cName', String($('#companyName').val() || '').trim());
    formData.append('cEmail', String($('#companyEmail').val() || '').trim());

    const logoFile = $('#companyLogo')[0]?.files?.[0];
    if (logoFile) {
      formData.append('companyLogo', logoFile);
    }

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'updateProfile',
      data: formData,
      dir: 'companyDP',
      onSuccess: () => {
        this.loadSettings();
        this.AuthApp.loadCompanyLogo();        
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  updateSecurity() {
    const question = String($('#securityQuestion').val() || '').trim();
    const answer = String($('#securityAnswer').val() || '').trim();
    const $btn = $('#saveSecurityBtn');

    if (!question || !answer) {
      this.app.showAlert('Question and answer are required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'updateSecurity',
      data: { question, answer },
      onSuccess: () => {
        $('#securityAnswer').val('');
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  changePassword() {
    const currentPassword = String($('#currentPassword').val() || '');
    const newPassword = String($('#newPassword').val() || '');
    const confirmPassword = String($('#confirmPassword').val() || '');
    const $btn = $('#changePasswordBtn');

    if (!currentPassword || !newPassword || !confirmPassword) {
      this.app.showAlert('All password fields are required', 'error');
      return;
    }

    if (newPassword !== confirmPassword) {
      this.app.showAlert('New password and confirm password do not match', 'error');
      return;
    }

    if (newPassword.length < 6) {
      this.app.showAlert('New password must be at least 6 characters', 'error');
      return;
    }

    if (newPassword === currentPassword) {
      this.app.showAlert('New password must be different from current password', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'changePassword',
      data: { currentPassword, newPassword, confirmPassword },
      onSuccess: () => {
        $('#passwordForm')[0].reset();
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  loadRoles(onSuccess = null) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadRoles',
      data: {},
      onSuccess: (res) => {
        this.roles = Array.isArray(res.data) ? res.data : [];
        this.populateRoleSelect('#newUserRole', this.roles, 0);
        if (typeof onSuccess === 'function') onSuccess(this.roles);
      }
    });
  }

  loadUsers() {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadUsers',
      data: {},
      onSuccess: (res) => {
        this.renderUsers(Array.isArray(res.data) ? res.data : []);
      }
    });
  }

  populateRoleSelect(selector, roles, selectedRoleId = 0) {
    const $select = $(selector);
    if (!$select.length) return;

    $select.empty();
    $select.append('<option value="">Select role</option>');

    (roles || []).forEach((role) => {
      const roleId = Number(role.role_id) || 0;
      const roleName = role.role_name || '';
      if (!roleId || !roleName) return;
      $select.append(`<option value="${roleId}">${roleName}</option>`);
    });

    if (selectedRoleId > 0) {
      $select.val(String(selectedRoleId));
    }
  }

  renderUsers(rows) {
    const $tbody = $('#usersTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted">No users found</td></tr>');
      return;
    }

    const roleOptions = (this.roles || []).map((r) => {
      const roleId = Number(r.role_id) || 0;
      const roleName = r.role_name || '';
      return `<option value="${roleId}">${roleName}</option>`;
    }).join('');

    const html = rows.map((user) => {
      const userId = Number(user.user_id) || 0;
      const active = Number(user.is_active) === 1;
      const nextState = active ? 0 : 1;
      const statusBadge = active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
      const createdAt = this.app.formatDateSafe(user.created_at, '-');

      return `
        <tr>
          <td>${user.full_name || '-'}</td>
          <td>${user.email || '-'}</td>
          <td>
            <select class="form-control form-control-sm" id="roleSelect_${userId}">
              ${roleOptions}
            </select>
          </td>
          <td>${statusBadge}</td>
          <td>${createdAt}</td>
          <td>
            <button type="button" class="btn btn-sm btn-info save-user-role-btn" data-id="${userId}">Save Role</button>
            <button type="button" class="btn btn-sm ${active ? 'btn-warning' : 'btn-success'} toggle-user-status-btn" data-id="${userId}" data-next="${nextState}">
              ${active ? 'Deactivate' : 'Activate'}
            </button>
          </td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);

    rows.forEach((user) => {
      const userId = Number(user.user_id) || 0;
      const roleId = Number(user.role_id) || 0;
      if (userId > 0 && roleId > 0) {
        $(`#roleSelect_${userId}`).val(String(roleId));
      }
    });
  }

  createUser() {
    const full_name = String($('#newUserFullName').val() || '').trim();
    const email = String($('#newUserEmail').val() || '').trim().toLowerCase();
    const password = String($('#newUserPassword').val() || '');
    const role_id = Number($('#newUserRole').val()) || 0;
    const $btn = $('#createUserBtn');

    if (!full_name || !email || !password || !role_id) {
      this.app.showAlert('All user fields are required', 'error');
      return;
    }

    if (password.length < 6) {
      this.app.showAlert('User password must be at least 6 characters', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'createUser',
      data: { full_name, email, password, role_id },
      onSuccess: () => {
        $('#createUserForm')[0].reset();
        this.loadUsers();
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  updateUserRole(userId, roleId) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'updateUserRole',
      data: { user_id: userId, role_id: roleId },
      onSuccess: () => {
        this.loadUsers();
      }
    });
  }

  toggleUserStatus(userId, isActive) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'toggleUserStatus',
      data: { user_id: userId, is_active: isActive },
      onSuccess: () => {
        this.loadUsers();
      }
    });
  }

  seedDemoUsers() {
    const $btn = $('#seedDemoUsersBtn');
    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'seedDemoUsers',
      data: {},
      onSuccess: (res) => {
        const users = Array.isArray(res.data) ? res.data : [];
        if (users.length) {
          const lines = users.map((u) => `${u.email} / ${u.password}`).join('\n');
          alert(`Demo accounts ready:\n${lines}`);
        }
        this.loadUsers();
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  loadRememberAudit() {
    const $btn = $('#refreshRememberAuditBtn');
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadRememberAudit',
      data: {},
      onSuccess: (res) => {
        this.renderRememberAudit(Array.isArray(res.data) ? res.data : []);
      },
      onComplete: () => {
        if ($btn.length) $btn.prop('disabled', false);
      }
    });
  }

  renderRememberAudit(rows) {
    const $tbody = $('#rememberAuditTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="5" class="text-center text-muted">No remember-me audit records yet</td></tr>');
      return;
    }

    const severityRank = {
      auto_login_rejected: 1,
      token_revoked: 2,
      token_issued: 3,
      token_rotated: 3,
      auto_login_success: 4
    };

    const rowClassMap = {
      auto_login_rejected: 'table-danger',
      token_revoked: 'table-warning',
      token_issued: 'table-info',
      token_rotated: 'table-primary',
      auto_login_success: 'table-success'
    };

    const sortedRows = [...rows].sort((left, right) => {
      const leftRank = severityRank[left.event_type] || 99;
      const rightRank = severityRank[right.event_type] || 99;
      if (leftRank !== rightRank) return leftRank - rightRank;
      return (Number(right.created_at) || 0) - (Number(left.created_at) || 0);
    });

    const html = sortedRows.map((row) => {
      const when = this.app.formatDateSafe(row.created_at, '-');
      const eventType = row.event_type || '-';
      const eventClassMap = {
        token_issued: 'badge-info',
        token_rotated: 'badge-primary',
        auto_login_success: 'badge-success',
        auto_login_rejected: 'badge-danger',
        token_revoked: 'badge-warning text-dark'
      };
      const eventBadgeClass = eventClassMap[eventType] || 'badge-secondary';
      const eventLabel = String(eventType).replace(/_/g, ' ');
      const rowClass = rowClassMap[eventType] || '';
      const userLabel = row.full_name && row.email ? `${row.full_name} (${row.email})` : (row.email || row.full_name || '-');
      const ip = row.ip_address || '-';
      let details = '-';

      if (row.details) {
        try {
          const parsed = JSON.parse(row.details);
          details = Object.entries(parsed)
            .map(([key, value]) => `${key}: ${String(value)}`)
            .join(', ');
        } catch (error) {
          details = String(row.details);
        }
      }

      return `
        <tr class="${rowClass}">
          <td>${when}</td>
          <td><span class="badge ${eventBadgeClass}">${eventLabel}</span></td>
          <td>${userLabel}</td>
          <td>${ip}</td>
          <td>${details || '-'}</td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  loadActiveSessions() {
    const $btn = $('#refreshSessionsBtn');
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadActiveSessions',
      data: {},
      onSuccess: (res) => {
        this.renderActiveSessions(Array.isArray(res.data) ? res.data : []);
      },
      onComplete: () => {
        if ($btn.length) $btn.prop('disabled', false);
      }
    });
  }

  renderActiveSessions(rows) {
    const $tbody = $('#activeSessionsTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted">No active sessions found</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const userLabel = row.full_name && row.email ? `${row.full_name} (${row.email})` : (row.email || row.full_name || '-');
      const ip = row.ip_address || '-';
      const device = row.user_agent || '-';
      const lastActivity = this.app.formatDateSafe(row.last_activity, '-');
      const createdAt = this.app.formatDateSafe(row.created_at, '-');
      const currentBadge = row.is_current ? ' <span class="badge badge-success">Current</span>' : '';

      return `
        <tr>
          <td>${userLabel}${currentBadge}</td>
          <td>${ip}</td>
          <td>${device}</td>
          <td>${lastActivity}</td>
          <td>${createdAt}</td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-danger revoke-session-btn" data-session="${row.session_id}">
              Revoke
            </button>
          </td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  revokeSession(sessionId) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'revokeSession',
      data: { session_id: sessionId },
      onSuccess: (res) => {
        if (res.is_current) {
          window.location.href = 'login.php';
          return;
        }
        this.loadActiveSessions();
      }
    });
  }

  logoutAllDevices() {
    this.app.ajaxHelper({
      url: 'apiAuthentications.php',
      action: 'logoutAllDevices',
      data: {},
      onSuccess: () => {
        window.location.href = 'login.php';
      }
    });
  }

  loadLoginLogs() {
    const $btn = $('#refreshLoginLogsBtn');
    const selectedStatus = String($('#loginLogsStatusFilter').val() || 'all').trim();
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadLoginLogs',
      data: { status: selectedStatus },
      onSuccess: (res) => {
        this.renderLoginLogs(Array.isArray(res.data) ? res.data : []);
      },
      onComplete: () => {
        if ($btn.length) $btn.prop('disabled', false);
      }
    });
  }

  renderLoginLogs(rows) {
    const $tbody = $('#loginLogsTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="5" class="text-center text-muted">No login activity found</td></tr>');
      return;
    }

    const statusClassMap = {
      failed: 'badge-danger',
      blocked: 'badge-warning text-dark',
      success: 'badge-success',
      success_auto: 'badge-info',
      failed_auto: 'badge-secondary'
    };

    const html = rows.map((row) => {
      const when = this.app.formatDateSafe(row.login_time, '-');
      const status = String(row.status || '-');
      const statusBadgeClass = statusClassMap[status] || 'badge-secondary';
      const statusLabel = status.replace(/_/g, ' ');
      const userLabel = row.full_name && row.email ? `${row.full_name} (${row.email})` : (row.email || row.full_name || '-');
      const ip = row.ip_address || '-';
      const device = row.user_agent || '-';

      return `
        <tr>
          <td>${when}</td>
          <td><span class="badge ${statusBadgeClass}">${statusLabel}</span></td>
          <td>${userLabel}</td>
          <td>${ip}</td>
          <td>${device}</td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  sendSmtpTestEmail() {
    const $btn = $('#sendSmtpTestEmailBtn');
    const email = String($('#smtpTestEmail').val() || '').trim().toLowerCase();

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiAuthentications.php',
      action: 'sendSmtpTestEmail',
      data: { email },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }
}

$(document).ready(function () {
  new SettingsPage();
});
