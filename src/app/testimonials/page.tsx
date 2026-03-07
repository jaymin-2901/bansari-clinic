'use client';

import { useState, useEffect, useRef } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { fetchTestimonials, getImageUrl } from '@/lib/api';
import { motion, useInView } from 'framer-motion';
import Lightbox from 'yet-another-react-lightbox';
import 'yet-another-react-lightbox/styles.css';

interface Testimonial {
  id: number;
  patient_name: string;
  is_anonymous: number;
  treatment_description: string;
  testimonial_text: string;
  before_image: string | null;
  after_image: string | null;
  rating: number;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || '/api/clinic';

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

/* ── Before/After Slider ── */
function BeforeAfterSlider({
  beforeSrc,
  afterSrc,
  patientName,
}: {
  beforeSrc: string;
  afterSrc: string;
  patientName: string;
}) {
  const [sliderPos, setSliderPos] = useState(50);
  const containerRef = useRef<HTMLDivElement>(null);
  const isDragging = useRef(false);

  const updateSlider = (clientX: number) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    const x = Math.min(Math.max(clientX - rect.left, 0), rect.width);
    setSliderPos((x / rect.width) * 100);
  };

  const handleMouseDown = () => { isDragging.current = true; };
  const handleMouseUp = () => { isDragging.current = false; };
  const handleMouseMove = (e: React.MouseEvent) => {
    if (isDragging.current) updateSlider(e.clientX);
  };
  const handleTouchMove = (e: React.TouchEvent) => {
    updateSlider(e.touches[0].clientX);
  };

  return (
    <div
      ref={containerRef}
      className="relative aspect-[4/3] rounded-xl overflow-hidden cursor-col-resize select-none bg-gray-100 dark:bg-dark-card"
      onMouseDown={handleMouseDown}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseUp}
      onMouseMove={handleMouseMove}
      onTouchMove={handleTouchMove}
    >
      {/* After image (full background) */}
      <Image
        src={afterSrc}
        alt={`${patientName} – After`}
        fill
        sizes="(max-width: 768px) 100vw, 50vw"
        className="object-cover"
      />
      {/* Before image (clipped) */}
      <div
        className="absolute inset-0 overflow-hidden"
        style={{ width: `${sliderPos}%` }}
      >
        <Image
          src={beforeSrc}
          alt={`${patientName} – Before`}
          fill
          sizes="(max-width: 768px) 100vw, 50vw"
          className="object-cover"
          style={{ minWidth: containerRef.current ? `${containerRef.current.offsetWidth}px` : '100%' }}
        />
      </div>
      {/* Slider line */}
      <div
        className="absolute top-0 bottom-0 w-0.5 bg-white shadow-lg z-10"
        style={{ left: `${sliderPos}%` }}
      >
        <div className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-10 h-10 bg-white rounded-full shadow-xl flex items-center justify-center">
          <svg className="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
          </svg>
        </div>
      </div>
      {/* Labels */}
      <div className="absolute top-3 left-3 bg-black/60 text-white text-xs font-bold px-2.5 py-1 rounded-md z-20 backdrop-blur-sm">BEFORE</div>
      <div className="absolute top-3 right-3 bg-primary-500/80 text-white text-xs font-bold px-2.5 py-1 rounded-md z-20 backdrop-blur-sm">AFTER</div>
    </div>
  );
}

/* ── Single image card (Before or After) ── */
function TestimonialImage({ src, alt, label, onClick }: { src: string | null; alt: string; label: string; onClick?: () => void }) {
  const [errored, setErrored] = useState(false);
  const imgUrl = getImageUrl(src);

  return (
    <div className="flex-1 min-w-0">
      <span className="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5 text-center">{label}</span>
      <div 
        className="relative aspect-[4/5] rounded-xl overflow-hidden bg-gray-100 dark:bg-dark-card group cursor-pointer"
        onClick={onClick}
      >
        {imgUrl && !errored ? (
          <img
            src={imgUrl}
            alt={alt}
            className="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105"
            onError={() => setErrored(true)}
          />
        ) : (
          <div className="w-full h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
            <svg className="w-10 h-10 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span className="text-xs font-medium">{`No ${label} Photo`}</span>
          </div>
        )}
        {/* Click to enlarge overlay */}
        <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-all duration-300 flex items-center justify-center">
          <div className="opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-white/90 dark:bg-dark-card/90 rounded-full p-2 shadow-lg">
            <svg className="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
            </svg>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ── Star rating renderer ── */
function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {Array.from({ length: 5 }, (_, i) => (
        <svg
          key={i}
          className={`w-4 h-4 ${i < rating ? 'text-amber-400' : 'text-gray-200 dark:text-gray-600'}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
    </div>
  );
}

export default function TestimonialsPage() {
  const [testimonials, setTestimonials] = useState<Testimonial[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'with-images' | 'text-only'>('all');
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxIndex, setLightboxIndex] = useState(0);
  const { t } = useLanguage();

  useEffect(() => {
    fetchTestimonials().then((data) => {
      setTestimonials(data || []);
      setLoading(false);
    }).catch(() => {
      setLoading(false);
    });
  }, []);

  // Sample fallback
  const sampleTestimonials: Testimonial[] = [
    { id: 1, patient_name: 'Rajeshbhai Patel', is_anonymous: 0, treatment_description: 'Chronic Skin Eczema – 5 years', testimonial_text: 'I had been suffering from severe eczema for 5 years. After just 3 months of treatment with Dr. Bansari, my skin has completely cleared up. Homeopathy truly works!', before_image: null, after_image: null, rating: 5 },
    { id: 2, patient_name: 'Anonymous Patient', is_anonymous: 1, treatment_description: 'Migraine & Anxiety', testimonial_text: 'My chronic migraines and anxiety have reduced significantly since starting homeopathic treatment. Dr. Patel took detailed history and prescribed the perfect remedy.', before_image: null, after_image: null, rating: 5 },
    { id: 3, patient_name: 'Priyaben Shah', is_anonymous: 0, treatment_description: 'PCOD Treatment', testimonial_text: "After years of hormonal issues, Dr. Bansari's homeopathic treatment has helped me tremendously. My cycles are regular now and I feel so much better overall.", before_image: null, after_image: null, rating: 4 },
    { id: 4, patient_name: 'Mehulbhai Desai', is_anonymous: 0, treatment_description: 'Kidney Stones', testimonial_text: 'I was advised surgery for kidney stones, but Dr. Bansari\'s homeopathic treatment dissolved them within 2 months. I am grateful beyond words!', before_image: null, after_image: null, rating: 5 },
  ];

  const displayTestimonials = testimonials.length > 0 ? testimonials : sampleTestimonials;
  const hasImages = (item: Testimonial) => !!(item.before_image || item.after_image);
  const hasBothImages = (item: Testimonial) => !!(item.before_image && item.after_image);

  const filtered = displayTestimonials.filter((item) => {
    if (filter === 'with-images') return hasImages(item);
    if (filter === 'text-only') return !hasImages(item);
    return true;
  });

  const withImagesCount = displayTestimonials.filter(hasImages).length;

  /* ── Deduplicated grid items ── */
  // IDs already rendered in the featured Before/After section
  const featuredIds = new Set(
    filter !== 'text-only' ? displayTestimonials.filter(hasBothImages).map((t) => t.id) : []
  );
  // Grid shows only testimonials NOT already in the featured slider section
  const gridItems = filtered.filter((item) => !featuredIds.has(item.id));

  return (
    <>
      {/* ═══ Hero ═══ */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primary-50 via-white to-teal-50/30 dark:from-dark-bg dark:via-dark-surface dark:to-dark-bg py-16 md:py-24">
        {/* Decorative blobs */}
        <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-primary-100/30 dark:bg-teal-900/10 rounded-full blur-3xl -translate-y-1/3 translate-x-1/4 pointer-events-none" />
        <div className="absolute bottom-0 left-0 w-[300px] h-[300px] bg-teal-100/20 dark:bg-teal-300/5 rounded-full blur-3xl translate-y-1/3 -translate-x-1/4 pointer-events-none" />

        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 bg-primary-100/80 dark:bg-teal-300/15 text-primary-700 dark:text-teal-300 rounded-full text-sm font-medium mb-6 backdrop-blur-sm"
          >
            <span className="w-2 h-2 bg-amber-400 rounded-full animate-pulse" />
            {t('Real Results', 'વાસ્તવિક પરિણામો')}
          </motion.div>

          <motion.h1
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.1 }}
            className="text-4xl md:text-5xl font-bold font-heading text-gray-900 dark:text-white mb-4 tracking-tight"
          >
            {t('Patient Testimonials', 'દર્દીઓના પ્રશંસાપત્ર')}
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.2 }}
            className="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10"
          >
            {t('Real results, real stories. See how homeopathy has transformed our patients\' lives.', 'વાસ્તવિક પરિણામો, વાસ્તવિક વાર્તાઓ. જુઓ કેવી રીતે હોમિયોપેથીએ અમારા દર્દીઓના જીવનને બદલી નાખ્યું.')}
          </motion.p>

          {/* Stats */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.3 }}
            className="flex justify-center gap-6 sm:gap-10 mb-10"
          >
            {[
              { value: displayTestimonials.length, label: t('Happy Patients', 'ખુશ દર્દીઓ') },
              { value: withImagesCount, label: t('Before/After Cases', 'બિફોર/આફ્ટર કેસ') },
              { value: '4.9', label: t('Average Rating', 'સરેરાશ રેટિંગ') },
            ].map((stat, i) => (
              <div key={i} className="text-center">
                <p className="text-3xl sm:text-4xl font-bold font-heading text-primary-500 dark:text-teal-300">{stat.value}</p>
                <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">{stat.label}</p>
              </div>
            ))}
          </motion.div>

          {/* Filter pills */}
          {withImagesCount > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.4, delay: 0.4 }}
              className="flex justify-center gap-2"
            >
              {(['all', 'with-images', 'text-only'] as const).map((f) => (
                <button
                  key={f}
                  onClick={() => setFilter(f)}
                  className={`px-5 py-2 rounded-full text-sm font-medium transition-all duration-200 ${
                    filter === f
                      ? 'bg-primary-500 dark:bg-teal-300 text-white dark:text-dark-bg shadow-md shadow-primary-500/25 dark:shadow-teal-300/25 scale-105'
                      : 'bg-white dark:bg-dark-card text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-dark-surface shadow-sm'
                  }`}
                >
                  {f === 'all' ? t('All', 'બધા') : f === 'with-images' ? t('With Photos', 'ફોટો સાથે') : t('Text Only', 'ટેક્સ્ટ')}
                </button>
              ))}
            </motion.div>
          )}
        </div>
      </section>

      {/* ═══ Featured Before/After (if any have both images) ═══ */}
      {displayTestimonials.some(hasBothImages) && filter !== 'text-only' && (
        <section className="py-12 md:py-16 bg-gray-50 dark:bg-dark-bg">
          <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <FadeIn>
              <h2 className="text-2xl md:text-3xl font-bold font-heading text-center text-gray-900 dark:text-white mb-3 tracking-tight">
                {t('Before & After Results', 'બિફોર અને આફ્ટર પરિણામો')}
              </h2>
              <p className="text-center text-gray-500 dark:text-gray-400 mb-10 max-w-lg mx-auto">
                {t('Drag the slider to compare before and after treatment results.', 'સારવાર પહેલા અને પછીના પરિણામોની સરખામણી કરવા સ્લાઇડર ખેંચો.')}
              </p>
            </FadeIn>

            <div className="grid md:grid-cols-2 gap-8">
              {displayTestimonials.filter(hasBothImages).map((item, idx) => (
                <FadeIn key={`featured-${item.id}`} delay={idx * 0.1}>
                  <div className="bg-white dark:bg-dark-card rounded-2xl shadow-soft hover:shadow-soft-lg transition-shadow duration-300 overflow-hidden">
                    <div className="p-4 sm:p-5">
                      <BeforeAfterSlider
                        beforeSrc={getImageUrl(item.before_image)!}
                        afterSrc={getImageUrl(item.after_image)!}
                        patientName={item.patient_name}
                      />
                    </div>
                    <div className="px-5 pb-5 sm:px-6 sm:pb-6">
                      <div className="flex items-center justify-between mb-3">
                        <StarRating rating={item.rating} />
                        <span className="inline-block bg-primary-50 dark:bg-teal-300/15 text-primary-700 dark:text-teal-300 text-xs font-semibold px-3 py-1 rounded-full">
                          {item.treatment_description}
                        </span>
                      </div>
                      {item.testimonial_text && (
                        <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed italic">
                          &ldquo;{item.testimonial_text}&rdquo;
                        </p>
                      )}
                      <div className="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-dark-border">
                        <div className="w-9 h-9 sm:w-10 sm:h-10 bg-gradient-to-br from-primary-400 to-teal-300 dark:from-teal-300 dark:to-primary-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm">
                          <span className="text-white font-semibold text-sm">
                            {(item.is_anonymous ? 'A' : item.patient_name.charAt(0)).toUpperCase()}
                          </span>
                        </div>
                        <div className="min-w-0">
                          <p className="font-semibold text-gray-900 dark:text-gray-200 text-sm truncate">
                            {item.is_anonymous ? t('Anonymous Patient', 'અનામી દર્દી') : item.patient_name}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <svg className="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" /></svg>
                            {t('Verified Patient', 'વેરિફાઇડ દર્દી')}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </FadeIn>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ═══ All Testimonials Grid ═══ */}
      <section className="py-12 md:py-16 bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Section heading only when featured section is also shown */}
          {featuredIds.size > 0 && gridItems.length > 0 && (
            <FadeIn className="mb-10">
              <h2 className="text-2xl md:text-3xl font-bold font-heading text-center text-gray-900 dark:text-white tracking-tight">
                {t('More Patient Stories', 'વધુ દર્દીની વાર્તાઓ')}
              </h2>
            </FadeIn>
          )}

          {loading ? (
            <div className="text-center py-16">
              <div className="inline-block w-10 h-10 border-4 border-primary-200 dark:border-dark-border border-t-primary-500 dark:border-t-teal-300 rounded-full animate-spin" />
              <p className="text-gray-500 dark:text-gray-400 mt-4">{t('Loading testimonials...', 'પ્રશંસાપત્ર લોડ થઈ રહ્યા છે...')}</p>
            </div>
          ) : gridItems.length === 0 && featuredIds.size === 0 ? (
            <div className="text-center py-16">
              <p className="text-gray-500 dark:text-gray-400">{t('No testimonials found for this filter.', 'આ ફિલ્ટર માટે કોઈ પ્રશંસાપત્ર મળ્યા નથી.')}</p>
            </div>
          ) : gridItems.length > 0 ? (
            <div className="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
              {gridItems.map((testimonial, idx) => (
                <FadeIn key={testimonial.id} delay={idx * 0.08}>
                  <TestimonialCard testimonial={testimonial} t={t} />
                </FadeIn>
              ))}
            </div>
          ) : null}
        </div>
      </section>

      {/* ═══ CTA ═══ */}
      <section className="relative overflow-hidden bg-gradient-to-r from-primary-600 to-primary-700 dark:from-charcoal-900 dark:to-navy text-white py-16 md:py-20">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.08),transparent_70%)] pointer-events-none" />
        <div className="relative z-10 max-w-4xl mx-auto text-center px-4">
          <FadeIn>
            <h2 className="text-3xl md:text-4xl font-bold font-heading mb-4 tracking-tight">{t('Start Your Healing Journey Today', 'આજે જ તમારી ઉપચાર યાત્રા શરૂ કરો')}</h2>
            <p className="text-primary-100 text-lg mb-10 max-w-2xl mx-auto">
              {t('Join thousands of satisfied patients who have found relief through homeopathy.', 'હજારો સંતુષ્ટ દર્દીઓ સાથે જોડાઓ જેમણે હોમિયોપેથી દ્વારા રાહત મેળવી છે.')}
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link href="/book-appointment" className="bg-white text-primary-600 hover:bg-primary-50 font-semibold py-3.5 px-8 rounded-xl transition-all text-lg shadow-lg shadow-black/10 hover:shadow-xl hover:-translate-y-0.5 active:scale-[0.98]">
                {t('Book Your Consultation', 'તમારું કન્સલ્ટેશન બુક કરો')}
              </Link>
              <Link href="/contact" className="border-2 border-white/80 text-white hover:bg-white hover:text-primary-600 font-semibold py-3.5 px-8 rounded-xl transition-all text-lg">
                {t('Contact Us', 'સંપર્ક કરો')}
              </Link>
            </div>
          </FadeIn>
        </div>
      </section>

      {/* Lightbox for enlarged image view */}
      <Lightbox
        open={lightboxOpen}
        close={() => setLightboxOpen(false)}
        index={lightboxIndex}
        slides={displayTestimonials
          .filter(hasImages)
          .flatMap((t) => [
            t.before_image ? { src: getImageUrl(t.before_image)!, alt: `${t.patient_name} - Before` } : null,
            t.after_image ? { src: getImageUrl(t.after_image)!, alt: `${t.patient_name} - After` } : null,
          ])
          .filter(Boolean) as { src: string; alt: string }[]}
        styles={{
          container: { backgroundColor: 'rgba(0, 0, 0, 0.95)' },
        }}
        carousel={{ preload: 2 }}
        animation={{ fade: 250, swipe: 250 }}
        controller={{ closeOnBackdropClick: true }}
      />
    </>
  );
}

/* ══════════════════════════════════════════════ */
/* ── Testimonial Card Component ── */
/* ══════════════════════════════════════════════ */
function TestimonialCard({
  testimonial,
  t,
}: {
  testimonial: Testimonial;
  t: (en: string, gu: string) => string;
}) {
  const hasImages = !!(testimonial.before_image || testimonial.after_image);

  return (
    <div className="group bg-white dark:bg-dark-card rounded-2xl shadow-soft hover:shadow-soft-lg transition-all duration-300 hover:-translate-y-1 flex flex-col h-full overflow-hidden border border-gray-100/80 dark:border-dark-border/50">
      {/* Before / After Side-by-Side images */}
      {hasImages && (
        <div className="px-5 pt-5">
          <div className="flex gap-3">
            <TestimonialImage
              src={testimonial.before_image}
              alt={`${testimonial.patient_name} – Before`}
              label="Before"
            />
            <div className="w-px bg-gray-200 dark:bg-dark-border self-stretch mt-6 flex-shrink-0" />
            <TestimonialImage
              src={testimonial.after_image}
              alt={`${testimonial.patient_name} – After`}
              label="After"
            />
          </div>
        </div>
      )}

      {/* Card body */}
      <div className="flex flex-col flex-1 p-5 sm:p-6">
        {/* Rating */}
        <div className="mb-3">
          <StarRating rating={testimonial.rating} />
        </div>

        {/* Treatment badge */}
        <span className="inline-block bg-primary-50 dark:bg-teal-300/15 text-primary-700 dark:text-teal-300 text-xs font-semibold px-3 py-1 rounded-full mb-3 self-start">
          {testimonial.treatment_description}
        </span>

        {/* Testimonial text */}
        {testimonial.testimonial_text && (
          <div className="relative flex-1 mb-4">
            <svg className="absolute -top-1 -left-1 w-6 h-6 text-primary-100 dark:text-teal-300/15" fill="currentColor" viewBox="0 0 24 24">
              <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z" />
            </svg>
            <p className="text-gray-600 dark:text-gray-400 leading-relaxed text-sm pl-5 italic">
              {testimonial.testimonial_text}
            </p>
          </div>
        )}

        {/* Patient info */}
        <div className="flex items-center gap-3 mt-auto pt-4 border-t border-gray-100 dark:border-dark-border">
          <div className="w-10 h-10 sm:w-11 sm:h-11 bg-gradient-to-br from-primary-400 to-teal-300 dark:from-teal-300 dark:to-primary-500 rounded-full flex items-center justify-center flex-shrink-0 shadow-sm">
            <span className="text-white font-semibold text-sm">
              {(testimonial.is_anonymous ? 'A' : testimonial.patient_name.charAt(0)).toUpperCase()}
            </span>
          </div>
          <div className="min-w-0">
            <p className="font-semibold text-gray-900 dark:text-gray-200 text-sm truncate">
              {testimonial.is_anonymous ? t('Anonymous Patient', 'અનામી દર્દી') : testimonial.patient_name}
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
              <svg className="w-3 h-3 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" /></svg>
              {t('Verified Patient', 'વેરિફાઇડ દર્દી')}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
