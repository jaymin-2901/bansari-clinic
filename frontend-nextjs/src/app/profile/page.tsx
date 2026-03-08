'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import EditProfileModal from '@/components/EditProfileModal';

/* ── Types ── */
interface PatientProfile {
  id: number;
  full_name: string;
  mobile: string;
  age: number | null;
  gender: string | null;
  city: string | null;
  address: string | null;
  email: string | null;
  is_registered: boolean;
  created_at: string;
}

interface AppointmentSummary {
  total_appointments: number;
  upcoming_count: number;
  completed_count: number;
  followup_count: number;
  next_appointment: {
    id: number;
    appointment_date: string;
    appointment_time: string | null;
    status: string;
    confirmation_status: string;
    consultation_type: string;
    is_followup: boolean;
  } | null;
}

interface AppointmentItem {
  id: number;
  appointment_date: string;
  appointment_time: string | null;
  consultation_type: string;
  status: string;
  confirmation_status: string;
  is_followup: boolean;
  followup_done: boolean;
  created_at: string;
}

/* ── Helper Functions ── */
function formatDate(dateStr: string) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(timeStr: string | null) {
  if (!timeStr) return '—';
  const parts = timeStr.split(':');
  if (parts.length < 2) return timeStr;
  const h = parseInt(parts[0], 10);
  const m = parseInt(parts[1], 10);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 || 12;
  return `${hour}:${m.toString().padStart(2, '0')} ${ampm}`;
}

const statusConfig: Record<string, { label: string; labelGu: string; color: string; bg: string; darkBg: string }> = {
  pending:   { label: 'Scheduled',  labelGu: 'શેડ્યૂલ',   color: 'text-yellow-700 dark:text-yellow-400', bg: 'bg-yellow-50 border-yellow-200', darkBg: 'dark:bg-yellow-900/20 dark:border-yellow-800' },
  confirmed: { label: 'Confirmed',  labelGu: 'કન્ફર્મ',    color: 'text-blue-700 dark:text-blue-400',     bg: 'bg-blue-50 border-blue-200',     darkBg: 'dark:bg-blue-900/20 dark:border-blue-800' },
  completed: { label: 'Completed',  labelGu: 'પૂર્ણ',      color: 'text-green-700 dark:text-green-400',   bg: 'bg-green-50 border-green-200',   darkBg: 'dark:bg-green-900/20 dark:border-green-800' },
  cancelled: { label: 'Cancelled',  labelGu: 'રદ',        color: 'text-red-700 dark:text-red-400',       bg: 'bg-red-50 border-red-200',       darkBg: 'dark:bg-red-900/20 dark:border-red-800' },
};

/* ── Gender Display ── */
function genderLabel(gender: string | null, t: (en: string, gu: string) => string) {
  if (!gender) return '—';
  const map: Record<string, [string, string]> = {
    male: ['Male', 'પુરુષ'],
    female: ['Female', 'સ્ત્રી'],
    other: ['Other', 'અન્ય'],
  };
  const pair = map[gender.toLowerCase()];
  return pair ? t(pair[0], pair[1]) : gender;
}

/* ════════════════════════════════════════════════
   Profile Page Component
   ════════════════════════════════════════════════ */
export default function ProfilePage() {
  const { t } = useLanguage();

  const [patient, setPatient] = useState<PatientProfile | null>(null);
  const [summary, setSummary] = useState<AppointmentSummary | null>(null);
  const [appointments, setAppointments] = useState<AppointmentItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [editOpen, setEditOpen] = useState(false);

  useEffect(() => {
    const raw = localStorage.getItem('patient');
    if (!raw) {
      setIsLoggedIn(false);
      setLoading(false);
      return;
    }
    setIsLoggedIn(true);
    const stored = JSON.parse(raw);
    fetchProfile(stored.id);
  }, []);

  const fetchProfile = async (patientId: number) => {
    try {
      const res = await fetch(`/api/patient/profile?patient_id=${patientId}`);
      const data = await res.json();
      if (data.success) {
        setPatient(data.patient);
        setSummary(data.summary);
        setAppointments(data.appointments || []);
      } else {
        setError(data.error || 'Failed to load profile');
      }
    } catch {
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

  const handleProfileSaved = (updatedPatient: PatientProfile) => {
    setPatient(updatedPatient);
    // Re-fetch to get fresh summary data
    if (updatedPatient.id) {
      fetchProfile(updatedPatient.id);
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
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">
            {t('Login Required', 'લોગિન જરૂરી છે')}
          </h2>
          <p className="text-gray-600 dark:text-gray-400 mb-6">
            {t('Please login to view your profile.', 'કૃપા કરીને તમારી પ્રોફાઈલ જોવા માટે લોગિન કરો.')}
          </p>
          <div className="space-y-3">
            <Link href="/login" className="btn-primary block text-center">
              {t('Login', 'લોગિન')}
            </Link>
            <Link href="/signup" className="block text-primary-600 dark:text-dark-accent hover:text-primary-700 dark:hover:text-dark-accent-hover font-medium">
              {t('Create Account', 'એકાઉન્ટ બનાવો')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  /* ── Loading ── */
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg">
        <div className="flex flex-col items-center gap-3">
          <svg className="w-10 h-10 text-primary-500 dark:text-dark-accent animate-spin" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <p className="text-gray-500 dark:text-gray-400">{t('Loading profile...', 'પ્રોફાઈલ લોડ થઈ રહી છે...')}</p>
        </div>
      </div>
    );
  }

  /* ── Error ── */
  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg px-4">
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-8 max-w-md w-full text-center">
          <div className="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
            </svg>
          </div>
          <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-2">{t('Error', 'ભૂલ')}</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-4">{error}</p>
          <button onClick={() => window.location.reload()} className="btn-primary">
            {t('Retry', 'ફરી પ્રયાસ કરો')}
          </button>
        </div>
      </div>
    );
  }

  if (!patient) return null;

  const getInitials = (name: string) =>
    name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2);

  const nextApt = summary?.next_appointment;
  const nextAptStatus = nextApt ? statusConfig[nextApt.status] || statusConfig.pending : null;

  return (
    <>
      {/* ── Hero Section ── */}
      <section className="bg-gradient-to-br from-primary-50 to-white dark:from-dark-bg dark:to-dark-surface py-10 md:py-14">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            {/* Avatar */}
            <div className="w-16 h-16 bg-gradient-to-br from-primary-400 to-primary-600 dark:from-dark-accent dark:to-teal-600 rounded-2xl flex items-center justify-center shadow-lg">
              <span className="text-white font-bold text-2xl">{getInitials(patient.full_name)}</span>
            </div>
            <div className="flex-1">
              <h1 className="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-200">
                {patient.full_name}
              </h1>
              <p className="text-gray-500 dark:text-gray-400 mt-0.5">
                {patient.email || patient.mobile}
              </p>
            </div>
            <button
              onClick={() => setEditOpen(true)}
              className="btn-primary !rounded-xl flex items-center gap-2 text-sm"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              {t('Edit Profile', 'પ્રોફાઈલ સંપાદિત કરો')}
            </button>
          </div>
        </div>
      </section>

      {/* ── Main Content ── */}
      <section className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {/* Grid: 2 cols desktop, 1 col mobile */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

          {/* ── Card 1: Basic Information ── */}
          <div className="bg-white dark:bg-dark-card rounded-2xl shadow-soft dark:shadow-none dark:border dark:border-dark-border p-6">
            <h2 className="text-lg font-bold text-gray-900 dark:text-gray-200 mb-5 flex items-center gap-2">
              <svg className="w-5 h-5 text-primary-500 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {t('Basic Information', 'મૂળભૂત માહિતી')}
            </h2>

            <div className="space-y-4">
              <InfoRow label={t('Full Name', 'પૂરું નામ')} value={patient.full_name} />
              <InfoRow label={t('Email', 'ઈમેઇલ')} value={patient.email || '—'} />
              <InfoRow label={t('Mobile Number', 'મોબાઈલ નંબર')} value={patient.mobile} />
              <InfoRow label={t('Age', 'ઉંમર')} value={patient.age !== null ? `${patient.age} ${t('years', 'વર્ષ')}` : '—'} />
              <InfoRow label={t('Gender', 'જાતિ')} value={genderLabel(patient.gender, t)} />
              <InfoRow label={t('City', 'શહેર')} value={patient.city || '—'} />
              <InfoRow label={t('Address', 'સરનામું')} value={patient.address || '—'} />
            </div>
          </div>

          {/* ── Card 2: Appointment Summary ── */}
          <div className="bg-white dark:bg-dark-card rounded-2xl shadow-soft dark:shadow-none dark:border dark:border-dark-border p-6">
            <h2 className="text-lg font-bold text-gray-900 dark:text-gray-200 mb-5 flex items-center gap-2">
              <svg className="w-5 h-5 text-primary-500 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {t('Appointment Summary', 'એપોઈન્ટમેન્ટ સારાંશ')}
            </h2>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 gap-3 mb-6">
              <StatCard
                label={t('Total', 'કુલ')}
                value={summary?.total_appointments || 0}
                color="bg-gray-50 dark:bg-dark-surface text-gray-700 dark:text-gray-300"
              />
              <StatCard
                label={t('Upcoming', 'આગામી')}
                value={summary?.upcoming_count || 0}
                color="bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400"
              />
              <StatCard
                label={t('Completed', 'પૂર્ણ')}
                value={summary?.completed_count || 0}
                color="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400"
              />
              <StatCard
                label={t('Follow-Ups', 'ફોલો-અપ')}
                value={summary?.followup_count || 0}
                color="bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400"
              />
            </div>

            {/* Next Appointment */}
            {nextApt ? (
              <div className={`rounded-xl border p-4 ${nextAptStatus?.bg} ${nextAptStatus?.darkBg}`}>
                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                  {t('Upcoming Appointment', 'આગામી એપોઈન્ટમેન્ટ')}
                </p>
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-bold text-gray-900 dark:text-gray-200">
                      {formatDate(nextApt.appointment_date.slice(0, 10))}
                    </p>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                      {formatTime(nextApt.appointment_time)}
                    </p>
                  </div>
                  <span className={`text-xs font-bold px-3 py-1 rounded-full border ${nextAptStatus?.bg} ${nextAptStatus?.darkBg} ${nextAptStatus?.color}`}>
                    {t(nextAptStatus?.label || '', nextAptStatus?.labelGu || '')}
                  </span>
                </div>
                {nextApt.is_followup && (
                  <p className="text-xs text-purple-600 dark:text-purple-400 mt-2 font-medium">
                    {t('Follow-Up Appointment', 'ફોલો-અપ એપોઈન્ટમેન્ટ')}
                  </p>
                )}
              </div>
            ) : (
              <div className="rounded-xl border border-gray-100 dark:border-dark-border bg-gray-50 dark:bg-dark-surface p-4 text-center">
                <p className="text-gray-500 dark:text-gray-400 text-sm">
                  {t('No upcoming appointments', 'કોઈ આગામી એપોઈન્ટમેન્ટ નથી')}
                </p>
                <Link href="/book-appointment" className="text-primary-600 dark:text-dark-accent text-sm font-medium hover:underline mt-1 inline-block">
                  {t('Book Now', 'હમણાં બુક કરો')}
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* ── Appointment History ── */}
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-soft dark:shadow-none dark:border dark:border-dark-border p-6">
          <div className="flex items-center justify-between mb-5">
            <h2 className="text-lg font-bold text-gray-900 dark:text-gray-200 flex items-center gap-2">
              <svg className="w-5 h-5 text-primary-500 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              {t('Appointment History', 'એપોઈન્ટમેન્ટ ઇતિહાસ')}
            </h2>
            <Link
              href="/my-appointments"
              className="text-sm text-primary-600 dark:text-dark-accent font-medium hover:underline flex items-center gap-1"
            >
              {t('View All', 'બધા જુઓ')}
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>

          {appointments.length === 0 ? (
            <div className="text-center py-8">
              <svg className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <p className="text-gray-500 dark:text-gray-400">{t('No appointments yet', 'હજી સુધી કોઈ એપોઈન્ટમેન્ટ નથી')}</p>
              <Link href="/book-appointment" className="btn-primary inline-block mt-4 text-sm">
                {t('Book Your First Appointment', 'તમારી પ્રથમ એપોઈન્ટમેન્ટ બુક કરો')}
              </Link>
            </div>
          ) : (
            <div className="space-y-3 max-h-[400px] overflow-y-auto pr-1">
              {appointments.slice(0, 10).map((apt) => {
                const sc = statusConfig[apt.status] || statusConfig.pending;
                return (
                  <div
                    key={apt.id}
                    className="flex items-center justify-between p-4 rounded-xl border border-gray-100 dark:border-dark-border hover:bg-gray-50 dark:hover:bg-dark-surface transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 rounded-xl bg-primary-50 dark:bg-dark-accent/15 flex items-center justify-center flex-shrink-0">
                        <svg className="w-5 h-5 text-primary-500 dark:text-dark-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <div>
                        <p className="font-semibold text-gray-900 dark:text-gray-200 text-sm">
                          {formatDate(apt.appointment_date.slice(0, 10))}
                          <span className="text-gray-400 dark:text-gray-500 font-normal ml-2">
                            {formatTime(apt.appointment_time)}
                          </span>
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                          {apt.consultation_type === 'online' ? t('Online', 'ઓનલાઈન') : t('Clinic Visit', 'ક્લિનિક મુલાકાત')}
                          {apt.is_followup && (
                            <span className="ml-2 text-purple-500 dark:text-purple-400">
                              • {t('Follow-Up', 'ફોલો-અપ')}
                            </span>
                          )}
                        </p>
                      </div>
                    </div>
                    <span className={`text-xs font-bold px-3 py-1 rounded-full border ${sc.bg} ${sc.darkBg} ${sc.color}`}>
                      {t(sc.label, sc.labelGu)}
                    </span>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* ── Action Buttons ── */}
        <div className="flex flex-col sm:flex-row gap-3">
          <button
            onClick={() => setEditOpen(true)}
            className="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-primary-200 dark:border-dark-accent/30 text-primary-700 dark:text-dark-accent bg-primary-50/50 dark:bg-dark-accent/10 hover:bg-primary-100 dark:hover:bg-dark-accent/20 font-medium transition-colors"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            {t('Edit Profile', 'પ્રોફાઈલ સંપાદિત કરો')}
          </button>
          <Link
            href="/my-appointments"
            className="flex-1 flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-gray-200 dark:border-dark-border text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-surface font-medium transition-colors"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            {t('View Appointment History', 'એપોઈન્ટમેન્ટ ઇતિહાસ જુઓ')}
          </Link>
        </div>
      </section>

      {/* ── Edit Profile Modal ── */}
      <EditProfileModal
        patient={patient as any}
        isOpen={editOpen}
        onClose={() => setEditOpen(false)}
        onSaved={handleProfileSaved as any}
      />
    </>
  );
}

/* ── Sub-Components ── */

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-start gap-3 py-2 border-b border-gray-50 dark:border-dark-border/50 last:border-0">
      <span className="text-sm text-gray-500 dark:text-gray-400 w-32 flex-shrink-0 font-medium">{label}</span>
      <span className="text-sm text-gray-900 dark:text-gray-200 font-medium">{value}</span>
    </div>
  );
}

function StatCard({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <div className={`rounded-xl p-3 text-center ${color}`}>
      <p className="text-2xl font-bold">{value}</p>
      <p className="text-xs font-medium mt-0.5 opacity-80">{label}</p>
    </div>
  );
}
