'use client';

import { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { fetchTestimonials, fetchSettings, getImageUrl } from '@/lib/api';
import { motion, useInView } from 'framer-motion';

/* ── Hook to detect mobile screen ── */
function useIsMobile() {
  const [isMobile, setIsMobile] = useState(false);
  
  useEffect(() => {
    // Check on mount and resize
    const checkMobile = () => {
      setIsMobile(window.innerWidth <= 768);
    };
    
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);
  
  return isMobile;
}

interface Testimonial {
  id: number;
  patient_name: string;
  is_anonymous: number;
  treatment_description: string;
  testimonial_text: string;
  rating: number;
}

interface HomeSettings {
  home_hero_title?: string;
  home_hero_subtitle?: string;
  home_hero_description?: string;
  home_hero_image?: string;
  home_hero_image_mobile?: string;
}

/* ── Reusable fade-in wrapper ── */
function FadeIn({ children, className = '', delay = 0 }: { children: React.ReactNode; className?: string; delay?: number }) {
  const ref = useRef(null);
  const isInView = useInView(ref, { once: true, margin: '-60px' });
  return (
    <motion.div
      ref={ref}
      initial={{ opacity: 0, y: 24 }}
      animate={isInView ? { opacity: 1, y: 0 } : {}}
      transition={{ duration: 0.5, delay, ease: 'easeOut' }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

export default function HomePage() {
  const { t } = useLanguage();
  const [testimonials, setTestimonials] = useState<Testimonial[]>([]);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [settings, setSettings] = useState<HomeSettings>({});
  const [loadingSettings, setLoadingSettings] = useState(true);
  const isMobile = useIsMobile();

  // Determine hero image based on screen size
  const heroImage = isMobile 
    ? (settings.home_hero_image_mobile || settings.home_hero_image)
    : settings.home_hero_image;

  useEffect(() => {
    // Fetch settings
    fetchSettings('home').then((data) => {
      setSettings(data || {});
      setLoadingSettings(false);
    });
    
    // Fetch testimonials
    fetchTestimonials().then((data) => {
      setTestimonials((data || []).slice(0, 6));
    });
  }, []);

  // Auto-rotate testimonials
  useEffect(() => {
    if (testimonials.length <= 3) return;
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % Math.ceil(testimonials.length / 3));
    }, 5000);
    return () => clearInterval(timer);
  }, [testimonials.length]);

  const renderStars = (rating: number) =>
    Array.from({ length: 5 }, (_, i) => (
      <span key={i} className={`text-sm ${i < rating ? 'text-amber-400' : 'text-gray-200 dark:text-gray-600'}`}>★</span>
    ));

  return (
    <>
      {/* ═══════════════════════════════════════════ */}
      {/* ═══ HERO SECTION ═══ */}
      {/* ═══════════════════════════════════════════ */}
      {/* 
        Hero background image logic:
        - Mobile (≤768px): Use mobile image if available, otherwise fallback to desktop image
        - Desktop (>768px): Use desktop image 
      */}
      <section 
        className="relative overflow-hidden"
        style={heroImage ? {
          backgroundImage: `url(${getImageUrl(heroImage) || ''})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        } : undefined}
      >
        {/* Background overlay - always present for text readability */}
        <div className="absolute inset-0 bg-gradient-to-br from-primary-50/95 via-white/95 to-teal-50/95 dark:from-dark-bg/95 dark:via-dark-surface/95 dark:to-dark-bg/95" />
        
        {/* Decorative blobs - only show when no hero image */}
        {!(isMobile ? (settings.home_hero_image_mobile || settings.home_hero_image) : settings.home_hero_image) && (
          <>
            <div className="absolute top-10 right-0 w-[500px] h-[500px] bg-primary-100/30 dark:bg-teal-900/10 rounded-full blur-3xl -translate-y-1/3 translate-x-1/4 pointer-events-none animate-float" />
            <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-teal-100/20 dark:bg-teal-300/5 rounded-full blur-3xl translate-y-1/3 -translate-x-1/4 pointer-events-none" />
          </>
        )}

        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 md:py-28 lg:py-36">
          <div className="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            {/* Left content */}
            <div>
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                className="inline-flex items-center gap-2 px-4 py-2 bg-primary-100/80 dark:bg-teal-300/15 text-primary-700 dark:text-teal-300 rounded-full text-sm font-medium mb-6 backdrop-blur-sm"
              >
                <span className="w-2 h-2 bg-primary-500 dark:bg-teal-300 rounded-full animate-pulse" />
                {t('Classical Homeopathy', 'ક્લાસિકલ હોમિયોપેથી')}
              </motion.div>

              <motion.h1
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.1 }}
                className="text-4xl sm:text-5xl lg:text-6xl font-bold font-heading text-gray-900 dark:text-white leading-[1.1] tracking-tight mb-6"
              >
                {settings.home_hero_title || t('Gentle Healing,', 'હળવા ઉપચાર,')}{' '}
                <span className="text-primary-500 dark:text-teal-300">
                  {settings.home_hero_subtitle || (settings.home_hero_title ? '' : t('Lasting Results', 'કાયમી પરિણામો'))}
                </span>
              </motion.h1>

              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.2 }}
                className="text-base sm:text-lg lg:text-xl text-gray-600 dark:text-gray-400 mb-8 leading-relaxed max-w-xl"
              >
                {settings.home_hero_description || (settings.home_hero_title ? '' : t(
                  'Experience personalized homeopathic treatment with Dr. Bansari Patel. Holistic care for chronic and acute conditions, treating mind, body and spirit.',
                  'ડૉ. બંસરી પટેલ સાથે વ્યક્તિગત હોમિયોપેથિક સારવારનો અનુભવ કરો. ક્રોનિક અને એક્યુટ રોગો માટે મન, શરીર અને આત્માની સંપૂર્ણ સંભાળ.'
                ))}
              </motion.p>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.3 }}
                className="flex flex-col sm:flex-row gap-3 sm:gap-4"
              >
                <Link href="/book-appointment" className="btn-primary text-center text-base sm:text-lg px-8 py-3.5 flex items-center justify-center gap-2">
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
                </Link>
                <Link href="/about" className="btn-outline text-center text-base sm:text-lg px-8 py-3.5">
                  {t('Learn More', 'વધુ જાણો')}
                </Link>
              </motion.div>

              {/* Quick stats */}
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.5 }}
                className="flex flex-wrap gap-6 sm:gap-8 mt-10 pt-8 border-t border-gray-200/60 dark:border-dark-border"
              >
                {[
                  { value: '10+', label: t('Years Experience', 'વર્ષોનો અનુભવ') },
                  { value: '5000+', label: t('Patients Treated', 'દર્દીઓની સારવાર') },
                  { value: '4.9★', label: t('Patient Rating', 'દર્દી રેટિંગ') },
                ].map((stat) => (
                  <div key={stat.label}>
                    <p className="text-2xl sm:text-3xl font-bold font-heading text-primary-500 dark:text-teal-300">{stat.value}</p>
                    <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">{stat.label}</p>
                  </div>
                ))}
              </motion.div>
            </div>

            {/* Right visual */}
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="relative hidden lg:block"
            >
              <div className="relative w-full aspect-[4/5] max-w-md mx-auto">
                {/* Main card */}
                <div className="absolute inset-0 bg-gradient-to-br from-primary-400/90 to-primary-600 rounded-3xl shadow-soft-xl overflow-hidden">
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_40%,rgba(255,255,255,0.12),transparent)]" />
                  <div className="flex flex-col items-center justify-center h-full p-8 text-white">
                    <div className="w-20 h-20 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center mb-6 shadow-glow-lg">
                      <svg className="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                      </svg>
                    </div>
                    <h3 className="text-2xl font-bold font-heading mb-1">Dr. Bansari Patel</h3>
                    <p className="text-primary-100 text-sm mb-4">BHMS, MD (Homeopathy)</p>
                    <div className="w-16 h-px bg-white/30 mb-4" />
                    <p className="text-primary-100 text-center text-sm leading-relaxed max-w-[240px]">
                      {t('Dedicated to gentle, holistic healthcare through classical homeopathy', 'ક્લાસિકલ હોમિયોપેથી દ્વારા હળવી, સંપૂર્ણ આરોગ્ય સેવા માટે સમર્પિત')}
                    </p>
                  </div>
                </div>

                {/* Floating badge */}
                <div className="absolute -bottom-4 -left-4 bg-white dark:bg-dark-card rounded-2xl shadow-soft-lg p-4 flex items-center gap-3 border border-gray-100 dark:border-dark-border">
                  <div className="w-10 h-10 bg-green-100 dark:bg-green-500/20 rounded-xl flex items-center justify-center">
                    <svg className="w-5 h-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-gray-900 dark:text-white">{t('Trusted Care', 'વિશ્વસનીય સંભાળ')}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{t('10+ years of practice', '10+ વર્ષનો અનુભવ')}</p>
                  </div>
                </div>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ SERVICES SECTION ═══ */}
      {/* ═══════════════════════════════════════════ */}
      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto">
          <FadeIn className="text-center mb-12 md:mb-16">
            <p className="text-sm font-semibold text-primary-500 dark:text-teal-300 uppercase tracking-wider mb-3">
              {t('What We Offer', 'અમે શું આપીએ છીએ')}
            </p>
            <h2 className="section-heading mb-4 font-heading">
              {t('Our Services', 'અમારી સેવાઓ')}
            </h2>
            <p className="section-subheading">
              {t('Comprehensive homeopathic care for the whole family', 'સમગ્ર પરિવાર માટે સંપૂર્ણ હોમિયોપેથિક સંભાળ')}
            </p>
          </FadeIn>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
            {[
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                ),
                title: t('In-Clinic Visit', 'ક્લિનિક મુલાકાત'),
                desc: t('Face-to-face consultation with detailed case taking', 'વિગતવાર કેસ ટેકિંગ સાથે સામ-સામે કન્સલ્ટેશન'),
                link: '/book-appointment',
                color: 'from-blue-500 to-blue-600',
                bg: 'bg-blue-50 dark:bg-blue-500/10',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                ),
                title: t('Online Consultation', 'ઓનલાઇન કન્સલ્ટેશન'),
                desc: t('Video consultations from the comfort of your home', 'તમારા ઘરેથી જ વિડિઓ કન્સલ્ટેશન'),
                link: '/book-appointment',
                color: 'from-violet-500 to-violet-600',
                bg: 'bg-violet-50 dark:bg-violet-500/10',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                  </svg>
                ),
                title: t('Follow-up Care', 'ફોલો-અપ સંભાળ'),
                desc: t('Regular monitoring and remedy adjustments', 'નિયમિત મોનિટરિંગ અને દવા ગોઠવણ'),
                link: '/book-appointment',
                color: 'from-emerald-500 to-emerald-600',
                bg: 'bg-emerald-50 dark:bg-emerald-500/10',
              },
              {
                icon: (
                  <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                ),
                title: t('Chronic Treatment', 'ક્રોનિક સારવાર'),
                desc: t('Long-term treatment plans for chronic conditions', 'ક્રોનિક રોગો માટે લાંબા ગાળાની સારવાર યોજના'),
                link: '/about',
                color: 'from-rose-500 to-rose-600',
                bg: 'bg-rose-50 dark:bg-rose-500/10',
              },
            ].map((service, i) => (
              <FadeIn key={i} delay={i * 0.1}>
                <Link
                  href={service.link}
                  className="group block card h-full border border-gray-100 dark:border-dark-border hover:-translate-y-1 hover:shadow-soft-lg transition-all duration-300"
                >
                  <div className={`w-14 h-14 ${service.bg} rounded-2xl flex items-center justify-center mb-5 group-hover:scale-105 transition-transform duration-300`}>
                    <div className={`bg-gradient-to-br ${service.color} bg-clip-text text-transparent`}>
                      {service.icon}
                    </div>
                  </div>
                  <h3 className="text-lg font-bold font-heading text-gray-900 dark:text-white mb-2 group-hover:text-primary-500 dark:group-hover:text-teal-300 transition-colors">
                    {service.title}
                  </h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{service.desc}</p>
                  <div className="mt-4 flex items-center gap-1 text-sm font-medium text-primary-500 dark:text-teal-300 opacity-0 group-hover:opacity-100 transition-opacity">
                    {t('Learn more', 'વધુ જાણો')}
                    <svg className="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                </Link>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ WHY HOMEOPATHY ═══ */}
      {/* ═══════════════════════════════════════════ */}
      <section className="section-padding bg-gray-50 dark:bg-dark-bg">
        <div className="max-w-7xl mx-auto">
          <FadeIn className="text-center mb-12 md:mb-16">
            <p className="text-sm font-semibold text-primary-500 dark:text-teal-300 uppercase tracking-wider mb-3">
              {t('Why Choose Us', 'અમને કેમ પસંદ કરો')}
            </p>
            <h2 className="section-heading mb-4 font-heading">
              {t('Why Choose Homeopathy?', 'હોમિયોપેથી કેમ પસંદ કરો?')}
            </h2>
            <p className="section-subheading">
              {t(
                'Homeopathy offers a natural, holistic approach to healthcare that treats the root cause of your condition.',
                'હોમિયોપેથી તમારી બીમારીના મૂળ કારણની સારવાર કરતી કુદરતી, સંપૂર્ણ આરોગ્ય સેવા આપે છે.'
              )}
            </p>
          </FadeIn>

          <div className="grid md:grid-cols-3 gap-6 md:gap-8">
            {[
              {
                icon: (
                  <svg className="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                ),
                title: t('Natural Healing', 'કુદરતી ઉપચાર'),
                desc: t('Safe, gentle remedies derived from natural substances with no side effects. Suitable for all ages including children and elderly.', 'કુદરતી પદાર્થોમાંથી બનેલી સુરક્ષિત, હળવી દવાઓ જેની કોઈ આડઅસર નથી. બાળકો અને વૃદ્ધો સહિત બધી ઉંમર માટે યોગ્ય.'),
                bg: 'bg-emerald-50 dark:bg-emerald-500/10',
              },
              {
                icon: (
                  <svg className="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                ),
                title: t('Holistic Approach', 'સંપૂર્ણ અભિગમ'),
                desc: t('We treat the whole person — mind, body, and emotions — not just isolated symptoms. This leads to deeper, lasting healing.', 'અમે સંપૂર્ણ વ્યક્તિની સારવાર કરીએ છીએ — મન, શરીર અને લાગણીઓ. આ ગહન અને કાયમી ઉપચાર તરફ દોરી જાય છે.'),
                bg: 'bg-blue-50 dark:bg-blue-500/10',
              },
              {
                icon: (
                  <svg className="w-8 h-8 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                ),
                title: t('Personalized Care', 'વ્યક્તિગત સંભાળ'),
                desc: t('Every patient receives an individualized treatment plan based on their unique constitution, symptoms, and medical history.', 'દરેક દર્દીને તેમની અનોખી પ્રકૃતિ, લક્ષણો અને તબીબી ઇતિહાસના આધારે વ્યક્તિગત સારવાર યોજના મળે છે.'),
                bg: 'bg-violet-50 dark:bg-violet-500/10',
              },
            ].map((feature, i) => (
              <FadeIn key={i} delay={i * 0.1}>
                <div className="card text-center group hover:-translate-y-1 transition-all duration-300 h-full">
                  <div className={`w-16 h-16 ${feature.bg} rounded-2xl flex items-center justify-center mx-auto mb-5 group-hover:scale-105 transition-transform`}>
                    {feature.icon}
                  </div>
                  <h3 className="text-xl font-bold font-heading text-gray-900 dark:text-white mb-3">{feature.title}</h3>
                  <p className="text-gray-600 dark:text-gray-400 leading-relaxed">{feature.desc}</p>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ CONDITIONS WE TREAT ═══ */}
      {/* ═══════════════════════════════════════════ */}
      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto">
          <FadeIn className="text-center mb-12 md:mb-16">
            <p className="text-sm font-semibold text-primary-500 dark:text-teal-300 uppercase tracking-wider mb-3">
              {t('Specializations', 'વિશેષતાઓ')}
            </p>
            <h2 className="section-heading mb-4 font-heading">
              {t('Conditions We Treat', 'અમે સારવાર કરીએ છીએ')}
            </h2>
            <p className="section-subheading">
              {t(
                'Dr. Bansari Patel specializes in treating a wide range of conditions through classical homeopathy.',
                'ડો. બંસરી પટેલ ક્લાસિકલ હોમિયોપેથી દ્વારા વિવિધ રોગોની સારવારમાં માહિર છે.'
              )}
            </p>
          </FadeIn>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
            {[
              { icon: '🩺', label: t('Skin Diseases', 'ચામડીના રોગો') },
              { icon: '🤧', label: t('Allergies', 'એલર્જી') },
              { icon: '🫁', label: t('Digestive Issues', 'પાચનની સમસ્યા') },
              { icon: '💨', label: t('Respiratory Problems', 'શ્વાસની સમસ્યા') },
              { icon: '🦴', label: t('Joint Pain', 'સાંધાનો દુખાવો') },
              { icon: '🦋', label: t('Thyroid Disorders', 'થાયરોઇડ રોગ') },
              { icon: '💇', label: t('Hair Loss', 'વાળ ખરવા') },
              { icon: '🧠', label: t('Anxiety & Depression', 'ચિંતા અને ડિપ્રેશન') },
              { icon: '🤕', label: t('Migraine', 'માઇગ્રેન') },
              { icon: '🩸', label: t('PCOD/PCOS', 'PCOD/PCOS') },
              { icon: '💎', label: t('Kidney Stones', 'કિડની પથરી') },
              { icon: '🛡️', label: t('Autoimmune Disorders', 'ઓટોઇમ્યૂન રોગ') },
            ].map((condition, i) => (
              <FadeIn key={i} delay={i * 0.03}>
                <div className="bg-gray-50 dark:bg-dark-card rounded-2xl p-4 md:p-5 text-center hover:shadow-soft hover:bg-white dark:hover:bg-dark-surface transition-all duration-300 group border border-transparent hover:border-gray-100 dark:hover:border-dark-border cursor-default">
                  <span className="text-2xl md:text-3xl block mb-2 group-hover:scale-110 transition-transform duration-300">{condition.icon}</span>
                  <p className="font-medium text-gray-800 dark:text-gray-200 text-sm">{condition.label}</p>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ TESTIMONIALS ═══ */}
      {/* ═══════════════════════════════════════════ */}
      {testimonials.length > 0 && (
        <section className="section-padding bg-gray-50 dark:bg-dark-bg">
          <div className="max-w-7xl mx-auto">
            <FadeIn className="text-center mb-12 md:mb-16">
              <p className="text-sm font-semibold text-primary-500 dark:text-teal-300 uppercase tracking-wider mb-3">
                {t('Patient Stories', 'દર્દીઓની વાર્તાઓ')}
              </p>
              <h2 className="section-heading mb-4 font-heading">
                {t('What Our Patients Say', 'અમારા દર્દીઓ શું કહે છે')}
              </h2>
            </FadeIn>

            <div className="grid md:grid-cols-3 gap-6">
              {testimonials.slice(0, 3).map((item, i) => (
                <FadeIn key={item.id} delay={i * 0.1}>
                  <div className="card h-full flex flex-col">
                    {/* Quote mark */}
                    <svg className="w-8 h-8 text-primary-200 dark:text-teal-300/20 mb-3" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609L9.978 5.151c-2.432.917-3.995 3.638-3.995 5.849H10v10H0z" />
                    </svg>
                    <div className="mb-3">{renderStars(item.rating)}</div>
                    <span className="inline-block self-start bg-primary-50 dark:bg-teal-300/10 text-primary-700 dark:text-teal-300 text-xs font-medium px-3 py-1 rounded-full mb-3">
                      {item.treatment_description}
                    </span>
                    {item.testimonial_text && (
                      <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed italic mb-4 flex-1 line-clamp-4">
                        &ldquo;{item.testimonial_text}&rdquo;
                      </p>
                    )}
                    <div className="flex items-center gap-3 mt-auto pt-4 border-t border-gray-100 dark:border-dark-border">
                      <div className="w-9 h-9 bg-gradient-to-br from-primary-400 to-primary-600 rounded-xl flex items-center justify-center">
                        <span className="text-white font-semibold text-xs">{item.patient_name.charAt(0)}</span>
                      </div>
                      <p className="font-semibold text-gray-900 dark:text-white text-sm">
                        {item.is_anonymous ? t('Anonymous', 'અનામી') : item.patient_name}
                      </p>
                    </div>
                  </div>
                </FadeIn>
              ))}
            </div>

            <FadeIn className="text-center mt-10">
              <Link href="/testimonials" className="btn-outline inline-flex items-center gap-2">
                {t('View All Testimonials', 'બધા પ્રશંસાપત્ર જુઓ')}
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </FadeIn>
          </div>
        </section>
      )}

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ HOW IT WORKS ═══ */}
      {/* ═══════════════════════════════════════════ */}
      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto">
          <FadeIn className="text-center mb-12 md:mb-16">
            <p className="text-sm font-semibold text-primary-500 dark:text-teal-300 uppercase tracking-wider mb-3">
              {t('Simple Process', 'સરળ પ્રક્રિયા')}
            </p>
            <h2 className="section-heading mb-4 font-heading">
              {t('How It Works', 'કેવી રીતે કામ કરે છે')}
            </h2>
          </FadeIn>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
            {[
              {
                step: '1',
                icon: (
                    <svg className="w-7 h-7 text-primary-500 dark:text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                ),
                title: t('Book Online', 'ઓનલાઇન બુક કરો'),
                desc: t('Choose your date, time & consultation type', 'તારીખ, સમય અને કન્સલ્ટેશન પ્રકાર પસંદ કરો'),
              },
              {
                step: '2',
                icon: (
                    <svg className="w-7 h-7 text-primary-500 dark:text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                ),
                title: t('Fill Form', 'ફોર્મ ભરો'),
                desc: t('Complete your medical history form before visit', 'મુલાકાત પહેલાં તમારો તબીબી ઇતિહાસ ફોર્મ ભરો'),
              },
              {
                step: '3',
                icon: (
                    <svg className="w-7 h-7 text-primary-500 dark:text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                  </svg>
                ),
                title: t('Consultation', 'કન્સલ્ટેશન'),
                desc: t('Detailed case taking & remedy selection', 'વિગતવાર કેસ ટેકિંગ અને દવા પસંદગી'),
              },
              {
                step: '4',
                icon: (
                    <svg className="w-7 h-7 text-primary-500 dark:text-teal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                  </svg>
                ),
                title: t('Get Treatment', 'સારવાર મેળવો'),
                desc: t('Receive personalized remedy & follow-up plan', 'વ્યક્તિગત દવા અને ફોલો-અપ યોજના'),
              },
            ].map((item, i) => (
              <FadeIn key={i} delay={i * 0.1}>
                <div className="text-center relative group">
                  {/* Connector line */}
                  {i < 3 && (
                    <div className="hidden lg:block absolute top-10 left-[60%] w-[80%] border-t-2 border-dashed border-primary-200 dark:border-dark-border/50" />
                  )}
                  <div className="relative z-10">
                    <div className="w-20 h-20 bg-primary-50 dark:bg-teal-300/10 rounded-2xl flex items-center justify-center mx-auto mb-5 group-hover:scale-105 transition-transform duration-300 border border-primary-100 dark:border-teal-300/20">
                      {item.icon}
                    </div>
                    <div className="inline-flex items-center justify-center w-7 h-7 bg-primary-500 dark:bg-teal-300 text-white dark:text-dark-bg text-xs font-bold rounded-full mb-3">
                      {item.step}
                    </div>
                  </div>
                  <h3 className="font-bold font-heading text-gray-900 dark:text-white mb-2 text-lg">{item.title}</h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{item.desc}</p>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════ */}
      {/* ═══ CTA SECTION ═══ */}
      {/* ═══════════════════════════════════════════ */}
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 dark:from-dark-surface dark:via-dark-bg dark:to-navy" />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.08),transparent)]" />

        <div className="relative z-10 max-w-4xl mx-auto text-center section-padding">
          <FadeIn>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-bold font-heading text-white mb-6 tracking-tight">
              {t('Ready to Start Your Healing Journey?', 'તમારી ઉપચાર યાત્રા શરૂ કરવા તૈયાર છો?')}
            </h2>
            <p className="text-lg sm:text-xl text-primary-100 dark:text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
              {t(
                'Book your consultation with Dr. Bansari Patel today. Available for both in-clinic and online consultations.',
                'આજે જ ડો. બંસરી પટેલ સાથે તમારું કન્સલ્ટેશન બુક કરો. ક્લિનિક અને ઓનલાઇન બંને ઉપલબ્ધ.'
              )}
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link
                href="/book-appointment"
                className="bg-white text-primary-700 hover:bg-primary-50 font-semibold py-3.5 px-8 rounded-xl transition-all text-lg shadow-soft-lg hover:shadow-soft-xl active:scale-[0.98] flex items-center justify-center gap-2"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
              </Link>
              <Link
                href="/contact"
                className="border-2 border-white/30 text-white hover:bg-white/10 font-semibold py-3.5 px-8 rounded-xl transition-all text-lg active:scale-[0.98]"
              >
                {t('Contact Us', 'સંપર્ક કરો')}
              </Link>
            </div>
          </FadeIn>
        </div>
      </section>
    </>
  );
}
