'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { fetchSettings } from '@/lib/api';

export default function Footer() {
  const currentYear = new Date().getFullYear();
  const { t } = useLanguage();
  const [settings, setSettings] = useState<Record<string, string>>({});

  useEffect(() => {
    fetchSettings('contact').then((data) => {
      if (data && typeof data === 'object' && !Array.isArray(data)) {
        // data is already a key-value map from the API
        setSettings(data as Record<string, string>);
      } else if (Array.isArray(data)) {
        const map: Record<string, string> = {};
        data.forEach((s: { setting_key: string; setting_value: string }) => {
          map[s.setting_key] = s.setting_value;
        });
        setSettings(map);
      }
    });
  }, []);

  const phone = settings.contact_phone || '+91 98765 43210';
  const email = settings.contact_email || 'info@bansarihomeopathy.com';
  const address = settings.contact_address || 'Near City Hospital, Main Road, Ahmedabad, Gujarat';
  const whatsapp = settings.contact_whatsapp || phone;
  const mapUrl = settings.contact_map_url || '';
  const clinicHours = settings.contact_hours || '9:30 AM - 1:00 PM, 5:00 PM - 8:00 PM';

  return (
    <footer className="bg-charcoal-900 dark:bg-navy text-gray-300 transition-colors duration-500 border-t border-charcoal-800 dark:border-dark-border">
      {/* Main Footer Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-8">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">
          {/* ── Clinic Info ── */}
          <div className="sm:col-span-2 lg:col-span-1">
            <div className="flex items-center gap-3 mb-5">
              <div className="w-10 h-10 bg-gradient-to-br from-primary-400 to-teal-300 rounded-xl flex items-center justify-center shadow-glow">
                <span className="text-white font-bold text-lg">B</span>
              </div>
              <div>
                <h3 className="text-white font-bold font-heading text-lg tracking-tight">Bansari Homeopathy</h3>
                <p className="text-gray-500 text-xs font-medium">Dr. Bansari Patel</p>
              </div>
            </div>
            <p className="text-gray-400 text-sm leading-relaxed mb-5 max-w-xs">
              {t(
                'Classical homeopathic treatment for chronic and acute conditions. Gentle healing that addresses the root cause.',
                'ક્રોનિક અને એક્યુટ રોગો માટે ક્લાસિકલ હોમિયોપેથિક સારવાર. મૂળ કારણને સંબોધતો હળવો ઉપચાર.'
              )}
            </p>
            {/* WhatsApp CTA */}
            <a
              href={`https://wa.me/${whatsapp.replace(/[^0-9]/g, '')}`}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition-all hover:shadow-lg active:scale-[0.98]"
            >
              <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" /><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.638l4.687-1.228A11.953 11.953 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.395 0-4.605-.766-6.415-2.067l-.386-.28-3.227.846.862-3.147-.306-.407A9.953 9.953 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z" /></svg>
              {t('Chat on WhatsApp', 'WhatsApp પર ચેટ')}
            </a>
          </div>

          {/* ── Quick Links ── */}
          <div>
            <h4 className="text-white font-semibold font-heading mb-4 text-sm uppercase tracking-wider">{t('Quick Links', 'ઝડપી લિંક્સ')}</h4>
            <ul className="space-y-2.5">
              {[
                { href: '/', label: t('Home', 'હોમ') },
                { href: '/about', label: t('About Us', 'અમારા વિશે') },
                { href: '/testimonials', label: t('Testimonials', 'પ્રશંસાપત્ર') },
                { href: '/contact', label: t('Contact Us', 'સંપર્ક કરો') },
                { href: '/book-appointment', label: t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો') },
              ].map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="text-gray-400 hover:text-white text-sm transition-colors inline-flex items-center gap-1.5 group hover:translate-x-0.5 duration-200"
                  >
                    <svg className="w-3 h-3 text-gray-600 group-hover:text-primary-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* ── Contact Info ── */}
          <div>
            <h4 className="text-white font-semibold font-heading mb-4 text-sm uppercase tracking-wider">{t('Contact Us', 'સંપર્ક કરો')}</h4>
            <div className="space-y-3.5 text-sm">
              <div className="flex items-start gap-3">
                <div className="w-8 h-8 bg-charcoal-800 dark:bg-dark-card rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                  <svg className="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
                {mapUrl ? (
                  <a href={mapUrl} target="_blank" rel="noopener noreferrer" className="text-gray-400 leading-relaxed hover:text-white transition-colors">{address}</a>
                ) : (
                  <span className="text-gray-400 leading-relaxed">{address}</span>
                )}
              </div>
              <a href={`tel:${phone}`} className="flex items-center gap-3 text-gray-400 hover:text-white transition-colors group">
                <div className="w-8 h-8 bg-gray-800 dark:bg-dark-card rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-primary-500/20 transition-colors">
                  <svg className="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                </div>
                <span>{phone}</span>
              </a>
              <a href={`mailto:${email}`} className="flex items-center gap-3 text-gray-400 hover:text-white transition-colors group">
                <div className="w-8 h-8 bg-gray-800 dark:bg-dark-card rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-primary-500/20 transition-colors">
                  <svg className="w-4 h-4 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <span>{email}</span>
              </a>
            </div>
          </div>

          {/* ── Clinic Hours ── */}
          <div>
            <h4 className="text-white font-semibold font-heading mb-4 text-sm uppercase tracking-wider">{t('Clinic Hours', 'ક્લિનિક સમય')}</h4>
            <div className="space-y-3 text-sm">
              <div className="bg-gray-800/50 dark:bg-dark-card/50 rounded-xl p-4 space-y-3 border border-gray-800 dark:border-dark-border">
                <div className="flex justify-between items-center">
                  <span className="text-gray-400">{t('Mon - Sat', 'સોમ - શનિ')}</span>
                  <span className="text-teal-300 dark:text-teal-300 font-medium text-xs">{clinicHours}</span>
                </div>
                <div className="border-t border-gray-700/50 dark:border-dark-border pt-3 flex justify-between items-center">
                  <span className="text-gray-400">{t('Sunday', 'રવિવાર')}</span>
                  <span className="text-red-400 font-medium text-xs">{t('Closed', 'બંધ')}</span>
                </div>
              </div>

              {/* Social links placeholder */}
              <div className="flex items-center gap-2 pt-2">
                <a
                  href={`https://wa.me/${whatsapp.replace(/[^0-9]/g, '')}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-9 h-9 bg-gray-800 dark:bg-dark-card rounded-lg flex items-center justify-center text-gray-400 hover:text-green-400 hover:bg-gray-700 dark:hover:bg-dark-border transition-all"
                  aria-label="WhatsApp"
                >
                  <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" /></svg>
                </a>
                <a
                  href={`tel:${phone}`}
                  className="w-9 h-9 bg-gray-800 dark:bg-dark-card rounded-lg flex items-center justify-center text-gray-400 hover:text-primary-400 hover:bg-gray-700 dark:hover:bg-dark-border transition-all"
                  aria-label="Phone"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                </a>
                <a
                  href={`mailto:${email}`}
                  className="w-9 h-9 bg-gray-800 dark:bg-dark-card rounded-lg flex items-center justify-center text-gray-400 hover:text-primary-400 hover:bg-gray-700 dark:hover:bg-dark-border transition-all"
                  aria-label="Email"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </a>
              </div>
            </div>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="border-t border-charcoal-800 dark:border-dark-border mt-10 pt-6 flex flex-col sm:flex-row justify-between items-center gap-3 text-sm text-gray-500">
          <p>&copy; {currentYear} Bansari Homeopathy Clinic. {t('All rights reserved.', 'સર્વ હક્ક અનામત.')}</p>
          <div className="flex items-center gap-4">
            <Link href="/privacy-policy" className="hover:text-white transition-colors">
              {t('Privacy Policy', 'ગોપનીયતા નીતિ')}
            </Link>
            <span className="text-gray-700">|</span>
            <Link href="/terms-conditions" className="hover:text-white transition-colors">
              {t('Terms & Conditions', 'નિયમો અને શરતો')}
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
