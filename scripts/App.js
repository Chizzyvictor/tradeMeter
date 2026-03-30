// ============================
// AppCore (shared foundation)
// ============================
class AppCore {
  constructor(CSRF_TOKEN) {
    this.CSRF_TOKEN = CSRF_TOKEN;
    this.userPermissions = []; // Store user permissions

    this.productUnits = [
      { value: "sheet", label: "Sheet" },
      { value: "size", label: "Size" },
      { value: "yard", label: "Yard" },
      { value: "pcs", label: "Pieces (pcs)" },
      { value: "kg", label: "Kilograms (kg)" },
      { value: "g", label: "Grams (g)" },
      { value: "ltr", label: "Litres (ltr)" },
      { value: "ml", label: "Millilitres (ml)" },
      { value: "pack", label: "Pack" },
      { value: "box", label: "Box" },
      { value: "dozen", label: "Dozen" },
      { value: "pair", label: "Pair" },
      { value: "set", label: "Set" },
      { value: "roll", label: "Roll" },
      { value: "bag", label: "Bag" },
      { value: "carton", label: "Carton" },
      { value: "sachet", label: "Sachet" },
      { value: "bundle", label: "Bundle" }
    ];

    this.responseKeyAliases = {
      // Partner/profile style keys commonly lowercased by PostgreSQL drivers
      sname: "sName",
      semail: "sEmail",
      sphone: "sPhone",
      saddress: "sAddress",
      slogo: "sLogo",
      cname: "cName",
      cemail: "cEmail",
      clogo: "cLogo",
      regdate: "regDate",
      // Transaction/report keys
      totalamount: "totalAmount",
      amountpaid: "amountPaid",
      createdat: "createdAt",
      updatedat: "updatedAt",
      advancepayment: "advancePayment",
      lastactivity: "lastActivity",
      fullname: "fullName",
      transactiontype: "transactionType",
      partnername: "partnerName",
      partnerphone: "partnerPhone",
      purchaseid: "purchaseId",
      totalsales: "totalSales",
      totalpurchases: "totalPurchases",
      rangetransactions: "rangeTransactions",
      inventoryvalue: "inventoryValue",
      activedebtors: "activeDebtors",
      activecreditors: "activeCreditors",
      totalpartners: "totalPartners",
      topsellingproducts: "topSellingProducts",
      topsuppliers: "topSuppliers",
      topbuyers: "topBuyers"
    };
  }

  normalizeResponseKeys(payload) {
    if (Array.isArray(payload)) {
      return payload.map(item => this.normalizeResponseKeys(item));
    }

    if (!payload || typeof payload !== "object") {
      return payload;
    }

    const out = {};
    Object.keys(payload).forEach((key) => {
      const value = this.normalizeResponseKeys(payload[key]);
      out[key] = value;

      const lower = String(key).toLowerCase();

      const aliased = this.responseKeyAliases[lower];
      if (aliased && !(aliased in out)) {
        out[aliased] = value;
      }

      if (key.includes("_")) {
        const camel = key.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
        if (!(camel in out)) {
          out[camel] = value;
        }
      }
    });

    return out;
  }

  getResponseText(payload, fallback = "") {
    if (!payload || typeof payload !== "object") {
      return String(fallback || "");
    }

    const normalized = this.normalizeResponseKeys(payload);
    const text = normalized?.text ?? normalized?.message ?? fallback;
    return String(text || "");
  }

  getResponseReference(payload, fallback = "") {
    if (!payload || typeof payload !== "object") {
      return String(fallback || "");
    }

    const normalized = this.normalizeResponseKeys(payload);
    return String(normalized?.reference || fallback || "");
  }

  // Core utilities: AJAX, alerts, helpers, CSRF, etc.
	ajaxHelper({
		url = "",
		method = "POST",
		action = "",
		dir = "",
		data = {},
		onSuccess = null,
		onComplete = null,
		successMsg = "Operation successful.",
		errorMsg = "Operation failed.",
    timeout = 30000,
    silent = false
	} = {}) {
		if (!url || !action) {
			this.showAlert("ajaxHelper: url and action are required", "error");
			return;
		}

		const isFormData = data instanceof FormData;
		if (isFormData) {
			data.append("action", action);
			data.append("csrf_token", this.CSRF_TOKEN);
			if (dir) data.append("dir", dir);
		} else {
			data = { ...data, action, csrf_token: this.CSRF_TOKEN };
			if (dir) data.dir = dir;
		}

		$.ajax({
			url: url,
			type: method,
			data,
			timeout,
			processData: !isFormData,
			contentType: isFormData ? false : "application/x-www-form-urlencoded; charset=UTF-8",
			dataType: "json",
			success: (res) => {
        const normalizedRes = this.normalizeResponseKeys(res || {});
        const ok = normalizedRes?.status === "success";

        if (!ok && /session expired/i.test(this.getResponseText(normalizedRes))) {
					alert("Your session has expired. Please log in again.");
                   window.location.href = "login.php";
					return;
				}

        if (!silent) {
          this.showAlert(
          this.getResponseText(normalizedRes, ok ? successMsg : errorMsg),
            ok ? "success" : "error"
          );
        }

        if (ok && typeof onSuccess === "function") onSuccess(normalizedRes);
        if (typeof onComplete === "function") onComplete(normalizedRes);
			},
			error: (xhr, status, error) => {
        let msg = status === "timeout"
          ? "Request timeout. Please try again."
          : "Server error. Please try again.";

        const payload = xhr?.responseJSON || null;
        if (payload && typeof payload === "object") {
          msg = this.getResponseText(payload, msg);
          const reference = this.getResponseReference(payload);
          if (reference) {
            msg += ` (${reference})`;
          }
        } else if (xhr?.responseText) {
          try {
            const parsed = JSON.parse(xhr.responseText);
            msg = this.getResponseText(parsed, msg);
            const reference = this.getResponseReference(parsed);
            if (reference) {
              msg += ` (${reference})`;
            }
          } catch (_parseError) {
            // Keep generic message when response is not JSON.
          }
        }

				this.showAlert(msg, "error");
				console.error("AJAX Error:", status, error, xhr.responseText);
			},
			complete: () => {
				if (typeof onComplete === "function") onComplete();
			}
		});
	}
  
  showAlert(msg, type = "success") {
    $(".showAlert").remove();

    const alertClass = type === "success" ? "bg-success" : "bg-danger";
    const safeMsg = AppCore.escapeHtml(msg);

    const box = $(`
      <div id="alertBox" class="showAlert ${alertClass}"
           style="position:fixed;top:10px;right:10px;z-index:9999;
                  padding:12px;border-radius:6px;color:#fff;">
        <span>${safeMsg}</span>
        <button type="button" class="close"
                style="margin-left:10px;background:none;border:none;color:#fff;font-size:18px;">
          &times;
        </button>
      </div>
    `);

    $("body").append(box);

    box.find(".close").on("click", () => box.remove());
    setTimeout(() => box.fadeOut(() => box.remove()), 5000);
  }

  switchTab(tabId) {
    $(".tab-content").hide();
    $(`#${tabId}`).show();
  }

  formatNumber(num) {
    return (parseFloat(num) || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  static escapeHtml(text) {
    return $("<div>").text(text || "").html();
  }

  toNumber(value, fallback = 0) {
    const numeric = Number(value);
    return Number.isNaN(numeric) ? fallback : numeric;
  }

  formatDateSafe(value, fallback = "-") {
    if (value === null || value === undefined || value === "") return fallback;

    const numeric = Number(value);
    if (!Number.isNaN(numeric) && numeric > 0) {
      return new Date(numeric * 1000).toLocaleString();
    }

    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed.toLocaleString();
    }

    return fallback;
  }

  formatCurrency(amount) {
    return "₦" + this.formatNumber(amount);
  }

  static isAbsoluteUrl(value) {
    return /^https?:\/\//i.test(String(value || "").trim());
  }

  resolveImagePath(value, baseDir, fallback) {
    const trimmed = String(value || "").trim();
    if (AppCore.isAbsoluteUrl(trimmed)) {
      return trimmed;
    }

    const normalizedFallback = String(fallback || "").trim();
    if (!trimmed) {
      return normalizedFallback;
    }

    const normalizedBase = String(baseDir || "").replace(/\/+$/, "");
    return `${normalizedBase}/${trimmed}`;
  }

  static safeHideModal(modalSelector) {
    const modalElement = document.querySelector(modalSelector);
    if (!modalElement) return;

    const activeElement = document.activeElement;
    if (activeElement && modalElement.contains(activeElement)) {
      activeElement.blur();
    }

    $(modalSelector).modal('hide');
  }

  // Load user permissions from API
  loadUserPermissions(onSuccess = null) {
    this.ajaxHelper({
      url: "apiAuthentications.php",
      action: "getUserPermissions",
      onSuccess: (res) => {
        this.userPermissions = res.permissions || [];
        if (typeof onSuccess === "function") {
          onSuccess(this.userPermissions);
        }
        this.applyPermissionGates();
      },
      successMsg: "",
      errorMsg: "",
      silent: true
    });
  }

  // Check if user has a specific permission
  hasPermission(permissionKey) {
    return this.userPermissions.includes(permissionKey);
  }

  // Check if user has any of the specified permissions
  hasAnyPermission(...permissionKeys) {
    return permissionKeys.some(key => this.userPermissions.includes(key));
  }

  // Apply permission-based UI gating
  applyPermissionGates() {
    // Hide delete buttons if user doesn't have delete_records permission
    if (!this.hasPermission('delete_records')) {
      $('.btn-delete, [data-action="delete"], .delete-btn').hide();
    }

    // Hide settings nav and page if user doesn't have manage_users permission
    if (!this.hasPermission('manage_users')) {
      $('a[href="settings.php"]').parent().hide();
      $('#settingsTab, [data-tab="settings"]').hide();
    }

    // Hide report sections if user doesn't have view_reports permission
    if (!this.hasPermission('view_reports')) {
      $('a[href="index.php"], .navbar-brand[href="index.php"]').hide();
      $('#dashboardReports, .reports-section, [data-section="reports"]').hide();
    }

    // Hide purchase creation if user doesn't have create_purchases permission
    if (!this.hasPermission('create_purchases')) {
      $('#createPurchaseBtn, [data-action="createPurchase"], .btn-purchase').hide();
    }
  }
}

// ============================
// FormValidator
// ============================
class FormValidator {
  constructor() {
    this.rules = {
      email: {
        required: true,
        regex: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
        msg: "Invalid email address"
      },
      password: {
        required: true,
        regex: /^.{6,25}$/,
        msg: "Password must be 6–25 characters"
      },
      phone: {
        required: true,
        regex: /^\+?[0-9\s-]{7,15}$/,
        msg: "Invalid phone number"
      },
      amount: {
        required: true,
        regex: /^\d+(\.\d{1,2})?$/,
        msg: "Invalid amount",
        validate: v => v >= 100 && v <= 5000000
      },
      name: {
        required: true,
        regex: /^[a-zA-Z0-9 .'-]{2,50}$/,
        msg: "Name must be 2–50 letters/numbers"
      },
      address: {
        required: true,
        regex: /^.{5,100}$/,
        msg: "Address must be 5–100 characters"
      },
      answer: {
        required: true,
        regex: /^.{1,200}$/,
        msg: "Answer required (max 200 chars)"
      },
      description: {
        required: false,
        regex: /^.{0,200}$/,
        msg: "Description max 200 characters"
      },
      category: {
        required: true,
        regex: /^[a-zA-Z0-9 .'-]{2,30}$/,
        msg: "Category must be 2–30 letters/numbers"
      },
      product: {
        required: true,
        regex: /^[a-zA-Z0-9 .'-]{2,50}$/,
        msg: "Product name must be 2–50 letters/numbers"
      }
    };

    this.initPasswordToggle();
  }

  setFieldError(fieldSelector, message = "", errorSelector = null) {
    const $field = typeof fieldSelector === "string" ? $(fieldSelector) : $(fieldSelector);
    let targetErrorSelector = errorSelector;
    if (!targetErrorSelector && typeof fieldSelector === "string") {
      targetErrorSelector = `${fieldSelector}_err`;
    }
    const $error = targetErrorSelector ? $(targetErrorSelector) : $();
    if (message) {
      $field.addClass("is-invalid");
      if ($error.length) $error.text(message).show();
      else $field.attr("title", message);
      $field.attr("aria-invalid", "true");
      return;
    }
    $field.removeClass("is-invalid");
    if ($error.length) $error.text("").hide();
    $field.removeAttr("aria-invalid");
    $field.removeAttr("title");
  }

  setTextError(errorSelector, message = "") {
    const $el = $(errorSelector);
    if (message) {
      $el.text(message).show();
    } else {
      $el.text("").hide();
    }
  }

  validateField(el, rule, errSel) {
    const $el = $(el);
    let val = $el.length ? $el.val() : "";
    if (typeof val !== "string") val = "";
    val = val.trim();
    let ok = true;
    if (!$el.length) {
      // Field not found in form, warn in console
      console.warn("FormValidator: Field not found for selector:", el);
      ok = false;
    }
    if (rule.required && !val) ok = false;
    if (ok && rule.regex && !rule.regex.test(val)) ok = false;
    if (ok && rule.validate && !rule.validate(parseFloat(val))) ok = false;
    this.setFieldError($el, ok ? "" : rule.msg, errSel);
    return ok;
  }

  validateForm($form, rulesMap) {
    let valid = true;
    for (const [field, rule] of Object.entries(rulesMap)) {
      const el = $form.find(`[name='${field}']`);
      const errSel = `#${field}_err`;
      if (!this.validateField(el, rule, errSel)) valid = false;
    }
    return valid;
  }

  initPasswordToggle() {
    $("input[type='password']").each(function () {
      const input = $(this);
      if (input.next(".toggle-pass").length) return;

      input.wrap('<div style="position:relative"></div>');
      const btn = $('<span class="toggle-pass">👁️</span>')
        .css({ position: "absolute", right: "10px", top: "50%", cursor: "pointer" })
        .insertAfter(input);

      btn.on("click", () => {
        input.attr("type", input.attr("type") === "password" ? "text" : "password");
        btn.text(input.attr("type") === "password" ? "👁️" : "🙈");
      });
    });
  }
}

// ============================
// Auth
// ============================
class Auth {
  constructor(appCore) { this.app = appCore; }
  login(data) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "login",
      data,
      onSuccess: () => location.href = "index.php"
    });
  }
  signup(data) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "signup",
      data,
      onSuccess: () => this.app.switchTab("loginTab")
    });
  }
  logout() {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "logout",
      onSuccess: () => location.href = "login.php"
    });
  }
  loadCompanyLogo() {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "cLogo",
      onSuccess: res => {
        if (res.data) {
          const src = this.app.resolveImagePath(res.data, "Images/companyDP", "Images/companyDP/logo.jpg");
          $("#cLogo").attr("src", src);
        }
      }
    });
  }
  requestPasswordReset(email) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "requestPasswordReset",
      data: {"fEmail" : email},
      onSuccess: res => {
       $("#fQuestion").val(res.question);
       this.app.switchTab("forgotQandATab");
       }
    });
  }
  forgotQandA(answer) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "forgotQandA",
      data: { answer },
      onSuccess: res => this.app.switchTab("resetPwdTab")
    });
  }
  resetPassword(password) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "resetPassword",
      data: {"pwd" : password},
      onSuccess: () => this.app.switchTab("loginTab")
    });
  }

  loadCurrentUserContext(onSuccess = null) {
    this.app.ajaxHelper({
      url: "apiAuthentications.php",
      action: "getCurrentUserContext",
      data: {},
      silent: true,
      onSuccess: (res) => {
        if (typeof onSuccess === "function") {
          onSuccess(res.user || null);
        }
      }
    });
  }
}

// ============================
// Dashboard
// ============================
class Dashboard {
  constructor(appCore) {
    this.app = appCore;
    this.lastUpdatedAt = null;
    this.lastUpdatedTicker = null;
  }

  formatRelativeTime() {
    if (!this.lastUpdatedAt) return "Last updated: --";

    const elapsedMs = Date.now() - this.lastUpdatedAt.getTime();
    const elapsedSeconds = Math.max(0, Math.floor(elapsedMs / 1000));

    if (elapsedSeconds < 5) return "Last updated: just now";
    if (elapsedSeconds < 60) return `Last updated: ${elapsedSeconds}s ago`;

    const elapsedMinutes = Math.floor(elapsedSeconds / 60);
    if (elapsedMinutes < 60) return `Last updated: ${elapsedMinutes}m ago`;

    const elapsedHours = Math.floor(elapsedMinutes / 60);
    if (elapsedHours < 24) return `Last updated: ${elapsedHours}h ago`;

    const elapsedDays = Math.floor(elapsedHours / 24);
    return `Last updated: ${elapsedDays}d ago`;
  }

  updateLastUpdatedLabel() {
    $("#dashboardLastUpdated").text(this.formatRelativeTime());
  }

  startLastUpdatedTicker() {
    if (this.lastUpdatedTicker) return;
    this.lastUpdatedTicker = setInterval(() => {
      this.updateLastUpdatedLabel();
    }, 1000);
  }

  rangeLabel(range) {
    const labels = {
      today: "Today",
      "7d": "Last 7 Days",
      "30d": "Last 30 Days",
      all: "All Time"
    };
    return labels[range] || "All Time";
  }

  renderTopTable(selector, rows, mapRow, colspan = 3) {
    const $tbody = $(selector);
    if (!$tbody.length) return;

    if (!Array.isArray(rows) || !rows.length) {
      $tbody.html(`<tr><td colspan="${colspan}" class="empty-row">No data found</td></tr>`);
      return;
    }

    const html = rows.map(mapRow).join("");
    $tbody.html(html);
  }

  loadDashboard(range = "all") {
    const selectedRange = String(range || "all").toLowerCase();
    this.app.ajaxHelper({
      url: "apiRequest.php",
      action: "loadDashboard",
      data: { range: selectedRange },
      onSuccess: res => {
        if (!res || res.status !== "success") return;

    $("#dashboardRangeLabel").text(`Showing: ${this.rangeLabel(selectedRange)}`);
    this.lastUpdatedAt = new Date();
    this.updateLastUpdatedLabel();
    this.startLastUpdatedTicker();

     $("#loadOutstanding").html(this.app.formatCurrency(res.outstanding));
     $("#loadAdvancePayments").html(this.app.formatCurrency(res.advancePayment));
     $("#loadActiveDebtors").html(this.app.toNumber(res.activeDebtors));
     $("#loadActiveCreditors").html(this.app.toNumber(res.activeCreditors));
     $("#loadTotalSales").html(this.app.formatCurrency(res.totalSales));
     $("#loadTotalPurchases").html(this.app.formatCurrency(res.totalPurchases));
     $("#loadTodayTransactions").html(this.app.toNumber(res.rangeTransactions));
     $("#loadInventoryValue").html(this.app.formatCurrency(res.inventoryValue));
     const profit = this.app.toNumber(res.profit, 0);
     const profitIsPositive = profit >= 0;
     $("#loadProfit").html(this.app.formatCurrency(profit));
     $("#loadProfit")
       .removeClass("text-success text-danger")
       .addClass(profitIsPositive ? "text-success" : "text-danger");
     $("#loadProfitIconWrap")
       .removeClass("bg-success bg-danger text-success text-danger")
       .addClass(profitIsPositive ? "bg-success text-success" : "bg-danger text-danger");

     this.renderTopTable("#topSellingProductsTable tbody", res.topSellingProducts, (item) => {
       const qty = this.app.toNumber(item.total_qty, 0);
       const amount = this.app.formatCurrency(item.total_amount || 0);
       return `
         <tr>
           <td>${item.product_name || "-"}</td>
           <td class="text-right">${qty}</td>
           <td class="text-right">${amount}</td>
         </tr>
       `;
     });

     this.renderTopTable("#topSuppliersTable tbody", res.topSuppliers, (item) => {
       const txns = this.app.toNumber(item.transactions, 0);
       const amount = this.app.formatCurrency(item.total_amount || 0);
       return `
         <tr>
           <td>${item.sName || "-"}</td>
           <td class="text-right">${txns}</td>
           <td class="text-right">${amount}</td>
         </tr>
       `;
     });

     this.renderTopTable("#topBuyersTable tbody", res.topBuyers, (item) => {
       const txns = this.app.toNumber(item.transactions, 0);
       const amount = this.app.formatCurrency(item.total_amount || 0);
       return `
         <tr>
           <td>${item.sName || "-"}</td>
           <td class="text-right">${txns}</td>
           <td class="text-right">${amount}</td>
         </tr>
       `;
     });
      }
    });
  }
}

$(document).on('hide.bs.modal', '.modal', function () {
  const activeElement = document.activeElement;
  if (activeElement && this.contains(activeElement)) {
    activeElement.blur();
  }
});

// Example instantiation:
// const AppCoreInstance = new AppCore(csrf_token);
// const DashboardInstance = new Dashboard(AppCoreInstance);
// const InventoryInstance = new Inventory(AppCoreInstance);
// const PartnersInstance = new Partners(AppCoreInstance);
// const AuthInstance = new Auth(AppCoreInstance);
// const ValidatorInstance = new FormValidator();
