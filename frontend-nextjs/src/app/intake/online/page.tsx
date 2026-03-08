'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useTranslation } from '@/i18n';
import StepProgressBar from '@/components/forms/StepProgressBar';
import MainComplaintsStep from '@/components/forms/online/MainComplaints';
import PastHistoryStep from '@/components/forms/online/PastHistory';
import FamilyHistoryStep from '@/components/forms/online/FamilyHistory';
import PhysicalGeneralsStep from '@/components/forms/online/PhysicalGenerals';
import MentalProfileStep from '@/components/forms/online/MentalProfile';
import DeclarationStep from '@/components/forms/online/Declaration';
import type { BasicInfoData, ComplaintEntry, PastDiseaseEntry, FamilyHistoryEntry, PhysicalGeneralsData, MentalProfileData, DeclarationData } from '@/types';

const STEP_KEYS = [
  'online.step1',
  'online.step2',
  'online.step3',
  'online.step4',
  'online.step5',
  'online.step6',
];

const emptyPhysicalGenerals: PhysicalGeneralsData = {
  appetite: '', thirst: '', foodCravings: '', foodAversions: '',
  stool: '', urine: '', sweat: '', sleep: '', dreams: '',
  thermalWinter: '', thermalSummer: '', thermalMonsoon: '', menstrualHistory: '',
};

const emptyMentalProfile: MentalProfileData = {
  nature: '', lifeGoal: '', anxietyTriggers: '', fears: '',
  angerExpression: '', sadnessTriggers: '', memory: '', hobbies: '',
  saddestEvent: '', happiestEvent: '', unforgettableIncident: '',
  revengeTendency: '', cryingNature: '', aloneVsCompany: '',
  relationshipImpact: '', mentalEffectOfDisease: '', socialComfort: '',
  personalityTraits: [],
};

export default function OnlineIntakePage() {
  const { tr, t } = useTranslation();
  const router = useRouter();

  const [basicInfo, setBasicInfo] = useState<BasicInfoData | null>(null);
  const [currentStep, setCurrentStep] = useState(0);
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [draftSaved, setDraftSaved] = useState(false);

  // Form state for all 6 steps
  const [complaints, setComplaints] = useState<ComplaintEntry[]>([{
    affectedArea: '', symptoms: '', sinceWhen: '', frequency: '',
    worseBy: '', betterBy: '', associatedSymptoms: '',
  }]);
  const [pastDiseases, setPastDiseases] = useState<PastDiseaseEntry[]>([]);
  const [familyHistory, setFamilyHistory] = useState<FamilyHistoryEntry[]>([]);
  const [physicalGenerals, setPhysicalGenerals] = useState<PhysicalGeneralsData>(emptyPhysicalGenerals);
  const [mentalProfile, setMentalProfile] = useState<MentalProfileData>(emptyMentalProfile);
  const [declaration, setDeclaration] = useState<DeclarationData>({ signature: '', truthConfirmation: false });

  useEffect(() => {
    const saved = sessionStorage.getItem('basicInfo');
    if (!saved) {
      router.push('/intake');
      return;
    }
    setBasicInfo(JSON.parse(saved));

    // Load draft if exists
    const draft = localStorage.getItem('onlineDraft');
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        if (parsed.complaints) setComplaints(parsed.complaints);
        if (parsed.pastDiseases) setPastDiseases(parsed.pastDiseases);
        if (parsed.familyHistory) setFamilyHistory(parsed.familyHistory);
        if (parsed.physicalGenerals) setPhysicalGenerals(parsed.physicalGenerals);
        if (parsed.mentalProfile) setMentalProfile(parsed.mentalProfile);
        if (parsed.currentStep !== undefined) setCurrentStep(parsed.currentStep);
      } catch { /* ignore invalid draft */ }
    }
  }, [router]);

  // Auto-save draft every 30 seconds
  const saveDraft = useCallback(() => {
    const draft = {
      complaints, pastDiseases, familyHistory,
      physicalGenerals, mentalProfile, currentStep,
    };
    localStorage.setItem('onlineDraft', JSON.stringify(draft));
    setDraftSaved(true);
    setTimeout(() => setDraftSaved(false), 2000);
  }, [complaints, pastDiseases, familyHistory, physicalGenerals, mentalProfile, currentStep]);

  useEffect(() => {
    const interval = setInterval(saveDraft, 30000);
    return () => clearInterval(interval);
  }, [saveDraft]);

  const handleNext = () => {
    saveDraft();
    setCurrentStep((prev) => Math.min(prev + 1, 5));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handlePrev = () => {
    setCurrentStep((prev) => Math.max(prev - 1, 0));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmit = async () => {
    if (!basicInfo) return;

    setLoading(true);
    try {
      const payload = {
        basicInfo,
        onlineForm: {
          complaints,
          pastDiseases,
          familyHistory,
          physicalGenerals,
          mentalProfile,
          declaration,
        },
        consultationType: 'ONLINE',
        formType: 'FULL',
      };

      const res = await fetch('/api/intake/online', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!res.ok) throw new Error('Failed to submit');

      localStorage.removeItem('onlineDraft');
      sessionStorage.removeItem('basicInfo');
      setSubmitted(true);
    } catch (error) {
      console.error('Submission error:', error);
      alert(t('Something went wrong. Please try again.', 'કંઈક ખોટું થયું. ફરીથી પ્રયાસ કરો.'));
    } finally {
      setLoading(false);
    }
  };

  // Success Screen
  if (submitted) {
    return (
      <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white flex items-center justify-center px-4">
        <div className="card text-center max-w-md">
          <div className="w-20 h-20 mx-auto mb-6 bg-green-100 rounded-full flex items-center justify-center">
            <svg className="w-10 h-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 className="text-2xl font-bold text-gray-900 mb-2">
            {tr('online.declaration.caseSubmitted')}
          </h2>
          <p className="text-gray-600 mb-6">
            {tr('online.declaration.caseSubmittedMsg')}
          </p>
          <button onClick={() => router.push('/')} className="btn-primary">
            {tr('common.home')}
          </button>
        </div>
      </div>
    );
  }

  if (!basicInfo) return null;

  // Step renderer
  const renderStep = () => {
    switch (currentStep) {
      case 0:
        return <MainComplaintsStep complaints={complaints} setComplaints={setComplaints} />;
      case 1:
        return <PastHistoryStep pastDiseases={pastDiseases} setPastDiseases={setPastDiseases} />;
      case 2:
        return <FamilyHistoryStep familyHistory={familyHistory} setFamilyHistory={setFamilyHistory} />;
      case 3:
        return <PhysicalGeneralsStep data={physicalGenerals} setData={setPhysicalGenerals} gender={basicInfo.gender} />;
      case 4:
        return <MentalProfileStep data={mentalProfile} setData={setMentalProfile} />;
      case 5:
        return <DeclarationStep data={declaration} setData={setDeclaration} onSubmit={handleSubmit} loading={loading} />;
      default:
        return null;
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-primary-50 to-white py-8 px-4">
      <div className="max-w-3xl mx-auto">
        {/* Header */}
        <div className="text-center mb-6">
          <button
            onClick={() => router.back()}
            className="inline-flex items-center text-primary-600 hover:text-primary-800 mb-4"
          >
            <svg className="w-5 h-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
            {tr('common.back')}
          </button>
          <div className="flex items-center justify-center gap-2 mb-2">
            <span className="text-2xl">💻</span>
            <h1 className="text-2xl md:text-3xl font-bold text-gray-900">{tr('online.title')}</h1>
          </div>
          <p className="text-gray-600 text-sm">{tr('online.subtitle')}</p>
          <div className="mt-3 inline-flex items-center px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-sm">
            📅 {basicInfo.fullName} • {basicInfo.appointmentDate}
          </div>
        </div>

        {/* Progress Bar */}
        <StepProgressBar steps={STEP_KEYS} currentStep={currentStep} />

        {/* Draft Saved Notification */}
        {draftSaved && (
          <div className="mb-4 bg-green-50 text-green-700 text-sm px-4 py-2 rounded-lg flex items-center gap-2 animate-fade-in">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
            {tr('online.draftSaved')}
          </div>
        )}

        {/* Step Content */}
        <div className="card">
          {renderStep()}
        </div>

        {/* Navigation Buttons */}
        {currentStep < 5 && (
          <div className="flex justify-between mt-6">
            <button
              onClick={handlePrev}
              disabled={currentStep === 0}
              className={`flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-all ${
                currentStep === 0
                  ? 'text-gray-400 cursor-not-allowed'
                  : 'text-primary-600 hover:bg-primary-50 border border-primary-200'
              }`}
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 17l-5-5m0 0l5-5m-5 5h12" />
              </svg>
              {tr('common.previous')}
            </button>

            <div className="flex gap-3">
              <button onClick={saveDraft} className="px-4 py-3 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium">
                {tr('online.saveDraft')}
              </button>
              <button onClick={handleNext} className="btn-primary flex items-center gap-2">
                {tr('common.next')}
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
