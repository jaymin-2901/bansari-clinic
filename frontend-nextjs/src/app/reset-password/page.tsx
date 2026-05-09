'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import { useLanguage } from '@/lib/LanguageContext';
import { resetPassword } from '@/lib/api';
import { useClinicSettings } from '@/components/ClinicSettingsContext';

function ResetPasswordForm() {
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);
  
  const searchParams = useSearchParams();
  const router = useRouter();
  const { t } = useLanguage();
  const { clinicName, clinicLogo } = useClinicSettings();
  
  const token = searchParams.get('token');

  useEffect(() => {
    if (!token) {
      setError('Invalid reset link. Token is missing.');
    }
  }, [token]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!token) {
      setError('Invalid or expired reset link.');
      return;
    }

    if (password.length < 8) {
      setError('Password must be at least 8 characters long.');
      return;
    }

    if (password !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    setLoading(true);
    try {
      const data = await resetPassword({ token, password });
      if (data.success) {
        setSuccess(true);
        setTimeout(() => {
          router.push('/login');
        }, 3000);
      } else {
        setError(data.error || 'Failed to reset password. The link might be expired.');
      }
    } catch (err) {
      setError('Network error. Please try again later.');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg py-12 px-4">
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
          <div className="w-16 h-16 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">{t('Password Reset Successfully', 'પાસવર્ડ સફળતાપૂર્વક રીસેટ થયો')}</h1>
          <p className="text-gray-600 dark:text-gray-400 mb-6">{t('You will be redirected to the login page shortly.', 'તમને ટૂંક સમયમાં લોગિન પેજ પર મોકલવામાં આવશે.')}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg py-12 px-4">
      <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-8 max-w-md w-full">
        <div className="text-center mb-6">
          {clinicLogo ? (
            <img 
              src={clinicLogo} 
              alt={clinicName}
              className="w-12 h-12 rounded-full object-cover mx-auto mb-3 shadow-glow"
            />
          ) : (
            <div className="w-12 h-12 bg-primary-500 rounded-full flex items-center justify-center mx-auto mb-3">
              <span className="text-white font-bold text-xl">B</span>
            </div>
          )}
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200">{t('Reset Password', 'પાસવર્ડ રીસેટ કરો')}</h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{t('Enter your new password below', 'નીચે તમારો નવો પાસવર્ડ દાખલ કરો')}</p>
        </div>

        {error && (
          <div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('New Password', 'નવો પાસવર્ડ')}</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-4 py-3 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200"
              placeholder="Min 8 characters"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Confirm Password', 'પાસવર્ડની પુષ્ટિ કરો')}</label>
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              className="w-full px-4 py-3 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200"
              placeholder="Confirm your new password"
              required
            />
          </div>

          <button
            type="submit"
            disabled={loading || !!error && !token}
            className="w-full btn-primary py-3 text-base disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {loading ? t('Resetting...', 'રીસેટ થઈ રહ્યું છે...') : t('Reset Password', 'પાસવર્ડ રીસેટ કરો')}
          </button>
        </form>
      </div>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<div>Loading...</div>}>
      <ResetPasswordForm />
    </Suspense>
  );
}
