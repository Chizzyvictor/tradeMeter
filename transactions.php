<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>



                 
<div class="content">

  <!-- PAGE HEADER -->
  <div class="content-header">
    <div class="container-fluid">

      <div class="row mb-2">

        <div class="col-sm-6">
          <h1 class="m-0">Transactions</h1>
        </div>

        <div class="col-sm-6">
          <div class="float-sm-right transactions-page-actions">

            <button class="btn btn-outline-secondary mr-2" id="viewTransactionsBtn">
              View Transactions
            </button>

            <button class="btn btn-primary" id="addTransactionBtn">
              Add Transaction
            </button>

          </div>
        </div>

      </div>
    </div>
  </div>



  <!-- PAGE BODY -->
  <div class="content-body">

    <div class="container-fluid">

      <div class="row mb-4 tab-contents" id="addTransactionTab">
        <!-- LEFT SIDE : ADD TRANSACTION -->
        <div class="col-lg-5 mb-4">

          <div class="card shadow-sm transactions-section-card">

            <div class="card-header font-weight-bold">
              Add New Transaction
            </div>

            <div class="card-body">

              <form id="addTransactionForm">

                <input type="hidden" id="transaction_id" name="transaction_id">

                <div class="mb-3">
                  <label>Transaction Type</label>

                  <select id="transactionType" name="transactionType" class="form-control" required></select>
                </div>


                <div class="mb-3 position-relative">

                  <label>Partner Name</label>

                  <input
                    type="text"
                    id="partner_name"
                    name="partner_name"
                    class="form-control"
                    placeholder="Enter partner name"
                    autocomplete="off"
                    required
                  >

                  <div
                    id="partnerSuggestions"
                    class="list-group position-absolute w-100"
                    style="z-index:1000;" ></div>

                  <input type="hidden" id="partnerId" name="partner_id">

                </div>


                <div class="mb-3">
                  <label>Transaction Date</label>

                  <input
                    type="date"
                    id="transactionDate"
                    name="transactionDate"
                    class="form-control"
                    required
                  >
                </div>

              </form>

            </div>
          </div>
        </div>



        <!-- RIGHT SIDE : ITEMS -->
        <div class="col-lg-7 mb-4">

          <div class="card shadow-sm transactions-section-card">

            <div class="card-header d-flex justify-content-between align-items-center">

              <strong>Transaction Items</strong>

              <button
                id="addItemBtn"
                class="btn btn-primary"
                type="button"
                data-toggle="modal"
                data-target="#addItemsModal"
              >
                Add Item
              </button>

            </div>



            <div class="table-responsive-sm transactions-items-table-wrap">

              <table
                id="transactionItemsTable"
                class="table table-striped table-hover table-bordered"
              >

                <thead class="thead-light">
                  <tr>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Description</th>
                    <th>Rate</th>
                    <th>Amount</th>
                    <th width="160">Actions</th>
                  </tr>
                </thead>

                <tbody></tbody>

              </table>

            </div>
            <div id="transactionItemsCards" class="transactions-items-cards" aria-live="polite"></div>
            <div id="transactionItemsTable_err" class="text-danger small mt-2" style="display:none;"></div>


            <!-- TOTAL SECTION -->
            <div class="card-body">

              <div class="row">

                <div class="col-md-4">
                  <label>Total Amount</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">₦</span>
                    </div>
                    <input
                      type="number"
                      id="totalAmount"
                      class="form-control"
                      readonly
                    >
                  </div>
                </div>

                <div class="col-md-4">
                  <label>Paying</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">₦</span>
                    </div>
                    <input
                      type="number"
                      id="paying"
                      class="form-control"
                    >
                  </div>
                </div>

                <div class="col-md-4">
                  <label>Balance</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">₦</span>
                    </div>
                    <input
                      type="number"
                      id="remaining"
                      class="form-control"
                      readonly
                    >
                  </div>
                </div>

              </div>


              <button
                type="button"
                id="savePurchaseBtn"
                class="btn btn-success btn-lg btn-block mt-3"
                disabled
              >
                PROCESS TRANSACTION
              </button>

            </div>

            <div id="mobileTransactionSummary" class="transactions-mobile-summary d-md-none" aria-live="polite">
              <div class="transactions-mobile-summary-grid">
                <div class="transactions-mobile-summary-item">
                  <small>Total</small>
                  <strong id="mobileTotalAmount">₦0.00</strong>
                </div>
                <div class="transactions-mobile-summary-item">
                  <small>Paying</small>
                  <strong id="mobilePayingAmount">₦0.00</strong>
                </div>
                <div class="transactions-mobile-summary-item">
                  <small>Balance</small>
                  <strong id="mobileBalanceAmount">₦0.00</strong>
                </div>
              </div>
            </div>

          </div>
        </div>
        </div>

      </div>
      <!-- END OF ROW -->




      <!-- TRANSACTION HISTORY TABLE -->

      <div class="card shadow-sm mt-4 tab-contents transactions-section-card" id="transactionsHistoryTab">

        <div class="card-header font-weight-bold">
          Transaction History
        </div>


<!-- Filters -->
<div class="row mb-3 transactions-history-filters">
  <div class="col-md-4">
    <input type="text" id="searchPartner" class="form-control" placeholder="Search by partner...">
  </div>
  <div class="col-md-3">
    <select id="filterStatus" class="form-control">
      <option value="">All Types</option>
      <option value="sell">Sell</option>
      <option value="buy">Buy</option>
    </select>
  </div>
  <div class="col-md-2">
    <input type="date" id="filterFrom" class="form-control">
  </div>
  <div class="col-md-2">
    <input type="date" id="filterTo" class="form-control">
  </div>
  <div class="col-md-1">
    <button id="resetFilters" class="btn btn-secondary w-100">Reset</button>
  </div>
</div>


        <div class="table-responsive-sm transactions-history-table-wrap">

          <table
            id="transactionsHistoryTable"
            class="table table-striped table-bordered table-hover"
          >

            <thead class="thead-dark">

              <tr>
                <th>ID</th>
                <th>Partner</th>
                <th>Type</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Date</th>
                <th width="180">Actions</th>
              </tr>

            </thead>

            <tbody>

              <tr>
                <td colspan="8" class="text-center text-muted p-4">
                  <i class="fa fa-receipt fa-2x mb-2"></i><br>
                  No transactions recorded yet
                </td>
              </tr>

            </tbody>

          </table>

        </div>
        <div id="transactionsHistoryCards" class="transactions-history-cards" aria-live="polite"></div>

        <div id="transactionsLoader" class="text-center p-3 d-none">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
          </div>
        </div>

      </div>

    </div>

  </div>

</div>




<!-- ============================
     ADD ITEM MODAL
============================= -->
<div class="modal fade" id="addItemsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="fa fa-plus-circle mr-2"></i>Add Item
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <form id="addItemsForm">
        <div class="modal-body">

          <div class="form-group position-relative">
            <label>Product Name</label>
            <input type="text" id="purchaseProductInput" class="form-control" required>
            <div id="productSuggestions" class="list-group position-absolute w-100" style="z-index:1050;"></div>
            <input type="hidden" id="purchaseProduct_id">
          </div>

          <div class="form-group">
            <label>Unit</label>
            <select id="productUnitSelect" class="form-control" required></select>
          </div>

          <div class="form-group">
            <label>Qty</label>
            <input type="number" id="qty" class="form-control" value="1">
          </div>

          <div class="form-group">
            <label>Rate</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">₦</span>
              </div>
              <input type="number" id="rate" class="form-control">
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-plus mr-1"></i>Add Item
          </button>
        </div>
      </form>

    </div>
  </div>
</div>




<?php include "INC/footer.php"; ?>

<!-- Transactions modules docs: scripts/transactions/README.md -->
<script src="scripts/transactions/transaction-manager.js?v=<?= asset_ver('scripts/transactions/transaction-manager.js') ?>"></script>
<script src="scripts/transactions/autocomplete.js?v=<?= asset_ver('scripts/transactions/autocomplete.js') ?>"></script>
<script src="scripts/transactions/items.js?v=<?= asset_ver('scripts/transactions/items.js') ?>"></script>
<script src="scripts/transactions/transactions-api.js?v=<?= asset_ver('scripts/transactions/transactions-api.js') ?>"></script>
<script src="scripts/transactions/history.js?v=<?= asset_ver('scripts/transactions/history.js') ?>"></script>
<script src="scripts/transactions/bootstrap.js?v=<?= asset_ver('scripts/transactions/bootstrap.js') ?>"></script>

</body>
</html>
