class SettingsPage {
  constructor() {
    const csrf   = $('meta[name="csrf-token"]').attr('content') || '';
    this.app     = new AppCore(csrf);
    this.AuthApp = new Auth(this.app);
    this.roles = [];
    this.canManageUsers = false;
    this.canManageBackups = false;
    this.isOwner = false;
    this.backupSupported = false;
    this.backupCapabilityLoaded = false;
    this.backupCapabilityLoading = false;
    this.settingsTabStorageKey = 'settings_active_tab';
    this.settingsSidebarStorageKey = 'settings_sidebar_collapsed';
    this.searchDebounceTimers = {};
    this.lazyLoadedTabs = {
      users: false,
      attendance: false,
      security: false,
      sessions: false,
      loginLogs: false,
      backups: false
    };

    this.usersPager = { page: 1, perPage: 10, totalPages: 1, totalItems: 0, search: '' };
    this.sessionsPager = { page: 1, perPage: 10, totalPages: 1, totalItems: 0, search: '' };
    this.loginLogsPager = { page: 1, perPage: 10, totalPages: 1, totalItems: 0, search: '' };
    this.backupsPager = { page: 1, perPage: 10, totalPages: 1, totalItems: 0, search: '' };

    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.setupTabs();
    this.loadSettings();

    this.app.loadUserPermissions(() => {
      this.canManageUsers = this.app.hasPermission('manage_users');

      if (this.canManageUsers) {
        $('.settings-admin-section').show();

        this.AuthApp.loadCurrentUserContext((user) => {
          const roleName = String(user?.role || '').toLowerCase();
          this.isOwner = roleName === 'owner';
          this.canManageBackups = roleName === 'owner';

          if (this.isOwner) {
            $('.settings-owner-section').show();
            this.loadAttendancePolicy();
          } else {
            $('.settings-owner-section').hide();
          }

          if (this.canManageBackups) {
            $('.settings-backup-section').show();
          } else {
            $('.settings-backup-section').hide();
          }

          this.activateFirstVisibleTab();
          this.loadActiveTabData();
        });
      } else {
        $('.settings-admin-section').hide();
        $('.settings-backup-section').hide();
        this.activateFirstVisibleTab();
        this.loadActiveTabData();
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

    $('#usersSearchInput').on('input', (e) => {
      const value = String($(e.currentTarget).val() || '').trim();
      this.debounceSearch('users', () => {
        this.usersPager.search = value;
        this.usersPager.page = 1;
        this.loadUsers();
      });
    });

    $('#usersPrevPageBtn').on('click', () => {
      this.setUsersPage(this.usersPager.page - 1);
    });

    $('#usersNextPageBtn').on('click', () => {
      this.setUsersPage(this.usersPager.page + 1);
    });

    $('#refreshSessionsBtn').on('click', () => {
      this.loadActiveSessions();
    });

    $('#sessionsSearchInput').on('input', (e) => {
      const value = String($(e.currentTarget).val() || '').trim();
      this.debounceSearch('sessions', () => {
        this.sessionsPager.search = value;
        this.sessionsPager.page = 1;
        this.loadActiveSessions();
      });
    });

    $('#sessionsPrevPageBtn').on('click', () => {
      this.setSessionsPage(this.sessionsPager.page - 1);
    });

    $('#sessionsNextPageBtn').on('click', () => {
      this.setSessionsPage(this.sessionsPager.page + 1);
    });

    $('#logoutAllDevicesBtn').on('click', () => {
      this.logoutAllDevices();
    });

    $('#refreshLoginLogsBtn').on('click', () => {
      this.loadLoginLogs();
    });

    $('#loginLogsSearchInput').on('input', (e) => {
      const value = String($(e.currentTarget).val() || '').trim();
      this.debounceSearch('loginLogs', () => {
        this.loginLogsPager.search = value;
        this.loginLogsPager.page = 1;
        this.loadLoginLogs();
      });
    });

    $('#loginLogsPrevPageBtn').on('click', () => {
      this.setLoginLogsPage(this.loginLogsPager.page - 1);
    });

    $('#loginLogsNextPageBtn').on('click', () => {
      this.setLoginLogsPage(this.loginLogsPager.page + 1);
    });

    $('#createBackupBtn').on('click', () => {
      this.createBackup();
    });

    $('#refreshBackupsBtn').on('click', () => {
      this.loadBackups();
    });

    $('#backupsSearchInput').on('input', (e) => {
      const value = String($(e.currentTarget).val() || '').trim();
      this.debounceSearch('backups', () => {
        this.backupsPager.search = value;
        this.backupsPager.page = 1;
        this.loadBackups();
      });
    });

    $('#backupsPrevPageBtn').on('click', () => {
      this.setBackupsPage(this.backupsPager.page - 1);
    });

    $('#backupsNextPageBtn').on('click', () => {
      this.setBackupsPage(this.backupsPager.page + 1);
    });

    $('#refreshBackupAuditBtn').on('click', () => {
      this.loadBackupAudit();
    });

    $('#restoreEncryptedBackupForm').on('submit', (e) => {
      e.preventDefault();
      this.restoreEncryptedBackup();
    });

    $('#loginLogsStatusFilter').on('change', () => {
      this.loginLogsPager.page = 1;
      this.loadLoginLogs();
    });

    $('#smtpTestEmailForm').on('submit', (e) => {
      e.preventDefault();
      this.sendSmtpTestEmail();
    });

    $('#attendancePolicyForm').on('submit', (e) => {
      e.preventDefault();
      this.saveAttendancePolicy();
    });

    $('#toggleSettingsSidebar').on('click', () => {
      const $sidebar = $('#settingsSidebar');
      if (!$sidebar.length) return;
      $sidebar.toggleClass('collapsed');
      try {
        const isCollapsed = $sidebar.hasClass('collapsed') ? '1' : '0';
        window.localStorage.setItem(this.settingsSidebarStorageKey, isCollapsed);
      } catch (_error) {
        // Ignore storage errors in private mode.
      }
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

    $(document).on('click', '.restore-backup-btn', (e) => {
      const fileName = String($(e.currentTarget).data('file') || '').trim();
      if (!fileName) return;
      this.restoreBackup(fileName);
    });

    $(document).on('click', '.download-backup-btn', (e) => {
      const fileName = String($(e.currentTarget).data('file') || '').trim();
      if (!fileName) return;
      this.downloadBackup(fileName);
    });

    $(document).on('click', '.download-encrypted-backup-btn', (e) => {
      const fileName = String($(e.currentTarget).data('file') || '').trim();
      if (!fileName) return;
      this.downloadEncryptedBackup(fileName);
    });
  }

  debounceSearch(key, callback, delay = 300) {
    if (this.searchDebounceTimers[key]) {
      clearTimeout(this.searchDebounceTimers[key]);
    }

    this.searchDebounceTimers[key] = setTimeout(() => {
      callback();
    }, delay);
  }

  normalizePager(rawPager, fallbackPerPage = 10) {
    const perPage = Math.max(1, Number(rawPager?.per_page) || fallbackPerPage);
    const totalItems = Math.max(0, Number(rawPager?.total_items) || 0);
    const totalPages = Math.max(1, Number(rawPager?.total_pages) || Math.ceil(totalItems / perPage) || 1);
    const page = Math.min(totalPages, Math.max(1, Number(rawPager?.page) || 1));
    return { page, perPage, totalItems, totalPages };
  }

  renderPager(infoSelector, prevSelector, nextSelector, pager) {
    const infoText = `Page ${pager.page} of ${pager.totalPages} (${pager.totalItems} record${pager.totalItems === 1 ? '' : 's'})`;
    $(infoSelector).text(infoText);
    $(prevSelector).prop('disabled', pager.page <= 1);
    $(nextSelector).prop('disabled', pager.page >= pager.totalPages);
  }

  setUsersPage(page) {
    const nextPage = Math.max(1, Math.min(this.usersPager.totalPages, Number(page) || 1));
    if (nextPage === this.usersPager.page) return;
    this.usersPager.page = nextPage;
    this.loadUsers();
  }

  setSessionsPage(page) {
    const nextPage = Math.max(1, Math.min(this.sessionsPager.totalPages, Number(page) || 1));
    if (nextPage === this.sessionsPager.page) return;
    this.sessionsPager.page = nextPage;
    this.loadActiveSessions();
  }

  setLoginLogsPage(page) {
    const nextPage = Math.max(1, Math.min(this.loginLogsPager.totalPages, Number(page) || 1));
    if (nextPage === this.loginLogsPager.page) return;
    this.loginLogsPager.page = nextPage;
    this.loadLoginLogs();
  }

  setBackupsPage(page) {
    const nextPage = Math.max(1, Math.min(this.backupsPager.totalPages, Number(page) || 1));
    if (nextPage === this.backupsPager.page) return;
    this.backupsPager.page = nextPage;
    this.loadBackups();
  }

  setupTabs() {
    const $tabs = $('#settingsTabs a[data-toggle="tab"]');
    if (!$tabs.length) return;

    try {
      const collapsed = String(window.localStorage.getItem(this.settingsSidebarStorageKey) || '') === '1';
      if (collapsed) {
        $('#settingsSidebar').addClass('collapsed');
      }
    } catch (_error) {
      // Ignore storage errors in private mode.
    }

    let requestedTab = window.location.hash || '';
    if (!requestedTab || !$(requestedTab).length) {
      requestedTab = String(window.localStorage.getItem(this.settingsTabStorageKey) || '').trim();
    }

    if (requestedTab && $(requestedTab).length) {
      const $requestedLink = $(`#settingsTabs a[href="${requestedTab}"]`);
      if ($requestedLink.length && $requestedLink.is(':visible')) {
        $requestedLink.tab('show');
      }
    }

    $tabs.on('shown.bs.tab', (event) => {
      const target = String($(event.target).attr('href') || '');
      if (!target || !$(target).length) return;
      try {
        window.localStorage.setItem(this.settingsTabStorageKey, target);
      } catch (_error) {
        // Ignore storage errors in private mode.
      }
      if (window.location.hash !== target) {
        history.replaceState(null, '', target);
      }
      this.handleLazyTabLoad(target);
    });
  }

  activateFirstVisibleTab() {
    const $activeLink = $('#settingsTabs a[data-toggle="tab"].active');
    if ($activeLink.length && $activeLink.is(':visible')) return;

    const $firstVisible = $('#settingsTabs a[data-toggle="tab"]:visible').first();
    if ($firstVisible.length) {
      $firstVisible.tab('show');
    }
  }

  loadActiveTabData() {
    const activeTarget = String($('#settingsTabs a[data-toggle="tab"].active').attr('href') || '').trim();
    if (!activeTarget) return;
    this.handleLazyTabLoad(activeTarget);
  }

  handleLazyTabLoad(target) {
    switch (target) {
      case '#settings-users':
        if (!this.canManageUsers || this.lazyLoadedTabs.users) return;
        this.lazyLoadedTabs.users = true;
        this.loadRoles(() => this.loadUsers());
        break;
      case '#settings-attendance':
        if (!this.isOwner || this.lazyLoadedTabs.attendance) return;
        this.lazyLoadedTabs.attendance = true;
        this.loadAttendancePolicy();
        break;
      case '#settings-security':
        if (!this.canManageUsers || this.lazyLoadedTabs.security) return;
        this.lazyLoadedTabs.security = true;
        this.loadRememberAudit();
        break;
      case '#settings-sessions':
        if (!this.canManageUsers || this.lazyLoadedTabs.sessions) return;
        this.lazyLoadedTabs.sessions = true;
        this.loadActiveSessions();
        break;
      case '#settings-login-logs':
        if (!this.canManageUsers || this.lazyLoadedTabs.loginLogs) return;
        this.lazyLoadedTabs.loginLogs = true;
        this.loadLoginLogs();
        break;
      case '#settings-backups':
        if (!this.canManageBackups || this.lazyLoadedTabs.backups) return;
        this.lazyLoadedTabs.backups = true;
        this.loadBackupsTabData();
        break;
      default:
        break;
    }
  }

  loadBackupsTabData() {
    if (this.backupCapabilityLoaded) {
      if (this.backupSupported) {
        this.loadBackups();
        this.loadBackupAudit();
      }
      return;
    }

    if (this.backupCapabilityLoading) return;
    this.backupCapabilityLoading = true;

    this.loadBackupCapability((capability) => {
      this.backupCapabilityLoading = false;
      this.backupCapabilityLoaded = true;
      this.backupSupported = Boolean(capability?.supported);

      if (this.backupSupported) {
        this.loadBackups();
        this.loadBackupAudit();
      } else {
        this.disableBackupActions();
        this.renderBackupPolicy({
          supported: false,
          retention_days: capability?.retention_days,
          scheduler_hint: capability?.scheduler_hint,
          message: capability?.message,
          last_auto_backup_created_at: 0
        });
        this.renderBackups([]);
        this.renderBackupAudit([]);
      }
    });
  }

  setButtonLoading($btn, isLoading, loadingText = 'Saving...') {
    if (!$btn || !$btn.length) return;

    const cachedHtml = String($btn.data('default-html') || '');
    if (!cachedHtml) {
      $btn.data('default-html', $btn.html());
    }

    if (isLoading) {
      $btn.prop('disabled', true);
      $btn.html(`<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>${loadingText}`);
      return;
    }

    const originalHtml = String($btn.data('default-html') || '');
    if (originalHtml) {
      $btn.html(originalHtml);
    }
    $btn.prop('disabled', false);
  }

  getPasswordStrengthError(password) {
    if (password.length < 8) {
      return 'User password must be at least 8 characters';
    }

    if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
      return 'Password must include uppercase, lowercase and number';
    }

    return '';
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
    const logo = data.cLogo || 'logo.jpg';

    $('#settingsCompanyName').text(cName);
    $('#settingsCompanyEmail').text(cEmail);
    $('#settingsRegDate').text(this.app.formatDateSafe(data.regDate, '-'));
    const logoSrc = this.app.resolveImagePath(logo, 'Images/companyDP', 'Images/companyDP/logo.jpg');
    $('#settingsCompanyLogo').attr('src', logoSrc);

    $('#companyName').val(cName === '-' ? '' : cName);
    $('#companyEmail').val(cEmail === '-' ? '' : cEmail);
    $('#companyLogo').val('');
  }

  updateProfile() {
    const $btn = $('#saveCompanyProfileBtn');
    this.setButtonLoading($btn, true, 'Saving...');

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
        this.setButtonLoading($btn, false);
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
    const pager = this.usersPager;
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadUsers',
      data: {
        page: pager.page,
        per_page: pager.perPage,
        search: pager.search
      },
      onSuccess: (res) => {
        const normalizedPager = this.normalizePager(res.pagination, pager.perPage);
        this.usersPager.page = normalizedPager.page;
        this.usersPager.perPage = normalizedPager.perPage;
        this.usersPager.totalPages = normalizedPager.totalPages;
        this.usersPager.totalItems = normalizedPager.totalItems;
        this.renderUsers(Array.isArray(res.data) ? res.data : []);
        this.renderPager('#usersPageInfo', '#usersPrevPageBtn', '#usersNextPageBtn', this.usersPager);
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

    const passwordError = this.getPasswordStrengthError(password);
    if (passwordError) {
      this.app.showAlert(passwordError, 'error');
      return;
    }

    this.setButtonLoading($btn, true, 'Creating...');

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'createUser',
      data: { full_name, email, password, role_id },
      onSuccess: () => {
        $('#createUserForm')[0].reset();
        this.usersPager.page = 1;
        this.loadUsers();
      },
      onComplete: () => {
        this.setButtonLoading($btn, false);
      }
    });
  }

  updateUserRole(userId, roleId) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'updateUserRole',
      data: { user_id: userId, role_id: roleId },
      onSuccess: () => {
        this.usersPager.page = 1;
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
    const pager = this.sessionsPager;
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadActiveSessions',
      data: {
        page: pager.page,
        per_page: pager.perPage,
        search: pager.search
      },
      onSuccess: (res) => {
        const normalizedPager = this.normalizePager(res.pagination, pager.perPage);
        this.sessionsPager.page = normalizedPager.page;
        this.sessionsPager.perPage = normalizedPager.perPage;
        this.sessionsPager.totalPages = normalizedPager.totalPages;
        this.sessionsPager.totalItems = normalizedPager.totalItems;
        this.renderActiveSessions(Array.isArray(res.data) ? res.data : []);
        this.renderPager('#sessionsPageInfo', '#sessionsPrevPageBtn', '#sessionsNextPageBtn', this.sessionsPager);
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
      const currentBadge = row.is_current ? ' <span class="badge badge-success">Current Device</span>' : '';

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
        this.sessionsPager.page = 1;
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
    const pager = this.loginLogsPager;
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadLoginLogs',
      data: {
        status: selectedStatus,
        page: pager.page,
        per_page: pager.perPage,
        search: pager.search
      },
      onSuccess: (res) => {
        const normalizedPager = this.normalizePager(res.pagination, pager.perPage);
        this.loginLogsPager.page = normalizedPager.page;
        this.loginLogsPager.perPage = normalizedPager.perPage;
        this.loginLogsPager.totalPages = normalizedPager.totalPages;
        this.loginLogsPager.totalItems = normalizedPager.totalItems;
        this.renderLoginLogs(Array.isArray(res.data) ? res.data : []);
        this.renderPager('#loginLogsPageInfo', '#loginLogsPrevPageBtn', '#loginLogsNextPageBtn', this.loginLogsPager);
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

    this.setButtonLoading($btn, true, 'Sending...');

    this.app.ajaxHelper({
      url: 'apiAuthentications.php',
      action: 'sendSmtpTestEmail',
      data: { email },
      onComplete: () => {
        this.setButtonLoading($btn, false);
      }
    });
  }

  loadAttendancePolicy() {
    if (!this.isOwner) return;

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'loadAttendancePolicy',
      data: {},
      silent: true,
      onSuccess: (res) => {
        const data = res.data || {};
        $('#attendanceResumptionTime').val(String(data.resumption_time || '09:00'));
        $('#attendanceFine0To15').val(String(data.fine_0_15 ?? 200));
        $('#attendanceFine15To60').val(String(data.fine_15_60 ?? 500));
        $('#attendanceFine60Plus').val(String(data.fine_60_plus ?? 1000));
      }
    });
  }

  saveAttendancePolicy() {
    if (!this.isOwner) return;

    const resumption_time = String($('#attendanceResumptionTime').val() || '').trim();
    const fine_0_15 = Number($('#attendanceFine0To15').val() || 0);
    const fine_15_60 = Number($('#attendanceFine15To60').val() || 0);
    const fine_60_plus = Number($('#attendanceFine60Plus').val() || 0);
    const $btn = $('#saveAttendancePolicyBtn');

    if (!resumption_time) {
      this.app.showAlert('Resumption time is required', 'error');
      return;
    }

    if (fine_0_15 < 0 || fine_15_60 < 0 || fine_60_plus < 0) {
      this.app.showAlert('Fine values cannot be negative', 'error');
      return;
    }

    this.setButtonLoading($btn, true, 'Saving...');

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'saveAttendancePolicy',
      data: {
        resumption_time,
        fine_0_15,
        fine_15_60,
        fine_60_plus
      },
      onSuccess: () => {
        this.loadAttendancePolicy();
      },
      onComplete: () => {
        this.setButtonLoading($btn, false);
      }
    });
  }

  formatFileSize(sizeBytes) {
    const size = Number(sizeBytes) || 0;
    if (size <= 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let index = 0;

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }

    return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
  }

  loadBackups() {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const $btn = $('#refreshBackupsBtn');
    const pager = this.backupsPager;
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadBackups',
      data: {
        page: pager.page,
        per_page: pager.perPage,
        search: pager.search
      },
      onSuccess: (res) => {
        const normalizedPager = this.normalizePager(res.pagination, pager.perPage);
        this.backupsPager.page = normalizedPager.page;
        this.backupsPager.perPage = normalizedPager.perPage;
        this.backupsPager.totalPages = normalizedPager.totalPages;
        this.backupsPager.totalItems = normalizedPager.totalItems;
        this.renderBackupPolicy(res);
        this.renderBackups(Array.isArray(res.data) ? res.data : []);
        this.renderPager('#backupsPageInfo', '#backupsPrevPageBtn', '#backupsNextPageBtn', this.backupsPager);
      },
      onComplete: () => {
        if ($btn.length) $btn.prop('disabled', false);
      }
    });
  }

  renderBackupPolicy(res) {
    const $note = $('#backupPolicyNote');
    const $scheduler = $('#backupSchedulerNote');
    if (!$note.length) return;

    const retentionDays = Number(res.retention_days) || 14;
    const lastAuto = this.app.formatDateSafe(res.last_auto_backup_created_at || 0, '-');
    if (res.supported === false) {
      const msg = String(res.message || 'Backup operations are not available in this deployment.');
      $note.text(msg);
    } else {
      $note.text(`Auto backups run via scheduler. Retention: ${retentionDays} day(s). Last auto backup: ${lastAuto}.`);
    }

    if ($scheduler.length) {
      const schedulerHint = String(res.scheduler_hint || 'php tasks/run_backup_scheduler.php').trim();
      $scheduler.text(`Scheduler command: ${schedulerHint}`);
    }
  }

  renderBackups(rows) {
    const $tbody = $('#backupsTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="4" class="text-center text-muted">No backups available</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const when = this.app.formatDateSafe(row.created_at, '-');
      const fileName = String(row.filename || '');
      const size = this.formatFileSize(row.size || 0);
      const isAuto = Boolean(row.is_auto);
      const fileLabel = isAuto
        ? `${fileName} <span class="badge badge-info ml-1">Auto</span>`
        : fileName;

      return `
        <tr>
          <td>${when}</td>
          <td>${fileLabel || '-'}</td>
          <td>${size}</td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-secondary mr-1 download-backup-btn" data-file="${fileName}">
              Download
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary mr-1 download-encrypted-backup-btn" data-file="${fileName}">
              Download Encrypted
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger restore-backup-btn" data-file="${fileName}">
              Restore
            </button>
          </td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  createBackup() {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const $btn = $('#createBackupBtn');
    this.setButtonLoading($btn, true, 'Creating...');

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'createBackup',
      data: {},
      onSuccess: () => {
        this.loadBackups();
        this.loadBackupAudit();
      },
      onComplete: () => {
        this.setButtonLoading($btn, false);
      }
    });
  }

  restoreBackup(fileName) {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const confirmed = window.confirm(`Restore backup ${fileName}? This will permanently overwrite the current database.`);
    if (!confirmed) return;

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'restoreBackup',
      data: { filename: fileName },
      onSuccess: () => {
        this.backupsPager.page = 1;
        this.loadBackups();
        this.loadBackupAudit();
        this.loadSettings();
      }
    });
  }

  downloadBackup(fileName) {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'apiSettings.php';
    form.target = '_blank';
    form.style.display = 'none';

    const fields = {
      action: 'downloadBackup',
      csrf_token: this.app.CSRF_TOKEN,
      filename: fileName
    };

    Object.keys(fields).forEach((key) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = String(fields[key] || '');
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();

    setTimeout(() => this.loadBackupAudit(), 500);
  }

  downloadEncryptedBackup(fileName) {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const passphrase = window.prompt('Enter passphrase for encrypted backup (minimum 8 characters):', '');
    if (passphrase === null) return;

    const normalizedPassphrase = String(passphrase || '').trim();
    if (normalizedPassphrase.length < 8) {
      this.app.showAlert('Passphrase must be at least 8 characters', 'error');
      return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'apiSettings.php';
    form.target = '_blank';
    form.style.display = 'none';

    const fields = {
      action: 'downloadEncryptedBackup',
      csrf_token: this.app.CSRF_TOKEN,
      filename: fileName,
      passphrase: normalizedPassphrase
    };

    Object.keys(fields).forEach((key) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = String(fields[key] || '');
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();

    setTimeout(() => this.loadBackupAudit(), 500);
  }

  restoreEncryptedBackup() {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const file = $('#encryptedBackupFile')[0]?.files?.[0] || null;
    const passphrase = String($('#encryptedBackupPassphrase').val() || '').trim();
    const $btn = $('#restoreEncryptedBackupBtn');

    if (!file) {
      this.app.showAlert('Select an encrypted backup file', 'error');
      return;
    }

    if (passphrase.length < 8) {
      this.app.showAlert('Passphrase must be at least 8 characters', 'error');
      return;
    }

    const confirmed = window.confirm('Restore encrypted backup now? This will permanently overwrite the current database.');
    if (!confirmed) return;

    this.setButtonLoading($btn, true, 'Restoring...');

    const formData = new FormData();
    formData.append('encryptedBackupFile', file);
    formData.append('passphrase', passphrase);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'restoreEncryptedBackup',
      data: formData,
      onSuccess: () => {
        $('#restoreEncryptedBackupForm')[0].reset();
        this.backupsPager.page = 1;
        this.loadBackups();
        this.loadBackupAudit();
        this.loadSettings();
      },
      onComplete: () => {
        this.setButtonLoading($btn, false);
      }
    });
  }

  loadBackupAudit() {
    if (!this.canManageUsers || !this.canManageBackups || !this.backupSupported) return;

    const $btn = $('#refreshBackupAuditBtn');
    if ($btn.length) $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'loadBackupAudit',
      data: {},
      onSuccess: (res) => {
        this.renderBackupAudit(Array.isArray(res.data) ? res.data : []);
      },
      onComplete: () => {
        if ($btn.length) $btn.prop('disabled', false);
      }
    });
  }

  renderBackupAudit(rows) {
    const $tbody = $('#backupAuditTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="5" class="text-center text-muted">No backup audit entries yet</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const when = this.app.formatDateSafe(row.created_at, '-');
      const eventLabel = String(row.event_type || '-').replace(/_/g, ' ');
      const actor = row.full_name && row.email && row.full_name !== '-'
        ? `${row.full_name} (${row.email})`
        : 'System';
      const fileName = String(row.filename || '-');
      const ip = String(row.ip_address || '-');

      return `
        <tr>
          <td>${when}</td>
          <td>${eventLabel}</td>
          <td>${fileName}</td>
          <td>${actor}</td>
          <td>${ip}</td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  loadBackupCapability(onSuccess = null) {
    this.app.ajaxHelper({
      url: 'apiSettings.php',
      action: 'getBackupCapability',
      data: {},
      silent: true,
      onSuccess: (res) => {
        const data = res.data || {};
        if (typeof onSuccess === 'function') onSuccess(data);
      }
    });
  }

  disableBackupActions() {
    $('#createBackupBtn, #refreshBackupsBtn, #refreshBackupAuditBtn, #restoreEncryptedBackupBtn').prop('disabled', true);
  }
}

$(document).ready(function () {
  new SettingsPage();
});
