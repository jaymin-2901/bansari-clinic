'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useSearchParams, useRouter } from 'next/navigation';
import { useLanguage } from '@/lib/LanguageContext';
import { loginPatient } from '@/lib/api';
import { useClinicSettings } from '@/components/ClinicSettingsContext';
import { useAuth } from '@/components/AuthContext';

/* ── Eye icon SVGs ── */
function EyeIcon() {
  return (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  );
}
function EyeOffIcon() {
  return (
    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
    </svg>
  );
}

export default function LoginPage() {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { t } = useLanguage();
  const searchParams = useSearchParams();
  const router = useRouter();
  
  // Get dynamic clinic settings
  const { clinicName, clinicLogo } = useClinicSettings();

  // Get return URL from query params
  const returnUrl = searchParams.get('returnUrl') || '/book-appointment';

  const isEmail = identifier.includes('@');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!identifier.trim() || !password) {
      setError('Please enter your mobile number or email and password.');
      return;
    }

    // Validate based on input type
    if (isEmail) {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(identifier)) {
        setError('Please enter a valid email address.');
        return;
      }
    } else {
      if (identifier.replace(/[^0-9]/g, '').length < 10) {
        setError('Please enter a valid 10-digit mobile number.');
        return;
      }
    }

    setLoading(true);
    try {
      const body: { mobile?: string; email?: string; password: string } = { password };
      if (isEmail) {
        body.email = identifier;
      } else {
        body.mobile = identifier;
      }

      const data = await loginPatient(body);
      if (!data.success) {
        setError(data.message || 'Login failed. Please try again.');
      } else {
        localStorage.setItem('patient', JSON.stringify(data.patient));
        // Redirect to returnUrl or default to book-appointment
        window.location.href = returnUrl;
      }
    } catch {
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

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
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200">{t('Welcome Back', 'પાછા આવો')}</h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{t('Login to your patient account', 'તમારા દર્દી ખાતામાં લોગિન કરો')}</p>
        </div>

        {error && (
          <div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Mobile Number or Email', 'મોબાઇલ નંબર અથવા ઈમેઇલ')}</label>
            <input
              type="text"
              value={identifier}
              onChange={(e) => { setIdentifier(e.target.value); setError(''); }}
              className="w-full px-4 py-3 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none text-lg bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
              placeholder="9876543210 or you@email.com"
              required
              autoFocus
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Password', 'પાસવર્ડ')}</label>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(e) => { setPassword(e.target.value); setError(''); }}
                className="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                placeholder="Enter your password"
                required
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                tabIndex={-1}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? <EyeOffIcon /> : <EyeIcon />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full btn-primary py-3 text-base disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {loading ? t('Logging in...', 'લોગિન થઈ રહ્યું છે...') : t('Login', 'લોગિન')}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
          {t("Don't have an account?", 'ખાતું નથી?')}{' '}
          <Link href="/signup" className="text-primary-600 dark:text-dark-accent hover:text-primary-700 dark:hover:text-dark-accent-hover font-semibold">
            {t('Sign Up', 'સાઇન અપ')}
          </Link>
        </p>
      </div>
    </div>
  );
}
