class EmployeeAttendancePage {
  constructor() {
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    this.app = new AppCore(csrf);
    this.AuthApp = new Auth(this.app);
    this.employees = [];
    this.filteredEmployees = [];
    this.corrections = [];
    this.currentEmployeeId = 0;
    this.chart = null;
    this.currentRole = 'user';
    this.searchTerm = '';

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
      this.loadAttendancePolicyMeta();
      this.loadCorrections();
    });
  }

  bindEvents() {
    $('#attendanceRange').on('change', () => {
      this.loadOverview();
      if (this.currentEmployeeId > 0) {
        this.loadEmployeeProfile(this.currentEmployeeId);
      }
    });

    this.setupPageGuard();

    $('#attendanceEmployeeSearch').on('input', (e) => {
      this.searchTerm = String($(e.currentTarget).val() || '').trim().toLowerCase();
      this.renderEmployees(this.employees);
    });

    $('#attendanceCorrectionStatus').on('change', () => {
      this.loadCorrections();
    });

    $('#openAttendanceSignInModalBtn').on('click', () => {
      $('#attendanceSignInModal').modal('show');
    });

    $('#runAutoAbsenceBtn').on('click', () => {
      this.runAutoAbsence();
    });

    $('#attendanceExportCsvBtn').on('click', () => {
      this.exportCsv();
    });

    $('#attendanceExportPdfBtn').on('click', () => {
      this.exportPdf();
    });

    $('#openCorrectionRequestBtn').on('click', () => {
      const today = new Date();
      const yyyy = today.getFullYear();
      const mm = String(today.getMonth() + 1).padStart(2, '0');
      const dd = String(today.getDate()).padStart(2, '0');
      $('#attendanceCorrectionDate').val(`${yyyy}-${mm}-${dd}`);
      $('#attendanceCorrectionModal').modal('show');
    });

    $('#attendanceSignInForm').on('submit', (e) => {
      e.preventDefault();
      this.signInEmployee();
    });

    $('#attendanceSignOutForm').on('submit', (e) => {
      e.preventDefault();
      this.signOutEmployee();
    });

    $('#attendanceShiftForm').on('submit', (e) => {
      e.preventDefault();
      this.saveShiftRule();
    });

    $('#attendanceCorrectionForm').on('submit', (e) => {
      e.preventDefault();
      this.requestCorrection();
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

    $(document).on('click', '.attendance-shift-btn', (e) => {
      e.stopPropagation();
      const $btn = $(e.currentTarget);
      $('#attendanceShiftUserId').val(String(Number($btn.data('id')) || 0));
      $('#attendanceShiftStart').val(String($btn.data('shiftStart') || '09:00'));
      $('#attendanceShiftEnd').val(String($btn.data('shiftEnd') || '17:00'));
      $('#attendanceShiftGrace').val(String(Number($btn.data('grace')) || 0));
      $('#attendanceShiftActive').val(String(Number($btn.data('active')) === 1 ? 1 : 0));
      $('#attendanceShiftModal').modal('show');
    });

    $(document).on('click', '.attendance-request-correction-btn', (e) => {
      e.stopPropagation();
      const userId = Number($(e.currentTarget).data('id')) || 0;
      if (!userId) return;
      $('#attendanceCorrectionEmployee').val(String(userId));
      $('#attendanceCorrectionModal').modal('show');
    });

    $(document).on('click', '.attendance-correction-review-btn', (e) => {
      const correctionId = Number($(e.currentTarget).data('id')) || 0;
      const decision = String($(e.currentTarget).data('decision') || '').trim().toLowerCase();
      if (!correctionId || !decision) return;
      this.reviewCorrection(correctionId, decision);
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

  loadCorrections() {
    const status = String($('#attendanceCorrectionStatus').val() || 'pending');
    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'loadCorrectionRequests',
      data: { status },
      silent: true,
      onSuccess: (res) => {
        this.corrections = Array.isArray(res.data) ? res.data : [];
        this.renderCorrections(this.corrections);
      }
    });
  }

  renderSummary(summary) {
    $('#attendanceStatEmployees').text(String(Number(summary.employees) || 0));
    $('#attendanceStatSignedInToday').text(String(Number(summary.signed_in_today) || 0));
    $('#attendanceStatLateToday').text(String(Number(summary.late_today) || 0));
    $('#attendanceStatAbsentToday').text(String(Number(summary.absent_today) || 0));
    $('#attendanceStatFinesToday').text(`N${this.app.formatNumber(summary.total_fines_today || 0)}`);
  }

  renderEmployees(rows) {
    const $tbody = $('#attendanceEmployeesTableBody');
    if (!$tbody.length) return;

    const sourceRows = Array.isArray(rows) ? rows : [];
    const signedInRows = sourceRows.filter((row) => String(row.today_signin_at || '').trim() !== '');
    const filteredRows = signedInRows.filter((row) => {
      if (!this.searchTerm) return true;

      const haystack = [
        row.full_name || '',
        row.email || '',
        row.role_name || ''
      ].join(' ').toLowerCase();

      return haystack.includes(this.searchTerm);
    });

    this.filteredEmployees = filteredRows;

    if (!filteredRows.length) {
      const emptyMessage = signedInRows.length
        ? 'No signed-in employees match your search.'
        : 'No employees have signed in today.';
      $tbody.html(`<tr class="attendance-empty-row"><td colspan="4" class="text-center text-muted py-4">${emptyMessage}</td></tr>`);
      return;
    }

    const html = filteredRows.map((row) => {
      const statusMeta = this.getEmployeeTodayStatusMeta(row);
      const signInTime = this.formatAttendanceTime(row.today_signin_at);
      const employeeLabel = AppCore.escapeHtml(row.full_name || '-');
      const email = AppCore.escapeHtml(row.email || '-');
      const role = AppCore.escapeHtml(row.role_name || 'User');

      return `
        <tr class="attendance-employee-row" data-id="${Number(row.user_id) || 0}">
          <td data-label="Employee">
            <div class="attendance-employee-name">${employeeLabel}</div>
            <div class="attendance-employee-meta">${email}</div>
          </td>
          <td data-label="Role">${role}</td>
          <td data-label="Signed In">${signInTime}</td>
          <td data-label="Status"><span class="badge ${statusMeta.badgeClass}">${statusMeta.label}</span></td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  getEmployeeTodayStatusMeta(row) {
    const hasSignOut = String(row.today_signout_at || '').trim() !== '';
    const minutesLate = Number(row.today_minutes_late || 0);
    const grade = String(row.today_late_grade || '').trim().toLowerCase();

    if (hasSignOut) {
      return { label: 'Signed out', badgeClass: 'badge-secondary px-2 py-1' };
    }

    if (grade === 'absent') {
      return { label: 'Absent', badgeClass: 'badge-danger px-2 py-1' };
    }

    if (minutesLate > 0) {
      return { label: `Late by ${minutesLate}m`, badgeClass: 'badge-warning text-dark px-2 py-1' };
    }

    return { label: 'On time', badgeClass: 'badge-success px-2 py-1' };
  }

  formatAttendanceTime(value) {
    const raw = String(value || '').trim();
    if (!raw) return '-';

    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) {
      return raw;
    }

    return parsed.toLocaleTimeString([], {
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  renderCorrections(rows) {
    const $tbody = $('#attendanceCorrectionsTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No pending correction requests</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const status = String(row.status || 'pending');
      const badgeClass = status === 'approved' ? 'badge-success' : (status === 'rejected' ? 'badge-danger' : 'badge-warning text-dark');
      const currentText = `In: ${row.current_signin_at || '-'} <br> Out: ${row.current_signout_at || '-'}`;
      const proposedText = `<span class="text-primary">In: ${row.proposed_signin_at || '-'}</span> <br> <span class="text-primary">Out: ${row.proposed_signout_at || '-'}</span>`;
      const reviewButtons = status === 'pending'
        ? `<div class="btn-group shadow-sm">
            <button class="btn btn-sm btn-success attendance-correction-review-btn" data-id="${row.correction_id}" data-decision="approve"><i class="fas fa-check"></i></button>
            <button class="btn btn-sm btn-danger attendance-correction-review-btn" data-id="${row.correction_id}" data-decision="reject"><i class="fas fa-times"></i></button>
           </div>`
        : '-';

      return `
        <tr>
          <td class="pl-4 font-weight-bold text-dark">${AppCore.escapeHtml(row.full_name || '-')}</td>
          <td>${AppCore.escapeHtml(row.attendance_date || '-')}</td>
          <td class="small">${currentText}</td>
          <td class="small">${proposedText}</td>
          <td><span class="text-muted">${AppCore.escapeHtml(row.reason || '-')}</span></td>
          <td><span class="badge ${badgeClass} text-uppercase px-2">${AppCore.escapeHtml(status)}</span></td>
          <td class="pr-4 text-right">${reviewButtons}</td>
        </tr>
      `;
    }).join('');

    $tbody.html(html);
  }

  loadEmployeeOptionsForModals() {
    const options = ['<option value="">Select employee</option>'];
    this.employees.forEach((e) => {
      options.push(`<option value="${e.user_id}">${AppCore.escapeHtml(e.full_name)} (${AppCore.escapeHtml(e.role_name)})</option>`);
    });

    $('#attendanceSignOutEmployee').html(options.join(''));
    $('#attendanceCorrectionEmployee').html(options.join(''));
  }

  signInEmployee() {
    const email = String($('#attendanceSignInEmail').val() || '').trim().toLowerCase();
    const password = String($('#attendanceSignInPassword').val() || '');
    const notes = String($('#attendanceSignInNotes').val() || '').trim();
    const $btn = $('#attendanceSignInSubmitBtn');

    if (!email || !password) {
      this.app.showAlert('Employee email and password are required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'signInEmployee',
      data: { email, password, notes },
      onSuccess: (res) => {
        $('#attendanceSignInForm')[0].reset();
        AppCore.safeHideModal('#attendanceSignInModal');
        this.loadOverview();
        const userId = Number(res?.data?.user_id || 0);
        if (userId > 0 && this.currentEmployeeId === userId) {
          this.loadEmployeeProfile(userId);
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

  saveShiftRule() {
    const user_id = Number($('#attendanceShiftUserId').val()) || 0;
    const shift_start = String($('#attendanceShiftStart').val() || '').trim();
    const shift_end = String($('#attendanceShiftEnd').val() || '').trim();
    const grace_minutes = Number($('#attendanceShiftGrace').val() || 0);
    const is_active = Number($('#attendanceShiftActive').val() || 1);
    const $btn = $('#attendanceShiftSubmitBtn');

    if (!user_id || !shift_start || !shift_end) {
      this.app.showAlert('Shift details are required', 'error');
      return;
    }

    $btn.prop('disabled', true);
    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'saveShiftRule',
      data: { user_id, shift_start, shift_end, grace_minutes, is_active },
      onSuccess: () => {
        AppCore.safeHideModal('#attendanceShiftModal');
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

  toDbDateTime(datetimeLocalValue) {
    const raw = String(datetimeLocalValue || '').trim();
    if (!raw) return '';
    return raw.replace('T', ' ') + ':00';
  }

  requestCorrection() {
    const user_id = Number($('#attendanceCorrectionEmployee').val()) || 0;
    const attendance_date = String($('#attendanceCorrectionDate').val() || '').trim();
    const proposed_signin_at = this.toDbDateTime($('#attendanceCorrectionSignIn').val());
    const proposed_signout_at = this.toDbDateTime($('#attendanceCorrectionSignOut').val());
    const reason = String($('#attendanceCorrectionReason').val() || '').trim();
    const $btn = $('#attendanceCorrectionSubmitBtn');

    if (!user_id || !attendance_date || !reason) {
      this.app.showAlert('Employee, date and reason are required', 'error');
      return;
    }

    $btn.prop('disabled', true);

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'requestCorrection',
      data: { user_id, attendance_date, proposed_signin_at, proposed_signout_at, reason },
      onSuccess: () => {
        $('#attendanceCorrectionForm')[0].reset();
        AppCore.safeHideModal('#attendanceCorrectionModal');
        this.loadCorrections();
      },
      onComplete: () => {
        $btn.prop('disabled', false);
      }
    });
  }

  reviewCorrection(correctionId, decision) {
    const review_note = window.prompt(`Optional ${decision} note:`, '') || '';

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'reviewCorrection',
      data: { correction_id: correctionId, decision, review_note },
      onSuccess: () => {
        this.loadCorrections();
        this.loadOverview();
        if (this.currentEmployeeId > 0) {
          this.loadEmployeeProfile(this.currentEmployeeId);
        }
      }
    });
  }

  runAutoAbsence() {
    const date = window.prompt('Enter date for auto-absence (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
    if (!date) return;

    this.app.ajaxHelper({
      url: 'apiEmployeeAttendance.php',
      action: 'runAutoAbsence',
      data: { date },
      onSuccess: (res) => {
        const inserted = Number(res?.data?.inserted || 0);
        this.app.showAlert(`Auto-absence complete: ${inserted} record(s) created.`, 'success');
        this.loadOverview();
        this.loadCorrections();
        if (this.currentEmployeeId > 0) {
          this.loadEmployeeProfile(this.currentEmployeeId);
        }
      }
    });
  }

  exportCsv() {
    const rows = this.filteredEmployees || [];
    if (!rows.length) {
      this.app.showAlert('No rows to export', 'error');
      return;
    }

    const headers = ['Name', 'Email', 'Role', 'Attendance Days', 'On Time', 'Late', 'Absent', 'Total Fine', 'GPI', 'Performance', 'Shift'];
    const body = rows.map((row) => [
      row.full_name || '',
      row.email || '',
      row.role_name || '',
      Number(row.attendance_days || 0),
      Number(row.on_time_days || 0),
      Number(row.late_days || 0),
      Number(row.absent_days || 0),
      Number(row.total_fine || 0),
      Number(row.gpi || 0),
      row.performance_label || '',
      Number(row.has_shift || 0) === 1 ? `${row.shift_start || ''}-${row.shift_end || ''} (+${Number(row.grace_minutes || 0)}m)` : 'Default'
    ]);

    const csv = [headers, ...body].map((line) => line.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance-overview-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  exportPdf() {
    const rows = this.filteredEmployees || [];
    if (!rows.length) {
      this.app.showAlert('No rows to export', 'error');
      return;
    }

    if (!(window.jspdf && window.jspdf.jsPDF)) {
      this.app.showAlert('PDF library unavailable', 'error');
      return;
    }

    const doc = new window.jspdf.jsPDF({ orientation: 'landscape' });
    doc.setFontSize(12);
    doc.text('Employee Attendance Overview', 14, 14);

    const tableRows = rows.map((row) => [
      row.full_name || '-',
      row.role_name || '-',
      Number(row.attendance_days || 0),
      Number(row.on_time_days || 0),
      Number(row.late_days || 0),
      Number(row.absent_days || 0),
      `N${this.app.formatNumber(row.total_fine || 0)}`,
      this.app.formatNumber(row.gpi || 0),
      row.performance_label || '-'
    ]);

    doc.autoTable({
      startY: 20,
      head: [['Employee', 'Role', 'Attendance', 'On Time', 'Late', 'Absent', 'Fine', 'GPI', 'Performance']],
      body: tableRows,
      styles: { fontSize: 8 }
    });

    doc.save(`attendance-overview-${new Date().toISOString().slice(0, 10)}.pdf`);
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
        const summary = data.summary || {};
        const activities = Array.isArray(data.activities) ? data.activities : [];

        this.currentEmployeeId = Number(profile.user_id || userId) || userId;
        this.switchTab('details');

        $('#attendanceEmployeeTitle').text(profile.full_name || 'Employee Details');
        $('#attendanceEmployeeRoleBadge').text(profile.role_name || 'Role');
        $('#attendanceEmployeeEmail').text(profile.email || '-');
        $('#attendanceEmployeeAuthState').text(profile.signin_auth || 'Email + Password');
        $('#attendanceEmployeeShiftWindow').text(Number(profile.has_shift || 0) === 1 ? `${profile.shift_start || '-'}-${profile.shift_end || '-'}` : 'Default policy');
        $('#attendanceEmployeeShiftGrace').text(String(Number(profile.grace_minutes || 0)));

        const tone = String(summary.performance_tone || 'danger');
        const badgeClass = tone === 'success' ? 'badge-success' : (tone === 'warning' ? 'badge-warning text-dark' : 'badge-danger');
        $('#attendanceEmployeeGpi').html(`<span class="badge ${badgeClass}">${this.app.formatNumber(summary.gpi || 0)}</span>`);
        $('#attendanceEmployeeAttendanceDays').text(String(Number(summary.attendance_days || 0)));
        $('#attendanceEmployeeOnTimeDays').text(String(Number(summary.on_time_days || 0)));
        $('#attendanceEmployeeLateDays').text(String(Number(summary.late_days || 0)));
        $('#attendanceEmployeeTotalFine').text(`N${this.app.formatNumber(summary.total_fine || 0)}`);

        this.renderActivities(activities);
        this.renderChart(data.chart || {});
      }
    });
  }

  renderActivities(rows) {
    const $tbody = $('#attendanceActivitiesTable tbody');
    if (!$tbody.length) return;

    if (!rows.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No recent activities recorded</td></tr>');
      return;
    }

    const html = rows.map((row) => {
      const minutesLate = Number(row.minutes_late || 0);
      const grade = String(row.late_grade || 'on_time');
      let statusLabel = 'On time';
      let statusClass = 'badge-success';
      let statusIcon = '<i class="fas fa-check-circle mr-1"></i>';

      if (grade === 'absent') {
        statusLabel = 'Absent';
        statusClass = 'badge-danger';
        statusIcon = '<i class="fas fa-user-slash mr-1"></i>';
      } else if (minutesLate > 60) {
        statusLabel = 'Very late';
        statusClass = 'badge-danger';
        statusIcon = '<i class="fas fa-exclamation-circle mr-1"></i>';
      } else if (minutesLate > 0) {
        statusLabel = 'Late';
        statusClass = 'badge-warning text-dark';
        statusIcon = '<i class="fas fa-clock mr-1"></i>';
      }

      return `
        <tr>
          <td class="pl-3">${row.attendance_date || '-'}</td>
          <td>${row.signin_at ? this.app.formatDateSafe(row.signin_at, '-') : '<span class="text-muted">-</span>'}</td>
          <td>${row.signout_at ? this.app.formatDateSafe(row.signout_at, '-') : '<span class="text-muted">-</span>'}</td>
          <td><span class="badge ${statusClass} px-2">${statusIcon}${statusLabel}</span></td>
          <td class="${minutesLate > 0 ? 'text-warning font-weight-bold' : ''}">${minutesLate}</td>
          <td class="pr-3 ${Number(row.fine_amount) > 0 ? 'text-danger font-weight-bold' : ''}">N${this.app.formatNumber(row.fine_amount || 0)}</td>
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
            label: 'Late / Absent',
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

  setupPageGuard() {
    // Intercept clicks on navbar and other links to prevent leaving without password
    const $links = $('.tab button, .navbar a, .sidebar a, a[href]');

    $links.on('click', (e) => {
      const target = $(e.currentTarget).attr('href') || '';
      // Allow internal hashes or empty links
      if (!target || target.startsWith('#') || target.includes('javascript:')) return;

      e.preventDefault();
      const pass = window.prompt('Owner Security: Enter password to exit attendance kiosk mode:', '');

      if (!pass) return;

      this.app.ajaxHelper({
        url: 'apiAuthentications.php',
        action: 'verifyOwnerPassword',
        data: { password: pass },
        onSuccess: () => {
          window.location.href = target;
        },
        onError: () => {
          this.app.showAlert('Incorrect password. Access denied.', 'error');
        }
      });
    });

    // Also handle browser back/forward if possible, though restricted by modern browsers
    window.addEventListener('popstate', () => {
      // Logic to push state back if they try to use browser buttons?
      // Usually prompt is better on click events.
    });
  }
}

$(document).ready(function () {
  if ($('.attendance-page').length) {
    new EmployeeAttendancePage();
  }
});
