// Use NEXT_PUBLIC_BACKEND_URL for direct browser-to-backend calls
// This bypasses Vercel's server-to-server proxy which fails with InfinityFree's SSL
export function getBackendUrl(): string {
  if (typeof window !== 'undefined') {
    return (window as any).env?.NEXT_PUBLIC_BACKEND_URL || 
           process.env.NEXT_PUBLIC_BACKEND_URL || 
           'https://bansari-homeopathic-clinic.infinityfreeapp.com';
  }
  return process.env.NEXT_PUBLIC_BACKEND_URL || 'https://bansari-homeopathic-clinic.infinityfreeapp.com';
}

export const API_URL = getBackendUrl() + '/api/clinic';

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
  
  // Use full URL pointing to InfinityFree backend
  return `${getBackendUrl()}${path.startsWith('/') ? '' : '/'}${path}`;
}

/* ── Clinic Images ── */
export async function fetchClinicImages() {
  try {
    const res = await fetch(`${API_URL}/clinic_images.php`, {
      cache: 'no-store',
    });
    if (!res.ok) return [];
    const json = await res.json();
    return json.data || [];
  } catch {
    return [];
  }
}

/* ── Upload Cropped Image ── */
export async function uploadCroppedImage(imageData: string, prefix: string = 'img') {
  const res = await fetch(`${API_URL}/crop_image.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      image_data: imageData,
      prefix: prefix,
    }),
  });
  return res.json();
}

