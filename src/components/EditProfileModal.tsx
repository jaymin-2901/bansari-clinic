'use client';

import { useState, useEffect, useCallback } from 'react';
import { useLanguage } from '@/lib/LanguageContext';

interface PatientData {
  id: number;
  full_name: string;
  mobile: string;
  age: number | null;
  gender: string | null;
  city: string | null;
  address: string | null;
  email: string | null;
}

interface EditProfileModalProps {
  patient: PatientData;
  isOpen: boolean;
  onClose: () => void;
  onSaved: (updated: PatientData) => void;
}

export default function EditProfileModal({
  patient,
  isOpen,
  onClose,
  onSaved,
}: EditProfileModalProps) {
  const { t } = useLanguage();

  const [form, setForm] = useState({
    full_name: '',
    mobile: '',
    age: '',
    gender: '',
    city: '',
    address: '',
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  // Initialize form from patient data
  useEffect(() => {
    if (patient && isOpen) {
      setForm({
        full_name: patient.full_name || '',
        mobile: patient.mobile || '',
        age: patient.age !== null ? String(patient.age) : '',
        gender: patient.gender || '',
        city: patient.city || '',
        address: patient.address || '',
      });
      setError('');
      setSuccess('');
      setFieldErrors({});
    }
  }, [patient, isOpen]);

  // Close on Escape key
  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) onClose();
    };
    window.addEventListener('keydown', handleEsc);
    return () => window.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  const validate = useCallback((): boolean => {
    const errors: Record<string, string> = {};

    if (!form.full_name.trim() || form.full_name.trim().length < 2) {
      errors.full_name = t('Name must be at least 2 characters', 'નામ ઓછામાં ઓછા 2 અક્ષરનું હોવું જોઈએ');
    }

    if (!/^[6-9]\d{9}$/.test(form.mobile)) {
      errors.mobile = t('Enter a valid 10-digit mobile number', 'માન્ય 10 આંકડાનો મોબાઈલ નંબર લખો');
    }

    const ageNum = parseInt(form.age, 10);
    if (form.age && (isNaN(ageNum) || ageNum < 0 || ageNum > 120)) {
      errors.age = t('Age must be between 0 and 120', 'ઉંમર 0 થી 120 વચ્ચે હોવી જોઈએ');
    }

    if (form.city && form.city.trim().length > 100) {
      errors.city = t('City name is too long', 'શહેરનું નામ ખૂબ લાંબું છે');
    }

    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }, [form, t]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (!validate()) return;

    setSaving(true);
    try {
      const payload: Record<string, any> = {
        patient_id: patient.id,
        full_name: form.full_name.trim(),
        mobile: form.mobile,
      };

      if (form.age) payload.age = parseInt(form.age, 10);
      if (form.gender) payload.gender = form.gender;
      if (form.city) payload.city = form.city.trim();
      payload.address = form.address.trim() || null;

      const res = await fetch('/api/patient/profile', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!data.success) {
        setError(data.error || t('Update failed', 'અપડેટ નિષ્ફળ'));
        return;
      }

      setSuccess(t('Profile updated successfully!', 'પ્રોફાઈલ સફળતાપૂર્વક અપડેટ થઈ!'));

      // Update localStorage
      const stored = localStorage.getItem('patient');
      if (stored) {
        const current = JSON.parse(stored);
        const updated = {
          ...current,
          name: data.patient.full_name,
          mobile: data.patient.mobile,
        };
        localStorage.setItem('patient', JSON.stringify(updated));
      }

      // Notify parent
      onSaved(data.patient);

      // Close after short delay
      setTimeout(() => {
        onClose();
        setSuccess('');
      }, 1200);
    } catch {
      setError(t('Network error. Please try again.', 'નેટવર્ક ભૂલ. ફરી પ્રયાસ કરો.'));
    } finally {
      setSaving(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative bg-white dark:bg-dark-card rounded-2xl shadow-2xl dark:shadow-none dark:border dark:border-dark-border max-w-lg w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white dark:bg-dark-card border-b border-gray-100 dark:border-dark-border px-6 py-4 flex items-center justify-between rounded-t-2xl z-10">
          <h2 className="text-xl font-bold text-gray-900 dark:text-gray-200">
            {t('Edit Profile', 'પ્રોફાઈલ સંપાદિત કરો')}
          </h2>
          <button
            onClick={onClose}
            className="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors"
            aria-label="Close"
          >
            <svg className="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {/* Error / Success Messages */}
          {error && (
            <div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl text-sm">
              {error}
            </div>
          )}
          {success && (
            <div className="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
              <svg className="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              {success}
            </div>
          )}

          {/* Full Name */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
              {t('Full Name', 'પૂરું નામ')} <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.full_name}
              onChange={(e) => setForm({ ...form, full_name: e.target.value })}
              className="input-field"
              placeholder={t('Enter your full name', 'તમારું પૂરું નામ લખો')}
              required
            />
            {fieldErrors.full_name && (
              <p className="text-red-500 text-xs mt-1">{fieldErrors.full_name}</p>
            )}
          </div>

          {/* Mobile */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
              {t('Mobile Number', 'મોબાઈલ નંબર')} <span className="text-red-500">*</span>
            </label>
            <input
              type="tel"
              value={form.mobile}
              onChange={(e) => setForm({ ...form, mobile: e.target.value.replace(/\D/g, '').slice(0, 10) })}
              className="input-field"
              placeholder={t('10-digit mobile number', '10 આંકડાનો मોબાઈલ નંબર')}
              maxLength={10}
              required
            />
            {fieldErrors.mobile && (
              <p className="text-red-500 text-xs mt-1">{fieldErrors.mobile}</p>
            )}
          </div>

          {/* Email (readonly) */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
              {t('Email', 'ઈમેઇલ')} <span className="text-gray-400 text-xs font-normal">({t('cannot be changed', 'બદલી શકાતું નથી')})</span>
            </label>
            <input
              type="email"
              value={patient.email || ''}
              readOnly
              className="input-field bg-gray-50 dark:bg-dark-surface cursor-not-allowed opacity-70"
            />
          </div>

          {/* Age + Gender row */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
                {t('Age', 'ઉંમર')}
              </label>
              <input
                type="number"
                value={form.age}
                onChange={(e) => setForm({ ...form, age: e.target.value })}
                className="input-field"
                min={0}
                max={120}
              />
              {fieldErrors.age && (
                <p className="text-red-500 text-xs mt-1">{fieldErrors.age}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
                {t('Gender', 'જાતિ')}
              </label>
              <select
                value={form.gender}
                onChange={(e) => setForm({ ...form, gender: e.target.value })}
                className="input-field"
              >
                <option value="">{t('Select', 'પસંદ કરો')}</option>
                <option value="male">{t('Male', 'પુરુષ')}</option>
                <option value="female">{t('Female', 'સ્ત્રી')}</option>
                <option value="other">{t('Other', 'અન્ય')}</option>
              </select>
            </div>
          </div>

          {/* City */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
              {t('City', 'શહેર')}
            </label>
            <input
              type="text"
              value={form.city}
              onChange={(e) => setForm({ ...form, city: e.target.value })}
              className="input-field"
              placeholder={t('Your city', 'તમારું શહેર')}
            />
            {fieldErrors.city && (
              <p className="text-red-500 text-xs mt-1">{fieldErrors.city}</p>
            )}
          </div>

          {/* Address */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">
              {t('Address', 'સરનામું')}
            </label>
            <textarea
              value={form.address}
              onChange={(e) => setForm({ ...form, address: e.target.value })}
              className="input-field min-h-[80px] resize-y"
              placeholder={t('Your address (optional)', 'તમારું સરનામું (વૈકલ્પિક)')}
              rows={3}
            />
          </div>

          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-dark-border text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-surface font-medium transition-colors"
              disabled={saving}
            >
              {t('Cancel', 'રદ કરો')}
            </button>
            <button
              type="submit"
              disabled={saving}
              className="flex-1 btn-primary !rounded-xl flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {saving ? (
                <>
                  <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  {t('Saving...', 'સેવ થઈ રહ્યું છે...')}
                </>
              ) : (
                t('Save Changes', 'ફેરફાર સેવ કરો')
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
