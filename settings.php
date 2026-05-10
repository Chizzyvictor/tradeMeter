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

                <div class="profile-avatar">
                    CV
                    <button class="edit-avatar-btn">
                        <i class="fas fa-pen"></i>
                    </button>
                </div>

                <h3>Chidiogo Victor</h3>
                <p>Administrator</p>

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
                        <h4>Attendance</h4>
                        <span>Employee attendance policy</span>
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

                    <button class="save-btn">
                        Save Changes
                    </button>

                </form>

            </div>

        </section>

    </main>

</div>
<?php include "INC/footer.php"; ?>
<script src="scripts/settings.js?v=<?= asset_ver('scripts/settings.js') ?>"></script>

</body>
</html>
				