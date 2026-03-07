/**
 * Bansari Homeopathy – Follow-Up Reminder JS
 * Handles sending WhatsApp or Email reminders individually from the Follow-Up admin page
 */
(function () {
  'use strict';

  const NEXT_API_URL = window.NEXT_API_URL || 'http://localhost:3000';

  let currentAppointment = null;
  let currentChannel = null; // 'whatsapp' | 'email'
  let sending = false;

  // ─── Initialize ───
  function init() {
    // Bind WhatsApp reminder buttons
    document.querySelectorAll('.btn-followup-whatsapp').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openModal(this, 'whatsapp');
      });
    });

    // Bind Email reminder buttons
    document.querySelectorAll('.btn-followup-email').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openModal(this, 'email');
      });
    });

    // Confirm button
    var confirmBtn = document.getElementById('confirmFollowupReminder');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', sendFollowupReminder);
    }
  }

  // ─── Open Modal ───
  function openModal(btnEl, channel) {
    var data = btnEl.dataset;
    currentChannel = channel;
    currentAppointment = {
      id: data.appointmentId,
      name: data.patientName,
      mobile: data.patientMobile,
      email: data.patientEmail,
      date: data.appointmentDate,
      time: data.appointmentTime,
    };

    // Populate common fields
    document.getElementById('fupPatientName').textContent = data.patientName;
    document.getElementById('fupDate').textContent = data.appointmentDate;
    document.getElementById('fupTime').textContent = data.appointmentTime;
    document.getElementById('fupMobile').textContent = data.patientMobile || 'Not provided';
    document.getElementById('fupEmail').textContent = data.patientEmail || 'Not provided';

    // Channel-specific UI
    var modalTitle = document.getElementById('fupModalTitle');
    var channelLabel = document.getElementById('fupChannelLabel');
    var waPreview = document.getElementById('fupWhatsappPreview');
    var emPreview = document.getElementById('fupEmailPreview');
    var mobileRow = document.getElementById('fupMobileRow');
    var emailRow = document.getElementById('fupEmailRow');
    var confirmBtnEl = document.getElementById('confirmFollowupReminder');

    if (channel === 'whatsapp') {
      modalTitle.innerHTML = '<i class="bi bi-whatsapp text-success me-2"></i>Send WhatsApp Reminder';
      channelLabel.textContent = 'WhatsApp';
      waPreview.style.display = 'block';
      emPreview.style.display = 'none';
      mobileRow.style.display = '';
      emailRow.style.display = 'none';
      confirmBtnEl.className = 'btn btn-sm btn-followup-whatsapp';
      confirmBtnEl.innerHTML = '<i class="bi bi-whatsapp me-1"></i>Send WhatsApp';

      // Build WhatsApp message preview
      var preview =
        'Hello ' + data.patientName +
        ',\nThis is a reminder for your follow-up appointment at Bansari Homeopathy Clinic scheduled on ' +
        data.appointmentDate + ' at ' + data.appointmentTime +
        '.\nPlease reply YES to confirm or NO to cancel.';
      document.getElementById('fupMessagePreview').textContent = preview;
    } else {
      modalTitle.innerHTML = '<i class="bi bi-envelope-fill text-danger me-2"></i>Send Email Reminder';
      channelLabel.textContent = 'Email';
      waPreview.style.display = 'none';
      emPreview.style.display = 'block';
      mobileRow.style.display = 'none';
      emailRow.style.display = '';
      confirmBtnEl.className = 'btn btn-sm btn-followup-email';
      confirmBtnEl.innerHTML = '<i class="bi bi-envelope me-1"></i>Send Email';
    }

    // Reset result
    document.getElementById('fupReminderResult').style.display = 'none';

    var modal = new bootstrap.Modal(document.getElementById('followupReminderModal'));
    modal.show();
  }

  // ─── Send Reminder ───
  async function sendFollowupReminder() {
    if (sending || !currentAppointment || !currentChannel) return;
    sending = true;

    var btn = document.getElementById('confirmFollowupReminder');
    var resultDiv = document.getElementById('fupReminderResult');
    var channelIcon = currentChannel === 'whatsapp' ? 'bi-whatsapp' : 'bi-envelope';
    var channelName = currentChannel === 'whatsapp' ? 'WhatsApp' : 'Email';

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending ' + channelName + '...';
    resultDiv.style.display = 'none';

    // ─── WhatsApp: Open wa.me link directly (no API needed) ───
    if (currentChannel === 'whatsapp') {
      var mobile = (currentAppointment.mobile || '').replace(/[^0-9]/g, '');
      if (!mobile) {
        showResult(resultDiv, 'danger', '<i class="bi bi-x-circle me-1"></i>No mobile number available for this patient.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i>Send WhatsApp';
        sending = false;
        return;
      }
      // Add India country code if not present
      if (mobile.length === 10) mobile = '91' + mobile;

      var waMessage = 'Hello ' + currentAppointment.name +
        ',\nThis is a reminder for your follow-up appointment at Bansari Homeopathy Clinic scheduled on ' +
        currentAppointment.date + ' at ' + currentAppointment.time +
        '.\nPlease reply YES to confirm or NO to cancel.\nThank you!';

      var waUrl = 'https://wa.me/' + mobile + '?text=' + encodeURIComponent(waMessage);
      window.open(waUrl, '_blank');

      showResult(resultDiv, 'success', '<i class="bi bi-check-circle-fill me-1"></i><strong>WhatsApp opened!</strong> Send the message from the WhatsApp tab.');
      btn.innerHTML = '<i class="bi bi-whatsapp me-1"></i>Open Again';
      btn.disabled = false;
      showToast('WhatsApp opened for ' + currentAppointment.name, 'success');
      sending = false;
      return;
    }

    // ─── Email: Send via API ───
    try {
      var response = await fetch(NEXT_API_URL + '/api/followup/reminder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-admin-api-key': window.ADMIN_API_KEY || '',
          'x-admin-id': window.ADMIN_ID || '1',
        },
        body: JSON.stringify({
          appointmentId: parseInt(currentAppointment.id),
          channel: currentChannel,
        }),
      });

      var data = await response.json();

      if (response.status === 429) {
        showResult(resultDiv, 'warning', '<i class="bi bi-exclamation-triangle me-1"></i>' + (data.error || 'Rate limit reached. Please wait.'));
        btn.disabled = false;
        btn.innerHTML = '<i class="bi ' + channelIcon + ' me-1"></i>Retry';
        sending = false;
        return;
      }

      if (data.success && data.result) {
        var r = data.result;
        var channelResult = r.email;

        if (channelResult && channelResult.sent) {
          var msg = '<strong>' + channelName + ' reminder sent!</strong>';
          showResult(resultDiv, 'success', '<i class="bi bi-check-circle-fill me-1"></i>' + msg);
          btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Sent!';
          showToast(channelName + ' reminder sent to ' + currentAppointment.name, 'success');

          // Reload page after 2s to show updated status
          setTimeout(function () {
            window.location.reload();
          }, 2000);
        } else {
          var errMsg = channelResult ? channelResult.error || 'Not sent' : 'Channel not available';
          showResult(resultDiv, 'danger', '<i class="bi bi-x-circle me-1"></i><strong>' + channelName + ' failed:</strong> ' + errMsg);
          btn.disabled = false;
          btn.innerHTML = '<i class="bi ' + channelIcon + ' me-1"></i>Retry';
          showToast(channelName + ' failed — ' + errMsg, 'danger');
        }
      } else {
        showResult(resultDiv, 'danger', '<i class="bi bi-x-circle me-1"></i>' + (data.error || 'Failed to send reminder'));
        btn.disabled = false;
        btn.innerHTML = '<i class="bi ' + channelIcon + ' me-1"></i>Retry';
        showToast(data.error || 'Failed to send reminder', 'danger');
      }
    } catch (error) {
      showResult(
        resultDiv,
        'danger',
        '<i class="bi bi-wifi-off me-1"></i>Network error. Is the Next.js server running at ' + NEXT_API_URL + '?'
      );
      btn.disabled = false;
      btn.innerHTML = '<i class="bi ' + channelIcon + ' me-1"></i>Retry';
      showToast('Network error — check server', 'danger');
    } finally {
      sending = false;
    }
  }

  // ─── UI Helpers ───
  function showResult(el, type, message) {
    el.className = 'alert alert-' + type + ' mt-3 small mb-0';
    el.innerHTML = message;
    el.style.display = 'block';
  }

  function showToast(message, type) {
    var toast = document.getElementById('followupToast');
    var body = document.getElementById('followupToastBody');
    if (!toast || !body) return;

    toast.className = 'toast align-items-center border-0 text-bg-' + type;
    body.textContent = message;

    var bsToast = bootstrap.Toast.getOrCreateInstance(toast, { delay: 4000 });
    bsToast.show();
  }

  // ─── Init ───
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
