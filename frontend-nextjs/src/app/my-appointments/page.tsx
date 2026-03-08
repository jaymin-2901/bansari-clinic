'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';

const API_URL = process.env.NEXT_PUBLIC_API_URL || '/api/clinic';

interface Appointment {
  id: number;
  appointment_date: string;
  appointment_time: string | null;
  appointment_type: string;
  status: string;
  created_at: string;
  chief_complaint: string | null;
}

const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
  pending:   { label: 'Pending',   color: 'text-yellow-700', bg: 'bg-yellow-50 border-yellow-200' },
  confirmed: { label: 'Confirmed', color: 'text-blue-700',   bg: 'bg-blue-50 border-blue-200' },
  completed: { label: 'Completed', color: 'text-green-700',  bg: 'bg-green-50 border-green-200' },
  cancelled: { label: 'Cancelled', color: 'text-red-700',    bg: 'bg-red-50 border-red-200' },
};

function formatDate(dateStr: string) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr: string | null) {
  if (!timeStr) return '—';
  const [h, m] = timeStr.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 || 12;
  return `${hour}:${m.toString().padStart(2, '0')} ${ampm}`;
}

export default function MyAppointmentsPage() {
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  useEffect(() => {
    const raw = localStorage.getItem('patient');
    if (!raw) {
      setIsLoggedIn(false);
      setLoading(false);
      return;
    }
    setIsLoggedIn(true);
    const patient = JSON.parse(raw);
    fetchAppointments(patient.id);
  }, []);

  const fetchAppointments = async (patientId: number) => {
    try {
      const res = await fetch(`${API_URL}/my_appointments.php?patient_id=${patientId}`);
      const json = await res.json();
      if (json.success) {
        setAppointments(json.data);
      } else {
        setError(json.error || 'Failed to load appointments.');
      }
    } catch {
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

  /* ── Not logged in ── */
  if (!loading && !isLoggedIn) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg px-4">
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-8 max-w-md w-full text-center">
          <div className="w-16 h-16 rounded-full bg-primary-100 dark:bg-dark-accent/15 flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-primary-600 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">Login Required</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-6">Please login to view your appointments.</p>
          <div className="space-y-3">
            <Link href="/login" className="btn-primary block text-center">Login</Link>
            <Link href="/signup" className="block text-primary-600 dark:text-dark-accent hover:text-primary-700 dark:hover:text-dark-accent-hover font-medium">Create Account</Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      {/* Hero */}
      <section className="bg-gradient-to-br from-primary-50 to-white dark:from-dark-bg dark:to-dark-surface py-12 md:py-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <h1 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-200 mb-2">My Appointments</h1>
          <p className="text-gray-600 dark:text-gray-400">View and track all your appointments with Dr. Bansari Patel.</p>
        </div>
      </section>

      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-5xl mx-auto">
          {loading ? (
            <div className="text-center py-16">
              <div className="inline-block w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
              <p className="text-gray-500 mt-4">Loading appointments...</p>
            </div>
          ) : error ? (
            <div className="text-center py-16">
              <div className="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p className="text-red-600 font-medium">{error}</p>
            </div>
          ) : appointments.length === 0 ? (
            /* Empty state */
            <div className="text-center py-16">
              <div className="w-20 h-20 rounded-full bg-primary-50 dark:bg-dark-card flex items-center justify-center mx-auto mb-4">
                <svg className="w-10 h-10 text-primary-400 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold text-gray-900 dark:text-gray-200 mb-2">No Appointments Yet</h3>
              <p className="text-gray-500 dark:text-gray-400 mb-6">You haven&apos;t booked any appointments. Start your healing journey today!</p>
              <Link href="/book-appointment" className="btn-primary inline-block">
                Book Appointment
              </Link>
            </div>
          ) : (
            /* Appointments list */
            <div className="space-y-4">
              <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">
                Showing {appointments.length} appointment{appointments.length !== 1 ? 's' : ''}
              </p>

              {appointments.map((appt) => {
                const st = statusConfig[appt.status] || statusConfig.pending;
                const isPast = new Date(appt.appointment_date) < new Date(new Date().toDateString());

                return (
                  <div
                    key={appt.id}
                    className={`border dark:border-dark-border rounded-xl p-5 transition-shadow hover:shadow-md dark:bg-dark-card ${isPast ? 'opacity-75' : ''}`}
                  >
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                      {/* Left: date & info */}
                      <div className="flex items-start gap-4">
                        {/* Calendar icon */}
                        <div className="hidden sm:flex flex-col items-center justify-center w-14 h-14 rounded-lg bg-primary-50 dark:bg-dark-accent/15 text-primary-700 dark:text-dark-accent flex-shrink-0">
                          <span className="text-xs font-medium leading-none">
                            {new Date(appt.appointment_date + 'T00:00:00').toLocaleDateString('en-IN', { month: 'short' })}
                          </span>
                          <span className="text-xl font-bold leading-none mt-0.5">
                            {new Date(appt.appointment_date + 'T00:00:00').getDate()}
                          </span>
                        </div>

                        <div>
                          <p className="font-semibold text-gray-900 dark:text-gray-200">
                            {formatDate(appt.appointment_date)}
                            {appt.appointment_time && (
                              <span className="text-gray-500 dark:text-gray-400 font-normal ml-2">at {formatTime(appt.appointment_time)}</span>
                            )}
                          </p>
                          <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            <span className="capitalize">{appt.appointment_type || 'Offline'}</span> Consultation
                            {appt.chief_complaint && (
                              <span className="ml-2 text-gray-400 dark:text-gray-500">• {appt.chief_complaint}</span>
                            )}
                          </p>
                        </div>
                      </div>

                      {/* Right: status badge */}
                      <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${st.bg} ${st.color} self-start sm:self-center`}>
                        {st.label}
                      </span>
                    </div>
                  </div>
                );
              })}

              {/* Book another */}
              <div className="text-center pt-6">
                <Link href="/book-appointment" className="btn-primary inline-block">
                  Book Another Appointment
                </Link>
              </div>
            </div>
          )}
        </div>
      </section>
    </>
  );
}
