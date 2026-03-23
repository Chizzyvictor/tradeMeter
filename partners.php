<?php
session_start();
include "INC/isLogedin.php";
include "INC/header.php";
include "INC/navbar.php";
?>

<div class="tab-content panel-box" id="home">
	<div class="filter-bar d-flex justify-content-between align-items-center my-3 flex-wrap">
		<button type="button" class="filter-btn bg-success text-light mb-2" id="totalPartners">0 Partners</button>
		<button type="button" class="filter-btn cfilter-btn active mb-2" id="allPartnersBtn" data-action="loadAllPartners">All Partners</button>
		<button type="button" class="filter-btn cfilter-btn mb-2" id="activeDebtorsBtn" data-action="loadActivePartnerDebtors">Active Debtors</button>
		<button type="button" class="filter-btn cfilter-btn mb-2" id="activeCreditorsBtn" data-action="loadActivePartnerCreditors">Active Creditors</button>

		<form id="searchPartnersForm" class="form-inline my-2 my-lg-0" action="#" method="post" onsubmit="return false;">
			<input id="searchPartners" class="form-control mr-sm-2" type="search" placeholder="Search partner" aria-label="Search">
		</form>

		<button type="button" class="btn btn-info nav-btn" data-toggle="modal" data-target="#addPartnerModal" aria-label="Add Partner">&plus;</button>
	</div>


	<div class="container-fluid note">
		<h4>Partners</h4>
		<h6>Manage and track all your customers and suppliers</h6>
		<hr>
		<div class="table-responsive partners-table-wrap">
			<table id="partnersTable" border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;" class="table">
				<thead>
					<tr>
						<th>Partner Image</th>
						<th>Partner Name </th>
						<th>Amount </th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<div class="tab-content panel-box" id="aDetails">
	<nav class="navbar navbar-expand-lg navbar-light bg-light">
		<button class="btn btn-secondary back" type="button" id="back" aria-label="Back to partners">
			<i class="fa fa-arrow-left"></i> back
		</button>
		<a class="navbar-brand" href="#">Partner details</a>
		<button type="button" class="btn btn-danger action-btn" id="addDebt">-ADD DEBT</button>
		<button type="button" class="btn btn-info action-btn" id="payDebt">+PAY DEBT</button>
		<button type="button" class="btn btn-primary action-btn" id="editPartnerBtn">EDIT PARTNER</button>
		<button type="button" class="btn btn-secondary action-btn" id="viewMode"></button>
	</nav>
	<div id="partnerDetails"></div>
    <div id="transactionsView" class="transactions-wrap viewMode">
        <h4 class="mt-4">Partner Transactions</h4>
				 <div class="table-responsive partners-transactions-table-wrap">
           <table id="transactionsBody" class="table transactions-table">
			<thead>
				<tr>
					<th>TYPE</th>
					<th>DATE</th>
					<th>TOTAL</th>
                    <th>PAID</th>
                    <th>STATUS</th>
				</tr>
			  </thead>
			<tbody></tbody>           
           </table>
        </div>
		  <div id="partnerTransactionsCards" class="partners-transactions-cards" aria-live="polite"></div>
     </div> 

     <div id="ledgerView" class="ledger-wrap viewMode">
        <h4 class="mt-4">Partner Ledger</h4>
			<div class="table-responsive partners-ledger-table-wrap">
           <table id="ledgerBody" class="table ledger-table">
			<thead>
				<tr>
                    <th>TYPE</th>
                    <th>DATE</th>
                    <th>DEBIT</th>
                    <th>CREDIT</th>
                    <th>OUTSTANDING</th>
                    <th>ADVANCE PAYMENT</th>
                    <th>DESCRIPTION</th>
                </tr>
              </thead>
            <tbody></tbody>
           </table>
        </div>
		  <div id="partnerLedgerCards" class="partners-ledger-cards" aria-live="polite"></div>
     </div> 

     
     </div> 




<?php include "INC/footer.php"; ?>

<script src="scripts/partners.js?ver=<?= time() ?>"></script>

</body>
</html>

		<div id="partnersCards" class="partners-cards" aria-live="polite"></div>
