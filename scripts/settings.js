class SettingsTemplateApp {
    constructor() {
        this.csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
        this.app = new AppCore(this.csrfToken);
        this.auth = typeof Auth === 'function' ? new Auth(this.app) : null;

        this.roles = [];
        this.tabLoaded = {};
        this.usersSearch = '';
        this.sessionsSearch = '';
        this.logsSearch = '';
        this.backupsSearch = '';
        this.attendanceSearch = '';
        this.tabLoadingState = {};

        this.bindLayoutEvents();
        this.bindActionEvents();
        this.populateCurrentUser();

        const initialTab = String($('.settings-item.active').data('tab') || 'profileTab');
        this.populateTab(initialTab);
    }

    bindLayoutEvents() {
        this.toggleBackButton();

        $(window).on('resize', () => {
            this.toggleBackButton();
            if ($(window).width() >= 992) {
                $('#sidebar').removeClass('show');
            }
        });

        $('#mobileMenuBtn').on('click', () => {
            $('#sidebar').toggleClass('show');
        });

        $(document).on('click', '.back-btn', () => {
            if ($(window).width() < 992) {
                $('#sidebar').removeClass('show');
            }
        });

        $(document).on('click', (event) => {
            if (!$(event.target).closest('#sidebar, #mobileMenuBtn').length && $(window).width() < 992) {
                $('#sidebar').removeClass('show');
            }
        });

        $(document).on('click', '.settings-item', (event) => {
            const $item = $(event.currentTarget);
            const tabId = String($item.data('tab') || '').trim();
            if (!tabId) return;

            $('.settings-item').removeClass('active');
            $item.addClass('active');

            $('.tab-content').removeClass('active');
            $(`#${tabId}`).addClass('active');

            if ($(window).width() < 992) {
                $('#sidebar').removeClass('show');
            }

            this.populateTab(tabId);
        });
    }

    bindActionEvents() {
        $('#companyProfileForm').on('submit', (event) => {
            event.preventDefault();
            this.updateProfile();
        });

        $('#smtpSettingsForm').on('submit', (event) => {
            event.preventDefault();
            this.sendSmtpTestEmail();
        });

        $('#attendancePolicyForm').on('submit', (event) => {
            event.preventDefault();
            this.saveAttendancePolicy();
        });

        $('#runAutoAbsenceSettingsBtn').on('click', () => {
            this.runAutoAbsence();
        });

        $('#attendanceCorrectionStatusSettings').on('change', () => {
            this.loadCorrections();
        });

        $('#attendanceEmployeeSearchSettings').on('input', (e) => {
            this.attendanceSearch = String($(e.currentTarget).val() || '').trim().toLowerCase();
            this.renderEmployees(this.employees);
        });

        $(document).on('click', '.settings-correction-review-btn', (event) => {
            const correctionId = Number($(event.currentTarget).data('id')) || 0;
            const decision = String($(event.currentTarget).data('decision') || '').trim().toLowerCase();
            if (!correctionId || !decision) return;
            this.reviewCorrection(correctionId, decision);
        });

        $('#seedDemoUsersBtn').on('click', () => {
            this.openCreateUserModal();
        });

        $('#createUserForm').on('submit', (event) => {
            event.preventDefault();
            this.createUserFromModal();
        });

        $('#editUserForm').on('submit', (event) => {
            event.preventDefault();
            this.updateUserFromModal();
        });

        $('#refreshSessionsBtn').on('click', () => {
            this.loadSessions();
        });

        $('#refreshLoginLogsBtn').on('click', () => {
            this.loadLoginLogs();
        });

        $('#createBackupBtn').on('click', () => {
            this.createBackup();
        });

        $('#refreshBackupListBtn').on('click', () => {
            this.loadBackups();
        });

        $('#restoreBackupUploadBtn').on('click', () => {
            this.restoreBackupJson();
        });

        $('#restoreEncryptedBackupBtn').on('click', () => {
            this.restoreEncryptedBackup();
        });

        $('#usersSearchInput').on('input', () => {
            this.usersSearch = String($('#usersSearchInput').val() || '').trim();
            this.loadUsers();
        });

        $('#sessionsSearchInput').on('input', () => {
            this.sessionsSearch = String($('#sessionsSearchInput').val() || '').trim();
            this.loadSessions();
        });

        $('#loginLogsSearchInput').on('input', () => {
            this.logsSearch = String($('#loginLogsSearchInput').val() || '').trim();
            this.loadLoginLogs();
        });

        $('#loginLogsStatusFilter').on('change', () => {
            this.loadLoginLogs();
        });

        $('#backupsSearchInput').on('input', () => {
            this.backupsSearch = String($('#backupsSearchInput').val() || '').trim();
            this.loadBackups();
        });

        $(document).on('click', '.save-user-role-btn', (event) => {
            const userId = Number($(event.currentTarget).data('id')) || 0;
            const roleId = Number($(`#roleSelect_${userId}`).val()) || 0;
            if (!userId || !roleId) return;
            this.updateUserRole(userId, roleId);
        });

        $(document).on('click', '.edit-user-btn', (event) => {
            const userId = Number($(event.currentTarget).data('id')) || 0;
            if (!userId) return;
            this.openEditUserModal(userId);
        });

        $(document).on('click', '.toggle-user-status-btn', (event) => {
            const userId = Number($(event.currentTarget).data('id')) || 0;
            const nextState = Number($(event.currentTarget).data('next')) || 0;
            if (!userId) return;
            this.toggleUserStatus(userId, nextState);
        });

        $(document).on('click', '.revoke-session-btn', (event) => {
            const sessionId = String($(event.currentTarget).data('session') || '').trim();
            if (!sessionId) return;
            this.revokeSession(sessionId);
        });

        $(document).on('click', '.attendance-signout-btn', (e) => {
            e.stopPropagation();
            const userId = Number($(e.currentTarget).data('id')) || 0;
            if (!userId) return;
            this.signOutEmployee(userId);
        });

        $(document).on('click', '.attendance-shift-btn', (e) => {
            e.stopPropagation();
            const $btn = $(e.currentTarget);
            this.openShiftModal($btn);
        });

        $(document).on('click', '.backup-download-btn', (event) => {
            const filename = String($(event.currentTarget).data('filename') || '').trim();
            if (!filename) return;
            this.submitBackupDownload('downloadBackup', { filename });
        });

        $(document).on('click', '.backup-download-enc-btn', (event) => {
            const filename = String($(event.currentTarget).data('filename') || '').trim();
            if (!filename) return;

            const passphrase = window.prompt('Enter passphrase for encrypted backup:', '');
            if (!passphrase) return;

            this.submitBackupDownload('downloadEncryptedBackup', {
                filename,
                passphrase: passphrase.trim()
            });
        });
    }

    toggleBackButton() {
        if ($(window).width() < 992) {
            $('.back-btn').show();
            return;
        }
        $('.back-btn').hide();
    }

    setButtonLoading($btn, isLoading, loadingText = 'Loading...') {
        if (!$btn.length) return;
        if (isLoading) {
            if (!$btn.data('default-html')) {
                $btn.data('default-html', $btn.html());
            }
            $btn.prop('disabled', true);
            $btn.html(`<i class="fas fa-spinner fa-spin"></i> ${loadingText}`);
            return;
        }

        $btn.prop('disabled', false);
        if ($btn.data('default-html')) {
            $btn.html(String($btn.data('default-html')));
        }
    }

    applySkeletonStagger($card) {
        if (!$card || !$card.length) return;

        const $animatedNodes = $card.find('.settings-skeleton-line, .settings-skeleton-card, .settings-skeleton-row span');
        if (!$animatedNodes.length) return;

        const stepMs = 70;
        const cycleMs = 560;

        $animatedNodes.each((index, node) => {
            const delay = ((index * stepMs) % cycleMs) / 1000;
            node.style.setProperty('--settings-skeleton-delay', `${delay.toFixed(2)}s`);
        });
    }

    buildTabSkeleton(tabId) {
        switch (tabId) {
            case 'profileTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-grid two">
                        <div class="settings-skeleton-field">
                            <div class="settings-skeleton-line xs"></div>
                            <div class="settings-skeleton-line"></div>
                        </div>
                        <div class="settings-skeleton-field">
                            <div class="settings-skeleton-line xs"></div>
                            <div class="settings-skeleton-line"></div>
                        </div>
                    </div>
                    <div class="settings-skeleton-field">
                        <div class="settings-skeleton-line xs"></div>
                        <div class="settings-skeleton-line"></div>
                    </div>
                    <div class="settings-skeleton-line"></div>
                    <div class="settings-skeleton-line sm action"></div>
                `;
            case 'smtpTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-grid two">
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                    </div>
                    <div class="settings-skeleton-line"></div>
                    <div class="settings-skeleton-line sm action"></div>
                `;
            case 'usersTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-line sm"></div>
                    <div class="settings-skeleton-table">
                        <div class="settings-skeleton-row head">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span><span></span></div>
                    </div>
                `;
            case 'sessionsTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-line sm"></div>
                    <div class="settings-skeleton-table">
                        <div class="settings-skeleton-row head">
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                    </div>
                `;
            case 'logsTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-grid two">
                        <div class="settings-skeleton-line"></div>
                        <div class="settings-skeleton-line"></div>
                    </div>
                    <div class="settings-skeleton-table">
                        <div class="settings-skeleton-row head">
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span></div>
                    </div>
                `;
            case 'backupTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-grid three">
                        <div class="settings-skeleton-card"></div>
                        <div class="settings-skeleton-card"></div>
                        <div class="settings-skeleton-card"></div>
                    </div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-table">
                        <div class="settings-skeleton-row head">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="settings-skeleton-row"><span></span><span></span><span></span><span></span><span></span></div>
                    </div>
                `;
            case 'attendanceTab':
                return `
                    <div class="settings-skeleton-line lg"></div>
                    <div class="settings-skeleton-line md"></div>
                    <div class="settings-skeleton-grid two">
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                        <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                    </div>
                    <div class="settings-skeleton-field"><div class="settings-skeleton-line xs"></div><div class="settings-skeleton-line"></div></div>
                    <div class="settings-skeleton-line sm action"></div>
                `;
            default:
                return `
                    <div class="settings-skeleton-line"></div>
                    <div class="settings-skeleton-line"></div>
                    <div class="settings-skeleton-line"></div>
                `;
        }
    }

    setTabLoading(tabId, isLoading) {
        const $tab = $(`#${tabId}`);
        if (!$tab.length) return;

        const $card = $tab.find('.content-card').first();
        if (!$card.length) return;

        const $existingLoader = $card.find('.settings-tab-loader');

        if (isLoading) {
            this.tabLoadingState[tabId] = true;
            if ($existingLoader.length) return;

            const skeleton = this.buildTabSkeleton(tabId);
            const loaderHtml = `
                <div class="settings-tab-loader" aria-hidden="true">
                    <div class="settings-skeleton-wrap">
                        ${skeleton}
                    </div>
                </div>
            `;
            $card.addClass('is-loading');
            $card.append(loaderHtml);
            this.applySkeletonStagger($card);
            return;
        }

        this.tabLoadingState[tabId] = false;
        $card.removeClass('is-loading');
        $existingLoader.remove();
    }

    populateCurrentUser() {
        if (!this.auth || typeof this.auth.loadCurrentUserContext !== 'function') return;

        this.auth.loadCurrentUserContext((user) => {
            const fullName = String(user?.full_name || user?.fullName || user?.name || '').trim();
            const role = String(user?.role || user?.role_name || 'User').trim();

            if (fullName) {
                $('#settingsSidebarName').text(fullName);
                const initials = fullName
                    .split(/\s+/)
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part.charAt(0).toUpperCase())
                    .join('');
                if (initials) {
                    $('#settingsSidebarAvatar').contents().first()[0].textContent = `${initials} `;
                }
            }

            $('#settingsSidebarRole').text(role || 'User');
        });
    }

    populateTab(tabId) {
        this.setTabLoading(tabId, true);

        // Required switch-based tab population flow.
        switch (tabId) {
            case 'profileTab':
                this.loadProfile();
                break;
            case 'smtpTab':
                this.loadSmtpTab();
                break;
            case 'usersTab':
                this.loadUsersTab();
                break;
            case 'attendanceTab':
                this.loadAttendancePolicy();
                break;
            case 'attendanceAdminTab':
                this.loadOverview();
                this.loadCorrections();
                break;
            case 'sessionsTab':
                this.loadSessions();
                break;
            case 'logsTab':
                this.loadLoginLogs();
                break;
            case 'backupTab':
                this.loadBackupTab();
                break;
            default:
                break;
        }

        this.tabLoaded[tabId] = true;
    }

    loadProfile() {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadSettings',
            data: {},
            silent: true,
            onSuccess: (res) => {
                const data = res.data || {};
                $('#companyName').val(String(data.cName || ''));
                $('#companyEmail').val(String(data.cEmail || ''));

                const logoName = String(data.cLogo || 'logo.jpg');
                const logoPath = this.app.resolveImagePath(logoName, 'Images/companyDP', 'Images/companyDP/logo.jpg');
                const initials = String(data.cName || 'TM')
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((part) => part.charAt(0).toUpperCase())
                    .join('') || 'TM';

                $('#settingsSidebarAvatar')
                    .css('background-image', `url('${logoPath}')`)
                    .css('background-size', 'cover')
                    .css('background-position', 'center')
                    .css('color', 'transparent');

                $('#settingsSidebarName').text(String(data.cName || $('#settingsSidebarName').text() || '-'));
                $('#settingsSidebarAvatar').contents().first()[0].textContent = `${initials} `;
            },
            onComplete: () => {
                this.setTabLoading('profileTab', false);
            }
        });
    }

    updateProfile() {
        const $btn = $('#saveCompanyProfileBtn');
        this.setButtonLoading($btn, true, 'Saving...');

        const formData = new FormData();
        formData.append('cName', String($('#companyName').val() || '').trim());
        formData.append('cEmail', String($('#companyEmail').val() || '').trim());

        const file = $('#companyLogo')[0]?.files?.[0];
        if (file) {
            formData.append('companyLogo', file);
        }

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'updateProfile',
            data: formData,
            dir: 'companyDP',
            onSuccess: () => {
                this.loadProfile();
                if (this.auth && typeof this.auth.loadCompanyLogo === 'function') {
                    this.auth.loadCompanyLogo();
                }
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    loadSmtpTab() {
        $('#smtpSettingsInfo').text('SMTP settings are managed by server configuration in this build. You can still send a test email below.');
        this.setTabLoading('smtpTab', false);
    }

    sendSmtpTestEmail() {
        const $btn = $('#sendSmtpTestEmailBtn');
        const email = String($('#smtpTestEmail').val() || '').trim();
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

    loadUsersTab() {
        this.loadRoles(() => this.loadUsers());
    }

    loadRoles(onDone = null) {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadRoles',
            data: {},
            silent: true,
            onSuccess: (res) => {
                this.roles = Array.isArray(res.data) ? res.data : [];
                if (typeof onDone === 'function') {
                    onDone();
                }
            },
            onError: () => {
                this.setTabLoading('usersTab', false);
            }
        });
    }

    renderRoleOptions(selector, selectedRoleId = 0) {
        const $roleSelect = $(selector);
        if (!$roleSelect.length) return;

        const optionsHtml = ['<option value="">Select role</option>']
            .concat((this.roles || []).map((role) => {
                const roleId = Number(role.role_id) || 0;
                const roleName = String(role.role_name || '').trim();
                if (roleId <= 0 || !roleName) return '';
                return `<option value="${roleId}">${roleName}</option>`;
            }).filter(Boolean))
            .join('');

        $roleSelect.html(optionsHtml);
        if (Number(selectedRoleId) > 0) {
            $roleSelect.val(String(selectedRoleId));
        }
    }

    renderCreateUserRoleOptions() {
        this.renderRoleOptions('#createUserRole');
    }

    renderEditUserRoleOptions(selectedRoleId = 0) {
        this.renderRoleOptions('#editUserRole', selectedRoleId);
    }

    openCreateUserModal() {
        const showModal = () => {
            $('#createUserForm')[0]?.reset();
            this.renderCreateUserRoleOptions();
            $('#createUserModal').modal('show');
            setTimeout(() => $('#createUserFullName').trigger('focus'), 150);
        };

        if (!Array.isArray(this.roles) || !this.roles.length) {
            this.loadRoles(() => showModal());
            return;
        }

        showModal();
    }

    openEditUserModal(userId) {
        const showModal = () => {
            const $row = $(`#usersTableBody button.edit-user-btn[data-id="${userId}"]`).closest('tr');
            if (!$row.length) {
                this.app.showAlert('User row not found', 'error');
                return;
            }

            const fullName = String($row.find('td[data-label="Name"]').text() || '').trim();
            const email = String($row.find('td[data-label="Email"]').text() || '').trim();
            const roleId = Number($row.find(`#roleSelect_${userId}`).val() || 0);

            $('#editUserId').val(String(userId));
            $('#editUserFullName').val(fullName);
            $('#editUserEmail').val(email);
            $('#editUserPassword').val('');
            this.renderEditUserRoleOptions(roleId);

            $('#editUserModal').modal('show');
            setTimeout(() => $('#editUserFullName').trigger('focus'), 150);
        };

        if (!Array.isArray(this.roles) || !this.roles.length) {
            this.loadRoles(() => showModal());
            return;
        }

        showModal();
    }

    createUserFromModal() {
        const $btn = $('#createUserSubmitBtn');
        const fullName = String($('#createUserFullName').val() || '').trim();
        const email = String($('#createUserEmail').val() || '').trim();
        const password = String($('#createUserPassword').val() || '');
        const roleId = Number($('#createUserRole').val() || 0);

        if (!fullName || !email || !password || roleId <= 0) {
            this.app.showAlert('Please complete all user fields', 'error');
            return;
        }

        this.setButtonLoading($btn, true, 'Creating...');

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'createUser',
            data: {
                full_name: fullName,
                email,
                password,
                role_id: roleId
            },
            onSuccess: () => {
                $('#createUserForm')[0]?.reset();
                $('#createUserModal').modal('hide');
                this.loadUsers();
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    updateUserFromModal() {
        const $btn = $('#editUserSubmitBtn');
        const userId = Number($('#editUserId').val() || 0);
        const fullName = String($('#editUserFullName').val() || '').trim();
        const email = String($('#editUserEmail').val() || '').trim();
        const password = String($('#editUserPassword').val() || '');
        const roleId = Number($('#editUserRole').val() || 0);

        if (!userId || !fullName || !email || roleId <= 0) {
            this.app.showAlert('Please complete all required user fields', 'error');
            return;
        }

        this.setButtonLoading($btn, true, 'Updating...');

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'updateUserDetails',
            data: {
                user_id: userId,
                full_name: fullName,
                email,
                password,
                role_id: roleId
            },
            onSuccess: () => {
                $('#editUserForm')[0]?.reset();
                $('#editUserModal').modal('hide');
                this.loadUsers();
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    loadUsers() {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadUsers',
            data: {
                page: 1,
                per_page: 50,
                search: this.usersSearch
            },
            silent: true,
            onSuccess: (res) => {
                this.renderUsers(Array.isArray(res.data) ? res.data : []);
            },
            onComplete: () => {
                this.setTabLoading('usersTab', false);
            }
        });
    }

    renderUsers(users) {
        const $tbody = $('#usersTableBody');
        if (!$tbody.length) return;

        if (!users.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="5" class="text-muted">No users found.</td></tr>');
            return;
        }

        const roleOptions = (this.roles || []).map((role) => {
            const roleId = Number(role.role_id) || 0;
            const roleName = String(role.role_name || '').trim();
            return `<option value="${roleId}">${roleName}</option>`;
        }).join('');

        const rowsHtml = users.map((user) => {
            const userId = Number(user.user_id) || 0;
            const roleId = Number(user.role_id) || 0;
            const isActive = Number(user.is_active) === 1;
            const statusBadge = isActive
                ? '<span class="badge badge-success">Active</span>'
                : '<span class="badge badge-secondary">Inactive</span>';

            return `
                <tr>
                    <td data-label="Name">${user.full_name || '-'}</td>
                    <td data-label="Email">${user.email || '-'}</td>
                    <td data-label="Role">
                        <select class="form-control form-control-sm" id="roleSelect_${userId}">
                            ${roleOptions}
                        </select>
                    </td>
                    <td data-label="Status">${statusBadge}</td>
                    <td data-label="Actions">
                        <button type="button" class="edit-btn edit-user-btn" data-id="${userId}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="edit-btn save-user-role-btn" data-id="${userId}"><i class="fas fa-save"></i> Save Role</button>
                        <button type="button" class="delete-btn toggle-user-status-btn" data-id="${userId}" data-next="${isActive ? 0 : 1}">
                            <i class="fas ${isActive ? 'fa-user-slash' : 'fa-user-check'}"></i> ${isActive ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        $tbody.html(rowsHtml);

        users.forEach((user) => {
            const userId = Number(user.user_id) || 0;
            const roleId = Number(user.role_id) || 0;
            if (userId > 0 && roleId > 0) {
                $(`#roleSelect_${userId}`).val(String(roleId));
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

    loadAttendancePolicy() {
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
            },
            onComplete: () => {
                this.setTabLoading('attendanceTab', false);
            }
        });
    }

    saveAttendancePolicy() {
        const $btn = $('#saveAttendancePolicyBtn');
        this.setButtonLoading($btn, true, 'Saving...');

        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'saveAttendancePolicy',
            data: {
                resumption_time: String($('#attendanceResumptionTime').val() || '').trim(),
                fine_0_15: Number($('#attendanceFine0To15').val() || 0),
                fine_15_60: Number($('#attendanceFine15To60').val() || 0),
                fine_60_plus: Number($('#attendanceFine60Plus').val() || 0)
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    loadCorrections() {
        const status = String($('#attendanceCorrectionStatusSettings').val() || 'pending');
        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'loadCorrectionRequests',
            data: { status },
            silent: true,
            onSuccess: (res) => {
                this.renderCorrections(Array.isArray(res.data) ? res.data : []);
            },
            onComplete: () => {
                this.setTabLoading('attendanceAdminTab', false);
            }
        });
    }

    loadOverview() {
        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'loadEmployeeOverview',
            data: { range: '30d' }, // Default range for settings overview
            silent: true,
            onSuccess: (res) => {
                this.employees = Array.isArray(res.data) ? res.data : [];
                this.renderEmployees(this.employees);
            }
        });
    }

    renderEmployees(rows) {
        const $tbody = $('#attendanceEmployeesTableSettings tbody');
        if (!$tbody.length) return;

        const filteredRows = (rows || []).filter((row) => {
            if (!this.attendanceSearch) return true;
            const haystack = `${row.full_name || ''} ${row.email || ''} ${row.role_name || ''} ${row.performance_label || ''}`.toLowerCase();
            return haystack.includes(this.attendanceSearch);
        });

        $('#attendanceSearchSummarySettings').text(
            filteredRows.length === (rows || []).length
                ? `Showing all ${filteredRows.length} employees`
                : `Showing ${filteredRows.length} of ${(rows || []).length} employees`
        );

        if (!filteredRows.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="8" class="text-center text-muted py-5">No employees found matching your search</td></tr>');
            return;
        }

        const html = filteredRows.map((row) => {
            const gpi = Number(row.gpi || 0);
            const tone = String(row.performance_tone || 'danger');
            const badgeClass = tone === 'success' ? 'badge-success' : (tone === 'warning' ? 'badge-warning text-dark' : 'badge-danger');
            const shiftText = Number(row.has_shift || 0) === 1
                ? `<span class="text-dark font-weight-bold">${row.shift_start || '-'} - ${row.shift_end || '-'}</span><br><small class="text-muted">+${Number(row.grace_minutes || 0)}m grace</small>`
                : '<span class="text-muted">Global Policy</span>';

            return `
                <tr class="attendance-employee-row" data-id="${row.user_id}">
                    <td class="py-3" data-label="Employee Details">
            <div class="d-flex align-items-center">
               <div class="avatar-circle mr-3 bg-light d-flex align-items-center justify-content-center rounded-circle" style="width:40px; height:40px; border: 1px solid #e2e8f0;">
                  <i class="fas fa-user text-secondary"></i>
               </div>
               <div>
                  <div class="font-weight-bold text-dark">${AppCore.escapeHtml(row.full_name || '-')}</div>
                  <div class="small text-muted">${AppCore.escapeHtml(row.email || '-')}</div>
               </div>
            </div>
          </td>
                    <td data-label="Designation"><span class="badge badge-light border text-uppercase" style="font-size:0.7rem;">${AppCore.escapeHtml(row.role_name || '-')}</span></td>
                    <td data-label="Attendance"><span class="h6 mb-0 font-weight-bold">${Number(row.attendance_days || 0)}</span> <small class="text-muted">days</small></td>
                    <td data-label="Status Mix">
            <div class="d-flex">
              <span class="badge badge-success mr-1" title="On Time">${Number(row.on_time_days || 0)}</span>
              <span class="badge badge-warning text-dark" title="Late">${Number(row.late_days || 0)}</span>
            </div>
          </td>
                    <td data-label="Fines"><span class="text-danger font-weight-bold">N${this.app.formatNumber(row.total_fine || 0)}</span></td>
                    <td data-label="GPI Score">
            <div class="d-flex flex-column">
               <span class="font-weight-bold h6 mb-1">${this.app.formatNumber(gpi)}</span>
               <span class="badge ${badgeClass} badge-performance text-center" style="width: fit-content;">${AppCore.escapeHtml(row.performance_label || 'Needs attention')}</span>
            </div>
          </td>
                    <td data-label="Shift Details">${shiftText}</td>
                    <td class="text-right" data-label="Quick Actions">
            <div class="btn-group shadow-sm bg-white">
              <button class="btn btn-sm btn-light border attendance-signout-btn" data-id="${row.user_id}" title="Force Sign-Out"><i class="fas fa-sign-out-alt"></i></button>
              <button class="btn btn-sm btn-light border attendance-shift-btn" data-id="${row.user_id}" data-shift-start="${row.shift_start || '09:00'}" data-shift-end="${row.shift_end || '17:00'}" data-grace="${Number(row.grace_minutes || 0)}" data-active="${Number(row.has_shift || 0)}" title="Manage Shift"><i class="fas fa-user-cog"></i></button>
            </div>
          </td>
        </tr>
      `;
        }).join('');

        $tbody.html(html);
    }

    openShiftModal($btn) {
        // Need to ensure modals are available. settings.php includes INC/modals.php
        $('#attendanceShiftUserId').val(String(Number($btn.data('id')) || 0));
        $('#attendanceShiftStart').val(String($btn.data('shiftStart') || '09:00'));
        $('#attendanceShiftEnd').val(String($btn.data('shiftEnd') || '17:00'));
        $('#attendanceShiftGrace').val(String(Number($btn.data('grace')) || 0));
        $('#attendanceShiftActive').val(String(Number($btn.data('active')) === 1 ? 1 : 0));
        $('#attendanceShiftModal').modal('show');
    }

    signOutEmployee(userId) {
        if (!confirm('Are you sure you want to force sign-out this employee?')) return;

        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'signOutEmployee',
            data: { user_id: userId },
            onSuccess: () => {
                this.loadOverview();
            }
        });
    }

    renderCorrections(rows) {
        const $tbody = $('#attendanceCorrectionsTableSettings tbody');
        if (!$tbody.length) return;

        if (!rows.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="6" class="text-center text-muted py-4">No correction requests found</td></tr>');
            return;
        }

        const html = rows.map((row) => {
            const status = String(row.status || 'pending');
            const badgeClass = status === 'approved' ? 'badge-success' : (status === 'rejected' ? 'badge-danger' : 'badge-warning text-dark');
            const proposedText = `In: ${row.proposed_signin_at || '-'} <br> Out: ${row.proposed_signout_at || '-'}`;
            const reviewButtons = status === 'pending'
                ? `<div class="btn-group">
                    <button class="btn btn-sm btn-success settings-correction-review-btn" data-id="${row.correction_id}" data-decision="approve"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-danger settings-correction-review-btn" data-id="${row.correction_id}" data-decision="reject"><i class="fas fa-times"></i></button>
                   </div>`
                : '-';

            return `
                <tr>
                    <td class="pl-3 font-weight-bold" data-label="Employee">${AppCore.escapeHtml(row.full_name || '-')}</td>
                    <td data-label="Date">${AppCore.escapeHtml(row.attendance_date || '-')}</td>
                    <td class="small" data-label="Proposed Changes">${proposedText}</td>
                    <td data-label="Reason"><small class="text-muted">${AppCore.escapeHtml(row.reason || '-')}</small></td>
                    <td data-label="Status"><span class="badge ${badgeClass} text-uppercase">${AppCore.escapeHtml(status)}</span></td>
                    <td class="text-right pr-3" data-label="Action">${reviewButtons}</td>
                </tr>
            `;
        }).join('');

        $tbody.html(html);
    }

    reviewCorrection(correctionId, decision) {
        const review_note = window.prompt(`Optional ${decision} note:`, '') || '';
        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'reviewCorrection',
            data: { correction_id: correctionId, decision, review_note },
            onSuccess: () => {
                this.loadCorrections();
            }
        });
    }

    runAutoAbsence() {
        const date = window.prompt('Enter date for auto-absence (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
        if (!date) return;

        const $btn = $('#runAutoAbsenceSettingsBtn');
        this.setButtonLoading($btn, true, 'Processing...');

        this.app.ajaxHelper({
            url: 'apiEmployeeAttendance.php',
            action: 'runAutoAbsence',
            data: { date },
            onSuccess: (res) => {
                const inserted = Number(res?.data?.inserted || 0);
                this.app.showAlert(`Auto-absence complete: ${inserted} records created.`, 'success');
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    loadSessions() {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadActiveSessions',
            data: {
                page: 1,
                per_page: 50,
                search: this.sessionsSearch
            },
            silent: true,
            onSuccess: (res) => {
                this.renderSessions(Array.isArray(res.data) ? res.data : []);
            },
            onComplete: () => {
                this.setTabLoading('sessionsTab', false);
            }
        });
    }

    renderSessions(rows) {
        const $tbody = $('#sessionsTableBody');
        if (!$tbody.length) return;

        if (!rows.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="4" class="text-muted">No active sessions found.</td></tr>');
            return;
        }

        const html = rows.map((row) => {
            const device = String(row.user_agent || '-');
            const ip = String(row.ip_address || '-');
            const when = this.app.formatDateSafe(row.last_activity, '-');
            const current = row.is_current ? ' <span class="badge badge-success">Current Device</span>' : '';
            return `
                <tr>
                    <td data-label="Device">${device}${current}</td>
                    <td data-label="IP Address">${ip}</td>
                    <td data-label="Last Active">${when}</td>
                    <td data-label="Actions">
                        <button type="button" class="logout-btn revoke-session-btn" data-session="${row.session_id}">
                            <i class="fas fa-sign-out-alt"></i> Revoke
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
                this.loadSessions();
            }
        });
    }

    loadLoginLogs() {
        const status = String($('#loginLogsStatusFilter').val() || 'all').trim();
        const $tbody = $('#loginLogsTableBody');
        if ($tbody.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="4" class="text-muted">Loading login logs...</td></tr>');
        }

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadLoginLogs',
            data: {
                status,
                page: 1,
                per_page: 50,
                search: this.logsSearch
            },
            silent: true,
            onSuccess: (res) => {
                const rows = Array.isArray(res.data)
                    ? res.data
                    : (Array.isArray(res.rows) ? res.rows : []);
                this.renderLoginLogs(rows);
            },
            onError: () => {
                if ($tbody.length) {
                    $tbody.html('<tr class="table-empty-row"><td colspan="4" class="text-danger">Failed to load login logs.</td></tr>');
                }
            },
            onComplete: () => {
                this.setTabLoading('logsTab', false);
            }
        });
    }

    renderLoginLogs(rows) {
        const $tbody = $('#loginLogsTableBody');
        if (!$tbody.length) return;

        if (!rows.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="4" class="text-muted">No login logs found.</td></tr>');
            return;
        }

        const badgeMap = {
            success: 'badge-success',
            failed: 'badge-danger',
            blocked: 'badge-warning text-dark',
            success_auto: 'badge-info',
            failed_auto: 'badge-secondary'
        };

        const html = rows.map((row) => {
            const userLabel = row.full_name && row.email ? `${row.full_name} (${row.email})` : (row.email || row.full_name || '-');
            const ip = String(row.ip_address || '-');
            const when = this.app.formatDateSafe(row.login_time, '-');
            const status = String(row.status || '-');
            const statusClass = badgeMap[status] || 'badge-secondary';
            const statusLabel = status.replace(/_/g, ' ');
            return `
                <tr>
                    <td data-label="User">${userLabel}</td>
                    <td data-label="IP Address">${ip}</td>
                    <td data-label="Timestamp">${when}</td>
                    <td data-label="Status"><span class="badge ${statusClass}">${statusLabel}</span></td>
                </tr>
            `;
        }).join('');

        $tbody.html(html);
    }

    loadBackupTab() {
        this.loadBackupCapability();
        this.loadBackups();
    }

    loadBackupCapability() {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'getBackupCapability',
            data: {},
            silent: true,
            onSuccess: (res) => {
                const data = res.data || {};
                const supported = Boolean(data.supported);
                if (supported) {
                    $('#backupCapabilityText').text(`Backups enabled. Retention: ${data.retention_days || '-'} days.`);
                } else {
                    $('#backupCapabilityText').text(String(data.message || 'Backups are not available for your role or deployment.'));
                }
                $('#createBackupBtn').prop('disabled', !supported);
            }
        });
    }

    loadBackups() {
        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'loadBackups',
            data: {
                page: 1,
                per_page: 50,
                search: this.backupsSearch
            },
            silent: true,
            onSuccess: (res) => {
                this.renderBackups(Array.isArray(res.data) ? res.data : []);
            },
            onError: () => {
                $('#backupListBody').html('<tr class="table-empty-row"><td colspan="5" class="backup-empty">Failed to load backups.</td></tr>');
                $('#backupListMeta').text('0 files');
            },
            onComplete: () => {
                this.setTabLoading('backupTab', false);
            }
        });
    }

    renderBackups(rows) {
        const $tbody = $('#backupListBody');
        if (!$tbody.length) return;

        if (!rows.length) {
            $tbody.html('<tr class="table-empty-row"><td colspan="5" class="backup-empty">No backups available.</td></tr>');
            $('#backupListMeta').text('0 files');
            return;
        }

        const html = rows.map((row) => {
            const filename = String(row.filename || '-');
            const created = this.app.formatDateSafe(row.created_at, '-');
            const size = this.formatFileSize(Number(row.size || 0));
            const type = row.is_auto ? '<span class="badge badge-info">Auto</span>' : '<span class="badge badge-secondary">Manual</span>';

            return `
                <tr>
                    <td data-label="Filename" class="backup-filename" title="${filename}">${filename}</td>
                    <td data-label="Created">${created}</td>
                    <td data-label="Size">${size}</td>
                    <td data-label="Type">${type}</td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-sm btn-info backup-download-btn" data-filename="${filename}">
                            <i class="fas fa-download"></i> JSON
                        </button>
                        <button type="button" class="btn btn-sm btn-warning backup-download-enc-btn" data-filename="${filename}">
                            <i class="fas fa-lock"></i> Encrypted
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        $tbody.html(html);
        $('#backupListMeta').text(`${rows.length} file${rows.length === 1 ? '' : 's'}`);
    }

    createBackup() {
        const $btn = $('#createBackupBtn');
        this.setButtonLoading($btn, true, 'Creating...');

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'createBackup',
            data: {},
            onSuccess: () => {
                this.loadBackups();
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    restoreBackupJson() {
        const input = $('#restoreBackupFileInput')[0];
        const file = input?.files?.[0] || null;
        if (!file) {
            this.app.showAlert('Please select a JSON backup file', 'error');
            return;
        }

        if (!window.confirm('Restore this JSON backup? This will overwrite current company data.')) {
            return;
        }

        const $btn = $('#restoreBackupUploadBtn');
        this.setButtonLoading($btn, true, 'Restoring...');

        const formData = new FormData();
        formData.append('backupFile', file);

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'restoreBackup',
            data: formData,
            onSuccess: () => {
                $('#restoreBackupFileInput').val('');
                this.loadBackups();
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    restoreEncryptedBackup() {
        const file = $('#restoreEncryptedBackupFileInput')[0]?.files?.[0] || null;
        const passphrase = String($('#restoreEncryptedPassphrase').val() || '').trim();

        if (!file) {
            this.app.showAlert('Please select an encrypted backup file', 'error');
            return;
        }
        if (passphrase.length < 8) {
            this.app.showAlert('Passphrase must be at least 8 characters', 'error');
            return;
        }

        if (!window.confirm('Restore this encrypted backup? This will overwrite current company data.')) {
            return;
        }

        const $btn = $('#restoreEncryptedBackupBtn');
        this.setButtonLoading($btn, true, 'Restoring...');

        const formData = new FormData();
        formData.append('encryptedBackupFile', file);
        formData.append('passphrase', passphrase);

        this.app.ajaxHelper({
            url: 'apiSettings.php',
            action: 'restoreEncryptedBackup',
            data: formData,
            onSuccess: () => {
                $('#restoreEncryptedBackupFileInput').val('');
                $('#restoreEncryptedPassphrase').val('');
                this.loadBackups();
            },
            onComplete: () => {
                this.setButtonLoading($btn, false);
            }
        });
    }

    submitBackupDownload(action, fields = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'apiSettings.php';
        form.style.display = 'none';

        const payload = {
            action,
            csrf_token: this.csrfToken,
            ...fields
        };

        Object.keys(payload).forEach((key) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(payload[key] ?? '');
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        form.remove();
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
}

$(document).ready(() => {
    new SettingsTemplateApp();
});
