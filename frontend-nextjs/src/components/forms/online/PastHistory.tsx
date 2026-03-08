'use client';

import { useState } from 'react';
import { useTranslation } from '@/i18n';
import type { PastDiseaseEntry } from '@/types';
import { DISEASE_CATEGORIES } from '@/types';

interface Props {
  pastDiseases: PastDiseaseEntry[];
  setPastDiseases: React.Dispatch<React.SetStateAction<PastDiseaseEntry[]>>;
}

const categoryKeys: Record<string, string> = {
  respiratory: 'online.pastHistory.respiratory',
  skin: 'online.pastHistory.skin',
  fever: 'online.pastHistory.fever',
  surgery: 'online.pastHistory.surgery',
};

const diseaseTranslationKey: Record<string, string> = {
  Asthma: 'online.pastHistory.asthma',
  Tuberculosis: 'online.pastHistory.tuberculosis',
  Pneumonia: 'online.pastHistory.pneumonia',
  Bronchitis: 'online.pastHistory.bronchitis',
  Tonsillitis: 'online.pastHistory.tonsillitis',
  Measles: 'online.pastHistory.measles',
  Chickenpox: 'online.pastHistory.chickenpox',
  Psoriasis: 'online.pastHistory.psoriasis',
  Eczema: 'online.pastHistory.eczema',
  Allergy: 'online.pastHistory.allergy',
  Dengue: 'online.pastHistory.dengue',
  Malaria: 'online.pastHistory.malaria',
  Hepatitis: 'online.pastHistory.hepatitis',
  Appendix: 'online.pastHistory.appendix',
  Hernia: 'online.pastHistory.hernia',
  'Kidney Stone': 'online.pastHistory.kidneyStone',
};

export default function PastHistoryStep({ pastDiseases, setPastDiseases }: Props) {
  const { tr } = useTranslation();
  const [selectedDiseases, setSelectedDiseases] = useState<Set<string>>(
    new Set(pastDiseases.map((d) => `${d.category}:${d.diseaseName}`))
  );

  const toggleDisease = (category: string, disease: string) => {
    const key = `${category}:${disease}`;
    setSelectedDiseases((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
        setPastDiseases((pd) => pd.filter((d) => !(d.category === category && d.diseaseName === disease)));
      } else {
        next.add(key);
        setPastDiseases((pd) => [...pd, {
          category, diseaseName: disease, treatmentTaken: '',
          yearsAgo: null, duration: '', residualSymptoms: '',
        }]);
      }
      return next;
    });
  };

  const updateDisease = (category: string, disease: string, field: keyof PastDiseaseEntry, value: string | number | null) => {
    setPastDiseases((prev) =>
      prev.map((d) =>
        d.category === category && d.diseaseName === disease
          ? { ...d, [field]: value }
          : d
      )
    );
  };

  const getDiseaseData = (category: string, disease: string) =>
    pastDiseases.find((d) => d.category === category && d.diseaseName === disease);

  const categoryEmojis: Record<string, string> = {
    respiratory: '🫁',
    skin: '🩹',
    fever: '🤒',
    surgery: '🏥',
  };

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.pastHistory.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.pastHistory.subtitle')}</p>

      <div className="space-y-6">
        {Object.entries(DISEASE_CATEGORIES).map(([category, diseases]) => (
          <div key={category} className="bg-gray-50 rounded-xl p-5 border border-gray-100">
            <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
              <span>{categoryEmojis[category]}</span>
              {tr(categoryKeys[category])}
            </h3>

            {/* Disease Checkboxes */}
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
              {diseases.map((disease) => {
                const isSelected = selectedDiseases.has(`${category}:${disease}`);
                return (
                  <label
                    key={disease}
                    className={`flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 cursor-pointer transition-all text-sm ${
                      isSelected
                        ? 'border-primary-500 bg-primary-50 text-primary-700'
                        : 'border-gray-200 bg-white hover:border-gray-300'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={isSelected}
                      onChange={() => toggleDisease(category, disease)}
                      className="sr-only"
                    />
                    <span className={`w-4 h-4 rounded flex items-center justify-center border-2 flex-shrink-0 ${
                      isSelected ? 'bg-primary-500 border-primary-500 text-white' : 'border-gray-300'
                    }`}>
                      {isSelected && (
                        <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                      )}
                    </span>
                    {tr(diseaseTranslationKey[disease] || disease)}
                  </label>
                );
              })}
            </div>

            {/* Dynamic Detail Fields for Selected Diseases */}
            {diseases
              .filter((d) => selectedDiseases.has(`${category}:${d}`))
              .map((disease) => {
                const data = getDiseaseData(category, disease);
                if (!data) return null;
                return (
                  <div key={disease} className="mt-4 bg-white rounded-lg p-4 border border-primary-100">
                    <h4 className="text-sm font-semibold text-primary-700 mb-3">
                      📋 {tr(diseaseTranslationKey[disease] || disease)}
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label className="label-text text-xs">{tr('online.pastHistory.treatmentTaken')}</label>
                        <input
                          type="text"
                          className="input-field text-sm"
                          placeholder={tr('online.pastHistory.treatmentPlaceholder')}
                          value={data.treatmentTaken}
                          onChange={(e) => updateDisease(category, disease, 'treatmentTaken', e.target.value)}
                        />
                      </div>
                      <div>
                        <label className="label-text text-xs">{tr('online.pastHistory.yearsAgo')}</label>
                        <input
                          type="number"
                          min={0}
                          className="input-field text-sm"
                          value={data.yearsAgo ?? ''}
                          onChange={(e) => updateDisease(category, disease, 'yearsAgo', e.target.value ? parseInt(e.target.value) : null)}
                        />
                      </div>
                      <div>
                        <label className="label-text text-xs">{tr('online.pastHistory.duration')}</label>
                        <input
                          type="text"
                          className="input-field text-sm"
                          placeholder={tr('online.pastHistory.durationPlaceholder')}
                          value={data.duration}
                          onChange={(e) => updateDisease(category, disease, 'duration', e.target.value)}
                        />
                      </div>
                      <div>
                        <label className="label-text text-xs">{tr('online.pastHistory.residualSymptoms')}</label>
                        <input
                          type="text"
                          className="input-field text-sm"
                          placeholder={tr('online.pastHistory.residualPlaceholder')}
                          value={data.residualSymptoms}
                          onChange={(e) => updateDisease(category, disease, 'residualSymptoms', e.target.value)}
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
          </div>
        ))}
      </div>
    </div>
  );
}
