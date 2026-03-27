// ============================
// Partners Manager
// ============================
class Partners {

  constructor(appCore) {

    this.app = appCore;

    this.state = {
      partners: [],
      currentPartner: null,
      currentAction: "loadAllPartners",
      partnerDetails: null,
      ledgers: [],
      purchases: [],
      viewMode: "ledger" // or "transactions"
    };

    this.bindUI();
  }

  // ============================
  // LOAD PARTNERS
  // ============================
  loadPartners(action = "loadAllPartners", onSuccess = null) {

    this.state.currentAction = action;

    this.app.ajaxHelper({
      url: "apiPartners.php",
      action: action,

      onSuccess: res => {

        if (!res || res.status !== "success") return;

        this.state.partners = res.data || [];

        this.renderPartners();

        if (typeof onSuccess === "function") {
          onSuccess(this.state.partners);
        }

      }
    });
  }

  // ============================
  // RENDER PARTNERS TABLE
  // ============================
  renderPartners() {

    const tbody = $("#partnersTable tbody");
    const cards = $("#partnersCards");

    tbody.empty();
    cards.empty();

    const partners = this.state.partners;

    if (!partners.length) {

      tbody.html("<tr><td colspan='3'>No partners found</td></tr>");
      cards.html("<div class='text-center text-muted p-3'>No partners found</div>");
      $("#totalPartners").text("0 Partners");
      return;
    }

    $("#totalPartners").text(`${partners.length} Partner${partners.length > 1 ? "s" : ""}`);
    const amountCell = (p) => {
      const outstanding = this.app.toNumber(p.outstanding, 0);
      const advance = this.app.toNumber(p.advancePayment, 0);
      if (outstanding > 0) return `<span class="text-danger">-${this.app.formatCurrency(outstanding)}</span>`;
      if (advance > 0) return `<span class="text-success">+${this.app.formatCurrency(advance)}</span>`;
      return '<span class="text-muted">₦0.00</span>';
    };

    const tableRows = [];
    const cardRows = [];

    partners.forEach(p => {
      const logo = this.app.resolveImagePath(p.sLogo, "Images/partnersDP", "Images/partnersDP/user.jpg");

      tableRows.push(`
        <tr class="partnerRow" data-id="${p.sid}">
          <td>
            <img src="${logo}"
              width="40"
              class="image rounded-circle"
            >
          </td>

          <td>${p.sName}</td>

          <td>${amountCell(p)}</td>
        </tr>
      `);

      cardRows.push(`
        <div class="card shadow-sm partners-mobile-card mb-3 partnerRow" data-id="${p.sid}">
          <div class="card-body p-3 d-flex align-items-center">
            <img src="${logo}" width="44" class="image rounded-circle mr-2">
            <div class="flex-grow-1">
              <h6 class="mb-1">${p.sName}</h6>
              <div>${amountCell(p)}</div>
            </div>
          </div>
        </div>
      `);

    });

    tbody.html(tableRows.join(""));
    cards.html(cardRows.join(""));

  }

  // ============================
  // LOAD PARTNER DETAILS
  // ============================
  loadPartnerDetails(id, onLoaded = null) {

    this.app.ajaxHelper({
      url: "apiPartners.php",
      action: "loadPartnerDetails",
      data: { id: id },

      onSuccess: res => {

        if (!res || res.status !== "success") return;

        const data = res || {};
        
        this.state.partnerDetails = data.partner || null;
        this.state.currentPartner = data.partner.sid || null;
        this.state.ledgers = data.partner_ledger || [];
        this.state.purchases = data.purchases || [];

        this.renderPartnerDetails();
        this.renderLedgers();
        this.renderPurchases();
        this.app.switchTab("aDetails");

        if (typeof onLoaded === "function") {
          onLoaded(data);
        }

      }
    });
  }

  // ============================
  // REFRESH CURRENT PARTNER
  // ============================
  refreshPartner() {

    if (!this.state.currentPartner) return;

    this.loadPartnerDetails(this.state.currentPartner);
    this.loadPartners(this.state.currentAction);
  }

  // ============================
  // RENDER PARTNER DETAILS
  // ============================
  renderPartnerDetails() {

    const partner = this.state.partnerDetails;

    if (!partner) return;

    const logo = this.app.resolveImagePath(partner.sLogo, "Images/partnersDP", "Images/partnersDP/user.jpg");
    const html = `
      <div class="card my-3">
      <div class="card-header d-flex align-items-center">
      <img src="${logo}"
        width="50"
        class="image rounded-circle"
      >
        <h5 class="mb-0 ml-3">${partner.sName}</h5>
      </div>
        <div class="card-body">
          <p class="mb-1"><strong>Email:</strong> ${partner.sEmail || 'N/A'}</p>
          <p class="mb-1"><strong>Phone:</strong> ${partner.sPhone || 'N/A'}</p>
          <p class="mb-1"><strong>Address:</strong> ${partner.sAddress || 'N/A'}</p>
          <p class="mb-1 text-danger"><strong>Outstanding:</strong> ${this.app.formatCurrency(partner.outstanding || 0)}</p>
          <p class="mb-1 text-success"><strong>Advance Payment:</strong> ${this.app.formatCurrency(partner.advancePayment || 0)}</p>
        </div>
      </div>
    `;

    $("#partnerDetails").html(html);
    
  }

  // ============================
  // RENDER LEDGER
  // ============================
  renderLedgers() {

    const container = $("#ledgerBody tbody");
    const cards = $("#partnerLedgerCards");

    container.empty();
    cards.empty();

    const ledgers = this.state.ledgers;

    if (!ledgers.length) {

      container.html("<tr><td colspan='7'>No ledger entries.</td></tr>");
      cards.html("<div class='text-center text-muted p-3'>No ledger entries.</div>");
      return;
    }

    const tableRows = [];
    const cardRows = [];

    ledgers.forEach(l => {
      const entryTypeRaw = String(l.type || "").trim();
      const entryType = entryTypeRaw.toLowerCase();
      const entryLabel = {
        sell: "SELL",
        sale: "SELL",
        buy: "BUY",
        purchase: "BUY",
        adddebt: "ADD DEBT",
        add_debt: "ADD DEBT",
        paydebt: "PAY DEBT",
        pay_debt: "PAY DEBT"
      }[entryType] || entryTypeRaw.toUpperCase();

      let badgeType = "secondary";

      if (["sell", "sale"].includes(entryType)) badgeType = "info";
      else if (["buy", "purchase"].includes(entryType)) badgeType = "warning";
      else if (["adddebt", "add_debt"].includes(entryType)) badgeType = "danger";
      else if (["paydebt", "pay_debt"].includes(entryType)) badgeType = "success";

      tableRows.push(`
        <tr data-id="${l.ledger_id}">
          <td><span class="badge badge-${badgeType}">
            ${entryLabel}
          </span></td>

          <td>${this.app.formatDateSafe(l.createdAt)}</td>

          <td class="text-danger">
            ${this.app.formatCurrency(l.debit)}
          </td>

          <td class="text-success">
            ${this.app.formatCurrency(l.credit)}
          </td>

          <td class="text-warning">
            ${this.app.formatCurrency(l.outstanding)}
          </td>

          <td class="text-info">
            ${this.app.formatCurrency(l.advancePayment)}
          </td>

          <td>${l.note || ""}</td>
        </tr>
      `);

      cardRows.push(`
        <div class="card shadow-sm partners-mobile-card mb-3" data-id="${l.ledger_id}">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge badge-${badgeType}">${entryLabel}</span>
              <small class="text-muted">${this.app.formatDateSafe(l.createdAt)}</small>
            </div>
            <div class="partners-mobile-meta text-muted mb-2">
              <span>Debit: ${this.app.formatCurrency(l.debit)}</span>
              <span>Credit: ${this.app.formatCurrency(l.credit)}</span>
              <span>Outstanding: ${this.app.formatCurrency(l.outstanding)}</span>
              <span>Advance: ${this.app.formatCurrency(l.advancePayment)}</span>
            </div>
            <div>${l.note || ""}</div>
          </div>
        </div>
      `);

    });

    container.html(tableRows.join(""));
    cards.html(cardRows.join(""));
  }

  // ============================
  // RENDER PURCHASES
  // ============================
  renderPurchases() {

    const container = $("#transactionsBody tbody");
    const cards = $("#partnerTransactionsCards");

    container.empty();
    cards.empty();

    const purchases = this.state.purchases;

    if (!purchases.length) {

      container.html("<tr><td colspan='5'>No purchases.</td></tr>");
      cards.html("<div class='text-center text-muted p-3'>No purchases.</div>");
      return;
    }

    const tableRows = [];
    const cardRows = [];

    purchases.forEach(p => {

      const txType = String(p.transaction_type || "buy").toLowerCase();
      const txLabel = ["buy", "purchase"].includes(txType) ? "BUY" : (["sell", "sale"].includes(txType) ? "SELL" : txType.toUpperCase());

      let badgeType = "secondary";

      if (["buy", "purchase"].includes(txType)) badgeType = "primary";
      if (["sell", "sale"].includes(txType)) badgeType = "success";

      tableRows.push(`
        <tr data-id="${p.purchase_id}">
          <td>
            <span class="badge badge-${badgeType}">
              ${txLabel}
            </span>
          </td>

          <td>${this.app.formatDateSafe(p.createdAt)}</td>

          <td>${this.app.formatCurrency(p.totalAmount)}</td>

          <td>${this.app.formatCurrency(p.amountPaid)}</td>

          <td>${p.status}</td>
        </tr>
      `);

      cardRows.push(`
        <div class="card shadow-sm partners-mobile-card mb-3" data-id="${p.purchase_id}">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="badge badge-${badgeType}">${txLabel}</span>
              <small class="text-muted">${this.app.formatDateSafe(p.createdAt)}</small>
            </div>
            <div class="partners-mobile-meta text-muted">
              <span>Total: ${this.app.formatCurrency(p.totalAmount)}</span>
              <span>Paid: ${this.app.formatCurrency(p.amountPaid)}</span>
              <span>Status: ${p.status}</span>
            </div>
          </div>
        </div>
      `);

    });

    container.html(tableRows.join(""));
    cards.html(cardRows.join(""));

  }

  // ============================
  // SEARCH PARTNERS
  // ============================
  searchPartners(keyword) {

    keyword = keyword.toLowerCase();

    const filtered = this.state.partners.filter(p =>
      p.sName.toLowerCase().includes(keyword)
    );

    const tbody = $("#partnersTable tbody");
    const cards = $("#partnersCards");

    tbody.empty();
    cards.empty();

    if (!filtered.length) {
      tbody.html("<tr><td colspan='3'>No partners found</td></tr>");
      cards.html("<div class='text-center text-muted p-3'>No partners found</div>");
      return;
    }

    const amountCell = (p) => {
      const outstanding = this.app.toNumber(p.outstanding, 0);
      const advance = this.app.toNumber(p.advancePayment, 0);
      if (outstanding > 0) return `<span class="text-danger">-${this.app.formatCurrency(outstanding)}</span>`;
      if (advance > 0) return `<span class="text-success">+${this.app.formatCurrency(advance)}</span>`;
      return '<span class="text-muted">₦0.00</span>';
    };

    const tableRows = [];
    const cardRows = [];

    filtered.forEach(p => {
      const logo = this.app.resolveImagePath(p.sLogo, "Images/partnersDP", "Images/partnersDP/user.jpg");

      tableRows.push(`
        <tr class="partnerRow" data-id="${p.sid}">
          <td>
            <img src="${logo}"
              width="40"
              class="image rounded-circle"
            >
          </td>
          <td>${p.sName}</td>
          <td>${amountCell(p)}</td>
        </tr>
      `);

      cardRows.push(`
        <div class="card shadow-sm partners-mobile-card mb-3 partnerRow" data-id="${p.sid}">
          <div class="card-body p-3 d-flex align-items-center">
            <img src="${logo}" width="44" class="image rounded-circle mr-2">
            <div class="flex-grow-1">
              <h6 class="mb-1">${p.sName}</h6>
              <div>${amountCell(p)}</div>
            </div>
          </div>
        </div>
      `);
    });

    tbody.html(tableRows.join(""));
    cards.html(cardRows.join(""));
  }

  // ============================
  // EVENT BINDINGS
  // ============================
  bindUI() {

    const self = this;

    // open partner details
    $(document).on("click", ".partnerRow", function () {
      const id = $(this).data("id");
      self.loadPartnerDetails(id);
    });

    // filters
    $(".cfilter-btn").on("click", function () {

      $(".cfilter-btn").removeClass("active");

      $(this).addClass("active");

      const action = $(this).data("action");

      self.loadPartners(action);

    });

    // search
    $("#searchPartners").on("keyup", function () {

      self.searchPartners($(this).val());

    });

    // back buttons
    $("#back").on("click", () => {
      this.state.currentPartner = null;
      this.state.partnerDetails = null;
      this.state.ledgers = [];
      this.state.purchases = [];
      this.loadPartners(this.state.currentAction);
        this.app.switchTab("home");
    });


    // add debt
    $("#addDebt").on("click", () => {

      if (!this.state.currentPartner) return;

      $("#debtAmount").val("");

      $("#debtDesc").val("");

      $("#addDebtModal").modal("show");

    });

    // pay debt
    $("#payDebt").on("click", () => {

      if (!this.state.currentPartner) return;

      $("#payDebtAmount").val("");

      $("#payDesc").val("");

      $("#payDebtModal").modal("show");

    });

    // edit partner
    $("#editPartnerBtn").on("click", () => {

      if (!this.state.currentPartner) return;

      const p = this.state.partnerDetails;

      $("#editPartnerName").val(p.sName || "");

      $("#editPartnerEmail").val(p.sEmail || "");

      $("#editPartnerPhone").val(p.sPhone || "");

      $("#editPartnerAddress").val(p.sAddress || "");

      $("#editPartnerImage").val("");

      $("#editPartnerModal").modal("show");

    });

    // form submits
    $("#addPartnerForm").on("submit", (e) => {

      e.preventDefault();

      const formData = new FormData();
      formData.append("aName", $("#partnerName").val().trim());
      formData.append("aEmail", $("#partnerEmail").val().trim());
      formData.append("aAddress", $("#partnerAddress").val().trim());
      formData.append("aPhone", $("#partnerPhone").val().trim());

      const imageFile = $("#partnerImage")[0]?.files?.[0];
      if (imageFile) {
        formData.append("partnerImage", imageFile);
      }

      this.app.ajaxHelper({

        url: "apiPartners.php",

        action: "addPartner",

        data: formData,

        dir: "partnersDP",

        onSuccess: (res) => {

          AppCore.safeHideModal("#addPartnerModal");

          $("#addPartnerForm")[0].reset();

          this.app.showAlert("Partner added successfully", "success");

          this.loadPartners(this.state.currentAction);

        }

      });

    });

    $("#editPartnerForm").on("submit", (e) => {

      e.preventDefault();

      const formData = new FormData();
      formData.append("id", this.state.currentPartner);
      formData.append("aName", $("#editPartnerName").val().trim());
      formData.append("aEmail", $("#editPartnerEmail").val().trim());
      formData.append("aAddress", $("#editPartnerAddress").val().trim());
      formData.append("aPhone", $("#editPartnerPhone").val().trim());

      const imageFile = $("#editPartnerImage")[0]?.files?.[0];
      if (imageFile) {
        formData.append("editPartnerImage", imageFile);
      }

      this.app.ajaxHelper({

        url: "apiPartners.php",

        action: "editPartner",

        data: formData,

        dir: "partnersDP",

        onSuccess: (res) => {

          AppCore.safeHideModal("#editPartnerModal");

          this.app.showAlert("Partner updated successfully", "success");

          this.refreshPartner();


        }

      });

    });

    $("#addDebtForm").on("submit", (e) => {

      e.preventDefault();
      const $submitBtn = $("#addDebtForm button[type='submit']");
      $submitBtn.prop("disabled", true);

      const data = {

        id: this.state.currentPartner,

        amount: $("#debtAmount").val(),

        debtDesc: $("#debtDesc").val().trim()

      };

      this.app.ajaxHelper({

        url: "apiPartners.php",

        action: "addDebt",

        data: data,

        onSuccess: (res) => {

          AppCore.safeHideModal("#addDebtModal");

          this.app.showAlert("Debt added successfully", "success");

          this.refreshPartner();

        },
        onComplete: () => {
          $submitBtn.prop("disabled", false);
        }

      });

    });

    $("#payDebtForm").on("submit", (e) => {

      e.preventDefault();
      const $submitBtn = $("#payDebtForm button[type='submit']");
      $submitBtn.prop("disabled", true);

      const data = {

        id: this.state.currentPartner,

        amount: $("#payDebtAmount").val(),

        payDesc: $("#payDesc").val().trim()

      };

      this.app.ajaxHelper({

        url: "apiPartners.php",

        action: "payDebt",

        data: data,

        onSuccess: (res) => {

          AppCore.safeHideModal("#payDebtModal");

          this.app.showAlert("Payment recorded successfully", "success");

          this.refreshPartner();

        },
        onComplete: () => {
          $submitBtn.prop("disabled", false);
        }

      });

    });

  }

}

$(document).ready(function() {
    
    const csrf_token = $('meta[name="csrf-token"]').attr('content') || "";
    const App = new AppCore(csrf_token);
    
    const AuthApp = new Auth(App);
    const PartnersApp = new Partners(App);

    PartnersApp.loadPartners();

    const queryParams = new URLSearchParams(window.location.search);
    const deepPartnerId = Number(queryParams.get("partnerId") || 0);
    const openModal = String(queryParams.get("openModal") || "").trim();

    if (deepPartnerId > 0) {
      PartnersApp.loadPartnerDetails(deepPartnerId, () => {
        if (openModal === "payDebt") {
          $("#payDebtAmount").val("");
          $("#payDesc").val("");
          $("#payDebtModal").modal("show");
        } else if (openModal === "addDebt") {
          $("#debtAmount").val("");
          $("#debtDesc").val("");
          $("#addDebtModal").modal("show");
        }

        if (window.history && typeof window.history.replaceState === "function") {
          window.history.replaceState({}, document.title, "partners.php");
        }
      });
    }

    $(document).off('click', '#viewMode').on('click', '#viewMode', function() {
        const currentMode = PartnersApp.state.viewMode;
        const newMode = currentMode === "transactions" ? "ledger" : "transactions";
        PartnersApp.state.viewMode = newMode;
          $(".viewMode").hide();
        if (newMode === "transactions") {
           $("#viewMode").text("VIEW PARTNER LEDGER");
            $("#transactionsView").show();
        } else {
            $("#viewMode").text("VIEW PARTNER PURCHASES");
            $("#ledgerView").show();
        }
    });

     if(PartnersApp.state.viewMode === "transactions") {
        $("#transactionsView").show();
        $("#viewMode").text("VIEW PARTNER LEDGER");
    } else {
        $("#ledgerView").show();
        $("#viewMode").text("VIEW PARTNER PURCHASES");
    }

});