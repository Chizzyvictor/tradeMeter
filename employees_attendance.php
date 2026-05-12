<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>
<link rel="stylesheet" href="styles/attendance.css?v=<?= asset_ver('styles/attendance.css') ?>">

<?php
$roles = $_SESSION['roles'] ?? [];
$roles = is_array($roles) ? array_map(static function ($v) {
    return strtolower(trim((string)$v));
}, $roles) : [];
$currentUserId = intval($_SESSION['user_id'] ?? 0);
$isManagerOrOwner = $currentUserId <= 0 || in_array('owner', $roles, true) || in_array('manager', $roles, true);
if (!$isManagerOrOwner) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Unauthorized</div></div>';
    include "INC/footer.php";
    echo '</body></html>';
    exit;
}
?>

<div class="content attendance-page">
  <div class="content-header">
    <div class="container-fluid">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
          <h1 class="m-0"><i class="fas fa-user-clock mr-2 text-primary"></i>Employee Attendance</h1>
          <p class="text-muted mb-0">Monitor sign-in/out, late grades, fines, and performance metrics</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex flex-wrap align-items-center">
          <div class="input-group mr-2 mb-2 mb-md-0 shadow-sm" style="width: auto;">
            <div class="input-group-prepend">
              <span class="input-group-text bg-white border-right-0"><i class="fas fa-calendar-alt text-muted"></i></span>
            </div>
            <select id="attendanceRange" class="form-control border-left-0 pl-0">
              <option value="today">Today</option>
              <option value="7d">Last 7 Days</option>
              <option value="30d" selected>Last 30 Days</option>
              <option value="all">All Time</option>
            </select>
          </div>

          <div class="btn-group mr-2 mb-2 mb-md-0 shadow-sm">
            <button class="btn btn-outline-secondary btn-sm" id="attendanceExportCsvBtn" title="Export CSV"><i class="fas fa-file-csv"></i></button>
            <button class="btn btn-outline-secondary btn-sm" id="attendanceExportPdfBtn" title="Export PDF"><i class="fas fa-file-pdf"></i></button>
          </div>

          <button class="btn btn-primary btn-sm shadow-sm" id="openAttendanceSignInModalBtn">
            <i class="fas fa-plus-circle mr-1"></i> Record Sign-In
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="content-body">
    <div class="container-fluid">
      <!-- Summary Cards -->
      <div class="row mb-4">
        <div class="col-12 col-md-4 col-xl mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-indigo">
            <div class="card-body d-flex align-items-center">
              <div class="stat-icon mr-3 bg-indigo-soft text-indigo p-3 rounded-circle" style="background: rgba(102, 126, 234, 0.1);">
                <i class="fas fa-users fa-2x"></i>
              </div>
              <div>
                <small class="text-muted">Total Employees</small>
                <h3 id="attendanceStatEmployees" class="mb-0">0</h3>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4 col-xl mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-green">
            <div class="card-body d-flex align-items-center">
              <div class="stat-icon mr-3 p-3 rounded-circle" style="background: rgba(72, 187, 120, 0.1);">
                <i class="fas fa-check-circle fa-2x text-success"></i>
              </div>
              <div>
                <small class="text-muted">Active Today</small>
                <h3 id="attendanceStatSignedInToday" class="mb-0">0</h3>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4 col-xl mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-amber">
            <div class="card-body d-flex align-items-center">
              <div class="stat-icon mr-3 p-3 rounded-circle" style="background: rgba(237, 137, 54, 0.1);">
                <i class="fas fa-clock fa-2x text-warning"></i>
              </div>
              <div>
                <small class="text-muted">Late Today</small>
                <h3 id="attendanceStatLateToday" class="mb-0">0</h3>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4 col-xl mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-slate">
            <div class="card-body d-flex align-items-center">
              <div class="stat-icon mr-3 p-3 rounded-circle" style="background: rgba(160, 174, 192, 0.1);">
                <i class="fas fa-user-times fa-2x text-secondary"></i>
              </div>
              <div>
                <small class="text-muted">Absent Today</small>
                <h3 id="attendanceStatAbsentToday" class="mb-0">0</h3>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4 col-xl mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-rose">
            <div class="card-body d-flex align-items-center">
              <div class="stat-icon mr-3 p-3 rounded-circle" style="background: rgba(245, 101, 101, 0.1);">
                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
              </div>
              <div>
                <small class="text-muted">Total Fines</small>
                <h3 id="attendanceStatFinesToday" class="mb-0">N0.00</h3>
              </div>
            </div>
          </div>
        </div>
      </div>


      <div id="attendanceDetailsTab" class="attendance-tab-pane" style="display:none;">
        <div class="card shadow-sm mb-4 border-0">
          <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div class="d-flex align-items-center">
              <button class="btn btn-sm btn-light shadow-sm mr-3" id="attendanceBackToListBtn">
                <i class="fas fa-chevron-left"></i>
              </button>
              <div>
                <h5 class="mb-0 font-weight-bold" id="attendanceEmployeeTitle">Employee Details</h5>
                <small class="text-muted" id="attendanceEmployeeEmail">email@example.com</small>
              </div>
            </div>
            <div id="attendanceEmployeeRoleBadge" class="badge badge-primary px-3 py-2">Role</div>
          </div>
          <div class="card-body bg-light-soft">
            <div class="detail-grid">
              <div class="detail-item shadow-sm border-0">
                <small><i class="fas fa-id-badge mr-1"></i> Sign-In Auth</small>
                <div id="attendanceEmployeeAuthState">-</div>
              </div>
              <div class="detail-item shadow-sm border-0">
                <small><i class="fas fa-chart-line mr-1"></i> GPI Performance</small>
                <div id="attendanceEmployeeGpi">-</div>
              </div>
              <div class="detail-item shadow-sm border-0">
                <small><i class="fas fa-clock mr-1"></i> Shift Window</small>
                <div id="attendanceEmployeeShiftWindow">-</div>
              </div>
              <div class="detail-item shadow-sm border-0">
                <small><i class="fas fa-hourglass-start mr-1"></i> Grace Period</small>
                <div id="attendanceEmployeeShiftGrace">0</div>
              </div>
            </div>

            <div class="row mb-4">
              <div class="col-6 col-lg-3 mb-2">
                <div class="card border-0 shadow-sm text-center py-3">
                  <small class="text-muted font-weight-bold text-uppercase">Total Days</small>
                  <h4 class="mb-0 font-weight-bold text-indigo" id="attendanceEmployeeAttendanceDays">0</h4>
                </div>
              </div>
              <div class="col-6 col-lg-3 mb-2">
                <div class="card border-0 shadow-sm text-center py-3">
                  <small class="text-muted font-weight-bold text-uppercase">On Time</small>
                  <h4 class="mb-0 font-weight-bold text-success" id="attendanceEmployeeOnTimeDays">0</h4>
                </div>
              </div>
              <div class="col-6 col-lg-3 mb-2">
                <div class="card border-0 shadow-sm text-center py-3">
                  <small class="text-muted font-weight-bold text-uppercase">Late Days</small>
                  <h4 class="mb-0 font-weight-bold text-warning" id="attendanceEmployeeLateDays">0</h4>
                </div>
              </div>
              <div class="col-6 col-lg-3 mb-2">
                <div class="card border-0 shadow-sm text-center py-3">
                  <small class="text-muted font-weight-bold text-uppercase">Total Fines</small>
                  <h4 class="mb-0 font-weight-bold text-danger" id="attendanceEmployeeTotalFine">N0.00</h4>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-5 mb-3">
                <div class="card shadow-sm border-0 h-100 p-3">
                  <h6 class="font-weight-bold mb-3">Trend Analysis</h6>
                  <div style="height: 250px;">
                    <canvas id="attendanceEmployeeChart"></canvas>
                  </div>
                </div>
              </div>
              <div class="col-lg-7 mb-3">
                <div class="card shadow-sm border-0 h-100">
                  <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 font-weight-bold">Recent Activities</h6>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm table-borderless table-striped mb-0" id="attendanceActivitiesTable">
                      <thead class="bg-light">
                        <tr>
                          <th class="pl-3">Date</th>
                          <th>In</th>
                          <th>Out</th>
                          <th>Status</th>
                          <th>Late</th>
                          <th class="pr-3">Fine</th>
                        </tr>
                      </thead>
                      <tbody class="small"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


    </div>
  </div>
</div>

<?php include "INC/footer.php"; ?>
<script src="assets/vendor/js/chart.umd.min.js"></script>
<script src="assets/vendor/js/jspdf.umd.min.js"></script>
<script src="assets/vendor/js/jspdf.plugin.autotable.min.js"></script>
<script src="scripts/employees_attendance.js?v=<?= asset_ver('scripts/employees_attendance.js') ?>"></script>

</body>
</html>
