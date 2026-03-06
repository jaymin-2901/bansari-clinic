'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslation } from '@/i18n';
import type { BasicInfoData, OfflineFormData } from '@/types';
import { MEDICAL_HISTORY_OPTIONS } from '@/types';

export default function OfflineIntakePage() {
  const { tr, t } = useTranslation();
  const router = useRouter();

  const [basicInfo, setBasicInfo] = useState<BasicInfoData | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [formData, setFormData] = useState<OfflineFormData>({
    chiefComplaint: '',
    complaintDuration: '',
    medicalHistory: [],
    currentMedicines: '',
    hasAllergy: false,
    allergyDetails: '',
    clinicConfirmation: false,
  });

  const [reportFiles, setReportFiles] = useState<File[]>([]);

  useEffect(() => {
    const saved = sessionStorage.getItem('basicInfo');
    if (!saved) {
      router.push('/intake');
      return;
    }
    setBasicInfo(JSON.parse(saved));
  }, [router]);

  const handleChange = (field: keyof OfflineFormData, value: unknown) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: '' }));
  };

  const toggleMedicalHistory = (item: string) => {
    setFormData((prev) => {
      const current = prev.medicalHistory;
      if (item === 'None') {
        return { ...prev, medicalHistory: current.includes('None') ? [] : ['None'] };
      }
      const filtered = current.filter((h) => h !== 'None');
      return {
        ...prev,
        medicalHistory: filtered.includes(item)
          ? filtered.filter((h) => h !== item)
          : [...filtered, item],
      };
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) {
      const newFiles = Array.from(e.target.files);
      // Validate file types and size
      const validFiles = newFiles.filter((file) => {
        const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        return validTypes.includes(file.type) && file.size <= maxSize;
      });
      setReportFiles((prev) => [...prev, ...validFiles]);
    }
  };

  const removeFile = (index: number) => {
    setReportFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    if (!formData.chiefComplaint.trim()) errs.chiefComplaint = tr('common.required');
    if (!formData.complaintDuration.trim()) errs.complaintDuration = tr('common.required');
    if (formData.hasAllergy && !formData.allergyDetails.trim())
      errs.allergyDetails = tr('common.required');
    if (!formData.clinicConfirmation)
      errs.clinicConfirmation = t('Please confirm', 'કૃપા કરીને ખાતરી કરો');
    setErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate() || !basicInfo) return;

    setLoading(true);
    try {
      const payload = {
        basicInfo,
        offlineForm: formData,
        consultationType: 'OFFLINE',
        formType: 'SHORT',
      };

      const res = await fetch('/api/intake/offline', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) throw new Error('Failed to submit');

      // Upload files if any
      if (reportFiles.length > 0) {
        const result = await res.json();
        const uploadData = new FormData();
        reportFiles.forEach((file) => uploadData.append('reports', file));
        uploadData.append('appointmentId', result.data.appointmentId);

        await fetch('/api/upload', {
          method: 'POST',
          body: uploadData,
        });
      }

      sessionStorage.removeItem('basicInfo');
      setSubmitted(true);
    } catch (error) {
      console.error('Submission error:', error);
      setErrors({ general: t('Something went wrong. Please try again.', 'કંઈક ખોટું થયું. ફરીથી પ્રયાસ કરો.') });
    } finally {
      setLoading(false);
    }
  };

  // Medical history label helper
  const medHistLabel = (item: string) => {
    const keyMap: Record<string, string> = {
      Diabetes: 'offline.diabetes',
      BP: 'offline.bp',
      Thyroid: 'offline.thyroid',
      Asthma: 'offline.asthma',
      TB: 'offline.tb',
      Surgery: 'offline.surgery',
      None: 'offline.none',
    };
    return tr(keyMap[item] || item);
  };

  // ─── Success Screen ─────────────────────────────────
  if (submitted) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white flex items-center justify-center px-4">
        <div className="card text-center max-w-md">
          <div className="w-20 h-20 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
            <svg className="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">{tr('offline.appointmentConfirmed')}</h2>
          <p className="text-gray-600 mb-6">{tr('offline.appointmentConfirmedMsg')}</p>
          <button onClick={() => router.push('/')} className="btn-primary">
            {tr('common.home')}
          </button>
        </div>
      </div>
    );
  }

  if (!basicInfo) return null;

  // ─── Offline Form ───────────────────────────────────
  return (
    <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white py-8 px-4">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="text-center mb-8">
          <button
            onClick={() => router.back()}
            className="inline-flex items-center text-primary-600 hover:text-primary-800 mb-4"
          >
            <svg className="w-5 h-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            {tr('common.back')}
          </button>
          <div className="flex items-center justify-center gap-2 mb-2">
            <span className="text-2xl">🏥</span>
            <h1 className="text-3xl font-bold text-gray-900">{tr('offline.title')}</h1>
          </div>
          <p className="text-gray-600">{tr('offline.subtitle')}</p>
          <div className="mt-3 inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm">
            📅 {basicInfo.fullName} • {basicInfo.appointmentDate}
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {errors.general && (
            <div className="bg-red-50 text-red-700 p-4 rounded-lg text-sm">{errors.general}</div>
          )}

          {/* 1. Chief Complaint */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-7 h-7 bg-primary-500 text-white text-sm rounded-full flex items-center justify-center">1</span>
              {tr('offline.chiefComplaint')}
            </h3>
            <div className="space-y-4">
              <div>
                <label className="label-text">{tr('offline.chiefComplaint')} *</label>
                <textarea
                  rows={3}
                  className={`input-field ${errors.chiefComplaint ? 'border-red-500' : ''}`}
                  placeholder={tr('offline.chiefComplaintPlaceholder')}
                  value={formData.chiefComplaint}
                  onChange={(e) => handleChange('chiefComplaint', e.target.value)}
                />
                {errors.chiefComplaint && <p className="text-red-500 text-sm mt-1">{errors.chiefComplaint}</p>}
              </div>
              <div>
                <label className="label-text">{tr('offline.complaintDuration')} *</label>
                <input
                  type="text"
                  className={`input-field ${errors.complaintDuration ? 'border-red-500' : ''}`}
                  placeholder={tr('offline.complaintDurationPlaceholder')}
                  value={formData.complaintDuration}
                  onChange={(e) => handleChange('complaintDuration', e.target.value)}
                />
                {errors.complaintDuration && <p className="text-red-500 text-sm mt-1">{errors.complaintDuration}</p>}
              </div>
              {/* File Upload */}
              <div>
                <label className="label-text">{tr('offline.uploadReports')}</label>
                <p className="text-xs text-gray-500 mb-2">{tr('offline.uploadReportsDesc')}</p>
                <label className="flex items-center justify-center gap-2 px-4 py-8 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all">
                  <svg className="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span className="text-sm text-gray-500">
                    {t('Click to upload (JPG, PNG, PDF — max 10MB)', 'અપલોડ કરવા ક્લિક કરો (JPG, PNG, PDF — મહત્તમ 10MB)')}
                  </span>
                  <input type="file" className="sr-only" accept=".jpg,.jpeg,.png,.pdf" multiple onChange={handleFileChange} />
                </label>
                {reportFiles.length > 0 && (
                  <ul className="mt-3 space-y-2">
                    {reportFiles.map((file, i) => (
                      <li key={i} className="flex items-center justify-between bg-gray-50 px-3 py-2 rounded-lg text-sm">
                        <span className="truncate mr-3">{file.name}</span>
                        <button type="button" onClick={() => removeFile(i)} className="text-red-500 hover:text-red-700 flex-shrink-0">
                          ✕
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>

          {/* 2. Medical History */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-2 flex items-center gap-2">
              <span className="w-7 h-7 bg-primary-500 text-white text-sm rounded-full flex items-center justify-center">2</span>
              {tr('offline.medicalHistory')}
            </h3>
            <p className="text-sm text-gray-500 mb-4">{tr('offline.medicalHistoryDesc')}</p>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
              {MEDICAL_HISTORY_OPTIONS.map((item) => (
                <label
                  key={item}
                  className={`flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 cursor-pointer transition-all text-sm ${
                    formData.medicalHistory.includes(item)
                      ? 'border-primary-500 bg-primary-50 text-primary-700'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={formData.medicalHistory.includes(item)}
                    onChange={() => toggleMedicalHistory(item)}
                    className="sr-only"
                  />
                  <span className={`w-4 h-4 rounded flex items-center justify-center border-2 ${
                    formData.medicalHistory.includes(item)
                      ? 'bg-primary-500 border-primary-500 text-white'
                      : 'border-gray-300'
                  }`}>
                    {formData.medicalHistory.includes(item) && (
                      <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    )}
                  </span>
                  {medHistLabel(item)}
                </label>
              ))}
            </div>
          </div>

          {/* 3. Current Medicines */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-7 h-7 bg-primary-500 text-white text-sm rounded-full flex items-center justify-center">3</span>
              {tr('offline.currentMedicines')}
            </h3>
            <textarea
              rows={3}
              className="input-field"
              placeholder={tr('offline.currentMedicinesPlaceholder')}
              value={formData.currentMedicines}
              onChange={(e) => handleChange('currentMedicines', e.target.value)}
            />
          </div>

          {/* 4. Allergy */}
          <div className="card">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="w-7 h-7 bg-primary-500 text-white text-sm rounded-full flex items-center justify-center">4</span>
              {tr('offline.allergy')}
            </h3>
            <div className="flex gap-4 mb-4">
              <label className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer ${
                formData.hasAllergy ? 'border-primary-500 bg-primary-50' : 'border-gray-200'
              }`}>
                <input type="radio" name="allergy" className="sr-only" checked={formData.hasAllergy} onChange={() => handleChange('hasAllergy', true)} />
                {tr('common.yes')}
              </label>
              <label className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer ${
                !formData.hasAllergy ? 'border-primary-500 bg-primary-50' : 'border-gray-200'
              }`}>
                <input type="radio" name="allergy" className="sr-only" checked={!formData.hasAllergy} onChange={() => handleChange('hasAllergy', false)} />
                {tr('common.no')}
              </label>
            </div>
            {formData.hasAllergy && (
              <div>
                <label className="label-text">{tr('offline.allergyDetails')} *</label>
                <textarea
                  rows={2}
                  className={`input-field ${errors.allergyDetails ? 'border-red-500' : ''}`}
                  placeholder={tr('offline.allergyDetailsPlaceholder')}
                  value={formData.allergyDetails}
                  onChange={(e) => handleChange('allergyDetails', e.target.value)}
                />
                {errors.allergyDetails && <p className="text-red-500 text-sm mt-1">{errors.allergyDetails}</p>}
              </div>
            )}
          </div>

          {/* 5. Clinic Confirmation */}
          <div className="card">
            <label className={`flex items-start gap-3 cursor-pointer ${errors.clinicConfirmation ? 'text-red-600' : ''}`}>
              <input
                type="checkbox"
                checked={formData.clinicConfirmation}
                onChange={(e) => handleChange('clinicConfirmation', e.target.checked)}
                className="mt-1 w-5 h-5 rounded border-gray-300 text-primary-500 focus:ring-primary-500"
              />
              <span className="text-sm font-medium text-gray-700">{tr('offline.clinicConfirmation')}</span>
            </label>
            {errors.clinicConfirmation && <p className="text-red-500 text-sm mt-2">{errors.clinicConfirmation}</p>}
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
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                </svg>
                {tr('offline.submitForm')}
              </>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
