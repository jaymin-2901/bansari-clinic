'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useLanguage } from '@/lib/LanguageContext';
import ThemeToggle from '@/components/ThemeToggle';
import { motion, AnimatePresence } from 'framer-motion';

interface PatientInfo {
  id: number;
  name: string;
  mobile: string;
  email?: string;
}

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [patient, setPatient] = useState<PatientInfo | null>(null);
  const [profileOpen, setProfileOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);
  const { lang, setLang, t } = useLanguage();
  const pathname = usePathname();

  useEffect(() => {
    try {
      const stored = localStorage.getItem('patient');
      if (stored) setPatient(JSON.parse(stored));
    } catch {}

    const handleClickOutside = (e: MouseEvent) => {
      if (profileRef.current && !profileRef.current.contains(e.target as Node)) {
        setProfileOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 10);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  // Lock body scroll when mobile menu is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  const handleLogout = () => {
    localStorage.removeItem('patient');
    setPatient(null);
    setProfileOpen(false);
    window.location.href = '/';
  };

  const closeMobile = useCallback(() => setIsOpen(false), []);

  const getInitials = (name: string) =>
    name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2);

  const navLinks = [
    { href: '/', label: t('Home', 'હોમ') },
    { href: '/about', label: t('About Us', 'અમારા વિશે') },
    { href: '/testimonials', label: t('Testimonials', 'પ્રશંસાપત્ર') },
    { href: '/contact', label: t('Contact Us', 'સંપર્ક કરો') },
  ];

  const isActive = (href: string) => pathname === href;

  return (
    <>
      <header
        className={`sticky top-0 z-50 transition-all duration-500 ${
          scrolled
            ? 'bg-white/75 dark:bg-dark-surface/70 backdrop-blur-2xl shadow-glass border-b border-gray-100/50 dark:border-dark-border/30'
            : 'bg-white/95 dark:bg-dark-surface/95 backdrop-blur-sm'
        }`}
      >
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-[70px] lg:h-[80px]">
            {/* ── Logo ── */}
            <Link href="/" className="flex items-center gap-3 group">
              <div className="relative w-10 h-10 bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 rounded-xl flex items-center justify-center shadow-glow group-hover:shadow-glow-lg transition-all duration-300 group-hover:scale-105">
                <span className="text-white font-bold text-lg font-heading">B</span>
              </div>
              <div>
                <h1 className="text-lg font-bold text-gray-900 dark:text-white leading-tight tracking-tight font-heading">
                  Bansari Homeopathy
                </h1>
                <p className="text-[11px] text-charcoal-400 dark:text-dark-muted hidden sm:block font-medium tracking-wide">
                  Dr. Bansari Patel
                </p>
              </div>
            </Link>

            {/* ── Desktop Navigation ── */}
            <nav className="hidden lg:flex items-center gap-1">
              {navLinks.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`relative px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 ${
                    isActive(link.href)
                      ? 'text-primary-500 dark:text-dark-accent bg-primary-50/80 dark:bg-dark-accent/10'
                      : 'text-charcoal-800 dark:text-gray-400 hover:text-primary-500 dark:hover:text-dark-accent hover:bg-gray-50/80 dark:hover:bg-dark-card/50'
                  }`}
                >
                  {link.label}
                  {isActive(link.href) && (
                    <motion.div
                      layoutId="nav-indicator"
                      className="absolute -bottom-0.5 left-3 right-3 h-[2.5px] bg-gradient-to-r from-primary-500 to-teal-300 dark:from-dark-accent dark:to-teal-400 rounded-full"
                      transition={{ type: 'spring', stiffness: 400, damping: 30 }}
                    />
                  )}
                </Link>
              ))}
            </nav>

            {/* ── Desktop Right Actions ── */}
            <div className="hidden lg:flex items-center gap-2">
              {/* Language Toggle */}
              <button
                onClick={() => setLang(lang === 'en' ? 'gu' : 'en')}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-dark-card transition-colors text-sm font-medium"
                title={lang === 'en' ? 'Switch to Gujarati' : 'Switch to English'}
              >
                <svg className="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" />
                </svg>
                <span className={lang === 'en' ? 'text-primary-600 dark:text-dark-accent font-semibold' : 'text-gray-400'}>EN</span>
                <span className="text-gray-300 dark:text-gray-600">|</span>
                <span className={lang === 'gu' ? 'text-primary-600 dark:text-dark-accent font-semibold' : 'text-gray-400'}>ગુ</span>
              </button>

              {/* Theme Toggle */}
              <ThemeToggle />

              {/* Divider */}
              <div className="w-px h-6 bg-gray-200 dark:bg-dark-border mx-1" />

              {patient ? (
                /* ── Logged-in: Profile Dropdown ── */
                <div className="relative" ref={profileRef}>
                  <button
                    onClick={() => setProfileOpen(!profileOpen)}
                    className="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 dark:hover:bg-dark-card transition-all border border-gray-200 dark:border-dark-border"
                  >
                    <div className="w-8 h-8 bg-gradient-to-br from-primary-400 to-primary-600 rounded-lg flex items-center justify-center">
                      <span className="text-white font-semibold text-xs">{getInitials(patient.name)}</span>
                    </div>
                    <span className="text-sm font-medium text-gray-700 dark:text-gray-200 max-w-[120px] truncate">
                      {patient.name}
                    </span>
                    <motion.svg
                      animate={{ rotate: profileOpen ? 180 : 0 }}
                      transition={{ duration: 0.2 }}
                      className="w-4 h-4 text-gray-400"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </motion.svg>
                  </button>

                  <AnimatePresence>
                    {profileOpen && (
                      <motion.div
                        initial={{ opacity: 0, y: 8, scale: 0.96 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 8, scale: 0.96 }}
                        transition={{ duration: 0.15 }}
                        className="absolute right-0 mt-2 w-64 bg-white dark:bg-dark-card rounded-2xl shadow-soft-lg border border-gray-100 dark:border-dark-border py-2 z-50"
                      >
                        <div className="px-4 py-3 border-b border-gray-100 dark:border-dark-border">
                          <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-primary-100 dark:bg-dark-accent/20 rounded-xl flex items-center justify-center">
                              <span className="text-primary-600 dark:text-dark-accent font-bold text-sm">{getInitials(patient.name)}</span>
                            </div>
                            <div className="min-w-0">
                              <p className="font-semibold text-gray-900 dark:text-gray-200 text-sm truncate">{patient.name}</p>
                              <p className="text-xs text-gray-500 dark:text-gray-400">{patient.mobile}</p>
                              {patient.email && <p className="text-xs text-gray-400 dark:text-gray-500 truncate">{patient.email}</p>}
                            </div>
                          </div>
                        </div>
                        <div className="py-1">
                          <Link href="/profile" className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-surface rounded-lg mx-1 transition-colors" onClick={() => setProfileOpen(false)}>
                            <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            <span>{t('My Profile', 'મારી પ્રોફાઈલ')}</span>
                          </Link>
                          <Link href="/book-appointment" className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-surface rounded-lg mx-1 transition-colors" onClick={() => setProfileOpen(false)}>
                            <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            <span>{t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}</span>
                          </Link>
                          <Link href="/my-appointments" className="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-surface rounded-lg mx-1 transition-colors" onClick={() => setProfileOpen(false)}>
                            <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            <span>{t('My Appointments', 'મારી એપોઇન્ટમેન્ટ')}</span>
                          </Link>
                        </div>
                        <div className="border-t border-gray-100 dark:border-dark-border pt-1 mx-1">
                          <button onClick={handleLogout} className="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg w-full text-left transition-colors">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            <span>{t('Logout', 'લોગઆઉટ')}</span>
                          </button>
                        </div>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </div>
              ) : (
                <>
                  <Link
                    href="/login"
                    className="text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-dark-accent font-medium transition-colors text-sm px-3 py-2 rounded-xl hover:bg-gray-50 dark:hover:bg-dark-card"
                  >
                    {t('Login', 'લોગિન')}
                  </Link>
                </>
              )}

              {/* Primary CTA */}
              <Link
                href="/book-appointment"
                className="btn-primary text-sm !py-2.5 !px-5 !rounded-xl flex items-center gap-2 shadow-glow hover:shadow-glow-lg"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
              </Link>
            </div>

            {/* ── Mobile: Right Actions ── */}
            <div className="lg:hidden flex items-center gap-2">
              <ThemeToggle />
              <button
                onClick={() => setIsOpen(!isOpen)}
                className="relative w-10 h-10 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-dark-card hover:bg-gray-200 dark:hover:bg-dark-border transition-colors"
                aria-label="Toggle menu"
              >
                <div className="w-5 h-4 flex flex-col justify-between">
                  <motion.span
                    animate={isOpen ? { rotate: 45, y: 7 } : { rotate: 0, y: 0 }}
                    className="block w-5 h-0.5 bg-gray-700 dark:bg-gray-300 rounded-full origin-center"
                    transition={{ duration: 0.2 }}
                  />
                  <motion.span
                    animate={isOpen ? { opacity: 0, scaleX: 0 } : { opacity: 1, scaleX: 1 }}
                    className="block w-5 h-0.5 bg-gray-700 dark:bg-gray-300 rounded-full"
                    transition={{ duration: 0.15 }}
                  />
                  <motion.span
                    animate={isOpen ? { rotate: -45, y: -7 } : { rotate: 0, y: 0 }}
                    className="block w-5 h-0.5 bg-gray-700 dark:bg-gray-300 rounded-full origin-center"
                    transition={{ duration: 0.2 }}
                  />
                </div>
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* ═══ Mobile Slide-In Drawer ═══ */}
      <AnimatePresence>
        {isOpen && (
          <>
            {/* Backdrop */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="fixed inset-0 bg-black/40 backdrop-blur-sm z-[60] lg:hidden"
              onClick={closeMobile}
            />

            {/* Drawer Panel */}
            <motion.div
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ type: 'spring', stiffness: 300, damping: 30 }}
              className="fixed top-0 right-0 bottom-0 w-[85%] max-w-sm bg-white dark:bg-dark-surface z-[70] lg:hidden shadow-soft-xl flex flex-col border-l border-gray-100/50 dark:border-dark-border/30"
            >
              {/* Drawer Header */}
              <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-dark-border">
                <Link href="/" className="flex items-center gap-3" onClick={closeMobile}>
                  <div className="w-9 h-9 bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 rounded-xl flex items-center justify-center shadow-glow">
                    <span className="text-white font-bold text-sm font-heading">B</span>
                  </div>
                  <span className="font-bold text-gray-900 dark:text-white font-heading">Bansari Homeopathy</span>
                </Link>
                <button
                  onClick={closeMobile}
                  className="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-100 dark:bg-dark-card hover:bg-gray-200 dark:hover:bg-dark-border transition-colors"
                >
                  <svg className="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              {/* Drawer Navigation */}
              <div className="flex-1 overflow-y-auto px-4 py-4">
                <nav className="space-y-1 mb-6">
                  {navLinks.map((link, i) => (
                    <motion.div
                      key={link.href}
                      initial={{ opacity: 0, x: 20 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: i * 0.05 + 0.1 }}
                    >
                      <Link
                        href={link.href}
                        className={`flex items-center gap-3 px-4 py-3.5 rounded-xl text-[15px] font-medium transition-all duration-300 ${
                          isActive(link.href)
                            ? 'bg-primary-50/80 dark:bg-dark-accent/10 text-primary-500 dark:text-dark-accent shadow-sm'
                            : 'text-charcoal-800 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-dark-card'
                        }`}
                        onClick={closeMobile}
                      >
                        {link.label}
                        {isActive(link.href) && (
                          <div className="ml-auto w-1.5 h-1.5 bg-primary-500 dark:bg-dark-accent rounded-full" />
                        )}
                      </Link>
                    </motion.div>
                  ))}
                </nav>

                {/* CTA */}
                <motion.div
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.3 }}
                >
                  <Link
                    href="/book-appointment"
                    className="btn-primary w-full text-center flex items-center justify-center gap-2 text-[15px]"
                    onClick={closeMobile}
                  >
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
                  </Link>
                </motion.div>

                {/* Controls Row */}
                <div className="flex items-center gap-3 mt-6 px-1">
                  <button
                    onClick={() => setLang(lang === 'en' ? 'gu' : 'en')}
                    className="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gray-50 dark:bg-dark-card border border-gray-100 dark:border-dark-border transition-colors text-sm font-medium"
                  >
                    <svg className="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" />
                    </svg>
                    <span className={lang === 'en' ? 'text-primary-600 dark:text-dark-accent font-semibold' : 'text-gray-400'}>EN</span>
                    <span className="text-gray-300 dark:text-gray-600">|</span>
                    <span className={lang === 'gu' ? 'text-primary-600 dark:text-dark-accent font-semibold' : 'text-gray-400'}>ગુ</span>
                  </button>
                </div>

                {/* Patient Section */}
                {patient ? (
                  <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.35 }}
                    className="mt-6 p-4 bg-gray-50 dark:bg-dark-card rounded-2xl border border-gray-100 dark:border-dark-border"
                  >
                    <div className="flex items-center gap-3 mb-4">
                      <div className="w-11 h-11 bg-gradient-to-br from-primary-400 to-primary-600 rounded-xl flex items-center justify-center">
                        <span className="text-white font-bold text-sm">{getInitials(patient.name)}</span>
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="font-semibold text-gray-900 dark:text-gray-200 text-sm truncate">{patient.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{patient.mobile}</p>
                      </div>
                    </div>
                    <div className="space-y-2">
                      <div className="flex gap-2">
                        <Link
                          href="/profile"
                          className="flex-1 text-center py-2.5 text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-dark-border rounded-xl hover:bg-white dark:hover:bg-dark-surface font-medium transition-colors"
                          onClick={closeMobile}
                        >
                          {t('My Profile', 'મારી પ્રોફાઈલ')}
                        </Link>
                        <Link
                          href="/my-appointments"
                          className="flex-1 text-center py-2.5 text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-dark-border rounded-xl hover:bg-white dark:hover:bg-dark-surface font-medium transition-colors"
                          onClick={closeMobile}
                        >
                          {t('Appointments', 'એપોઇન્ટમેન્ટ')}
                        </Link>
                      </div>
                      <button onClick={handleLogout} className="w-full py-2.5 text-sm text-red-600 border border-red-200 dark:border-red-500/30 rounded-xl hover:bg-red-50 dark:hover:bg-red-500/10 font-medium transition-colors">
                        {t('Logout', 'લોગઆઉટ')}
                      </button>
                    </div>
                  </motion.div>
                ) : (
                  <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.35 }}
                    className="flex gap-3 mt-6"
                  >
                    <Link
                      href="/login"
                      className="flex-1 text-center py-3 text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-dark-border rounded-xl hover:bg-gray-50 dark:hover:bg-dark-card font-medium transition-colors"
                      onClick={closeMobile}
                    >
                      {t('Login', 'લોગિન')}
                    </Link>
                    <Link
                      href="/signup"
                      className="flex-1 text-center py-3 text-sm text-primary-600 dark:text-dark-accent border-2 border-primary-500 dark:border-dark-accent rounded-xl hover:bg-primary-50 dark:hover:bg-dark-accent/10 font-semibold transition-colors"
                      onClick={closeMobile}
                    >
                      {t('Sign Up', 'સાઇન અપ')}
                    </Link>
                  </motion.div>
                )}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </>
  );
}
