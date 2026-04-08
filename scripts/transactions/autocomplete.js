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

    const normalize = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
    const exact = this.partners.find(p => normalize(p.sName) === normalize(name));
    const match = exact || this.partners.find(p => normalize(p.sName).includes(normalize(name)));
    if (match) {
        $('#partnerId').val(match.sid);
        $('#partner_name').val(match.sName || '');
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

TransactionManager.prototype.normalizeUnitValue = function (unit) {
    return String(unit || '').trim().toLowerCase();
};

TransactionManager.prototype.getUnitLabel = function (unit) {
    const normalizedUnit = this.normalizeUnitValue(unit);
    if (!normalizedUnit) return '-';

    const knownUnits = (this.app && Array.isArray(this.app.productUnits)) ? this.app.productUnits : [];
    const matched = knownUnits.find(u => this.normalizeUnitValue(u.value) === normalizedUnit);
    if (matched) return String(matched.label || normalizedUnit);

    return normalizedUnit.charAt(0).toUpperCase() + normalizedUnit.slice(1);
};

TransactionManager.prototype.getSelectableUnitsForProduct = function (productUnit) {
    const normalizedUnit = this.normalizeUnitValue(productUnit);
    if (normalizedUnit === 'size') {
        return ['size', 'sheet'];
    }
    if (normalizedUnit === 'yard') {
        return ['yard', 'roll'];
    }
    return normalizedUnit ? [normalizedUnit] : [];
};

TransactionManager.prototype.convertSellQtyToStockQty = function (qty, selectedUnit, productBaseUnit = '') {
    const parsedQty = parseFloat(qty) || 0;
    if (parsedQty <= 0) return 0;

    const normalizedUnit = this.normalizeUnitValue(selectedUnit);
    const normalizedBaseUnit = this.normalizeUnitValue(productBaseUnit);
    if (normalizedBaseUnit === 'size') {
        if (normalizedUnit === 'sheet') return parsedQty * 32;
        return parsedQty;
    }
    if (normalizedBaseUnit === 'yard') {
        if (normalizedUnit === 'roll') return parsedQty * 270;
        return parsedQty;
    }
    return parsedQty;
};

TransactionManager.prototype.getItemStockQty = function (item) {
    const explicitStockQty = parseFloat(item?.stock_qty);
    if (Number.isFinite(explicitStockQty) && explicitStockQty > 0) {
        return explicitStockQty;
    }

    const qty = parseFloat(item?.qty) || 0;
    if (this.transactionType !== 'sell') return qty;

    return this.convertSellQtyToStockQty(qty, item?.unit, item?.base_unit);
};

TransactionManager.prototype.getAvailableStock = function (productId) {
    const product = this.products.find(p => Number(p.product_id) === Number(productId));
    return parseFloat(product?.available_stock) || 0;
};

TransactionManager.prototype.getCartQtyForProduct = function (productId, excludeIndex = null) {
    return this.transactionItems.reduce((sum, item, idx) => {
        if (Number(item.product_id) !== Number(productId)) return sum;
        if (excludeIndex !== null && idx === excludeIndex) return sum;
        return sum + this.getItemStockQty(item);
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
    const normalizedProductUnit = this.normalizeUnitValue(product.product_unit);
    const selectableUnits = this.getSelectableUnitsForProduct(normalizedProductUnit);

    let unitOptionsHtml = '';
    selectableUnits.forEach(unitValue => {
        const selectedAttr = unitValue === normalizedProductUnit ? ' selected' : '';
        unitOptionsHtml += `<option value="${unitValue}"${selectedAttr}>${this.getUnitLabel(unitValue)}</option>`;
    });

    if (!unitOptionsHtml) {
        const fallbackUnit = normalizedProductUnit || 'pcs';
        unitOptionsHtml = `<option value="${fallbackUnit}" selected>${this.getUnitLabel(fallbackUnit)}</option>`;
    }

    $('#purchaseProduct_id').val(product.product_id);
    $('#purchaseProductInput').val(product.product_name || '');
    $('#productUnitSelect').html(unitOptionsHtml);
    $('#rate').val(rate);
    $('#qty').val(1);
};
