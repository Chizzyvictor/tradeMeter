<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="content">
  <div class="content-header">
    <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-12 col-sm-6 mb-2 mb-sm-0">
                    <h1 class="m-0">Inventory</h1>
        </div>
                <div class="col-12 col-sm-6">
                    <ol class="breadcrumb float-sm-right inventory-action-list mb-0">
            <li class="breadcrumb-item">
                <button class="btn btn-outline-secondary" id="viewStockMovementBtn">View Stock Movement</button>
            </li>
            <li class="breadcrumb-item">
                <button class="btn btn-primary" id="newSaleBtn">&#128176; New Sale</button>
            </li>
            <li class="breadcrumb-item">
                <button class="btn btn-success" id="newPurchaseBtn">&#10133; New Purchase</button>
            </li>
            <li class="breadcrumb-item">
                <button class="btn btn-warning" id="viewReorderBtn">&#128722; Reorder Suggestions</button>
            </li>
            <li class="breadcrumb-item">
              <button class="btn btn-outline-dark" id="viewStockTakingBtn">Stock Taking</button>
            </li>
            <li class="breadcrumb-item">
                <button class="btn btn-outline-primary" id="addCategoryBtn">Add Category<span class="badge badge-primary badge-pill ml-2" id="badgeCategories">0</span></button>
            </li>
            <li class="breadcrumb-item active">
                <button class="btn btn-outline-primary" id="addProductBtn">Add Product<span class="badge badge-primary badge-pill ml-2" id="badgeProducts">0</span></button>
           </li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  <div class="content-body">

    <div class="row mb-4 inventory-stats-grid">
      <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card shadow-sm inventory-stat-card inventory-stat-card-primary h-100">
          <div class="card-body d-flex align-items-center">
            <div class="inventory-stat-icon inventory-stat-icon-primary">
              <i class="fas fa-layer-group"></i>
            </div>
            <div>
              <h6 class="inventory-stat-label">Categories</h6>
              <h3 class="inventory-stat-value" id="statCategories">0</h3>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card shadow-sm inventory-stat-card inventory-stat-card-success h-100">
          <div class="card-body d-flex align-items-center">
            <div class="inventory-stat-icon inventory-stat-icon-success">
              <i class="fas fa-boxes"></i>
            </div>
            <div>
              <h6 class="inventory-stat-label">Products</h6>
              <h3 class="inventory-stat-value" id="statProducts">0</h3>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card shadow-sm inventory-stat-card inventory-stat-card-warning h-100">
          <div class="card-body d-flex align-items-center">
            <div class="inventory-stat-icon inventory-stat-icon-warning">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
              <h6 class="inventory-stat-label">Low Stock</h6>
              <h3 class="inventory-stat-value" id="lowStockCount">0</h3>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card shadow-sm inventory-stat-card inventory-stat-card-slate h-100">
          <div class="card-body d-flex align-items-center">
            <div class="inventory-stat-icon inventory-stat-icon-slate">
              <i class="fas fa-cubes"></i>
            </div>
            <div>
              <h6 class="inventory-stat-label">Inventory Items</h6>
              <h3 class="inventory-stat-value" id="inventoryCount">0</h3>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card shadow-sm inventory-stat-card inventory-stat-card-info h-100">
          <div class="card-body d-flex align-items-center">
            <div class="inventory-stat-icon inventory-stat-icon-info">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div>
              <h6 class="inventory-stat-label">Inventory Value</h6>
              <h3 class="inventory-stat-value" id="inventoryValue">0</h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="container-fluid px-0">
      <div id="lowStockAlert" class="alert alert-warning shadow-sm fade show inventory-alert-card align-items-center" style="display:none;" role="alert">
        <div class="inventory-alert-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="inventory-alert-copy">
          <strong>Stock Prediction Alert!</strong>
          <div class="text-muted small mb-1">The following products may finish soon:</div>
          <ul id="lowStockList" class="mb-0"></ul>
        </div>
        <button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    </div>
    </div>



<div class="tab-content" id="stockMovementTab">
    <div class="card p-3 inventory-section-card">
        <div class="d-flex justify-content-between align-items-center mb-4 inventory-section-head">
            <button class="btn btn-secondary" id="backToCategoriesFromMovementBtn">Back to Categories</button>
            <h3 class="mb-0">Stock Movement</h3>
        </div>
        <div class="table-responsive inventory-table-wrap inventory-stock-movement-table-wrap">
            <table class="table table-hover table-striped table-bordered" id="stockMovementTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Quantity In</th>
                        <th>Quantity Out</th>
                        <th>Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Stock movement records will be dynamically loaded here -->
                </tbody>
            </table>
        </div>
        <div id="stockMovementCards" class="inventory-stock-movement-cards" aria-live="polite">
            <!-- Mobile stock movement cards will be dynamically loaded here -->
        </div>
    </div>
</div>

<div class="tab-content" id="reorderTab">
    <div class="card p-3 inventory-section-card">
        <div class="d-flex justify-content-between align-items-center mb-4 inventory-section-head">
            <button class="btn btn-secondary" id="backToHomeFromReorder">Back</button>
            <h3 class="mb-0">Reorder Suggestions</h3>
        </div>
        <div class="table-responsive inventory-table-wrap">
            <table class="table table-hover table-striped table-bordered" id="reorderSuggestionsTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th>Current Stock</th>
                        <th>Days Left</th>
                        <th>Suggested Qty</th>
                    </tr>
                </thead>
                <tbody id="reorderTableBody">
                    <!-- Reorder suggestions will be dynamically loaded here -->
                </tbody>
            </table>
        </div>
        <div id="reorderSuggestionsCards" class="inventory-products-cards" aria-live="polite">
            <!-- Mobile reorder suggestions will be dynamically loaded here -->
        </div>
    </div>
</div>

  <div class="tab-content" id="stockTakingTab">
    <div class="card p-3 inventory-section-card">
      <div class="d-flex justify-content-between align-items-center mb-4 inventory-section-head">
        <button class="btn btn-secondary" id="backToHomeFromStockTaking">Back</button>
        <h3 class="mb-0">Stock Taking</h3>
      </div>

      <div class="mb-3">
        <input
          type="text"
          id="stockTakingSearchInput"
          class="form-control"
          placeholder="Search product by name, category, or unit..."
          autocomplete="off"
        >
        <small id="stockTakingSearchSummary" class="inventory-search-indicator d-none"></small>
      </div>

      <div class="table-responsive inventory-table-wrap inventory-stock-taking-table-wrap">
        <table class="table table-hover table-striped table-bordered" id="stockTakingProductsTable">
          <thead class="thead-dark">
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>System Qty</th>
              <th>Last Count</th>
              <th>Last Variance</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="stockTakingProductsTableBody">
            <tr>
              <td colspan="6" class="text-center text-muted">Loading products...</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div id="stockTakingProductsCards" class="inventory-stock-taking-cards" aria-live="polite">
        <!-- Mobile stock taking cards will be dynamically loaded here -->
      </div>
    </div>
  </div>

<div class="tab-content" id="home">
    <div class="card p-3 inventory-section-card">
        <div class="d-flex justify-content-between align-items-center mb-4 inventory-section-head">
            <h3 class="mb-0">Inventory Categories</h3>
            <div> </div>
        </div>
        <div class="table-responsive inventory-table-wrap inventory-categories-table-wrap">
            <table class="table table-hover table-striped table-bordered" id="inventoryCategoriesTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Inventory categories will be dynamically loaded here -->
                </tbody>
            </table>
        </div>
            <div id="inventoryCategoriesCards" class="inventory-categories-cards" aria-live="polite">
              <!-- Mobile category cards will be dynamically loaded here -->
            </div>
        </div>
    </div>  



    
<div class="tab-content" id="InventoryProducts">
    <div class="card p-3 inventory-section-card">
        <div class="d-flex justify-content-between align-items-center mb-4 inventory-section-head">
            <button class="btn btn-secondary" id="backToCategoriesBtn">Back to Categories</button>
            <h3 class="mb-0">Inventory Products</h3>
        </div>
        <div class="mb-3">
            <input 
                type="text"
                id="productSearchInput"
                class="form-control"
                placeholder="Search products..."
                autocomplete="off"
            >
            <small id="searchStatsIndicator" class="inventory-search-indicator d-none"></small>
        </div>
        <div class="table-responsive inventory-table-wrap inventory-products-table-wrap">
            <table class="table table-hover table-striped table-bordered" id="inventoryProductsTable">
                <thead class="thead-dark">
                    <tr>
                        <th>Product Image</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Reorder Level</th>
                        <th>Quantity</th>
                      <th>Fraction Balance</th>
                        <th>Stock Prediction</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Inventory products will be dynamically loaded here -->
                </tbody>
            </table>
        </div>
        <div id="inventoryProductsCards" class="inventory-products-cards" aria-live="polite">
            <!-- Mobile product cards will be dynamically loaded here -->
        </div>
    </div>
    </div>  




<div class="tab-content" id="productDetailsTab">
    <div class="card p-3 inventory-section-card">
        <h3 class="mb-4 text-primary text-uppercase text-center" id="productDetailsTitle"></h3>
    </div>
    <div class="card p-3 inventory-section-card" id="productDetailsCard">
        <div class="row">
            <div class="col-md-4 text-center">
                <img id="productImage" src="Images/productsDP/product.jpg" alt="Product Image" class="image img-fluid mb-3 inventory-detail-image" style="max-height: 200px;">
            </div>
            <div class="col-md-8">
                <p><strong>Category:</strong> <span id="productCategory"></span></p>
                <p><strong>Unit:</strong> <span id="productUnit"></span></p>
                <p><strong>Reorder Level:</strong> <span id="productReorderLevel"></span></p>
                <p><strong>Quantity:</strong> <span id="productQuantity"></span></p>
                <p><strong>Fraction Balance:</strong> <span id="productFractionQty"></span></p>
                <p><strong>Status:</strong> <span id="productStatus"></span></p>
                <p><strong>Cost Price:</strong> <span id="productCostPrice"></span></p>
                <p><strong>Selling Price:</strong> <span id="productSellingPrice"></span></p>
            </div>
        </div><hr>
        <div class="mt-4 inventory-detail-actions">
            <button class="btn btn-outline-secondary" id="backToProductsBtn">Back to Products</button>
            <button class="btn btn-outline-primary" id="editProductBtn">Edit Product</button>
            <button class="btn btn-outline-danger" id="deleteProductBtn">Delete Product</button>
            <button class="btn btn-outline-success" id="restockProductBtn">Restock Product</button>
            <button class="btn btn-outline-info" id="toggleProductDetailViewBtn">View Stock Movement</button>
        </div><hr>
        <div id="productTransactionsContainer" class="mt-4" >
            <h4 class="mb-3">Product Transactions</h4>
            <div class="table-responsive inventory-table-wrap inventory-product-transactions-table-wrap">
                <table class="table table-hover table-striped table-bordered" id="productTransactionsTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Partner</th>
                            <th>Total amount</th>
                            <th>Amount paid</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Product transactions will be dynamically loaded here -->
                    </tbody>
                </table>
            </div>
            <div id="productTransactionsCards" class="inventory-product-transactions-cards" aria-live="polite">
                <!-- Mobile product transaction cards will be dynamically loaded here -->
            </div>
        </div>
        <div id="productStockMovementContainer" class="mt-4">
            <h4 class="mb-3">Product Stock Movement</h4>
            <div class="table-responsive inventory-table-wrap inventory-product-stock-movement-table-wrap">
                <table class="table table-hover table-striped table-bordered" id="productStockMovementTable">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Quantity In</th>
                            <th>Quantity Out</th>
                            <th>Balance After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Product stock movement will be dynamically loaded here -->
                    </tbody>
                </table>
            </div>
            <div id="productStockMovementCards" class="inventory-product-stock-movement-cards" aria-live="polite">
                <!-- Mobile product stock movement cards will be dynamically loaded here -->
            </div>
        </div>
</div>
      </div>

<div class="modal fade" id="stockTakingModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="stockTakingModalLabel">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form id="stockTakingForm">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="stockTakingModalLabel">Record Stock Count</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close stock taking modal">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="stockTakingProductId">

          <div class="mb-3">
            <label class="font-weight-bold mb-1">Product</label>
            <div id="stockTakingProductName" class="text-dark">-</div>
          </div>

          <div class="mb-3">
            <label class="font-weight-bold mb-1">System Quantity</label>
            <div id="stockTakingSystemQty" class="h5 mb-0">0</div>
          </div>

          <div class="form-group">
            <label for="stockTakingCountedQty">Counted Quantity</label>
            <input type="number" class="form-control" id="stockTakingCountedQty" min="0" step="1" required>
          </div>

          <div class="form-group">
            <label for="stockTakingNotes">Notes</label>
            <textarea class="form-control" id="stockTakingNotes" rows="3" placeholder="Optional reconciliation note"></textarea>
          </div>

          <div class="alert alert-light border mb-0" id="stockTakingVariancePreview">
            Variance: <strong id="stockTakingVarianceValue">0</strong>
          </div>
          <div class="alert alert-warning mt-2 mb-0 d-none" id="stockTakingVarianceAlert">
            Large stock discrepancy detected.
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success" id="saveStockTakingBtn">Save Stock Count</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="stockTakingHistoryModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="stockTakingHistoryModalLabel">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="stockTakingHistoryModalLabel">Stock Taking History</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close stock taking history modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="table-responsive inventory-table-wrap inventory-stock-taking-history-table-wrap">
          <table class="table table-hover table-striped table-bordered" id="stockTakingHistoryTable">
            <thead class="thead-dark">
              <tr>
                <th>Date</th>
                <th>System Qty</th>
                <th>Counted Qty</th>
                <th>Variance</th>
                <th>Counted By</th>
                <th>Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody id="stockTakingHistoryTableBody">
              <tr>
                <td colspan="7" class="text-center text-muted">Loading history...</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="stockTakingHistoryCards" class="inventory-stock-taking-history-cards" aria-live="polite"></div>
      </div>
    </div>
  </div>
</div>


<?php include "INC/footer.php"; ?>
<script src="scripts/inventory.js?v=<?= asset_ver('scripts/inventory.js') ?>"></script>

</body>
</html>
