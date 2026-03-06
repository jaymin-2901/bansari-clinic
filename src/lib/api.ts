export const BACKEND_URL = process.env.NEXT_PUBLIC_BACKEND_URL || 'http://localhost:8000';
const API_URL = process.env.NEXT_PUBLIC_API_URL || `${BACKEND_URL}/api/clinic`;

/* ── Settings (CMS key-value) ── */
export async function fetchSettings(group: string = 'general') {
  try {
    const res = await fetch(`${API_URL}/settings.php?group=${group}`, {
      cache: 'no-store',
    });
    if (!res.ok) return {};
    const json = await res.json();
    return json.data || {};
  } catch {
    return {};
  }
}

/* ── Testimonials ── */
export async function fetchTestimonials() {
  try {
    const res = await fetch(`${API_URL}/testimonials.php`, {
      cache: 'no-store',
    });
    if (!res.ok) return [];
    const json = await res.json();
    return json.data || [];
  } catch {
    return [];
  }
}

/* ── Contact Form ── */
export async function submitContactForm(data: {
  name: string;
  email?: string;
  phone?: string;
  subject?: string;
  message: string;
}) {
  const res = await fetch(`${API_URL}/contact.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

/* ── Appointments ── */
export async function bookAppointment(data: Record<string, any>) {
  const res = await fetch(`${API_URL}/appointments.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

/* ── Slots ── */
export async function fetchClosedDays() {
  try {
    const res = await fetch(`${API_URL}/slots.php?action=closed_days`);
    const json = await res.json();
    return json.success ? json.closed_days : [0];
  } catch {
    return [0];
  }
}

export async function fetchAvailableSlots(date: string, patientId?: number) {
  try {
    const pidParam = patientId ? `&patient_id=${patientId}` : '';
    const res = await fetch(`${API_URL}/slots.php?action=available_slots&date=${date}${pidParam}`);
    return res.json();
  } catch {
    return { success: false, error: 'Failed to load slots' };
  }
}

/* ── Patient Auth ── */
export async function loginPatient(data: { mobile?: string; email?: string; password: string }) {
  const res = await fetch(`${API_URL}/login.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

export async function signupPatient(data: Record<string, any>) {
  const res = await fetch(`${API_URL}/signup.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

/* ── My Appointments ── */
export async function fetchMyAppointments(patientId: number) {
  try {
    const res = await fetch(`${API_URL}/my_appointments.php?patient_id=${patientId}`);
    const json = await res.json();
    return json.data || [];
  } catch {
    return [];
  }
}

/* ── Image URL helper ── */
export function getImageUrl(path: string | null): string | null {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
}
