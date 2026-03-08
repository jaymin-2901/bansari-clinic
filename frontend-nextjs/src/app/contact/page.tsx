'use client';

import { useState, useEffect, FormEvent } from 'react';
import Link from 'next/link';
import { submitContactForm, fetchSettings } from '@/lib/api';
import { useLanguage } from '@/lib/LanguageContext';

interface ContactSettings {
  contact_address?: string;
  contact_phone?: string;
  contact_whatsapp?: string;
  contact_email?: string;
  contact_map_iframe?: string;
  contact_map_url?: string;
  contact_hours?: string;
  [key: string]: string | undefined;
}

export default function ContactPage() {
  const { t } = useLanguage();
  const [form, setForm] = useState({ name: '', email: '', phone: '', subject: '', message: '' });
  const [status, setStatus] = useState<'idle' | 'sending' | 'success' | 'error'>('idle');
  const [settings, setSettings] = useState<ContactSettings>({});

  useEffect(() => {
    fetchSettings('contact').then(setSettings);
  }, []);

  const address = settings.contact_address || '';
  const phone = settings.contact_phone || '';
  const whatsapp = settings.contact_whatsapp || '';
  const email = settings.contact_email || '';
  const mapIframe = settings.contact_map_iframe || '';
  const mapUrl = settings.contact_map_url || (address ? `https://www.google.com/maps/search/${encodeURIComponent(address)}` : '');
  const hours = settings.contact_hours || '';
  const whatsappNumber = whatsapp.replace(/[^0-9]/g, '');

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!form.name || !form.message) return;
    setStatus('sending');
    try {
      const res = await submitContactForm(form);
      if (res.success) {
        setStatus('success');
        setForm({ name: '', email: '', phone: '', subject: '', message: '' });
        setTimeout(() => setStatus('idle'), 5000);
      } else {
        setStatus('error');
      }
    } catch {
      setStatus('error');
    }
  };

  return (
    <>
      {/* ═══ Hero ═══ */}
      <section className="bg-gradient-to-br from-primary-50 via-white to-primary-50 dark:from-dark-bg dark:via-dark-surface dark:to-dark-bg py-16 md:py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <div className="inline-flex items-center px-4 py-1.5 bg-primary-100 dark:bg-dark-accent/15 text-primary-700 dark:text-dark-accent rounded-full text-sm font-medium mb-6">
            📞 {t('We\'re Here to Help', 'અમે અહીં મદદ કરવા છીએ')}
          </div>
          <h1 className="text-4xl md:text-5xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Contact Us', 'સંપર્ક કરો')}</h1>
          <p className="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            {t('Have questions or want to schedule a visit? We\'d love to hear from you.', 'પ્રશ્નો છે કે મુલાકાત ગોઠવવી છે? અમને તમારો સંપર્ક ગમશે.')}
          </p>
        </div>
      </section>

      {/* ═══ Contact Cards ═══ */}
      <section className="bg-white dark:bg-dark-surface py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 -mt-16 relative z-10">
            {/* Phone */}
            {phone && (
            <a href={`tel:${phone.replace(/\s/g, '')}`} className="card text-center hover:-translate-y-1 transition-transform group">
              <div className="w-14 h-14 bg-primary-100 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary-500 transition-colors">
                <svg className="w-7 h-7 text-primary-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-1">{t('Call Us', 'ફોન કરો')}</h3>
              <p className="text-primary-600 dark:text-dark-accent font-medium">{phone}</p>
            </a>
            )}

            {/* WhatsApp */}
            {whatsapp && (
            <a href={`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(t('Hello Dr. Bansari, I would like to book an appointment.', 'નમસ્તે ડૉ. બંસરી, હું એપોઇન્ટમેન્ટ બુક કરવા માંગુ છું.'))}`} target="_blank" rel="noopener noreferrer" className="card text-center hover:-translate-y-1 transition-transform group">
              <div className="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-green-500 transition-colors">
                <svg className="w-7 h-7 text-green-600 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
              </div>
              <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-1">{t('WhatsApp', 'WhatsApp')}</h3>
              <p className="text-green-600 font-medium">{t('Chat Now', 'હમણાં ચેટ કરો')}</p>
            </a>
            )}

            {/* Email */}
            {email && (
            <a href={`mailto:${email}`} className="card text-center hover:-translate-y-1 transition-transform group">
              <div className="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-4 group-hover:bg-blue-500 transition-colors">
                <svg className="w-7 h-7 text-blue-600 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-1">{t('Email', 'ઈમેઇલ')}</h3>
              <p className="text-blue-600 font-medium text-sm">{email}</p>
            </a>
            )}

            {/* Hours */}
            {hours && (
            <div className="card text-center">
              <div className="w-14 h-14 bg-accent-50 rounded-xl flex items-center justify-center mx-auto mb-4">
                <svg className="w-7 h-7 text-accent-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-1">{t('Working Hours', 'કાર્ય સમય')}</h3>
              <p className="text-gray-600 dark:text-gray-400 text-sm whitespace-pre-line">{hours}</p>
            </div>
            )}
          </div>
        </div>
      </section>

      {/* ═══ Map + Form ═══ */}
      <section className="section-padding bg-gray-50 dark:bg-dark-bg">
        <div className="max-w-7xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12">
            {/* Left: Address + Map */}
            <div>
              <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-6">{t('Find Us', 'અમને શોધો')}</h2>

              {address && (
              <div className="flex items-start space-x-4 mb-8">
                <div className="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg className="w-6 h-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                <div>
                  <h3 className="font-semibold text-gray-900 dark:text-gray-200 mb-1">{t('Address', 'સરનામું')}</h3>
                  <p className="text-gray-600 dark:text-gray-400 whitespace-pre-line">{address}</p>
                </div>
              </div>
              )}

              {/* Map */}
              {mapIframe ? (
                <div className="rounded-xl overflow-hidden shadow-lg h-80">
                  <iframe
                    src={mapIframe}
                    width="100%"
                    height="100%"
                    style={{ border: 0 }}
                    allowFullScreen
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                  />
                </div>
              ) : mapUrl ? (
                <div className="rounded-xl overflow-hidden shadow-lg h-80 bg-gray-200 flex items-center justify-center">
                  <div className="text-center text-gray-500">
                    <svg className="w-16 h-16 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                    <p className="font-medium">{t('Map Loading...', 'નકશો લોડ થઈ રહ્યો છે...')}</p>
                  </div>
                </div>
              ) : null}

              {/* Directions button */}
              {mapUrl && (
              <a
                href={mapUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 mt-4 text-primary-600 hover:text-primary-700 font-medium"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                {t('Get Directions', 'દિશાનિર્દેશ મેળવો')}
              </a>
              )}
            </div>

            {/* Right: Contact Form */}
            <div>
              <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-6">{t('Send a Message', 'સંદેશ મોકલો')}</h2>

              {status === 'success' && (
                <div className="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 mb-6 flex items-center gap-3">
                  <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg className="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <p>{t('Thank you for your message! We will get back to you soon.', 'તમારા સંદેશ માટે આભાર! અમે ટૂંક સમયમાં તમને જવાબ આપીશું.')}</p>
                </div>
              )}
              {status === 'error' && (
                <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6">
                  ❌ {t('Failed to send message. Please try again.', 'સંદેશ મોકલવામાં નિષ્ફળ. ફરી પ્રયાસ કરો.')}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-5">
                <div>
                  <label className="label-text">{t('Your Name', 'તમારું નામ')} <span className="text-red-500">*</span></label>
                  <input
                    type="text"
                    className="input-field"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    required
                    placeholder={t('Enter your full name', 'તમારું પૂરું નામ લખો')}
                  />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="label-text">{t('Email', 'ઈમેઇલ')}</label>
                    <input
                      type="email"
                      className="input-field"
                      value={form.email}
                      onChange={(e) => setForm({ ...form, email: e.target.value })}
                      placeholder="your@email.com"
                    />
                  </div>
                  <div>
                    <label className="label-text">{t('Phone', 'ફોન')}</label>
                    <input
                      type="tel"
                      className="input-field"
                      value={form.phone}
                      onChange={(e) => setForm({ ...form, phone: e.target.value })}
                      placeholder="+91 63543 88539"
                    />
                  </div>
                </div>
                <div>
                  <label className="label-text">{t('Subject', 'વિષય')}</label>
                  <input
                    type="text"
                    className="input-field"
                    value={form.subject}
                    onChange={(e) => setForm({ ...form, subject: e.target.value })}
                    placeholder={t('How can we help?', 'અમે કેવી રીતે મદદ કરી શકીએ?')}
                  />
                </div>
                <div>
                  <label className="label-text">{t('Message', 'સંદેશ')} <span className="text-red-500">*</span></label>
                  <textarea
                    className="input-field h-32 resize-none"
                    value={form.message}
                    onChange={(e) => setForm({ ...form, message: e.target.value })}
                    required
                    placeholder={t('Write your message here...', 'તમારો સંદેશ અહીં લખો...')}
                  ></textarea>
                </div>
                <button
                  type="submit"
                  disabled={status === 'sending'}
                  className="btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  {status === 'sending' ? (
                    <>
                      <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                      {t('Sending...', 'મોકલી રહ્યું છે...')}
                    </>
                  ) : (
                    <>
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                      </svg>
                      {t('Send Message', 'સંદેશ મોકલો')}
                    </>
                  )}
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>
    </>
  );
}

