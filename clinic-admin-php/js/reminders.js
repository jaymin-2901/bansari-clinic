/**
 * Bansari Homeopathy – Reminder System Admin JS
 * Handles manual reminder sending from the admin panel
 */
(function () {
  'use strict';

  // Configuration — points to Next.js API (injected from header.php)
  const NEXT_API_URL = window.NEXT_API_URL || 'http://localhost:3000';

  let currentAppointmentId = null;
  let sendingReminder = false;

  // ─── Initialize Reminder Buttons ───
  function initReminderButtons() {
    document.querySelectorAll('.btn-send-reminder').forEach((btn) => {
      btn.addEventListener('click', function () {
        const appointmentId = this.dataset.appointmentId;
        const patientName = this.dataset.patientName;

        currentAppointmentId = appointmentId;
        document.getElementById('reminderPatientName').textContent = patientName;
        document.getElementById('reminderResult').style.display = 'none';
        document.getElementById('confirmSendReminder').disabled = false;
        document.getElementById('confirmSendReminder').innerHTML =
          '<i class="bi bi-send me-1"></i>Send Reminder';

        const modal = new bootstrap.Modal(
          document.getElementById('reminderModal')
        );
        modal.show();
      });
    });

    // Confirm send button
    const confirmBtn = document.getElementById('confirmSendReminder');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', sendReminder);
    }
  }

  // ─── Send Reminder via Next.js API ───
  async function sendReminder() {
    if (sendingReminder || !currentAppointmentId) return;

    sendingReminder = true;
    const btn = document.getElementById('confirmSendReminder');
    const resultDiv = document.getElementById('reminderResult');

    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
    resultDiv.style.display = 'none';

    try {
      const response = await fetch(`${NEXT_API_URL}/api/admin/reminders`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-admin-api-key': getAdminApiKey(),
          'x-admin-id': getAdminId(),
        },
        body: JSON.stringify({
          appointmentId: parseInt(currentAppointmentId),
        }),
      });

      const data = await response.json();

      if (data.success) {
        showResult(
          resultDiv,
          'success',
          buildSuccessMessage(data.result)
        );
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Sent!';

        // Refresh page after 2 seconds to show updated status
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        showResult(
          resultDiv,
          'danger',
          data.error || 'Failed to send reminder'
        );
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Retry';
      }
    } catch (error) {
      showResult(
        resultDiv,
        'danger',
        'Network error. Make sure the Next.js server is running on ' + NEXT_API_URL
      );
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-send me-1"></i>Retry';
    } finally {
      sendingReminder = false;
    }
  }

  // ─── Build Success Message ───
  function buildSuccessMessage(result) {
    if (!result) return 'Reminder sent successfully!';

    let msg = '<strong>Reminder sent!</strong><br>';

    if (result.whatsapp) {
      const waIcon = result.whatsapp.sent
        ? '<i class="bi bi-check-circle text-success"></i>'
        : '<i class="bi bi-x-circle text-danger"></i>';
      msg += `${waIcon} WhatsApp: ${result.whatsapp.sent ? 'Delivered' : result.whatsapp.error || 'Not sent'}<br>`;
    }

    if (result.email) {
      const emIcon = result.email.sent
        ? '<i class="bi bi-check-circle text-success"></i>'
        : '<i class="bi bi-x-circle text-danger"></i>';
      msg += `${emIcon} Email: ${result.email.sent ? 'Delivered' : result.email.error || 'No email on file'}<br>`;
    }

    return msg;
  }

  // ─── Show Result in Modal ───
  function showResult(el, type, message) {
    el.className = `alert alert-${type} mt-3 small`;
    el.innerHTML = message;
    el.style.display = 'block';
  }

  // ─── Get Admin API Key from env config ───
  function getAdminApiKey() {
    // This should match the ADMIN_API_KEY in the Next.js .env
    // In production, this would be injected server-side
    return window.ADMIN_API_KEY || 'your-admin-api-key-here';
  }

  // ─── Get Admin ID from session ───
  function getAdminId() {
    // Try to extract from the page or use default
    return window.ADMIN_ID || '1';
  }

  // ─── Initialize on DOM Ready ───
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReminderButtons);
  } else {
    initReminderButtons();
  }
})();
