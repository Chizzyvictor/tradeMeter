<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="content">
<div class="container-fluid">

<div class="tab-content dashboard-home" id="home">

<div class="dashboard-hero panel-box mt-3 mb-4">
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center dashboard-filter-wrap">
    <div class="mb-3 mb-md-0 dashboard-hero-copy">
        <span class="dashboard-hero-kicker">TradeMeter Command Center</span>
        <h3 class="mb-2 dashboard-hero-title">Performance Overview</h3>
        <p class="mb-2 text-muted dashboard-hero-text">Stay on top of revenue, stock value, debt exposure, and your strongest trading relationships from one premium dashboard.</p>
        <div class="dashboard-context-list">
            <span id="dashboardUserContext" class="dashboard-context-pill">User: - | Role: -</span>
            <span id="dashboardRangeLabel" class="dashboard-context-pill">Showing: All Time</span>
            <span id="dashboardLastUpdated" class="dashboard-context-pill">Last updated: --</span>
        </div>
    </div>
    <div class="dashboard-controls">
        <div class="dashboard-range-control">
            <label for="dashboardRangeFilter" class="mb-1 d-block text-muted">Date Range</label>
            <select id="dashboardRangeFilter" class="form-control form-control-sm">
                <option value="today">Today</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="all" selected>All Time</option>
            </select>
        </div>
        <div class="custom-control custom-switch dashboard-refresh-switch mt-2 mt-md-0">
            <input type="checkbox" class="custom-control-input" id="dashboardAutoRefreshToggle" checked>
            <label class="custom-control-label text-muted" for="dashboardAutoRefreshToggle">Auto-refresh (60s)</label>
        </div>
    </div>
</div>
</div>

<!-- SUMMARY CARDS -->
<div class="row g-4 my-4 dashboard-stats-grid">

    <!-- Total Outstanding -->
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-danger shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Total Outstanding</p>
                    <h4 id="loadOutstanding" class="counter dashboard-stat-value text-danger">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-danger" id="trendOutstanding">Needs attention</span>
                </div>
                <div class="dashboard-icon dashboard-icon-danger">
                    <i class="fas fa-exclamation-circle fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Advance Payments -->
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-primary shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Advance Payments</p>
                    <h4 id="loadAdvancePayments" class="counter dashboard-stat-value text-primary">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-primary" id="trendAdvancePayments">Cash buffer</span>
                </div>
                <div class="dashboard-icon dashboard-icon-primary">
                    <i class="fas fa-wallet fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Debtors -->
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-warning shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Active Debtors</p>
                    <h4 id="loadActiveDebtors" class="counter dashboard-stat-value text-warning">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-warning" id="trendActiveDebtors">Collections watch</span>
                </div>
                <div class="dashboard-icon dashboard-icon-warning">
                    <i class="fas fa-user-clock fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Creditors -->
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-success shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Active Creditors</p>
                    <h4 id="loadActiveCreditors" class="counter dashboard-stat-value text-success">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-success" id="trendActiveCreditors">Supplier trust</span>
                </div>
                <div class="dashboard-icon dashboard-icon-success">
                    <i class="fas fa-hand-holding-usd fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- EXTRA KPI CARDS -->
<div class="row g-4 mb-4 dashboard-stats-grid">
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-success shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Total Sales</p>
                    <h4 id="loadTotalSales" class="counter dashboard-stat-value text-success">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-success" id="trendTotalSales">Loading trend</span>
                </div>
                <div class="dashboard-icon dashboard-icon-success">
                    <i class="fas fa-chart-line fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-info shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Total Purchases</p>
                    <h4 id="loadTotalPurchases" class="counter dashboard-stat-value text-info">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-info" id="trendTotalPurchases">Loading trend</span>
                </div>
                <div class="dashboard-icon dashboard-icon-info">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-primary shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Transactions (Range)</p>
                    <h4 id="loadTodayTransactions" class="counter dashboard-stat-value text-primary">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-primary" id="trendRangeTransactions">Loading trend</span>
                </div>
                <div class="dashboard-icon dashboard-icon-primary">
                    <i class="fas fa-calendar-day fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-slate shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Inventory Value</p>
                    <h4 id="loadInventoryValue" class="counter dashboard-stat-value text-dark">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-slate" id="trendInventoryValue">Warehouse value</span>
                </div>
                <div class="dashboard-icon dashboard-icon-slate">
                    <i class="fas fa-boxes fa-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-card dashboard-stat-card dashboard-stat-card-success shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="dashboard-stat-label text-muted mb-1">Profit</p>
                    <h4 id="loadProfit" class="counter dashboard-stat-value text-success">Loading...</h4>
                    <span class="dashboard-trend-badge dashboard-trend-badge-success" id="trendProfit">Loading trend</span>
                </div>
                <div class="dashboard-icon dashboard-icon-success" id="loadProfitIconWrap">
                    <i class="fas fa-coins fa-lg"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TOP METRICS TABLES -->
<div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6">
        <div class="card dashboard-card dashboard-section-card shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pb-0 dashboard-section-head">
                <div>
                    <span class="dashboard-section-kicker">High Performers</span>
                    <h5 class="mb-0">Top Selling Products</h5>
                </div>
                <span class="dashboard-section-icon dashboard-icon-success"><i class="fas fa-fire"></i></span>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive dashboard-table-wrap">
                    <table class="table table-sm mb-0 dashboard-mini-table" id="topSellingProductsTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="card dashboard-card dashboard-section-card shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pb-0 dashboard-section-head">
                <div>
                    <span class="dashboard-section-kicker">Supply Network</span>
                    <h5 class="mb-0">Top Suppliers</h5>
                </div>
                <span class="dashboard-section-icon dashboard-icon-info"><i class="fas fa-truck-loading"></i></span>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive dashboard-table-wrap">
                    <table class="table table-sm mb-0 dashboard-mini-table" id="topSuppliersTable">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th class="text-right">Txn</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-12">
        <div class="card dashboard-card dashboard-section-card shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pb-0 dashboard-section-head">
                <div>
                    <span class="dashboard-section-kicker">Customer Momentum</span>
                    <h5 class="mb-0">Top Buyers</h5>
                </div>
                <span class="dashboard-section-icon dashboard-icon-primary"><i class="fas fa-user-friends"></i></span>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive dashboard-table-wrap">
                    <table class="table table-sm mb-0 dashboard-mini-table" id="topBuyersTable">
                        <thead>
                            <tr>
                                <th>Buyer</th>
                                <th class="text-right">Txn</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- tab-content -->

</div>
</div>

<?php 
include "INC/footer.php";
?>
<style>

</style>
<script>
$(document).ready(function () {

    const csrf_token = $('meta[name="csrf-token"]').attr('content');

    const appCoreInstance = new AppCore(csrf_token);

    const dashboardApp = new Dashboard(appCoreInstance);

    const storageKey = "dashboardRangeFilter";
    const autoRefreshStorageKey = "dashboardAutoRefreshEnabled";
    const $rangeFilter = $("#dashboardRangeFilter");
    const $autoRefreshToggle = $("#dashboardAutoRefreshToggle");
    const allowedRanges = ["today", "7d", "30d", "all"];
    const savedRange = localStorage.getItem(storageKey);
    const savedAutoRefresh = localStorage.getItem(autoRefreshStorageKey);
    const selectedRange = allowedRanges.includes(savedRange) ? savedRange : ($rangeFilter.val() || "all");
    const autoRefreshEnabled = savedAutoRefresh === null ? true : savedAutoRefresh === "true";

    $rangeFilter.val(selectedRange);
    $autoRefreshToggle.prop("checked", autoRefreshEnabled);

    const refreshDashboard = () => {
        dashboardApp.loadDashboard($rangeFilter.val() || "all");
    };

    let dashboardAutoRefreshId = null;

    const setAutoRefresh = (enabled) => {
        if (dashboardAutoRefreshId) {
            clearInterval(dashboardAutoRefreshId);
            dashboardAutoRefreshId = null;
        }

        if (enabled) {
            dashboardAutoRefreshId = setInterval(() => {
                refreshDashboard();
            }, 60000);
        }
    };

    appCoreInstance.loadUserPermissions(() => {
        if (!appCoreInstance.hasPermission("view_reports")) {
            $(".content-body").html(`
                <div class="container-fluid">
                    <div class="alert alert-warning mb-0">You do not have permission to view dashboard reports.</div>
                </div>
            `);
            return;
        }

        refreshDashboard();
        setAutoRefresh(autoRefreshEnabled);
    });

    $rangeFilter.on("change", function () {
        const range = $(this).val() || "all";
        localStorage.setItem(storageKey, range);
        refreshDashboard();
    });

    $autoRefreshToggle.on("change", function () {
        const enabled = $(this).is(":checked");
        localStorage.setItem(autoRefreshStorageKey, String(enabled));
        setAutoRefresh(enabled);
    });

    $(window).on("beforeunload", function () {
        if (dashboardAutoRefreshId) {
            clearInterval(dashboardAutoRefreshId);
            dashboardAutoRefreshId = null;
        }
    });

});
</script>

</body>
</html>
