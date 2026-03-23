TransactionManager.prototype.loadPartners = function () {
    this.app.ajaxHelper({
        url: 'apiRequest.php',
        action: 'loadPartners',
        data: {},
        onSuccess: (res) => {
            this.partners = Array.isArray(res.data) ? res.data : [];
        }
    });
};

TransactionManager.prototype.loadProducts = function () {
    this.app.ajaxHelper({
        url: 'apiTransactions.php',
        action: 'loadProducts',
        data: {},
        onSuccess: (res) => {
            this.products = Array.isArray(res.data) ? res.data : [];
        }
    });
};

TransactionManager.prototype.showPartnerSuggestions = function (query) {
    const filteredPartners = this.partners
        .filter(p => String(p.sName || '').toLowerCase().includes(query))
        .slice(0, 6);

    if (!filteredPartners.length) {
        $('#partnerSuggestions').hide();
        return;
    }

    let html = '';
    filteredPartners.forEach(p => {
        html += `<a href="#" class="list-group-item list-group-item-action partnerSuggestionItem" data-partner-id="${p.sid}" data-partner-name="${p.sName}">${p.sName}</a>`;
    });

    $('#partnerSuggestions').html(html).show();
};

TransactionManager.prototype.resolvePartnerFromInput = function () {
    const name = String($('#partner_name').val() || '').trim().toLowerCase();
    if (!name) {
        $('#partnerId').val('');
        return 0;
    }

    const match = this.partners.find(p => String(p.sName || '').trim().toLowerCase() === name);
    if (match) {
        $('#partnerId').val(match.sid);
        this.clearFieldError('#partner_name');
        return Number(match.sid) || 0;
    }

    $('#partnerId').val('');
    return 0;
};

TransactionManager.prototype.showProductSuggestions = function (query) {
    const filtered = this.products
        .filter(p => {
            const productName = String(p.product_name || '').toLowerCase();
            return productName.includes(query);
        })
        .slice(0, 8);

    if (!filtered.length) {
        $('#productSuggestions').hide();
        return;
    }

    let html = '';
    filtered.forEach(p => {
        const rate = this.getProductRateByType(p);
        const availableStock = parseFloat(p.available_stock) || 0;
        const stockText = this.transactionType === 'sell'
            ? `<br><small class="text-muted">Stock: ${availableStock}</small>`
            : '';
        html += `<div class="bg-dark productSuggestionItem p-2 border-bottom" style="cursor:pointer;" data-product-id="${p.product_id}">
                    <strong>${p.product_name}</strong>
                    <br><small class="text-muted">Unit: ${p.product_unit || '-'}</small>
                    ${stockText}
                    <br><small class="text-success">${this.app.formatCurrency(rate)}</small>
                </div>`;
    });

    $('#productSuggestions').html(html).show();
};

TransactionManager.prototype.resolveProductFromInput = function () {
    const query = String($('#purchaseProductInput').val() || '').trim().toLowerCase();
    if (!query) {
        $('#purchaseProduct_id').val('');
        return null;
    }

    const matchedProduct = this.products.find(p => {
        const productName = String(p.product_name || '').trim().toLowerCase();
        const barcode = String(p.barcode || '').trim().toLowerCase();
        return productName === query || (barcode && barcode === query);
    });

    if (!matchedProduct) {
        $('#purchaseProduct_id').val('');
        return null;
    }

    this.applySelectedProductToModal(matchedProduct);
    return matchedProduct;
};

TransactionManager.prototype.getProductRateByType = function (product) {
    if (this.transactionType === 'buy') return parseFloat(product.cost_price) || 0;
    return parseFloat(product.selling_price) || 0;
};

TransactionManager.prototype.getAvailableStock = function (productId) {
    const product = this.products.find(p => Number(p.product_id) === Number(productId));
    return parseFloat(product?.available_stock) || 0;
};

TransactionManager.prototype.getCartQtyForProduct = function (productId, excludeIndex = null) {
    return this.transactionItems.reduce((sum, item, idx) => {
        if (Number(item.product_id) !== Number(productId)) return sum;
        if (excludeIndex !== null && idx === excludeIndex) return sum;
        return sum + (parseFloat(item.qty) || 0);
    }, 0);
};

TransactionManager.prototype.getSellStockSnapshot = function (productId, nextQty, excludeIndex = null) {
    const availableStockRaw = parseFloat(this.getAvailableStock(productId)) || 0;
    const available = Math.max(0, availableStockRaw);
    const inCart = parseFloat(this.getCartQtyForProduct(productId, excludeIndex)) || 0;
    const requested = parseFloat(nextQty) || 0;
    const totalRequested = inCart + requested;

    return {
        available,
        inCart,
        requested,
        totalRequested
    };
};

TransactionManager.prototype.validateStockForSell = function (productId, nextQty, excludeIndex = null) {
    if (this.transactionType !== 'sell') return true;

    const snapshot = this.getSellStockSnapshot(productId, nextQty, excludeIndex);
    const epsilon = 0.000001;
    if (snapshot.requested <= 0) return false;
    return snapshot.totalRequested <= (snapshot.available + epsilon);
};

TransactionManager.prototype.applySelectedProductToModal = function (product) {
    const rate = this.getProductRateByType(product);

    $('#purchaseProduct_id').val(product.product_id);
    $('#purchaseProductInput').val(product.product_name || '');
    $('#productUnitSelect').html(`<option value="${product.product_unit || ''}" selected>${product.product_unit || '-'}</option>`);
    $('#rate').val(rate);
    $('#qty').val(1);
};