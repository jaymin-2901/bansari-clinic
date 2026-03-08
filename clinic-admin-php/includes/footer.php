    </div><!-- /.content-area -->
</div><!-- /.main-content -->
</div><!-- /.admin-wrapper -->

<?php if (!empty($extraScripts)): ?>
<?= $extraScripts ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle for mobile
document.getElementById('openSidebar')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.add('show');
});
document.getElementById('closeSidebar')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('show');
});

// Delete confirmation
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
        if (!confirm(el.dataset.confirm || 'Are you sure you want to delete this?')) {
            e.preventDefault();
        }
    });
});

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        bootstrap.Alert.getOrCreateInstance(alert).close();
    });
}, 5000);

// Theme toggle
(function() {
    const toggle = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    if (!toggle || !icon) return;

    function updateIcon() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        icon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        toggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    }
    updateIcon();

    toggle.addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('admin-theme', 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('admin-theme', 'dark');
        }
        updateIcon();
    });
})();

// Password Show/Hide Toggle (Patients Page)
(function() {
    var buttons = document.querySelectorAll('.toggle-pwd-btn');
    if (buttons.length === 0) return;
    
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var pid = this.getAttribute('data-patient-id');
            var mask = document.getElementById('pwd-' + pid);
            var icon = this.querySelector('i');
            var plainPwd = mask.getAttribute('data-plain');
            
            if (mask.textContent === '••••••••') {
                mask.textContent = plainPwd || 'N/A';
                icon.className = 'bi bi-eye-slash';
            } else {
                mask.textContent = '••••••••';
                icon.className = 'bi bi-eye';
            }
        });
    });
})();

// Clickable status update on appointments page
(function() {
    const statusBadges = document.querySelectorAll('.clickable-status');
    if (statusBadges.length === 0) return;

    const statusCycle = ['pending', 'confirmed', 'completed', 'cancelled'];
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    statusBadges.forEach(badge => {
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', async function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const currentStatus = this.dataset.current;
            
            // Find next status in cycle
            const currentIndex = statusCycle.indexOf(currentStatus);
            const nextStatus = statusCycle[(currentIndex + 1) % statusCycle.length];
            
            // Show confirmation for cancelling
            if (nextStatus === 'cancelled') {
                if (!confirm('Are you sure you want to mark this appointment as cancelled?')) {
                    return;
                }
            }

            // Visual feedback - show spinner
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            this.style.opacity = '0.7';

            try {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('appointment_id', id);
                formData.append('status', nextStatus);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('appointments.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    // Update badge
                    this.className = 'badge-status badge-' + nextStatus;
                    this.dataset.current = nextStatus;
                    this.innerHTML = nextStatus.charAt(0).toUpperCase() + nextStatus.slice(1);
                    this.style.opacity = '1';
                    
                    // Show success toast
                    showToast('Status updated to ' + nextStatus, 'success');
                } else {
                    throw new Error('Failed to update');
                }
            } catch (error) {
                console.error('Status update error:', error);
                this.innerHTML = originalText;
                this.style.opacity = '1';
                showToast('Failed to update status', 'danger');
            }
        });
    });
})();

// Toast helper function
function showToast(message, type) {
    const toastHtml = `
        <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
            <div class="toast align-items-center border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body text-${type === 'success' ? 'success' : 'danger'}">
                        <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', toastHtml);
    setTimeout(() => {
        document.querySelector('.toast-container')?.remove();
    }, 3000);
}

// ── Notification Badge – Dynamic Counts ──
(function() {
    const badge = document.getElementById('notifBadge');
    const bell = document.getElementById('notificationBell');
    if (!badge || !bell) return;

    async function updateBadge() {
        try {
const res = await fetch('api/notifications.php?action=get-counts');
            const data = await res.json();
            if (data.success && data.counts) {
                const c = data.counts;
                const total = (c.pending_appointments || 0) + (c.unread_messages || 0) + 
                              (c.whatsapp_failures || 0) + (c.pending_reminders || 0);
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        } catch (e) { /* silent */ }
    }

    updateBadge();
    // Refresh every 60 seconds
    setInterval(updateBadge, 60000);

    // Click bell to go to dashboard
    bell.addEventListener('click', () => { window.location.href = 'dashboard.php'; });
})();
</script>
</body>
</html>
