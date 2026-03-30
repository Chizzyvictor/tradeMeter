TransactionManager.prototype.loadTransactionHistory = function () {
    $('#transactionsLoader').removeClass('d-none');
    this.app.ajaxHelper({
        url: 'apiTransactions.php',
        action: 'loadPurchases',
        data: {},
        onSuccess: (res) => {
            this.historyRows = Array.isArray(res.data) ? res.data : [];
            this.renderTransactionHistory(this.historyRows);
        },
        onComplete: () => {
            $('#transactionsLoader').addClass('d-none');
        }
    });
};

TransactionManager.prototype.normalizeTransactionType = function (value, fallback = 'buy') {
    const normalized = String(value || '').toLowerCase();
    if (normalized === 'purchase') return 'buy';
    if (normalized === 'sale') return 'sell';
    if (normalized === 'buy' || normalized === 'sell') return normalized;
    return fallback;
};

TransactionManager.prototype.renderTransactionHistory = function (rows) {
    const $tbody = $('#transactionsHistoryTable tbody');
    const $cards = $('#transactionsHistoryCards');
    $tbody.empty();
    $cards.empty();

    if (!rows.length) {
        $tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted p-4">
                    <i class="fa fa-receipt fa-2x mb-2"></i><br>
                    No transactions recorded yet
                </td>
            </tr>
        `);
        $cards.html(`
            <div class="text-center text-muted p-4">
                <i class="fa fa-receipt fa-2x mb-2"></i><br>
                No transactions recorded yet
            </div>
        `);
        return;
    }

    const tableRows = [];
    const cardRows = [];

    rows.forEach(row => {
        const total = parseFloat(row.totalAmount) || 0;
        const paid = parseFloat(row.amountPaid) || 0;
        const balance = Math.max(0, total - paid);
        const status = String(row.status || 'pending').toLowerCase();
        const type = this.normalizeTransactionType(row.transaction_type, 'buy');
        const dateText = row.createdAt ? new Date(row.createdAt).toLocaleDateString() : '-';
        const typeBadge = type === 'sell'
            ? '<span class="badge badge-success">Sell</span>'
            : '<span class="badge badge-warning">Buy</span>';
        const actionButtons = `
            <button type="button" class="btn btn-sm btn-primary viewTransactionBtn" data-id="${row.purchase_id}">View</button>
            ${status !== 'paid' ? `<button type="button" class="btn btn-sm btn-success ml-1 payTransactionBtn" data-id="${row.purchase_id}">Pay</button>` : ''}
        `;

        tableRows.push(`
            <tr>
                <td>${row.purchase_id}</td>
                <td>${row.partner_name || row.supplier_name || 'Unknown'}</td>
                <td>${typeBadge}</td>
                <td>${this.app.formatCurrency(total)}</td>
                <td>${this.app.formatCurrency(paid)}</td>
                <td>${this.app.formatCurrency(balance)}</td>
                <td>${dateText}</td>
                <td>${actionButtons}</td>
            </tr>
        `);

        cardRows.push(`
            <div class="card shadow-sm transactions-mobile-card mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-0">#${row.purchase_id} · ${row.partner_name || row.supplier_name || 'Unknown'}</h6>
                            <small class="text-muted">${dateText}</small>
                        </div>
                        ${typeBadge}
                    </div>
                    <div class="transactions-mobile-meta text-muted mb-2">
                        <span>Total: ${this.app.formatCurrency(total)}</span>
                        <span>Paid: ${this.app.formatCurrency(paid)}</span>
                        <span>Balance: ${this.app.formatCurrency(balance)}</span>
                        <span>Status: ${status}</span>
                    </div>
                    <div>${actionButtons}</div>
                </div>
            </div>
        `);
    });

    $tbody.html(tableRows.join(''));
    $cards.html(cardRows.join(''));
};

TransactionManager.prototype.filterTransactions = function () {
    const partnerQuery = String($('#searchPartner').val() || '').toLowerCase().trim();
    const typeFilter = String($('#filterStatus').val() || '').toLowerCase().trim();
    const fromDate = $('#filterFrom').val();
    const toDate = $('#filterTo').val();

    const filtered = this.historyRows.filter(row => {
        const partnerName = String(row.partner_name || row.supplier_name || '').toLowerCase();
        const type = this.normalizeTransactionType(row.transaction_type, '');
        const createdAt = row.createdAt ? new Date(row.createdAt) : null;

        if (partnerQuery && !partnerName.includes(partnerQuery)) return false;
        if (typeFilter && type !== typeFilter) return false;
        if (fromDate && createdAt && createdAt < new Date(fromDate + 'T00:00:00')) return false;
        if (toDate && createdAt && createdAt > new Date(toDate + 'T23:59:59')) return false;
        return true;
    });

    this.renderTransactionHistory(filtered);
};

TransactionManager.prototype.resetFilters = function () {
    $('#searchPartner').val('');
    $('#filterStatus').val('');
    $('#filterFrom').val('');
    $('#filterTo').val('');
    this.renderTransactionHistory(this.historyRows);
};

TransactionManager.prototype.viewTransactionDetails = function (purchaseId) {
    const $modal = $('#purchaseDetailsModal');
    const $payPurchaseBtn = $modal.find('#payPurchaseBtn');
    const $payPurchaseId = $modal.find('#payPurchaseId');
    const $payBalanceDue = $modal.find('#payBalanceDue');
    const $payAmount = $modal.find('#payAmount');
    const $payAmountErr = $modal.find('#payAmount_err');
    const $payStatusNote = $modal.find('#payStatusNote');
    const $itemsTbody = $modal.find('#purchaseDetailsItemsTable tbody');

    $payPurchaseBtn.prop('disabled', true);
    this.app.ajaxHelper({
        url: 'apiTransactions.php',
        action: 'loadPurchaseDetails',
        data: { purchase_id: purchaseId },
        onSuccess: (res) => {
            const purchase = res.purchase || {};
            const items = Array.isArray(res.items) ? res.items : [];

            const total = parseFloat(purchase.totalAmount) || 0;
            const paid = parseFloat(purchase.amountPaid) || 0;
            const balance = Math.max(0, total - paid);

            $('#metaPartner').text(purchase.partner_name || purchase.supplier_name || '-');
            $('#metaTotal').text(this.app.formatCurrency(total));
            $('#metaPaid').text(this.app.formatCurrency(paid));
            $('#metaBalance').text(this.app.formatCurrency(balance));

            $itemsTbody.empty();

            if (!items.length) {
                $itemsTbody.html(`
                    <tr>
                        <td colspan="5" class="text-center text-muted">No items found for this transaction</td>
                    </tr>
                `);
            } else {
                items.forEach(item => {
                    const lineTotal = parseFloat(item.total) || ((parseFloat(item.qty) || 0) * (parseFloat(item.costPrice) || 0));
                    $itemsTbody.append(`
                        <tr>
                            <td>${item.product_name || '-'}</td>
                            <td>${item.qty || 0}</td>
                            <td>${item.product_unit || '-'}</td>
                            <td>${this.app.formatCurrency(parseFloat(item.costPrice) || 0)}</td>
                            <td>${this.app.formatCurrency(lineTotal)}</td>
                        </tr>
                    `);
                });
            }

            $payPurchaseId.val(purchaseId);
            $payBalanceDue.val(balance.toFixed(2));
            $payAmount.val('');
            $payAmount.attr('max', balance.toFixed(2));
            $payAmountErr.text('');
            $payPurchaseBtn.prop('disabled', balance <= 0);
            $payStatusNote.toggleClass('d-none', balance > 0);
            $modal.modal('show');
        },
        onError: () => {
            $payPurchaseBtn.prop('disabled', true);
            $payStatusNote.addClass('d-none');
            this.app.showAlert('Failed to load transaction details. Please try again.', 'error');
        }
    });
};

TransactionManager.prototype.payPurchase = function () {
    const $modal = $('#purchaseDetailsModal');
    const $payPurchaseId = $modal.find('#payPurchaseId');
    const $payAmount = $modal.find('#payAmount');
    const $payBalanceDue = $modal.find('#payBalanceDue');
    const $payBtn = $modal.find('#payPurchaseBtn');
    const $payAmountErr = $modal.find('#payAmount_err');
    const $payStatusNote = $modal.find('#payStatusNote');

    const purchaseId = Number($payPurchaseId.val() || 0);
    const amount = parseFloat($payAmount.val()) || 0;
    const balanceDue = parseFloat($payBalanceDue.val()) || 0;

    if (purchaseId <= 0 || amount <= 0) {
        $payAmountErr.text('Enter a valid payment amount');
        return;
    }

    if (balanceDue > 0 && amount - balanceDue > 0.0001) {
        $payAmountErr.text('Amount cannot exceed outstanding balance');
        return;
    }

    $payBtn.prop('disabled', true);

    this.app.ajaxHelper({
        url: 'apiTransactions.php',
        action: 'payPurchase',
        data: {
            purchase_id: purchaseId,
            amount
        },
        onSuccess: (res) => {
            if (res.status === 'success') {
                $payAmountErr.text('');
                const updatedPaid = parseFloat(res.amountPaid) || 0;
                const nextBalance = Math.max(0, balanceDue - amount);
                const nextStatus = String(res.status || (nextBalance <= 0 ? 'paid' : 'partial')).toLowerCase();

                $('#metaPaid').text(this.app.formatCurrency(updatedPaid));
                $('#metaBalance').text(this.app.formatCurrency(nextBalance));
                $payBalanceDue.val(nextBalance.toFixed(2));
                $payAmount.val('');
                $payAmount.attr('max', nextBalance.toFixed(2));
                $payStatusNote.toggleClass('d-none', nextBalance > 0);
                $payBtn.prop('disabled', nextBalance <= 0);

                const target = this.historyRows.find(r => Number(r.purchase_id) === purchaseId);
                if (target) {
                    const totalAmount = parseFloat(target.totalAmount) || 0;
                    target.amountPaid = Math.min(totalAmount, updatedPaid);
                    target.status = nextStatus;
                }

                const hasFilters = Boolean(String($('#searchPartner').val() || '').trim())
                    || Boolean(String($('#filterStatus').val() || '').trim())
                    || Boolean($('#filterFrom').val())
                    || Boolean($('#filterTo').val());

                if (hasFilters) {
                    this.filterTransactions();
                } else {
                    this.renderTransactionHistory(this.historyRows);
                }

                this.loadTransactionHistory();
            } else {
                $payAmountErr.text(res.message || 'Payment failed');
            }
        },
        onError: () => {
            $payAmountErr.text('Payment failed. Please try again.');
        },
        onComplete: () => {
            if ($('#purchaseDetailsModal').hasClass('show')) {
                $payBtn.prop('disabled', false);
            }
        }
    });
};

TransactionManager.prototype.populateTransactionTypeOptions = function (selector) {
    const select = $(selector);
    if (!select.length) return;
    select.empty();

    const types = [];
    if (this.app.hasPermission('create_sales')) {
        types.push({ value: 'sell', label: 'Sell' });
    }
    if (this.app.hasAnyPermission('create_purchases', 'manage_inventory')) {
        types.push({ value: 'buy', label: 'Purchase' });
    }

    if (!types.length) {
        select.append('<option value="" disabled selected>No transaction types available</option>');
        $('#addTransactionBtn, #savePurchaseBtn').hide();
        return;
    }

    types.forEach(t => {
        select.append(`<option value="${t.value}">${t.label}</option>`);
    });

    if (!types.some(t => t.value === this.transactionType)) {
        this.transactionType = types[0].value;
    }

    select.val(this.transactionType);
};

TransactionManager.prototype.populateUnitOptions = function (selector) {
    const select = $(selector);
    if (!select.length) return;
    select.empty();
    this.app.productUnits.forEach((u) => {
        select.append(`<option value="${u.value}">${u.label}</option>`);
    });
};
