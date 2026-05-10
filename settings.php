<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="content settings-page">
	<div class="content-header">
		<div class="container-fluid">
			<div class="row mb-2">
				<div class="col-sm-6">
					<h1 class="m-0">Company Settings</h1>
				</div>
				<div class="col-sm-6 text-right">
					<p class="text-muted small">Go to <a href="user_profile.php">My Profile</a> to manage personal settings.</p>
				</div>
			</div>
		</div>
	</div>

	<div class="content-body">
		<div class="container-fluid">
			<div class="row">
				<div class="col-lg-4 mb-4">
					<div class="card shadow-sm h-100 settings-profile-card">
						<div class="card-header font-weight-bold">Company Profile</div>
						<div class="card-body text-center">
							<img id="settingsCompanyLogo" src="Images/companyDP/logo.jpg" alt="Company Logo" class="image img-thumbnail mb-3" style="max-width: 160px; max-height: 160px;">
							<h5 id="settingsCompanyName" class="mb-1">-</h5>
							<p id="settingsCompanyEmail" class="text-muted mb-2">-</p>
							<small class="text-muted">Registered: <span id="settingsRegDate">-</span></small>
						</div>
					</div>
				</div>

				<div class="col-lg-8 mb-4 settings-main-column">
					<ul class="nav nav-tabs settings-tabs mb-3" id="settingsTabs" role="tablist">
						<li class="nav-item" role="presentation">
							<a class="nav-link active" id="settings-general-tab" data-toggle="tab" href="#settings-general" role="tab" aria-controls="settings-general" aria-selected="true">General</a>
						</li>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="settings-users-tab" data-toggle="tab" href="#settings-users" role="tab" aria-controls="settings-users" aria-selected="false">Users</a>
						</li>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="settings-security-tab" data-toggle="tab" href="#settings-security" role="tab" aria-controls="settings-security" aria-selected="false">Security</a>
						</li>
						<li class="nav-item settings-backup-section" role="presentation" style="display:none;">
							<a class="nav-link" id="settings-backups-tab" data-toggle="tab" href="#settings-backups" role="tab" aria-controls="settings-backups" aria-selected="false">Backups</a>
						</li>
						<li class="nav-item" role="presentation">
							<a class="nav-link" id="settings-smtp-tab" data-toggle="tab" href="#settings-smtp" role="tab" aria-controls="settings-smtp" aria-selected="false">SMTP</a>
						</li>
					</ul>

					<div class="tab-content settings-tab-content" id="settingsTabsContent">
						<div class="tab-pane fade show active" id="settings-general" role="tabpanel" aria-labelledby="settings-general-tab">
					<div class="card shadow-sm mb-4 settings-section-card">
						<div class="card-header font-weight-bold">Update Company Details</div>
						<div class="card-body">
							<form id="companyProfileForm" enctype="multipart/form-data">
								<div class="form-row">
									<div class="form-group col-md-6">
										<label for="companyName">Company Name</label>
										<input type="text" id="companyName" name="cName" class="form-control" required>
									</div>
									<div class="form-group col-md-6">
										<label for="companyEmail">Company Email</label>
										<input type="email" id="companyEmail" name="cEmail" class="form-control" required>
									</div>
								</div>
								<div class="form-group">
									<label for="companyLogo">Company Logo</label>
									<input type="file" id="companyLogo" name="companyLogo" class="form-control" accept="image/*">
									<small class="form-text text-muted">Optional. JPG, PNG or GIF (max 2MB).</small>
								</div>
								<div class="settings-form-actions">
									<button type="submit" class="btn btn-primary" id="saveCompanyProfileBtn">Save Profile</button>
								</div>
							</form>
						</div>
					</div>

					<div class="card shadow-sm mb-4 settings-section-card settings-owner-section" style="display:none;">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>Employee Attendance Policy</span>
							<span class="badge badge-dark">Owner Only</span>
						</div>
						<div class="card-body">
							<form id="attendancePolicyForm">
								<div class="form-row">
									<div class="form-group col-md-3">
										<label for="attendanceResumptionTime">Resumption Time</label>
										<input type="time" id="attendanceResumptionTime" class="form-control" required>
									</div>
									<div class="form-group col-md-3">
										<label for="attendanceFine0To15">0-15 mins late</label>
										<input type="number" id="attendanceFine0To15" class="form-control" min="0" step="0.01" required>
									</div>
									<div class="form-group col-md-3">
										<label for="attendanceFine15To60">15-60 mins late</label>
										<input type="number" id="attendanceFine15To60" class="form-control" min="0" step="0.01" required>
									</div>
									<div class="form-group col-md-3">
										<label for="attendanceFine60Plus">1hr+ late</label>
										<input type="number" id="attendanceFine60Plus" class="form-control" min="0" step="0.01" required>
									</div>
								</div>
								<div class="settings-form-actions">
									<button type="submit" class="btn btn-primary" id="saveAttendancePolicyBtn">Save Attendance Policy</button>
								</div>
							</form>
							<small class="text-muted d-block mt-2">Late fines are applied automatically on employee sign-in.</small>
						</div>
					</div>
						</div>
						<div class="tab-pane fade" id="settings-users" role="tabpanel" aria-labelledby="settings-users-tab">

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>User Management</span>
							<button type="button" class="btn btn-sm btn-outline-primary" id="seedDemoUsersBtn">Seed Demo Manager/Staff</button>
						</div>
						<div class="card-body">
							<form id="createUserForm" class="mb-4">
								<div class="form-row">
									<div class="form-group col-md-4">
										<label for="newUserFullName">Full Name</label>
										<input type="text" id="newUserFullName" class="form-control" required>
									</div>
									<div class="form-group col-md-4">
										<label for="newUserEmail">Email</label>
										<input type="email" id="newUserEmail" class="form-control" required>
									</div>
									<div class="form-group col-md-2">
										<label for="newUserPassword">Password</label>
										<input type="password" id="newUserPassword" class="form-control" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" autocomplete="new-password" required>
										<small class="form-text text-muted">8+ chars with uppercase, lowercase and number.</small>
									</div>
									<div class="form-group col-md-2">
										<label for="newUserRole">Role</label>
										<select id="newUserRole" class="form-control" required></select>
									</div>
								</div>
								<div class="settings-form-actions">
									<button type="submit" class="btn btn-primary" id="createUserBtn">Create User</button>
								</div>
							</form>

							<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
								<input type="text" id="usersSearchInput" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search by name or email">
								<div class="d-flex align-items-center mt-2 mt-sm-0">
									<button type="button" class="btn btn-sm btn-outline-secondary" id="usersPrevPageBtn">Prev</button>
									<span class="mx-2 text-muted small" id="usersPageInfo">Page 1 of 1</span>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="usersNextPageBtn">Next</button>
								</div>
							</div>

							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="usersTable">
									<thead>
										<tr>
											<th>Name</th>
											<th>Email</th>
											<th>Role</th>
											<th>Status</th>
											<th>Created</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="6" class="text-center text-muted">No users found</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
						</div>
						<div class="tab-pane fade" id="settings-security" role="tabpanel" aria-labelledby="settings-security-tab">

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>Remember Me Audit (Last 50)</span>
							<button type="button" class="btn btn-sm btn-outline-secondary" id="refreshRememberAuditBtn">Refresh</button>
						</div>
						<div class="card-body">
							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="rememberAuditTable">
									<thead>
										<tr>
											<th>Time</th>
											<th>Event</th>
											<th>User</th>
											<th>IP</th>
											<th>Details</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="5" class="text-center text-muted">No remember-me audit records yet</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>Active Device Sessions</span>
							<div>
								<button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="refreshSessionsBtn">Refresh</button>
								<button type="button" class="btn btn-sm btn-danger" id="logoutAllDevicesBtn">Logout All Devices</button>
							</div>
						</div>
						<div class="card-body">
							<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
								<input type="text" id="sessionsSearchInput" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search user, IP or device">
								<div class="d-flex align-items-center mt-2 mt-sm-0">
									<button type="button" class="btn btn-sm btn-outline-secondary" id="sessionsPrevPageBtn">Prev</button>
									<span class="mx-2 text-muted small" id="sessionsPageInfo">Page 1 of 1</span>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="sessionsNextPageBtn">Next</button>
								</div>
							</div>
							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="activeSessionsTable">
									<thead>
										<tr>
											<th>User</th>
											<th>IP</th>
											<th>Device</th>
											<th>Last Activity</th>
											<th>Created</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="6" class="text-center text-muted">No active sessions found</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>Login Activity Logs</span>
							<div class="d-flex align-items-center">
								<select id="loginLogsStatusFilter" class="form-control form-control-sm mr-2" style="min-width: 160px;">
									<option value="all">All statuses</option>
									<option value="failed">Failed</option>
									<option value="blocked">Blocked</option>
									<option value="success">Success</option>
									<option value="success_auto">Success (Auto)</option>
									<option value="failed_auto">Failed (Auto)</option>
								</select>
								<button type="button" class="btn btn-sm btn-outline-secondary" id="refreshLoginLogsBtn">Refresh</button>
							</div>
						</div>
						<div class="card-body">
							<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
								<input type="text" id="loginLogsSearchInput" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search user, IP or device">
								<div class="d-flex align-items-center mt-2 mt-sm-0">
									<button type="button" class="btn btn-sm btn-outline-secondary" id="loginLogsPrevPageBtn">Prev</button>
									<span class="mx-2 text-muted small" id="loginLogsPageInfo">Page 1 of 1</span>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="loginLogsNextPageBtn">Next</button>
								</div>
							</div>
							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="loginLogsTable">
									<thead>
										<tr>
											<th>Time</th>
											<th>Status</th>
											<th>User</th>
											<th>IP</th>
											<th>Device</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="5" class="text-center text-muted">No login activity found</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
						</div>
						<div class="tab-pane fade settings-backup-section" id="settings-backups" role="tabpanel" aria-labelledby="settings-backups-tab" style="display:none;">

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section settings-backup-section" style="display:none;">
						<div class="card-header font-weight-bold d-flex justify-content-between align-items-center">
							<span>Data Backup & Restore</span>
							<div>
								<button type="button" class="btn btn-sm btn-outline-secondary mr-2" id="refreshBackupsBtn">Refresh</button>
								<button type="button" class="btn btn-sm btn-primary" id="createBackupBtn">Create Backup</button>
							</div>
						</div>
						<div class="card-body">
							<div class="alert alert-info py-2 mb-3" role="alert">
								Restoring a backup replaces the current database immediately.
							</div>
							<small class="text-muted d-block mb-1" id="backupPolicyNote">Automatic backups are managed by scheduler.</small>
							<small class="text-muted d-block mb-2" id="backupSchedulerNote">Scheduler command: php tasks/run_backup_scheduler.php</small>
							<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
								<input type="text" id="backupsSearchInput" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search by backup filename">
								<div class="d-flex align-items-center mt-2 mt-sm-0">
									<button type="button" class="btn btn-sm btn-outline-secondary" id="backupsPrevPageBtn">Prev</button>
									<span class="mx-2 text-muted small" id="backupsPageInfo">Page 1 of 1</span>
									<button type="button" class="btn btn-sm btn-outline-secondary" id="backupsNextPageBtn">Next</button>
								</div>
							</div>
							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="backupsTable">
									<thead>
										<tr>
											<th>Created</th>
											<th>File</th>
											<th>Size</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="4" class="text-center text-muted">No backups available</td></tr>
									</tbody>
								</table>
							</div>
							<div class="d-flex justify-content-between align-items-center mt-3 mb-2">
								<strong>Backup Audit</strong>
								<button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBackupAuditBtn">Refresh Audit</button>
							</div>
							<form id="restoreEncryptedBackupForm" class="border rounded p-3 mb-3">
								<div class="form-row">
									<div class="form-group col-md-6 mb-2">
										<label class="mb-1" for="encryptedBackupFile">Encrypted Backup File</label>
										<input type="file" id="encryptedBackupFile" name="encryptedBackupFile" class="form-control" accept=".enc" required>
									</div>
									<div class="form-group col-md-4 mb-2">
										<label class="mb-1" for="encryptedBackupPassphrase">Passphrase</label>
										<input type="password" id="encryptedBackupPassphrase" class="form-control" minlength="8" required>
									</div>
									<div class="form-group col-md-2 mb-2 d-flex align-items-end">
										<button type="submit" class="btn btn-outline-danger w-100" id="restoreEncryptedBackupBtn">Restore Encrypted</button>
									</div>
								</div>
							</form>
							<div class="table-responsive">
								<table class="table table-bordered table-striped" id="backupAuditTable">
									<thead>
										<tr>
											<th>Time</th>
											<th>Event</th>
											<th>File</th>
											<th>Actor</th>
											<th>IP</th>
										</tr>
									</thead>
									<tbody>
										<tr><td colspan="5" class="text-center text-muted">No backup audit entries yet</td></tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
						</div>
						<div class="tab-pane fade" id="settings-smtp" role="tabpanel" aria-labelledby="settings-smtp-tab">

					<div class="card shadow-sm settings-section-card mt-4">
						<div class="card-header font-weight-bold">SMTP Test Email</div>
						<div class="card-body">
							<form id="smtpTestEmailForm" class="form-inline">
								<div class="form-group mr-2 mb-2 flex-grow-1" style="min-width: 280px;">
									<label for="smtpTestEmail" class="sr-only">Email</label>
									<input type="email" id="smtpTestEmail" class="form-control w-100" placeholder="recipient@example.com (leave empty to use your account email)">
								</div>
								<button type="submit" class="btn btn-outline-primary mb-2" id="sendSmtpTestEmailBtn">Send Test Email</button>
							</form>
							<small class="text-muted">Uses configured SMTP settings when available, otherwise falls back to local mail transport.</small>
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
<script src="scripts/settings.js?v=<?= asset_ver('scripts/settings.js') ?>"></script>

</body>
</html>
