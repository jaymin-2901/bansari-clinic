'use client';

import { useTranslation } from '@/i18n';
import type { MentalProfileData } from '@/types';
import { PERSONALITY_TRAITS } from '@/types';

interface Props {
  data: MentalProfileData;
  setData: React.Dispatch<React.SetStateAction<MentalProfileData>>;
}

const traitTranslations: Record<string, string> = {
  Introvert: 'online.mentalProfile.introvert',
  Extrovert: 'online.mentalProfile.extrovert',
  Optimistic: 'online.mentalProfile.optimistic',
  Pessimistic: 'online.mentalProfile.pessimistic',
  Perfectionist: 'online.mentalProfile.perfectionist',
  Lazy: 'online.mentalProfile.lazy',
  Anxious: 'online.mentalProfile.anxious',
  Calm: 'online.mentalProfile.calm',
  Sensitive: 'online.mentalProfile.sensitive',
  Aggressive: 'online.mentalProfile.aggressive',
  Stubborn: 'online.mentalProfile.stubborn',
  Flexible: 'online.mentalProfile.flexible',
  Jealous: 'online.mentalProfile.jealous',
  Sympathetic: 'online.mentalProfile.sympathetic',
  Ambitious: 'online.mentalProfile.ambitious',
  Indecisive: 'online.mentalProfile.indecisive',
};

export default function MentalProfileStep({ data, setData }: Props) {
  const { tr } = useTranslation();

  const handleChange = (field: keyof MentalProfileData, value: string) => {
    setData((prev) => ({ ...prev, [field]: value }));
  };

  const toggleTrait = (trait: string) => {
    setData((prev) => {
      const traits = prev.personalityTraits;
      return {
        ...prev,
        personalityTraits: traits.includes(trait)
          ? traits.filter((t) => t !== trait)
          : [...traits, trait],
      };
    });
  };

  const textareaFields: { field: keyof MentalProfileData; labelKey: string; placeholderKey: string }[] = [
    { field: 'nature', labelKey: 'online.mentalProfile.nature', placeholderKey: 'online.mentalProfile.naturePlaceholder' },
    { field: 'lifeGoal', labelKey: 'online.mentalProfile.lifeGoal', placeholderKey: 'online.mentalProfile.lifeGoalPlaceholder' },
    { field: 'anxietyTriggers', labelKey: 'online.mentalProfile.anxietyTriggers', placeholderKey: 'online.mentalProfile.anxietyPlaceholder' },
    { field: 'fears', labelKey: 'online.mentalProfile.fears', placeholderKey: 'online.mentalProfile.fearsPlaceholder' },
    { field: 'angerExpression', labelKey: 'online.mentalProfile.angerExpression', placeholderKey: 'online.mentalProfile.angerPlaceholder' },
    { field: 'sadnessTriggers', labelKey: 'online.mentalProfile.sadnessTriggers', placeholderKey: 'online.mentalProfile.sadnessPlaceholder' },
    { field: 'memory', labelKey: 'online.mentalProfile.memory', placeholderKey: 'online.mentalProfile.memoryPlaceholder' },
    { field: 'hobbies', labelKey: 'online.mentalProfile.hobbies', placeholderKey: 'online.mentalProfile.hobbiesPlaceholder' },
    { field: 'saddestEvent', labelKey: 'online.mentalProfile.saddestEvent', placeholderKey: 'online.mentalProfile.saddestPlaceholder' },
    { field: 'happiestEvent', labelKey: 'online.mentalProfile.happiestEvent', placeholderKey: 'online.mentalProfile.happiestPlaceholder' },
    { field: 'unforgettableIncident', labelKey: 'online.mentalProfile.unforgettableIncident', placeholderKey: 'online.mentalProfile.unforgettablePlaceholder' },
    { field: 'revengeTendency', labelKey: 'online.mentalProfile.revengeTendency', placeholderKey: 'online.mentalProfile.revengePlaceholder' },
    { field: 'cryingNature', labelKey: 'online.mentalProfile.cryingNature', placeholderKey: 'online.mentalProfile.cryingPlaceholder' },
    { field: 'aloneVsCompany', labelKey: 'online.mentalProfile.aloneVsCompany', placeholderKey: 'online.mentalProfile.alonePlaceholder' },
    { field: 'relationshipImpact', labelKey: 'online.mentalProfile.relationshipImpact', placeholderKey: 'online.mentalProfile.relationshipPlaceholder' },
    { field: 'mentalEffectOfDisease', labelKey: 'online.mentalProfile.mentalEffectOfDisease', placeholderKey: 'online.mentalProfile.mentalEffectPlaceholder' },
    { field: 'socialComfort', labelKey: 'online.mentalProfile.socialComfort', placeholderKey: 'online.mentalProfile.socialPlaceholder' },
  ];

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.mentalProfile.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.mentalProfile.subtitle')}</p>

      {/* Text Areas */}
      <div className="space-y-5">
        {textareaFields.map(({ field, labelKey, placeholderKey }) => (
          <div key={field}>
            <label className="label-text">{tr(labelKey)}</label>
            <textarea
              rows={2}
              className="input-field"
              placeholder={tr(placeholderKey)}
              value={(data[field] as string) || ''}
              onChange={(e) => handleChange(field, e.target.value)}
            />
          </div>
        ))}
      </div>

      {/* Personality Traits Checklist */}
      <div className="mt-8 bg-gray-50 rounded-xl p-5 border border-gray-100">
        <h3 className="text-lg font-semibold text-gray-800 mb-4">
          🧠 {tr('online.mentalProfile.personalityTraits')}
        </h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {PERSONALITY_TRAITS.map((trait) => {
            const isSelected = data.personalityTraits.includes(trait);
            return (
              <label
                key={trait}
                className={`flex items-center gap-2 px-3 py-2.5 rounded-lg border-2 cursor-pointer transition-all text-sm ${
                  isSelected
                    ? 'border-primary-500 bg-primary-50 text-primary-700'
                    : 'border-gray-200 bg-white hover:border-gray-300'
                }`}
              >
                <input
                  type="checkbox"
                  checked={isSelected}
                  onChange={() => toggleTrait(trait)}
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
                {tr(traitTranslations[trait] || trait)}
              </label>
            );
          })}
        </div>
      </div>
    </div>
  );
}
