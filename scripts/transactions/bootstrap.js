let TransactionApp;

$(document).ready(function () {
    TransactionApp = new TransactionManager();
    window.TransactionApp = TransactionApp;
    TransactionApp.app.loadUserPermissions(() => {
        TransactionApp.populateUnitOptions('#productUnitSelect');
        TransactionApp.populateTransactionTypeOptions('#transactionType');
    });
});
