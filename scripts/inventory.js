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
      purchasePartners: [],
      purchaseProducts: [],
      salePartners: [],
      saleProducts: [],
      currentCategoryId: 0,
      currentProduct: null
    };
    // Cache DOM elements
    this.$categoriesTable = $("#inventoryCategoriesTable tbody");
    this.$productsTable = $("#inventoryProductsTable tbody");
    this.$productsCards = $("#inventoryProductsCards");
    this.$stockMovementTable = $("#stockMovementTable tbody");
    this.$stockMovementCards = $("#stockMovementCards");
    this.$reorderTableBody = $("#reorderTableBody");
    this.$reorderSuggestionsCards = $("#reorderSuggestionsCards");
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
    this.$productSearchInput = $("#productSearchInput");
    this.$searchStatsIndicator = $("#searchStatsIndicator");
    this.$purchaseModal = $("#purchaseModal");
    this.$purchaseSupplierInput = $("#purchaseSupplierInput");
    this.$purchaseSupplier = $("#purchaseSupplier");
    this.$purchaseSupplierDatalist = $("#purchaseSupplierDatalist");
    this.$purchaseProductDatalist = $("#purchaseProductDatalist");
    this.$purchaseItems = $("#purchaseItems");
    this.$purchaseTotal = $("#purchaseTotal");
    this.$amountPaid = $("#amountPaid");
    this.$saleModal = $("#saleModal");
    this.$saleCustomerInput = $("#saleCustomerInput");
    this.$saleCustomer = $("#saleCustomer");
    this.$saleCustomerDatalist = $("#saleCustomerDatalist");
    this.$saleProductDatalist = $("#saleProductDatalist");
    this.$saleItems = $("#saleItems");
    this.$saleTotal = $("#saleTotal");
    this.$saleAmountPaid = $("#saleAmountPaid");
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
        dir: "productsDP",
        onSuccess
      });
    }

  postTransactions(action, data = {}, onSuccess = null, options = {}) {
    this.app.ajaxHelper({
      url: "apiTransactions.php",
      action,
      data,
      onSuccess,
      ...options
    });
  }

  postPartners(action, data = {}, onSuccess = null, options = {}) {
    this.app.ajaxHelper({
      url: "apiPartners.php",
      action,
      data,
      onSuccess,
      ...options
    });
  }

  showSection(sectionId) {
    $(".tab-content").hide();
    $(`#${sectionId}`).stop(true, true).fadeIn(180);
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
      this.updateLowStockAlert(rows);
      this.applyCurrentProductSearch();
      if (typeof onSuccess === "function") onSuccess(rows);
    });
  }

  applyCurrentProductSearch() {
    const activeSearch = String(this.$productSearchInput.val() || "").trim();

    if (activeSearch) {
      this.filterProducts(activeSearch);
      return;
    }

    this.state.filteredProducts = this.state.products;
    this.$searchStatsIndicator.addClass("d-none").text("");
    this.renderProducts(this.state.products);
    this.updateStatBadges();
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

  loadReorderSuggestions() {
    this.post("getReorderSuggestions", {}, (res) => {
      const rows = res.data || [];
      this.renderReorderSuggestions(rows);
      this.showSection("reorderTab");
    });
  }

  loadPurchasePartners(onSuccess = null) {
    this.postPartners("loadAllPartners", {}, (res) => {
      const rows = Array.isArray(res.data) ? res.data : [];
      this.state.purchasePartners = rows;
      this.populatePurchaseSupplierOptions(rows);
      if (typeof onSuccess === "function") onSuccess(rows);
    }, { silent: true });
  }

  loadPurchaseProducts(onSuccess = null) {
    this.postTransactions("loadProducts", {}, (res) => {
      const rows = Array.isArray(res.data) ? res.data : [];
      this.state.purchaseProducts = rows;
      this.populatePurchaseProductOptions(rows);
      if (typeof onSuccess === "function") onSuccess(rows);
    }, { silent: true });
  }

  loadSalePartners(onSuccess = null) {
    this.postPartners("loadAllPartners", {}, (res) => {
      const rows = Array.isArray(res.data) ? res.data : [];
      this.state.salePartners = rows;
      this.populateSaleCustomerOptions(rows);
      if (typeof onSuccess === "function") onSuccess(rows);
    }, { silent: true });
  }

  loadSaleProducts(onSuccess = null) {
    this.postTransactions("loadProducts", {}, (res) => {
      const rows = Array.isArray(res.data) ? res.data : [];
      this.state.saleProducts = rows;
      this.populateSaleProductOptions(rows);
      if (typeof onSuccess === "function") onSuccess(rows);
    }, { silent: true });
  }

  populatePurchaseSupplierOptions(rows = []) {
    this.$purchaseSupplierDatalist.empty();
    rows.forEach((partner) => {
      const partnerName = String(partner.sName || partner.sname || "").trim();
      if (partnerName) {
        this.$purchaseSupplierDatalist.append(`<option value="${AppCore.escapeHtml(partnerName)}"></option>`);
      }
    });
  }

  populatePurchaseProductOptions(rows = []) {
    this.$purchaseProductDatalist.empty();
    rows.forEach((product) => {
      const productName = String(product.product_name || "").trim();
      if (productName) {
        this.$purchaseProductDatalist.append(`<option value="${AppCore.escapeHtml(productName)}"></option>`);
      }
    });
  }

  populateSaleCustomerOptions(rows = []) {
    this.$saleCustomerDatalist.empty();
    rows.forEach((partner) => {
      const partnerName = String(partner.sName || partner.sname || "").trim();
      if (partnerName) {
        this.$saleCustomerDatalist.append(`<option value="${AppCore.escapeHtml(partnerName)}"></option>`);
      }
    });
  }

  populateSaleProductOptions(rows = []) {
    this.$saleProductDatalist.empty();
    rows.forEach((product) => {
      const productName = String(product.product_name || "").trim();
      if (productName) {
        const stockText = `${this.app.toNumber(product.available_stock, 0)} in stock`;
        this.$saleProductDatalist.append(`<option value="${AppCore.escapeHtml(productName)}" label="${AppCore.escapeHtml(stockText)}"></option>`);
      }
    });
  }

  normalizeAutocompleteText(value) {
    return String(value || "").trim().replace(/\s+/g, " ").toLowerCase();
  }

  isSheetFamily(unit) {
    const normalized = this.normalizeAutocompleteText(unit);
    return normalized === "size" || normalized === "sheet";
  }

  isRollFamily(unit) {
    const normalized = this.normalizeAutocompleteText(unit);
    return normalized === "yard" || normalized === "roll";
  }

  formatFractionValue(value) {
    const num = this.app.toNumber(value, 0);
    return Number.isInteger(num) ? String(num) : String(num.toFixed(2));
  }

  formatStockDisplay(product) {
    const unit = String(product?.product_unit || "");
    const quantity = this.app.toNumber(product?.quantity, 0);
    const fractionQty = this.app.toNumber(product?.fraction_qty, 0);

    if (this.isSheetFamily(unit)) {
      return `${quantity} sheet(s) + ${this.formatFractionValue(fractionQty)} sq ft`;
    }
    if (this.isRollFamily(unit)) {
      return `${quantity} roll(s) + ${this.formatFractionValue(fractionQty)} yard(s)`;
    }

    return String(quantity);
  }

  formatFractionBalanceDisplay(product) {
    const unit = String(product?.product_unit || "");
    const fractionQty = this.app.toNumber(product?.fraction_qty, 0);
    if (this.isSheetFamily(unit)) return `${this.formatFractionValue(fractionQty)} sq ft`;
    if (this.isRollFamily(unit)) return `${this.formatFractionValue(fractionQty)} yard(s)`;
    return "-";
  }

  findPartnerByName(rows, name) {
    const query = this.normalizeAutocompleteText(name);
    if (!query) return null;
    const exact = rows.find((partner) => this.normalizeAutocompleteText(partner.sName || partner.sname) === query);
    if (exact) return exact;
    return rows.find((partner) => this.normalizeAutocompleteText(partner.sName || partner.sname).includes(query)) || null;
  }

  findProductByName(rows, name) {
    const query = this.normalizeAutocompleteText(name);
    if (!query) return null;
    const exact = rows.find((product) => this.normalizeAutocompleteText(product.product_name) === query);
    if (exact) return exact;
    return rows.find((product) => this.normalizeAutocompleteText(product.product_name).includes(query)) || null;
  }

  syncPurchaseSupplierFromInput() {
    const match = this.findPartnerByName(this.state.purchasePartners, this.$purchaseSupplierInput.val());
    if (!match) {
      this.$purchaseSupplier.val("");
      return null;
    }

    const partnerId = Number(match.sid || match.partner_id || 0);
    const partnerName = String(match.sName || match.sname || "").trim();
    this.$purchaseSupplier.val(partnerId > 0 ? partnerId : "");
    this.$purchaseSupplierInput.val(partnerName);
    return match;
  }

  syncSaleCustomerFromInput() {
    const match = this.findPartnerByName(this.state.salePartners, this.$saleCustomerInput.val());
    if (!match) {
      this.$saleCustomer.val("");
      return null;
    }

    const partnerId = Number(match.sid || match.partner_id || 0);
    const partnerName = String(match.sName || match.sname || "").trim();
    this.$saleCustomer.val(partnerId > 0 ? partnerId : "");
    this.$saleCustomerInput.val(partnerName);
    return match;
  }

  syncPurchaseProductRow($row) {
    const $input = $row.find(".purchaseProductInput");
    const $hidden = $row.find(".productSelect");
    const match = this.findProductByName(this.state.purchaseProducts, $input.val());

    if (!match) {
      $hidden.val("");
      return null;
    }

    const productId = Number(match.product_id) || 0;
    $hidden.val(productId > 0 ? productId : "");
    $input.val(String(match.product_name || ""));

    const $cost = $row.find(".cost");
    if ($cost.val() === "") {
      $cost.val(this.app.toNumber(match.cost_price, 0));
    }

    return match;
  }

  syncSaleProductRow($row) {
    const $input = $row.find(".saleProductInput");
    const $hidden = $row.find(".saleProduct");
    const match = this.findProductByName(this.state.saleProducts, $input.val());

    if (!match) {
      $hidden.val("");
      return null;
    }

    const productId = Number(match.product_id) || 0;
    $hidden.val(productId > 0 ? productId : "");
    $input.val(String(match.product_name || ""));

    const $price = $row.find(".salePrice");
    if ($price.val() === "") {
      $price.val(this.app.toNumber(match.selling_price, 0));
    }

    return match;
  }

  appendPurchaseItemRow(item = {}) {
    const productId = Number(item.product_id) || 0;
    const qty = this.app.toNumber(item.qty, 0);
    const costPrice = this.app.toNumber(item.costPrice, 0);
    const selectedProduct = this.state.purchaseProducts.find((product) => Number(product.product_id) === productId) || null;
    const productName = selectedProduct ? String(selectedProduct.product_name || "") : "";
    const hasEmptyState = this.$purchaseItems.find("td[colspan='5']").length > 0;

    if (hasEmptyState) {
      this.$purchaseItems.empty();
    }

    const row = $(`
      <tr>
        <td>
          <input type="text" class="form-control purchaseProductInput" list="purchaseProductDatalist" placeholder="Type product name" value="${AppCore.escapeHtml(productName)}">
          <input type="hidden" class="productSelect" value="${productId > 0 ? productId : ""}">
        </td>
        <td><input type="number" class="form-control qty" min="1" step="1" value="${qty > 0 ? qty : ""}"></td>
        <td><input type="number" class="form-control cost" min="0" step="0.01" value="${costPrice > 0 ? costPrice : ""}"></td>
        <td class="rowTotal">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger removePurchaseItemBtn">&times;</button></td>
      </tr>
    `);

    this.$purchaseItems.append(row);
    this.syncPurchaseProductRow(row);
    this.updatePurchaseRow(row);
    this.recalculatePurchaseTotal();
  }

  updatePurchaseRow($row) {
    const qty = this.app.toNumber($row.find(".qty").val(), 0);
    const cost = this.app.toNumber($row.find(".cost").val(), 0);
    const rowTotal = qty * cost;
    $row.find(".rowTotal").text(this.app.formatNumber(rowTotal));
  }

  recalculatePurchaseTotal() {
    let total = 0;

    this.$purchaseItems.find("tr").each((_, rowEl) => {
      const $row = $(rowEl);
      if ($row.find(".qty").length === 0) {
        return;
      }

      const qty = this.app.toNumber($row.find(".qty").val(), 0);
      const cost = this.app.toNumber($row.find(".cost").val(), 0);
      const rowTotal = qty * cost;

      $row.find(".rowTotal").text(this.app.formatNumber(rowTotal));
      total += rowTotal;
    });

    this.$purchaseTotal.text(this.app.formatNumber(total));
  }

  resetPurchaseModal() {
    this.$purchaseSupplierInput.val("");
    this.$purchaseSupplier.val("");
    this.$amountPaid.val("");
    this.showEmptyPurchaseItems();
    this.$purchaseTotal.text(this.app.formatNumber(0));
  }

  showEmptyPurchaseItems() {
    this.$purchaseItems.html("<tr><td colspan='5' class='text-center text-muted'>No items added</td></tr>");
  }

  appendSaleItemRow(item = {}) {
    const productId = Number(item.product_id) || 0;
    const qty = this.app.toNumber(item.qty, 0);
    const sellingPrice = this.app.toNumber(item.sellingPrice, 0);
    const selectedProduct = this.state.saleProducts.find((product) => Number(product.product_id) === productId) || null;
    const productName = selectedProduct ? String(selectedProduct.product_name || "") : "";
    const hasEmptyState = this.$saleItems.find("td[colspan='5']").length > 0;

    if (hasEmptyState) {
      this.$saleItems.empty();
    }

    const row = $(`
      <tr>
        <td>
          <input type="text" class="form-control saleProductInput" list="saleProductDatalist" placeholder="Type product name" value="${AppCore.escapeHtml(productName)}">
          <input type="hidden" class="saleProduct" value="${productId > 0 ? productId : ""}">
        </td>
        <td><input type="number" class="form-control saleQty" min="1" step="1" value="${qty > 0 ? qty : ""}"></td>
        <td><input type="number" class="form-control salePrice" min="0" step="0.01" value="${sellingPrice > 0 ? sellingPrice : ""}"></td>
        <td class="saleRowTotal">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger removeSaleItemBtn">&times;</button></td>
      </tr>
    `);

    this.$saleItems.append(row);
    this.syncSaleProductRow(row);
    this.updateSaleRow(row);
    this.recalculateSaleTotal();
  }

  updateSaleRow($row) {
    const qty = this.app.toNumber($row.find(".saleQty").val(), 0);
    const price = this.app.toNumber($row.find(".salePrice").val(), 0);
    const rowTotal = qty * price;
    $row.find(".saleRowTotal").text(this.app.formatNumber(rowTotal));
  }

  recalculateSaleTotal() {
    let total = 0;

    this.$saleItems.find("tr").each((_, rowEl) => {
      const $row = $(rowEl);
      if ($row.find(".saleQty").length === 0) {
        return;
      }

      const qty = this.app.toNumber($row.find(".saleQty").val(), 0);
      const price = this.app.toNumber($row.find(".salePrice").val(), 0);
      const rowTotal = qty * price;

      $row.find(".saleRowTotal").text(this.app.formatNumber(rowTotal));
      total += rowTotal;
    });

    this.$saleTotal.text(this.app.formatNumber(total));
  }

  showEmptySaleItems() {
    this.$saleItems.html("<tr><td colspan='5' class='text-center text-muted'>No items added</td></tr>");
  }

  resetSaleModal() {
    this.$saleCustomerInput.val("");
    this.$saleCustomer.val("");
    this.$saleAmountPaid.val("");
    this.showEmptySaleItems();
    this.$saleTotal.text(this.app.formatNumber(0));
  }

  openPurchaseModal() {
    const finalizeOpen = () => {
      this.resetPurchaseModal();
      this.appendPurchaseItemRow();
      this.$purchaseModal.modal("show");
    };

    const ensurePartners = () => {
      if (this.state.purchasePartners.length) {
        finalizeOpen();
        return;
      }
      this.loadPurchasePartners(() => finalizeOpen());
    };

    this.loadPurchaseProducts(() => ensurePartners());
  }

  openSaleModal() {
    const finalizeOpen = () => {
      this.resetSaleModal();
      this.appendSaleItemRow();
      this.$saleModal.modal("show");
    };

    const ensurePartners = () => {
      if (this.state.salePartners.length) {
        finalizeOpen();
        return;
      }
      this.loadSalePartners(() => finalizeOpen());
    };

    this.loadSaleProducts(() => ensurePartners());
  }

  savePurchase() {
    this.syncPurchaseSupplierFromInput();
    const partnerId = Number(this.$purchaseSupplier.val()) || 0;
    const items = [];

    this.$purchaseItems.find("tr").each((_, rowEl) => {
      const $row = $(rowEl);
      const productId = Number($row.find(".productSelect").val()) || 0;
      const qty = this.app.toNumber($row.find(".qty").val(), 0);
      const costPrice = this.app.toNumber($row.find(".cost").val(), 0);

      if (productId > 0 && qty > 0 && costPrice >= 0) {
        items.push({
          product_id: productId,
          qty,
          costPrice
        });
      }
    });

    if (partnerId <= 0) {
      this.app.showAlert("Please select a supplier.", "error");
      return;
    }

    if (!items.length) {
      this.app.showAlert("Add at least one valid purchase item.", "error");
      return;
    }

    const amountPaid = Math.max(0, this.app.toNumber(this.$amountPaid.val(), 0));
    const transactionDate = new Date().toISOString().slice(0, 10);

    this.postTransactions("createPurchase", {
      partner_id: partnerId,
      transaction_type: "buy",
      items: JSON.stringify(items),
      amountPaid,
      transaction_date: transactionDate
    }, () => {
      AppCore.safeHideModal("#purchaseModal");
      this.state.purchaseProducts = [];
      this.state.saleProducts = [];
      this.loadProducts(this.state.currentCategoryId || 0);
      if ($("#reorderTab").is(":visible")) {
        this.loadReorderSuggestions();
      }
      if (this.state.currentProduct?.product_id) {
        this.loadProductDetails(this.state.currentProduct.product_id);
      }
    });
  }

  saveSale() {
    this.syncSaleCustomerFromInput();
    const partnerId = Number(this.$saleCustomer.val()) || 0;
    const items = [];
    let hasStockError = false;

    this.$saleItems.find("tr").each((_, rowEl) => {
      const $row = $(rowEl);
      const $product = $row.find(".saleProduct");
      const productId = Number($product.val()) || 0;
      const qty = this.app.toNumber($row.find(".saleQty").val(), 0);
      const sellingPrice = this.app.toNumber($row.find(".salePrice").val(), 0);
      const selectedProduct = this.state.saleProducts.find((product) => Number(product.product_id) === productId) || null;
      const availableStock = this.app.toNumber(selectedProduct?.available_stock, 0);

      if (productId > 0 && qty > 0 && sellingPrice >= 0) {
        if (qty > availableStock) {
          this.app.showAlert(`Insufficient stock for ${$row.find(".saleProductInput").val() || "selected product"}.`, "error");
          hasStockError = true;
          items.length = 0;
          return false;
        }

        items.push({
          product_id: productId,
          qty,
          costPrice: sellingPrice
        });
      }
      return undefined;
    });

    if (hasStockError) {
      return;
    }

    if (partnerId <= 0) {
      this.app.showAlert("Please select a customer.", "error");
      return;
    }

    if (!items.length) {
      this.app.showAlert("Add at least one valid sale item.", "error");
      return;
    }

    const amountPaid = Math.max(0, this.app.toNumber(this.$saleAmountPaid.val(), 0));
    const transactionDate = new Date().toISOString().slice(0, 10);

    this.postTransactions("createSale", {
      partner_id: partnerId,
      transaction_type: "sell",
      items: JSON.stringify(items),
      amountPaid,
      transaction_date: transactionDate
    }, () => {
      AppCore.safeHideModal("#saleModal");
      this.state.purchaseProducts = [];
      this.state.saleProducts = [];
      this.loadProducts(this.state.currentCategoryId || 0);
      if ($("#reorderTab").is(":visible")) {
        this.loadReorderSuggestions();
      }
      if (this.state.currentProduct?.product_id) {
        this.loadProductDetails(this.state.currentProduct.product_id);
      }
    });
  }

  loadProductDetails(productId) {
    this.post("loadProductDetails", { product_id: productId }, (res) => {
      this.state.currentProduct = res.product || null;
      this.renderProductDetails(res.product || {});
      this.renderProductTransactions(res.transactions || []);
      this.renderProductStockMovement(res.stockMovement || []);
      // Reset toggle: show transactions, hide stock movement
      $("#productTransactionsContainer").show();
      $("#productStockMovementContainer").hide();
      $("#toggleProductDetailViewBtn").text("View Stock Movement");
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
            const displayName = String(item.display_label || item.product_name || "-");
            const displayUnit = String(item.sale_unit || item.purchase_unit || item.product_unit || "-");
            const normalizedUnit = this.normalizeAutocompleteText(displayUnit);
            const isFractionLine = normalizedUnit === "size" || normalizedUnit === "yard";
            const fractionQtyValue = this.app.toNumber(item.fraction_qty, 0);
            const displayQty = (isFractionLine && fractionQtyValue > 0)
              ? this.formatFractionValue(fractionQtyValue)
              : (item.qty || 0);
            return `
              <tr>
                <td>${displayName}</td>
                <td>${displayQty}</td>
                <td>${displayUnit}</td>
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
    const canSell = this.app.hasPermission("create_sales");
    const canDelete = this.app.hasPermission("delete_records");

    $("#addCategoryBtn, #addProductBtn, #editProductBtn").toggle(canManageCatalog);
    $("#newSaleBtn").toggle(canSell);
    $("#newPurchaseBtn").toggle(canRestock);
    $("#restockProductBtn").toggle(canRestock);
    $("#deleteProductBtn").toggle(canDelete);
  }

  renderProducts(rows) {
    this.$productsTable.empty();
    this.$productsCards.empty();

    if (!rows.length) {
      this.$productsTable.html("<tr><td colspan='10' class='text-center text-muted'>No products found</td></tr>");
      this.$productsCards.html("<div class='text-center text-muted py-3'>No products found</div>");
      return;
    }

    const tableHtml = rows.map((p) => {
      const image = this.app.resolveImagePath(p.product_image, "Images/productsDP", "Images/productsDP/product.jpg");
      const metrics = this.getProductStockPrediction(p);
      const name = p.product_name || "-";
      const category = p.category_name || "Uncategorized";
      const unit = p.product_unit || "pcs";
      const stockDisplay = this.formatStockDisplay(p);
      const fractionDisplay = this.formatFractionBalanceDisplay(p);

      return `
        <tr>
          <td><img src="${image}" width="40" class="image rounded"></td>
          <td>${name}</td>
          <td>${category}</td>
          <td>${unit}</td>
          <td>${metrics.reorder}</td>
          <td>${stockDisplay}</td>
          <td>${fractionDisplay}</td>
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
      const stockDisplay = this.formatStockDisplay(p);
      const fractionDisplay = this.formatFractionBalanceDisplay(p);

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
              <span>Qty: ${stockDisplay}</span>
              <span>Fraction: ${fractionDisplay}</span>
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
    $("#productQuantity").text(this.formatStockDisplay(product));
    $("#productFractionQty").text(this.formatFractionBalanceDisplay(product));
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

  renderReorderSuggestions(rows) {
    this.$reorderTableBody.empty();
    this.$reorderSuggestionsCards.empty();

    if (!rows.length) {
      this.$reorderTableBody.html("<tr><td colspan='4' class='text-center text-muted'>No suggestions</td></tr>");
      this.$reorderSuggestionsCards.html("<div class='text-center text-muted py-3'>No suggestions</div>");
      return;
    }

    const tableHtml = rows.map((p) => {
      const qty = this.app.toNumber(p.quantity, 0);
      const daysLeft = this.app.toNumber(p.days_left, 0);
      const suggestedQty = this.app.toNumber(p.suggested_qty, 0);

      return `
        <tr>
          <td>${p.product_name || "-"}</td>
          <td>${this.formatStockDisplay(p)}</td>
          <td>${daysLeft > 0 ? daysLeft : "-"}</td>
          <td><strong>${suggestedQty}</strong></td>
        </tr>
      `;
    }).join("");

    const cardsHtml = rows.map((p) => {
      const qty = this.app.toNumber(p.quantity, 0);
      const daysLeft = this.app.toNumber(p.days_left, 0);
      const suggestedQty = this.app.toNumber(p.suggested_qty, 0);

      return `
        <div class="card shadow-sm inventory-product-card mb-3">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h6 class="mb-0">${p.product_name || "-"}</h6>
                <small class="text-muted">Current Stock: ${this.formatStockDisplay(p)}</small>
              </div>
              <span class="badge badge-warning">${suggestedQty}</span>
            </div>

            <div class="inventory-product-meta text-muted mb-2">
              <span>Days Left: ${daysLeft > 0 ? daysLeft : "-"}</span>
              <span>Suggested Qty: ${suggestedQty}</span>
            </div>
          </div>
        </div>
      `;
    }).join("");

    this.$reorderTableBody.html(tableHtml);
    this.$reorderSuggestionsCards.html(cardsHtml);
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

  $("#newSaleBtn").on("click", function () {
    InventoryApp.openSaleModal();
  });

  $("#newPurchaseBtn").on("click", function () {
    InventoryApp.openPurchaseModal();
  });

  $("#viewReorderBtn").on("click", function () {
    InventoryApp.loadReorderSuggestions();
  });

  $("#backToCategoriesFromMovementBtn").on("click", function () {
    InventoryApp.loadCategories();
    InventoryApp.loadProducts(0);
    InventoryApp.showSection("home");
  });

  $("#backToHomeFromReorder").on("click", function () {
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

  $("#toggleProductDetailViewBtn").on("click", function () {
    const $btn = $(this);
    const $transactions = $("#productTransactionsContainer");
    const $stockMovement = $("#productStockMovementContainer");
    const showingTransactions = $transactions.is(":visible");
    if (showingTransactions) {
      $transactions.hide();
      $stockMovement.show();
      $btn.text("View Transactions");
    } else {
      $stockMovement.hide();
      $transactions.show();
      $btn.text("View Stock Movement");
    }
  });

  $("#backToCategoriesBtn").on("click", function () {
    InventoryApp.$productSearchInput.val("");
    InventoryApp.state.filteredProducts = InventoryApp.state.products;
    InventoryApp.$searchStatsIndicator.addClass("d-none").text("");
    InventoryApp.loadCategories();
    InventoryApp.loadProducts(0);
    InventoryApp.showSection("home");
  });

  $("#purchaseDetailsModal").on("hidden.bs.modal", function () {
    $(this).find("#payPurchaseForm").closest(".row").removeClass("d-none");
  });

  $("#addItemRow").on("click", function () {
    InventoryApp.appendPurchaseItemRow();
  });

  $("#addSaleItem").on("click", function () {
    InventoryApp.appendSaleItemRow();
  });

  InventoryApp.$purchaseSupplierInput.on("change blur", function () {
    InventoryApp.syncPurchaseSupplierFromInput();
  });

  InventoryApp.$saleCustomerInput.on("change blur", function () {
    InventoryApp.syncSaleCustomerFromInput();
  });

  $(document).on("change blur", ".purchaseProductInput", function () {
    const $row = $(this).closest("tr");
    InventoryApp.syncPurchaseProductRow($row);
    InventoryApp.updatePurchaseRow($row);
    InventoryApp.recalculatePurchaseTotal();
  });

  $(document).on("input", ".qty, .cost", function () {
    const $row = $(this).closest("tr");
    InventoryApp.updatePurchaseRow($row);
    InventoryApp.recalculatePurchaseTotal();
  });

  $(document).on("change blur", ".saleProductInput", function () {
    const $row = $(this).closest("tr");
    InventoryApp.syncSaleProductRow($row);
    InventoryApp.updateSaleRow($row);
    InventoryApp.recalculateSaleTotal();
  });

  $(document).on("input", ".saleQty, .salePrice", function () {
    const $row = $(this).closest("tr");
    InventoryApp.updateSaleRow($row);
    InventoryApp.recalculateSaleTotal();
  });

  $(document).on("click", ".removePurchaseItemBtn", function () {
    $(this).closest("tr").remove();
    if (!InventoryApp.$purchaseItems.find("tr").length) {
      InventoryApp.showEmptyPurchaseItems();
      InventoryApp.recalculatePurchaseTotal();
    } else {
      InventoryApp.recalculatePurchaseTotal();
    }
  });

  $(document).on("click", ".removeSaleItemBtn", function () {
    $(this).closest("tr").remove();
    if (!InventoryApp.$saleItems.find("tr").length) {
      InventoryApp.showEmptySaleItems();
      InventoryApp.recalculateSaleTotal();
    } else {
      InventoryApp.recalculateSaleTotal();
    }
  });

    $("#saveInventoryPurchaseBtn").on("click", function () {
    InventoryApp.savePurchase();
  });

  $("#saveSaleBtn").on("click", function () {
    InventoryApp.saveSale();
  });

  // Product search with debouncing
  InventoryApp.$productSearchInput.on("input", function () {
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
