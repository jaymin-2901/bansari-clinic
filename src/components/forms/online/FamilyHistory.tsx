'use client';

import { useTranslation } from '@/i18n';
import type { FamilyHistoryEntry } from '@/types';
import { FAMILY_RELATIONS } from '@/types';

interface Props {
  familyHistory: FamilyHistoryEntry[];
  setFamilyHistory: React.Dispatch<React.SetStateAction<FamilyHistoryEntry[]>>;
}

const emptyEntry: FamilyHistoryEntry = {
  relation: '', disease: '', ageOfOnset: null,
  causeOfDeath: '', isAlive: true,
};

const relationTranslationKey: Record<string, string> = {
  Father: 'online.familyHistory.father',
  Mother: 'online.familyHistory.mother',
  Brother: 'online.familyHistory.brother',
  Sister: 'online.familyHistory.sister',
  'Grandfather (Paternal)': 'online.familyHistory.grandfather',
  'Grandmother (Paternal)': 'online.familyHistory.grandmother',
  'Grandfather (Maternal)': 'online.familyHistory.grandfather',
  'Grandmother (Maternal)': 'online.familyHistory.grandmother',
};

export default function FamilyHistoryStep({ familyHistory, setFamilyHistory }: Props) {
  const { tr, t } = useTranslation();

  const addMember = () => {
    setFamilyHistory((prev) => [...prev, { ...emptyEntry }]);
  };

  const removeMember = (index: number) => {
    setFamilyHistory((prev) => prev.filter((_, i) => i !== index));
  };

  const updateMember = (index: number, field: keyof FamilyHistoryEntry, value: unknown) => {
    setFamilyHistory((prev) =>
      prev.map((m, i) => (i === index ? { ...m, [field]: value } : m))
    );
  };

  return (
    <div>
      <h2 className="text-xl font-bold text-gray-900 mb-1">{tr('online.familyHistory.title')}</h2>
      <p className="text-sm text-gray-500 mb-6">{tr('online.familyHistory.subtitle')}</p>

      {familyHistory.length === 0 && (
        <div className="text-center py-10 bg-gray-50 rounded-xl border border-dashed border-gray-300">
          <p className="text-gray-500 mb-4">
            {t('No family members added yet', 'હજુ સુધી કોઈ પરિવારના સભ્ય ઉમેર્યા નથી')}
          </p>
          <button onClick={addMember} className="btn-primary text-sm">
            {tr('online.familyHistory.addMember')}
          </button>
        </div>
      )}

      <div className="space-y-4">
        {familyHistory.map((member, index) => (
          <div key={index} className="bg-gray-50 rounded-xl p-5 border border-gray-100">
            <div className="flex items-center justify-between mb-4">
              <span className="text-sm font-semibold text-primary-700 bg-primary-100 px-3 py-1 rounded-full">
                #{index + 1}
              </span>
              <button
                onClick={() => removeMember(index)}
                className="text-red-500 hover:text-red-700 text-sm font-medium flex items-center gap-1"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                {tr('online.familyHistory.removeMember')}
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {/* Relation */}
              <div>
                <label className="label-text">{tr('online.familyHistory.relation')} *</label>
                <select
                  className="input-field"
                  value={member.relation}
                  onChange={(e) => updateMember(index, 'relation', e.target.value)}
                >
                  <option value="">{tr('common.select')}</option>
                  {FAMILY_RELATIONS.map((rel) => (
                    <option key={rel} value={rel}>
                      {tr(relationTranslationKey[rel] || rel)} {rel.includes('Paternal') ? `(${t('Paternal', 'પિતૃ')})` : rel.includes('Maternal') ? `(${t('Maternal', 'માતૃ')})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Disease */}
              <div>
                <label className="label-text">{tr('online.familyHistory.disease')}</label>
                <input
                  type="text"
                  className="input-field"
                  placeholder={tr('online.familyHistory.diseasePlaceholder')}
                  value={member.disease}
                  onChange={(e) => updateMember(index, 'disease', e.target.value)}
                />
              </div>

              {/* Age of Onset */}
              <div>
                <label className="label-text">{tr('online.familyHistory.ageOfOnset')}</label>
                <input
                  type="number"
                  min={0}
                  max={120}
                  className="input-field"
                  value={member.ageOfOnset ?? ''}
                  onChange={(e) => updateMember(index, 'ageOfOnset', e.target.value ? parseInt(e.target.value) : null)}
                />
              </div>

              {/* Is Alive */}
              <div>
                <label className="label-text">{tr('online.familyHistory.isAlive')}</label>
                <div className="flex gap-4 mt-1">
                  <label className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer text-sm ${
                    member.isAlive ? 'border-primary-500 bg-primary-50' : 'border-gray-200'
                  }`}>
                    <input type="radio" className="sr-only" checked={member.isAlive} onChange={() => updateMember(index, 'isAlive', true)} />
                    {tr('common.yes')}
                  </label>
                  <label className={`flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer text-sm ${
                    !member.isAlive ? 'border-primary-500 bg-primary-50' : 'border-gray-200'
                  }`}>
                    <input type="radio" className="sr-only" checked={!member.isAlive} onChange={() => updateMember(index, 'isAlive', false)} />
                    {tr('common.no')}
                  </label>
                </div>
              </div>

              {/* Cause of Death - only if not alive */}
              {!member.isAlive && (
                <div className="md:col-span-2">
                  <label className="label-text">{tr('online.familyHistory.causeOfDeath')}</label>
                  <input
                    type="text"
                    className="input-field"
                    placeholder={tr('online.familyHistory.causeOfDeathPlaceholder')}
                    value={member.causeOfDeath}
                    onChange={(e) => updateMember(index, 'causeOfDeath', e.target.value)}
                  />
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {familyHistory.length > 0 && (
        <button
          onClick={addMember}
          className="mt-4 w-full flex items-center justify-center gap-2 py-3 border-2 border-dashed border-primary-300 rounded-xl text-primary-600 hover:bg-primary-50 transition-all font-medium"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          {tr('online.familyHistory.addMember')}
        </button>
      )}
    </div>
  );
}
