<!-- =====================================================
     STANDARD MODAL HEADER STYLE:
     modal-header bg-primary text-white
====================================================== -->


<!-- ============================
     ADD PARTNER MODAL
============================ -->
<div class="modal fade" id="addPartnerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="addPartnerForm" enctype="multipart/form-data">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-user-plus mr-2"></i>Add Partner
          </h5>

          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <div class="form-group">
            <label>Partner Name</label>
            <input type="text" id="partnerName" class="form-control">
            <small class="form-error text-danger" id="partnerNameError"></small>
          </div>

          <div class="form-group">
            <label>Email</label>
            <input type="email" id="partnerEmail" class="form-control">
            <small class="form-error text-danger" id="partnerEmailError"></small>
          </div>

          <div class="form-group">
            <label>Address</label>
            <input type="text" id="partnerAddress" class="form-control">
            <small class="form-error text-danger" id="partnerAddressError"></small>
          </div>

          <div class="form-group">
            <label>Phone</label>
            <input type="tel" id="partnerPhone" class="form-control">
            <small class="form-error text-danger" id="partnerPhoneError"></small>
          </div>

          <div class="form-group">
            <label>Profile Picture</label>
            <input type="file" id="partnerImage" name="partnerImage" class="form-control" accept="image/*">
            <small class="form-text text-muted">Optional. JPG, PNG or GIF (max 2MB).</small>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-save mr-1"></i>Save Partner
          </button>
        </div>

      </form>

    </div>
  </div>
</div>




<!-- ============================
     EDIT PARTNER MODAL
============================ -->
<div class="modal fade" id="editPartnerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editPartnerForm" enctype="multipart/form-data">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-user-edit mr-2"></i>Edit Partner
          </h5>
            <button type="button" class="close text-white" data-dismiss="modal">
                <span>&times;</span>
            </button>
        </div>

        <div class="modal-body">

          <div class="form-group">
            <label>Partner Name</label>
            <input type="text" id="editPartnerName" class="form-control">
            <small class="form-error text-danger" id="editPartnerNameError"></small>
          </div>

          <div class="form-group">
            <label>Email</label>
            <input type="email" id="editPartnerEmail" class="form-control">
            <small class="form-error text-danger" id="editPartnerEmailError"></small>
          </div>

          <div class="form-group">
            <label>Address</label>
            <input type="text" id="editPartnerAddress" class="form-control">
            <small class="form-error text-danger" id="editPartnerAddressError"></small>
          </div>

          <div class="form-group">
            <label>Phone</label>
            <input type="tel" id="editPartnerPhone" class="form-control">
            <small class="form-error text-danger" id="editPartnerPhoneError"></small>
          </div>

          <div class="form-group">
            <label>Profile Picture</label>
            <input type="file" id="editPartnerImage" name="editPartnerImage" class="form-control" accept="image/*">
            <small class="form-text text-muted">Optional. JPG, PNG or GIF (max 2MB).</small>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-save mr-1"></i>UPDATE PARTNER
          </button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- ============================
     ADD DEBT MODAL
============================= -->
<div class="modal fade" id="addDebtModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="addDebtForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-credit-card mr-2"></i>Manage Debt
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">

          <div class="form-group">
            <label>Amount</label>
            <input type="number" id="debtAmount" class="form-control" required>
          </div>

          <div class="form-group">
            <label>Description</label>
            <input type="text" id="debtDesc" class="form-control" required>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            Save
          </button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- ============================
     PAY DEBT MODAL
============================= -->
<div class="modal fade" id="payDebtModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
	<div class="modal-content shadow rounded">

	  <form id="payDebtForm">

		<div class="modal-header bg-primary text-white">
		  <h5 class="modal-title">
			<i class="fa fa-credit-card mr-2"></i>Record Payment
		  </h5>
		  <button type="button" class="close text-white" data-dismiss="modal">
			<span>&times;</span>
		  </button>
		</div>

		<div class="modal-body">

		  <div class="form-group">
			<label>Amount</label>
      <input type="number" id="payDebtAmount" class="form-control" required>
		  </div>

		  <div class="form-group">
			<label>Description</label>
			<input type="text" id="payDesc" class="form-control" required>
		  </div>

		</div>

		<div class="modal-footer">
		  <button type="submit" class="btn btn-success btn-block">
			Save
		  </button>
		</div>

	  </form>

	</div>
  </div>
</div>



<!-- ============================
     PURCHASE DETAILS MODAL
============================= -->


<div class="modal fade" id="purchaseDetailsModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="purchaseDetailsModalLabel">

  <div class="modal-dialog modal-xl">

    <div class="modal-content">

      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="purchaseDetailsModalLabel">Transaction Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close transaction details modal">
          <span>&times;</span>
        </button>
      </div>


      <div class="modal-body">


        <!-- SUMMARY CARDS -->

        <div class="row mb-3">

          <div class="col-md-3">
            <div class="card border-left-primary shadow-sm">
              <div class="card-body">
                <small class="text-muted">Partner</small>
                <h6 id="metaPartner">-</h6>
              </div>
            </div>
          </div>


          <div class="col-md-3">
            <div class="card border-left-info shadow-sm">
              <div class="card-body">
                <small class="text-muted">Total</small>
                <h6 id="metaTotal">₦0</h6>
              </div>
            </div>
          </div>


          <div class="col-md-3">
            <div class="card border-left-success shadow-sm">
              <div class="card-body">
                <small class="text-muted">Paid</small>
                <h6 id="metaPaid">₦0</h6>
              </div>
            </div>
          </div>


          <div class="col-md-3">
            <div class="card border-left-danger shadow-sm">
              <div class="card-body">
                <small class="text-muted">Balance</small>
                <h6 id="metaBalance">₦0</h6>
              </div>
            </div>
          </div>

        </div>



        <!-- ITEMS TABLE -->

        <div class="table-responsive-sm">

          <table
            class="table table-bordered table-sm"
            id="purchaseDetailsItemsTable"
          >

            <thead class="thead-dark">
              <tr>
                <th>Product</th>
                <th width="100">Qty</th>
                <th width="100">Unit</th>
                <th width="120">Price</th>
                <th width="120">Total</th>
              </tr>
            </thead>

            <tbody>
              <tr>
                <td colspan="5" class="text-center text-muted">No items found for this transaction</td>
              </tr>
            </tbody>

          </table>

        </div>


        <hr>


        <!-- PAYMENT FORM -->

        <div class="row align-items-center">

          <div class="col-md-6">
            <strong>Add Payment</strong>
          </div>

          <div class="col-md-6">

            <form id="payPurchaseForm" class="form-inline justify-content-end">

              <input type="hidden" id="payPurchaseId" name="purchase_id">
              <input type="hidden" id="payBalanceDue" name="pay_balance_due">

              <div class="input-group mr-2">
                <div class="input-group-prepend">
                  <span class="input-group-text">₦</span>
                </div>
                <input
                  type="number"
                  class="form-control"
                  id="payAmount"
                  name="amount"
                  placeholder="Enter amount"
                  min="0.01"
                  step="0.01"
                  required
                >
              </div>

              <button type="submit" class="btn btn-success" id="payPurchaseBtn">
                Pay
              </button>

            </form>

            <div id="payAmount_err" class="text-danger small"></div>
            <div id="payStatusNote" class="text-success small mt-1 d-none">Paid in full</div>

          </div>

        </div>

      </div>

    </div>

  </div>

</div>


<!-- ============================
     NEW PURCHASE MODAL
============================= -->
<div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="purchaseModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content shadow rounded">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="purchaseModalLabel">New Purchase</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close new purchase modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label for="purchaseSupplier">Supplier</label>
          <select id="purchaseSupplier" class="form-control">
            <option value="">Select supplier</option>
          </select>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="purchaseItemsTable">
            <thead class="thead-light">
              <tr>
                <th>Product</th>
                <th width="110">Qty</th>
                <th width="140">Cost</th>
                <th width="140">Total</th>
                <th width="80">Action</th>
              </tr>
            </thead>
            <tbody id="purchaseItems">
              <tr>
                <td colspan="5" class="text-center text-muted">No items added</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
          <button class="btn btn-sm btn-primary" id="addItemRow" type="button">+ Add Item</button>
          <small class="text-muted">Tip: selecting a product pre-fills the current cost price.</small>
        </div>

        <div class="form-group">
          <label for="amountPaid">Amount Paid</label>
          <input type="number" id="amountPaid" class="form-control" min="0" step="0.01" placeholder="Amount Paid">
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Total: <span id="purchaseTotal">0.00</span></h5>
          <button class="btn btn-success" id="savePurchaseBtn" type="button">Save Purchase</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============================
     NEW SALE MODAL
============================= -->
<div class="modal fade" id="saleModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="saleModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content shadow rounded">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="saleModalLabel">New Sale</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close new sale modal">
          <span>&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label for="saleCustomer">Customer</label>
          <select id="saleCustomer" class="form-control">
            <option value="">Select customer</option>
          </select>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover" id="saleItemsTable">
            <thead class="thead-light">
              <tr>
                <th>Product</th>
                <th width="110">Qty</th>
                <th width="140">Price</th>
                <th width="140">Total</th>
                <th width="80">Action</th>
              </tr>
            </thead>
            <tbody id="saleItems">
              <tr>
                <td colspan="5" class="text-center text-muted">No items added</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
          <button class="btn btn-sm btn-primary" id="addSaleItem" type="button">+ Add Item</button>
          <small class="text-muted">Tip: selecting a product pre-fills the selling price.</small>
        </div>

        <div class="form-group">
          <label for="saleAmountPaid">Amount Paid</label>
          <input type="number" id="saleAmountPaid" class="form-control" min="0" step="0.01" placeholder="Amount Paid">
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Total: <span id="saleTotal">0.00</span></h5>
          <button class="btn btn-success" id="saveSaleBtn" type="button">Complete Sale</button>
        </div>
      </div>
    </div>
  </div>
</div>







<!-- ============================
     EDIT COMPANY MODALS
============================= -->
<div class="modal fade" id="editCompanyNameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editCompanyNameForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-building mr-2"></i>Edit Company Name
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="text" class="form-control" id="editCompanyNameInput" required>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-block">
            Save Changes
          </button>
        </div>

      </form>

    </div>
  </div>
</div>






<!-- ============================
     ADD PRODUCT MODAL
============================= -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">
          <i class="fa fa-box mr-2"></i>Add Product
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>

      <form id="addProductForm" enctype="multipart/form-data">
        <div class="modal-body">

          <div class="form-group">
            <label>Product Name</label>
            <input type="text" name="product_name" id="productNameInput" class="form-control" required>
          </div>

          <div class="form-group">
            <label>Unit</label>
            <select name="product_unit" id="productUnitSelect1" class="form-control" required></select>
          </div>

           <div class="form-group">
            <label>Reorder Level</label>
            <input type="number" name="reorder_level" id="reorderLevelInput" class="form-control" value="0" min="0">
            </div>

          <div class="form-group">
            <label>Product Category</label>
            <select name="category_id" id="productCategorySelect" class="form-control" required></select>
          </div>

          <div class="form-group">
            <label>Product Image</label>
            <input type="file" name="product_image" id="productImageInput" class="form-control">
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-save mr-1"></i>Save Product
          </button>
        </div>
      </form>

    </div>
  </div>
</div>



<!-- ============================
     EDIT PRODUCT MODAL
============================= -->
<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editProductForm" enctype="multipart/form-data">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-pencil mr-2"></i>Edit Product
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="product_id" id="editProductIdInput">

          <div class="form-group">
            <label>Product Name</label>
            <input type="text" name="product_name" id="editProductNameInput" class="form-control" required>
          </div>


           <div class="form-group">
            <label>Change Category</label>
            <select name="category_id" id="editProductCategorySelect" class="form-control" required></select>
          </div>

           <div class="form-group">
            <label>Change Unit</label>
            <select name="product_unit" id="editProductUnitSelect" class="form-control" required></select>
          </div>

           <div class="form-group">
            <label>Change Reorder Level</label>
            <input type="number" name="reorder_level" id="editReorderLevelInput" class="form-control" value="0" min="0">
          </div>


            <div class="form-group">
                <label>Change Cost Price</label>
                <input type="number" name="cost_price" id="editCostPriceInput" class="form-control" value="0.00" min="0" step="0.01">
            </div>

             <div class="form-group">
                <label>Change Selling Price</label>
                <input type="number" name="selling_price" id="editSellingPriceInput" class="form-control" value="0.00" min="0" step="0.01">
            </div>

              <div class="form-group">
                <label>Change Status</label>
                <select name="status" id="editStatusSelect" class="form-control" required>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
          </div>

          <div class="form-group">
            <label>Change Image</label>
            <input type="file" name="product_image" id="editProductImageInput" class="form-control">
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save mr-1"></i>Update Product
          </button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- ============================
     RESTOCK PRODUCT MODAL
============================= -->
<div class="modal fade" id="restockProductModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="restockProductForm">

        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">
            <i class="fa fa-plus mr-2"></i>Restock Product
          </h5>
            <button type="button" class="close text-white" data-dismiss="modal">
                <span>&times;</span>
            </button>
        </div>

        <div class="modal-body">
            <h4 class="mb-4 text-center" id="restockProductTitle"></h4>

          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" id="restockQuantityInput" class="form-control" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-plus mr-1"></i>Restock
          </button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- ============================
     ADD CATEGORY MODAL
============================= -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="addCategoryForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-plus mr-2"></i>Add Category
          </h5>
            <button type="button" class="close text-white" data-dismiss="modal">
                <span>&times;</span>
            </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Category Name</label>
            <input type="text" name="category_name" id="categoryNameInput" class="form-control" required>
          </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="category_description" id="categoryDescriptionInput" class="form-control" rows="3"></textarea> 
            </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-block">
            <i class="fa fa-plus mr-1"></i>Add Category
          </button>
        </div>

      </form>

    </div>
  </div>
</div>



<!-- ============================
    EDIT CATEGORY MODAL
============================= -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow rounded">

      <form id="editCategoryForm">

        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="fa fa-pencil mr-2"></i>Edit Category
          </h5>
            <button type="button" class="close text-white" data-dismiss="modal">
                <span>&times;</span>
            </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="category_id" id="editCategoryIdInput">

          <div class="form-group">
            <label>Category Name</label>
            <input type="text" name="category_name" id="editCategoryNameInput" class="form-control" required>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="category_description" id="editCategoryDescriptionInput" class="form-control" rows="3"></textarea>
          </div>
    

        <div class="form-group">
            <label>Change Status</label>
            <select name="status" id="editCategoryStatusSelect" class="form-control" required>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
          </div>
    </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-block">
            <i class="fa fa-pencil mr-1"></i>Update Category
          </button>
        </div>

      </form>

    </div>
  </div>
</div>


<!-- Image Viewer -->
<div id="imageViewer" class="image-viewer">
    
    <span class="close-viewer">&times;</span>

    <img class="viewer-content" id="viewerImg">

</div>
