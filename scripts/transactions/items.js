TransactionManager.prototype.addItemFromModal = function () {
    let productId = Number($('#purchaseProduct_id').val() || 0);
    const rawQty = Number($('#qty').val()) || 0;
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

    const selectedUnit = this.normalizeUnitValue($('#productUnitSelect').val() || product.product_unit || '');
    const baseUnit = this.normalizeUnitValue(product.product_unit);
    const isFractionalUnitSelection = this.isFractionalUnit(selectedUnit, baseUnit);

    let qty = rawQty;
    let fractionLength = 0;
    let fractionWidth = 0;
    let fractionQty = 0;

    if (isFractionalUnitSelection) {
        const fractionData = this.calculateFractionRequest(baseUnit);
        fractionLength = parseFloat(fractionData.length) || 0;
        fractionWidth = parseFloat(fractionData.width) || 0;
        fractionQty = parseFloat(fractionData.fractionQty) || 0;
        qty = 1;
        if (fractionQty <= 0) {
            this.app.showAlert('Enter a valid fractional measurement', 'error');
            return;
        }
    } else if (qty <= 0) {
        this.app.showAlert('Quantity must be greater than 0', 'error');
        return;
    }

    const stockQty = (this.transactionType === 'sell' && isFractionalUnitSelection) ? 0 : qty;

    const isFractionalSell = this.transactionType === 'sell' && isFractionalUnitSelection;
    if (stockQty <= 0 && !isFractionalSell) {
        this.app.showAlert('Quantity must be greater than 0', 'error');
        return;
    }

    if (rate <= 0) {
        this.app.showAlert('Rate must be greater than 0', 'error');
        return;
    }

    if (!this.validateStockForSell(productId, stockQty, null, {
        selectedUnit,
        baseUnit,
        fractionQty
    })) {
        const stock = this.getSellStockSnapshot(productId, stockQty);
        this.debug('addItemFromModal: stock validation failed', {
            productId,
            qty,
            stockQty,
            available: this.getAvailableStock(productId)
        });
        this.app.showAlert(`Insufficient stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
        return;
    }

    const item = {
        product_id: productId,
        product_name: String(product.product_name || ''),
        unit: selectedUnit,
        base_unit: baseUnit,
        qty,
        stock_qty: stockQty,
        rate,
        is_fractional: isFractionalUnitSelection ? 1 : 0,
        fraction_length: fractionLength,
        fraction_width: fractionWidth,
        fraction_qty: fractionQty,
        display_label: this.buildItemDisplayLabel(product.product_name, baseUnit, selectedUnit, {
            length: fractionLength,
            width: fractionWidth,
            yards: fractionQty
        }),
        description: '',
        amount: qty * rate
    };

    this.addItemToTransaction(item);
    this.debug('addItemFromModal: item added', item);
    AppCore.safeHideModal('#addItemsModal');
    this.clearAddItemModal();
};

TransactionManager.prototype.addItemToTransaction = function (item) {
    if (Number(item.is_fractional) === 1) {
        this.transactionItems.push(item);
        this.renderTransactionItems();
        return;
    }

    const existingIndex = this.transactionItems.findIndex(i =>
        Number(i.product_id) === Number(item.product_id) &&
        this.normalizeUnitValue(i.base_unit) === this.normalizeUnitValue(item.base_unit) &&
        this.normalizeUnitValue(i.unit) === this.normalizeUnitValue(item.unit) &&
        Number(i.fraction_length || 0) === Number(item.fraction_length || 0) &&
        Number(i.fraction_width || 0) === Number(item.fraction_width || 0) &&
        Number(i.fraction_qty || 0) === Number(item.fraction_qty || 0) &&
        Number(i.rate) === Number(item.rate)
    );

    if (existingIndex !== -1) {
        const existing = this.transactionItems[existingIndex];
        const nextQty = (parseFloat(existing.qty) || 0) + (parseFloat(item.qty) || 0);
        const nextStockQty = this.getItemStockQty(existing) + this.getItemStockQty(item);

        if (!this.validateStockForSell(item.product_id, nextStockQty, existingIndex, {
            selectedUnit: item.unit,
            baseUnit: item.base_unit,
            fractionQty: Number(item.fraction_qty || 0)
        })) {
            const stock = this.getSellStockSnapshot(item.product_id, nextStockQty, existingIndex);
            this.app.showAlert(`Insufficient stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
            return;
        }

        existing.qty = nextQty;
        existing.stock_qty = nextStockQty;
        existing.amount = existing.qty * existing.rate;
    } else {
        this.transactionItems.push(item);
    }

    this.renderTransactionItems();
};

TransactionManager.prototype.updateItemQty = function (index, qty) {
    if (!Number.isInteger(index) || index < 0 || index >= this.transactionItems.length) return;

    if (Number(this.transactionItems[index].is_fractional) === 1) {
        return;
    }

    if (qty <= 0) {
        this.app.showAlert('Quantity must be greater than 0', 'error');
        this.renderTransactionItems();
        return;
    }

    const item = this.transactionItems[index];
    const nextStockQty = this.transactionType === 'sell'
        ? this.convertSellQtyToStockQty(qty, item.unit, item.base_unit)
        : qty;
    if (!this.validateStockForSell(item.product_id, nextStockQty, index, {
        selectedUnit: item.unit,
        baseUnit: item.base_unit,
        fractionQty: Number(item.fraction_qty || 0)
    })) {
        const stock = this.getSellStockSnapshot(item.product_id, nextStockQty, index);
        this.app.showAlert(`Quantity exceeds stock. Available: ${stock.available}, In cart: ${stock.inCart}, Requested: ${stock.requested}`, 'error');
        this.renderTransactionItems();
        return;
    }

    this.transactionItems[index].qty = qty;
    this.transactionItems[index].stock_qty = nextStockQty;
    this.transactionItems[index].amount = this.transactionItems[index].qty * this.transactionItems[index].rate;
    this.renderTransactionItems();
};

TransactionManager.prototype.updateItemRate = function (index, rate) {
    if (!Number.isInteger(index) || index < 0 || index >= this.transactionItems.length) return;
    if (Number(this.transactionItems[index].is_fractional) === 1) return;
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

        const baseDescription = String(item.display_label || item.product_name || '-');
        const description = item.description ? `${baseDescription} - ${item.description}` : baseDescription;
        const qtyReadOnly = Number(item.is_fractional) === 1 ? 'readonly' : '';
        const qtyMin = Number(item.is_fractional) === 1 ? '1' : '1';
        const rateReadOnly = Number(item.is_fractional) === 1 ? 'readonly' : '';

        tableRows.push(`
            <tr data-index="${index}">
                <td><input type="number" class="form-control qtyInput" value="${item.qty}" min="${qtyMin}" ${qtyReadOnly}></td>
                <td>${item.unit || '-'}</td>
                <td>${description || '-'}</td>
                <td><input type="number" class="form-control rateInput" value="${item.rate}" min="0" step="0.01" ${rateReadOnly}></td>
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
                        <span>Qty: <input type="number" class="form-control form-control-sm qtyInput d-inline-block" style="width:90px;" value="${item.qty}" min="${qtyMin}" ${qtyReadOnly}></span>
                        <span>Rate: <input type="number" class="form-control form-control-sm rateInput d-inline-block" style="width:110px;" value="${item.rate}" min="0" step="0.01" ${rateReadOnly}></span>
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
        const nextStockQty = (this.transactionType === 'sell' && Number(item.is_fractional) === 1)
            ? 0
            : (this.transactionType === 'sell'
            ? this.convertSellQtyToStockQty(nextQty, item.unit, item.base_unit)
            : nextQty);
        const appliedRate = Number(item.is_fractional) === 1
            ? this.computeFractionRate(item.base_unit, nextRate, Number(item.fraction_qty || 0))
            : nextRate;
        return {
            ...item,
            stock_qty: nextStockQty,
            rate: appliedRate,
            amount: nextQty * appliedRate
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
