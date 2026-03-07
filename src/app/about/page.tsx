'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { useLanguage } from '@/lib/LanguageContext';
import { fetchSettings, getImageUrl, fetchClinicImages } from '@/lib/api';
import Lightbox from 'yet-another-react-lightbox';
import 'yet-another-react-lightbox/styles.css';

interface AboutSettings {
  about_doctor_name?: string;
  about_doctor_title?: string;
  about_doctor_image?: string;
  about_doctor_bio?: string;
  about_clinic_philosophy?: string;
  about_experience?: string;
  about_mission?: string;
  about_vision?: string;
  about_clinic_image?: string;
  [key: string]: string | undefined;
}

interface ClinicImage {
  id: number;
  image_path: string;
  created_at: string;
}

export default function AboutPage() {
  const { t } = useLanguage();
  const [settings, setSettings] = useState<AboutSettings>({});
  const [clinicImages, setClinicImages] = useState<ClinicImage[]>([]);
  const [loading, setLoading] = useState(true);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxIndex, setLightboxIndex] = useState(0);

  useEffect(() => {
    // Fetch both settings and clinic images in parallel
    Promise.all([
      fetchSettings('about'),
      fetchClinicImages()
    ]).then(([settingsData, images]) => {
      setSettings(settingsData);
      setClinicImages(images);
      setLoading(false);
    });
  }, []);

  const doctorName = settings.about_doctor_name || 'Dr. Bansari Patel';
  const doctorTitle = settings.about_doctor_title || 'BHMS, MD (Homeopathy)';
  const doctorBio = settings.about_doctor_bio ||
    'Dr. Bansari Patel is a dedicated homeopathic practitioner with years of experience in treating chronic and acute conditions through classical homeopathy.';
  const philosophy = settings.about_clinic_philosophy ||
    "At Bansari Homeopathy Clinic, we believe in the power of natural healing. Our approach combines classical homeopathic principles with modern diagnostic understanding.";
  const experience = settings.about_experience || '10+ Years of Experience';
  const mission = settings.about_mission ||
    'To provide gentle, effective, and lasting homeopathic treatment that improves quality of life for every patient.';
  const vision = settings.about_vision ||
    'To become the most trusted homeopathic healthcare provider, making natural healing accessible to everyone.';
  const doctorImg = getImageUrl(settings.about_doctor_image || null);
  const clinicImg = getImageUrl(settings.about_clinic_image || null);

  return (
    <>
      {/* ═══ Hero ═══ */}
      <section className="bg-gradient-to-br from-primary-50 via-white to-primary-50 dark:from-dark-bg dark:via-dark-surface dark:to-dark-bg py-16 md:py-24">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-16">
            <div className="inline-flex items-center px-4 py-1.5 bg-primary-100 dark:bg-dark-accent/15 text-primary-700 dark:text-dark-accent rounded-full text-sm font-medium mb-6">
              🌿 {t('Classical Homeopathy', 'ક્લાસિકલ હોમિયોપેથી')}
            </div>
            <h1 className="text-4xl md:text-5xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('About Us', 'અમારા વિશે')}</h1>
            <p className="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
              {t(
                'Get to know Dr. Bansari Patel and our commitment to natural healing through classical homeopathy.',
                'ડૉ. બંસરી પટેલ અને ક્લાસિકલ હોમિયોપેથી દ્વારા કુદરતી ઉપચાર પ્રત્યેની અમારી પ્રતિબદ્ધતા વિશે જાણો.'
              )}
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Doctor Profile Image */}
            <div className="order-2 md:order-1">
              {doctorImg ? (
                <div className="relative rounded-2xl overflow-hidden shadow-xl">
                  <img
                    src={doctorImg}
                    alt={doctorName}
                    className="w-full h-96 object-cover"
                    onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                  />
                  <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-6">
                    <p className="text-white font-bold text-lg">{doctorName}</p>
                    <p className="text-white/80 text-sm">{doctorTitle}</p>
                  </div>
                </div>
              ) : (
                <div className="w-full h-96 bg-gradient-to-br from-primary-200 to-primary-300 rounded-2xl flex items-center justify-center shadow-xl">
                  <div className="text-center text-primary-700">
                    <div className="w-32 h-32 bg-primary-400/50 rounded-full mx-auto mb-4 flex items-center justify-center backdrop-blur">
                      <span className="text-4xl text-white font-bold">BP</span>
                    </div>
                    <p className="text-lg font-medium">{t('Dr. Bansari Patel', 'ડૉ. બંસરી પટેલ')}</p>
                    <p className="text-sm opacity-70">{doctorTitle}</p>
                  </div>
                </div>
              )}
            </div>

            {/* Doctor Bio */}
            <div className="order-1 md:order-2">
              <div className="inline-flex items-center px-3 py-1 bg-primary-100 dark:bg-dark-accent/15 text-primary-700 dark:text-dark-accent rounded-full text-sm font-medium mb-4">
                👩‍⚕️ {t('Meet Your Doctor', 'તમારા ડોક્ટરને મળો')}
              </div>
              <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-200 mb-2">{t(doctorName, doctorName)}</h2>
              <p className="text-lg text-primary-600 dark:text-dark-accent font-medium mb-6">{doctorTitle}</p>
              <p className="text-gray-600 dark:text-gray-400 leading-relaxed mb-6">{doctorBio}</p>

              {/* Stats Row */}
              <div className="grid grid-cols-3 gap-4 mb-8">
                <div className="bg-primary-50 dark:bg-dark-card rounded-xl p-4 text-center">
                  <p className="text-2xl md:text-3xl font-bold text-primary-600 dark:text-dark-accent">{experience.replace(/[^0-9+]/g, '') || '10+'}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('Years Experience', 'વર્ષોનો અનુભવ')}</p>
                </div>
                <div className="bg-primary-50 dark:bg-dark-card rounded-xl p-4 text-center">
                  <p className="text-2xl md:text-3xl font-bold text-primary-600 dark:text-dark-accent">5000+</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('Patients Treated', 'દર્દીઓની સારવાર')}</p>
                </div>
                <div className="bg-primary-50 dark:bg-dark-card rounded-xl p-4 text-center">
                  <p className="text-2xl md:text-3xl font-bold text-primary-600 dark:text-dark-accent">98%</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{t('Satisfaction Rate', 'સંતોષ દર')}</p>
                </div>
              </div>

              <Link href="/book-appointment" className="btn-primary inline-flex items-center gap-2">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {t('Book Consultation', 'કન્સલ્ટેશન બુક કરો')}
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* ═══ Specializations ═══ */}
      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Areas of Expertise', 'નિષ્ણાત ક્ષેત્રો')}</h2>
            <p className="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
              {t('Specialized treatment through classical homeopathic principles', 'ક્લાસિકલ હોમિયોપેથિક સિદ્ધાંતો દ્વારા વિશિષ્ટ સારવાર')}
            </p>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {[
              { icon: '🧬', title: t('Chronic Diseases', 'ક્રોનિક રોગો'), desc: t('Skin, Thyroid, PCOD, Arthritis', 'ત્વચા, થાયરોઇડ, PCOD, સાંધા') },
              { icon: '🧠', title: t('Mental Health', 'માનસિક સ્વાસ્થ્ય'), desc: t('Anxiety, Depression, Insomnia', 'ચિંતા, ડિપ્રેશન, અનિદ્રા') },
              { icon: '👶', title: t('Pediatrics', 'બાળ ચિકિત્સા'), desc: t('Child allergies, Growth issues', 'બાળ એલર્જી, વૃદ્ધિ સમસ્યાઓ') },
              { icon: '🫁', title: t('Respiratory', 'શ્વસન'), desc: t('Asthma, Sinusitis, Allergies', 'અસ્થમા, સાઇનસ, એલર્જી') },
            ].map((item, i) => (
              <div key={i} className="bg-gray-50 dark:bg-dark-card rounded-xl p-6 text-center hover:shadow-md transition-all hover:-translate-y-1">
                <div className="text-4xl mb-3">{item.icon}</div>
                <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-2">{item.title}</h3>
                <p className="text-sm text-gray-600 dark:text-gray-400">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ Philosophy ═══ */}
      <section className="section-padding bg-gradient-to-br from-primary-600 to-primary-700 text-white">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-3xl md:text-4xl font-bold mb-8">{t('Our Philosophy', 'અમારી વિચારધારા')}</h2>
          <div className="bg-white/10 rounded-2xl p-8 md:p-12 backdrop-blur-sm">
            <svg className="w-12 h-12 mx-auto mb-4 text-primary-200 opacity-50" fill="currentColor" viewBox="0 0 24 24">
              <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
            </svg>
            <p className="text-lg md:text-xl leading-relaxed text-white/90 italic">
              {philosophy}
            </p>
            <p className="mt-6 text-primary-200 font-semibold text-lg">— {doctorName}</p>
          </div>
        </div>
      </section>

      {/* ═══ Mission & Vision ═══ */}
      <section className="section-padding bg-gray-50 dark:bg-dark-bg">
        <div className="max-w-7xl mx-auto">
          <div className="grid md:grid-cols-2 gap-8">
            <div className="card border-l-4 border-primary-500">
              <div className="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mb-4">
                <span className="text-2xl">🎯</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Our Mission', 'અમારું મિશન')}</h3>
              <p className="text-gray-600 dark:text-gray-400 leading-relaxed">{mission}</p>
            </div>
            <div className="card border-l-4 border-accent-500">
              <div className="w-12 h-12 bg-accent-50 rounded-lg flex items-center justify-center mb-4">
                <span className="text-2xl">🔭</span>
              </div>
              <h3 className="text-2xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Our Vision', 'અમારું વિઝન')}</h3>
              <p className="text-gray-600 dark:text-gray-400 leading-relaxed">{vision}</p>
            </div>
          </div>
        </div>
      </section>

      {/* ═══ Why Choose Us ═══ */}
      <section className="section-padding bg-white dark:bg-dark-surface">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Why Choose Us', 'અમને કેમ પસંદ કરો')}</h2>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            {[
              { icon: '✅', title: t('Detailed Case Taking', 'વિગતવાર કેસ ટેકિંગ'), desc: t('60-90 min first consultation covering physical, mental, and emotional aspects', '60-90 મિનિટનું પ્રથમ કન્સલ્ટેશન - શારીરિક, માનસિક અને ભાવનાત્મક પાસાઓ') },
              { icon: '💊', title: t('Single Remedy', 'એક જ દવા'), desc: t('Classical approach with one carefully selected remedy at a time for deeper healing', 'ઊંડા ઉપચાર માટે એક સમયે એક કાળજીપૂર્વક પસંદ કરેલી દવા') },
              { icon: '📊', title: t('Follow-up Tracking', 'ફોલો-અપ ટ્રેકિંગ'), desc: t('Regular progress monitoring with documented improvement records', 'દસ્તાવેજી સુધારણા રેકોર્ડ સાથે નિયમિત પ્રગતિ મોનિટરિંગ') },
              { icon: '🌍', title: t('Online Consultation', 'ઓનલાઇન કન્સલ્ટેશન'), desc: t('Convenient video consultations for patients anywhere in the world', 'વિશ્વમાં ગમે ત્યાંથી દર્દીઓ માટે અનુકૂળ વિડિઓ કન્સલ્ટેશન') },
              { icon: '🔒', title: t('Patient Privacy', 'દર્દી ગોપનીયતા'), desc: t('Complete confidentiality of your medical records and personal information', 'તમારા તબીબી રેકોર્ડ અને વ્યક્તિગત માહિતીની સંપૂર્ણ ગોપનીયતા') },
              { icon: '💰', title: t('Affordable Care', 'સસ્તી સંભાળ'), desc: t('Quality homeopathic treatment at reasonable consultation fees', 'વાજબી કન્સલ્ટેશન ફી પર ગુણવત્તાયુક્ત હોમિયોપેથિક સારવાર') },
            ].map((item, i) => (
              <div key={i} className="flex gap-4">
                <div className="text-2xl flex-shrink-0 mt-1">{item.icon}</div>
                <div>
                  <h3 className="font-bold text-gray-900 dark:text-gray-200 mb-1">{item.title}</h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{item.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ Clinic Images Gallery ═══ */}
      <section className="section-padding bg-gray-50 dark:bg-dark-bg">
        <div className="max-w-7xl mx-auto text-center">
          <h2 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-200 mb-4">{t('Our Clinic', 'અમારું ક્લિનિક')}</h2>
          <p className="text-lg text-gray-600 dark:text-gray-400 mb-8">{t('A warm, welcoming space designed for your comfort and healing.', 'તમારા આરામ અને ઉપચાર માટે ડિઝાઇન કરેલ ઉષ્માભર્યું, આવકારદાયક સ્થળ.')}</p>
          
          {/* Dynamic Gallery from clinic_images table */}
          {clinicImages && clinicImages.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-w-5xl mx-auto">
              {clinicImages.map((img) => (
                <div key={img.id} className="relative group overflow-hidden rounded-xl shadow-lg">
                  <img 
                    src={img.image_path} 
                    alt="Clinic Image" 
                    className="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-110"
                    onError={(e) => { 
                      (e.target as HTMLImageElement).style.display = 'none'; 
                    }}
                  />
                  <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all duration-300" />
                </div>
              ))}
            </div>
          ) : clinicImg ? (
            /* Fallback to single clinic image from settings */
            <div className="rounded-2xl overflow-hidden shadow-xl max-w-4xl mx-auto">
              <img src={clinicImg} alt="Bansari Homeopathy Clinic" className="w-full h-auto" />
            </div>
          ) : (
            /* Default placeholders when no images */
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4 max-w-4xl mx-auto">
              {[
                t('Reception Area', 'રિસેપ્શન એરિયા'),
                t('Consultation Room', 'કન્સલ્ટેશન રૂમ'),
                t('Dispensary', 'ડિસ્પેન્સરી'),
                t('Waiting Area', 'વેઇટિંગ એરિયા'),
                t('Medicine Store', 'દવા સ્ટોર'),
                t('Clinic Exterior', 'ક્લિનિક બહારનો ભાગ'),
              ].map((label, i) => (
                <div key={i} className="bg-white dark:bg-dark-card rounded-xl h-48 flex items-center justify-center shadow-sm border border-gray-100 dark:border-dark-border">
                  <div className="text-center text-gray-400">
                    <svg className="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p className="text-sm font-medium">{label}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ═══ CTA ═══ */}
      <section className="bg-primary-600 text-white py-16">
        <div className="max-w-4xl mx-auto text-center px-4">
          <h2 className="text-3xl md:text-4xl font-bold mb-4">{t('Ready to Start Healing?', 'ઉપચાર શરૂ કરવા તૈયાર છો?')}</h2>
          <p className="text-primary-100 text-lg mb-8">
            {t(
              'Book your consultation with Dr. Bansari Patel today. Both in-clinic and online appointments available.',
              'આજે જ ડો. બંસરી પટેલ સાથે તમારું કન્સલ્ટેશન બુક કરો. ક્લિનિક અને ઓનલાઇન બંને પ્રકારની એપોઇન્ટમેન્ટ ઉપલબ્ધ છે.'
            )}
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link href="/book-appointment" className="bg-white text-primary-600 hover:bg-primary-50 font-semibold py-3 px-8 rounded-lg transition-all text-lg">
              {t('Book Appointment', 'એપોઇન્ટમેન્ટ બુક કરો')}
            </Link>
            <Link href="/contact" className="border-2 border-white text-white hover:bg-white hover:text-primary-600 font-semibold py-3 px-8 rounded-lg transition-all text-lg">
              {t('Contact Us', 'સંપર્ક કરો')}
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
