TransactionManager.prototype.validateTransactionForm = function () {
    let ok = true;
    const issues = [];

    const type = String($('#transactionType').val() || '').trim();
    if (!type) {
        this.setFieldError('#transactionType', 'Please select transaction type');
        ok = false;
        issues.push('transaction type missing');
    } else {
        this.clearFieldError('#transactionType');
    }

    if (!Number($('#partnerId').val() || 0)) {
        this.resolvePartnerFromInput();
    }

    const partnerId = Number($('#partnerId').val() || 0);
    if (!partnerId) {
        this.setFieldError('#partner_name', 'Please select a partner');
        ok = false;
        issues.push('partner missing');
    } else {
        this.clearFieldError('#partner_name');
    }

    if (!this.transactionItems.length) {
        this.setTextError('#transactionItemsTable_err', 'Please add at least one item');
        ok = false;
        issues.push('no items in cart');
    } else {
        this.clearTextError('#transactionItemsTable_err');
    }

    this.transactionItems.forEach((item, index) => {
        const qty = parseFloat(item.qty) || 0;
        const stockQty = this.getItemStockQty(item);
        const rate = parseFloat(item.rate) || 0;
        if (qty <= 0 || rate <= 0) {
            ok = false;
            issues.push(`invalid qty/rate for product ${item.product_id}`);
        }

        if (this.transactionType === 'sell' && !this.validateStockForSell(item.product_id, stockQty, index, {
            selectedUnit: item.unit,
            baseUnit: item.base_unit,
            fractionQty: Number(item.fraction_qty || 0)
        })) {
            ok = false;
            issues.push(`insufficient stock for product ${item.product_id}`);
        }
    });

    if (!ok && this.transactionItems.length) {
        this.setTextError('#transactionItemsTable_err', 'Invalid quantity/rate or insufficient stock in one or more items');
    }

    if (!ok) {
        this.debug('validateTransactionForm: failed', {
            issues,
            transactionType: type,
            partnerId,
            itemCount: this.transactionItems.length,
            paying: parseFloat($('#paying').val()) || 0
        });
    } else {
        this.debug('validateTransactionForm: passed', {
            transactionType: type,
            partnerId,
            itemCount: this.transactionItems.length
        });
    }

    return ok;
};

TransactionManager.prototype.buildApiItemsPayload = function () {
    return this.transactionItems.map(item => ({
        product_id: Number(item.product_id),
        qty: Number(item.qty),
        costPrice: Number(item.rate),
        sale_unit: String(item.unit || ''),
        purchase_unit: String(item.unit || ''),
        base_unit: String(item.base_unit || ''),
        is_fractional: Number(item.is_fractional || 0),
        fraction_length: Number(item.fraction_length || 0),
        fraction_width: Number(item.fraction_width || 0),
        fraction_qty: Number(item.fraction_qty || 0),
        display_label: String(item.display_label || item.product_name || '')
    }));
};

TransactionManager.prototype.saveTransaction = function () {
    this.debug('saveTransaction: clicked', {
        partnerIdRaw: $('#partnerId').val(),
        transactionTypeRaw: $('#transactionType').val(),
        payingRaw: $('#paying').val(),
        itemCount: this.transactionItems.length
    });

    if (!this.validateTransactionForm()) return;

    const payloadItems = this.buildApiItemsPayload();
    const type = String($('#transactionType').val() || this.transactionType).toLowerCase();
    const requestData = {
        partner_id: Number($('#partnerId').val() || 0),
        transaction_type: type,
        amountPaid: parseFloat($('#paying').val()) || 0,
        transaction_date: $('#transactionDate').val(),
        items: JSON.stringify(payloadItems)
    };

    this.debug('saveTransaction: request payload', requestData);

    const action = type === 'buy' ? 'createPurchase' : 'createSale';
    $('#savePurchaseBtn').prop('disabled', true);

    this.app.ajaxHelper({
        url: 'apiTransactions.php',
        action,
        data: requestData,
        onSuccess: (res) => {
            this.debug('saveTransaction: API success callback', res);
            if (res.status === 'success') {
                this.app.showAlert('Transaction saved successfully', 'success');
                this.loadProducts();
                this.loadPartners();
                this.resetTransactionForm();
                this.showTransactionHistoryTab();
            } else {
                this.app.showAlert(this.app.getResponseText(res, 'Failed to save transaction'), 'error');
            }
        },
        onComplete: () => {
            this.updateProcessButtonState();
        }
    });
};
