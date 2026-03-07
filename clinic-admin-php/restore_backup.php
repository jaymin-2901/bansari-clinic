<?php
/**
 * Bansari Homeopathy – 1-Click Database Restore
 * Admin → System → Restore Backup
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Restore Backup';
$csrfToken = generateCSRFToken();

// API endpoint for restore operations
$restoreApi = 'api/restore_backup.php';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.restore-steps { list-style: none; padding: 0; margin: 0; }
.restore-steps li {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid var(--border-color, #e9ecef);
    font-size: 0.9rem; color: var(--text-secondary, #6c757d);
}
.restore-steps li:last-child { border-bottom: none; }
.restore-steps .step-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
    background: var(--bg-card, #f8f9fa); color: var(--text-secondary, #6c757d);
    border: 2px solid var(--border-color, #dee2e6);
    transition: all 0.3s;
}
.restore-steps li.active .step-icon {
    background: #0d6efd; color: #fff; border-color: #0d6efd;
    animation: pulse-step 1s infinite;
}
.restore-steps li.done .step-icon {
    background: #198754; color: #fff; border-color: #198754;
}
.restore-steps li.error .step-icon {
    background: #dc3545; color: #fff; border-color: #dc3545;
}
.restore-steps li.active { color: var(--text-primary, #212529); font-weight: 500; }
.restore-steps li.done { color: #198754; }
.restore-steps li.error { color: #dc3545; }
@keyframes pulse-step {
    0%, 100% { box-shadow: 0 0 0 0 rgba(13,110,253,0.4); }
    50% { box-shadow: 0 0 0 6px rgba(13,110,253,0); }
}
.upload-zone {
    border: 2px dashed var(--border-color, #dee2e6);
    border-radius: 12px; padding: 40px 20px;
    text-align: center; cursor: pointer;
    transition: border-color 0.3s, background 0.3s;
    background: var(--bg-card, #fff);
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: #0d6efd; background: rgba(13,110,253,0.03);
}
.upload-zone .upload-icon { font-size: 3rem; color: #0d6efd; }
.result-card { border-left: 4px solid; border-radius: 8px; }
.result-card.success { border-left-color: #198754; }
.result-card.failed { border-left-color: #dc3545; }
</style>

<!-- Upload Section -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="table-container">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-cloud-upload me-2"></i>Upload Backup File</h6>
            </div>
            <div class="p-4">
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('backupFileInput').click()">
                    <div class="upload-icon mb-2"><i class="bi bi-file-earmark-arrow-up"></i></div>
                    <h6 class="fw-bold">Drag & drop your backup file here</h6>
                    <p class="text-muted small mb-2">or click to browse</p>
                    <p class="text-muted small mb-0">
                        Accepted: <code>.sql</code>, <code>.zip</code> (containing .sql) &bull; Max 100 MB
                    </p>
                </div>
                <input type="file" id="backupFileInput" accept=".sql,.zip" class="d-none">

                <!-- File info after selection -->
                <div id="fileInfo" class="d-none mt-3">
                    <div class="d-flex align-items-center gap-3 p-3 rounded" style="background:var(--bg-card,#f8f9fa);">
                        <i class="bi bi-file-earmark-code fs-3 text-primary"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold" id="fileName"></div>
                            <small class="text-muted" id="fileSize"></small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Upload progress -->
                <div id="uploadProgress" class="d-none mt-3">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadBar" style="width:0%"></div>
                    </div>
                    <small class="text-muted mt-1 d-block" id="uploadStatus">Uploading...</small>
                </div>

                <!-- Action buttons -->
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary" id="btnUpload" disabled onclick="uploadFile()">
                        <i class="bi bi-cloud-upload me-1"></i> Upload File
                    </button>
                    <button class="btn btn-danger" id="btnRestore" disabled onclick="startRestore()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Database
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Progress Steps -->
    <div class="col-lg-5">
        <div class="table-container h-100">
            <div class="table-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Restore Progress</h6>
            </div>
            <div class="p-3">
                <ul class="restore-steps" id="restoreSteps">
                    <li id="step-upload">
                        <span class="step-icon"><i class="bi bi-cloud-upload"></i></span>
                        <span>Uploading Backup</span>
                    </li>
                    <li id="step-validate">
                        <span class="step-icon"><i class="bi bi-shield-check"></i></span>
                        <span>Validating File</span>
                    </li>
                    <li id="step-safety">
                        <span class="step-icon"><i class="bi bi-safe"></i></span>
                        <span>Creating Safety Backup</span>
                    </li>
                    <li id="step-restore">
                        <span class="step-icon"><i class="bi bi-database-gear"></i></span>
                        <span>Restoring Database</span>
                    </li>
                    <li id="step-complete">
                        <span class="step-icon"><i class="bi bi-check-lg"></i></span>
                        <span>Completed</span>
                    </li>
                </ul>

                <!-- Overall progress bar -->
                <div class="mt-3">
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" id="overallProgress" style="width:0%; transition: width 0.5s;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Result -->
<div id="restoreResult" class="d-none mb-4">
    <div class="table-container result-card" id="resultCard">
        <div class="table-header">
            <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Restore Status</h6>
        </div>
        <div class="p-3">
            <div class="row g-3">
                <div class="col-sm-6">
                    <small class="text-muted d-block">Status</small>
                    <span class="fw-bold" id="resultStatus"></span>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Backup File Name</small>
                    <code id="resultFilename"></code>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Database Name</small>
                    <code id="resultDatabase"></code>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted d-block">Restore Time</small>
                    <span id="resultDuration"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Available Backups Table -->
<div class="table-container mb-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-database me-2"></i>Available Backups</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadBackups()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Backup Name</th>
                    <th>Backup Type</th>
                    <th>Date Created</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="backupTableBody">
                <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Restore Logs -->
<div class="table-container mb-4">
    <div class="table-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2"></i>Restore Logs</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadLogs()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </div>
    <div id="logContainer" class="p-0">
        <div class="text-center text-muted py-4">Loading logs...</div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger bg-opacity-10">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Confirm Database Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger py-2 mb-3">
                    <strong>Warning:</strong> This will overwrite the current database with the backup data.
                    A safety backup will be created automatically before restoring.
                </div>
                <p class="mb-1"><strong>File to restore:</strong></p>
                <code id="confirmFilename" class="d-block mb-3"></code>
                <p class="mb-1">Type <strong>RESTORE</strong> to confirm:</p>
                <input type="text" id="confirmInput" class="form-control" placeholder="Type RESTORE" autocomplete="off">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRestoreBtn" disabled onclick="executeRestore()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Now
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';
const restoreApi = '<?= $restoreApi ?>';
let selectedFile = null;
let uploadedFilename = null;
let confirmModal = null;

document.addEventListener('DOMContentLoaded', async () => {
    confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    setupDragDrop();
    // Sequential calls — PHP built-in server is single-threaded
    await loadBackups();
    await loadLogs();
});

// ─── Drag & Drop ───
function setupDragDrop() {
    const zone = document.getElementById('uploadZone');
    ['dragenter','dragover'].forEach(e => {
        zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(e => {
        zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('dragover'); });
    });
    zone.addEventListener('drop', ev => {
        if (ev.dataTransfer.files.length) {
            document.getElementById('backupFileInput').files = ev.dataTransfer.files;
            handleFileSelect(ev.dataTransfer.files[0]);
        }
    });
    document.getElementById('backupFileInput').addEventListener('change', function() {
        if (this.files.length) handleFileSelect(this.files[0]);
    });
}

function handleFileSelect(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['sql','zip'].includes(ext)) {
        alert('Only .sql and .zip files are allowed.');
        return;
    }
    if (file.size > 100 * 1024 * 1024) {
        alert('File too large. Maximum size is 100 MB.');
        return;
    }
    selectedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatBytes(file.size);
    document.getElementById('fileInfo').classList.remove('d-none');
    document.getElementById('btnUpload').disabled = false;
    document.getElementById('btnRestore').disabled = true;
    uploadedFilename = null;
    resetSteps();
}

function clearFile() {
    selectedFile = null;
    uploadedFilename = null;
    document.getElementById('backupFileInput').value = '';
    document.getElementById('fileInfo').classList.add('d-none');
    document.getElementById('btnUpload').disabled = true;
    document.getElementById('btnRestore').disabled = true;
    document.getElementById('uploadProgress').classList.add('d-none');
    resetSteps();
}

// ─── Upload ───
function uploadFile() {
    if (!selectedFile) return;
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('csrf_token', csrfToken);
    formData.append('backup_file', selectedFile);

    document.getElementById('btnUpload').disabled = true;
    document.getElementById('uploadProgress').classList.remove('d-none');
    setStep('step-upload', 'active');
    setOverallProgress(10);

    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', e => {
        if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            document.getElementById('uploadBar').style.width = pct + '%';
            document.getElementById('uploadStatus').textContent = 'Uploading... ' + pct + '%';
        }
    });

    xhr.addEventListener('load', () => {
        try {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                uploadedFilename = data.filename;
                setStep('step-upload', 'done');
                setStep('step-validate', 'done');
                setOverallProgress(30);
                document.getElementById('uploadStatus').textContent = 'Upload complete!';
                document.getElementById('btnRestore').disabled = false;
                document.getElementById('btnUpload').disabled = true;
                loadBackups();
            } else {
                setStep('step-upload', 'error');
                document.getElementById('uploadStatus').textContent = data.message;
                document.getElementById('btnUpload').disabled = false;
            }
        } catch (e) {
            setStep('step-upload', 'error');
            document.getElementById('uploadStatus').textContent = 'Server error.';
            document.getElementById('btnUpload').disabled = false;
        }
    });

    xhr.addEventListener('error', () => {
        setStep('step-upload', 'error');
        document.getElementById('uploadStatus').textContent = 'Upload failed.';
        document.getElementById('btnUpload').disabled = false;
    });

    xhr.open('POST', restoreApi);
    xhr.send(formData);
}

// ─── Restore Flow ───
function startRestore() {
    const fname = uploadedFilename;
    if (!fname) { alert('Please upload a backup file first.'); return; }

    document.getElementById('confirmFilename').textContent = fname;
    document.getElementById('confirmInput').value = '';
    document.getElementById('confirmRestoreBtn').disabled = true;
    confirmModal.show();
}

// Also allow restoring from the backup table
function restoreFromTable(filename) {
    uploadedFilename = filename;
    document.getElementById('confirmFilename').textContent = filename;
    document.getElementById('confirmInput').value = '';
    document.getElementById('confirmRestoreBtn').disabled = true;
    confirmModal.show();
}

document.getElementById('confirmInput')?.addEventListener('input', function() {
    document.getElementById('confirmRestoreBtn').disabled = (this.value !== 'RESTORE');
});

async function executeRestore() {
    confirmModal.hide();
    const filename = uploadedFilename;
    if (!filename) return;

    document.getElementById('btnRestore').disabled = true;
    document.getElementById('btnUpload').disabled = true;
    document.getElementById('restoreResult').classList.add('d-none');

    // Step 1: Safety backup
    setStep('step-safety', 'active');
    setOverallProgress(45);

    try {
        const safetyRes = await apiPost('safety_backup', {});
        if (!safetyRes.success) {
            setStep('step-safety', 'error');
            showResult(false, { filename, message: safetyRes.message });
            return;
        }
        setStep('step-safety', 'done');
        setOverallProgress(65);

        // Step 2: Restore
        setStep('step-restore', 'active');
        setOverallProgress(75);

        const restoreRes = await apiPost('restore', { filename });
        if (restoreRes.success) {
            setStep('step-restore', 'done');
            setStep('step-complete', 'done');
            setOverallProgress(100);
            showResult(true, restoreRes);
        } else {
            setStep('step-restore', 'error');
            showResult(false, restoreRes);
        }
    } catch (e) {
        setStep('step-restore', 'error');
        showResult(false, { filename, message: 'Network error.' });
    }

    loadBackups();
    loadLogs();
}

// ─── API helper ───
async function apiPost(action, params) {
    const body = new URLSearchParams({ action, csrf_token: csrfToken, ...params });
    const res = await fetch(restoreApi, { method: 'POST', body });
    return res.json();
}

// ─── Step indicator ───
function setStep(id, state) {
    const el = document.getElementById(id);
    el.className = state;
}
function resetSteps() {
    ['step-upload','step-validate','step-safety','step-restore','step-complete'].forEach(id => setStep(id, ''));
    setOverallProgress(0);
    document.getElementById('restoreResult').classList.add('d-none');
}
function setOverallProgress(pct) {
    const bar = document.getElementById('overallProgress');
    bar.style.width = pct + '%';
    bar.className = 'progress-bar' + (pct >= 100 ? ' bg-success' : pct > 0 ? ' progress-bar-striped progress-bar-animated' : '');
}

// ─── Show result card ───
function showResult(success, data) {
    const card = document.getElementById('resultCard');
    card.className = 'table-container result-card ' + (success ? 'success' : 'failed');
    document.getElementById('resultStatus').innerHTML = success
        ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Success</span>'
        : '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Failed</span>';
    document.getElementById('resultFilename').textContent = data.filename || '-';
    document.getElementById('resultDatabase').textContent = data.database || '-';
    document.getElementById('resultDuration').textContent = data.duration || '-';
    document.getElementById('restoreResult').classList.remove('d-none');
    if (success) {
        showAlert('Database restored successfully.', 'success');
    }
}

function showAlert(msg, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}-fill me-2"></i>${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.querySelector('.main-content .topbar')?.insertAdjacentElement('afterend', alert);
    setTimeout(() => alert.remove(), 6000);
}

// ─── Load backup table ───
async function loadBackups() {
    try {
        const res = await fetch(restoreApi + '?action=list');
        const data = await res.json();
        const tbody = document.getElementById('backupTableBody');

        if (!data.success || !data.backups.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-database-x fs-3 d-block mb-2"></i>No backup files found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.backups.map(b => {
            const typeClass = { 'Daily DB': 'success', 'Uploaded': 'primary', 'Safety': 'warning' }[b.type] || 'secondary';
            return `<tr>
                <td><i class="bi bi-file-earmark-code text-${typeClass} me-1"></i><code class="small">${esc(b.name)}</code></td>
                <td><span class="badge bg-${typeClass} bg-opacity-10 text-${typeClass}">${esc(b.type)}</span></td>
                <td class="small">${esc(b.created)}</td>
                <td>${esc(b.size_readable)}</td>
                <td>
                    <button class="btn btn-sm btn-outline-success" onclick="restoreFromTable('${esc(b.name)}')" title="Restore">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    ${b.source !== 'daily' ? `<button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('${esc(b.name)}','${esc(b.source)}')" title="Delete"><i class="bi bi-trash"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        document.getElementById('backupTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Failed to load backups.</td></tr>';
    }
}

// ─── Delete backup ───
async function deleteBackup(filename, source) {
    if (!confirm(`Delete "${filename}"?`)) return;
    const data = await apiPost('delete', { filename, source });
    alert(data.message);
    loadBackups();
}

// ─── Load logs ───
async function loadLogs() {
    try {
        const res = await fetch(restoreApi + '?action=logs');
        const data = await res.json();
        const container = document.getElementById('logContainer');

        if (!data.success || !data.logs.length) {
            container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-journal fs-3 d-block mb-2"></i>No restore logs yet.</div>';
            return;
        }

        let html = '<pre class="mb-0 p-3 small" style="max-height:250px; overflow-y:auto; background:var(--bg-card); color:var(--text-primary); border:none;">';
        data.logs.forEach(line => {
            const escaped = line.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            if (line.includes('ERROR') || line.includes('FAILED') || line.includes('SECURITY')) {
                html += `<span class="text-danger">${escaped}</span>\n`;
            } else if (line.includes('Successfully') || line.includes('Completed')) {
                html += `<span class="text-success">${escaped}</span>\n`;
            } else if (line.includes('Started')) {
                html += `<span class="text-info fw-bold">${escaped}</span>\n`;
            } else {
                html += escaped + '\n';
            }
        });
        html += '</pre>';
        container.innerHTML = html;
    } catch (e) {
        document.getElementById('logContainer').innerHTML = '<div class="text-center text-danger py-3">Failed to load logs.</div>';
    }
}

// ─── Utilities ───
function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024, units = ['B','KB','MB','GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + units[i];
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
