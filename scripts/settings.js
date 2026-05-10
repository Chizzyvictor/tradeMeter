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

/* ===============================================
   BACKUP TAB HANDLERS
   =============================================== */

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

// GET BACKUP CAPABILITY ON PAGE LOAD
function getBackupCapability() {
    $.ajax({
        url: 'apiSettings.php',
        method: 'POST',
        data: {
            action: 'getBackupCapability'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const capText = response.data.supported 
                    ? `✓ Backups Supported - Scheduler: ${response.data.scheduler_enabled ? 'Enabled' : 'Disabled'} | Retention: ${response.data.retention_days} days`
                    : '✗ Backups Not Supported on this installation';
                
                $('#backupCapabilityText').text(capText);
                $('#createBackupBtn').prop('disabled', !response.data.supported);
            }
        },
        error: function() {
            $('#backupCapabilityText').text('? Could not check backup capability');
            $('#createBackupBtn').prop('disabled', true);
        }
    });
}

// LOAD BACKUPS LIST
function loadBackups() {
    $.ajax({
        url: 'apiSettings.php',
        method: 'POST',
        data: {
            action: 'loadBackups'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && Array.isArray(response.data)) {
                const backups = response.data;
                const tbody = $('#backupListBody');
                tbody.empty();
                
                if (backups.length === 0) {
                    tbody.html('<tr><td colspan="5" class="backup-empty">No backups available.</td></tr>');
                    $('#backupListMeta').text('0 files');
                } else {
                    backups.forEach(backup => {
                        const createdDate = new Date(backup.created_at * 1000).toLocaleDateString() + ' ' + 
                                           new Date(backup.created_at * 1000).toLocaleTimeString();
                        const sizeKB = (backup.size / 1024).toFixed(2);
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
                }
            } else {
                $('#backupListBody').html('<tr><td colspan="5" class="backup-empty">Error loading backups.</td></tr>');
                showBackupToast('Error loading backups: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr) {
            $('#backupListBody').html('<tr><td colspan="5" class="backup-empty">Error loading backups.</td></tr>');
            showBackupToast('Failed to load backups', 'error');
        }
    });
}

// CREATE BACKUP BUTTON
$('#createBackupBtn').on('click', function() {
    const btn = $(this);
    setButtonLoading(btn, true);
    
    $.ajax({
        url: 'apiSettings.php',
        method: 'POST',
        data: {
            action: 'createBackup'
        },
        dataType: 'json',
        success: function(response) {
            setButtonLoading(btn, false);
            if (response.success) {
                showBackupToast('✓ Backup created successfully: ' + response.data.filename, 'success');
                loadBackups(); // Refresh the list
            } else {
                showBackupToast('✗ Failed to create backup: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr) {
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
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const jsonContent = e.target.result;
            
            $.ajax({
                url: 'apiSettings.php',
                method: 'POST',
                data: {
                    action: 'restoreBackup',
                    backup_data: jsonContent
                },
                dataType: 'json',
                success: function(response) {
                    setButtonLoading(btn, false);
                    if (response.success) {
                        showBackupToast('✓ Backup restored successfully!', 'success');
                        fileInput.val('');
                        loadBackups();
                    } else {
                        showBackupToast('✗ Failed to restore: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr) {
                    setButtonLoading(btn, false);
                    showBackupToast('✗ Error restoring backup', 'error');
                }
            });
        } catch (e) {
            setButtonLoading(btn, false);
            showBackupToast('✗ Invalid backup file format', 'error');
        }
    };
    reader.readAsText(file);
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
    formData.append('action', 'restoreEncryptedBackup');
    formData.append('backup_file', file);
    formData.append('passphrase', passphrase);
    
    $.ajax({
        url: 'apiSettings.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            setButtonLoading(btn, false);
            if (response.success) {
                showBackupToast('✓ Encrypted backup restored successfully!', 'success');
                fileInput.val('');
                passphraseInput.val('');
                loadBackups();
            } else {
                showBackupToast('✗ Failed to restore: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr) {
            setButtonLoading(btn, false);
            showBackupToast('✗ Error restoring encrypted backup', 'error');
        }
    });
});

// DOWNLOAD BACKUP (JSON)
$(document).on('click', '.backup-download-btn', function() {
    const filename = $(this).data('filename');
    window.location.href = 'apiSettings.php?action=downloadBackup&filename=' + encodeURIComponent(filename);
});

// DOWNLOAD ENCRYPTED BACKUP
$(document).on('click', '.backup-download-enc-btn', function() {
    const filename = $(this).data('filename');
    window.location.href = 'apiSettings.php?action=downloadEncryptedBackup&filename=' + encodeURIComponent(filename);
});

// LOAD BACKUPS AND CAPABILITY ON PAGE LOAD IF BACKUP TAB EXISTS
$(document).ready(function() {
    if ($('#backupTab').length > 0) {
        getBackupCapability();
        loadBackups();
    }
});
