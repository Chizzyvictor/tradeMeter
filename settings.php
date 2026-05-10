<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<!-- SETTINGS PAGE LAYOUT -->
<div class="settings-app">

    <!-- SIDEBAR -->
    <aside class="settings-sidebar" id="settingsSidebar">

        <!-- TOP -->
        <div class="sidebar-top">

            <div class="company-box">
                <img src="Images/companyDP/logo.jpg" alt="Logo">

                <div class="company-info">
                    <h4>Chivicks Concept</h4>
                    <p>company@email.com</p>
                </div>
            </div>

            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>

        </div>

        <!-- MENU -->
        <div class="sidebar-menu">

            <p class="menu-title">GENERAL</p>

            <button class="menu-link active" data-tab="profileTab">
                <i class="fas fa-building"></i>
                <span>Company Profile</span>
            </button>

            <button class="menu-link" data-tab="smtpTab">
                <i class="fas fa-envelope"></i>
                <span>SMTP Settings</span>
            </button>

            <p class="menu-title">MANAGEMENT</p>

            <button class="menu-link" data-tab="usersTab">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </button>

            <button class="menu-link" data-tab="attendanceTab">
                <i class="fas fa-user-clock"></i>
                <span>Attendance</span>
            </button>

            <p class="menu-title">SECURITY</p>

            <button class="menu-link" data-tab="sessionsTab">
                <i class="fas fa-laptop"></i>
                <span>Sessions</span>
            </button>

            <button class="menu-link" data-tab="logsTab">
                <i class="fas fa-history"></i>
                <span>Login Logs</span>
            </button>

            <p class="menu-title">SYSTEM</p>

            <button class="menu-link" data-tab="backupTab">
                <i class="fas fa-database"></i>
                <span>Backups</span>
            </button>

        </div>

    </aside>

    <!-- MAIN -->
    <main class="settings-main">

        <!-- MOBILE TOPBAR -->
        <div class="mobile-topbar">

            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>

            <h3>Settings</h3>

        </div>

        <!-- HEADER -->
        <div class="main-header">

            <div>
                <h2 id="pageTitle">Company Profile</h2>
                <p>Manage company information and settings</p>
            </div>

        </div>

        <!-- CONTENT -->

        <!-- PROFILE -->
        <section class="settings-tab active" id="profileTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>Company Profile</h4>
                </div>

                <div class="card-body">

                    <form>

                        <div class="form-grid">

                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Company Email</label>
                                <input type="email" class="form-control">
                            </div>

                        </div>

                        <div class="form-group">
                            <label>Company Logo</label>
                            <input type="file" class="form-control">
                        </div>

                        <button class="btn-primary-custom">
                            Save Changes
                        </button>

                    </form>

                </div>

            </div>

        </section>

        <!-- USERS -->
        <section class="settings-tab" id="usersTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>User Management</h4>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="custom-table">

                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        No users found
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

        <!-- SMTP -->
        <section class="settings-tab" id="smtpTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>SMTP Test</h4>
                </div>

                <div class="card-body">

                    <form>

                        <div class="form-group">
                            <label>Test Email</label>
                            <input type="email" class="form-control">
                        </div>

                        <button class="btn-primary-custom">
                            Send Test Email
                        </button>

                    </form>

                </div>

            </div>

        </section>

        <!-- ATTENDANCE -->
        <section class="settings-tab" id="attendanceTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>Attendance Settings</h4>
                </div>

                <div class="card-body">

                    <form>

                        <div class="form-grid">

                            <div class="form-group">
                                <label>Resumption Time</label>
                                <input type="time" class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Late Fine (0-15 mins)</label>
                                <input type="number" class="form-control" step="0.01">
                            </div>

                        </div>

                        <button class="btn-primary-custom">
                            Save Settings
                        </button>

                    </form>

                </div>

            </div>

        </section>

        <!-- SESSIONS -->
        <section class="settings-tab" id="sessionsTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>Active Sessions</h4>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="custom-table">

                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Device</th>
                                    <th>IP</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        No sessions found
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

        <!-- LOGS -->
        <section class="settings-tab" id="logsTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>Login Logs</h4>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="custom-table">

                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>IP</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        No logs found
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

        <!-- BACKUPS -->
        <section class="settings-tab" id="backupTab">

            <div class="settings-card">

                <div class="card-header">
                    <h4>Backups</h4>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="custom-table">

                            <thead>
                                <tr>
                                    <th>Created</th>
                                    <th>File</th>
                                    <th>Size</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td colspan="3" class="empty-state">
                                        No backups found
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

<?php include "INC/footer.php"; ?>
<script src="scripts/settings.js?v=<?= asset_ver('scripts/settings.js') ?>"></script>

</body>
</html>
				