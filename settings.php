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

					<div class="card shadow-sm mb-4 settings-section-card">
						<div class="card-header font-weight-bold">Security Question & Answer</div>
						<div class="card-body">
							<form id="securityForm">
								<div class="form-group">
									<label for="securityQuestion">Security Question</label>
									<input type="text" id="securityQuestion" name="question" class="form-control" required>
								</div>
								<div class="form-group">
									<label for="securityAnswer">Security Answer</label>
									<input type="text" id="securityAnswer" name="answer" class="form-control" required>
								</div>
								<div class="settings-form-actions">
									<button type="submit" class="btn btn-info" id="saveSecurityBtn">Update Security</button>
								</div>
							</form>
						</div>
					</div>

					<div class="card shadow-sm settings-section-card">
						<div class="card-header font-weight-bold">Change Password</div>
						<div class="card-body">
							<form id="passwordForm">
								<div class="form-row">
									<div class="form-group col-md-4">
										<label for="currentPassword">Current Password</label>
										<input type="password" id="currentPassword" name="currentPassword" class="form-control" required>
									</div>
									<div class="form-group col-md-4">
										<label for="newPassword">New Password</label>
										<input type="password" id="newPassword" name="newPassword" class="form-control" required>
									</div>
									<div class="form-group col-md-4">
										<label for="confirmPassword">Confirm Password</label>
										<input type="password" id="confirmPassword" name="confirmPassword" class="form-control" required>
									</div>
								</div>
								<div class="settings-form-actions">
									<button type="submit" class="btn btn-warning" id="changePasswordBtn">Change Password</button>
								</div>
							</form>
						</div>
					</div>

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section settings-backup-section">
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
										<input type="password" id="newUserPassword" class="form-control" required>
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
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

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
									<tbody></tbody>
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
									<tbody></tbody>
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
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="card shadow-sm settings-section-card mt-4 settings-admin-section">
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
									<tbody></tbody>
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
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

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

<?php include "INC/footer.php"; ?>
<script src="scripts/settings.js?v=<?= asset_ver('scripts/settings.js') ?>"></script>

</body>
</html>
