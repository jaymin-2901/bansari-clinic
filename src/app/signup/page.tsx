'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { signupPatient } from '@/lib/api';

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

export default function SignupPage() {
  const [form, setForm] = useState({
    full_name: '',
    mobile: '',
    email: '',
    password: '',
    confirmPassword: '',
    age: '',
    gender: '',
    city: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const { t } = useLanguage();

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setForm({ ...form, [e.target.name]: e.target.value });
    setError('');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!form.full_name.trim() || !form.mobile.trim() || !form.password) {
      setError('Name, mobile number and password are required.');
      return;
    }
    if (form.mobile.replace(/[^0-9]/g, '').length < 10) {
      setError('Please enter a valid 10-digit mobile number.');
      return;
    }
    if (form.password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }
    if (form.password !== form.confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    setLoading(true);
    try {
      const data = await signupPatient({
        full_name: form.full_name,
        mobile: form.mobile,
        email: form.email || undefined,
        password: form.password,
        age: form.age ? parseInt(form.age) : undefined,
        gender: form.gender || undefined,
        city: form.city || undefined,
      });
      if (data.error) {
        setError(data.error || 'Signup failed. Please try again.');
      } else {
        localStorage.setItem('patient', JSON.stringify(data.patient));
        setSuccess(true);
      }
    } catch {
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg px-4">
        <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-8 max-w-md w-full text-center">
          <div className="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-2">{t('Account Created!', 'ખાતું બન્યું!')}</h2>
          <p className="text-gray-600 dark:text-gray-400 mb-6">{t('Your account has been created successfully.', 'તમારું ખાતું સફળતાપૂર્વક બન્યું છે.')}</p>
          <div className="space-y-3">
            <Link href="/book-appointment" className="btn-primary block text-center">
              {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
            </Link>
            <Link href="/" className="block text-primary-600 dark:text-dark-accent hover:text-primary-700 dark:hover:text-dark-accent-hover font-medium">
              {t('Go to Home', 'હોમ પર જાઓ')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-bg py-12 px-4">
      <div className="bg-white dark:bg-dark-card rounded-2xl shadow-lg dark:shadow-2xl dark:border dark:border-dark-border p-8 max-w-lg w-full">
        <div className="text-center mb-6">
          <div className="w-12 h-12 bg-primary-500 rounded-full flex items-center justify-center mx-auto mb-3">
            <span className="text-white font-bold text-xl">B</span>
          </div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200">{t('Create Account', 'ખાતું બનાવો')}</h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">{t('Sign up to book appointments easily', 'એપોઇન્ટમેન્ટ બુક કરવા માટે સાઇન અપ કરો')}</p>
        </div>

        {error && (
          <div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Full Name', 'પૂરું નામ')} <span className="text-red-500">*</span></label>
            <input
              type="text"
              name="full_name"
              value={form.full_name}
              onChange={handleChange}
              className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
              placeholder={t('Enter your full name', 'તમારું પૂરું નામ લખો')}
              required
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Mobile Number', 'મોબાઇલ નંબર')} <span className="text-red-500">*</span></label>
              <input
                type="tel"
                name="mobile"
                value={form.mobile}
                onChange={handleChange}
                className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                placeholder="9876543210"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Email', 'ઈમેઇલ')}</label>
              <input
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                placeholder="you@email.com"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Age', 'ઉંમર')}</label>
              <input
                type="number"
                name="age"
                value={form.age}
                onChange={handleChange}
                className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                placeholder="25"
                min="1"
                max="120"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Gender', 'લિંગ')}</label>
              <select
                name="gender"
                value={form.gender}
                onChange={handleChange}
                className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200"
              >
                <option value="">{t('Select', 'પસંદ કરો')}</option>
                <option value="male">{t('Male', 'પુરુષ')}</option>
                <option value="female">{t('Female', 'સ્ત્રી')}</option>
                <option value="other">{t('Other', 'અન્ય')}</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('City', 'શહેર')}</label>
              <input
                type="text"
                name="city"
                value={form.city}
                onChange={handleChange}
                className="w-full px-4 py-2.5 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                placeholder={t('Ahmedabad', 'અમદાવાદ')}
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Password', 'પાસવર્ડ')} <span className="text-red-500">*</span></label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  name="password"
                  value={form.password}
                  onChange={handleChange}
                  className="w-full px-4 py-2.5 pr-10 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                  placeholder={t('Min 6 characters', 'ઓછામાં ઓછા ૬ અક્ષર')}
                  required
                  minLength={6}
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
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Confirm Password', 'પાસવર્ડ ફરી લખો')} <span className="text-red-500">*</span></label>
              <div className="relative">
                <input
                  type={showConfirm ? 'text' : 'password'}
                  name="confirmPassword"
                  value={form.confirmPassword}
                  onChange={handleChange}
                  className="w-full px-4 py-2.5 pr-10 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent focus:border-primary-500 dark:focus:border-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200 placeholder:dark:text-gray-500"
                  placeholder={t('Re-enter password', 'પાસવર્ડ ફરી લખો')}
                  required
                  minLength={6}
                />
                <button
                  type="button"
                  onClick={() => setShowConfirm(!showConfirm)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                  tabIndex={-1}
                  aria-label={showConfirm ? 'Hide password' : 'Show password'}
                >
                  {showConfirm ? <EyeOffIcon /> : <EyeIcon />}
                </button>
              </div>
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full btn-primary py-3 text-base disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {loading ? t('Creating Account...', 'ખાતું બની રહ્યું છે...') : t('Sign Up', 'સાઇન અપ')}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
          {t('Already have an account?', 'પહેલથી ખાતું છે?')}{' '}
          <Link href="/login" className="text-primary-600 dark:text-dark-accent hover:text-primary-700 dark:hover:text-dark-accent-hover font-semibold">
            {t('Login', 'લોગિન')}
          </Link>
        </p>
      </div>
    </div>
  );
}
