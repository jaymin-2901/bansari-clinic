// Default backend URL - must match the actual InfinityFree domain
const DEFAULT_BACKEND_URL = 'https://bansari-homeopathic-clinic.infinityfreeapp.com';

// Use NEXT_PUBLIC_BACKEND_URL for direct browser-to-backend calls
// This bypasses Vercel's server-to-server proxy which fails with InfinityFree's SSL
export function getBackendUrl(): string {
  // Check for environment variable at runtime
  if (typeof window !== 'undefined') {
    // Try to get from window.env first (set by Vercel)
    const envBackendUrl = (window as any).env?.NEXT_PUBLIC_BACKEND_URL;
    if (envBackendUrl) return envBackendUrl;
    
    // Try process.env.NEXT_PUBLIC_BACKEND_URL
    if (process.env.NEXT_PUBLIC_BACKEND_URL) return process.env.NEXT_PUBLIC_BACKEND_URL;
    
    // Fallback to default
    return DEFAULT_BACKEND_URL;
  }
  
  // Server-side (for SSR)
  if (process.env.NEXT_PUBLIC_BACKEND_URL) return process.env.NEXT_PUBLIC_BACKEND_URL;
  return DEFAULT_BACKEND_URL;
}

export const API_URL = getBackendUrl() + '/api/clinic';

// Debug function to log URL issues
export function logApiError(context: string, error: unknown) {
  console.error(`[API Error] ${context}:`, error);
  console.error(`[API Error] Backend URL being used: ${API_URL}`);
  console.error(`[API Error] NEXT_PUBLIC_BACKEND_URL env: ${process.env.NEXT_PUBLIC_BACKEND_URL}`);
}

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
  const url = `${API_URL}/login.php`;
  console.log('[Login] Attempting login to:', url);
  console.log('[Login] Backend URL:', getBackendUrl());
  
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
      // Include credentials for session cookies
      credentials: 'include',
    });
    
    console.log('[Login] Response status:', res.status);
    console.log('[Login] Response ok:', res.ok);
    
    if (!res.ok) {
      const errorText = await res.text();
      console.error('[Login] Error response:', errorText);
      throw new Error(`HTTP ${res.status}: ${errorText}`);
    }
    
    const json = await res.json();
    console.log('[Login] Success:', json.success);
    return json;
  } catch (error) {
    console.error('[Login] Network error:', error);
    logApiError('loginPatient', error);
    throw error;
  }
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

