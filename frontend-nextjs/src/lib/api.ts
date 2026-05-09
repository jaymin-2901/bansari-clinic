// Default backend URL - empty string means use current origin (relative paths)
const DEFAULT_BACKEND_URL = '';

// Use NEXT_PUBLIC_BACKEND_URL for direct browser-to-backend calls
export function getBackendUrl(): string {
  if (typeof window !== 'undefined') {
    if (process.env.NODE_ENV !== 'production') {
      // For local development, using empty string makes requests relative to frontend
      // which are then proxied via next.config.js to the backend.
      // This is essential for mobile devices on the same network.
      return '';
    }

    const envBackendUrl = (window as any).env?.NEXT_PUBLIC_BACKEND_URL;
    if (envBackendUrl) return envBackendUrl;

    if (process.env.NEXT_PUBLIC_BACKEND_URL) return process.env.NEXT_PUBLIC_BACKEND_URL;

    return DEFAULT_BACKEND_URL;
  }

  if (process.env.NODE_ENV !== 'production') {
    return '';
  }
  if (process.env.NEXT_PUBLIC_BACKEND_URL) return process.env.NEXT_PUBLIC_BACKEND_URL;
  return DEFAULT_BACKEND_URL;
}

// API_PATH for clinic endpoints
const API_PATH = process.env.NODE_ENV === 'production' ? '/backend-php/api/clinic' : '/api/clinic';

export const API_URL = getBackendUrl() + API_PATH;

// Debug
export function logApiError(context: string, error: unknown) {
  console.error(`[API Error] ${context}:`, error);
  console.error(`[API Error] Backend URL: ${API_URL}`);
}

/**
 * Fetch with Authorization header
 */
export async function fetchWithAuth(url: string, options: RequestInit = {}) {
  const token = typeof window !== 'undefined' ? localStorage.getItem('access_token') : null;
  
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  } as Record<string, string>;

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return fetch(url, {
    ...options,
    headers,
  });
}

/* Settings */
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

/* Testimonials */
export async function fetchTestimonials() {
  try {
    const res = await fetch(`${API_URL}/testimonials.php`, {
      cache: 'no-store',
    });
    if (!res.ok) {
      const errorText = await res.text();
      logApiError('fetchTestimonials', new Error(`HTTP ${res.status}: ${errorText}`));
      return [];
    }
    const json = await res.json();
    return json.data || [];
  } catch (error) {
    logApiError('fetchTestimonials', error);
    return [];
  }
}

/* Contact */
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

/* Appointments */
export async function bookAppointment(data: Record<string, any>) {
  const res = await fetch(`${API_URL}/appointments.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}

/* Slots */
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

/* Patient Auth */
export async function loginPatient(data: { mobile?: string; email?: string; password: string; captcha_token?: string }) {
  const url = `${API_URL}/login.php`; // Updated path
  console.log('[Login] URL:', url);

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'include',
    });

    if (!res.ok) {
      const errorData = await res.json().catch(() => ({ error: 'Unknown error' }));
      throw new Error(errorData.error || `HTTP ${res.status}`);
    }

    return await res.json();
  } catch (error) {
    logApiError('loginPatient', error);
    throw error;
  }
}

export async function forgotPassword(email: string, captchaToken: string) {
  const res = await fetch(`${API_URL}/forgot_password.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, captcha_token: captchaToken }),
  });
  return res.json();
}

export async function resetPassword(data: { token: string; password: string }) {
  const res = await fetch(`${API_URL}/reset_password.php`, {
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

export async function refreshToken(token: string) {
  const res = await fetch(`${API_URL}/refresh.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: token }),
  });
  return res.json();
}

/* My Appointments */
export async function fetchMyAppointments() {
  try {
    const res = await fetchWithAuth(`${API_URL}/my_appointments.php`);
    const json = await res.json();
    return json.data || [];
  } catch {
    return [];
  }
}

/* ── FIXED Image URL helper ── */
export function getImageUrl(path: string | null): string | null {
  if (!path) return null;
  if (path.startsWith('http')) return path;

  // Ensure path starts with /
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  if (process.env.NODE_ENV === 'production') {
    return `${getBackendUrl()}${normalizedPath}`;
  }

  // Dev: Use relative path so it goes through Next.js proxy
  // This is essential for mobile devices to see images
  return normalizedPath;
}

/* Clinic Images (random fallback) */
const CLINIC_IMAGES = [
  'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=800&q=80',
  'https://images.unsplash.com/photo-1581595220892-b0739db338c5?w=800&q=80',
  'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
  'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=800&q=80',
  'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=800&q=80',
  'https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=800&q=80',
  'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=800&q=80',
  'https://images.unsplash.com/photo-1607990281513-2c110a25bd8c?w=800&q=80',
];

export function getRandomClinicImage(): string {
  return CLINIC_IMAGES[Math.floor(Math.random() * CLINIC_IMAGES.length)];
}

export function getRandomClinicImages(count = 6): string[] {
  const shuffled = [...CLINIC_IMAGES].sort(() => Math.random() - 0.5);
  return shuffled.slice(0, Math.min(count, CLINIC_IMAGES.length));
}

export async function fetchClinicImages() {
  return getRandomClinicImages(6);
}

/* Upload Cropped */
export async function uploadCroppedImage(imageData: string, prefix = 'img') {
  const res = await fetchWithAuth(`${API_URL}/crop_image.php`, {
    method: 'POST',
    body: JSON.stringify({
      image_data: imageData,
      prefix,
    }),
  });
  return res.json();
}
/* Clinic Status & Availability */
export async function fetchClinicStatus() {
  try {
    const res = await fetch(`${API_URL}/status.php`, {
      cache: 'no-store',
    });
    if (!res.ok) return { closed: false, message: '' };
    return await res.json();
  } catch {
    return { closed: false, message: '' };
  }
}

export async function checkBookingAvailability(date: string, time?: string) {
  try {
    const res = await fetch(`${API_URL}/check-availability.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date, time }),
    });
    return await res.json();
  } catch {
    return { available: true, message: '' };
  }
}

/* Hero Images */
export async function fetchHeroImages() {
  try {
    const res = await fetch(`${API_URL}/hero.php`, {
      cache: 'no-store',
    });
    if (!res.ok) return [];
    const json = await res.json();
    return json.data || [];
  } catch {
    return [];
  }
}
