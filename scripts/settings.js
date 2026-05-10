// HIDE BACK BUTTON ON LARGER SCREENS
function toggleBackButton() {
    if ($(window).width() < 992) {
        $(".back-btn").show();
    } else {
        $(".back-btn").hide();
    }
}

// INITIAL CHECK
toggleBackButton();

// CHECK ON RESIZE
$(window).on("resize", function () {
    toggleBackButton();
});

// MOBILE SIDEBAR
$("#mobileMenuBtn").on("click", function () {
    $("#sidebar").toggleClass("show");
});

// TAB SWITCHING
$(".settings-item").on("click", function () {

    // REMOVE ACTIVE CLASS
    $(".settings-item").removeClass("active");

    // ADD ACTIVE TO CLICKED ITEM
    $(this).addClass("active");

    // GET TARGET TAB
    let target = $(this).data("tab");

    // HIDE ALL TABS
    $(".tab-content").removeClass("active");

    // SHOW SELECTED TAB
    $("#" + target).addClass("active");

    // CLOSE SIDEBAR ON MOBILE
    if ($(window).width() < 992) {
        $("#sidebar").removeClass("show");
    }

});

    // CLOSE SIDEBAR ON BACK BUTTON (MOBILE)
    $(document).on("click", ".back-btn", function (e) {
      if ($(window).width() < 992) {
        $("#sidebar").removeClass("show");
    }
});

//CLOSE SIDEBAR ON OUTSIDE CLICK (MOBILE)
$(document).on("click", function (e) {
    if (!$(e.target).closest("#sidebar, #mobileMenuBtn").length) {
        if ($(window).width() < 992) {
            $("#sidebar").removeClass("show");
        }
    }
});

// CLOSE SIDEBAR ON RESIZE
$(window).on("resize", function () {
    if ($(window).width() >= 992) {
        $("#sidebar").removeClass("show");
    }
});

const settingsCsrfToken = $('meta[name="csrf-token"]').attr('content') || "";
const settingsApp = new AppCore(settingsCsrfToken);

/* ===============================================
   BACKUP TAB HANDLERS
   =============================================== */

function getBackupPayload(response) {
    if (!response || typeof response !== 'object') {
        return null;
    }

    if (response.data && typeof response.data === 'object') {
        return response.data.data ?? response.data;
    }

    return response.data ?? response;
}

// HELPER: Show toast notification
function showBackupToast(message, type = 'info') {
    const toastClass = type === 'error' ? 'alert-danger' : type === 'success' ? 'alert-success' : 'alert-info';
    const toast = $(`
        <div class="alert ${toastClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
    
    const container = $('#backupTab .alert-container');
    if (container.length === 0) {
        $('#backupTab').prepend('<div class="alert-container"></div>');
        $('#backupTab .alert-container').append(toast);
    } else {
        container.append(toast);
    }
    
    setTimeout(() => {
        toast.fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

// HELPER: Set loading state on button
function setButtonLoading(btn, isLoading) {
    if (isLoading) {
        btn.prop('disabled', true);
        btn.data('original-html', btn.html());
        btn.html('<i class="fas fa-spinner fa-spin"></i> Loading...');
    } else {
        btn.prop('disabled', false);
        btn.html(btn.data('original-html'));
    }
}

function submitBackupDownload(action, fields = {}) {
    const $form = $('<form>', {
        method: 'POST',
        action: 'apiSettings.php',
        style: 'display:none;'
    });

    $form.append($('<input>', { type: 'hidden', name: 'action', value: action }));
    $form.append($('<input>', { type: 'hidden', name: 'csrf_token', value: settingsCsrfToken }));

    Object.entries(fields).forEach(([key, value]) => {
        $form.append($('<input>', { type: 'hidden', name: key, value: value ?? '' }));
    });

    $('body').append($form);
    $form.trigger('submit');
    setTimeout(() => $form.remove(), 0);
}

// GET BACKUP CAPABILITY ON PAGE LOAD
function getBackupCapability() {
    settingsApp.ajaxHelper({
        url: 'apiSettings.php',
        action: 'getBackupCapability',
        silent: true,
        onSuccess: function(response) {
            const capability = getBackupPayload(response) || {};
            const supported = !!capability.supported;

            const capText = supported
                ? `✓ Backups Supported - Scheduler: ${capability.scheduler_enabled ? 'Enabled' : 'Disabled'} | Retention: ${capability.retention_days} days`
                : '✗ Backups Not Supported on this installation';

            $('#backupCapabilityText').text(capText);
            $('#createBackupBtn').prop('disabled', !supported);
        },
        onError: function() {
            $('#backupCapabilityText').text('? Could not check backup capability');
            $('#createBackupBtn').prop('disabled', true);
        }
    });
}

// LOAD BACKUPS LIST
function loadBackups() {
    settingsApp.ajaxHelper({
        url: 'apiSettings.php',
        action: 'loadBackups',
        silent: true,
        onSuccess: function(response) {
            const payload = getBackupPayload(response);
            const backups = Array.isArray(payload)
                ? payload
                : Array.isArray(payload?.data)
                    ? payload.data
                    : [];

            const tbody = $('#backupListBody');
            tbody.empty();

            if (backups.length === 0) {
                tbody.html('<tr><td colspan="5" class="backup-empty">No backups available.</td></tr>');
                $('#backupListMeta').text('0 files');
                return;
            }

            backups.forEach(backup => {
                const createdDate = new Date((backup.created_at || 0) * 1000).toLocaleDateString() + ' ' +
                    new Date((backup.created_at || 0) * 1000).toLocaleTimeString();
                const sizeKB = ((backup.size || 0) / 1024).toFixed(2);
                const isAutoText = backup.is_auto ? '<span class="badge badge-info">Auto</span>' : '<span class="badge badge-secondary">Manual</span>';

                const row = `
                    <tr>
                        <td class="backup-filename" title="${backup.filename}">${backup.filename}</td>
                        <td>${createdDate}</td>
                        <td>${sizeKB} KB</td>
                        <td>${isAutoText}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info backup-download-btn" data-filename="${backup.filename}">
                                <i class="fas fa-download"></i> JSON
                            </button>
                            ${backup.is_encrypted ? `
                                <button type="button" class="btn btn-sm btn-warning backup-download-enc-btn" data-filename="${backup.filename}">
                                    <i class="fas fa-lock"></i> Encrypted
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });

            $('#backupListMeta').text(backups.length + ' file' + (backups.length !== 1 ? 's' : ''));
        },
        onError: function() {
            $('#backupListBody').html('<tr><td colspan="5" class="backup-empty">Error loading backups.</td></tr>');
            showBackupToast('Failed to load backups', 'error');
        }
    });
}

// CREATE BACKUP BUTTON
$('#createBackupBtn').on('click', function() {
    const btn = $(this);
    setButtonLoading(btn, true);
    
    settingsApp.ajaxHelper({
        url: 'apiSettings.php',
        action: 'createBackup',
        silent: true,
        onSuccess: function(response) {
            const payload = getBackupPayload(response) || {};
            const filename = payload.filename || payload.name || 'backup';
            setButtonLoading(btn, false);
            showBackupToast('✓ Backup created successfully: ' + filename, 'success');
            loadBackups();
        },
        onError: function() {
            setButtonLoading(btn, false);
            showBackupToast('✗ Error creating backup', 'error');
        }
    });
});

// REFRESH BACKUPS LIST BUTTON
$('#refreshBackupListBtn').on('click', function() {
    const btn = $(this);
    setButtonLoading(btn, true);
    
    loadBackups();
    
    setTimeout(() => {
        setButtonLoading(btn, false);
    }, 500);
});

// RESTORE BACKUP FROM JSON UPLOAD
$('#restoreBackupUploadBtn').on('click', function() {
    const fileInput = $('#restoreBackupFileInput');
    const file = fileInput[0].files[0];
    
    if (!file) {
        showBackupToast('Please select a backup file', 'error');
        return;
    }
    
    if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
        showBackupToast('Please select a valid .json file', 'error');
        return;
    }
    
    // Confirm restore
    if (!confirm('Restore this backup? This will overwrite current company data.')) {
        return;
    }
    
    const btn = $(this);
    setButtonLoading(btn, true);

    const formData = new FormData();
    formData.append('backupFile', file);

    settingsApp.ajaxHelper({
        url: 'apiSettings.php',
        action: 'restoreBackup',
        data: formData,
        silent: true,
        onSuccess: function() {
            setButtonLoading(btn, false);
            showBackupToast('✓ Backup restored successfully!', 'success');
            fileInput.val('');
            loadBackups();
        },
        onError: function() {
            setButtonLoading(btn, false);
            showBackupToast('✗ Error restoring backup', 'error');
        }
    });
});

// RESTORE ENCRYPTED BACKUP
$('#restoreEncryptedBackupBtn').on('click', function() {
    const fileInput = $('#restoreEncryptedBackupFileInput');
    const passphraseInput = $('#restoreEncryptedPassphrase');
    const file = fileInput[0].files[0];
    const passphrase = passphraseInput.val();
    
    if (!file) {
        showBackupToast('Please select an encrypted backup file', 'error');
        return;
    }
    
    if (!passphrase || passphrase.length < 8) {
        showBackupToast('Passphrase must be at least 8 characters', 'error');
        return;
    }
    
    // Confirm restore
    if (!confirm('Restore this encrypted backup? This will overwrite current company data.')) {
        return;
    }
    
    const btn = $(this);
    setButtonLoading(btn, true);
    
    const formData = new FormData();
    formData.append('encryptedBackupFile', file);
    formData.append('passphrase', passphrase);

    settingsApp.ajaxHelper({
        url: 'apiSettings.php',
        action: 'restoreEncryptedBackup',
        data: formData,
        silent: true,
        onSuccess: function() {
            setButtonLoading(btn, false);
            showBackupToast('✓ Encrypted backup restored successfully!', 'success');
            fileInput.val('');
            passphraseInput.val('');
            loadBackups();
        },
        onError: function() {
            setButtonLoading(btn, false);
            showBackupToast('✗ Error restoring encrypted backup', 'error');
        }
    });
});

// DOWNLOAD BACKUP (JSON)
$(document).on('click', '.backup-download-btn', function() {
    const filename = $(this).data('filename');
    submitBackupDownload('downloadBackup', { filename });
});

// DOWNLOAD ENCRYPTED BACKUP
$(document).on('click', '.backup-download-enc-btn', function() {
    const filename = $(this).data('filename');
    const passphrase = window.prompt('Enter the backup passphrase');
    if (!passphrase) {
        return;
    }

    if (passphrase.length < 8) {
        showBackupToast('Passphrase must be at least 8 characters', 'error');
        return;
    }

    submitBackupDownload('downloadEncryptedBackup', {
        filename,
        passphrase
    });
});

// LOAD BACKUPS AND CAPABILITY ON PAGE LOAD IF BACKUP TAB EXISTS
$(document).ready(function() {
    if ($('#backupTab').length > 0) {
        getBackupCapability();
        loadBackups();
    }
});
