'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslation } from '@/i18n';
import { getMinAppointmentDate } from '@/lib/utils';
import type { BasicInfoData, ConsultationType, Gender } from '@/types';

const GENDERS: { value: Gender; labelKey: string }[] = [
  { value: 'MALE', labelKey: 'step1.male' },
  { value: 'FEMALE', labelKey: 'step1.female' },
  { value: 'OTHER', labelKey: 'step1.other' },
];

export default function IntakePage() {
  const { tr, t } = useTranslation();
  const router = useRouter();

  const [formData, setFormData] = useState<BasicInfoData>({
    fullName: '',
    mobile: '',
    age: 0,
    gender: 'MALE',
    city: '',
    appointmentDate: '',
    consultationType: 'OFFLINE',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  const handleChange = (field: keyof BasicInfoData, value: string | number) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: '' }));
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    if (!formData.fullName.trim()) errs.fullName = tr('common.required');
    if (!/^[6-9]\d{9}$/.test(formData.mobile))
      errs.mobile = t('Enter valid 10-digit mobile number', '10 આંકડાનો મોબાઈલ નંબર લખો');
    if (!formData.age || formData.age < 0 || formData.age > 120)
      errs.age = t('Enter valid age', 'માન્ય ઉંમર લખો');
    if (!formData.city.trim()) errs.city = tr('common.required');
    if (!formData.appointmentDate) errs.appointmentDate = tr('common.required');
    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setLoading(true);
    try {
      // Store basic info in sessionStorage for the next step
      sessionStorage.setItem('basicInfo', JSON.stringify(formData));

      // Redirect based on consultation type
      if (formData.consultationType === 'OFFLINE') {
        router.push('/intake/offline');
      } else {
        router.push('/intake/online');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white py-8 px-4">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center px-3 py-1 bg-primary-100 text-primary-700 rounded-full text-sm font-medium mb-4">
            🌿 {tr('common.clinicName')}
          </div>
          <h1 className="text-3xl md:text-4xl font-bold text-gray-900 mb-2">
            {tr('step1.title')}
          </h1>
          <p className="text-gray-600">{tr('step1.subtitle')}</p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="card space-y-6">
          {/* Full Name */}
          <div>
            <label className="label-text">{tr('step1.fullName')} *</label>
            <input
              type="text"
              className={`input-field ${errors.fullName ? 'border-red-500 ring-red-200' : ''}`}
              placeholder={tr('step1.fullNamePlaceholder')}
              value={formData.fullName}
              onChange={(e) => handleChange('fullName', e.target.value)}
            />
            {errors.fullName && <p className="text-red-500 text-sm mt-1">{errors.fullName}</p>}
          </div>

          {/* Mobile + Age in row */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="label-text">{tr('step1.mobile')} *</label>
              <input
                type="tel"
                maxLength={10}
                className={`input-field ${errors.mobile ? 'border-red-500 ring-red-200' : ''}`}
                placeholder={tr('step1.mobilePlaceholder')}
                value={formData.mobile}
                onChange={(e) => handleChange('mobile', e.target.value.replace(/\D/g, ''))}
              />
              {errors.mobile && <p className="text-red-500 text-sm mt-1">{errors.mobile}</p>}
            </div>
            <div>
              <label className="label-text">{tr('step1.age')} *</label>
              <input
                type="number"
                min={0}
                max={120}
                className={`input-field ${errors.age ? 'border-red-500 ring-red-200' : ''}`}
                placeholder={tr('step1.agePlaceholder')}
                value={formData.age || ''}
                onChange={(e) => handleChange('age', parseInt(e.target.value) || 0)}
              />
              {errors.age && <p className="text-red-500 text-sm mt-1">{errors.age}</p>}
            </div>
          </div>

          {/* Gender */}
          <div>
            <label className="label-text">{tr('step1.gender')} *</label>
            <div className="flex gap-4 mt-1">
              {GENDERS.map((g) => (
                <label
                  key={g.value}
                  className={`flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 cursor-pointer transition-all ${
                    formData.gender === g.value
                      ? 'border-primary-500 bg-primary-50 text-primary-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <input
                    type="radio"
                    name="gender"
                    value={g.value}
                    checked={formData.gender === g.value}
                    onChange={() => handleChange('gender', g.value)}
                    className="sr-only"
                  />
                  <span className="text-sm font-medium">{tr(g.labelKey)}</span>
                </label>
              ))}
            </div>
          </div>

          {/* City + Appointment Date */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="label-text">{tr('step1.city')} *</label>
              <input
                type="text"
                className={`input-field ${errors.city ? 'border-red-500 ring-red-200' : ''}`}
                placeholder={tr('step1.cityPlaceholder')}
                value={formData.city}
                onChange={(e) => handleChange('city', e.target.value)}
              />
              {errors.city && <p className="text-red-500 text-sm mt-1">{errors.city}</p>}
            </div>
            <div>
              <label className="label-text">{tr('step1.appointmentDate')} *</label>
              <input
                type="date"
                min={getMinAppointmentDate()}
                className={`input-field ${errors.appointmentDate ? 'border-red-500 ring-red-200' : ''}`}
                value={formData.appointmentDate}
                onChange={(e) => handleChange('appointmentDate', e.target.value)}
              />
              {errors.appointmentDate && (
                <p className="text-red-500 text-sm mt-1">{errors.appointmentDate}</p>
              )}
            </div>
          </div>

          {/* Consultation Type — THE KEY DECISION */}
          <div>
            <label className="label-text mb-3">
              {tr('step1.consultationType')} *
            </label>
            <p className="text-sm text-gray-500 mb-3">{tr('step1.consultationTypeSubtitle')}</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Offline */}
              <button
                type="button"
                onClick={() => handleChange('consultationType', 'OFFLINE' as ConsultationType)}
                className={`text-left p-5 rounded-xl border-2 transition-all duration-200 ${
                  formData.consultationType === 'OFFLINE'
                    ? 'border-primary-500 bg-primary-50 shadow-md'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="flex items-center gap-3 mb-2">
                  <span className="text-2xl">🏥</span>
                  <span className="font-semibold text-gray-900">{tr('step1.offline')}</span>
                </div>
                <p className="text-sm text-gray-500">{tr('step1.offlineDesc')}</p>
                {formData.consultationType === 'OFFLINE' && (
                  <div className="mt-2 text-primary-600 text-sm font-medium flex items-center gap-1">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                    {t('Selected', 'પસંદ કરેલ')}
                  </div>
                )}
              </button>

              {/* Online */}
              <button
                type="button"
                onClick={() => handleChange('consultationType', 'ONLINE' as ConsultationType)}
                className={`text-left p-5 rounded-xl border-2 transition-all duration-200 ${
                  formData.consultationType === 'ONLINE'
                    ? 'border-primary-500 bg-primary-50 shadow-md'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="flex items-center gap-3 mb-2">
                  <span className="text-2xl">💻</span>
                  <span className="font-semibold text-gray-900">{tr('step1.online')}</span>
                </div>
                <p className="text-sm text-gray-500">{tr('step1.onlineDesc')}</p>
                {formData.consultationType === 'ONLINE' && (
                  <div className="mt-2 text-primary-600 text-sm font-medium flex items-center gap-1">
                    <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                    </svg>
                    {t('Selected', 'પસંદ કરેલ')}
                  </div>
                )}
              </button>
            </div>
          </div>

          {/* Submit */}
          <button
            type="submit"
            disabled={loading}
            className="w-full btn-primary text-lg py-4 flex items-center justify-center gap-2"
          >
            {loading ? (
              <span className="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full" />
            ) : (
              <>
                {tr('step1.proceed')}
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
