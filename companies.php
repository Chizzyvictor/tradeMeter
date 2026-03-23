<?php 
include "INC/header.php"; ?>

<div class="tab-content" id="home">
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light d-flex justify-content-between align-items-center px-3">
    <a href="login.php" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
    <a class="navbar-brand mx-auto" href="#">COMPANIES</a>
  </nav>

  <!-- Companies Table -->
  <div id="companiesContainer" class="p-3 table-responsive">
    <!-- Search box -->
    <input type="text" id="searchBox" placeholder="Search companies..." class="form-control mb-3" style="max-width:300px;">

    <table id="companiesTable" class="table table-bordered table-hover">
      <thead class="thead-light">
        <tr>
          <th data-column="cid" data-order="asc">ID ▲</th>
          <th data-column="cName" data-order="asc">Company Name ▲</th>
          <th data-column="cEmail" data-order="asc">Email ▲</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
      </tbody>
    </table>

    <div class="d-flex justify-content-between align-items-center mt-2" id="companiesPaginationWrap">
      <small id="companiesCountInfo" class="text-muted">0 records</small>
      <div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="companiesPrevBtn">Prev</button>
        <span id="companiesPageInfo" class="mx-2 text-muted">Page 1 / 1</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="companiesNextBtn">Next</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="companyModal" tabindex="-1" aria-labelledby="companyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="companyModalLabel">Company Details</h5>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <span id="myLogo"> </span>
        <h4 id="modalTitle" class="mb-2"></h4>
        <p><strong>Email:</strong> <span id="modalEmail"></span></p>
        <p><strong>Registered:</strong> <span id="modalRegDate"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include "INC/footer.php"; ?>
<script src="scripts/companies.js?v=<?= asset_ver('scripts/companies.js') ?>"></script>

</body>
</html>