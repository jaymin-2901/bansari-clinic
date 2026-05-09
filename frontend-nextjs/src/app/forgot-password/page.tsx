'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { forgotPassword } from '@/lib/api';
import { useClinicSettings } from '@/components/ClinicSettingsContext';
import ReCAPTCHA from 'react-google-recaptcha';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { t } = useLanguage();
  const { clinicName, clinicLogo } = useClinicSettings();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setMessage('');

    if (!email.trim()) {
      setError('Please enter your email address.');
      return;
    }

    if (!captchaToken) {
      setError('Please solve the CAPTCHA.');
      return;
    }

    setLoading(true);
    try {
      const data = await forgotPassword(email, captchaToken);
      if (data.success) {
        setMessage(data.message || 'If an account exists with this email, you will receive a reset link shortly.');
      } else {
        setError(data.error || 'Failed to request password reset. Please try again.');
        setCaptchaToken(null); // Reset captcha on failure
      }
    } catch (err) {
      setError('Network error. Please try again later.');
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
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-200">{t('Forgot Password', 'પાસવર્ડ ભૂલી ગયા')}</h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">
            {t('Enter your email to receive a password reset link', 'પાસવર્ડ રીસેટ લિંક મેળવવા માટે તમારો ઈમેઈલ દાખલ કરો')}
          </p>
        </div>

        {error && (
          <div className="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
            {error}
          </div>
        )}

        {message && (
          <div className="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg mb-4 text-sm">
            {message}
          </div>
        )}

        {!message ? (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-1">{t('Email Address', 'ઈમેઈલ સરનામું')}</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-4 py-3 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-primary-500 dark:focus:ring-dark-accent outline-none bg-white dark:bg-dark-surface text-gray-900 dark:text-gray-200"
                placeholder="you@email.com"
                required
              />
            </div>

            <div className="flex justify-center py-2">
              <ReCAPTCHA
                sitekey={process.env.NEXT_PUBLIC_RECAPTCHA_SITE_KEY || '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'} // Test site key
                onChange={(token) => setCaptchaToken(token)}
                theme="light"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full btn-primary py-3 text-base disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {loading ? t('Processing...', 'પ્રોસેસિંગ...') : t('Send Reset Link', 'રીસેટ લિંક મોકલો')}
            </button>
          </form>
        ) : (
          <div className="text-center mt-4">
             <Link href="/login" className="text-primary-600 dark:text-dark-accent hover:underline font-medium">
               {t('Back to Login', 'લોગિન પર પાછા જાઓ')}
             </Link>
          </div>
        )}

        {!message && (
          <p className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
            <Link href="/login" className="text-primary-600 dark:text-dark-accent hover:text-primary-700 font-semibold">
              {t('Back to Login', 'લોગિન પર પાછા જાઓ')}
            </Link>
          </p>
        )}
      </div>
    </div>
  );
}
