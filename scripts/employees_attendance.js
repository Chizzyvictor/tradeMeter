class EmployeeAttendancePage {
  constructor() {
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    this.app = new AppCore(csrf);
    this.AuthApp = new Auth(this.app);
    this.employees = [];
    this.currentEmployeeId = 0;
    this.chart = null;
    this.currentRole = 'user';

    this.bindEvents();
    this.initialize();
  }

  initialize() {
    this.AuthApp.loadCurrentUserContext((user) => {
      this.currentRole = String(user?.role || 'user').toLowerCase();
      if (!['owner', 'manager'].includes(this.currentRole)) {
        this.app.showAlert('Unauthorized', 'error');
        window.location.href = 'index.php';
        return;
      }
      this.loadOverview();
      this.loadEmployeeOptionsForModals();
      this.loadAttendancePolicyMeta();
    });
  }

  bindEvents() {
    $('#attendanceRange').on('change', () => {
      this.loadOverview();
      if (this.currentEmployeeId > 0) {
        this.loadEmployeeProfile(this.currentEmployeeId);
      }
    });

    $('#openAttendanceSignInModalBtn').on('click', () => {
      $('#attendanceSignInModal').modal('show');
    });

    $('#attendanceSignInMethod').on('change', () => {
      const method = String($('#attendanceSignInMethod').val() || 'pin');
      $('#attendanceSignInSecretLabel').text(method === 'biometric' ? 'Biometric Token' : 'PIN');
    });

    $('#attendanceCredentialMethod').on('change', () => {
      const method = String($('#attendanceCredentialMethod').val() || 'pin');
      $('#attendanceCredentialSecretLabel').text(method === 'biometric' ? 'Biometric Token' : 'PIN');
    });

    $('#attendanceSignInForm').on('submit', (e) => {
      e.preventDefault();
      this.signInEmployee();
    });

    $('#attendanceSignOutForm').on('submit', (e) => {
      e.preventDefault();
      this.signOutEmployee();
    });

    $('#attendanceCredentialForm').on('submit', (e) => {
      e.preventDefault();
      this.saveCredential();
    });

    $('#attendanceBackToListBtn').on('click', () => {
      this.switchTab('list');
    });

    $(document).on('click', '.attendance-employee-row', (e) => {
      const userId = Number($(e.currentTarget).data('id')) || 0;
      if (!userId) return;
      this.loadEmployeeProfile(userId);
    });

    $(document).on('click', '.attendance-signout-btn', (e) => {
      e.stopPropagation();
      const userId = Number($(e.currentTarget).data('id')) || 0;
      if (!userId) return;
      $('#attendanceSignOutEmployee').val(String(userId));
      $('#attendanceSignOutModal').modal('show');
    });

    $(document).on('click', '.attendance-credential-btn', (e) => {
      e.stopPropagation();
      if (this.currentRole !== 'owner') {
        this.app.showAlert('Only owner can set credentials', 'error');
        return;
      }
      const userId = Number($(e.currentTarget).data('id')) || 0;
      if (!userId) return;
      $('#attendanceCredentialUserId').val(String(userId));
      $('#attendanceCredentialModal').modal('show');
    });
  }

  loadAttendancePolicyMeta() {
    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'loadAttendancePolicy',
      data: {},
      silent: true,
      onSuccess: (res) => {
        const p = res.data || {};
        const note = `Policy: resume ${p.resumption_time || '09:00'} | 0-15m: N${this.app.formatNumber(p.fine_0_15 || 0)} | 15-60m: N${this.app.formatNumber(p.fine_15_60 || 0)} | 1h+: N${this.app.formatNumber(p.fine_60_plus || 0)}`;
        $('#attendanceEmployeeTitle').attr('title', note);
      }
    });
  }

  loadOverview() {
    const range = String($('#attendanceRange').val() || '30d');
    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'loadEmployeeOverview',
      data: { range },
      onSuccess: (res) => {
        this.employees = Array.isArray(res.data) ? res.data : [];
        this.renderSummary(res.summary || {});
        this.renderEmployees(this.employees);
        this.loadEmployeeOptionsForModals();
      }
    });
  }

  renderSummary(summary) {
    $('#attendanceStatEmployees').text(String(Number(summary.employees) || 0));
    $('#attendanceStatSignedInToday').text(String(Number(summary.signed_in_today) || 0));
    $('#attendanceStatLateToday').text(String(Number(summary.late_today) || 0));
    $('#attendanceStatFinesToday').text(`N${this.app.formatNumber(summary.total_fines_today || 0)}`);
  }

  renderEmployees(rows) {
    const $tbody = $('#attendanceEmployeesTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="9" class="text-center text-muted">No employees found</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const gpi = Number(row.gpi || 0);
      const tone = String(row.performance_tone || 'danger');
      const badgeClass = tone === 'success' ? 'badge-success' : (tone === 'warning' ? 'badge-warning text-dark' : 'badge-danger');
      const onTime = Number(row.on_time_days || 0);
      const late = Number(row.late_days || 0);
      const credentialButton = this.currentRole === 'owner'
        ? `<button class="btn btn-sm btn-outline-info attendance-credential-btn" data-id="${row.user_id}">Credential</button>`
        : '';

      return `
        <tr class="attendance-employee-row" data-id="${row.user_id}" style="cursor:pointer;">
          <td>${row.full_name || '-'}<br><small class="text-muted">${row.email || '-'}</small></td>
          <td>${row.role_name || '-'}</td>
          <td>${Number(row.attendance_days || 0)}</td>
          <td><span class="badge badge-success">${onTime}</span></td>
          <td><span class="badge badge-warning text-dark">${late}</span></td>
          <td>N${this.app.formatNumber(row.total_fine || 0)}</td>
          <td><strong>${this.app.formatNumber(gpi)}</strong></td>
          <td><span class="badge ${badgeClass}">${row.performance_label || 'Needs attention'}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-dark attendance-signout-btn" data-id="${row.user_id}">Sign-Out</button>
            ${credentialButton}
          </td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  loadEmployeeOptionsForModals() {
    const options = ['<option value="">Select employee</option>'];
    this.employees.forEach((e) => {
      options.push(`<option value="${e.user_id}">${e.full_name} (${e.role_name})</option>`);
    });

    $('#attendanceSignInEmployee').html(options.join(''));
    $('#attendanceSignOutEmployee').html(options.join(''));
  }

  signInEmployee() {
    const user_id = Number($('#attendanceSignInEmployee').val()) || 0;
    const method = String($('#attendanceSignInMethod').val() || 'pin');
    const secret = String($('#attendanceSignInSecret').val() || '').trim();
    const notes = String($('#attendanceSignInNotes').val() || '').trim();
    const $btn = $('#attendanceSignInSubmitBtn');

    if (!user_id || !secret) {
      this.app.showAlert('Employee and credential are required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'signInEmployee',
      data: { user_id, method, secret, notes },
      onSuccess: () => {
        $('#attendanceSignInForm')[0].reset();
        AppCore.safeHideModal('#attendanceSignInModal');
        this.loadOverview();
        if (this.currentEmployeeId === user_id) {
          this.loadEmployeeProfile(user_id);
        }
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  signOutEmployee() {
    const user_id = Number($('#attendanceSignOutEmployee').val()) || 0;
    const $btn = $('#attendanceSignOutSubmitBtn');
    if (!user_id) {
      this.app.showAlert('Employee is required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'signOutEmployee',
      data: { user_id },
      onSuccess: () => {
        $('#attendanceSignOutForm')[0].reset();
        AppCore.safeHideModal('#attendanceSignOutModal');
        this.loadOverview();
        if (this.currentEmployeeId === user_id) {
          this.loadEmployeeProfile(user_id);
        }
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  saveCredential() {
    if (this.currentRole !== 'owner') {
      this.app.showAlert('Only owner can set credentials', 'error');
      return;
    }

    const user_id = Number($('#attendanceCredentialUserId').val()) || 0;
    const method = String($('#attendanceCredentialMethod').val() || 'pin');
    const secret = String($('#attendanceCredentialSecret').val() || '').trim();
    const $btn = $('#attendanceCredentialSubmitBtn');

    if (!user_id || !secret) {
      this.app.showAlert('Employee and credential are required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'saveAttendanceCredential',
      data: { user_id, method, secret },
      onSuccess: () => {
        $('#attendanceCredentialForm')[0].reset();
        AppCore.safeHideModal('#attendanceCredentialModal');
        this.loadOverview();
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  loadEmployeeProfile(userId) {
    const range = String($('#attendanceRange').val() || '30d');

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'loadEmployeeProfile',
      data: { user_id: userId, range },
      onSuccess: (res) => {
        const data = res.data || {};
        const profile = data.profile || {};
        const activities = Array.isArray(data.activities) ? data.activities : [];

        this.currentEmployeeId = Number(profile.user_id || userId) || userId;
        this.switchTab('details');

        $('#attendanceEmployeeTitle').text(profile.full_name || 'Employee');
        $('#attendanceEmployeeRoleBadge').text(profile.role_name || 'Role');
        $('#attendanceEmployeeEmail').text(profile.email || '-');
        $('#attendanceEmployeePinState').html(Number(profile.has_pin) === 1 ? '<span class="badge badge-success">Configured</span>' : '<span class="badge badge-secondary">Not Set</span>');
        $('#attendanceEmployeeBiometricState').html(Number(profile.has_biometric) === 1 ? '<span class="badge badge-success">Configured</span>' : '<span class="badge badge-secondary">Not Set</span>');

        this.renderActivities(activities);
        this.renderChart(data.chart || {});
      }
    });
  }

  renderActivities(rows) {
    const $tbody = $('#attendanceActivitiesTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted">No attendance records</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const minutesLate = Number(row.minutes_late || 0);
      const statusColorClass = row.status_color === 'green'
        ? 'badge-success'
        : (row.status_color === 'yellow' ? 'badge-warning text-dark' : 'badge-danger');
      const statusLabel = minutesLate <= 0 ? 'On time' : (minutesLate <= 60 ? 'Late' : 'Very late');

      return `
        <tr>
          <td>${row.attendance_date || '-'}</td>
          <td>${row.signin_at ? this.app.formatDateSafe(row.signin_at, '-') : '-'}</td>
          <td>${row.signout_at ? this.app.formatDateSafe(row.signout_at, '-') : '-'}</td>
          <td><span class="badge ${statusColorClass}">${statusLabel}</span></td>
          <td>${minutesLate}</td>
          <td>N${this.app.formatNumber(row.fine_amount || 0)}</td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  renderChart(chartData) {
    const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
    const onTime = Array.isArray(chartData.on_time) ? chartData.on_time : [];
    const late = Array.isArray(chartData.late) ? chartData.late : [];
    const ctx = document.getElementById('attendanceEmployeeChart');
    if (!ctx || typeof Chart === 'undefined') return;

    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }

    this.chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'On Time',
            data: onTime,
            backgroundColor: 'rgba(34, 197, 94, 0.75)'
          },
          {
            label: 'Late',
            data: late,
            backgroundColor: 'rgba(245, 158, 11, 0.75)'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top' }
        },
        scales: {
          x: { stacked: true },
          y: {
            stacked: true,
            ticks: { stepSize: 1, beginAtZero: true }
          }
        }
      }
    });
  }

  switchTab(tabName) {
    if (tabName === 'details') {
      $('#attendanceListTab').hide();
      $('#attendanceDetailsTab').show();
      return;
    }

    $('#attendanceDetailsTab').hide();
    $('#attendanceListTab').show();
  }
}

$(document).ready(function () {
  if ($('.attendance-page').length) {
    new EmployeeAttendancePage();
  }
});
