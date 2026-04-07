
<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMenu" 
                aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <a class="navbar-brand font-weight-bold <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="index.php">DASHBOARD</a>

        <div class="collapse navbar-collapse" id="navbarMenu">
            <ul class="navbar-nav mr-auto mt-2 mt-lg-0">
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'partners.php' ? 'active' : ''; ?>" href="partners.php">Partners</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">Transactions</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">Inventory</a></li>
                <li class="nav-item" id="attendanceNavItem"><a class="nav-link <?php echo $currentPage === 'employees_attendance.php' ? 'active' : ''; ?>" href="employees_attendance.php">Attendance</a></li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'user_profile.php' ? 'active' : ''; ?>" href="user_profile.php">
                        My Profile
                        <span class="badge badge-light ml-1" id="globalMessageUnreadBadge">0</span>
                    </a>
                </li>
                <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php">Settings</a></li>
                <li class="nav-item d-flex align-items-center ml-2">
                    <span id="currentUserRoleBadge" class="badge badge-info">Role: -</span>
                </li>
                <li class="nav-item"><a class="nav-link text-danger" href="#" id="logout">Logout</a></li>
            </ul>
        </div>
    </nav>
