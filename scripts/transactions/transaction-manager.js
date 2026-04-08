class TransactionManager {
    constructor() {
        this.app = new AppCore($('meta[name="csrf-token"]').attr('content') || "");
        this.validator = new FormValidator();
        this.debugMode = false;
        this.partners = [];
        this.products = [];
        this.transactionItems = [];
        this.transactionType = 'sell';
        this.historyRows = [];
        this.lastPurchaseDetailsTrigger = null;
        this.init();
    }

    debug(label, payload = null) {
        if (!this.debugMode) return;
        if (payload === null) {
            console.log(`[TransactionDebug] ${label}`);
            return;
        }
        console.log(`[TransactionDebug] ${label}`, payload);
    }

    setFieldError(fieldSelector, message = "", errorSelector = null) {
        if (this.validator && typeof this.validator.setFieldError === 'function') {
            this.validator.setFieldError(fieldSelector, message, errorSelector);
            return;
        }
        const $field = typeof fieldSelector === 'string' ? $(fieldSelector) : $(fieldSelector);
        if (message) {
            $field.addClass('is-invalid');
            return;
        }
        $field.removeClass('is-invalid');
    }

    clearFieldError(fieldSelector, errorSelector = null) {
        this.setFieldError(fieldSelector, '', errorSelector);
    }

    setTextError(errorSelector, message = "") {
        if (this.validator && typeof this.validator.setTextError === 'function') {
            this.validator.setTextError(errorSelector, message);
            return;
        }
        const $el = $(errorSelector);
        if (message) {
            $el.text(message).show();
        } else {
            $el.text('').hide();
        }
    }

    clearTextError(errorSelector) {
        this.setTextError(errorSelector, '');
    }

    init() {
        this.bindEvents();
        this.loadPartners();
        this.loadProducts();
        this.setDefaultDate();
        this.showAddTransactionTab();
        this.renderTransactionItems();
    }

    bindEvents() {
        const self = this;

        $('#addTransactionBtn').on('click', () => self.showAddTransactionTab());
        $('#viewTransactionsBtn').on('click', () => self.showTransactionHistoryTab());
        $('#savePurchaseBtn').on('click', () => self.saveTransaction());

        $('#transactionType').on('change', function () {
            self.transactionType = String($(this).val() || 'sell').toLowerCase();
            self.updateProductPrices();
            self.updateProcessButtonState();
        });

        $('#partner_name').on('input', function () {
            const query = String($(this).val() || '').toLowerCase().trim();
            if (!query) {
                $('#partnerSuggestions').hide();
                $('#partnerId').val('');
                self.updateProcessButtonState();
                return;
            }
            self.showPartnerSuggestions(query);
            self.updateProcessButtonState();
        });

        $('#partner_name').on('focus', function () {
            const query = String($(this).val() || '').toLowerCase().trim();
            if (query) self.showPartnerSuggestions(query);
        });

        $('#partner_name').on('blur', function () {
            setTimeout(() => {
                $('#partnerSuggestions').hide();
                self.resolvePartnerFromInput();
                self.updateProcessButtonState();
            }, 150);
        });

        $(document).on('click', '.partnerSuggestionItem', function (e) {
            e.preventDefault();
            const partnerId = $(this).data('partner-id');
            const partnerName = $(this).data('partner-name');
            $('#partner_name').val(partnerName);
            $('#partnerId').val(partnerId);
            $('#partnerSuggestions').hide();
            self.clearFieldError('#partner_name');
            self.updateProcessButtonState();
        });

        $('#purchaseProductInput').on('input', function () {
            const query = String($(this).val() || '').toLowerCase().trim();
            $('#purchaseProduct_id').val('');
            if (!query) {
                $('#productSuggestions').hide();
                $('#purchaseProduct_id').val('');
                return;
            }
            self.showProductSuggestions(query);
        });

        $('#purchaseProductInput').on('focus', function () {
            const query = String($(this).val() || '').toLowerCase().trim();
            if (query) self.showProductSuggestions(query);
        });

        $('#purchaseProductInput').on('blur', function () {
            setTimeout(() => {
                $('#productSuggestions').hide();
                self.resolveProductFromInput();
            }, 150);
        });

        $(document).on('click', '.productSuggestionItem', function () {
            const productId = Number($(this).data('product-id') || 0);
            const product = self.products.find(p => Number(p.product_id) === productId);
            if (!product) return;

            self.applySelectedProductToModal(product);
            $('#productSuggestions').empty().hide();
        });

        $('#addItemsForm').on('submit', function (e) {
            e.preventDefault();
            self.addItemFromModal();
        });

        $(document).on('input', '.qtyInput', function () {
            const index = Number($(this).closest('tr').data('index'));
            const qty = Number($(this).val()) || 0;
            self.updateItemQty(index, qty);
        });

        $(document).on('input', '.rateInput', function () {
            const index = Number($(this).closest('tr').data('index'));
            const rate = parseFloat($(this).val()) || 0;
            self.updateItemRate(index, rate);
        });

        $(document).on('click', '.removeItemBtn', function () {
            const index = Number($(this).closest('tr').data('index'));
            self.removeItem(index);
        });

        $('#paying').on('input', () => {
            self.updateBalance();
            self.updateProcessButtonState();
        });

        $('#transactionDate').on('change input', () => self.updateProcessButtonState());

        $('#searchPartner').on('input', () => self.filterTransactions());
        $('#filterStatus').on('change', () => self.filterTransactions());
        $('#filterFrom, #filterTo').on('change', () => self.filterTransactions());

        $('#resetFilters').on('click', function (e) {
            e.preventDefault();
            self.resetFilters();
        });

        $(document).on('click', '.viewTransactionBtn', function () {
            self.lastPurchaseDetailsTrigger = this;
            const transactionId = Number($(this).data('id') || 0);
            if (transactionId > 0) self.viewTransactionDetails(transactionId);
        });

        $(document).on('click', '.payTransactionBtn', function () {
            self.lastPurchaseDetailsTrigger = this;
            const transactionId = Number($(this).data('id') || 0);
            if (transactionId > 0) self.viewTransactionDetails(transactionId);
        });

        $('#payPurchaseForm').on('submit', function (e) {
            e.preventDefault();
            self.payPurchase();
        });

        $('#addItemsModal').on('hidden.bs.modal', function () {
            $('#addItemBtn').trigger('focus');
        });

        $('#purchaseDetailsModal').on('hidden.bs.modal', function () {
            const trigger = self.lastPurchaseDetailsTrigger;
            if (trigger && document.contains(trigger)) {
                trigger.focus();
                return;
            }
            $('#viewTransactionsBtn').trigger('focus');
        });
    }

    setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        $('#transactionDate').val(today);
    }

    showAddTransactionTab() {
        $('.tab-contents').addClass('d-none');
        $('#addTransactionTab').removeClass('d-none');
        $('#addTransactionBtn').removeClass('btn-outline-secondary').addClass('btn-primary');
        $('#viewTransactionsBtn').removeClass('btn-primary').addClass('btn-outline-secondary');
    }

    showTransactionHistoryTab() {
        $('.tab-contents').addClass('d-none');
        $('#transactionsHistoryTab').removeClass('d-none');
        $('#viewTransactionsBtn').removeClass('btn-outline-secondary').addClass('btn-primary');
        $('#addTransactionBtn').removeClass('btn-primary').addClass('btn-outline-secondary');
        this.loadTransactionHistory();
    }

    resetTransactionForm() {
        $('#addTransactionForm')[0].reset();

        const $transactionType = $('#transactionType');
        if ($transactionType.find('option[value="sell"]').length) {
            $transactionType.val('sell').trigger('change');
        } else {
            $transactionType.prop('selectedIndex', 0).trigger('change');
        }

        this.setDefaultDate();
        $('#partnerId').val('');
        $('#partnerSuggestions').hide();

        this.transactionItems = [];
        this.renderTransactionItems();

        $('#paying').val('');
        $('#remaining').val('0.00');

        this.clearAddItemModal();
    }

    clearAddItemModal() {
        $('#addItemsForm')[0].reset();
        $('#purchaseProduct_id').val('');
        $('#productUnitSelect').html('');
        $('#productSuggestions').empty().hide();
    }

    canSubmitTransaction() {
        const type = String($('#transactionType').val() || '').trim().toLowerCase();
        const partnerId = Number($('#partnerId').val() || 0);
        const transactionDate = String($('#transactionDate').val() || '').trim();
        const amountPaid = parseFloat($('#paying').val());

        if (!type) return false;
        if (!partnerId) return false;
        if (!transactionDate) return false;
        if (!this.transactionItems.length) return false;
        if (!Number.isNaN(amountPaid) && amountPaid < 0) return false;

        return this.transactionItems.every((item, index) => {
            const qty = parseFloat(item.qty) || 0;
            const rate = parseFloat(item.rate) || 0;
            const stockQty = this.getItemStockQty(item);

            if (qty <= 0 || rate <= 0) return false;
            if (type === 'sell' && !this.validateStockForSell(item.product_id, stockQty, index)) return false;
            return true;
        });
    }

    updateProcessButtonState() {
        $('#savePurchaseBtn').prop('disabled', !this.canSubmitTransaction());
    }
}
