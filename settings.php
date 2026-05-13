<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>
<!-- SETTINGS APP -->
<div class="settings-container">

    <!-- SIDEBAR -->
    <aside class="settings-sidebar" id="sidebar">

        <div class="sidebar-header">

            <button class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </button>

            <div class="profile-box">

				<div class="profile-avatar" id="settingsSidebarAvatar">
                    CV
                    <button class="edit-avatar-btn">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>

				<h3 id="settingsSidebarName">-</h3>
				<p id="settingsSidebarRole">-</p>

            </div>

        </div>

        <div class="settings-menu">

            <p class="menu-label">My Company</p>

            <button class="settings-item active" data-tab="profileTab">
                <div class="item-left">
                    <i class="fas fa-building"></i>
                    <div>
                        <h4>Company Profile</h4>
                        <span>Manage company details</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="smtpTab">
                <div class="item-left">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>SMTP Settings</h4>
                        <span>Email configuration</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="usersTab">
                <div class="item-left">
                    <i class="fas fa-users"></i>
                    <div>
                        <h4>Users</h4>
                        <span>Manage staff accounts</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="attendanceTab">
                <div class="item-left">
                    <i class="fas fa-user-clock"></i>
                    <div>
                        <h4>Attendance Policy</h4>
                        <span>General rules & fines</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="attendanceAdminTab">
                <div class="item-left">
                    <i class="fas fa-tasks"></i>
                    <div>
                        <h4>Attendance Mgmt</h4>
                        <span>Absence, shifts & corrections</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <p class="menu-label">Security</p>

            <button class="settings-item" data-tab="sessionsTab">
                <div class="item-left">
                    <i class="fas fa-laptop"></i>
                    <div>
                        <h4>Sessions</h4>
                        <span>Logged in devices</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="logsTab">
                <div class="item-left">
                    <i class="fas fa-history"></i>
                    <div>
                        <h4>Login Logs</h4>
                        <span>Authentication activity</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

            <button class="settings-item" data-tab="backupTab">
                <div class="item-left">
                    <i class="fas fa-database"></i>
                    <div>
                        <h4>Backups</h4>
                        <span>Database backup & restore</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </button>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="settings-main">

        <!-- MOBILE HEADER -->
        <div class="mobile-header">

            <button id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <h3>Settings</h3>

        </div>

        <!-- PROFILE TAB -->
        <section class="tab-content active" id="profileTab">

            <div class="content-card">

                <div class="content-header">
                    <h2>Company Profile</h2>
                    <p>Manage company information</p>
                </div>

				<form id="companyProfileForm" enctype="multipart/form-data">

                    <div class="form-grid">

                        <div class="form-group">
                            <label>Company Name</label>
							<input type="text" class="form-control" id="companyName" name="cName" required>
                        </div>

                        <div class="form-group">
                            <label>Company Email</label>
							<input type="email" class="form-control" id="companyEmail" name="cEmail" required>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Company Logo</label>
						<input type="file" class="form-control" id="companyLogo" name="companyLogo" accept="image/*">
                    </div>

					<button type="submit" class="save-btn" id="saveCompanyProfileBtn">
                        Save Changes
                    </button>

                </form>

            </div>

        </section>

		<!-- SMTP SETTINGS TAB -->
		<section class="tab-content" id="smtpTab">

			<div class="content-card">

				<div class="content-header">
					<h2>SMTP Settings</h2>
					<p>Configure email server</p>
				</div>

				<form id="smtpSettingsForm">

					<div class="form-grid">

						<div class="form-group">
							<label>SMTP Host</label>
							<input type="text" class="form-control" id="smtpHost" disabled>
						</div>	
						<div class="form-group">
							<label>SMTP Port</label>
							<input type="number" class="form-control" id="smtpPort" disabled>
						</div>
						<div class="form-group">
							<label>SMTP Username</label>
							<input type="text" class="form-control" id="smtpUsername" disabled>
						</div>
						<div class="form-group">
							<label>SMTP Password</label>
							<input type="password" class="form-control" id="smtpPassword" disabled>
						</div>	
						<div class="form-group">
							<label>Encryption</label>
							<select class="form-control" id="smtpEncryption" disabled>
								<option value="">None</option>
								<option value="ssl">SSL</option>
								<option value="tls">TLS</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label>Send Test Email</label>
						<div class="d-flex flex-wrap">
							<input type="email" class="form-control mr-2 mb-2" id="smtpTestEmail" placeholder="recipient@example.com" style="max-width:320px;">
							<button type="submit" class="save-btn mb-2" id="sendSmtpTestEmailBtn">Send Test Email</button>
						</div>
						<small class="text-muted d-block" id="smtpSettingsInfo">SMTP settings are managed by server configuration in this build.</small>
					</div>
				</form>
			</div>
		</section>

		<!-- USERS TAB -->
		<section class="tab-content" id="usersTab">
			<div class="content-card">
				<div class="content-header">
					<h2>Users</h2>
					<p>Manage staff accounts</p>
				</div>
				<button type="button" class="add-user-btn" id="seedDemoUsersBtn">
					<i class="fas fa-user-plus"></i> Add New User
				</button>
				<div class="form-group mt-3" style="max-width: 340px;">
					<label>Search Users</label>
					<input type="text" class="form-control" id="usersSearchInput" placeholder="Search by name or email">
				</div>
				<div class="table-responsive">
					<table class="table settings-mobile-cards">
						<tbody id="usersTableBody">
							<tr>
								<td data-label="Name">John Doe</td>
								<td data-label="Email">john.doe@example.com</td>
								<td data-label="Role">Admin</td>
								<td data-label="Status">Active</td>
								<td data-label="Actions">
									<button class="edit-btn">
										<i class="fas fa-edit"></i> Edit
									</button>
									<button class="delete-btn">
										<i class="fas fa-trash"></i> Delete
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<!-- ATTENDANCE TAB -->
		<section class="tab-content" id="attendanceTab">
			<div class="content-card">
				<div class="content-header">
					<h2>Attendance</h2>
					<p>Employee attendance policy</p>
				</div>
				<form id="attendancePolicyForm">
					<div class="form-group">
						<label>Resumption Time</label>
						<input type="time" class="form-control" id="attendanceResumptionTime" required>
					</div>
					<div class="form-group">
						<label>0-15 mins late fine</label>
						<input type="number" class="form-control" id="attendanceFine0To15" min="0" step="0.01" required>
					</div>
					<div class="form-group">
						<label>15-60 mins late fine</label>
						<input type="number" class="form-control" id="attendanceFine15To60" min="0" step="0.01" required>
					</div>
					<div class="form-group">
						<label>1hr+ late fine</label>
						<input type="number" class="form-control" id="attendanceFine60Plus" min="0" step="0.01" required>
					</div>
					<button type="submit" class="save-btn" id="saveAttendancePolicyBtn">	
						Save Changes
					</button>
				</form>
			</div>
		</section>

        <!-- ATTENDANCE MGMT TAB -->
        <section class="tab-content" id="attendanceAdminTab">
            <div class="content-card">
                <div class="content-header">
                    <h2>Attendance Management</h2>
                    <p>Handle automated tasks, shifts, and data corrections</p>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="p-3 border rounded bg-light shadow-sm h-100">
                            <h5 class="font-weight-bold mb-2">Automated Operations</h5>
                            <p class="small text-muted">Run daily routines like marking absence for employees who didn't sign in.</p>
                            <button class="btn btn-warning btn-block font-weight-bold" id="runAutoAbsenceSettingsBtn">
                                <i class="fas fa-magic mr-1"></i> Run Auto-Absence Routine
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="p-3 border rounded bg-light shadow-sm h-100">
                            <h5 class="font-weight-bold mb-2">Data Integrity</h5>
                            <p class="small text-muted">Audit and cleanup duplicate or invalid attendance records.</p>
                            <button class="btn btn-outline-danger btn-block font-weight-bold" id="auditAttendanceDataBtn" disabled>
                                <i class="fas fa-vial mr-1"></i> Run Data Audit (Coming Soon)
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-5 border-0">
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0 font-weight-bold"><i class="fas fa-list mr-2 text-info"></i>Employee Registry</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light border-right-0"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" id="attendanceEmployeeSearchSettings" class="form-control border-left-0 bg-light" placeholder="Search by name, email, or role...">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-hover mb-0 settings-mobile-cards" id="attendanceEmployeesTableSettings">
								<tbody>
									<tr class="table-empty-row"><td colspan="8" class="text-center text-muted py-3">Loading registry...</td></tr>
								</tbody>
							</table>
						</div>
                    </div>
                    <div class="card-footer bg-white text-right py-2">
                        <small class="text-muted font-italic" id="attendanceSearchSummarySettings">Showing all employees</small>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 font-weight-bold text-primary">Pending Correction Requests</h6>
                        <select id="attendanceCorrectionStatusSettings" class="form-control form-control-sm w-auto">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="card-body p-0">
						<div class="table-responsive">
							<table class="table mb-0 settings-mobile-cards" id="attendanceCorrectionsTableSettings">
								<tbody class="small">
									<tr class="table-empty-row"><td colspan="6" class="text-center text-muted py-3">Loading requests...</td></tr>
								</tbody>
							</table>
						</div>
                    </div>
                </div>
            </div>
        </section>

		<!-- SESSIONS TAB -->
		<section class="tab-content" id="sessionsTab">
			<div class="content-card">
				<div class="content-header">
					<h2>Sessions</h2>
					<p>Logged in devices</p>
				</div>
				<div class="d-flex flex-wrap mb-2">
					<input type="text" class="form-control mr-2 mb-2" id="sessionsSearchInput" placeholder="Search user, IP or device" style="max-width:320px;">
					<button type="button" class="backup-btn-secondary mb-2" id="refreshSessionsBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
				</div>
				<div class="table-responsive">
					<table class="table settings-mobile-cards">
						<tbody id="sessionsTableBody">
							<tr>
								<td data-label="Device">Chrome on Windows</td>
								<td data-label="IP Address">192.168.1.1</td>
								<td data-label="Last Active">2024-06-01 12:34:56</td>
								<td data-label="Actions">
									<button class="logout-btn">
										<i class="fas fa-sign-out-alt"></i> Logout
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>	

		<!-- LOGIN LOGS TAB -->
		<section class="tab-content" id="logsTab">
			<div class="content-card">
				<div class="content-header">
					<h2>Login Logs</h2>
					<p>Authentication activity</p>
				</div>
				<div class="d-flex flex-wrap mb-2">
					<select class="form-control mr-2 mb-2" id="loginLogsStatusFilter" style="max-width:220px;">
						<option value="all">All statuses</option>
						<option value="failed">Failed</option>
						<option value="blocked">Blocked</option>
						<option value="success">Success</option>
						<option value="success_auto">Success (Auto)</option>
						<option value="failed_auto">Failed (Auto)</option>
					</select>
					<input type="text" class="form-control mr-2 mb-2" id="loginLogsSearchInput" placeholder="Search user, IP or device" style="max-width:320px;">
					<button type="button" class="backup-btn-secondary mb-2" id="refreshLoginLogsBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
				</div>
				<div class="table-responsive">
					<table class="table settings-mobile-cards">
						<tbody id="loginLogsTableBody">
							<tr class="table-empty-row">
								<td colspan="4" data-label="Status" class="text-muted">Loading login logs...</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<!-- BACKUP TAB -->
		<section class="tab-content" id="backupTab">
			<div class="content-card">
				<div class="content-header">
					<h2>Backups</h2>
					<p>Tenant JSON backup, encrypted export, and restore</p>
				</div>

				<div class="backup-status" id="backupCapabilityNotice">
					<div class="backup-status-icon">
						<i class="fas fa-shield-alt"></i>
					</div>
					<div class="backup-status-body">
						<h4>Backup Capability</h4>
						<p id="backupCapabilityText">Checking backup support...</p>
					</div>
				</div>

				<div class="backup-grid">
					<div class="backup-card">
						<h5>Create And Export</h5>
						<p>Generate a fresh tenant backup and download as JSON.</p>
						<div class="backup-actions">
							<button type="button" class="save-btn" id="createBackupBtn">
								<i class="fas fa-plus-circle"></i> Create Backup
							</button>
							<button type="button" class="backup-btn-secondary" id="refreshBackupListBtn">
								<i class="fas fa-sync-alt"></i> Refresh List
							</button>
						</div>
					</div>

					<div class="backup-card">
						<h5>Restore From JSON</h5>
						<p>Upload a .json tenant backup file and restore company data.</p>
						<div class="backup-form-row">
							<input type="file" class="form-control" id="restoreBackupFileInput" accept=".json,application/json">
							<button type="button" class="backup-btn-secondary" id="restoreBackupUploadBtn">
								<i class="fas fa-upload"></i> Restore JSON
							</button>
						</div>
					</div>

					<div class="backup-card">
						<h5>Restore Encrypted Backup</h5>
						<p>Upload a .json.enc file and provide passphrase to restore.</p>
						<div class="backup-form-row">
							<input type="file" class="form-control" id="restoreEncryptedBackupFileInput" accept=".enc,.json.enc,application/octet-stream">
						</div>
						<div class="backup-form-row">
							<input type="password" class="form-control" id="restoreEncryptedPassphrase" placeholder="Enter passphrase">
							<button type="button" class="backup-btn-secondary" id="restoreEncryptedBackupBtn">
								<i class="fas fa-key"></i> Restore Encrypted
							</button>
						</div>
					</div>
				</div>

				<div class="backup-list-wrap">
					<div class="backup-list-head">
						<h5>Available Backups</h5>
						<span id="backupListMeta">0 files</span>
					</div>
					<div class="d-flex flex-wrap mb-2">
						<input type="text" class="form-control mr-2 mb-2" id="backupsSearchInput" placeholder="Search backup filename" style="max-width:320px;">
					</div>
					<div class="table-responsive">
						<table class="table backup-table settings-mobile-cards" id="backupTable">

							<tbody id="backupListBody">
								<tr class="table-empty-row">
									<td colspan="5" class="backup-empty">No backups loaded yet.</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</section>

		<!-- CREATE USER MODAL -->
		<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content shadow rounded">
					<form id="createUserForm">
						<div class="modal-header bg-primary text-white">
							<h5 class="modal-title" id="createUserModalLabel">
								<i class="fas fa-user-plus mr-2"></i>Add User
							</h5>
							<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close add user modal">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="createUserFullName">Full Name</label>
								<input type="text" class="form-control" id="createUserFullName" placeholder="Enter full name" required>
							</div>
							<div class="form-group">
								<label for="createUserEmail">Email</label>
								<input type="email" class="form-control" id="createUserEmail" placeholder="Enter email" required>
							</div>
							<div class="form-group">
								<label for="createUserPassword">Password</label>
								<input type="password" class="form-control" id="createUserPassword" placeholder="Min 8 chars, uppercase, lowercase, number, special" required>
							</div>
							<div class="form-group">
								<label for="createUserRole">Role</label>
								<select class="form-control" id="createUserRole" required>
									<option value="">Select role</option>
								</select>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
							<button type="submit" class="btn btn-primary" id="createUserSubmitBtn">Create User</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- EDIT USER MODAL -->
		<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content shadow rounded">
					<form id="editUserForm">
						<input type="hidden" id="editUserId" value="">
						<div class="modal-header bg-primary text-white">
							<h5 class="modal-title" id="editUserModalLabel">
								<i class="fas fa-user-edit mr-2"></i>Edit User
							</h5>
							<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close edit user modal">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="editUserFullName">Full Name</label>
								<input type="text" class="form-control" id="editUserFullName" placeholder="Enter full name" required>
							</div>
							<div class="form-group">
								<label for="editUserEmail">Email</label>
								<input type="email" class="form-control" id="editUserEmail" placeholder="Enter email" required>
							</div>
							<div class="form-group">
								<label for="editUserPassword">Password</label>
								<input type="password" class="form-control" id="editUserPassword" placeholder="Leave blank to keep current password">
								<small class="form-text text-muted">Only enter a value if you want to reset the password.</small>
							</div>
							<div class="form-group">
								<label for="editUserRole">Role</label>
								<select class="form-control" id="editUserRole" required>
									<option value="">Select role</option>
								</select>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
							<button type="submit" class="btn btn-primary" id="editUserSubmitBtn">Update User</button>
						</div>
					</form>
				</div>
			</div>
		</div>



    </main>

</div>
<?php include "INC/footer.php"; ?>
<script src="scripts/settings.js?v=<?= asset_ver('scripts/settings.js') ?>"></script>

</body>
</html>
				