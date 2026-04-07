<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";

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
      <div class="row mb-2 align-items-center">
        <div class="col-sm-7">
          <h1 class="m-0">Employee Attendance Tracker</h1>
          <small class="text-muted">Monitor sign-in/out, late grades, fines, and GPI performance</small>
        </div>
        <div class="col-sm-5 text-right">
          <select id="attendanceRange" class="form-control d-inline-block w-auto">
            <option value="today">Today</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d" selected>Last 30 Days</option>
            <option value="all">All Time</option>
          </select>
          <button class="btn btn-primary ml-2" id="openAttendanceSignInModalBtn">Record Sign-In</button>
        </div>
      </div>
    </div>
  </div>

  <div class="content-body">
    <div class="container-fluid">
      <div class="row mb-3">
        <div class="col-12 col-md-6 col-xl-3 mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-indigo">
            <div class="card-body">
              <small class="text-muted">Employees</small>
              <h3 id="attendanceStatEmployees">0</h3>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3 mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-green">
            <div class="card-body">
              <small class="text-muted">Signed In Today</small>
              <h3 id="attendanceStatSignedInToday">0</h3>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3 mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-amber">
            <div class="card-body">
              <small class="text-muted">Late Today</small>
              <h3 id="attendanceStatLateToday">0</h3>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3 mb-3">
          <div class="card shadow-sm attendance-stat-card attendance-stat-rose">
            <div class="card-body">
              <small class="text-muted">Fines Today</small>
              <h3 id="attendanceStatFinesToday">N0.00</h3>
            </div>
          </div>
        </div>
      </div>

      <div id="attendanceListTab" class="attendance-tab-pane">
        <div class="card shadow-sm mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Employees</strong>
            <small class="text-muted">Click employee row for details and activities</small>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-striped mb-0" id="attendanceEmployeesTable">
                <thead class="thead-dark">
                  <tr>
                    <th>Employee</th>
                    <th>Role</th>
                    <th>Attendance Days</th>
                    <th>On Time</th>
                    <th>Late</th>
                    <th>Total Fine</th>
                    <th>GPI</th>
                    <th>Performance</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div id="attendanceDetailsTab" class="attendance-tab-pane" style="display:none;">
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <button class="btn btn-sm btn-outline-secondary mr-2" id="attendanceBackToListBtn">Back</button>
              <strong id="attendanceEmployeeTitle">Employee Details</strong>
            </div>
            <span id="attendanceEmployeeRoleBadge" class="badge badge-info">Role</span>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4 mb-2"><div class="border rounded p-2"><small>Email</small><div id="attendanceEmployeeEmail">-</div></div></div>
              <div class="col-md-4 mb-2"><div class="border rounded p-2"><small>PIN</small><div id="attendanceEmployeePinState">-</div></div></div>
              <div class="col-md-4 mb-2"><div class="border rounded p-2"><small>Biometric</small><div id="attendanceEmployeeBiometricState">-</div></div></div>
            </div>

            <div class="row">
              <div class="col-lg-5 mb-3">
                <canvas id="attendanceEmployeeChart" height="220"></canvas>
              </div>
              <div class="col-lg-7 mb-3">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered" id="attendanceActivitiesTable">
                    <thead class="thead-light">
                      <tr>
                        <th>Date</th>
                        <th>Sign In</th>
                        <th>Sign Out</th>
                        <th>Status</th>
                        <th>Late (mins)</th>
                        <th>Fine</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
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

<?php include "INC/footer.php"; ?>
<script src="assets/vendor/js/chart.umd.min.js"></script>
<script src="scripts/employees_attendance.js?v=<?= asset_ver('scripts/employees_attendance.js') ?>"></script>

</body>
</html>
