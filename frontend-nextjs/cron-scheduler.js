/**
 * Local Reminder Cron Scheduler
 * 
 * Runs every 30 minutes and hits the Next.js reminder cron endpoint.
 * This replaces the need for an external cron service during local development.
 * 
 * Usage:  node cron-scheduler.js
 * Stop:   Ctrl+C
 */

const NEXT_API_URL = process.env.NEXT_API_URL || 'http://localhost:3000';
const CRON_SECRET = process.env.CRON_SECRET || 'bansari-cron-secret-2026';
const INTERVAL_MS = (parseInt(process.env.CRON_INTERVAL_MINUTES || '30')) * 60 * 1000;

function timestamp() {
  return new Date().toLocaleString('en-IN', { 
    timeZone: 'Asia/Kolkata',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    hour12: true 
  });
}

async function triggerReminders() {
  const url = `${NEXT_API_URL}/api/cron/reminders?secret=${CRON_SECRET}`;
  
  console.log(`\n[${timestamp()}] 🔔 Triggering reminder cron...`);
  console.log(`  URL: ${url}`);

  try {
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'x-cron-secret': CRON_SECRET,
      },
    });

    const data = await response.json();

    if (response.ok && data.success) {
      console.log(`  ✅ Success!`);
      console.log(`     Processed: ${data.processed || 0} appointments`);
      console.log(`     Sent: ${data.sent || 0}`);
      console.log(`     Failed: ${data.failed || 0}`);
      console.log(`     Duration: ${data.duration || 'N/A'}`);
      
      if (data.results && data.results.length > 0) {
        data.results.forEach(r => {
          const wa = r.whatsapp?.sent ? '✅ WhatsApp' : '❌ WhatsApp';
          const em = r.email?.sent ? '✅ Email' : '❌ Email';
          console.log(`     Appointment #${r.appointmentId}: ${wa} | ${em}`);
        });
      }
    } else {
      console.log(`  ❌ Error: ${data.error || `HTTP ${response.status}`}`);
    }
  } catch (error) {
    console.log(`  ❌ Connection failed: ${error.message}`);
    console.log(`     Is the Next.js server running on ${NEXT_API_URL}?`);
  }
}

// ─── Main ───

console.log('═══════════════════════════════════════════════════════');
console.log('  Bansari Homeopathy – Reminder Cron Scheduler');
console.log('═══════════════════════════════════════════════════════');
console.log(`  Next.js API:  ${NEXT_API_URL}`);
console.log(`  Interval:     ${INTERVAL_MS / 60000} minutes`);
console.log(`  Cron Secret:  ${CRON_SECRET.slice(0, 8)}...`);
console.log(`  Started at:   ${timestamp()}`);
console.log('═══════════════════════════════════════════════════════');
console.log('\nPress Ctrl+C to stop.\n');

// Run immediately on start
triggerReminders();

// Then repeat every INTERVAL_MS
const intervalId = setInterval(triggerReminders, INTERVAL_MS);

// Graceful shutdown
process.on('SIGINT', () => {
  console.log(`\n[${timestamp()}] Scheduler stopped.`);
  clearInterval(intervalId);
  process.exit(0);
});
