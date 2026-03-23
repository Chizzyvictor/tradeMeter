TransactionManager.prototype.addItemFromModal = function () {
    let productId = Number($('#purchaseProduct_id').val() || 0);
    const qty = Number($('#qty').val()) || 0;
    const rate = parseFloat($('#rate').val()) || 0;

    let product = this.products.find(p => Number(p.product_id) === productId);

    if (!productId || !product) {
        const resolved = this.resolveProductFromInput();
        if (resolved) {
            productId = Number(resolved.product_id);
            product = resolved;
        }
    }

    if (!productId || !product) {
        this.debug('addItemFromModal: invalid product selection', {
            productId,
            typedProduct: $('#purchaseProductInput').val()
        });
        this.app.showAlert('Please select a valid product', 'error');
        return;
    }

    if (qty <= 0) {
        this.app.showAlert('Quantity must be greater than 0', 'error');
        return;
    }

    if (rate <= 0) {
        this.app.showAlert('Rate must be greater than 0', 'error');
        return;
    }

    if (!this.validateStockForSell(productId, qty)) {
        const stock = this.getSellStockSnapshot(productId, qty);
        this.debug('addItemFromModal: stock validation failed', {
            productId,
            qty,
            available: this.getAvailableStock(productId)
        });
        this.app.showAlert(`Insufficient stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
        return;
    }

    const item = {
        product_id: productId,
        product_name: String(product.product_name || ''),
        unit: String($('#productUnitSelect').val() || product.product_unit || ''),
        qty,
        rate,
        description: '',
        amount: qty * rate
    };

    this.addItemToTransaction(item);
    this.debug('addItemFromModal: item added', item);
    AppCore.safeHideModal('#addItemsModal');
    this.clearAddItemModal();
};

TransactionManager.prototype.addItemToTransaction = function (item) {
    const existingIndex = this.transactionItems.findIndex(i => Number(i.product_id) === Number(item.product_id));

    if (existingIndex !== -1) {
        const existing = this.transactionItems[existingIndex];
        const nextQty = (parseFloat(existing.qty) || 0) + (parseFloat(item.qty) || 0);

        if (!this.validateStockForSell(item.product_id, nextQty, existingIndex)) {
            const stock = this.getSellStockSnapshot(item.product_id, nextQty, existingIndex);
            this.app.showAlert(`Insufficient stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
            return;
        }

        existing.qty = nextQty;
        existing.amount = existing.qty * existing.rate;
    } else {
        this.transactionItems.push(item);
    }

    this.renderTransactionItems();
};

TransactionManager.prototype.updateItemQty = function (index, qty) {
    if (!Number.isInteger(index) || index < 0 || index >= this.transactionItems.length) return;

    if (qty <= 0) {
        this.app.showAlert('Quantity must be greater than 0', 'error');
        this.renderTransactionItems();
        return;
    }

    const item = this.transactionItems[index];
    if (!this.validateStockForSell(item.product_id, qty, index)) {
        const stock = this.getSellStockSnapshot(item.product_id, qty, index);
        this.app.showAlert(`Quantity exceeds stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
        this.renderTransactionItems();
        return;
    }

    this.transactionItems[index].qty = qty;
    this.transactionItems[index].amount = this.transactionItems[index].qty * this.transactionItems[index].rate;
    this.renderTransactionItems();
};

TransactionManager.prototype.updateItemRate = function (index, rate) {
    if (!Number.isInteger(index) || index < 0 || index >= this.transactionItems.length) return;
    if (rate <= 0) {
        this.app.showAlert('Rate must be greater than 0', 'error');
        this.renderTransactionItems();
        return;
    }
    this.transactionItems[index].rate = rate;
    this.transactionItems[index].amount = this.transactionItems[index].qty * this.transactionItems[index].rate;
    this.renderTransactionItems();
};

TransactionManager.prototype.removeItem = function (index) {
    if (!Number.isInteger(index) || index < 0 || index >= this.transactionItems.length) return;
    this.transactionItems.splice(index, 1);
    this.renderTransactionItems();
};

TransactionManager.prototype.renderTransactionItems = function () {
    const $tbody = $('#transactionItemsTable tbody');
    const $cards = $('#transactionItemsCards');
    $tbody.empty();
    $cards.empty();

    if (this.transactionItems.length === 0) {
        $tbody.html(`
            <tr>
                <td colspan="6" class="text-center text-muted">No items added yet</td>
            </tr>
        `);
        $cards.html('<div class="text-center text-muted p-3">No items added yet</div>');
        $('#totalAmount').val('0.00');
        this.updateProcessButtonState();
        this.updateBalance();
        return;
    }

    let total = 0;
    const tableRows = [];
    const cardRows = [];

    this.transactionItems.forEach((item, index) => {
        const amount = (parseFloat(item.qty) || 0) * (parseFloat(item.rate) || 0);
        item.amount = amount;
        total += amount;

        const description = item.description ? `${item.product_name} - ${item.description}` : item.product_name;

        tableRows.push(`
            <tr data-index="${index}">
                <td><input type="number" class="form-control qtyInput" value="${item.qty}" min="1"></td>
                <td>${item.unit || '-'}</td>
                <td>${description || '-'}</td>
                <td><input type="number" class="form-control rateInput" value="${item.rate}" min="0" step="0.01"></td>
                <td class="itemAmount">${this.app.formatCurrency(amount)}</td>
                <td><button type="button" class="btn btn-danger btn-sm removeItemBtn">Remove</button></td>
            </tr>
        `);

        cardRows.push(`
            <div class="card shadow-sm transactions-mobile-card mb-3" data-index="${index}">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">${description || '-'}</h6>
                        <span class="badge badge-light border">${item.unit || '-'}</span>
                    </div>
                    <div class="transactions-mobile-meta text-muted mb-2">
                        <span>Qty: <input type="number" class="form-control form-control-sm qtyInput d-inline-block" style="width:90px;" value="${item.qty}" min="1"></span>
                        <span>Rate: <input type="number" class="form-control form-control-sm rateInput d-inline-block" style="width:110px;" value="${item.rate}" min="0" step="0.01"></span>
                        <span>Amount: ${this.app.formatCurrency(amount)}</span>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm removeItemBtn">Remove</button>
                </div>
            </div>
        `);
    });

    $tbody.html(tableRows.join(''));
    $cards.html(cardRows.join(''));

    $('#totalAmount').val(total.toFixed(2));
    this.updateProcessButtonState();
    this.updateBalance();
};

TransactionManager.prototype.updateProductPrices = function () {
    this.transactionItems = this.transactionItems.map(item => {
        const product = this.products.find(p => Number(p.product_id) === Number(item.product_id));
        if (!product) return item;

        const nextRate = this.getProductRateByType(product);
        const nextQty = parseFloat(item.qty) || 0;
        return {
            ...item,
            rate: nextRate,
            amount: nextQty * nextRate
        };
    });

    this.renderTransactionItems();
};

TransactionManager.prototype.updateBalance = function () {
    const total = parseFloat($('#totalAmount').val()) || 0;
    const paid = parseFloat($('#paying').val()) || 0;
    const balance = total - paid;
    $('#remaining').val(balance.toFixed(2));  

    $('#mobileTotalAmount').text(this.app.formatCurrency(total));
    $('#mobilePayingAmount').text(this.app.formatCurrency(paid));
    $('#mobileBalanceAmount')
        .text(this.app.formatCurrency(balance))
        .removeClass('transactions-balance-positive transactions-balance-negative')
        .addClass(balance <= 0 ? 'transactions-balance-positive' : 'transactions-balance-negative');
};
