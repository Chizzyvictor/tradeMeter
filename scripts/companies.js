$(document).ready(function () {
  const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
  const app = new AppCore(csrfToken);
  const authApp = new Auth(app);

  let companies = [];
  let filteredCompanies = [];
  const pageSize = 10;
  let currentPage = 1;
  let sortColumn = 'cid';
  let sortOrder = 'asc';

  const escapeHTML = (value) => $('<div>').text(String(value ?? '')).html();

  function showTableLoading() {
    $('#companiesTable tbody').html(`
      <tr>
        <td colspan="3" class="text-center">
          <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
        </td>
      </tr>
    `);
  }

  function openCompanyModal() {
    const modalElement = document.getElementById('companyModal');
    if (!modalElement) return;
    $('#companyModal').modal('show');
  }

  function updatePaginationControls() {
    const total = filteredCompanies.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));

    if (currentPage > totalPages) currentPage = totalPages;

    $('#companiesCountInfo').text(`${total} record${total === 1 ? '' : 's'}`);
    $('#companiesPageInfo').text(`Page ${currentPage} / ${totalPages}`);
    $('#companiesPrevBtn').prop('disabled', currentPage <= 1);
    $('#companiesNextBtn').prop('disabled', currentPage >= totalPages);
  }

  function applySort() {
    filteredCompanies.sort((a, b) => {
      let valA = String(a?.[sortColumn] ?? '').toLowerCase();
      let valB = String(b?.[sortColumn] ?? '').toLowerCase();

      if ($.isNumeric(valA) && $.isNumeric(valB)) {
        valA = Number(valA);
        valB = Number(valB);
      }

      if (valA === valB) return 0;
      if (sortOrder === 'asc') return valA > valB ? 1 : -1;
      return valA < valB ? 1 : -1;
    });
  }

  function renderTable() {
    const tbody = $('#companiesTable tbody');
    tbody.empty();

    if (!filteredCompanies.length) {
      tbody.html('<tr><td colspan="3" class="text-center text-muted">No companies found</td></tr>');
      updatePaginationControls();
      return;
    }

    const startIndex = (currentPage - 1) * pageSize;
    const pageRows = filteredCompanies.slice(startIndex, startIndex + pageSize);

    let rows = '';
    pageRows.forEach((company) => {
      rows += `
        <tr class="companyRow" data-id="${escapeHTML(company.cid)}" style="cursor:pointer;">
          <td>${escapeHTML(company.cid)}</td>
          <td>${escapeHTML(company.cName)}</td>
          <td>${escapeHTML(company.cEmail)}</td>
        </tr>
      `;
    });

    tbody.html(rows);
    updatePaginationControls();
  }

  function loadCompanies() {
    showTableLoading();

    $.ajax({
      url: 'apiAuthentications.php',
      type: 'POST',
      data: { action: 'loadCompanies', csrf_token: csrfToken },
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          companies = Array.isArray(response.data) ? response.data : [];
          filteredCompanies = [...companies];
          applySort();
          renderTable();
        } else {
          $('#companiesTable tbody').html(`<tr><td colspan="3" class="text-center text-danger">${response.text}</td></tr>`);
        }
      },
      error: function (_, __, error) {
        console.error('Error loading companies:', error);
        $('#companiesTable tbody').html('<tr><td colspan="3" class="text-center text-danger">Failed to load companies!</td></tr>');
      }
    });
  }

  $('#searchBox').on('keyup', function () {
    const value = String($(this).val() || '').toLowerCase();
    filteredCompanies = companies.filter((company) =>
      String(company.cName || '').toLowerCase().includes(value) ||
      String(company.cEmail || '').toLowerCase().includes(value) ||
      String(company.cid || '').includes(value)
    );
    currentPage = 1;
    applySort();
    renderTable();
  });

  $('#companiesPrevBtn').on('click', function () {
    if (currentPage > 1) {
      currentPage -= 1;
      renderTable();
    }
  });

  $('#companiesNextBtn').on('click', function () {
    const totalPages = Math.max(1, Math.ceil(filteredCompanies.length / pageSize));
    if (currentPage < totalPages) {
      currentPage += 1;
      renderTable();
    }
  });

  $('#companiesTable thead th').on('click', function () {
    const column = $(this).data('column');
    if (!column) return;

    if (sortColumn === column) {
      sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
      sortColumn = column;
      sortOrder = 'asc';
    }

    applySort();
    renderTable();

    $('#companiesTable thead th').each(function () {
      const col = $(this).data('column');
      const baseText = $(this).text().replace(/ ▲| ▼/g, '').trim();
      if (col === column) {
        $(this).text(baseText + (sortOrder === 'asc' ? ' ▲' : ' ▼'));
      } else {
        $(this).text(baseText + ' ▲');
      }
    });
  });

  $(document).on('click', '.companyRow', function () { const id = $(this).data('id');
    const company = companies.find((c) => String(c.cid) === String(id));
    if (!company) return;
    const logoFile = String(company.cLogo || 'logo.jpg').trim();
    const logoPath = app.resolveImagePath(logoFile, 'Images/companyDP', 'Images/companyDP/logo.jpg');
    const fallbackLogo = 'Images/companyDP/logo.jpg';
    const regDate = app.formatDateSafe(company.regDate, company.regDate || '-');

    const $logo = $('<img>', {
      src: logoPath,
      class: 'img-thumbnail mb-3',
      alt: 'Company Logo'
    }).css('max-height', '150px').attr('class', 'image img-thumbnail mb-3');

    $logo.on('error', function () {
      const currentSrc = String($(this).attr('src') || '');
      if (currentSrc.indexOf(fallbackLogo) !== -1) return;
      $(this).attr('src', fallbackLogo);
    });

    $('#myLogo').empty().append($logo);
    $('#modalTitle').text(company.cName);
    $('#modalEmail').text(company.cEmail);
    $('#modalRegDate').text(regDate);

    openCompanyModal();
  });

  authApp.loadCompanyLogo();
  loadCompanies();
});
