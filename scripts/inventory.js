// ============================
// Inventory Manager
// ============================
class Inventory {
  constructor(appCore) {
    this.app = appCore;
    this.state = {
      categories: [],
      products: [],
      filteredProducts: [],
      currentCategoryId: 0,
      currentProduct: null
    };
    // Cache DOM elements
    this.$categoriesTable = $("#inventoryCategoriesTable tbody");
    this.$productsTable = $("#inventoryProductsTable tbody");
    this.$productsCards = $("#inventoryProductsCards");
    this.$stockMovementTable = $("#stockMovementTable tbody");
    this.$stockMovementCards = $("#stockMovementCards");
    this.$productTransactionsTable = $("#productTransactionsTable tbody");
    this.$productTransactionsCards = $("#productTransactionsCards");
    this.$productStockMovementTable = $("#productStockMovementTable tbody");
    this.$productStockMovementCards = $("#productStockMovementCards");
    this.$statCategories = $("#statCategories");
    this.$statProducts = $("#statProducts");
    this.$lowStockCount = $("#lowStockCount");
    this.$inventoryCount = $("#inventoryCount");
    this.$inventoryValue = $("#inventoryValue");
    this.$badgeCategories = $("#badgeCategories");
    this.$badgeProducts = $("#badgeProducts");
    this.$lowStockAlert = $("#lowStockAlert");
    this.$lowStockList = $("#lowStockList");
    this.$searchStatsIndicator = $("#searchStatsIndicator");
    this.searchTimeout = null;
  }

  post(action, data = {}, onSuccess = null) {
    this.app.ajaxHelper({
      url: "apiInventory.php",
      action,
      data,
      onSuccess
    });
  }

  postForm(action, formData, onSuccess = null) {
    this.app.ajaxHelper({
      url: "apiInventory.php",
      action,
      data: formData,
      onSuccess
    });
  }

  showSection(sectionId) {
    $(".tab-content").hide();
    $(`#${sectionId}`).show();
  }

  loadCategories(onSuccess = null) {
    this.post("loadCategories", {}, (res) => {
      const rows = res.data || [];
      this.state.categories = rows;
      this.renderCategories(rows);
      this.populateCategoryOptions("#productCategorySelect", rows);
      this.populateCategoryOptions("#editProductCategorySelect", rows);
      this.updateStatBadges();
      if (typeof onSuccess === "function") onSuccess(rows);
    });
  }

  loadProducts(categoryId = 0, onSuccess = null) {
    this.state.currentCategoryId = Number(categoryId) || 0;
    this.post("loadInventory", { category_id: this.state.currentCategoryId }, (res) => {
      const rows = res.data || [];
      this.state.products = rows;
      this.state.filteredProducts = rows;
      this.renderProducts(rows);
      this.updateLowStockAlert(rows);
      this.updateStatBadges();
      if (typeof onSuccess === "function") onSuccess(rows);
    });
  }

  getProductStockPrediction(product) {
    const qty = this.app.toNumber(product.quantity, 0);
    const reorder = this.app.toNumber(product.reorder_level, 0);
    const totalSold = this.app.toNumber(product.total_sold, 0);
    const isActive = Number(product.is_active ?? 1) === 1;
    let days = 0;

    if (totalSold > 0 && qty > 0 && product.first_sale_date && product.last_sale_date) {
      const first = new Date(product.first_sale_date);
      const last = new Date(product.last_sale_date);
      const firstTime = first.getTime();
      const lastTime = last.getTime();

      if (!Number.isNaN(firstTime) && !Number.isNaN(lastTime)) {
        const diffDays = Math.max(1, (lastTime - firstTime) / (1000 * 60 * 60 * 24));
        const dailySales = totalSold / diffDays;

        if (dailySales > 0) {
          days = Math.floor(qty / dailySales);
        }
      }
    }

    let statusText = "In Stock";
    let badge = "success";

    if (!isActive) {
      statusText = "Inactive";
      badge = "secondary";
    } else if (qty <= reorder) {
      statusText = "Low Stock";
      badge = "warning";
    } else if (days > 0 && days <= 7) {
      statusText = `&#9888;&#65039; ${days} day(s) left`;
      badge = "danger";
    }

    return {
      qty,
      reorder,
      totalSold,
      days,
      isActive,
      statusText,
      badge
    };
  }

  updateLowStockAlert(rows = null) {
    const productsToCheck = rows || this.state.products;

    this.$lowStockList.empty();

    const alertItems = productsToCheck.filter((p) => {
      const metrics = this.getProductStockPrediction(p);
      return metrics.isActive && metrics.days > 0 && metrics.days <= 5;
    });

    if (alertItems.length > 0) {
      alertItems.forEach((p) => {
        const metrics = this.getProductStockPrediction(p);
        const li = $(`<li>${p.product_name} will finish in ${metrics.days} days</li>`);
        this.$lowStockList.append(li);
      });
      this.$lowStockAlert.show();
    } else {
      this.$lowStockAlert.hide();
    }
  }

  loadStockMovement(limit = 100) {
    this.post("loadStockLedger", { limit }, (res) => {
      const rows = res.data || [];
      this.renderStockMovement(rows);
      this.showSection("stockMovementTab");
    });
  }

  loadProductDetails(productId) {
    this.post("loadProductDetails", { product_id: productId }, (res) => {
      this.state.currentProduct = res.product || null;
      this.renderProductDetails(res.product || {});
      this.renderProductTransactions(res.transactions || []);
      this.renderProductStockMovement(res.stockMovement || []);
      this.showSection("productDetailsTab");
    });
  }

  openTransactionDetails(referenceId) {
    const transactionId = Number(referenceId) || 0;
    if (!transactionId) return;

    const $modal = $("#purchaseDetailsModal");
    const $itemsTbody = $modal.find("#purchaseDetailsItemsTable tbody");
    const $paymentSection = $modal.find("#payPurchaseForm").closest(".row");

    this.app.ajaxHelper({
      url: "apiTransactions.php",
      action: "loadPurchaseDetails",
      data: { purchase_id: transactionId },
      onSuccess: (res) => {
        const purchase = res.purchase || {};
        const items = Array.isArray(res.items) ? res.items : [];
        const total = parseFloat(purchase.totalAmount) || 0;
        const paid = parseFloat(purchase.amountPaid) || 0;
        const balance = Math.max(0, total - paid);

        $("#metaPartner").text(purchase.partner_name || purchase.supplier_name || "-");
        $("#metaTotal").text(this.app.formatCurrency(total));
        $("#metaPaid").text(this.app.formatCurrency(paid));
        $("#metaBalance").text(this.app.formatCurrency(balance));

        $itemsTbody.empty();

        if (!items.length) {
          $itemsTbody.html(`
            <tr>
              <td colspan="5" class="text-center text-muted">No items found for this transaction</td>
            </tr>
          `);
        } else {
          const html = items.map((item) => {
            const lineTotal = parseFloat(item.total) || ((parseFloat(item.qty) || 0) * (parseFloat(item.costPrice) || 0));
            return `
              <tr>
                <td>${item.product_name || "-"}</td>
                <td>${item.qty || 0}</td>
                <td>${item.product_unit || "-"}</td>
                <td>${this.app.formatCurrency(parseFloat(item.costPrice) || 0)}</td>
                <td>${this.app.formatCurrency(lineTotal)}</td>
              </tr>
            `;
          }).join("");
          $itemsTbody.html(html);
        }

        $paymentSection.addClass("d-none");
        $modal.modal("show");
      },
      onError: () => {
        this.app.showAlert("Failed to load transaction details. Please try again.", "error");
      }
    });
  }

  renderReferenceButton(referenceType, referenceId) {
    const normalizedType = String(referenceType || "").toLowerCase();
    const normalizedId = Number(referenceId) || 0;

    if (normalizedId > 0 && ["purchase", "sale", "buy", "sell"].includes(normalizedType)) {
      return `<button type="button" class="btn btn-sm btn-outline-primary openTransactionReferenceBtn" data-id="${normalizedId}">#${normalizedId}</button>`;
    }

    return normalizedId > 0 ? normalizedId : "-";
  }

  renderCategories(rows) {
    this.$categoriesTable.empty();

    if (!rows.length) {
      this.$categoriesTable.html("<tr><td colspan='3' class='text-center text-muted'>No categories found</td></tr>");
      return;
    }

    const canManageCatalog = this.app.hasAnyPermission("manage_products", "manage_inventory");
    const canDelete = this.app.hasPermission("delete_records");

    const html = rows.map((c) => {
      const isActive = Number(c.is_active ?? 1) === 1;
      const name = c.category_name || "-";
      const desc = c.category_description || "-";
      return `
        <tr>
          <td>${name}</td>
          <td>${desc}</td>
          <td>
            <button type="button" class="btn btn-sm btn-info viewProductsBtn" data-id="${c.category_id}">Products</button>
            ${canManageCatalog ? `<button type="button" class="btn btn-sm btn-primary editCategoryBtn" data-id="${c.category_id}" data-name="${name}" data-description="${desc}" data-status="${Number(c.is_active ?? 1)}">Edit</button>` : ""}
            ${canDelete ? `<button type="button" class="btn btn-sm btn-danger deleteCategoryBtn" data-id="${c.category_id}">Delete</button>` : ""}
          </td>
        </tr>
      `;
    }).join("");

    this.$categoriesTable.html(html);
  }

  applyPermissionState() {
    const canManageCatalog = this.app.hasAnyPermission("manage_products", "manage_inventory");
    const canRestock = this.app.hasAnyPermission("manage_inventory", "create_purchases");
    const canDelete = this.app.hasPermission("delete_records");

    $("#addCategoryBtn, #addProductBtn, #editProductBtn").toggle(canManageCatalog);
    $("#restockProductBtn").toggle(canRestock);
    $("#deleteProductBtn").toggle(canDelete);
  }

  renderProducts(rows) {
    this.$productsTable.empty();
    this.$productsCards.empty();

    if (!rows.length) {
      this.$productsTable.html("<tr><td colspan='9' class='text-center text-muted'>No products found</td></tr>");
      this.$productsCards.html("<div class='text-center text-muted py-3'>No products found</div>");
      return;
    }

    const tableHtml = rows.map((p) => {
      const image = this.app.resolveImagePath(p.product_image, "Images/productsDP", "Images/productsDP/product.jpg");
      const metrics = this.getProductStockPrediction(p);
      const name = p.product_name || "-";
      const category = p.category_name || "Uncategorized";
      const unit = p.product_unit || "pcs";

      return `
        <tr>
          <td><img src="${image}" width="40" class="image rounded"></td>
          <td>${name}</td>
          <td>${category}</td>
          <td>${unit}</td>
          <td>${metrics.reorder}</td>
          <td>${metrics.qty}</td>
          <td>${metrics.days > 0 ? `${metrics.days} days` : "-"}</td>
          <td><span class="badge badge-${metrics.badge}">${metrics.statusText}</span></td>
          <td>
            <button type="button" class="btn btn-sm btn-primary productDetailsBtn" data-id="${p.product_id}">Details</button>
          </td>
        </tr>
      `;
    }).join("");

    const cardsHtml = rows.map((p) => {
      const image = this.app.resolveImagePath(p.product_image, "Images/productsDP", "Images/productsDP/product.jpg");
      const metrics = this.getProductStockPrediction(p);
      const name = p.product_name || "-";
      const category = p.category_name || "Uncategorized";
      const unit = p.product_unit || "pcs";

      return `
        <div class="card shadow-sm inventory-product-card mb-3">
          <div class="card-body p-3">
            <div class="d-flex align-items-center mb-2">
              <img src="${image}" alt="${name}" class="rounded mr-2" width="44" height="44">
              <div class="flex-grow-1">
                <h6 class="mb-0">${name}</h6>
                <small class="text-muted">${category}</small>
              </div>
              <span class="badge badge-${metrics.badge}">${metrics.statusText}</span>
            </div>

            <div class="inventory-product-meta text-muted mb-2">
              <span>Unit: ${unit}</span>
              <span>Reorder: ${metrics.reorder}</span>
              <span>Qty: ${metrics.qty}</span>
              <span>Prediction: ${metrics.days > 0 ? `${metrics.days} days` : "-"}</span>
            </div>

            <button type="button" class="btn btn-sm btn-primary btn-block productDetailsBtn" data-id="${p.product_id}">Details</button>
          </div>
        </div>
      `;
    }).join("");

    this.$productsTable.html(tableHtml);
    this.$productsCards.html(cardsHtml);
  }

  filterProducts(searchTerm) {
    const normalizedTerm = String(searchTerm || "").trim();

    if (!normalizedTerm) {
      this.state.filteredProducts = this.state.products;
      this.$searchStatsIndicator.addClass("d-none").text("");
    } else {
      const term = normalizedTerm.toLowerCase();
      this.state.filteredProducts = this.state.products.filter(p =>
        (p.product_name || "").toLowerCase().includes(term) ||
        (p.category_name || "").toLowerCase().includes(term)
      );

      this.$searchStatsIndicator
        .removeClass("d-none")
        .text(`Showing stats for filtered results (${this.state.filteredProducts.length} of ${this.state.products.length})`);
    }
    this.renderProducts(this.state.filteredProducts);
    this.updateStatBadges(this.state.filteredProducts);
  }

  renderProductDetails(product) {
    const image = this.app.resolveImagePath(product.product_image, "Images/productsDP", "Images/productsDP/product.jpg");
    $("#productDetailsTitle").text(product.product_name || "Product Details");
    $("#productImage").attr("src", image);
    $("#productCategory").text(product.category_name || "Uncategorized");
    $("#productUnit").text(product.product_unit || "pcs");
    $("#productReorderLevel").text(this.app.toNumber(product.reorder_level, 0));
    $("#productQuantity").text(this.app.toNumber(product.quantity, 0));
    $("#productStatus").text(Number(product.is_active ?? 1) === 1 ? "Active" : "Inactive");
    $("#productCostPrice").text(this.app.formatCurrency(product.cost_price || 0));
    $("#productSellingPrice").text(this.app.formatCurrency(product.selling_price || 0));
  }

  renderProductTransactions(rows) {
    this.$productTransactionsTable.empty();
    this.$productTransactionsCards.empty();

    if (!rows.length) {
      this.$productTransactionsTable.html("<tr><td colspan='7' class='text-center text-muted'>No transactions found</td></tr>");
      this.$productTransactionsCards.html("<div class='text-center text-muted py-3'>No transactions found</div>");
      return;
    }

    const tableHtml = rows.map((t) => {
      const transactionId = Number(t.purchase_id) || 0;
      return `
        <tr>
          <td>${this.app.formatDateSafe(t.createdAt)}</td>
          <td>${t.transaction_type || "-"}</td>
          <td>${t.partner_name || "-"}</td>
          <td>${this.app.formatCurrency(t.totalAmount || 0)}</td>
          <td>${this.app.formatCurrency(t.amountPaid || 0)}</td>
          <td>${t.status || "-"}</td>
          <td><button type="button" class="btn btn-sm btn-outline-primary openTransactionReferenceBtn" data-id="${transactionId}">View</button></td>
        </tr>
      `;
    }).join("");

    const cardsHtml = rows.map((t) => {
      const transactionId = Number(t.purchase_id) || 0;
      return `
        <div class="card shadow-sm inventory-detail-card mb-3">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="mb-0 text-capitalize">${t.transaction_type || "-"}</h6>
                <small class="text-muted">${this.app.formatDateSafe(t.createdAt)}</small>
              </div>
              <span class="badge badge-light border">${t.status || "-"}</span>
            </div>

            <div class="inventory-stock-meta text-muted mb-2">
              <span>Partner: ${t.partner_name || "-"}</span>
              <span>Total: ${this.app.formatCurrency(t.totalAmount || 0)}</span>
              <span>Paid: ${this.app.formatCurrency(t.amountPaid || 0)}</span>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary btn-block openTransactionReferenceBtn" data-id="${transactionId}">View</button>
          </div>
        </div>
      `;
    }).join("");

    this.$productTransactionsTable.html(tableHtml);
    this.$productTransactionsCards.html(cardsHtml);
  }

  renderProductStockMovement(rows) {
    this.$productStockMovementTable.empty();
    this.$productStockMovementCards.empty();

    if (!rows.length) {
      this.$productStockMovementTable.html("<tr><td colspan='6' class='text-center text-muted'>No stock movements found</td></tr>");
      this.$productStockMovementCards.html("<div class='text-center text-muted py-3'>No stock movements found</div>");
      return;
    }

    const tableHtml = rows.map((movement) => {
      return `
        <tr>
          <td>${this.app.formatDateSafe(movement.created_at)}</td>
          <td>${movement.reference_type || "-"}</td>
          <td>${this.renderReferenceButton(movement.reference_type, movement.reference_id)}</td>
          <td>${this.app.toNumber(movement.qty_in, 0)}</td>
          <td>${this.app.toNumber(movement.qty_out, 0)}</td>
          <td>${this.app.toNumber(movement.balance_after, 0)}</td>
        </tr>
      `;
    }).join("");

    const cardsHtml = rows.map((movement) => {
      const type = movement.reference_type || "-";
      const qtyIn = this.app.toNumber(movement.qty_in, 0);
      const qtyOut = this.app.toNumber(movement.qty_out, 0);
      const balance = this.app.toNumber(movement.balance_after, 0);

      return `
        <div class="card shadow-sm inventory-detail-card mb-3">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="mb-0 text-capitalize">${type}</h6>
                <small class="text-muted">${this.app.formatDateSafe(movement.created_at)}</small>
              </div>
            </div>

            <div class="inventory-stock-meta text-muted mb-2">
              <span>Ref: ${this.renderReferenceButton(movement.reference_type, movement.reference_id)}</span>
              <span>In: ${qtyIn}</span>
              <span>Out: ${qtyOut}</span>
              <span>Balance: ${balance}</span>
            </div>
          </div>
        </div>
      `;
    }).join("");

    this.$productStockMovementTable.html(tableHtml);
    this.$productStockMovementCards.html(cardsHtml);
  }

  renderStockMovement(rows) {
    this.$stockMovementTable.empty();
    this.$stockMovementCards.empty();

    if (!rows.length) {
      this.$stockMovementTable.html("<tr><td colspan='7' class='text-center text-muted'>No stock movements found</td></tr>");
      this.$stockMovementCards.html("<div class='text-center text-muted py-3'>No stock movements found</div>");
      return;
    }

    const tableHtml = rows.map((s) => {
      return `
        <tr>
          <td>${this.app.formatDateSafe(s.created_at)}</td>
          <td>${s.product_name || "-"}</td>
          <td>${s.reference_type || "-"}</td>
          <td>${this.renderReferenceButton(s.reference_type, s.reference_id)}</td>
          <td>${this.app.toNumber(s.qty_in, 0)}</td>
          <td>${this.app.toNumber(s.qty_out, 0)}</td>
          <td>${this.app.toNumber(s.balance_after, 0)}</td>
        </tr>
      `;
    }).join("");

    const cardsHtml = rows.map((s) => {
      const productName = s.product_name || "-";
      const type = s.reference_type || "-";
      const qtyIn = this.app.toNumber(s.qty_in, 0);
      const qtyOut = this.app.toNumber(s.qty_out, 0);
      const balance = this.app.toNumber(s.balance_after, 0);

      return `
        <div class="card shadow-sm inventory-stock-card mb-3">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="mb-0">${productName}</h6>
                <small class="text-muted">${this.app.formatDateSafe(s.created_at)}</small>
              </div>
              <span class="badge badge-light border text-capitalize">${type}</span>
            </div>

            <div class="inventory-stock-meta text-muted mb-2">
              <span>Ref: ${this.renderReferenceButton(s.reference_type, s.reference_id)}</span>
              <span>In: ${qtyIn}</span>
              <span>Out: ${qtyOut}</span>
              <span>Balance: ${balance}</span>
            </div>
          </div>
        </div>
      `;
    }).join("");

    this.$stockMovementTable.html(tableHtml);
    this.$stockMovementCards.html(cardsHtml);
  }

  updateStatBadges(products = null) {
    const productsForStats = Array.isArray(products) ? products : this.state.products;
    const catCount = this.state.categories.length;
    const prodCount = productsForStats.length;
    const lowStockCount = productsForStats.filter((p) => {
      const qty = this.app.toNumber(p.quantity, 0);
      const reorder = this.app.toNumber(p.reorder_level, 0);
      const isActive = Number(p.is_active ?? 1) === 1;
      return isActive && qty <= reorder;
    }).length;
    const inventoryItemCount = productsForStats.reduce((sum, p) => {
      return sum + this.app.toNumber(p.quantity, 0);
    }, 0);
    const inventoryValue = productsForStats.reduce((sum, p) => {
      const qty = this.app.toNumber(p.quantity, 0);
      const cost = this.app.toNumber(p.cost_price, 0);
      return sum + (qty * cost);
    }, 0);

    this.$statCategories.text(catCount);
    this.$statProducts.text(prodCount);
    this.$lowStockCount.text(lowStockCount);
    this.$inventoryCount.text(inventoryItemCount);
    this.$inventoryValue.text(this.app.formatCurrency(inventoryValue));
    this.$badgeCategories.text(catCount);
    this.$badgeProducts.text(prodCount);
  }

  populateCategoryOptions(selector, rows) {
    const select = $(selector);
    if (!select.length) return;
    select.empty();
    select.append('<option value="">Select category</option>');
    rows.forEach((c) => {
      if (Number(c.is_active ?? 1) === 1) {
        select.append(`<option value="${c.category_id}">${c.category_name}</option>`);
      }
    });
  }

  populateUnitOptions(selector) {
    const select = $(selector);
    if (!select.length) return;
    select.empty();
    this.app.productUnits.forEach((u) => {
      select.append(`<option value="${u.value}">${u.label}</option>`);
    });
  }
}

$(document).ready(function () {
  const csrf_token = $('meta[name="csrf-token"]').attr('content') || "";
  const App = new AppCore(csrf_token);
  
  const AuthApp = new Auth(App);
  const InventoryApp = new Inventory(App);

  InventoryApp.populateUnitOptions("#productUnitSelect1");
  InventoryApp.populateUnitOptions("#editProductUnitSelect");
  InventoryApp.showSection("home");

  App.loadUserPermissions(() => {
    InventoryApp.applyPermissionState();
    InventoryApp.loadCategories();
    InventoryApp.loadProducts(0);
  });

  $("#addCategoryBtn").on("click", function () {
    $("#addCategoryForm")[0].reset();
    $("#addCategoryModal").modal("show");
  });

  $("#addProductBtn").on("click", function () {
    $("#addProductForm")[0].reset();
    InventoryApp.loadCategories(() => {
      $("#addProductModal").modal("show");
    });
  });

  $("#viewStockMovementBtn").on("click", function () {
    InventoryApp.loadStockMovement();
  });

  $("#backToCategoriesFromMovementBtn").on("click", function () {
    InventoryApp.loadCategories();
    InventoryApp.loadProducts(0);
    InventoryApp.showSection("home");
  });

  $(document).on("click", ".viewProductsBtn", function () {
    const categoryId = Number($(this).data("id")) || 0;
    InventoryApp.loadProducts(categoryId);
    InventoryApp.showSection("InventoryProducts");
  });

  $(document).on("click", ".productDetailsBtn", function () {
    const productId = Number($(this).data("id")) || 0;
    if (!productId) return;
    InventoryApp.loadProductDetails(productId);
  });

  $(document).on("click", ".openTransactionReferenceBtn", function () {
    const referenceId = Number($(this).data("id")) || 0;
    if (!referenceId) return;
    InventoryApp.openTransactionDetails(referenceId);
  });

  $("#backToProductsBtn").on("click", function () {
    InventoryApp.showSection("InventoryProducts");
  });

  $("#backToCategoriesBtn").on("click", function () {
    $("#productSearchInput").val("");
    InventoryApp.state.filteredProducts = InventoryApp.state.products;
    InventoryApp.$searchStatsIndicator.addClass("d-none").text("");
    InventoryApp.loadCategories();
    InventoryApp.loadProducts(0);
    InventoryApp.showSection("home");
  });

  $("#purchaseDetailsModal").on("hidden.bs.modal", function () {
    $(this).find("#payPurchaseForm").closest(".row").removeClass("d-none");
  });

  // Product search with debouncing
  $("#productSearchInput").on("input", function () {
    clearTimeout(InventoryApp.searchTimeout);
    const searchTerm = $(this).val();
    InventoryApp.searchTimeout = setTimeout(() => {
      InventoryApp.filterProducts(searchTerm);
    }, 300);
  });

  $("#addCategoryForm").on("submit", function (e) {
    e.preventDefault();
    InventoryApp.post("createCategory", {
      category_name: $("#categoryNameInput").val().trim(),
      category_description: $("#categoryDescriptionInput").val().trim()
    }, () => {
      AppCore.safeHideModal("#addCategoryModal");
      InventoryApp.loadCategories();
    });
  });

  $(document).on("click", ".editCategoryBtn", function () {
    $("#editCategoryIdInput").val($(this).data("id"));
    $("#editCategoryNameInput").val($(this).data("name") || "");
    $("#editCategoryDescriptionInput").val($(this).data("description") || "");
    $("#editCategoryStatusSelect").val(String($(this).data("status") ?? "1"));
    $("#editCategoryModal").modal("show");
  });

  $("#editCategoryForm").on("submit", function (e) {
    e.preventDefault();
    InventoryApp.post("editCategory", {
      category_id: $("#editCategoryIdInput").val(),
      category_name: $("#editCategoryNameInput").val().trim(),
      category_description: $("#editCategoryDescriptionInput").val().trim(),
      status: $("#editCategoryStatusSelect").val()
    }, () => {
      AppCore.safeHideModal("#editCategoryModal");
      InventoryApp.loadCategories(() => {
        // Only reload products if we're viewing a specific category
        if (InventoryApp.state.currentCategoryId) {
          InventoryApp.loadProducts(InventoryApp.state.currentCategoryId);
        }
      });
    });
  });

  $(document).on("click", ".deleteCategoryBtn", function () {
    const categoryId = Number($(this).data("id")) || 0;
    if (!categoryId) return;
    if (!confirm("Delete this category?")) return;
    InventoryApp.post("deleteCategory", { category_id: categoryId }, () => {
      InventoryApp.loadCategories(() => {
        InventoryApp.loadProducts(0);
      });
    });
  });

  $("#addProductForm").on("submit", function (e) {
    e.preventDefault();
    const fd = new FormData(this);

    fd.set("opening_qty", "0");
    fd.set("cost_price", "0");
    fd.set("selling_price", "0");
    InventoryApp.postForm("createProduct", fd, () => {
      AppCore.safeHideModal("#addProductModal");
      InventoryApp.loadProducts(InventoryApp.state.currentCategoryId || 0);
    });
  });

  $("#editProductBtn").on("click", function () {
    const p = InventoryApp.state.currentProduct;
    if (!p) return;

    InventoryApp.loadCategories(() => {
      $("#editProductIdInput").val(p.product_id);
      $("#editProductNameInput").val(p.product_name || "");
      $("#editProductCategorySelect").val(String(p.category_id || ""));
      $("#editProductUnitSelect").val(p.product_unit || "pcs");
      $("#editReorderLevelInput").val(Number(p.reorder_level || 0));
      $("#editCostPriceInput").val(Number(p.cost_price || 0));
      $("#editSellingPriceInput").val(Number(p.selling_price || 0));
      $("#editStatusSelect").val(String(Number(p.is_active ?? 1)));
      $("#editProductModal").modal("show");
    });
  });

  $("#editProductForm").on("submit", function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    InventoryApp.postForm("editProduct", fd, () => {
      AppCore.safeHideModal("#editProductModal");
      if (InventoryApp.state.currentProduct?.product_id) {
        InventoryApp.loadProductDetails(InventoryApp.state.currentProduct.product_id);
      }
      InventoryApp.loadProducts(InventoryApp.state.currentCategoryId || 0);
    });
  });

  $("#restockProductBtn").on("click", function () {
    const p = InventoryApp.state.currentProduct;
    if (!p) return;
    $("#restockProductTitle").text(p.product_name || "");
    $("#restockQuantityInput").val("");
    $("#restockProductModal").modal("show");
  });

  $("#restockProductForm").on("submit", function (e) {
    e.preventDefault();
    const p = InventoryApp.state.currentProduct;
    if (!p) return;

    InventoryApp.post("restockProduct", {
      product_id: p.product_id,
      quantity: $("#restockQuantityInput").val()
    }, () => {
      AppCore.safeHideModal("#restockProductModal");
      InventoryApp.loadProductDetails(p.product_id);
      InventoryApp.loadProducts(InventoryApp.state.currentCategoryId || 0);
    });
  });

  $("#deleteProductBtn").on("click", function () {
    const p = InventoryApp.state.currentProduct;
    if (!p) return;
    if (!confirm("Delete this product?")) return;

    InventoryApp.post("deleteProduct", { product_id: p.product_id }, () => {
      InventoryApp.state.currentProduct = null;
      InventoryApp.loadProducts(InventoryApp.state.currentCategoryId || 0);
      InventoryApp.showSection("InventoryProducts");
    });
  });
});
