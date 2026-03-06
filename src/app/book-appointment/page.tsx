'use client';

import { useState, useEffect, FormEvent } from 'react';
import { bookAppointment } from '@/lib/api';

type Step = 1 | 2 | 3 | 4;
type ConsultationType = 'offline' | 'online' | '';
type Severity = 'mild' | 'moderate' | 'severe';

interface MainComplaint {
  text: string;
  duration: string;
  severity: Severity;
}

interface PastDisease {
  name: string;
  details: string;
  year: string;
  treatment: string;
  is_current: boolean;
}

interface FamilyHistoryItem {
  relation: string;
  disease: string;
  details: string;
}

interface SlotInfo {
  time: string;
  display: string;
  available: boolean;
  booked: boolean;
  past: boolean;
  session: string;
}

const BACKEND_URL = process.env.NEXT_PUBLIC_BACKEND_URL || 'http://localhost:8000';
const API_URL = process.env.NEXT_PUBLIC_API_URL || `${BACKEND_URL}/api/clinic`;

export default function BookAppointmentPage() {
  const [step, setStep] = useState<Step>(1);
  const [consultationType, setConsultationType] = useState<ConsultationType>('');
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [errorMessage, setErrorMessage] = useState('');
  const [appointmentId, setAppointmentId] = useState<number | null>(null);
  const [loggedInPatientId, setLoggedInPatientId] = useState<number | null>(null);

  // Slot system state
  const [detectedPatientType, setDetectedPatientType] = useState<'new' | 'old'>('new');
  const [closedDays, setClosedDays] = useState<number[]>([]);
  const [slots, setSlots] = useState<SlotInfo[]>([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [slotMessage, setSlotMessage] = useState('');
  const [clinicOpen, setClinicOpen] = useState(true);

  // Step 1: Basic Info
  const [basic, setBasic] = useState({
    full_name: '',
    mobile: '',
    age: '',
    gender: '',
    city: '',
    appointment_date: '',
    appointment_time: '',
  });

  // Pre-fill from logged-in patient + fetch closed days
  useEffect(() => {
    try {
      const raw = localStorage.getItem('patient');
      if (raw) {
        const p = JSON.parse(raw);
        setLoggedInPatientId(p.id || null);
        setBasic((prev) => ({
          ...prev,
          full_name: p.name || prev.full_name,
          mobile: p.mobile || prev.mobile,
          age: p.age ? String(p.age) : prev.age,
          gender: p.gender || prev.gender,
          city: p.city || prev.city,
        }));
      }
    } catch {}

    // Fetch closed days for date picker
    fetch(`${API_URL}/slots.php?action=closed_days`)
      .then(r => r.json())
      .then(d => { if (d.success) setClosedDays(d.closed_days); })
      .catch(() => {});
  }, []);

  // Fetch available slots when date changes (auto-detects patient type via patient_id)
  useEffect(() => {
    if (!basic.appointment_date) {
      setSlots([]);
      setSlotMessage('');
      return;
    }

    // Check if selected date is a closed day
    const dow = new Date(basic.appointment_date + 'T00:00:00').getDay();
    if (closedDays.includes(dow)) {
      setSlots([]);
      setClinicOpen(false);
      setSlotMessage('Clinic is closed on Sunday. Please choose another date. / રવિવારે ક્લિનિક બંધ છે.');
      setBasic(prev => ({ ...prev, appointment_time: '' }));
      return;
    }

    setSlotsLoading(true);
    setSlotMessage('');
    setClinicOpen(true);
    setBasic(prev => ({ ...prev, appointment_time: '' }));

    const pidParam = loggedInPatientId ? `&patient_id=${loggedInPatientId}` : '';
    fetch(`${API_URL}/slots.php?action=available_slots&date=${basic.appointment_date}${pidParam}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          setSlots(data.slots || []);
          setClinicOpen(data.is_open);
          setDetectedPatientType(data.patient_type || 'new');
          if (!data.is_open) {
            setSlotMessage(data.message || 'Clinic is closed on this day.');
          } else if (data.available === 0) {
            setSlotMessage('All slots are booked for this date. Please choose another date. / આ તારીખ માટે બધા સ્લોટ બુક થઈ ગયા છે.');
          }
        } else {
          setSlotMessage(data.error || 'Failed to load slots.');
        }
      })
      .catch(() => setSlotMessage('Failed to load slots. Please try again.'))
      .finally(() => setSlotsLoading(false));
  }, [basic.appointment_date, loggedInPatientId, closedDays]);

  // Short Form (Offline)
  const [shortForm, setShortForm] = useState({
    chief_complaint: '',
    complaint_duration: '',
    major_diseases: [] as string[],
    current_medicines: '',
    allergy: '',
    declaration_accepted: false,
  });

  // Full Form (Online)
  const [mainComplaints, setMainComplaints] = useState<MainComplaint[]>([
    { text: '', duration: '', severity: 'moderate' },
  ]);
  const [pastDiseases, setPastDiseases] = useState<PastDisease[]>([]);
  const [familyHistory, setFamilyHistory] = useState<FamilyHistoryItem[]>([]);
  const [physicalGenerals, setPhysicalGenerals] = useState({
    appetite: 'good',
    thirst: 'normal',
    stool: 'regular',
    urine: 'normal',
    sweat: 'normal',
    sleep_quality: 'sound',
    sleep_position: '',
    thermal: 'ambithermal',
    cravings: '',
    aversions: '',
  });
  const [mentalProfile, setMentalProfile] = useState({
    temperament: '',
    fears: '',
    dreams: '',
    stress_factors: '',
    emotional_state: '',
    hobbies: '',
    social_behavior: '',
    additional_notes: '',
  });
  const [fullFormDeclaration, setFullFormDeclaration] = useState(false);

  const majorDiseaseOptions = [
    'Diabetes', 'High Blood Pressure', 'Thyroid', 'Asthma', 'Tuberculosis', 'Past Surgery',
  ];

  const toggleMajorDisease = (disease: string) => {
    setShortForm((prev) => ({
      ...prev,
      major_diseases: prev.major_diseases.includes(disease)
        ? prev.major_diseases.filter((d) => d !== disease)
        : [...prev.major_diseases, disease],
    }));
  };

  const addMainComplaint = () => {
    setMainComplaints([...mainComplaints, { text: '', duration: '', severity: 'moderate' }]);
  };

  const removeMainComplaint = (index: number) => {
    if (mainComplaints.length > 1) {
      setMainComplaints(mainComplaints.filter((_, i) => i !== index));
    }
  };

  const updateMainComplaint = (index: number, field: keyof MainComplaint, value: string) => {
    const updated = [...mainComplaints];
    updated[index] = { ...updated[index], [field]: value };
    setMainComplaints(updated);
  };

  const addPastDisease = () => {
    setPastDiseases([...pastDiseases, { name: '', details: '', year: '', treatment: '', is_current: false }]);
  };

  const addFamilyHistory = () => {
    setFamilyHistory([...familyHistory, { relation: '', disease: '', details: '' }]);
  };

  const validateStep1 = () => {
    return basic.full_name && basic.mobile && basic.age && basic.gender && basic.city && basic.appointment_date && basic.appointment_time && clinicOpen;
  };

  const validateStep2 = () => {
    return consultationType !== '';
  };

  const nextStep = () => {
    if (step === 1 && !validateStep1()) return;
    if (step === 2 && !validateStep2()) return;
    setStep((s) => Math.min(s + 1, 4) as Step);
  };

  const prevStep = () => {
    setStep((s) => Math.max(s - 1, 1) as Step);
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setStatus('submitting');

    setErrorMessage('');
    const payload: Record<string, any> = {
      ...basic,
      age: parseInt(basic.age),
      consultation_type: consultationType,
      ...(loggedInPatientId ? { patient_id: loggedInPatientId } : {}),
    };

    if (consultationType === 'offline') {
      payload.chief_complaint = shortForm.chief_complaint;
      payload.complaint_duration = shortForm.complaint_duration;
      payload.major_diseases = shortForm.major_diseases;
      payload.current_medicines = shortForm.current_medicines;
      payload.allergy = shortForm.allergy;
      payload.declaration_accepted = shortForm.declaration_accepted ? 1 : 0;
    } else {
      payload.main_complaints = mainComplaints.filter((c) => c.text.trim() !== '');
      payload.past_diseases = pastDiseases.filter((d) => d.name.trim() !== '');
      payload.family_history = familyHistory.filter((f) => f.relation.trim() !== '');
      payload.physical_generals = physicalGenerals;
      payload.mental_profile = mentalProfile;
    }

    try {
      const res = await bookAppointment(payload);
      if (res.success) {
        setStatus('success');
        setAppointmentId(res.appointment_id);
        setStep(4);
      } else {
        setErrorMessage(res.error || 'Booking failed. Please try again.');
        setStatus('error');
      }
    } catch {
      setStatus('error');
    }
  };

  const getMinDate = () => {
    const today = new Date();
    return today.toISOString().split('T')[0];
  };

  // Step indicator
  const steps = [
    { num: 1, label: 'Basic Info / મૂળભૂત માહિતી' },
    { num: 2, label: 'Consultation / પરામર્શ' },
    { num: 3, label: 'Medical / તબીબી' },
    { num: 4, label: 'Confirmation / પુષ્ટિ' },
  ];

  return (
    <>
      {/* ═══ Hero ═══ */}
      <section className="bg-gradient-to-br from-primary-50 to-white dark:from-dark-bg dark:to-dark-surface py-12 md:py-16">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h1 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-200 mb-2">Book Appointment / એપોઇન્ટમેન્ટ બુક કરો</h1>
          <p className="text-gray-600 dark:text-gray-400">Schedule your consultation with Dr. Bansari Patel / ડૉ. બંસરી પટેલ સાથે પરામર્શ નક્કી કરો</p>
        </div>
      </section>

      <section className="pb-16 -mt-4">
        <div className="max-w-3xl mx-auto px-4 sm:px-6">
          {/* Step Indicator */}
          <div className="flex items-center justify-between mb-10">
            {steps.map((s, i) => (
              <div key={s.num} className="flex items-center flex-1">
                <div className="flex flex-col items-center">
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all ${
                      step >= s.num
                        ? 'bg-primary-500 text-white'
                        : 'bg-gray-200 dark:bg-dark-border text-gray-500 dark:text-gray-400'
                    }`}
                  >
                    {step > s.num ? '✓' : s.num}
                  </div>
                  <span className="text-xs mt-1 text-gray-500 dark:text-gray-400 hidden sm:block">{s.label}</span>
                </div>
                {i < steps.length - 1 && (
                  <div className={`flex-1 h-0.5 mx-2 ${step > s.num ? 'bg-primary-500' : 'bg-gray-200 dark:bg-dark-border'}`}></div>
                )}
              </div>
            ))}
          </div>

          <form onSubmit={handleSubmit}>
            {/* ═══ STEP 1: Basic Information ═══ */}
            {step === 1 && (
              <div className="card">
                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-6">Basic Information / મૂળભૂત માહિતી</h2>
                <div className="space-y-4">
                  <div>
                    <label className="label-text">Full Name / પૂરું નામ *</label>
                    <input
                      type="text"
                      className="input-field"
                      value={basic.full_name}
                      onChange={(e) => setBasic({ ...basic, full_name: e.target.value })}
                      placeholder="Enter your full name / તમારું પૂરું નામ લખો"
                      required
                    />
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="label-text">Mobile Number / મોબાઇલ નંબર *</label>
                      <input
                        type="tel"
                        className="input-field"
                        value={basic.mobile}
                        onChange={(e) => setBasic({ ...basic, mobile: e.target.value })}
                        placeholder="+91 98765 43210"
                        required
                      />
                    </div>
                    <div>
                      <label className="label-text">Age / ઉંમર *</label>
                      <input
                        type="number"
                        className="input-field"
                        value={basic.age}
                        onChange={(e) => setBasic({ ...basic, age: e.target.value })}
                        placeholder="Age / ઉંમર"
                        min="1"
                        max="120"
                        required
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="label-text">Gender / લિંગ *</label>
                      <select
                        className="input-field"
                        value={basic.gender}
                        onChange={(e) => setBasic({ ...basic, gender: e.target.value })}
                        required
                      >
                        <option value="">Select Gender / લિંગ પસંદ કરો</option>
                        <option value="male">Male / પુરુષ</option>
                        <option value="female">Female / સ્ત્રી</option>
                        <option value="other">Other / અન્ય</option>
                      </select>
                    </div>
                    <div>
                      <label className="label-text">City / શહેર *</label>
                      <input
                        type="text"
                        className="input-field"
                        value={basic.city}
                        onChange={(e) => setBasic({ ...basic, city: e.target.value })}
                        placeholder="Your city / તમારું શહેર"
                        required
                      />
                    </div>
                  </div>

                  {/* Date */}
                  <div>
                    <label className="label-text">Appointment Date / એપોઇન્ટમેન્ટ તારીખ *</label>
                    <input
                      type="date"
                      className="input-field"
                      value={basic.appointment_date}
                      onChange={(e) => setBasic({ ...basic, appointment_date: e.target.value, appointment_time: '' })}
                      min={getMinDate()}
                      required
                    />
                    {basic.appointment_date && !clinicOpen && (
                      <p className="text-red-500 text-sm mt-1">⚠️ {slotMessage}</p>
                    )}
                  </div>

                  {/* Clinic Hours Info */}
                  {basic.appointment_date && clinicOpen && (
                    <div className="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-300">
                      <div className="flex items-center gap-2 mb-1">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span className="font-semibold">Clinic Hours / ક્લિનિક સમય:</span>
                      </div>
                      <p>Morning / સવાર: 9:30 AM – 1:00 PM &nbsp;|&nbsp; Evening / સાંજ: 5:00 PM – 8:00 PM</p>
                      {loggedInPatientId && (
                        <p className="mt-1 text-xs text-blue-600 dark:text-blue-400">
                          Patient Type / દર્દી પ્રકાર: <span className="font-semibold">{detectedPatientType === 'new' ? 'New Patient / નવા દર્દી' : 'Existing Patient / જૂના દર્દી'}</span>
                          &nbsp;({detectedPatientType === 'new' ? '30 min slots' : '15 min slots'})
                        </p>
                      )}
                    </div>
                  )}

                  {/* Slot Picker - Grouped by Session */}
                  {basic.appointment_date && clinicOpen && (
                    <div>
                      <label className="label-text">Select Time Slot / સમય પસંદ કરો *</label>
                      {slotsLoading ? (
                        <div className="flex items-center gap-2 py-4 text-gray-500 dark:text-gray-400">
                          <div className="w-5 h-5 border-2 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                          <span className="text-sm">Loading available slots... / ઉપલબ્ધ સ્લોટ લોડ થઈ રહ્યા છે...</span>
                        </div>
                      ) : slots.length > 0 ? (
                        <>
                          {/* Morning Slots */}
                          {slots.some(s => s.session === 'Morning') && (
                            <div className="mb-4">
                              <p className="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-1">
                                <span>🌅</span> Morning / સવાર (9:30 AM – 1:00 PM)
                              </p>
                              <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                                {slots.filter(s => s.session === 'Morning').map((slot) => (
                                  <button
                                    key={slot.time}
                                    type="button"
                                    disabled={!slot.available}
                                    onClick={() => setBasic({ ...basic, appointment_time: slot.time })}
                                    className={`py-2 px-1 rounded-lg text-sm font-medium border transition-all ${
                                      basic.appointment_time === slot.time
                                        ? 'border-primary-500 bg-primary-500 text-white shadow-md'
                                        : slot.available
                                        ? 'border-gray-200 dark:border-dark-border hover:border-primary-300 hover:bg-primary-50 dark:hover:bg-dark-card text-gray-700 dark:text-gray-400'
                                        : slot.booked
                                        ? 'border-red-100 dark:border-red-800 bg-red-50 dark:bg-red-900/30 text-red-300 dark:text-red-500 cursor-not-allowed line-through'
                                        : 'border-gray-100 dark:border-dark-border bg-gray-50 dark:bg-dark-card text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                    }`}
                                    title={slot.booked ? 'Already booked / પહેલેથી બુક' : slot.past ? 'Time passed / સમય વીતી ગયો' : slot.display}
                                  >
                                    {slot.display}
                                  </button>
                                ))}
                              </div>
                            </div>
                          )}

                          {/* Evening Slots */}
                          {slots.some(s => s.session === 'Evening') && (
                            <div className="mb-3">
                              <p className="text-sm font-semibold text-indigo-700 dark:text-indigo-400 mb-2 flex items-center gap-1">
                                <span>🌆</span> Evening / સાંજ (5:00 PM – 8:00 PM)
                              </p>
                              <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                                {slots.filter(s => s.session === 'Evening').map((slot) => (
                                  <button
                                    key={slot.time}
                                    type="button"
                                    disabled={!slot.available}
                                    onClick={() => setBasic({ ...basic, appointment_time: slot.time })}
                                    className={`py-2 px-1 rounded-lg text-sm font-medium border transition-all ${
                                      basic.appointment_time === slot.time
                                        ? 'border-primary-500 bg-primary-500 text-white shadow-md'
                                        : slot.available
                                        ? 'border-gray-200 dark:border-dark-border hover:border-primary-300 hover:bg-primary-50 dark:hover:bg-dark-card text-gray-700 dark:text-gray-400'
                                        : slot.booked
                                        ? 'border-red-100 dark:border-red-800 bg-red-50 dark:bg-red-900/30 text-red-300 dark:text-red-500 cursor-not-allowed line-through'
                                        : 'border-gray-100 dark:border-dark-border bg-gray-50 dark:bg-dark-card text-gray-300 dark:text-gray-600 cursor-not-allowed'
                                    }`}
                                    title={slot.booked ? 'Already booked / પહેલેથી બુક' : slot.past ? 'Time passed / સમય વીતી ગયો' : slot.display}
                                  >
                                    {slot.display}
                                  </button>
                                ))}
                              </div>
                            </div>
                          )}

                          {/* Legend */}
                          <div className="flex flex-wrap gap-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                            <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-primary-500 inline-block"></span> Selected / પસંદ</span>
                            <span className="flex items-center gap-1"><span className="w-3 h-3 rounded border border-gray-200 dark:border-dark-border inline-block"></span> Available / ઉપલબ્ધ</span>
                            <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-red-50 dark:bg-red-900/30 border border-red-100 dark:border-red-800 inline-block"></span> Booked / બુક</span>
                            <span className="flex items-center gap-1"><span className="w-3 h-3 rounded bg-gray-50 dark:bg-dark-card border border-gray-100 dark:border-dark-border inline-block"></span> Past / વીતેલ</span>
                          </div>
                        </>
                      ) : (
                        <p className="text-amber-600 dark:text-amber-400 text-sm mt-2 bg-amber-50 dark:bg-amber-900/30 p-3 rounded-lg">⚠️ {slotMessage || 'No slots available for this date. / આ તારીખ માટે કોઈ સ્લોટ ઉપલબ્ધ નથી.'}</p>
                      )}
                    </div>
                  )}
                </div>
                <div className="mt-6 flex justify-end">
                  <button type="button" onClick={nextStep} className="btn-primary" disabled={!validateStep1()}>
                    Next / આગળ →
                  </button>
                </div>
              </div>
            )}

            {/* ═══ STEP 2: Consultation Type ═══ */}
            {step === 2 && (
              <div className="card">
                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-6">Select Consultation Type / પરામર્શ પ્રકાર પસંદ કરો</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <button
                    type="button"
                    onClick={() => setConsultationType('offline')}
                    className={`p-6 rounded-xl border-2 text-left transition-all ${
                      consultationType === 'offline'
                        ? 'border-primary-500 bg-primary-50 dark:bg-dark-card'
                        : 'border-gray-200 dark:border-dark-border hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className="text-3xl mb-3">🏥</div>
                    <h3 className="font-bold text-gray-900 dark:text-gray-200 text-lg">Offline Consultation / ઓફલાઇન પરામર્શ</h3>
                    <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">Visit the clinic in person / ક્લિનિકમાં રૂબરૂ મુલાકાત</p>
                    <div className="mt-3 text-xs text-primary-600 dark:text-dark-accent font-medium bg-primary-50 dark:bg-dark-accent/15 px-2 py-1 rounded inline-block">
                      Short Form / ટૂંકું ફોર્મ
                    </div>
                  </button>

                  <button
                    type="button"
                    onClick={() => setConsultationType('online')}
                    className={`p-6 rounded-xl border-2 text-left transition-all ${
                      consultationType === 'online'
                        ? 'border-primary-500 bg-primary-50 dark:bg-dark-card'
                        : 'border-gray-200 dark:border-dark-border hover:border-gray-300 dark:hover:border-gray-500'
                    }`}
                  >
                    <div className="text-3xl mb-3">💻</div>
                    <h3 className="font-bold text-gray-900 dark:text-gray-200 text-lg">Online Consultation / ઓનલાઇન પરામર્શ</h3>
                    <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">Detailed case-taking for comprehensive treatment / વિગતવાર કેસ ટેકિંગ</p>
                    <div className="mt-3 text-xs text-primary-600 dark:text-dark-accent font-medium bg-primary-50 dark:bg-dark-accent/15 px-2 py-1 rounded inline-block">
                      Full Homeopathic Case Form / સંપૂર્ણ હોમિયોપેથિક ફોર્મ
                    </div>
                  </button>
                </div>
                <div className="mt-6 flex justify-between">
                  <button type="button" onClick={prevStep} className="btn-outline">
                    ← Back / પાછળ
                  </button>
                  <button type="button" onClick={nextStep} className="btn-primary" disabled={!validateStep2()}>
                    Next / આગળ →
                  </button>
                </div>
              </div>
            )}

            {/* ═══ STEP 3: Medical Details ═══ */}
            {step === 3 && consultationType === 'offline' && (
              <div className="card">
                <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-1">Medical Details / તબીબી વિગતો</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">Short form for offline consultation / ઓફલાઇન પરામર્શ માટે ટૂંકું ફોર્મ</p>
                <div className="space-y-4">
                  <div>
                    <label className="label-text">Chief Complaint / મુખ્ય તકલીફ *</label>
                    <textarea
                      className="input-field h-24 resize-none"
                      value={shortForm.chief_complaint}
                      onChange={(e) => setShortForm({ ...shortForm, chief_complaint: e.target.value })}
                      placeholder="Describe your main health concern / તમારી મુખ્ય સ્વાસ્થ્ય સમસ્યા જણાવો"
                      required
                    />
                  </div>
                  <div>
                    <label className="label-text">Since How Long? / ક્યારથી છે?</label>
                    <input
                      type="text"
                      className="input-field"
                      value={shortForm.complaint_duration}
                      onChange={(e) => setShortForm({ ...shortForm, complaint_duration: e.target.value })}
                      placeholder="e.g., 2 months, 1 year / દા.ત., 2 મહિના, 1 વર્ષ"
                    />
                  </div>
                  <div>
                    <label className="label-text">Major Disease History / મોટા રોગનો ઇતિહાસ</label>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-1">
                      {majorDiseaseOptions.map((disease) => (
                        <label
                          key={disease}
                          className={`flex items-center space-x-2 p-2 rounded-lg border cursor-pointer transition-colors ${
                            shortForm.major_diseases.includes(disease)
                              ? 'border-primary-500 bg-primary-50 dark:bg-dark-card'
                              : 'border-gray-200 dark:border-dark-border hover:border-gray-300 dark:hover:border-gray-500'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={shortForm.major_diseases.includes(disease)}
                            onChange={() => toggleMajorDisease(disease)}
                            className="accent-primary-500"
                          />
                          <span className="text-sm">{disease}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                  <div>
                    <label className="label-text">Current Medicines / હાલનાં દવાઓ</label>
                    <textarea
                      className="input-field h-20 resize-none"
                      value={shortForm.current_medicines}
                      onChange={(e) => setShortForm({ ...shortForm, current_medicines: e.target.value })}
                      placeholder="List any medicines you are currently taking / હાલમાં લેવાતી દવાઓ"
                    />
                  </div>
                  <div>
                    <label className="label-text">Any Allergy? / કોઈ એલર્જી?</label>
                    <input
                      type="text"
                      className="input-field"
                      value={shortForm.allergy}
                      onChange={(e) => setShortForm({ ...shortForm, allergy: e.target.value })}
                      placeholder="Drug, food, or other allergies / દવા, ખોરાક અથવા અન્ય એલર્જી"
                    />
                  </div>
                  <label className="flex items-start space-x-2 mt-4">
                    <input
                      type="checkbox"
                      checked={shortForm.declaration_accepted}
                      onChange={(e) => setShortForm({ ...shortForm, declaration_accepted: e.target.checked })}
                      className="mt-1 accent-primary-500"
                    />
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      I declare that the above information is true and correct to the best of my knowledge. / 
                      હું જાહેર કરું છું કે ઉપરોક્ત માહિતી મારી શ્રેષ્ઠ જાણકારી મુજબ સાચી અને સચોટ છે.
                    </span>
                  </label>
                </div>
                <div className="mt-6 flex justify-between">
                  <button type="button" onClick={prevStep} className="btn-outline">← Back / પાછળ</button>
                  <button
                    type="submit"
                    className="btn-primary"
                    disabled={!shortForm.chief_complaint || !shortForm.declaration_accepted || status === 'submitting'}
                  >
                    {status === 'submitting' ? 'Booking... / બુક થઈ રહ્યું છે...' : 'Book Appointment / એપોઇન્ટમેન્ટ બુક કરો'}
                  </button>
                </div>
              </div>
            )}

            {step === 3 && consultationType === 'online' && (
              <div className="space-y-6">
                {/* Main Complaints */}
                <div className="card">
                  <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-1">Main Complaints / મુખ્ય તકલીફો</h2>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Add all your health concerns / તમારી બધી સ્વાસ્થ્ય ચિંતાઓ ઉમેરો</p>
                  {mainComplaints.map((complaint, index) => (
                    <div key={index} className="bg-gray-50 dark:bg-dark-card rounded-lg p-4 mb-3">
                      <div className="flex justify-between items-center mb-2">
                        <span className="text-sm font-medium text-gray-700 dark:text-gray-400">Complaint #{index + 1}</span>
                        {mainComplaints.length > 1 && (
                          <button type="button" onClick={() => removeMainComplaint(index)} className="text-red-500 text-sm hover:text-red-700">
                            Remove
                          </button>
                        )}
                      </div>
                      <div className="space-y-2">
                        <input
                          type="text"
                          className="input-field"
                          value={complaint.text}
                          onChange={(e) => updateMainComplaint(index, 'text', e.target.value)}
                          placeholder="Describe your complaint"
                        />
                        <div className="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            className="input-field"
                            value={complaint.duration}
                            onChange={(e) => updateMainComplaint(index, 'duration', e.target.value)}
                            placeholder="Duration (e.g., 3 months)"
                          />
                          <select
                            className="input-field"
                            value={complaint.severity}
                            onChange={(e) => updateMainComplaint(index, 'severity', e.target.value as Severity)}
                          >
                            <option value="mild">Mild</option>
                            <option value="moderate">Moderate</option>
                            <option value="severe">Severe</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  ))}
                  <button type="button" onClick={addMainComplaint} className="text-primary-600 dark:text-dark-accent text-sm font-medium hover:text-primary-700 dark:hover:text-dark-accent">
                    + Add Another Complaint
                  </button>
                </div>

                {/* Past Medical History */}
                <div className="card">
                  <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-1">Past Medical History / ભૂતકાળની બીમારી</h2>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Any previous illnesses, surgeries, or conditions / અગાઉની બીમારીઓ, ઓપરેશન</p>
                  {pastDiseases.map((disease, index) => (
                    <div key={index} className="bg-gray-50 dark:bg-dark-card rounded-lg p-4 mb-3">
                      <div className="grid grid-cols-2 gap-2 mb-2">
                        <input
                          type="text"
                          className="input-field"
                          value={disease.name}
                          onChange={(e) => {
                            const updated = [...pastDiseases];
                            updated[index] = { ...updated[index], name: e.target.value };
                            setPastDiseases(updated);
                          }}
                          placeholder="Disease/Condition"
                        />
                        <input
                          type="text"
                          className="input-field"
                          value={disease.year}
                          onChange={(e) => {
                            const updated = [...pastDiseases];
                            updated[index] = { ...updated[index], year: e.target.value };
                            setPastDiseases(updated);
                          }}
                          placeholder="Year diagnosed"
                        />
                      </div>
                      <input
                        type="text"
                        className="input-field mb-2"
                        value={disease.treatment}
                        onChange={(e) => {
                          const updated = [...pastDiseases];
                          updated[index] = { ...updated[index], treatment: e.target.value };
                          setPastDiseases(updated);
                        }}
                        placeholder="Treatment taken"
                      />
                      <label className="flex items-center space-x-2">
                        <input
                          type="checkbox"
                          checked={disease.is_current}
                          onChange={(e) => {
                            const updated = [...pastDiseases];
                            updated[index] = { ...updated[index], is_current: e.target.checked };
                            setPastDiseases(updated);
                          }}
                          className="accent-primary-500"
                        />
                        <span className="text-sm text-gray-600 dark:text-gray-400">Currently ongoing</span>
                      </label>
                    </div>
                  ))}
                  <button type="button" onClick={addPastDisease} className="text-primary-600 dark:text-dark-accent text-sm font-medium hover:text-primary-700 dark:hover:text-dark-accent">
                    + Add Past Disease
                  </button>
                </div>

                {/* Family History */}
                <div className="card">
                  <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-1">Family History / કૌટુંબિક ઇતિહાસ</h2>
                  <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">Any diseases running in the family / કુટુંબમાં ચાલતી બીમારીઓ</p>
                  {familyHistory.map((item, index) => (
                    <div key={index} className="grid grid-cols-3 gap-2 mb-3">
                      <input
                        type="text"
                        className="input-field"
                        value={item.relation}
                        onChange={(e) => {
                          const updated = [...familyHistory];
                          updated[index] = { ...updated[index], relation: e.target.value };
                          setFamilyHistory(updated);
                        }}
                        placeholder="Relation (e.g., Father)"
                      />
                      <input
                        type="text"
                        className="input-field"
                        value={item.disease}
                        onChange={(e) => {
                          const updated = [...familyHistory];
                          updated[index] = { ...updated[index], disease: e.target.value };
                          setFamilyHistory(updated);
                        }}
                        placeholder="Disease"
                      />
                      <input
                        type="text"
                        className="input-field"
                        value={item.details}
                        onChange={(e) => {
                          const updated = [...familyHistory];
                          updated[index] = { ...updated[index], details: e.target.value };
                          setFamilyHistory(updated);
                        }}
                        placeholder="Details"
                      />
                    </div>
                  ))}
                  <button type="button" onClick={addFamilyHistory} className="text-primary-600 dark:text-dark-accent text-sm font-medium hover:text-primary-700 dark:hover:text-dark-accent">
                    + Add Family History
                  </button>
                </div>

                {/* Physical Generals */}
                <div className="card">
                  <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4">Physical Generals / શારીરિક સામાન્ય</h2>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {([
                      { key: 'appetite', label: 'Appetite', options: ['good', 'moderate', 'poor', 'variable'] },
                      { key: 'thirst', label: 'Thirst', options: ['normal', 'increased', 'decreased', 'absent'] },
                      { key: 'stool', label: 'Stool', options: ['regular', 'constipated', 'loose', 'alternating'] },
                      { key: 'urine', label: 'Urine', options: ['normal', 'frequent', 'scanty', 'burning'] },
                      { key: 'sweat', label: 'Sweat', options: ['normal', 'profuse', 'absent', 'offensive'] },
                      { key: 'sleep_quality', label: 'Sleep Quality', options: ['sound', 'disturbed', 'insomnia', 'excessive'] },
                      { key: 'thermal', label: 'Thermal Preference', options: ['hot', 'chilly', 'ambithermal'] },
                    ] as const).map(({ key, label, options }) => (
                      <div key={key}>
                        <label className="label-text">{label}</label>
                        <select
                          className="input-field"
                          value={physicalGenerals[key]}
                          onChange={(e) => setPhysicalGenerals({ ...physicalGenerals, [key]: e.target.value })}
                        >
                          {options.map((opt) => (
                            <option key={opt} value={opt}>{opt.charAt(0).toUpperCase() + opt.slice(1)}</option>
                          ))}
                        </select>
                      </div>
                    ))}
                    <div>
                      <label className="label-text">Sleep Position</label>
                      <input
                        type="text"
                        className="input-field"
                        value={physicalGenerals.sleep_position}
                        onChange={(e) => setPhysicalGenerals({ ...physicalGenerals, sleep_position: e.target.value })}
                        placeholder="e.g., On back, left side"
                      />
                    </div>
                    <div>
                      <label className="label-text">Cravings</label>
                      <input
                        type="text"
                        className="input-field"
                        value={physicalGenerals.cravings}
                        onChange={(e) => setPhysicalGenerals({ ...physicalGenerals, cravings: e.target.value })}
                        placeholder="e.g., Sweet, salty, sour"
                      />
                    </div>
                    <div>
                      <label className="label-text">Aversions</label>
                      <input
                        type="text"
                        className="input-field"
                        value={physicalGenerals.aversions}
                        onChange={(e) => setPhysicalGenerals({ ...physicalGenerals, aversions: e.target.value })}
                        placeholder="e.g., Oily food, milk"
                      />
                    </div>
                  </div>
                </div>

                {/* Mental & Emotional Profile */}
                <div className="card">
                  <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200 mb-4">Mental & Emotional Profile / માનસિક અને ભાવનાત્મક પ્રોફાઇલ</h2>
                  <div className="space-y-4">
                    {([
                      { key: 'temperament', label: 'Temperament', placeholder: 'e.g., Calm, Irritable, Anxious' },
                      { key: 'fears', label: 'Fears', placeholder: 'e.g., Fear of dark, heights, being alone' },
                      { key: 'dreams', label: 'Recurring Dreams', placeholder: 'Describe any recurring dreams' },
                      { key: 'stress_factors', label: 'Stress Factors', placeholder: 'What causes you stress?' },
                      { key: 'emotional_state', label: 'Current Emotional State', placeholder: 'How are you feeling emotionally?' },
                      { key: 'hobbies', label: 'Hobbies & Interests', placeholder: 'What do you enjoy doing?' },
                      { key: 'social_behavior', label: 'Social Behavior', placeholder: 'e.g., Extrovert, Introvert, Mixed' },
                    ] as const).map(({ key, label, placeholder }) => (
                      <div key={key}>
                        <label className="label-text">{label}</label>
                        <input
                          type="text"
                          className="input-field"
                          value={mentalProfile[key]}
                          onChange={(e) => setMentalProfile({ ...mentalProfile, [key]: e.target.value })}
                          placeholder={placeholder}
                        />
                      </div>
                    ))}
                    <div>
                      <label className="label-text">Additional Notes</label>
                      <textarea
                        className="input-field h-20 resize-none"
                        value={mentalProfile.additional_notes}
                        onChange={(e) => setMentalProfile({ ...mentalProfile, additional_notes: e.target.value })}
                        placeholder="Anything else you want Dr. Bansari to know?"
                      />
                    </div>
                  </div>
                </div>

                {/* Declaration */}
                <div className="card">
                  <label className="flex items-start space-x-2">
                    <input
                      type="checkbox"
                      checked={fullFormDeclaration}
                      onChange={(e) => setFullFormDeclaration(e.target.checked)}
                      className="mt-1 accent-primary-500"
                    />
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      I declare that all the information provided above is true and correct to the best of my knowledge. /
                      હું જાહેર કરું છું કે ઉપર આપેલી તમામ માહિતી મારી શ્રેષ્ઠ જાણકારી મુજબ સાચી અને સચોટ છે.
                    </span>
                  </label>
                  <div className="mt-6 flex justify-between">
                    <button type="button" onClick={prevStep} className="btn-outline">← Back / પાછળ</button>
                    <button
                      type="submit"
                      className="btn-primary"
                      disabled={!fullFormDeclaration || status === 'submitting'}
                    >
                      {status === 'submitting' ? 'Booking... / બુક થઈ રહ્યું છે...' : 'Book Appointment / એપોઇન્ટમેન્ટ બુક કરો'}
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* ═══ STEP 4: Confirmation ═══ */}
            {step === 4 && status === 'success' && (
              <div className="card text-center py-12">
                <div className="w-20 h-20 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                  <svg className="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">Appointment Booked! / એપોઇન્ટમેન્ટ બુક થઈ!</h2>
                <p className="text-gray-600 dark:text-gray-400 mb-2">
                  Your appointment has been successfully booked. / તમારી એપોઇન્ટમેન્ટ સફળતાપૂર્વક બુક થઈ ગઈ છે.
                </p>
                {appointmentId && (
                  <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Reference ID: <span className="font-mono font-bold">BHC-{appointmentId.toString().padStart(5, '0')}</span>
                  </p>
                )}
                <div className="bg-primary-50 dark:bg-dark-card rounded-lg p-4 mb-6 text-left max-w-sm mx-auto">
                  <p className="text-sm text-gray-700 dark:text-gray-400"><strong>Name / નામ:</strong> {basic.full_name}</p>
                  <p className="text-sm text-gray-700 dark:text-gray-400"><strong>Date / તારીખ:</strong> {basic.appointment_date}</p>
                  <p className="text-sm text-gray-700 dark:text-gray-400"><strong>Time / સમય:</strong> {basic.appointment_time ? (() => { const [h,m] = basic.appointment_time.split(':').map(Number); return `${h % 12 || 12}:${m.toString().padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`; })() : '—'}</p>
                  <p className="text-sm text-gray-700 dark:text-gray-400"><strong>Type / પ્રકાર:</strong> {consultationType === 'offline' ? 'Offline (In-Clinic) / ઓફલાઇન' : 'Online (Detailed) / ઓનલાઇન'}</p>
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                  Dr. Bansari Patel will confirm your appointment shortly. /
                  ડૉ. બંસરી પટેલ ટૂંક સમયમાં તમારી એપોઇન્ટમેન્ટની પુષ્ટિ કરશે.
                </p>
                <a href="/" className="btn-primary inline-block">
                  Back to Home / હોમ પર પાછા જાઓ
                </a>
              </div>
            )}

            {step === 4 && status === 'error' && (
              <div className="card text-center py-12">
                <div className="w-20 h-20 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
                  <svg className="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </div>
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">Booking Failed / બુકિંગ નિષ્ફળ</h2>
                <p className="text-gray-600 dark:text-gray-400 mb-6">{errorMessage || 'Something went wrong. Please try again. / કંઈક ખોટું થયું. ફરી પ્રયાસ કરો.'}</p>
                <button type="button" onClick={() => { setStatus('idle'); setErrorMessage(''); setStep(1); }} className="btn-primary">
                  Try Again / ફરી પ્રયાસ કરો
                </button>
              </div>
            )}
          </form>
        </div>
      </section>
    </>
  );
}
